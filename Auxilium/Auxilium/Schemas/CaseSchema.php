<?php

namespace Auxilium\Schemas;

use Darksparrow\AuxiliumSchemaBuilder\Attributes\SchemaDocument;
use Darksparrow\AuxiliumSchemaBuilder\Attributes\SchemaDocumentField;
use Darksparrow\AuxiliumSchemaBuilder\Enumerators\SchemaFieldExistence;

#[SchemaDocument(
    Name   : "case",
    MaxSize: 0,
    Comment: "The case object itself SHOULD not have a value",
)]
class CaseSchema
{
    #[SchemaDocumentField(
        Name     : "title",
        Existence: SchemaFieldExistence::SHOULD,
        Comment  : "This SHOULD be a short description of what the case is about",
        MaxSize  : 2048,
        MimeType : "text/plain",
    )]
    public string $Title;

    #[SchemaDocumentField(
        Name        : "clients",
        Existence   : SchemaFieldExistence::SHOULD,
        Comment     : "This SHOULD be an 'array node' of all the clients involved in the case",
        ValidSchemas: [
            CollectionSchema::class,
        ],
        MaxSize     : 0,
        Child       : new SchemaDocumentField(
            Name        : "clients",
            Comment     : "",
            ValidSchemas: [
                UserSchema::class,
            ]
        ),
    )]
    public array $Clients;

    #[SchemaDocumentField(
        Name        : "workers",
        Existence   : SchemaFieldExistence::SHOULD,
        Comment     : "This SHOULD be an 'array node' of all staff working on the case",
        ValidSchemas: [
            CollectionSchema::class,
        ],
        MaxSize     : 0,
        Child       : new SchemaDocumentField(
            Name        : "workers",
            Comment     : "",
            ValidSchemas: [
                UserSchema::class,
                OrganisationSchema::class,
            ]
        ),
    )]
    public array $Workers;

    #[SchemaDocumentField(
        Name        : "documents",
        Existence   : SchemaFieldExistence::SHOULD,
        Comment     : "This SHOULD be an 'array node' of all the case documents",
        ValidSchemas: [
            CollectionSchema::class,
        ],
        MaxSize     : 0,
        Child       : new SchemaDocumentField(
            Name        : "documents",
            Comment     : "",
            ValidSchemas: [
                DocumentSchema::class,
            ]
        ),
    )]
    public array $Documents;

    #[SchemaDocumentField(
        Name        : "messages",
        Existence   : SchemaFieldExistence::SHOULD,
        Comment     : "This SHOULD be an 'array node' of all messages that relate to this case",
        ValidSchemas: [
            CollectionSchema::class,
        ],
        MaxSize     : 0,
        Child       : new SchemaDocumentField(
            Name        : "messages",
            Comment     : "",
            ValidSchemas: [
                MessageSchema::class,
            ]
        ),
    )]
    public array $Messages;
}

