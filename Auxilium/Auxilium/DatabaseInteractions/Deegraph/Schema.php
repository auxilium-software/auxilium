<?php

namespace Auxilium\DatabaseInteractions\Deegraph;

use Exception;

class Schema
{
    private static $cache = [];
    private $url = null;
    private $extends = null;
    private $data = null;

    private function __construct(string $url)
    {
        $url = trim($url);
        if(mb_strpos($url, "https://") === 0)
        {
            $this->url = $url;
        }
        else
        {
            throw new Exception("Only https:// schemas are accepted in Auxilium, '" . $url . "' is invalid");
        }
    }

    public static function from_url(?string $url)
    {
        $url = trim($url);
        if($url === null || strlen($url) === 0)
        {
            return null;
        }
        if(!isset(self::$cache[$url]))
        {
            self::$cache[$url] = new Schema($url);
        }
        return self::$cache[$url];
    }

    public function getRawDefinition()
    {
        if($this->data === null)
        {
            $curl_handle = curl_init();
            curl_setopt($curl_handle, CURLOPT_URL, $this->url);
            curl_setopt($curl_handle, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curl_handle, CURLOPT_MAXREDIRS, 32); // Let's not get into infinite loops -> Anything beyond 32 redirects is kind of silly
            curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
            $server_output = curl_exec($curl_handle);
            if(curl_getinfo($curl_handle, CURLINFO_RESPONSE_CODE) >= 200 && curl_getinfo($curl_handle, CURLINFO_RESPONSE_CODE) < 300)
            {
                if(str_starts_with($server_output, "{"))
                {
                    $this->data = json_decode($server_output, true, 512, JSON_THROW_ON_ERROR);
                }
            }
            curl_close($curl_handle);
        }
        return $this->data;
    }

    public function __toString()
    {
        return $this->url;
    }
}
