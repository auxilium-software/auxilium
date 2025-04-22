<?php

namespace Auxilium\Auxilium\API\Controllers;

use Auxilium\APITools;
use Auxilium\Helpers\PDF\PDFGeneration;
use Auxilium\Utilities\URIUtilities;
use JetBrains\PhpStorm\NoReturn;
use OpenApi\Attributes\Get;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Response;

class PDFController
{
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
    public function GeneratePDF(): void
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
