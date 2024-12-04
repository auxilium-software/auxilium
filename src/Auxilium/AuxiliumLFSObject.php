<?php

namespace Auxilium;

use Auxilium\SessionHandling\Session;

class AuxiliumLFSObject
{
    private $data = null;
    private $dataHash = null;
    private $mimeType = null;
    private $uuid = null;
    private $length = null;
    private $domain = null;
    private $readPermission = [];

    public function __construct(string $stringRepresentation)
    {
        $stringRepresentation = trim($stringRepresentation);
        if(mb_strpos($stringRepresentation, "auxlfs://") === 0)
        {
            $stringRepresentation = mb_substr($stringRepresentation, 9);
            $expl1 = explode("/", $stringRepresentation);
            $expl2 = [];
            if(strlen($expl1[0]) > 0)
            {
                $this->domain = $expl1[0];
            }
            if(count($expl1) > 1)
            {
                $expl2 = explode("+", $expl1[1]);
            }
            if(strlen($expl2[0]) > 0)
            {
                $this->uuid = $expl2[0];
            }
            if(count($expl2) > 1)
            {
                if(strlen($expl2[1]) > 0)
                {
                    $this->dataHash = $expl2[1];
                }
            }
            if(count($expl2) > 2)
            {
                if(strlen($expl2[2]) > 0)
                {
                    $this->mimeType = urldecode($expl2[2]);
                }
            }
            if(count($expl2) > 3)
            {
                if(strlen($expl2[3]) > 0)
                {
                    $this->length = intval($expl2[3]);
                }
            }
        }
    }

    public function isWriteable(User $actor = null)
    {
        if($this->exists())
        {
            return false;
        }
        if($actor == null)
        {
            $actor = Session::get_current()->getUser();
        }
        $actorId = ($actor == null) ? null : $actor->GetNodeID();
        if($this->GetObjectID() == null)
        {
            return false;
        }
        else
        {
            $query = "SELECT {" . $this->GetObjectID() . "}/@creator/@id";
            $response = GraphDatabaseConnection::query($actor, $query);
            if(isset($response["@rows"]))
            {
                if(count($response["@rows"]) > 0)
                {
                    if($response["@rows"][0]["{" . $this->GetObjectID() . "}/@creator/@id"]["{" . $this->GetObjectID() . "}/@creator/@id"] == "{" . $actorId . "}")
                    {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    public function exists()
    {
        if(file_exists(LOCAL_STORAGE_DIRECTORY . $this->GetObjectID()))
        {
            return true;
        }
        return false;
    }

    public function GetObjectID()
    {
        return $this->uuid;
    }

    public function __toString()
    {
        return "auxlfs://" . $this->domain . "/" . $this->uuid . "+" . $this->getHash() . "+" . urlencode($this->getMimeType()) . "+" . $this->getSize();
    }

    public function getHash()
    {
        return $this->dataHash;
    }

    public function getMimeType()
    {
        return ($this->mimeType == null) ? "application/octet-stream" : $this->mimeType;
    }

    public function getSize()
    {
        if($this->length == null)
        {
            $this->length = strlen($this->getData());
        }
        return $this->length;
    }

    public function getData(User $actor = null)
    {
        if($this->canRead($actor))
        {
            if($this->data == null)
            {
                if($this->exists())
                {
                    $this->data = file_get_contents(LOCAL_STORAGE_DIRECTORY . $this->GetObjectID());
                }
            }
            return $this->data;
        }
        return null;
    }

    public function canRead(User $actor = null)
    {
        if($actor == null)
        {
            $actor = Session::get_current()->getUser();
        }
        $actorId = ($actor == null) ? null : $actor->GetNodeID();
        if(!isset($this->readPermission[$actorId]))
        {
            if($this->GetObjectID() == null)
            {
                $this->readPermission[$actorId] = false;
            }
            else
            {
                $query = "SELECT {" . $this->GetObjectID() . "}";
                $response = GraphDatabaseConnection::query($actor, $query);
                $this->readPermission[$actorId] = false;
                if(isset($response["@rows"]))
                {
                    if(count($response["@rows"]) > 0)
                    {
                        $query = "PERMS ON {" . $this->GetObjectID() . "}";
                        $response = GraphDatabaseConnection::query($actor, $query);
                        if(isset($response["@permissions"]))
                        {
                            foreach($response["@permissions"] as $value)
                            {
                                if(strtoupper($value) == "READ")
                                {
                                    $this->readPermission[$actorId] = true;
                                }
                            }
                        }
                    }
                }
            }
        }
        return $this->readPermission[$actorId];
    }
}
