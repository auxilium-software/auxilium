<?php
namespace auxilium;

class MicroTemplate {
    protected static $lookup_table = null;
    protected $path = null;
    protected $lang = "en";
    protected $template_variables = [];
    protected $null_on_unknown = false;
    
    public function __construct($path, $lang = "en", $template_variables = [], $null_on_unknown = false) {
        $this->path = $path;
        $this->lang = $lang;
        $this->template_variables = $template_variables;
        $this->null_on_unknown = $null_on_unknown;
    }
    
    public static function from_packed_template($str, $lang = "en") {
        if (strpos($str, "::auxpckstr:") === 0) {
            $strcomponents = explode(":", substr($str, 12, strlen($str) - 14));
            foreach ($strcomponents as &$strcomponent) {
                $strcomponent = $strcomponent;
            }
            if (count($strcomponents) > 0) {
                $path = array_shift($strcomponents);
                $elem = array_shift($strcomponents);
                $key = null;
                $template_variables = [];
                while ($elem != null) {
                    if ($key == null) {
                        $key = $elem;
                    } else {
                        $template_variables[$key] = $elem;
                        $key = null;
                    }
                    $elem = array_shift($strcomponents);
                }
                return new MicroTemplate($path, $lang, $template_variables);
            }
        }
        return $str;
    }
    
    public function asPackedTemplate() {
        $pack = [str_replace(":", "", $this->path)];
        foreach ($template_variables as $key => $value) {
            array_push($pack, str_replace(":", "", $key));
            $encoded_val = $value;
            if ($value instanceof PersistentObject) {
                $encoded_val = "{".$value->getUuid()."}";
            }
            array_push($pack, str_replace(":", "", $encoded_val));
        }
        return "::auxpckstr:".implode(":", $pack)."::";
    }
    
    public function asInnerPackedTemplate() {
        return "{".EncodingTools::base64_encode_url_safe($this->generate_packed_template())."}";
    }
    
    public static function ui_text_root($string, $lang = "en", $template_variables = []) {
        $from_template = strval(new MicroTemplate(strtolower($string), $lang, $template_variables, true));
        if ($from_template == null || strlen($from_template) == 0) {
            $string = explode("/", $string);
            $string = array_pop($string);
            $from_template = str_replace("_", " ", strtolower($string));
        }
        return $from_template;
    }
    
    public static function ui_heading($string, $lang = "en", $template_variables = []) {
        $from_template = strval(new MicroTemplate("ui_heading/".strtolower($string), $lang, $template_variables, true));
        if (strlen($from_template) == 0) {
            $string = explode("/", $string);
            $string = array_pop($string);
            $from_template = str_replace("_", " ", strtolower($string));
            $pcs = mb_split(" ", $from_template);
            foreach ($pcs as &$pc) {
                $pc = mb_strtoupper(mb_substr($pc, 0, 1)) . mb_substr($pc, 1);
            }
            $from_template = implode(" ", $pcs);
        }
        return $from_template;
    }
    
    public static function ui_text($string, $lang = "en", $template_variables = []) {
        $from_template = strval(new MicroTemplate("ui_text/".strtolower($string), $lang, $template_variables, true));
        if ($from_template == null || strlen($from_template) == 0) {
            $string = explode("/", $string);
            $string = array_pop($string);
            $from_template = str_replace("_", " ", strtolower($string));
        }
        return $from_template;
    }
    
    public static function data_type_to_human_name($string, $lang = "en") {
        $from_template = strval(new MicroTemplate("data_types/".strtolower($string), $lang, [], true));
        if ($from_template == null || strlen($from_template) == 0) {
            $from_template = strtoupper(substr($string, 0, 1)).str_replace("_", " ", strtolower(substr($string, 1)));
        }
        return $from_template;
    }

    public static function does_template_exist($path) {
        if (self::$lookup_table == null) {
            self::$lookup_table = json_decode(file_get_contents(WEB_ROOT_DIRECTORY."localised-strings.json"), true);
        }
        $path = explode("/", $path);
        $cdir = self::$lookup_table;
        foreach ($path as &$pathele) {
            if (is_array($cdir)) {
                if (isset($cdir[$pathele])) {
                    $cdir = $cdir[$pathele];
                } else {
                    $cdir = null;
                }
            } else {
                $cdir = null;
            }
        }
        if (is_array($cdir)) {
            return true;
        } else {
            return false;
        }
    }
    
    public function __toString() {
        if (self::$lookup_table == null) {
            self::$lookup_table = json_decode(file_get_contents(WEB_ROOT_DIRECTORY."localised-strings.json"), true);
        }
        $this->path = explode("/", $this->path);
        $cdir = self::$lookup_table;
        foreach ($this->path as &$pathele) {
            if (is_array($cdir)) {
                if (isset($cdir[$pathele])) {
                    $cdir = $cdir[$pathele];
                } else {
                    $cdir = null;
                }
            } else {
                $cdir = null;
            }
        }
        if (is_array($cdir)) {
            if (isset($cdir[$this->lang])) {
                $template_string = $cdir[$this->lang];
                foreach ($this->template_variables as $tvkey => $tvval) {
                    if (is_string($tvval) || (is_object($tvval) && method_exists($tvval, "__toString"))) {
                        if (preg_match("/^\{[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\}$/", $tvval)) { // Might be a node uuid
                            $tvval = strval(Node::from_id(substr($tvval, 1, 36)));
                        } elseif (preg_match("/^\{(?:[A-Za-z0-9_-]{4})*(?:[A-Za-z0-9_-]{2}|[A-Za-z0-9_-]{3})?\}$/", $tvval)) { // Might be an inner encoded template
                            $tvval = strval(self::from_packed_template(EncodingTools::base64_decode_url_safe(substr($tvval, 1, strlen($tvval) - 2)), $this->lang, $this->template_variables));
                        }
                        $template_string = str_replace("{{".$tvkey."}}", $tvval, $template_string);
                        $template_string = str_replace("{{ ".$tvkey." }}", $tvval, $template_string);
                    }
                }
                return $template_string;
            } else {
                if ($this->null_on_unknown) {
                    return "";
                } else {
                    return "Missing translation: ".implode("/",$this->path).":".strtoupper($this->lang);
                }
            }
        } else {
            if ($this->null_on_unknown) {
                return "";
            } else {
                return "Missing string: ".implode("/",$this->path);
            }
        }
    }
}
?>
