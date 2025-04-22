<?php

namespace Auxilium\Auxilium\API\Controllers;

use Auxilium\Auxilium\API\APITools2;
use Auxilium\Auxilium\API\Models\QueryModel;
use Auxilium\DatabaseInteractions\GraphDatabaseConnection;
use Auxilium\Exceptions\DeegraphException;
use Auxilium\SessionHandling\Session;
use Auxilium\Utilities\URIUtilities;

class QueryController
{
    private QueryModel $Model;
    private APITools2 $APITools;
    private URIUtilities $URIUtilities;

    public function __construct()
    {
        $this->Model = new QueryModel();
        $this->APITools = new APITools2($this->Model);
        $this->URIUtilities = new URIUtilities();

        $this->APITools->requireLogin();
    }
    
    
    public function RunQuery(): void
    {
        $queries = [];
        $results = [];
        $paginate = false;
        $recall_key = null;
        $page_size = 8;
        $current_page = 0;

        if(isset($_POST["queries"]))
        {
            if(strlen($_POST["queries"]) > 0)
            {
                $queries = json_decode($_POST["queries"], true, 512, JSON_THROW_ON_ERROR);
            }
        }

        if(!is_array($queries))
        {
            $this->APITools->setErrorText("Queries array must be a JSON array");
            $this->APITools->output();
        }

        if(isset($_POST["query"]))
        {
            if(strlen($_POST["query"]) > 0)
            {
                $queries[] = trim($_POST["query"]);
            }
        }

        if(isset($_POST["paginate"]))
        {
            $paginate = ($_POST["paginate"] == "true");
        }

        if(isset($_POST["page_size"]))
        {
            $page_size = (int)$_POST["page_size"];
            if($page_size <= 0)
            {
                $page_size = 8;
            }
        }

        if(isset($_POST["page"]))
        {
            $current_page = (int)$_POST["page"];
            if($current_page < 0)
            {
                $current_page = 0;
            }
        }

        try
        {
            if(count($queries) > 1)
            {
                for($i = 0, $iMax = count($queries); $i < $iMax; $i++)
                {
                    $results[$i] = GraphDatabaseConnection::query(Session::get_current()->getUser(), $queries[$i]);
                }
                $this->Model->Results = $results;
                $this->Model->Queries = $queries;
                $this->APITools->output();
            }
            elseif(count($queries) > 0)
            {
                $results[0] = GraphDatabaseConnection::query(Session::get_current()->getUser(), $queries[0]);
                if($paginate)
                {
                    if(array_key_exists("@rows", $results[0]))
                    {
                        $rows = $results[0]["@rows"];
                        $keys = array_keys($rows);
                        $slice = $current_page * $page_size;
                        if($slice >= count($keys))
                        {
                            $results[0]["@rows"] = [];
                            $this->Model->ResultSlice = $results[0];
                            $this->Model->StartIndex = null;
                            $this->Model->Page = $current_page;
                        }
                        else
                        {
                            $chosen_keys = [];
                            if(($slice + $page_size) < count($keys))
                            {
                                $chosen_keys = array_slice($keys, $slice, $page_size);
                            }
                            else
                            {
                                $chosen_keys = array_slice($keys, $slice);
                            }
                            $recontituted_rows = [];
                            foreach($chosen_keys as &$key)
                            {
                                $recontituted_rows[$key] = $rows[$key];
                            }
                            $results[0]["@rows"] = $recontituted_rows;
                            $this->Model->ResultSlice = $results[0];
                            $this->Model->StartIndex = $slice;
                            $this->Model->Page = $current_page;
                        }
                    }
                    else
                    {
                        $this->Model->Result = $results[0];
                    }
                    $this->Model->Query = $queries[0];
                }
                else
                {
                    $this->Model->Result = $results[0];
                    $this->Model->Query = $queries[0];
                }
                $this->APITools->output();
            }
            else
            {
                $this->APITools->setErrorText("Missing query parameter");
                $this->APITools->output();
            }
        }
        catch(DeegraphException $e)
        {
            throw $e;
            $this->APITools->setErrorText("Query error");
            $this->APITools->setVariable("stack_trace", $e->getInnerTrace());
            $this->APITools->output();
        }

    }
    
}