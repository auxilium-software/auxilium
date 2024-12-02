<?php

namespace Auxilium\DatabaseInteractions\MariaDB;

use Aura\SqlQuery\Common\SelectInterface;
use PDO;

class MariaDBServerConnection
{
    private PDO $pdo;

    public function __construct()
    {
        $servername = INSTANCE_CREDENTIAL_SQL_HOST;
        $database = INSTANCE_CREDENTIAL_SQL_DATABASE;
        $username = INSTANCE_CREDENTIAL_SQL_USERNAME;
        $password = INSTANCE_CREDENTIAL_SQL_PASSWORD;

        $this->pdo = new PDO(
            dsn     : "mysql:host=$servername;dbname=$database",
            username: $username,
            password: $password,
        );
        $this->pdo->setAttribute(attribute: PDO::ATTR_ERRMODE, value: PDO::ERRMODE_EXCEPTION);
    }

    public static function RunSelect(SelectInterface $queryBuilder): array
    {
        $db = new MariaDBServerConnection();

        $sth = $db->pdo->prepare($queryBuilder->getStatement());
        $sth->execute($queryBuilder->getBindValues());
        $result = $sth->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    public static function RunOneRowSelect(SelectInterface $queryBuilder): array|null
    {
        $db = new MariaDBServerConnection();

        $sth = $db->pdo->prepare($queryBuilder->getStatement());
        $sth->execute($queryBuilder->getBindValues());
        $result = $sth->fetchAll(PDO::FETCH_ASSOC);

        if(sizeof($result) == 1) return $result[0];

        return null;
    }
}
