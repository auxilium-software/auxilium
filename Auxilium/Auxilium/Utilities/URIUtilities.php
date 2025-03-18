<?php

namespace Auxilium\Utilities;


use BadMethodCallException;

/**
 * @method array getURIComponents()
 * @method string getLastURIComponent()
 * @method string getGetParameters()
 */
class URIUtilities
{
    private array $uriComponents = [];
    private string $getParameters;

    public function __construct()
    {
        $this->uriComponents = explode(
            separator: "/",
            string   : $_SERVER["REQUEST_URI"],
        );

        $temp = explode("?", end($this->uriComponents));
        $this->getParameters = "";
        if(count($temp) > 1)
        {
            $this->getParameters = $temp[1];
        }
        $this->uriComponents[count($this->uriComponents) - 1] = $temp[0];
    }

    public function __call(string $name, array $arguments): string|array
    {
        return match ($name)
        {
            "getURIComponents" => $this->uriComponents,
            "getLastURIComponent" => explode("?", end($this->uriComponents))[0],
            "getGetParameters" => $this->getParameters,
            default => throw new BadMethodCallException("Method $name does not exist."),
        };
    }
}
