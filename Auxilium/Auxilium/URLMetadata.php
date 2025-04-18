<?php

namespace Auxilium;

use Auxilium\DatabaseInteractions\Deegraph\DeegraphNode;
use Auxilium\SessionHandling\Session;
use Auxilium\Utilities\EncodingTools;

/**
 * Represents metadata that can be associated with a URL and includes
 * methods for handling JSON Web Tokens (JWTs) and metadata operations.
 */
class URLMetadata
{
    private $metadata = [];
    private $jwtValidated = false;
    private $jwtMatchedUser = false;

    /**
     * Parses a JWT (JSON Web Token) and extracts its metadata if the token is valid.
     *
     * This method attempts to decode a JWT, validate its structure and headers,
     * and ensure its signature is authenticated using the application's secret key.
     * If the JWT is valid and contains user and metadata information, these are
     * extracted and populated into a URLMetadata object.
     *
     * @param string $jwt The JSON Web Token to process and validate.
     * @return URLMetadata A URLMetadata object that contains the extracted
     *                      metadata and validation status from the given JWT.
     */
    public static function from_jwt(string $jwt): URLMetadata
    {
        $mdo = new URLMetadata();

        $components = explode(".", $jwt);

        if(count($components) === 3)
        {
            $header = json_decode(EncodingTools::Base64DecodeURLSafe($components[0]), true, 512, JSON_THROW_ON_ERROR);
            $payload = json_decode(EncodingTools::Base64DecodeURLSafe($components[1]), true, 512, JSON_THROW_ON_ERROR);

            if(!is_array($header) || !is_array($payload))
            {
                return $mdo;
            }

            //$userId = \Auxilium\EncodingTools::base64_encode_url_safe(URLMetadata::crush_uuid(Session::get_current()->getUser()->GetNodeID()));

            $valid = true;

            if(!$header)
            {
                $valid = false;
            }
            if(!$payload)
            {
                $valid = false;
            }

            $matchHeader = [
                "alg" => "HS256",
                "typ" => "JWT"
            ];
            if($matchHeader["alg"] !== $header["alg"])
            {
                $valid = false;
            }
            if($matchHeader["typ"] !== $header["typ"])
            {
                $valid = false;
            }

            if($valid)
            {
                $valid = hash_hmac(
                        algo  : "sha256",
                        data  : $components[0] . "." . $components[1],
                        key   : base64_decode(INSTANCE_CREDENTIAL_URL_METADATA_JWT_SECRET),
                        binary: true,
                    ) === EncodingTools::Base64DecodeURLSafe($components[2]);
            }

            if($valid)
            {
                if(isset($payload["sub"]))
                { // Restriction on who this is valid for
                    $mdo->jwtMatchedUser = ($payload["sub"] == EncodingTools::Base64EncodeURLSafe(URLMetadata::crush_uuid(Session::get_current()->getUser()->getId())));
                }
            }

            if(isset($payload["mda"]))
            {
                if(isset($payload["mda"]["tn"]))
                {
                    $payload["mda"]["tn"] = EncodingTools::Base64DecodeURLSafe($payload["mda"]["tn"]);
                }
                if(isset($payload["mda"]["rc"]))
                {
                    $payload["mda"]["rc"] = EncodingTools::Base64DecodeURLSafe($payload["mda"]["rc"]);
                }
                $mdo->metadata = $payload["mda"];
            }
            $mdo->jwtValidated = $valid;
        }

        return $mdo;
    }

    /**
     * Converts a UUID string into a binary representation.
     *
     * @param string $input The UUID string to be converted, expected in standard hexadecimal format with dashes.
     *
     * @return false|string Returns the binary string representation of the UUID on success, or false on failure.
     */
    public static function crush_uuid(string $input): false|string
    {
        return hex2bin(str_replace("-", "", $input));
    }

    /**
     * Expands a crushed UUID string into its full, hyphenated form.
     *
     * @param string $input The crushed UUID input as a binary string.
     *
     * @return string The expanded UUID in the standard format.
     */
    public static function expand_crushed_uuid(string $input): string
    {
        $uuid_bin = bin2hex($input);
        return substr($uuid_bin, 0, 8) . "-" . substr($uuid_bin, 8, 4) . "-" . substr($uuid_bin, 12, 4) . "-" . substr($uuid_bin, 16, 4) . "-" . substr($uuid_bin, 20, 32);
    }

    public function isValid(): bool
    {
        return $this->jwtValidated;
    }

    public function isSecureMatch(): bool
    {
        return $this->jwtValidated && $this->jwtMatchedUser;
    }

    public function setPath(string $path, DeegraphNode $node = null): static
    {
        $this->metadata["rp"] = rtrim(ltrim($path, "/"), "/"); // Relative Path
        if($node === null)
        {
            $node = DeegraphNode::from_path($this->metadata["rp"]);
        }
        $this->metadata["tn"] = $node;
        if($this->metadata["tn"] !== null)
        {
            $this->metadata["tn"] = self::crush_uuid($this->metadata["tn"]->getId());
        }
        $this->metadata["rc"] = self::standard_metadata_checksum($this->metadata["rp"]);
        return $this;
    }

    public static function standard_metadata_checksum(string $input): string
    {
        return substr(hash("sha256", $input, true), 0, 8);
    }

    public function checkPath(string $path): bool
    {
        return (isset($this->metadata["rc"])) ? (URLMetadata::standard_metadata_checksum($path) == $this->metadata["rc"]) : false;
    }

