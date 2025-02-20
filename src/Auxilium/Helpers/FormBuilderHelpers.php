<?php

namespace Auxilium\Helpers;

use Auxilium\DatabaseInteractions\Deegraph\DeegraphNode;

class FormBuilderHelpers
{
    public static function CheckForPathTraversal(&$uri_components): void
    {
        if(!preg_match('/^[a-f0-9-]+$/', $uri_components[0]))
        {
            // Make sure nobody is trying anything like path traversal
            die();
        }
    }

    public static function CreateTempDirectory(): void
    {
        if(!file_exists(LOCAL_EPHEMERAL_CREDENTIAL_STORE . "FormsInProgress"))
        {
            mkdir(LOCAL_EPHEMERAL_CREDENTIAL_STORE . "FormsInProgress", 0700, true);
        }
    }

    public static function ResolveInternalVariables(&$fvars, &$internal_vars)
    {
        if(str_starts_with($fvars["target"], "\$"))
        {
            foreach($internal_vars as $key => &$prop)
            {
                if(strpos($fvars["target"], "\$" . $key) === 0)
                {
                    if(is_a($prop, DeegraphNode::class))
                    {
                        $fvars["target"] = "{" . $prop->getId() . "}" . substr($fvars["target"], strlen($key) + 1);
                    }
                    else
                    {
                        $fvars["target"] = $prop . substr($fvars["target"], strlen($key) + 1);
                    }
                }
            }
        }
    }
}
