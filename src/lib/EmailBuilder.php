<?php
namespace auxilium;

class EmailBuilder {
    private $emailData = null;
    private $recipients = [];
    
    public function __construct() {
        $this->emailData = [
            "sender" => null,
            "template" => "generic-case-email",
            "template_properties" => [],
            "subject" => null
        ];
    }
    
    public function setSubject($subject) {
        $this->emailData["subject"] = $subject;
        return $this;
    }
    
    public function setTemplate($template) {
        $this->emailData["template"] = $template;
        return $this;
    }
    
    public function setTemplateProperty($key, $value) {
        $this->emailData["template_properties"][$key] = $value;
        return $this;
    }
    
    public function addRecipient($recipient, $name = null) {
        if (filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $recipientString = $recipient;
            if ($name !== null) {
                $recipientString = "\"".mb_ereg_replace("\"", "\\\"", $name)."\" <".$recipientString.">";
            }
            array_push($this->recipients, $recipientString);
            return $this;
        } else {
            throw new Exception("Bad recipient string");
        }
    }
    
    public function build() {
        $twigLoader = new \Twig\Loader\FilesystemLoader(WEB_ROOT_DIRECTORY."/templates");
        $twig = new \Twig\Environment($twigLoader, [
            "cache" => false,
        ]);
        
        //$this->emailData["template_properties"]["selected_lang"] = "en";

        $templatePath = "email-templates/".$this->emailData["template"].".html";
        $content = null;
        $fixedTemplateProperties = [
            "INSTANCE_BRANDING_LOGO" => INSTANCE_BRANDING_LOGO,
            "INSTANCE_BRANDING_LOGO_CONTRAST_BRAND_COLOR" => INSTANCE_BRANDING_LOGO_CONTRAST_BRAND_COLOR,
            "INSTANCE_BRANDING_NAME" => INSTANCE_BRANDING_NAME,
            "INSTANCE_BRANDING_DOMAIN_NAME" => INSTANCE_BRANDING_DOMAIN_NAME,

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
        $fullTemplateProperties = array_merge($fixedTemplateProperties, $this->emailData["template_properties"]);

        $filter = new \Twig\TwigFilter("uiprop", function ($string) {
            return MicroTemplate::data_type_to_human_name($string, $this->twigVariables["selected_lang"]);
        });
        $twig->addFilter($filter);
        $filter = new \Twig\TwigFilter("uitxt", function ($string) {
            return MicroTemplate::ui_text($string, $this->twigVariables["selected_lang"]);
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
        $content = $twig->render($templatePath, $fullTemplateProperties);

        $this->emailData["body"] = $content;

        $build_content = "MIME-Version: 1.0\r\n";
        $boundary = \auxilium\EncodingTools::base64_encode_url_safe(openssl_random_pseudo_bytes(48));
        
        $message_parties = [];
        
        $build_content .= "To: ";
        $first = true;
        foreach ($this->recipients as &$recipient) {
            if ($first) {
                $first = false;
            } else {
                $build_content .= ", ";
            }
            $build_content .= $recipient;
        }
        $build_content .= "\r\n";
        
        $build_content .= "Subject: ".$this->emailData["subject"]."\r\n";
        $build_content .= "Content-Type: multipart/alternative; boundary=$boundary\r\n";
        $build_content .= "\r\n";
        
        $contents = [
            [
                "content_type" => "text/html",
                "content" => $this->emailData["body"]
            ]
        ];
        
        foreach ($contents as &$content) {
            if ($content["content_type"] == "text/plain") {
                $content["content_type"] = "text/plain; charset=\"UTF-8\"";
            }
            if ($content["content_type"] == "text/html") {
                $content["content_type"] = "text/html; charset=\"UTF-8\"";
            }
        }
        
        $first = true;
        foreach ($contents as &$content) {
            if ($first) {
                $build_content .= "--$boundary\r\n";
                $first = false;
            } else {
                $build_content .= "\r\n";
            }
            
            $build_content .= "Content-Type: ".$content["content_type"]."\r\n\r\n";
            $build_content .= $content["content"]."\r\n";
            
            $build_content .= "--$boundary";
        }
        $build_content .= "--\r\n";
        
        return $build_content;
    }
}