    public function checkNode(?DeegraphNode $node): bool
    {
        if($node === null)
        {
            return false;
        }
        return isset($this->metadata["tn"]) ? (URLMetadata::crush_uuid($node->getId()) == $this->metadata["tn"]) : true; // If there isn't a value set match anything
    }

    public function getPath()
    {
        if(isset($this->metadata["rp"]))
        {
            return $this->metadata["rp"];
        }
        return null;
    }

    public function pushToReturnStack(string $url)
    {
        if(!isset($this->metadata["rts"]))
        {
            $this->metadata["rts"] = [];
        }
        $this->metadata["rts"][] = $url;
        return $this;
    }

    public function pushCurrentToReturnStack(): static
    {
        $url = $_SERVER["REQUEST_URI"];
        $pos = strpos($url, "?");
        $url = substr($url, 0, $pos); // We almost never want the get parameter since we put the previous JWT here
        if(!isset($this->metadata["rts"]))
        {
            $this->metadata["rts"] = [];
        }
        $this->metadata["rts"][] = $url;
        return $this;
    }

    public function popFromReturnStack()
    {
        if(!isset($this->metadata["rts"]))
        {
            return null;
        }
        $cstk = array_pop($this->metadata["rts"]);
        $estk = end($this->metadata["rts"]);
        if($cstk == null)
        {
            return null;
        }
        while($cstk === $estk)
        { // Clear the stack until we have something different
            $cstk = array_pop($this->metadata["rts"]);
        }
        return $cstk;
    }

    public function peekReturnStack()
    {
        if(!isset($this->metadata["rts"]))
        {
            return null;
        }
        return end($this->metadata["rts"]);
    }

    public function clearReturnStack()
    {
        if(isset($this->metadata["rts"]))
        {
            unset($this->metadata["rts"]);
        }
        return $this;
    }

    public function setProperty(string $key, ?string $property)
    {
        if($property === null)
        {
            if(isset($this->metadata[$key]))
            {
                unset($this->metadata[$key]);
            }
        }
        else
        {
            $this->metadata[$key] = $property;
        }
        return $this;
    }

    public function getProperty(string $key)
    {
        if(isset($this->metadata[$key]))
        {
            return $this->metadata[$key];
        }
        return null;
    }

    public function parent(): URLMetadata
    {
        $parent = $this->metadata;
        $pthcps = explode("/", $parent["rp"]);
        array_pop($pthcps);
        $parent["rp"] = rtrim(ltrim(implode("/", $pthcps), "/"), "/");
        $parent["tn"] = DeegraphNode::from_path($parent["rp"]);
        if($parent["tn"] != null)
        {
            $parent["tn"] = self::crush_uuid($parent["tn"]->getId());
        }
        $parent["rc"] = self::standard_metadata_checksum($parent["rp"]);
        $mdo = self::from_metadata($parent);
        return $mdo;
    }

    public static function from_metadata(array $metadata)
    {
        $mdo = new URLMetadata();
        $mdo->metadata = $metadata;
        return $mdo;
    }

    public function child(string $child)
    {
        $childmd = $this->metadata;
        $pthcps = explode("/", $childmd["rp"]);
        $pthcps[] = $child;
        $childmd["rp"] = rtrim(ltrim(implode("/", $pthcps), "/"), "/");
        $childmd["tn"] = DeegraphNode::from_path($childmd["rp"]);
        if($childmd["tn"] != null)
        {
            $childmd["tn"] = self::crush_uuid($childmd["tn"]->getId());
        }
        $childmd["rc"] = self::standard_metadata_checksum($childmd["rp"]);
        $mdo = self::from_metadata($childmd);
        return $mdo;
    }

    public function copy()
    {
        return clone $this;
    }

    public function __toString()
    {
        $md = $this->metadata;
        if(isset($md["rp"]))
        {
            unset($md["rp"]);
        }
        if(isset($md["tn"]))
        {
            $md["tn"] = EncodingTools::Base64EncodeURLSafe($md["tn"]);
        }
        if(isset($md["rc"]))
        {
            $md["rc"] = EncodingTools::Base64EncodeURLSafe($md["rc"]);
        }

        $subject = null;
        if(Session::get_current()?->getUser() !== null)
        {
            $subject = EncodingTools::Base64EncodeURLSafe(self::crush_uuid(Session::get_current()?->getUser()?->getId()));
        }
        $header = [
            "alg" => "HS256",
            "typ" => "JWT"
        ];
        $payload = [
            "sub" => $subject,
            "iat" => time(),
            "mda" => $md
        ];
        // (new \DateTime("now", new \DateTimeZone("UTC")))->format("Y-m-d\\TH:i:s\\Z")
        $header = EncodingTools::Base64EncodeURLSafe(json_encode($header, JSON_THROW_ON_ERROR));
        $payload = EncodingTools::Base64EncodeURLSafe(json_encode($payload, JSON_THROW_ON_ERROR));
        $jwt = $header . "." . $payload . "." . EncodingTools::Base64EncodeURLSafe(hash_hmac("sha256", $header . "." . $payload, base64_decode(INSTANCE_CREDENTIAL_URL_METADATA_JWT_SECRET), true));

        $this->jwtValidated = true;
        $this->jwtMatchedUser = true;
        return $jwt;
    }
}
