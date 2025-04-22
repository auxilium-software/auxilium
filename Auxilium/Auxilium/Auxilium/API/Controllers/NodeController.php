<?php

namespace Auxilium\Auxilium\API\Controllers;

use Auxilium\Auxilium\API\APITools2;
use Auxilium\Auxilium\API\Models\NodeModel;
use Auxilium\DatabaseInteractions\GraphDatabaseConnection;
use Auxilium\Exceptions\DeegraphException;
use Auxilium\SessionHandling\Session;
use Darksparrow\DeegraphInteractions\DataStructures\UUID;
use JetBrains\PhpStorm\NoReturn;
use OpenApi\Attributes\Get;

class NodeController
{
    function debug_to_console($data)
    {
        $output = $data;
        if(is_array($output))
            $output = implode(',', $output);

        echo "<script>console.log('" . $output . "');</script>";
    }



    #[NoReturn]
    #[Get(
        path: "/api/v2/pdf",
        operationId: "[GET]/api/v2/pdf",
        description: "Renders a PDF",
        summary: "Renders a PDF",
        tags: [
            "PDF Generation",
        ],
        responses: [
        ],
        deprecated: false,
    )]
    public function GetNode()
    {
        $model = new NodeModel();
        $at = new APITools2($model);
        $at->requireLogin();


        $uri_components = explode("/", $_SERVER["REQUEST_URI"]);
        $last_uri_component = explode("?", end($uri_components));
        $get_params = "";
        if(count($last_uri_component) > 1)
        {
            $get_params = $last_uri_component[1];
        }
        $uri_components[count($uri_components) - 1] = $last_uri_component[0];

        $node_id = $uri_components[4];

        if(!preg_match("/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/", $node_id))
        {
            $at->setErrorText("Formatting error");
            $at->setResponseCode(400);
            $at->output();
            exit();
        }

        switch($_SERVER['REQUEST_METHOD'])
        {
            case "DELETE":
                $query = "DELETE {" . $node_id . "}";
                GraphDatabaseConnection::query(Session::get_current()->getUser(), $query);
                $at->output();
                break;
            case "GET":
            default:
                $node_info = GraphDatabaseConnection::get_raw_node_info(
                    actor: Session::get_current()->getUser(),
                    uuid : new UUID($node_id),
                );

                $model->Result = $node_info;
                $model->Request = $node_id;

                $at->output();
                break;
        }


    }
}