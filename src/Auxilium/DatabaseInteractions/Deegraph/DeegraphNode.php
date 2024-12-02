<?php

namespace Auxilium\DatabaseInteractions\Deegraph;

use Auxilium\AuxiliumLFSObject;
use Auxilium\DataURL;
use Auxilium\GraphDatabaseConnection;
use Auxilium\Schema;
use Auxilium\SessionHandling\Session;
use Auxilium\User;
use Darksparrow\DeegraphInteractions\DataStructures\UUID;
use Darksparrow\DeegraphInteractions\QueryBuilder\QueryBuilder;

class DeegraphNode
{
    private static $cached_nodes = [];

    
    private $RawContent = null;
    private $RawContentFetched = false;
    private $Metadata = null;
    private $MetadataFetched = false;
    private $CachedProperties = null;
    private $CachedReferences = null;
    private $CachedPermissions = null;
    private $NodeID = null;



    public function __construct(string $id)
    {
        if(strlen($id) == 36)
        {
            $this->NodeID = $id;
        }
    }

    public static function from_path(string $path)
    {
        return GraphDatabaseConnection::node_from_path($path);
    }

    public function getUuid()
    {
        return $this->NodeID;
    }

    public function addProperty(string $key, DeegraphNode $node, User $actor = null, bool $force = false)
    {
        if($actor == null)
        {
            $actor = Session::get_current()->getUser();
        }
        if(preg_match('/^[a-z_][a-z0-9_]*$/', $key) || preg_match('/^[0-9]+$/', $key) || $key == "#")
        { // Let's not allow injections! (Even though DDS handles permissions and damage will be limited to this user anyway, there's not really a benefit to *not* preventing injections)
            // $query = "LINK {".$node->getId()."} AS ".$key." OF {".$this->getId()."}".($force ? " FORCE" : "");
            $query = QueryBuilder::Link()
                ->LinkOfRelativePath(new UUID($node->getId()), new UUID($this->getId()))
                ->As($key);
            if($force) $query = $query->Force();
            $query = $query->Build();
            return GraphDatabaseConnection::query($actor, $query);
        }
        else
        {
            return false;
        }
    }

    public function getId()
    {
        return $this->NodeID;
    }

    public function unlinkProperty(string $key, User $actor = null)
    {
        if($actor == null)
        {
            $actor = Session::get_current()->getUser();
        }
        if(preg_match('/^[a-z_][a-z0-9_]*$/', $key) || preg_match('/^[0-9]+$/', $key))
        { // Let's not allow injections! (Even though DDS handles permissions and damage will be limited to this user anyway, there's not really a benefit to *not* preventing injections)
            // $query = "UNLINK ".$key." FROM {".$this->getId()."}";
            $query = QueryBuilder::Unlink()
                ->UnlinkWhat($key)
                ->From(new UUID($this->getId()))
                ->Build();
            if($this->CachedProperties != null)
            {
                if(isset($this->CachedProperties[$key]))
                {
                    unset($this->CachedProperties[$key]); // Get rid of any dangling references to this
                }
            }
            return GraphDatabaseConnection::query($actor, $query);
        }
        else
        {
            return false;
        }
    }

    public function delete(User $actor = null)
    {
        if($actor == null)
        {
            $actor = Session::get_current()->getUser();
        }

        // $query = "DELETE {".$this->getId()."}";
        $query = QueryBuilder::Delete()
            ->RelativePath(new UUID($this->getId()))
            ->Build();
        return GraphDatabaseConnection::query($actor, $query);
    }

    public function is(DeegraphNode $n)
    {
        if($this->getId() == $n->getId())
        {
            return true;
        }
        return false;
    }

    public function getPermissions(User $actor = null)
    {
        if($actor == null)
        {
            $actor = Session::get_current()->getUser();
        }
        if($this->CachedPermissions != null)
        {
            return $this->CachedPermissions;
        }
        $outputMap = [];
        // $query = "PERMS ON {".$this->getId()."}";
        $query = QueryBuilder::Permission()
            ->On(new UUID($this->getId()))
            ->Build();
        $response = GraphDatabaseConnection::query($actor, $query);
        if(isset($response["@permissions"]))
        {
            foreach($response["@permissions"] as $value)
            {
                array_push($outputMap, $value);
            }
            $this->CachedPermissions = $outputMap;
            return $this->CachedPermissions;
        }
        else
        {
            return null;
        }
    }

    public function getReferences(User $actor = null)
    {
        if($actor == null)
        {
            $actor = Session::get_current()->getUser();
        }
        if($this->CachedReferences != null)
        {
            return $this->CachedReferences;
        }
        $outputMap = [];
        // $query = "REFERENCES {".$this->getId()."}";
        $query = QueryBuilder::References()
            ->RelativePath(new UUID($this->getId()))
            ->Build();
        $response = GraphDatabaseConnection::query($actor, $query);
        if(isset($response["@map"]))
        {
            foreach($response["@map"] as $key => $value)
            {
                $arr = [];
                foreach($value as $refNodeId)
                {
                    array_push($arr, DeegraphNode::from_id($refNodeId));
                }
                $outputMap[$key] = $arr;
            }
            $this->CachedReferences = $outputMap;
            return $outputMap;
        }
        else
        {
            return null;
        }
    }

