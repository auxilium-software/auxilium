<?php

namespace Auxilium\DatabaseInteractions\Deegraph\Nodes;

use Auxilium\DatabaseInteractions\Deegraph\DeegraphNode;

class User extends DeegraphNode
{
    public function __construct(string $objectUuid)
    {
        parent::__construct($objectUuid);
    }

    /**
     * Retrieves the system node user instance.
     *
     * @return User Returns a User instance representing the system node.
     */
    public static function get_system_node(): User
    {
        return new User(INSTANCE_CREDENTIAL_DDS_LOGIN_NODE);
    }

    public function getDisplayName(): mixed
    {
        if($this->getProperty("display_name") != null)
        {
            return $this->getProperty("display_name");
        }
        if($this->getProperty("name") != null)
        {
            return $this->getProperty("name");
        }
        return null;
    }

    public function getFullName(): mixed
    {
        if($this->getProperty("name") != null)
        {
            return $this->getProperty("name");
        }
        return null;
    }

    public function getContactEmail(): mixed
    {
        if($this->getProperty("contact_email") != null)
        {
            return $this->getProperty("contact_email");
        }
        return null;
    }

    public function __toString(): string
    {
        if($this->getProperty("name") == null)
        {
            return "";
        }
        else
        {
            return strval($this->getProperty("name"));
        }
    }
}
