<?php
namespace Auxilium\TwigHandling;

use Auxilium\EncodingTools;
use Auxilium\Exceptions\DatabaseConnectionException;
use Auxilium\MicroTemplate;
use Auxilium\Session;

class PageBuilder {
    private static $instance = null;
    private $twigVariables;
    private $template;

    public function setDefaultVariables() {
        $this->twigVariables = [
            "style_options" => [],
            "head_asset_options" => [],
            "selected_lang" => "en",
            
            "INSTANCE_BRANDING_LOGO" => INSTANCE_BRANDING_LOGO,
            "INSTANCE_BRANDING_LOGO_CONTRAST_BRAND_COLOR" => INSTANCE_BRANDING_LOGO_CONTRAST_BRAND_COLOR,
            "INSTANCE_BRANDING_NAME" => INSTANCE_BRANDING_NAME,
            "INSTANCE_BRANDING_DOMAIN_NAME" => INSTANCE_BRANDING_DOMAIN_NAME,
            "INSTANCE_DOMAIN_NAME" => INSTANCE_DOMAIN_NAME,

            "INSTANCE_INFO_MAIN_EMAIL" => INSTANCE_INFO_MAIN_EMAIL,
            "INSTANCE_INFO_MAIN_PHONE" => INSTANCE_INFO_MAIN_PHONE,
            "INSTANCE_INFO_MAIN_PHONE_OPENING_HOURS" => INSTANCE_INFO_MAIN_PHONE_OPENING_HOURS,
            "INSTANCE_INFO_MAIN_TEXT" => INSTANCE_INFO_MAIN_TEXT,
            "INSTANCE_INFO_MAIN_TEXT_OPENING_HOURS" => INSTANCE_INFO_MAIN_TEXT_OPENING_HOURS,

            "INSTANCE_INFO_MAINTAINER_NAME" => INSTANCE_INFO_MAINTAINER_NAME,
            "INSTANCE_INFO_MAINTAINER_EMAIL" => INSTANCE_INFO_MAINTAINER_EMAIL,
            "INSTANCE_INFO_GENERAL_ENQUIRIES_CONTACT_NAME" => INSTANCE_INFO_GENERAL_ENQUIRIES_CONTACT_NAME,
            "INSTANCE_INFO_GENERAL_ENQUIRIES_CONTACT_EMAIL" => INSTANCE_INFO_GENERAL_ENQUIRIES_CONTACT_EMAIL,
            
            "INSTANCE_UUID" => INSTANCE_UUID,
        ];
        
        // Serve the correct language *if* the cookie is set
        if(isset($_COOKIE["lang"])) {
            switch ($_COOKIE["lang"]) {
                case "cy":
                    $this->twigVariables["selected_lang"] = "cy";
                    break;
                case "zh": // For testing only, this language pack is shoddy at best
                    $this->twigVariables["selected_lang"] = "zh";
                    break;
                case "ar": // For testing only, this language pack is shoddy at best
                    $this->twigVariables["selected_lang"] = "ar";
                    break;
                case "en":
                default:
                    $this->twigVariables["selected_lang"] = "en";
                    break;
            }
        }

        // Grab style options if present
        if(isset($_COOKIE["style"])) {
            if(isset($_COOKIE["style"])) {
                $this->twigVariables["head_asset_options"] = explode(" ", $_COOKIE["style"]);
            }
        }
    }
    
    private function __construct() {
        $this->setDefaultVariables();
        try {
            $this->twigVariables["current_user"] = Session::get_current()->getUser();
        } catch (\Exception $e) {
            $this->twigVariables["current_user"] = null;
        }
    }
    
    public function getCurrentLanguage() {
        return $this->twigVariables["selected_lang"];
    }
    
    public function overrideCurrentLanguage($lang) {
        switch (strtolower($lang)) {
            case "cy":
                $this->twigVariables["selected_lang"] = "cy";
                break;
            case "zh": // For testing only, this language pack is shoddy at best
                $this->twigVariables["selected_lang"] = "zh";
                break;
            case "ar": // For testing only, this language pack is shoddy at best
                $this->twigVariables["selected_lang"] = "ar";
                break;
            case "en":
            default:
                $this->twigVariables["selected_lang"] = "en";
                break;
        }
    }
    
    public static function get_instance() {
        if (self::$instance == null) {
            self::$instance = new PageBuilder();
        }
        
        return self::$instance;
    }
    
    /*  No longer used, we ask for this on login and sign-up
    public function requireCookieConsent() {
        if (!isset($_COOKIE["cookie-consent"])) {
            $this->setTemplate("cookie-consent-required");
            $this->render();
            exit();
        }
        if ($_COOKIE["cookie-consent"] != "true") {
            $this->setTemplate("cookie-consent-required");
            $this->render();
            exit();
        }
        return $this;
    }
    */
    
    public function requireLogin() {
        if (Session::get_current()->sessionAuthenticated()) {
            /*
            if (!file_exists(LOCAL_EPHEMERAL_CREDENTIAL_STORE."root-encryption-key.json")) {
                if ($_SERVER["REQUEST_URI"] != "/unlock") {
                    header("Location: /unlock");
                    exit();
                }
            }
            */
        } else {
            header("Location: /login");
            exit();
        }
        return $this;
    }
    
    public function addResourceFlag($resource) {
        array_push($this->twigVariables["head_asset_options"], $resource);
        return $this;
    }
    
    public function isResourceFlagSet($resource) {
        return in_array($resource, $this->twigVariables["head_asset_options"]);
    }
    
    public function setTemplate($template) {
        if (str_ends_with($template, ".html")) {
            $this->template = $template;
        } else {
            $this->template = $template.".html";
        }
        return $this;
    }
    
    public function setResponseCode($responseCode = 200) {
        http_response_code($responseCode);
        return $this;
    }
    
