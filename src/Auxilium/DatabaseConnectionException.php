<?php
namespace Auxilium;

class DatabaseConnectionException extends \Exception {
    public function __construct($reason = null, $code = 0, Throwable $previous = null) {
        parent::__construct("Could not connect to database".(($reason == null) ? "." : ":\n    ".$reason."."), $code, $previous);
    }
    
    public function __toString() {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }
}
