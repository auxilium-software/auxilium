<?php
namespace Auxilium;

class URLMetadata {
    private $metadata = [];
    private $jwtValidated = false;
    private $jwtMatchedUser = false;
    
    public static function from_metadata(array $metadata) {
        $mdo = new URLMetadata();
        $mdo->metadata = $metadata;
        return $mdo;
    }
    
    public static function from_jwt(string $jwt) {
        $mdo = new URLMetadata();
        
        $components = explode(".", $jwt);
        
        if (count($components) == 3) {
            $header = json_decode(\Auxilium\EncodingTools::base64_decode_url_safe($components[0]), true);
            $payload = json_decode(\Auxilium\EncodingTools::base64_decode_url_safe($components[1]), true);
            
            if (!is_array($header) || !is_array($payload)) {
                return $mdo;
            }
            
            //$userId = \auxilium\EncodingTools::base64_encode_url_safe(URLMetadata::crush_uuid(Session::get_current()->getUser()->getId()));
            
            $valid = true;
            
            if ($header == false) { $valid = false; }
            if ($payload == false) { $valid = false; }
            
            $matchHeader = [
                "alg" => "HS256",
                "typ" => "JWT"
            ];
            if ($matchHeader["alg"] != $header["alg"]) { $valid = false; }
            if ($matchHeader["typ"] != $header["typ"]) { $valid = false; }
            
            if ($valid) {
                $valid = hash_hmac("sha256", $components[0].".".$components[1], base64_decode(INSTANCE_CREDENTIAL_URL_METADATA_JWT_SECRET), true) == \Auxilium\EncodingTools::base64_decode_url_safe($components[2]);
            }
            
            if ($valid) {
                if (isset($payload["sub"])) { // Restriction on who this is valid for
                    $mdo->jwtMatchedUser = ($payload["sub"] == \Auxilium\EncodingTools::base64_encode_url_safe(URLMetadata::crush_uuid(Session::get_current()->getUser()->getId())));
                }
            }
            
            if (isset($payload["mda"])) {
                if (isset($payload["mda"]["tn"])) {
                    $payload["mda"]["tn"] = \Auxilium\EncodingTools::base64_decode_url_safe($payload["mda"]["tn"]);
                }
                if (isset($payload["mda"]["rc"])) {
                    $payload["mda"]["rc"] = \Auxilium\EncodingTools::base64_decode_url_safe($payload["mda"]["rc"]);
                }
                $mdo->metadata = $payload["mda"];
            }
            $mdo->jwtValidated = $valid;
        }
        
        return $mdo;
    }
    
    public function isValid() {
        return $this->jwtValidated;
    }
    
    public function isSecureMatch() {
        return $this->jwtValidated && $this->jwtMatchedUser;
    }

    public function setPath(string $path, \Auxilium\Node $node = null) {
        $this->metadata["rp"] = rtrim(ltrim($path, "/"), "/"); // Relative Path
        if ($node == null) { $node = \Auxilium\Node::from_path($this->metadata["rp"]); }
        $this->metadata["tn"] = $node;
        if ($this->metadata["tn"] != null) {
            $this->metadata["tn"] = URLMetadata::crush_uuid($this->metadata["tn"]->getId());
        }
        $this->metadata["rc"] = URLMetadata::standard_metadata_checksum($this->metadata["rp"]);
        return $this;
    }
    
    public function checkPath(string $path) {
        return (isset($this->metadata["rc"])) ? (URLMetadata::standard_metadata_checksum($path) == $this->metadata["rc"]) : false;
    }
    
    public function checkNode(?Node $node) {
        if ($node == null) {
            return false;
        }
        return isset($this->metadata["tn"]) ? (URLMetadata::crush_uuid($node->getId()) == $this->metadata["tn"]) : true; // If there isn't a value set match anything
    }
    
    public function getPath() {
        if (isset($this->metadata["rp"])) {
            return $this->metadata["rp"];
        }
        return null;
    }
    
    public function pushToReturnStack(string $url) {
        if (!isset($this->metadata["rts"])) {
            $this->metadata["rts"] = [];
        }
        array_push($this->metadata["rts"], $url);
        return $this;
    }
    
    public function pushCurrentToReturnStack() {
        $url = $_SERVER["REQUEST_URI"];
        $pos = strpos($url, "?");
        $url = substr($url, 0, $pos); // We almost never want the get parameter since we put the previous JWT here
        if (!isset($this->metadata["rts"])) {
            $this->metadata["rts"] = [];
        }
        array_push($this->metadata["rts"], $url);
        return $this;
    }
    
