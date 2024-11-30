<?php

namespace Auxilium\DatabaseInteractions\Deegraph;

use Darksparrow\DeegraphInteractions\Core\DeegraphServer;

class DeegraphServerConnection
{
    public static function GetConnection(): DeegraphServer
    {
        return new DeegraphServer(
            token: INSTANCE_CREDENTIAL_DDS_TOKEN,
            actor: INSTANCE_CREDENTIAL_DDS_LOGIN_NODE,
            server: INSTANCE_CREDENTIAL_DDS_HOST,
            port: INSTANCE_CREDENTIAL_DDS_PORT,
        );
    }

    public static function GetInstanceNode(): DeegraphNode
    {
        return new DeegraphNode(INSTANCE_UUID);
    }
}
