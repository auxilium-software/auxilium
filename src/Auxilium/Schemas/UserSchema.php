<?php

namespace Auxilium\Schemas;

use Darksparrow\AuxiliumSchemaBuilder\Attributes\SchemaDocument;
use Darksparrow\AuxiliumSchemaBuilder\Attributes\SchemaDocumentField;
use Darksparrow\AuxiliumSchemaBuilder\Enumerators\SchemaFieldExistence;

#[SchemaDocument(
    Name   : "user",
    MaxSize: 0,
    Comment: "The user object itself SHOULD not have a value",
)]
class UserSchema
{
    #[SchemaDocumentField(
        Name     : "name",
        Existence: SchemaFieldExistence::MUST,
        Comment  : "This MUST be the user's full name",
        MaxSize  : 2048,
        MimeType : "text/plain",
    )]
    public string $Name;


    #[SchemaDocumentField(
        Name     : "display_name",
        Existence: SchemaFieldExistence::SHOULD,
        Comment  : "This SHOULD be the user's first name or 'preferred' name used for UI",
        MaxSize  : 256,
        MimeType : "text/plain",
    )]
    public string $DisplayName;


    #[SchemaDocumentField(
        Name     : "contact_email",
        Existence: SchemaFieldExistence::SHOULD,
        Comment  : "This SHOULD NOT be used for unverified user input",
        MaxSize  : 512,
        MimeType : "text/plain",
    )]
    public string $ContactEmail;


    #[SchemaDocumentField(
        Name        : "auxiliary_emails",
        Existence   : SchemaFieldExistence::MAY,
        Comment     : "This SHOULD be used for unverified user input instead of the 'contact_email' field",
        ValidSchemas: [
            CollectionSchema::class,
        ],
        MaxSize     : 0,
        Child       : new SchemaDocumentField(
            Name    : "auxiliary_emails",
            MaxSize : 512,
            MimeType: "text/plain",
        )
    )]
    public array $AuxiliaryEmails;


    #[SchemaDocumentField(
        Name     : "contact_phone_number",
        Existence: SchemaFieldExistence::SHOULD,
        Comment  : "This SHOULD include country code, and SHOULD NOT include spaces",
        MaxSize  : 64,
        MimeType : "text/plain",
    )]
    public string $ContactPhoneNumber;


    #[SchemaDocumentField(
        Name        : "auxiliary_phone_numbers",
        Existence   : SchemaFieldExistence::MAY,
        Comment     : "This SHOULD be used for phone numbers that need to be on record, but shouldn't be used for contact",
        ValidSchemas: [
            CollectionSchema::class,
        ],
        MaxSize     : 0,
        Child       : new SchemaDocumentField(
            Name    : "auxiliary_phone_numbers",
            Comment : "This SHOULD include country code, and SHOULD NOT include spaces",
            MaxSize : 64,
            MimeType: "text/plain",
        )
    )]
    public array $AuxiliaryPhoneNumbers;


    #[SchemaDocumentField(
        Name     : "contact_address",
        Existence: SchemaFieldExistence::SHOULD,
        Comment  : "This SHOULD be a full address, suitable for international postage",
        MimeType : "text/plain",
    )]
    public string $ContactAddress;


    #[SchemaDocumentField(
        Name        : "auxiliary_addresses",
        Existence   : SchemaFieldExistence::MAY,
        Comment     : "This SHOULD be used for addresses that need to be on record, but shouldn't be used for contact",
        ValidSchemas: [
            CollectionSchema::class,
        ],
        MaxSize     : 0,
        Child       : new SchemaDocumentField(
            Name    : "auxiliary_addresses",
            Comment : "This MAY be a full address, or a shortened address only suitable for local mail",
            MimeType: "text/plain",
        )
    )]
    public array $AuxiliaryAddresses;


    #[SchemaDocumentField(
        Name        : "documents",
        Existence   : SchemaFieldExistence::SHOULD,
        Comment     : "This SHOULD be an 'array node' of all the user's documents",
        ValidSchemas: [
            CollectionSchema::class,
        ],
        MaxSize     : 0,
        Child       : new SchemaDocumentField(
            Name        : "documents",
            ValidSchemas: [
                DocumentSchema::class,
            ]
        )
    )]
    public array $Documents;


    #[SchemaDocumentField(
        Name        : "cases",
        Existence   : SchemaFieldExistence::SHOULD,
        Comment     : "This SHOULD be an 'array node' of all the cases the user is either directly working on or the client of",
        ValidSchemas: [
            CollectionSchema::class,
        ],
        MaxSize     : 0,
        Child       : new SchemaDocumentField(
            Name        : "cases",
            ValidSchemas: [
                CaseSchema::class,
            ]
        )
    )]
    public array $Cases;


    #[SchemaDocumentField(
        Name        : "messages",
        Existence   : SchemaFieldExistence::SHOULD,
        Comment     : "This SHOULD be an 'array node' of all the user's send and recieved messages",
        ValidSchemas: [
            CollectionSchema::class,
        ],
        MaxSize     : 0,
        Child       : new SchemaDocumentField(
            Name        : "messages",
            ValidSchemas: [
                MessageSchema::class,
            ]
        )
    )]
    public array $Messages;
}
