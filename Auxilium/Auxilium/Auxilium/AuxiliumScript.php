<?php

namespace Auxilium\Auxilium;

use Auxilium\DatabaseInteractions\Deegraph\DeegraphNode;
use Auxilium\DatabaseInteractions\GraphDatabaseConnection;

class AuxiliumScript
{
    public static function evaluate_expression(string $string, array $vars)
    {
        $string = trim($string);
        if(str_starts_with($string, "\$"))
        {
            return AuxiliumScript::evaluate_variable_path($string, $vars);
        }
        elseif(str_starts_with($string, "\""))
        {
            $id = 1;
            $output = "";
            while($id < strlen($string))
            {
                $contents = substr($string, $id, 1);
                if($contents == "\\")
                {
                    $id++;
                    $output .= substr($string, $id, 1);
                }
                elseif($contents == "\"")
                {
                    break;
                }
                else
                {
                    $output .= $contents;
                }
                $id++;
            }
            return $output;
        }
        else
        {
            $id = strpos($string, "(");
            $fn = substr($string, 0, $id);
            $bl = 1;
            $id++;
            $args = [];
            $arg = "";
            while(($bl > 0) && ($id < strlen($string)))
            {
                $contents = substr($string, $id, 1);
                if($contents == "(")
                {
                    $arg .= "(";
                    $bl++;
                }
                elseif($contents == ")")
                {
                    if($bl != 1)
                    {
                        $arg .= ")";
                    }
                    $bl--;
                }
                elseif($contents == ",")
                {
                    if($bl == 1)
                    {
                        $args[] = $arg;
                        $arg = "";
                    }
                    else
                    {
                        $arg .= ",";
                    }
                }
                else
                {
                    $arg .= $contents;
                }
                $id++;
            }
            $args[] = $arg;
            switch(strtolower($fn))
            {
                case "true":
                    return true;
                case "false":
                    return false;
                case "not":
                    return !AuxiliumScript::evaluate_expression($args[0], $vars);
                case "exists":
                    $expr = AuxiliumScript::evaluate_expression($args[0], $vars);
                    return !($expr == null || (is_string($expr) ? strlen($expr) == 0 : false));
                case "or":
                    for($i = 0; $i < count($args); $i++)
                    {
                        if(AuxiliumScript::evaluate_expression($args[$i], $vars) == true)
                        {
                            return true;
                        }
                    }
                    return false;
                case "and":
                    for($i = 0; $i < count($args); $i++)
                    {
                        if(AuxiliumScript::evaluate_expression($args[$i], $vars) == false)
                        {
                            return false;
                        }
                    }
                    return true;
                case "eq":
                case "equals":
                    $evali0 = AuxiliumScript::evaluate_expression($args[0], $vars);
                    for($i = 1; $i < count($args); $i++)
                    {
                        $evalin = AuxiliumScript::evaluate_expression($args[$i], $vars);
                        if($evali0 != $evalin)
                        {
                            return false;
                        }
                    }
                    return true;
                case "concat":
                    $evald_args = [];
                    for($i = 0; $i < count($args); $i++)
                    {
                        $evald_args[] = AuxiliumScript::evaluate_expression($args[$i], $vars);
                    }
                    return implode("", $evald_args);
                default:
                    return null;
            }
        }
    }

    public static function evaluate_variable_path(string $string, array $vars)
    {
        if(str_starts_with($string, "\$"))
        {
            $pth = explode("/", $string);
            $st = substr(array_shift($pth), 1);
            if(isset($vars[$st]))
            {
                if(is_a($vars[$st], DeegraphNode::class))
                {
                    array_unshift($pth, "{" . $vars[$st]->getId() . "}");
                    $fcn = "@view";
                    if(str_starts_with(end($pth), "@"))
                    {
                        $fcn = array_pop($pth);
                    }
                    $string = implode("/", $pth);
                    switch($fcn)
                    {
                        case "@created":
                            $node = GraphDatabaseConnection::node_from_path($string);
                            return ($node == null) ? null : $node->getTimestamp();
                        case "@schema":
                            $node = GraphDatabaseConnection::node_from_path($string);
                            return ($node == null) ? null : $node->getSchemaUrl();
                        case "@creator":
                            $node = GraphDatabaseConnection::node_from_path($string);
                            return ($node == null) ? null : $node->getCreator();
                        case "@creator_id":
                            $node = GraphDatabaseConnection::node_from_path($string);
                            if($node != null)
                            {
                                $node = $node->getCreator();
                            }
                            return ($node == null) ? null : $node->getId();
                        case "@id":
                            $node = GraphDatabaseConnection::node_from_path($string);
                            return ($node == null) ? null : $node->getId();
                        case "@view":
                            return GraphDatabaseConnection::node_from_path($string);
                    }
                    return null;
                }
                else
                {
                    $string = $vars[$st];
                }
            }
            else
            {
                return null;
            }
        }
        elseif(str_starts_with($string, "\\\$"))
        {
            $string = substr($string, 1);
        }
        return $string;
    }
}
