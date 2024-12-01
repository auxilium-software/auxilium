<?php

namespace Auxilium;

use Auxilium\SessionHandling\Session;
use Darksparrow\DeegraphInteractions\DataStructures\UUID;
use Darksparrow\DeegraphInteractions\QueryBuilder\QueryBuilder;

class Node
{
    private static $cached_nodes = [];
    private $rawContent = null;
    private $rawContentFetched = false;
    private $metadata = null;
    private $metadataFetched = false;
    private $cachedProperties = null;
    private $cachedReferences = null;
    private $cachedPermissions = null;
    private $nodeId = null;

    public function __construct(string $id)
    {
        if (strlen($id) == 36) {
            $this->nodeId = $id;
        }
    }

    public static function from_path(string $path)
    {
        return GraphDatabaseConnection::node_from_path($path);
    }

    public function getUuid()
    {
        return $this->nodeId;
    }

    public function addProperty(string $key, Node $node, User $actor = null, bool $force = false)
    {
        if ($actor == null) {
            $actor = Session::get_current()->getUser();
        }
        if (preg_match('/^[a-z_][a-z0-9_]*$/', $key) || preg_match('/^[0-9]+$/', $key) || $key == "#") { // Let's not allow injections! (Even though DDS handles permissions and damage will be limited to this user anyway, there's not really a benefit to *not* preventing injections)
            // $query = "LINK {".$node->getId()."} AS ".$key." OF {".$this->getId()."}".($force ? " FORCE" : "");
            $query = QueryBuilder::Link()
                ->LinkOfRelativePath(new UUID($node->getId()), new UUID($this->getId()))
                ->As($key);
            if ($force) $query = $query->Force();
            $query = $query->Build();
            return GraphDatabaseConnection::query($actor, $query);
        } else {
            return false;
        }
    }

    public function getId()
    {
        return $this->nodeId;
    }

    public function unlinkProperty(string $key, User $actor = null)
    {
        if ($actor == null) {
            $actor = Session::get_current()->getUser();
        }
        if (preg_match('/^[a-z_][a-z0-9_]*$/', $key) || preg_match('/^[0-9]+$/', $key)) { // Let's not allow injections! (Even though DDS handles permissions and damage will be limited to this user anyway, there's not really a benefit to *not* preventing injections)
            // $query = "UNLINK ".$key." FROM {".$this->getId()."}";
            $query = QueryBuilder::Unlink()
                ->UnlinkWhat($key)
                ->From(new UUID($this->getId()))
                ->Build();
            if ($this->cachedProperties != null) {
                if (isset($this->cachedProperties[$key])) {
                    unset($this->cachedProperties[$key]); // Get rid of any dangling references to this
                }
            }
            return GraphDatabaseConnection::query($actor, $query);
        } else {
            return false;
        }
    }

    public function delete(User $actor = null)
    {
        if ($actor == null) {
            $actor = Session::get_current()->getUser();
        }

        // $query = "DELETE {".$this->getId()."}";
        $query = QueryBuilder::Delete()
            ->RelativePath(new UUID($this->getId()))
            ->Build();
        return GraphDatabaseConnection::query($actor, $query);
    }

    public function is(Node $n)
    {
        if ($this->getId() == $n->getId()) {
            return true;
        }
        return false;
    }

    public function getPermissions(User $actor = null)
    {
        if ($actor == null) {
            $actor = Session::get_current()->getUser();
        }
        if ($this->cachedPermissions != null) {
            return $this->cachedPermissions;
        }
        $outputMap = [];
        // $query = "PERMS ON {".$this->getId()."}";
        $query = QueryBuilder::Permission()
            ->On(new UUID($this->getId()))
            ->Build();
        $response = GraphDatabaseConnection::query($actor, $query);
        if (isset($response["@permissions"])) {
            foreach ($response["@permissions"] as $value) {
                array_push($outputMap, $value);
            }
            $this->cachedPermissions = $outputMap;
            return $this->cachedPermissions;
        } else {
            return null;
        }
    }

