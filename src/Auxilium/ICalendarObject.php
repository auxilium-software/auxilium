<?php
namespace Auxilium;
class ICalendarObject {
    protected $sections = null;

    public function __construct($content = null) {
        if ($content = null) {
            $this->sections = [];
        } else {
            $content = explode("\r\n ", $content);
            $content = implode("", $content); // unfold lines
            $content = explode("\r\n", $content);
            $state = 0;
            $etype = 0;
            $elines = [];
            foreach ($content as &$line) {
                switch ($state) {
                    case 2:
                        if (strpos($line, "END:") === 0) {
                            switch (substr($line, 4)) {
                                case "VEVENT":
                                    if ($etype == 1) {
                                        $state = 1;
                                    }
                                    break;
                                case "VJOURNAL":
                                    if ($etype == 2) {
                                        $state = 1;
                                    }
                                    break;
                            }
                        }
                        if ($state == 1) {
                            switch ($etype) {
                                case 2:
                                    array_push($this->sections, new ICalendarJournal($elines));
                                    break;
                            }
                            $elines = [];
                            $etype = 0;
                        } else {
                            array_push($elines, $line);
                        }
                        break;
                    case 1:
                        if (strpos($line, "BEGIN:") === 0) {
                            switch (substr($line, 6)) {
                                case "VEVENT":
                                    $state = 2;
                                    $etype = 1;
                                    break;
                                case "VJOURNAL":
                                    $state = 2;
                                    $etype = 2;
                                    break;
                            }
                        }
                        break;
                    case 0:
                        if ($line == "BEGIN:VCALENDAR") {
                            $state = 1;
                        }
                        break;
                    default:
                        break;
                }
                if ($state = -1) {
                    break;
                }
            }
        }
    }
    
    public static function fold(string $raw) {
        return chunk_split($raw, 75, "\r\n ");
    }
    
    public static function stringify_datetime(\DateTime $date) {
        return $date->format("Ymd\THis\Z")
    }
    
    public static function escape(string $raw) {
        $search  = array(",", ";", "\N", "\R", "\n", "\r", "\\");
        $replace = array("\\,", "\\;", "\\N", "\\R", "\\n", "\\r", "\\\\");
        return str_replace($search, $replace, $raw);
    }
    
    public static function unescape(string $raw) {
        $search  = array("\\,", "\\;", "\\N", "\\R", "\\n", "\\r", "\\\\");
        $replace = array(",", ";", "\N", "\R", "\n", "\r", "\\");
        return str_replace($search, $replace, $raw);
    }
    
    public function stringify() {
        $stringified = "BEGIN:VCALENDAR\r\n";
        $stringified .= "VERSION:2.0\r\n";
        foreach ($this->sections as &$section) {
            $stringified .= $section->stringify();
        }
        $stringified .= "END:VCALENDAR\r\n";
        return $stringified;
    }
    
    public function __toString() {
        return $this->stringify();
    }
}
