<?php

namespace Auxilium\Schemas;

use Darksparrow\AuxiliumSchemaBuilder\Attributes\SchemaDocument;
use Darksparrow\AuxiliumSchemaBuilder\Attributes\SchemaDocumentField;
use Darksparrow\AuxiliumSchemaBuilder\Enumerators\SchemaFieldExistence;

#[SchemaDocument(
    Name   : "document",
    Comment: "Any given document SHOULD have metadata attached",
)]
class DocumentSchema
{
    #[SchemaDocumentField(
        Name     : "file_name",
        Existence: SchemaFieldExistence::SHOULD,
        Comment  : "The filename SHOULD NOT contain an extension, this SHOULD be added by the application based on the file's mime type",
        MaxSize  : 256,
        MimeType : "text/plain",
    )]
    public string $FileName;


    #[SchemaDocumentField(
        Name     : "created",
        Existence: SchemaFieldExistence::MAY,
        Comment  : "The creation date, if supplied MUST be in ISO 8601 format",
        MaxSize  : 64,
        MimeType : "text/plain",
    )]
    public string $Created;


    #[SchemaDocumentField(
        Name     : "modified",
        Existence: SchemaFieldExistence::MAY,
        Comment  : "The last modified date, if supplied MUST be in ISO 8601 format",
        MaxSize  : 64,
        MimeType : "text/plain",
    )]
    public string $Modified;
}