    public function getReferences(User $actor = null)
    {
        if ($actor == null) {
            $actor = Session::get_current()->getUser();
        }
        if ($this->cachedReferences != null) {
            return $this->cachedReferences;
        }
        $outputMap = [];
        // $query = "REFERENCES {".$this->getId()."}";
        $query = QueryBuilder::References()
            ->RelativePath(new UUID($this->getId()))
            ->Build();
        $response = GraphDatabaseConnection::query($actor, $query);
        if (isset($response["@map"])) {
            foreach ($response["@map"] as $key => $value) {
                $arr = [];
                foreach ($value as $refNodeId) {
                    array_push($arr, Node::from_id($refNodeId));
                }
                $outputMap[$key] = $arr;
            }
            $this->cachedReferences = $outputMap;
            return $outputMap;
        } else {
            return null;
        }
    }

    public static function from_id(string $id = null)
    {
        if ($id == null) {
            return null;
        }

        if (isset(self::$cached_nodes[$id])) {
            if (self::$cached_nodes[$id] instanceof Node) {
                return self::$cached_nodes[$id]; // Skip creating the node representation - we've already loaded it!
            }
        }

        // Do stuff

        self::$cached_nodes[$id] = new Node($id);

        if (isset(self::$cached_nodes[$id])) {
            return self::$cached_nodes[$id];
        } else {
            return null;
        }
    }

    public function getProperty(string $property, User $actor = null)
    {
        if ($actor == null) {
            $actor = Session::get_current()->getUser();
        }
        if (isset($this->getProperties($actor)[$property])) {
            return $this->getProperties($actor)[$property];
        }
        return null;
    }

    public function getProperties(User $actor = null)
    {
        if ($actor == null) {
            $actor = Session::get_current()->getUser();
        }
        if ($this->cachedProperties != null) {
            return $this->cachedProperties;
        }
        $outputMap = [];
        // $query = "DIRECTORY {".$this->getId()."}";
        $query = QueryBuilder::Directory()
            ->RelativePath(new UUID($this->getId()))
            ->Build();

        $response = GraphDatabaseConnection::query($actor, $query);
        if (isset($response["@map"])) {
            foreach ($response["@map"] as $key => $value) {
                $outputMap[$key] = Node::from_id($value);
            }
            $this->cachedProperties = $outputMap;
            return $outputMap;
        } else {
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
        if ($this->getRawContent($actor) != null) {
            if (substr($this->getRawContent($actor), 0, 5) === "data:") {
                return new DataURL($this->getRawContent($actor));
            } elseif (substr($this->getRawContent($actor), 0, 7) === "auxlfs:") {
                return new AuxiliumLFSObject($this->getRawContent($actor));
            }
        }
        return null;
    }

    public function getRawContent(User $actor = null)
    {
        if ($this->rawContentFetched) {
            return $this->rawContent;
        }

        if ($actor == null) {
            $actor = Session::get_current()->getUser();
        }

        $result = GraphDatabaseConnection::raw_request($actor, "/api/v1/{" . $this->getId() . "}", "GET");

        if (is_array($result)) {
            if (isset($result["@data"])) {
                $this->rawContent = $result["@data"];
            }
        }

        $this->rawContentFetched = true;
        return $this->rawContent;
    }

    public function getMimeType()
    {
        $content = $this->getContent($actor);
        return ($content == null) ? null : $content->getMimeType();
    }

    public function __toString()
    {
        if (is_string($this->getData())) {
            return $this->getData();
        } else {
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
        if ($this->metadataFetched) {
            return $this->metadata;
        }

        if ($actor == null) {
            $actor = Session::get_current()->getUser();
        }

        $result = GraphDatabaseConnection::raw_request($actor, "/api/v1/{" . $this->getId() . "}", "GET");

        if (is_array($result)) {
            $this->metadata = $result;
        }

        $this->metadataFetched = true;
        return $this->metadata;
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
        return Node::from_id($this->getNodeMetadata()["@creator"]);
    }

    public function extendsOrInstanceOf(string $schema)
    {
        if (!isset($this->getNodeMetadata()["@schema"])) {
            return false;
        }
        return $this->getNodeMetadata()["@schema"] == $schema;
    }
}

?>