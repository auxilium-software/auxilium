<?php

namespace Auxilium;

class DataURL
{
    private $data = null;
    private $mimeType = null;

    public function __construct(string $stringRepresentation)
    {
        $stringRepresentation = trim($stringRepresentation);
        if(mb_strpos($stringRepresentation, "data:") === 0)
        {
            $stringRepresentation = mb_substr($stringRepresentation, 5);
            if(mb_strpos($stringRepresentation, ",") !== false)
            {
                $parts = explode(",", $stringRepresentation, 2);
                $header = $parts[0];
                if(mb_strpos($stringRepresentation, ";base64") === false)
                {
                    $this->mimeType = $header;
                    $this->data = urldecode($parts[1]);
                }
                else
                {
                    $this->mimeType = explode(";", $header, 2)[0];
                    $this->data = base64_decode($parts[1]);
                }
            }
        }
    }

    public function getData()
    {
        return $this->data;
    }

    public function getSize()
    {
        return strlen($this->data);
    }

    public function getMimeType()
    {
        return $this->mimeType;
    }

    public function __toString()
    {
        switch($this->mimeType)
        {
            case "text/plain":
            case "application/json":
                return "data:" . $this->mimeType . "," . urlencode($this->data);
                break;
            default:
                return "data:" . $this->mimeType . ";base64," . base64_encode($this->data);
        }
    }
}