    public static function from_id(string $id = null)
    {
        if($id == null)
        {
            return null;
        }

        if(isset(self::$cached_nodes[$id]))
        {
            if(self::$cached_nodes[$id] instanceof DeegraphNode)
            {
                return self::$cached_nodes[$id]; // Skip creating the node representation - we've already loaded it!
            }
        }

        // Do stuff

        self::$cached_nodes[$id] = new DeegraphNode($id);

        if(isset(self::$cached_nodes[$id]))
        {
            return self::$cached_nodes[$id];
        }
        else
        {
            return null;
        }
    }

    public function getProperty(string $property, User $actor = null)
    {
        if($actor == null)
        {
            $actor = Session::get_current()->getUser();
        }
        if(isset($this->getProperties($actor)[$property]))
        {
            return $this->getProperties($actor)[$property];
        }
        return null;
    }

    public function getProperties(User $actor = null)
    {
        if($actor == null)
        {
            $actor = Session::get_current()->getUser();
        }
        if($this->CachedProperties != null)
        {
            return $this->CachedProperties;
        }
        $outputMap = [];
        // $query = "DIRECTORY {".$this->getId()."}";
        $query = QueryBuilder::Directory()
            ->RelativePath(new UUID($this->getId()))
            ->Build();

        $response = GraphDatabaseConnection::query($actor, $query);
        if(isset($response["@map"]))
        {
            foreach($response["@map"] as $key => $value)
            {
                $outputMap[$key] = DeegraphNode::from_id($value);
            }
            $this->CachedProperties = $outputMap;
            return $outputMap;
        }
        else
        {
            return null;
        }
    }

    public function getObjectSize()
    {
        $content = $this->getContent($actor);
        return ($content == null) ? 0 : $content->getSize();
    }

    public function getContent(User $actor = null)
    {
        if($this->getRawContent($actor) != null)
        {
            if(substr($this->getRawContent($actor), 0, 5) === "data:")
            {
                return new DataURL($this->getRawContent($actor));
            }
            elseif(substr($this->getRawContent($actor), 0, 7) === "auxlfs:")
            {
                return new AuxiliumLFSObject($this->getRawContent($actor));
            }
        }
        return null;
    }

    public function getRawContent(User $actor = null)
    {
        if($this->RawContentFetched)
        {
            return $this->RawContent;
        }

        if($actor == null)
        {
            $actor = Session::get_current()->getUser();
        }

        $result = GraphDatabaseConnection::raw_request($actor, "/api/v1/{" . $this->getId() . "}", "GET");

        if(is_array($result))
        {
            if(isset($result["@data"]))
            {
                $this->RawContent = $result["@data"];
            }
        }

        $this->RawContentFetched = true;
        return $this->RawContent;
    }

    public function getMimeType()
    {
        $content = $this->getContent($actor);
        return ($content == null) ? null : $content->getMimeType();
    }

    public function __toString()
    {
        if(is_string($this->getData()))
        {
            return $this->getData();
        }
        else
        {
            return "";
        }
    }

    public function getData(User $actor = null)
    {
        $content = $this->getContent($actor);
        return ($content == null) ? null : $content->getData();
    }

    public function getTimestamp()
    {
        return date("c", strtotime($this->getNodeMetadata()["@created"]));
    }

    public function getNodeMetadata(User $actor = null)
    {
        if($this->MetadataFetched)
        {
            return $this->Metadata;
        }

        if($actor == null)
        {
            $actor = Session::get_current()->getUser();
        }

        $result = GraphDatabaseConnection::raw_request($actor, "/api/v1/{" . $this->getId() . "}", "GET");

        if(is_array($result))
        {
            $this->Metadata = $result;
        }

        $this->MetadataFetched = true;
        return $this->Metadata;
    }

    public function getTimestampInt()
    {
        return strtotime($this->getNodeMetadata()["@created"]);
    }

    public function getSchema()
    {
        return Schema::from_url($this->getSchemaUrl());
    }

    public function getSchemaUrl()
    {
        return isset($this->getNodeMetadata()["@schema"]) ? $this->getNodeMetadata()["@schema"] : null;
    }

    public function getCreator()
    {
        return DeegraphNode::from_id($this->getNodeMetadata()["@creator"]);
    }

    public function extendsOrInstanceOf(string $schema)
    {
        if(!isset($this->getNodeMetadata()["@schema"]))
        {
            return false;
        }
        return $this->getNodeMetadata()["@schema"] == $schema;
    }
}

?>