    public function setVariable($key, $value = null) {
        $this->twigVariables[$key] = $value;
        return $this;
    }
    
    public function getVariable($key) {
        return $this->twigVariables[$key];
    }
    
    public function render() {
        $twigLoader = new \Twig\Loader\FilesystemLoader(WEB_ROOT_DIRECTORY."/templates");
        $twig = new \Twig\Environment($twigLoader, [
            "cache" => false,
        ]);
        $filter = new \Twig\TwigFilter("uiprop", function ($string) {
            return MicroTemplate::data_type_to_human_name($string, $this->twigVariables["selected_lang"]);
        });
        $twig->addFilter($filter);
        $filter = new \Twig\TwigFilter("uitxt", function ($string) {
            return MicroTemplate::ui_text($string, $this->twigVariables["selected_lang"]);
        });
        $twig->addFilter($filter);
        $filter = new \Twig\TwigFilter("uitxtrt", function ($string) {
            return MicroTemplate::ui_text_root($string, $this->twigVariables["selected_lang"], $this->twigVariables);
        });
        $twig->addFilter($filter);
        $filter = new \Twig\TwigFilter("ndtitle", function ($string) {
            $pcs = mb_split(" ", $string);
            foreach ($pcs as &$pc) {
                $pc = mb_strtoupper(mb_substr($pc, 0, 1)) . mb_substr($pc, 1);
            }
            return implode(" ", $pcs);
        });
        $twig->addFilter($filter);
        $filter = new \Twig\TwigFilter("ndsentence", function ($string) {
            return mb_strtoupper(mb_substr($string, 0, 1)) . mb_substr($string, 1);
        });
        $twig->addFilter($filter);
        $filter = new \Twig\TwigFilter("uihdg", function ($string) {
            return MicroTemplate::ui_heading($string, $this->twigVariables["selected_lang"]);
        });
        $twig->addFilter($filter);
        $filter = new \Twig\TwigFilter("unpack_string", function ($string) {
            return MicroTemplate::from_packed_template($string, $this->twigVariables["selected_lang"]);
        });
        $twig->addFilter($filter);
        $filter = new \Twig\TwigFilter("b64_url_safe", function ($string) {
            return EncodingTools::base64_encode_url_safe($string);
        });
        $twig->addFilter($filter);
        $filter = new \Twig\TwigFilter("un_b64_url_safe", function ($string) {
            return EncodingTools::base64_decode_url_safe($string);
        });
        $twig->addFilter($filter);
        $filter = new \Twig\TwigFilter("human_filesize", function ($string) {
            $size = intval($string);
            if ($size <= 256) {
                return $size." B";
            } elseif ($size <= 256 * pow(1024, 1)) {
                return substr($size / pow(1024, 1), 0, 3)." KiB";
            } elseif ($size <= 256 * pow(1024, 2)) {
                return substr($size / pow(1024, 2), 0, 3)." MiB";
            } elseif ($size <= 256 * pow(1024, 3)) {
                return substr($size / pow(1024, 3), 0, 3)." GiB";
            } else {
                return substr($size / pow(1024, 4), 0, 3)." TiB";
            }
        });
        $twig->addFilter($filter);
        $filter = new \Twig\TwigFilter("dnd", function ($path) {
            $rid = openssl_random_pseudo_bytes(16);
            $rid = bin2hex($rid);
            return "<span id=\"dynamic_inline_node_element_$rid\"></span><script>document.getElementById(\"dynamic_inline_node_element_$rid\").appendChild((new InlineNodeView(\"$path\")).render())</script>";
        }); // Macro to insert a dynamically loaded node view
        $twig->addFilter($filter);
        $function = new \Twig\TwigFunction("proplist", function ($path, $hidden_props = [], $compact = false, $sort = null, $recursive = false) {
            $rid = openssl_random_pseudo_bytes(16);
            $rid = bin2hex($rid);
            return "<span id=\"dynamic_property_list_element_$rid\"></span><script>document.getElementById(\"dynamic_property_list_element_$rid\").appendChild((new PropertyList(\"$path\", ".($compact?"true":"false").", ".json_encode($hidden_props).", ".json_encode($sort).", ".($recursive?"true":"false").")).render())</script>";
        }); // Macro to insert a dynamically loaded property list
        $twig->addFunction($function);
        $function = new \Twig\TwigFunction("ui_template", function ($path, $template_variables = []) {
            return strval(new MicroTemplate("ui_templates/".$path, $this->twigVariables["selected_lang"], $template_variables, false));
        });
        $twig->addFunction($function);
        $this->twigVariables["current_uri"] = $_SERVER["REQUEST_URI"];
        try {
            echo $twig->render($this->template, $this->twigVariables);
            exit();
        } catch (\Twig\Error\RuntimeError $e) {
            $e = $e->getPrevious();
            if ($e instanceof DatabaseConnectionException) {
                http_response_code(500);
                $this->setDefaultVariables();
                $this->twigVariables["technical_details"] = "Exception Type:\n    ".get_class($e);
                $this->twigVariables["technical_details"] .= "\nMessage:\n    ".$e->getMessage();
                $this->twigVariables["technical_details"] .= "\nURI:\n    ".$_SERVER["HTTP_HOST"].$_SERVER["REQUEST_URI"];
                echo $twig->render("internal-system-error.html", $this->twigVariables);
                exit();
            } else {
                echo "<pre>";
                echo get_class($e)."\n";
                echo htmlentities($e->getMessage())."\n";
                echo htmlentities(json_encode($e->getTrace(), JSON_PRETTY_PRINT))."\n";
                echo htmlentities(json_encode(get_class_methods($e), JSON_PRETTY_PRINT));
                echo "</pre>";
                exit();
            }
        }
    }
}
