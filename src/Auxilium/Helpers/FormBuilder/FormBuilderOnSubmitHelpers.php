<?php

namespace Auxilium\Helpers\FormBuilder;

use Auxilium\AuxiliumScript;
use Auxilium\DatabaseInteractions\Deegraph\DeegraphNode;
use Auxilium\GraphDatabaseConnection;

class FormBuilderOnSubmitHelpers
{
    public static function NewNode(&$internal_vars, &$as_node, &$action, &$fvars): void
    {
        // Handle the creation of a new node with optional schema, mime type, and content
        $schema = isset($action["schema"])          ? AuxiliumScript::evaluate_variable_path($action["schema"], $internal_vars)     : null;
        $mime_type = isset($action["mime_type"])    ? AuxiliumScript::evaluate_variable_path($action["mime_type"], $internal_vars)  : null;
        $content = isset($action["content"])        ? AuxiliumScript::evaluate_variable_path($action["content"], $internal_vars)    : null;
        // Creates a new graph node
        $out_node = GraphDatabaseConnection::new_node($content, $mime_type, $schema, $as_node);

        // Optionally store the created node in an internal variable
        if(isset($action["output_variable"]))
        {
            $internal_vars["output_var_" . $action["output_variable"]] = $out_node;
        }

        // Handles linking the new node to a specified target
        if(isset($action["target"]))
        {
            $fvars = [
                "property" => $out_node,
                "target" => $action["target"]
            ];
            // Prepare property id for the target if it is a node object
            if(is_a($fvars["property"], DeegraphNode::class))
            {
                $fvars["property"] = "{" . $fvars["property"]->getId() . "}";
            }

            // Resolve internal variable-referenced targets (target starts with "$")
            FormBuilderHelpers::ResolveInternalVariables($fvars, $internal_vars);

            // Build the query to link the node
            $query = "LINK \$property TO \$target";

            // If a name is provided, append it to the query
            if(isset($action["name"]))
            {
                $query = $query . " AS \$name";
                $fvars["name"] = AuxiliumScript::evaluate_variable_path($action["name"], $internal_vars);
            }
            // Execute the query to establish the link
            GraphDatabaseConnection::query($as_node, $query, $fvars);
        }
    }

    public static function Permission(&$internal_vars, &$as_node, &$action, &$fvars): void
    {
        if(isset($action["permissions"]) && isset($action["target"]))
        {
            $fvars = [
                // Evaluate the property and target from internal variables
                "permissions" => implode(separator: ',', array: $action['permissions']),
                "target" => $action["target"]
            ];

            // Resolve target if it references an internal variable
            FormBuilderHelpers::ResolveInternalVariables($fvars, $internal_vars);

            $query = "GRANT " . $fvars['permissions'] . ' ON ' . $fvars["target"];
            GraphDatabaseConnection::query($as_node, $query, $fvars);
        }
    }

    public static function Link(&$internal_vars, &$as_node, &$action, &$fvars): void
    {
        // Link a property to a target
        if(isset($action["property"]) && isset($action["target"]))
        {
            $fvars = [
                // Evaluate the property and target from internal variables
                "property" => AuxiliumScript::evaluate_variable_path($action["property"], $internal_vars),
                "target" => $action["target"]
            ];
            // Optionally attach a name to the link
            if(isset($action["name"]))
            {
                $fvars["name"] = $action["name"];
            }

            // Handle property as a node object
            if(is_a($fvars["property"], DeegraphNode::class))
            {
                $fvars["property"] = "{" . $fvars["property"]->getId() . "}";
            }

            // Resolve target if it references an internal variable
            FormBuilderHelpers::ResolveInternalVariables($fvars, $internal_vars);

            // Build the query to link the property to the target
            $query = "LINK \$property TO \$target";

            // If a name is present, append it to the query
            if(isset($fvars["name"]))
            {
                $query = $query . " AS \$name";
            }
            // Execute the query
            GraphDatabaseConnection::query($as_node, $query, $fvars);
        }
    }

    public static function Set(&$internal_vars, &$action): void
    {
        // Set a variable in the internal_vars array
        if(isset($action["output_variable"]))
        {
            $internal_vars["output_var_" . $action["output_variable"]] = isset($action["eval"]) ? AuxiliumScript::evaluate_expression($action["eval"], $internal_vars) : (isset($action["value"]) ? AuxiliumScript::evaluate_variable_path($action["value"], $internal_vars) : null);
        }
    }

    public static function Export(&$internal_vars, &$action, &$export): void
    {
        // Evaluate and store the result in an export variable
        $export = isset($action["eval"])
            ? AuxiliumScript::evaluate_expression($action["eval"], $internal_vars)
            : (isset($action["value"])
                ? AuxiliumScript::evaluate_variable_path($action["value"], $internal_vars)
                : null
            );
    }

    public static function Navigate(&$internal_vars, &$action, &$navigate, &$navigate_replace): void
    {
        // Handle navigation logic
        if(isset($action["replace_last_return_url"]))
        {
            $navigate_replace = $action["replace_last_return_url"];
        }
        // Evaluate or resolve navigation target
        $navigate = isset($action["eval"])
            ? AuxiliumScript::evaluate_expression($action["eval"], $internal_vars)
            : (isset($action["value"]) ? AuxiliumScript::evaluate_variable_path($action["value"], $internal_vars) : null);
    }
}