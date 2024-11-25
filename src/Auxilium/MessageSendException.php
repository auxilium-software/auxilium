<?php
namespace Auxilium;

class MessageSendException extends \Exception {
    public function __construct($reason = null, $code = 0, Throwable $previous = null) {
        parent::__construct("Could not send mail".(($reason == null) ? "." : ":\n    ".$reason."."), $code, $previous);
    }
    
    public function __toString() {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }
}
?>
