<?php

namespace Auxilium\Helpers\FormBuilder;

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
        if(!file_exists(LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/FormsInProgress"))
        {
            if(!mkdir($concurrentDirectory = LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/FormsInProgress", 0700, true) && !is_dir($concurrentDirectory))
            {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
            }
        }
    }

    public static function ResolveInternalVariables(&$fvars, &$internal_vars, array $targets): void
    {
        foreach($targets as $target)
        {
            //if(in_array(needle: $target, haystack: $fvars, strict: false))
            //{
            if(str_starts_with($fvars[$target], "\$"))
            {
                foreach($internal_vars as $key => &$prop)
                {
                    if(str_starts_with($fvars[$target], "\$" . $key))
                    {
                        if(is_a($prop, DeegraphNode::class))
                        {
                            $fvars[$target] = "{" . $prop->getId() . "}" . substr($fvars[$target], strlen($key) + 1);
                        }
                        else
                        {
                            $fvars[$target] = $prop . substr($fvars[$target], strlen($key) + 1);
                        }
                    }
                }
            }
            //}
        }
    }

    public static function UpdateTempFiles($form_persistence_file, $form_persistent_data): void
    {
        fwrite($form_persistence_file, json_encode($form_persistent_data, JSON_THROW_ON_ERROR));
        fclose($form_persistence_file);
    }
}
