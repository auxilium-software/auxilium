<?php

namespace Auxilium\TwigHandling;

use Auxilium\SessionHandling\Session;
use Auxilium\TwigHandling\Extensions\CommonFilters;
use Auxilium\TwigHandling\Extensions\CommonFunctions;
use Auxilium\Utilities\NavigationUtilities;
use Exception;
use Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\Loader\FilesystemLoader;

class PageBuilder
{
    private static $instance = null;
    private $twigVariables;
    private $template;

    private function __construct()
    {
        $this->setDefaultVariables();
        try
        {
            $this->twigVariables["current_user"] = Session::get_current()->getUser();
        }
        catch(Exception $e)
        {
            $this->twigVariables["current_user"] = null;
        }
    }

    public function setDefaultVariables()
    {
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
        if(isset($_COOKIE["lang"]))
        {
            switch($_COOKIE["lang"])
            {
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
        if(isset($_COOKIE["style"]))
        {
            if(isset($_COOKIE["style"]))
            {
                $this->twigVariables["head_asset_options"] = explode(" ", $_COOKIE["style"]);
            }
        }
    }

    public static function get_instance()
    {
        if(self::$instance == null)
        {
            self::$instance = new PageBuilder();
        }

        return self::$instance;
    }

    public function getCurrentLanguage()
    {
        return $this->twigVariables["selected_lang"];
    }

    public function overrideCurrentLanguage($lang)
    {
        switch(strtolower($lang))
        {
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

    public function requireLogin()
    {
        if(Session::get_current()->sessionAuthenticated())
        {
            /*
            if (!file_exists(LOCAL_EPHEMERAL_CREDENTIAL_STORE."root-encryption-key.json")) {
                if ($_SERVER["REQUEST_URI"] != "/unlock") {
                    \Auxilium\Utilities\NavigationUtilities::Redirect(target: "/unlock");
                    exit();
                }
            }
            */
        }
        else
        {
            NavigationUtilities::Redirect(target: "/login");
            exit();
        }
        return $this;
    }

    public function addResourceFlag($resource)
    {
        array_push($this->twigVariables["head_asset_options"], $resource);
        return $this;
    }

    public function isResourceFlagSet($resource)
    {
        return in_array($resource, $this->twigVariables["head_asset_options"]);
    }

    public function setTemplate($template)
    {
        $template = "/" . $template;
        if(str_ends_with($template, ".html.twig"))
        {
            $this->template = $template;
        }
        else
        {
            $this->template = $template . ".html.twig";
        }
        return $this;
    }

    public function setResponseCode($responseCode = 200)
    {
        http_response_code($responseCode);
        return $this;
    }

    public function setVariable($key, $value = null)
    {
        $this->twigVariables[$key] = $value;
        return $this;
    }

    public function getVariable($key)
    {
        return $this->twigVariables[$key];
    }

    public function render()
    {
        // $twigLoader = new FilesystemLoader(WEB_ROOT_DIRECTORY . "/Templates");
        $twigLoader = new FilesystemLoader(dirname($_SERVER["DOCUMENT_ROOT"]) . "/Templates/");
        $twig = new Environment($twigLoader, [
                "cache" => false,
            ]
        );

        $twig->addExtension(new CommonFilters());
        $twig->addExtension(new CommonFunctions());

        $this->twigVariables["current_uri"] = $_SERVER["REQUEST_URI"];
        try
        {
            echo $twig->render($this->template, $this->twigVariables);
            exit();
        }
        catch(RuntimeError $e)
        {
            $e = $e->getPrevious();
            PageBuilder2::RenderInternalSystemError($e);
        }
    }
}
