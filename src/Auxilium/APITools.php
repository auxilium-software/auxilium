<?php
namespace Auxilium;

use Auxilium\SessionHandling\Session;

class APITools {
    private static $instance = null;
    private $returnData;

    private function __construct() {
        $this->returnData = [
            "response_code" => http_response_code()
        ];
    }
    
    public static function get_instance() {
        if (self::$instance == null) {
            self::$instance = new APITools();
        }
        
        return self::$instance;
    }
    
    public function isInIpRange($range, $ip) {
        list($range, $netmask) = explode('/', $range, 2);
        $rangeDecimal = ip2long($range);
        $ipDecimal = ip2long($ip);
        $wildcardDecimal = pow(2, (32 - $netmask)) - 1;
        $netmaskDecimal = ~$wildcardDecimal;
        if (($ipDecimal & $netmaskDecimal) == ($rangeDecimal & $netmaskDecimal)) {
            return true;
        } else {
            return false;
        }
    }

    public function isInInternalIpRange($ip) {
        foreach (INSTANCE_CREDENTIAL_LOCAL_IP_RANGES as &$ipr) {
            if ($this->isInIpRange($ipr, $ip)) {
                return true;
            }
        }
        return false;
    }
    
    public function clearReturnData() {
        $this->returnData = [
            "response_code" => http_response_code()
        ];
    }
    
    public function requireLogin() {
        if (!Session::get_current()->sessionAuthenticated()) {
            $this->clearReturnData();
            $this->setStatus("UNAUTHORIZED");
            $this->setErrorText("Login required for this API. Check session token.");
            $this->setResponseCode(401);
            $this->output();
        }
    }
    
    public function requireInternalIpRange() {
        $internalAPIConnection = false;
        if ($this->isInInternalIpRange($_SERVER["REMOTE_ADDR"])) {
            $internalAPIConnection = true;
        }
        
        if (!$internalAPIConnection) {
            header("Content-Type: application/json; charset=utf-8");
            http_response_code(401);
            $returnData = [
                "status" => "UNAUTHORIZED",
                "error_message" => "This API is for internal use only. Check client ip range.",
                "response_code" => 401
            ];
            echo json_encode($returnData, JSON_PRETTY_PRINT);
            exit();
        }
    }

    public function requireInternalApiKey() {
        $internalAPIConnection = false;
        
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            if (str_starts_with($_SERVER['HTTP_AUTHORIZATION'], "Digest ")) {
                $authDigest = substr($_SERVER['HTTP_AUTHORIZATION'], strlen("Digest "));
                if ($authDigest == hash("sha256", INSTANCE_CREDENTIAL_LOCAL_ONLY_API_KEY)) {
                    $internalAPIConnection = true;
                }
            }
        }

        if (!$internalAPIConnection) {
            header("Content-Type: application/json; charset=utf-8");
            http_response_code(401);
            $returnData = [
                "status" => "UNAUTHORIZED",
                "error_message" => "This API is for internal use only. Check bearer token.",
                "response_code" => 401
            ];
            echo json_encode($returnData, JSON_PRETTY_PRINT);
            exit();
        }
    }
    
    public function setVariable($key, $value = null) {
        $this->returnData[$key] = $value;
    }
    
    public function getVariable($key) {
        return $this->returnData[$key];
    }
    
    public function setStatus($value) {
        $this->setVariable("status", $value);
    }
    
    public function setErrorText($value) {
        $this->setVariable("error_message", $value);
        if (http_response_code() == 200) {
            http_response_code(400);
        }
    }
    
    public function setResponseCode($responseCode = 200) {
        http_response_code($responseCode);
    }
    
    public function output() {
        header("Content-Type: application/json; charset=utf-8");
        $this->returnData["response_code"] = http_response_code();
        if (!isset($this->returnData["status"])) {
            if (http_response_code() == 200) {
                $this->returnData["status"] = "OK";
            } else {
                $this->returnData["status"] = "ERROR";
            }
        }
        echo json_encode($this->returnData, JSON_PRETTY_PRINT);
        echo "\n";
        exit();
    }
}
