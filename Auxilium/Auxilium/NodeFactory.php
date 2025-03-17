<?php

namespace Auxilium;

use Auxilium\DatabaseInteractions\GraphDatabaseConnection;

class NodeFactory
{
    protected $mimeType = null;
    protected $data = null;
    protected $actor = null;
    protected $schema = null;

    public function __construct()
    {
    }

    public function setMimeType($mimeType)
    {
        $this->mimeType = $mimeType;
        return $this;
    }

    public function setSchema($schema)
    {
        $this->schema = $schema;
        return $this;
    }

    public function setData($data)
    {
        $this->data = $data;
        return $this;
    }

    protected function build()
    {
        if($this->actor == null)
        {
            $this->actor = SessionHandling\Session::get_current()->getUser();
        }
        return GraphDatabaseConnection::new_node($this->data, $this->mimeType, $this->schema, $this->actor);
    }
}
