<?php
require_once "../../environment.php";

$at = \auxilium\APITools::get_instance();
$at->requireLogin();

$queries = [];
$results = [];
$paginate = false;
$recall_key = null;
$page_size = 8;
$current_page = 0;

if (isset($_POST["queries"])) {
    if (strlen($_POST["queries"]) > 0) {
        $queries = json_decode($_POST["queries"], true);
    }
}

if (!is_array($queries)) {
    $at->setErrorText("Queries array must be a JSON array");
    $at->output();
}

if (isset($_POST["query"])) {
    if (strlen($_POST["query"]) > 0) {
        array_push($queries, trim($_POST["query"]));
    }
}

if (isset($_POST["paginate"])) {
    $paginate = ($_POST["paginate"] == "true");
}

if (isset($_POST["page_size"])) {
    $page_size = intval($_POST["page_size"]);
    if ($page_size <= 0) {
        $page_size = 8;
    }
}

if (isset($_POST["page"])) {
    $current_page = intval($_POST["page"]);
    if ($current_page < 0) {
        $current_page = 0;
    }
}

try {
    if (count($queries) > 1) {
        for ($i = 0; $i < count($queries); $i++) {
            $results[$i] = \auxilium\GraphDatabaseConnection::query(\auxilium\Session::get_current()->getUser(), $queries[$i]);
        }
        $at->setVariable("results", $results);
        $at->setVariable("queries", $queries);
        $at->output();
    } elseif (count($queries) > 0) {
        $results[0] = \auxilium\GraphDatabaseConnection::query(\auxilium\Session::get_current()->getUser(), $queries[0]);
        if ($paginate) {
            if (array_key_exists("@rows", $results[0])) {
                $rows = $results[0]["@rows"];
                $keys = array_keys($rows);
                $slice = $current_page*$page_size;
                if ($slice >= count($keys)) {
                    $results[0]["@rows"] = [];
                    $at->setVariable("result_slice", $results[0]);
                    $at->setVariable("start_index", null);
                    $at->setVariable("page", $current_page);
                } else {
                    $chosen_keys = [];
                    if (($slice + $page_size) < count($keys)) {
                        $chosen_keys = array_slice($keys, $slice, $page_size);
                    } else {
                        $chosen_keys = array_slice($keys, $slice);
                    }
                    $recontituted_rows = [];
                    foreach($chosen_keys as &$key) {
                        $recontituted_rows[$key] = $rows[$key];
                    }
                    $results[0]["@rows"] = $recontituted_rows;
                    $at->setVariable("result_slice", $results[0]);
                    $at->setVariable("start_index", $slice);
                    $at->setVariable("page", $current_page);
                }
            } else {
                $at->setVariable("result", $results[0]);
            }
            $at->setVariable("query", $queries[0]);
        } else {
            $at->setVariable("result", $results[0]);
            $at->setVariable("query", $queries[0]);
        }
        $at->output();
    } else {
        $at->setErrorText("Missing query parameter");
        $at->output();
    }
} catch (\auxilium\DeegraphException $e) {
    $at->setErrorText("Query error");
    $at->setVariable("stack_trace", $e->getInnerTrace());
    $at->output();
} 

?>
