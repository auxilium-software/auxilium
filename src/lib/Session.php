<?php
namespace auxilium;

class Session {
    private static $current = null;
    private $currentUser;
    
    private function __construct() {
        $this->currentUser = null;
        if(isset($_COOKIE["session_key"])) {
            try {
                $ts = date("Y-m-d G:i:s", time() - (3600 * 48)); // 48 hr Sessions
                $bindVariables = [
                    "session_key" => $_COOKIE["session_key"],
                    "min_issue_date" => $ts
                ];
                $sql = "SELECT user_uuid FROM portal_sessions WHERE session_key=:session_key AND start_timestamp>:min_issue_date AND active=1";
                $statement = RelationalDatabaseConnection::get_pdo()->prepare($sql);
                $statement->execute($bindVariables);
                $sessionInfo = $statement->fetch();
                if ($sessionInfo == null) { // This isn't a valid session
                    setcookie("session_key", "", time() - (3600 * 48), "/", "", true, true); // Delete cookie by setting expiry to the past.
                } else { // This *is* a valid session
                    $this->currentUser = new User($sessionInfo["user_uuid"]);
                }
            } catch (\Exception $e) { // Something has gone very wrong with this session key, time to trash it
                $this->currentUser = null;
                setcookie("session_key", "", time() - (3600 * 48), "/", "", true, true); // Delete cookie by setting expiry to the past.
            }
        }
    }
    
    public static function get_current() {
        if (self::$current == null) {
            self::$current = new Session();
        }
        
        return self::$current;
    }
    
    public function getUser() {
        return $this->currentUser;
    }
    
    public function forceSetCurrentUser($user) {
        return $this->currentUser = $user;
    }
    
    public function sessionAuthenticated() {
        return !($this->currentUser == null);
    }
}
    
?>
