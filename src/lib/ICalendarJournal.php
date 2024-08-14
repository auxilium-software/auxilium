<?php
namespace auxilium;

class ICalendarJournal extends ICalendarComponent {
    public function __construct(array $content = null) {
        parent::__construct($content);
    }
    
    public function stringify() {
        return "BEGIN:VJOURNAL\r\n".parent::stringify()."END:VJOURNAL\r\n";
    }
}
?>
