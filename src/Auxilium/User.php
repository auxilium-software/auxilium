<?php

namespace Auxilium;

class User extends Node
{
    public function __construct(string $objectUuid)
    {
        parent::__construct($objectUuid);
    }

    public static function get_system_node()
    {
        return new User(INSTANCE_CREDENTIAL_DDS_LOGIN_NODE);
    }

    public function getDisplayName()
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

    public function getFullName()
    {
        if($this->getProperty("name") != null)
        {
            return $this->getProperty("name");
        }
        return null;
    }

    public function getContactEmail()
    {
        if($this->getProperty("contact_email") != null)
        {
            return $this->getProperty("contact_email");
        }
        return null;
    }

    public function __toString()
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
