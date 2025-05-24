<?php

namespace Auxilium\DatabaseInteractions\Redis;


use Predis\Client;

class RedisServerConnection
{
    public Client $RedisClient;

    public function __construct()
    {
        $this->RedisClient = new Client([
            'scheme'    => 'tcp',
            'host'      => INSTANCE_CREDENTIAL_REDIS_HOST,
            'port'      => INSTANCE_CREDENTIAL_REDIS_PORT,
            'password'  => INSTANCE_CREDENTIAL_REDIS_PASSWORD,
            'database'  => INSTANCE_CREDENTIAL_REDIS_DATABASE,
        ]);
    }
}
