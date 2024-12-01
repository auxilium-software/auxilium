<?php


namespace Auxilium\Schemas;

use Darksparrow\AuxiliumSchemaBuilder\Attributes\SchemaDocumentChildField;
use Darksparrow\AuxiliumSchemaBuilder\Enumerators\SchemaFieldExistence;
use Darksparrow\AuxiliumSchemaBuilder\Attributes\SchemaDocument;
use Darksparrow\AuxiliumSchemaBuilder\Attributes\SchemaDocumentField;
use Darksparrow\AuxiliumSchemaBuilder\Interfaces\SchemaDocumentInterface;

#[SchemaDocument(
    Name: "organisation",
    MaxSize: 0,
    Comment: "The organisation object itself SHOULD not have a value",
    MimeType: "message/rfc822",
)]
class OrganisationSchema implements SchemaDocumentInterface
{
    #[SchemaDocumentField(
        Name: "name",
        Existence: SchemaFieldExistence::MUST,
        Comment: "This MUST be the organisation's name",
        MaxSize: 2048,
        MimeType: "test/plain",
        Children: [
            new SchemaDocumentField(
                Name: "trading_as",
                Existence: SchemaFieldExistence::SHOULD,
                MaxSize: 256,
                MIMEType: "text/plain",
                Comment: 	"This SHOULD be the organisation's short trading name, or the abbreviation they would usually go by",
            )
        ]
    )]
    public string $Name;


    #[SchemaDocumentField(
        Name: "departments",
        Existence: SchemaFieldExistence::SHOULD,
        Comment: "This SHOULD be an 'array node' of all sub-organisations if applicable",
        ValidSchemas: [
            CollectionSchema::class,
        ],
        MaxSize: 0,
        Child: new SchemaDocumentChildField(
            Comment: null,
            ValidSchemas: [
                OrganisationSchema::class,
            ],
        ),
    )]
    public array $Departments;


    #[SchemaDocumentField(
        Name: "cases",
        Existence: SchemaFieldExistence::SHOULD,
        Comment: "This SHOULD be an 'array node' of all the cases the organisation is either directly working on or the client of",
        ValidSchemas: [
            CollectionSchema::class,
        ],
        MaxSize: 0,
        Child: new SchemaDocumentChildField(
            Comment: null,
            ValidSchemas: [
                CaseSchema::class,
            ],
        ),
    )]
    public array $Cases;


    #[SchemaDocumentField(
        Name: "staff",
        Existence: SchemaFieldExistence::SHOULD,
        Comment: 	"This SHOULD be an 'array node' of all the staff that cannot be categorised into departments, or in the case of small organisations with no departments, all staff",
        ValidSchemas: [
            CollectionSchema::class,
        ],
        MaxSize: 0,
        Child: new SchemaDocumentChildField(
            Comment: null,
            ValidSchemas: [
                "https://schemas.auxiliumsoftware.co.uk/v1/user.json",
            ],
        ),
    )]
    public array $Staff;


    public function __construct(array $data)
    {
        $this->Name = $data["name"];
        $this->Departments = $data["departments"];
        $this->Cases = $data["cases"];
        $this->Staff = $data["staff"];
    }
}
