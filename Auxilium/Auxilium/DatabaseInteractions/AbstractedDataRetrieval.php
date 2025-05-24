<?php

namespace Auxilium\DatabaseInteractions;

use Auxilium\DatabaseInteractions\MariaDB\MariaDBServerConnection;
use Auxilium\DatabaseInteractions\MariaDB\MariaDBTable;
use Auxilium\DatabaseInteractions\MariaDB\SQLQueryBuilderWrapper;
use Auxilium\DatabaseInteractions\Redis\RedisServerConnection;
use Auxilium\Utilities\Security;
use Exception;

class AbstractedDataRetrieval
{
    public static string $URLScheme = "auxdata://";

    public static function CreateData(string $data): string
    {
        $db = new MariaDBServerConnection();
        $redis = new RedisServerConnection();

        // generate pseudo-random uuid
        $randomBytes = Security::GeneratePseudoRandomBytes(16);
        $randomBytes[6] = chr(ord($randomBytes[6]) & 0x0f | 0x40);
        $randomBytes[8] = chr(ord($randomBytes[8]) & 0x3f | 0x80);
        $id = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($randomBytes), 4));

        // hash data
        $dataHash = hash(algo: "sha512", data: $id.'->'.$data);

        // store data in mariadb
        $db->RunInsert(queryBuilder: SQLQueryBuilderWrapper::INSERT(
            table: MariaDBTable::DATA
        )
            ->set(col: 'id', value: ':__value__')
            ->set(col: 'sha512_hash', value: ':__sha512_hash__')
            ->set(col: 'data', value: ':__data__')
            ->bindValue(name: '__value__', value: $id)
            ->bindValue(name: '__sha512_hash__', value: $dataHash)
            ->bindValue(name: '__data__', value: $data)
        );

        // store data in redis
        $redis->RedisClient->hset(
            key  : $id,
            field: 'hash',
            value: $dataHash,
        );
        $redis->RedisClient->hset(
            key  : $id,
            field: 'data',
            value: $data,
        );
        if (!$redis->RedisClient->hexists($id, 'data'))
        {
            throw new \RuntimeException("Failed to store data in Redis for key $id");
        }

        return self::$URLScheme . $id . '?hash=' . $dataHash;
    }

    /**
     * @throws Exception
     */
    public static function GetData(string $dataURL): string|null
    {
        $parsed = parse_url($dataURL);
        $scheme = $parsed['scheme'] . '://';
        $id = ltrim($parsed['host'], '/');
        parse_str($parsed['query'], $queryParts);
        $providedHash = $queryParts['hash'] ?? "invalid hash";

        if($scheme !== self::$URLScheme)
        {
            throw new Exception("invalid scheme for data object");
        }

        $redis = new RedisServerConnection();

        // get data from redis
        $storedHash = $redis->RedisClient->hget(
            key: $id,
            field: 'hash',
        );
        $data = $redis->RedisClient->hget(
            key: $id,
            field: 'data',
        );
        if (!is_string($storedHash) || !is_string($data))
        {
            return null;
        }

        // check data is fine
        if(hash_equals(known_string: $storedHash, user_string: $providedHash))
        {
            return $data;
        }
        return "~~data_corrupted~~";
    }

    public static function QueueDataForDeletion(string $id): bool
    {

    }
}
