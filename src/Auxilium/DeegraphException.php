<?php
namespace Auxilium;

class DeegraphException extends \Exception {
    protected $trace = null;

    public function __construct($reason = null, $code = 0, Throwable $previous = null, array $trace = null) {
        $this->trace = $trace;
        parent::__construct($reason == null ? "Unknown database issue" : $reason, $code, $previous);
    }
    
    public function getInnerTrace() {
        return $this->trace;
    }
    
    public function __toString() {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }
}
