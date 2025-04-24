<?php

namespace Auxilium\Auxilium\API\Controllers;

use Auxilium\APITools;
use Auxilium\Auxilium\API\APITools2;
use Auxilium\Auxilium\API\Models\IndexModel;
use Auxilium\Auxilium\API\Superclasses\APIController;
use Auxilium\Helpers\PDF\PDFGeneration;
use Auxilium\Utilities\URIUtilities;
use JetBrains\PhpStorm\NoReturn;
use OpenApi\Attributes\Get;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Response;

class PDFController extends APIController
{

    public function __construct()
    {
        parent::__construct();
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
            new Response(
                response: 200,
                description: "",
                // content:
            )
        ],
        deprecated: false,
    )]
    public function Get(): void
    {
        $at = APITools::get_instance();
        $at->requireLogin();

        $uri = new URIUtilities();
        $type = $uri->getURIComponents()[4];
        $uuid = $uri->getURIComponents()[5];

        switch($type)
        {
            case "case":
                PDFGeneration::GenerateCaseOverviewPage(caseID: "{{$uuid}}")->Render();
            default:
                $at->setErrorText(value: "PDF Type not recognized");
                $at->output();
        }
    }
}
