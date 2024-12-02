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
    private $Metadata = null;

    private ?array $CachedProperties = null;
    private ?array $CachedReferences = null;
    private ?array $CachedPermissions = null;

    private bool $HasRawContentBeenFetchedYet = false;
    private bool $HasMetadataBeenFetchedYet = false;

    private UUID $NodeID;


    public function __construct(string $id)
    {
        $this->NodeID = new UUID($id);
    }
    public function __toString()
    {
        $temp = $this->GetData();
        if(is_string($temp))
            return $temp;
        return "";
    }


    /**
     * Returns the UUID of the node.
     * @return string Node UUID.
     */
    public function GetNodeID(): string
    {
        return substr($this->NodeID, 1, -1);
    }



    public static function FromPath(string $path): ?DeegraphNode
    {
        return GraphDatabaseConnection::node_from_path($path);
    }
    public static function FromID(string $id = null): ?DeegraphNode
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


    public function AddProperty(string $key, DeegraphNode $node, User $actor = null, bool $force = false)
    {
        if($actor == null)
        {
            $actor = Session::get_current()->getUser();
        }
        if(preg_match('/^[a-z_][a-z0-9_]*$/', $key) || preg_match('/^[0-9]+$/', $key) || $key == "#")
        { // Let's not allow injections! (Even though DDS handles permissions and damage will be limited to this user anyway, there's not really a benefit to *not* preventing injections)
            // $query = "LINK {".$node->getId()."} AS ".$key." OF {".$this->getId()."}".($force ? " FORCE" : "");
            $query = QueryBuilder::Link()
                ->LinkOfRelativePath(new UUID($node->GetNodeID()), new UUID($this->GetNodeID()))
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


    public function UnlinkProperty(string $key, User $actor = null)
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
                ->From(new UUID($this->GetNodeID()))
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

    public function Delete(User $actor = null)
    {
        if($actor == null)
        {
            $actor = Session::get_current()->getUser();
        }

        // $query = "DELETE {".$this->getId()."}";
        $query = QueryBuilder::Delete()
            ->RelativePath(new UUID($this->GetNodeID()))
            ->Build();
        return GraphDatabaseConnection::query($actor, $query);
    }

    public function Is(DeegraphNode $n): bool
    {
        if($this->GetNodeID() == $n->GetNodeID())
        {
            return true;
        }
        return false;
    }

    public function GetPermissions(User $actor = null): ?array
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
            ->On(new UUID($this->GetNodeID()))
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

    public function GetReferences(User $actor = null): ?array
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
            ->RelativePath(new UUID($this->GetNodeID()))
            ->Build();
        $response = GraphDatabaseConnection::query($actor, $query);
        if(isset($response["@map"]))
        {
            foreach($response["@map"] as $key => $value)
            {
                $arr = [];
                foreach($value as $refNodeId)
                {
                    array_push($arr, DeegraphNode::FromID($refNodeId));
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

    public function GetProperty(string $property, User $actor = null)
    {
        if($actor == null)
        {
            $actor = Session::get_current()->getUser();
        }
        $temp = $this->GetProperties($actor);
        if(isset($temp[$property]))
            return $temp[$property];
        return null;
    }

    public function GetProperties(User $actor = null): ?array
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
            ->RelativePath(new UUID($this->GetNodeID()))
            ->Build();

        $response = GraphDatabaseConnection::query($actor, $query);
        if(isset($response["@map"]))
        {
            foreach($response["@map"] as $key => $value)
            {
                $outputMap[$key] = DeegraphNode::FromID($value);
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
        $content = $this->GetContent($actor);
        return ($content == null) ? 0 : $content->getSize();
    }

    public function GetContent(User $actor = null): AuxiliumLFSObject|DataURL|null
    {
        if($this->GetRawContent($actor) != null)
        {
            if(substr($this->GetRawContent($actor), 0, 5) === "data:")
            {
                return new DataURL($this->GetRawContent($actor));
            }
            elseif(substr($this->GetRawContent($actor), 0, 7) === "auxlfs:")
            {
                return new AuxiliumLFSObject($this->GetRawContent($actor));
            }
        }
        return null;
    }

    public function GetRawContent(User $actor = null)
    {
        if($this->HasRawContentBeenFetchedYet)
        {
            return $this->RawContent;
        }

        if($actor == null)
        {
            $actor = Session::get_current()->getUser();
        }

        $result = GraphDatabaseConnection::raw_request($actor, "/api/v1/{" . $this->GetNodeID() . "}", "GET");

        if(is_array($result))
        {
            if(isset($result["@data"]))
            {
                $this->RawContent = $result["@data"];
            }
        }

        $this->HasRawContentBeenFetchedYet = true;
        return $this->RawContent;
    }

    public function getMimeType()
    {
        $content = $this->GetContent($actor);
        return ($content == null) ? null : $content->getMimeType();
    }

    public function GetData(User $actor = null)
    {
        $content = $this->GetContent($actor);
        return ($content == null) ? null : $content->getData();
    }

    public function GetTimestamp(): string
    {
        return date("c", strtotime($this->GetNodeMetadata()["@created"]));
    }

    public function GetNodeMetadata(User $actor = null): ?array
    {
        if($this->HasMetadataBeenFetchedYet)
        {
            return $this->Metadata;
        }

        if($actor == null)
        {
            $actor = Session::get_current()->getUser();
        }

        $result = GraphDatabaseConnection::raw_request($actor, "/api/v1/{" . $this->GetNodeID() . "}", "GET");

        if(is_array($result))
        {
            $this->Metadata = $result;
        }

        $this->HasMetadataBeenFetchedYet = true;
        return $this->Metadata;
    }

    public function GetTimestampInt(): false|int
    {
        return strtotime($this->GetNodeMetadata()["@created"]);
    }

    public function GetSchema()
    {
        return Schema::from_url($this->GetSchemaUrl());
    }

    public function GetSchemaUrl()
    {
        return isset($this->GetNodeMetadata()["@schema"]) ? $this->GetNodeMetadata()["@schema"] : null;
    }

    public function GetCreator(): ?DeegraphNode
    {
        return DeegraphNode::FromID($this->GetNodeMetadata()["@creator"]);
    }

    public function ExtendsOrInstanceOf(string $schema): bool
    {
        if(!isset($this->GetNodeMetadata()["@schema"]))
        {
            return false;
        }
        return $this->GetNodeMetadata()["@schema"] == $schema;
    }
}
