<?php

namespace Auxilium\DatabaseInteractions\Redis;

use Auxilium\DatabaseInteractions\Deegraph\DeegraphNode;
use Auxilium\Utilities\Security;
use Darksparrow\DeegraphInteractions\DataStructures\DeegraphNodeDetails;
use Predis\Client;

class RedisServerConnection
{
    private Client $RedisClient;

    public function __construct()
    {
        $this->RedisClient = new Client([
        ]);
    }


    public static function Set(string|null $value): string
    {
        $redisClient = new RedisServerConnection();

        $randomBytes = Security::GeneratePseudoRandomBytes(16);
        $randomBytes[6] = chr(ord($randomBytes[6]) & 0x0f | 0x40);
        $randomBytes[8] = chr(ord($randomBytes[8]) & 0x3f | 0x80);
        $id = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($randomBytes), 4));

        $redisClient->RedisClient->set(
            $id,
            $value,
        );

        $redisClient->RedisClient->disconnect();

        return $id;
    }

    public static function Get(string $id): string|null
    {
        $redisClient = new RedisServerConnection();

        $temp =  $redisClient->RedisClient->get(
            $id,
        );

        $redisClient->RedisClient->disconnect();

        return $temp;
    }
}
