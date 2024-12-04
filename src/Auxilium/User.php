<?php

namespace Auxilium;

use Auxilium\DatabaseInteractions\Deegraph\DeegraphNode;

class User extends DeegraphNode
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
        if($this->GetProperty("display_name") != null)
        {
            return $this->GetProperty("display_name");
        }
        if($this->GetProperty("name") != null)
        {
            return $this->GetProperty("name");
        }
        return null;
    }

    public function getFullName()
    {
        if($this->GetProperty("name") != null)
        {
            return $this->GetProperty("name");
        }
        return null;
    }

    public function getContactEmail()
    {
        if($this->GetProperty("contact_email") != null)
        {
            return $this->GetProperty("contact_email");
        }
        return null;
    }

    public function __toString()
    {
        if($this->GetProperty("name") == null)
        {
            return "";
        }
        else
        {
            return strval($this->GetProperty("name"));
        }
    }
}
