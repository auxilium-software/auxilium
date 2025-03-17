<?php

namespace Auxilium\DatabaseInteractions;

use PDO;

require_once CREDENTIALS_FILE_LOCATION;

class RelationalDatabaseConnection
{
    private static $pdo;

    public static function get_pdo()
    {
        if(self::$pdo == null)
        {
            self::$pdo = new PDO("mysql:host=" . INSTANCE_CREDENTIAL_SQL_HOST . ";dbname=" . INSTANCE_CREDENTIAL_SQL_DATABASE, INSTANCE_CREDENTIAL_SQL_USERNAME, INSTANCE_CREDENTIAL_SQL_PASSWORD);
            self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }

        return self::$pdo;
    }
}