    public function popFromReturnStack() {
        if (!isset($this->metadata["rts"])) {
            return null;
        }
        $cstk = array_pop($this->metadata["rts"]);
        $estk = end($this->metadata["rts"]);
        if ($cstk == null) {
            return null;
        }
        while ($cstk == $estk) { // Clear the stack until we have something different
            $cstk = array_pop($this->metadata["rts"]);
        }
        return $cstk;
    }
    
    public function peekReturnStack() {
        if (!isset($this->metadata["rts"])) {
            return null;
        }
        return end($this->metadata["rts"]);
    }
    
    public function clearReturnStack() {
        if (isset($this->metadata["rts"])) {
            unset($this->metadata["rts"]);
        }
        return $this;
    }
    
    public function setProperty(string $key, ?string $property) {
        if ($property == null) {
            if (isset($this->metadata[$key])) {
                unset($this->metadata[$key]);
            }
        } else {
            $this->metadata[$key] = $property;
        }
        return $this;
    }
    
    public function getProperty(string $key) {
        if (isset($this->metadata[$key])) {
            return $this->metadata[$key];
        }
        return null;
    }
    
    public static function standard_metadata_checksum(string $input) {
        return substr(hash("sha256", $input, true), 0, 8);
    }
    
    public static function crush_uuid(string $input) {
        return hex2bin(str_replace("-", "", $input));
    }
    
    public static function expand_crushed_uuid(string $input) {
        $uuid_bin = bin2hex($input);
        return substr($uuid_bin, 0, 8)."-".substr($uuid_bin, 8, 4)."-".substr($uuid_bin, 12, 4)."-".substr($uuid_bin, 16, 4)."-".substr($uuid_bin, 20, 32);
    }
    
    public function parent() {
        $parent = $this->metadata;
        $pthcps = explode("/", $parent["rp"]);
        array_pop($pthcps);
        $parent["rp"] = rtrim(ltrim(implode("/", $pthcps), "/"), "/");
        $parent["tn"] = \Auxilium\Node::from_path($parent["rp"]);
        if ($parent["tn"] != null) {
            $parent["tn"] = URLMetadata::crush_uuid($parent["tn"]->getId());
        }
        $parent["rc"] = URLMetadata::standard_metadata_checksum($parent["rp"]);
        $mdo = URLMetadata::from_metadata($parent);
        return $mdo;
    }
    
    public function child(string $child) {
        $childmd = $this->metadata;
        $pthcps = explode("/", $childmd["rp"]);
        array_push($pthcps, $child);
        $childmd["rp"] = rtrim(ltrim(implode("/", $pthcps), "/"), "/");
        $childmd["tn"] = \Auxilium\Node::from_path($childmd["rp"]);
        if ($childmd["tn"] != null) {
            $childmd["tn"] = URLMetadata::crush_uuid($childmd["tn"]->getId());
        }
        $childmd["rc"] = URLMetadata::standard_metadata_checksum($childmd["rp"]);
        $mdo = URLMetadata::from_metadata($childmd);
        return $mdo;
    }
    
    public function copy() {
        return clone $this;
    }
    
    public function __toString() {
        $md = $this->metadata;
        if (isset($md["rp"])) {
            unset($md["rp"]);
        }
        if (isset($md["tn"])) {
            $md["tn"] = \Auxilium\EncodingTools::base64_encode_url_safe($md["tn"]);
        }
        if (isset($md["rc"])) {
            $md["rc"] = \Auxilium\EncodingTools::base64_encode_url_safe($md["rc"]);
        }
    
        $subject = null;
        if (Session::get_current()->getUser() != null) {
            $subject = \Auxilium\EncodingTools::base64_encode_url_safe(URLMetadata::crush_uuid(Session::get_current()->getUser()->getId()));
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
        $header = \Auxilium\EncodingTools::base64_encode_url_safe(json_encode($header));
        $payload = \Auxilium\EncodingTools::base64_encode_url_safe(json_encode($payload));
        $jwt = $header.".".$payload.".". \Auxilium\EncodingTools::base64_encode_url_safe(hash_hmac("sha256", $header.".".$payload, base64_decode(INSTANCE_CREDENTIAL_URL_METADATA_JWT_SECRET), true));

        $this->jwtValidated = true;
        $this->jwtMatchedUser = true;
        return $jwt;
    }
}
?>
