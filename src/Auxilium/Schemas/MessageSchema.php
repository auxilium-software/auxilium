<?php

namespace Auxilium\Schemas;

use Darksparrow\AuxiliumSchemaBuilder\Attributes\SchemaDocument;
use Darksparrow\AuxiliumSchemaBuilder\Attributes\SchemaDocumentField;
use Darksparrow\AuxiliumSchemaBuilder\Enumerators\SchemaFieldExistence;

#[SchemaDocument(
    Name: "message",
    MimeType: "message/rfc822",
)]
class MessageSchema
{
    #[SchemaDocumentField(
        Name: "sender",
        Existence: SchemaFieldExistence::SHOULD,
        Comment: "The sender should be attached to a user object if known",
        ValidSchemas: [
            UserSchema::class,
        ],
    )]
    public string $Sender;

    #[SchemaDocumentField(
        Name: "recipients",
        Existence: SchemaFieldExistence::SHOULD,
        Comment: "All direct recipients that are known should be attached",
        ValidSchemas: [
            CollectionSchema::class,
        ],
        MaxSize: 0,
        Child: new SchemaDocumentField(
            Name: "recipients",
            Comment: "All direct recipients should be addressed",
            ValidSchemas: [
                UserSchema::class,
            ]
        ),
    )]
    public string $Recipients;

    #[SchemaDocumentField(
        Name: "indirect_recipients",
        Existence: SchemaFieldExistence::SHOULD,
        Comment: "All cc'd recipients that are known should be attached",
        ValidSchemas: [
            CollectionSchema::class,
        ],
        MaxSize: 0,
        Child: new SchemaDocumentField(
            Name: "indirect_recipients",
            Comment: "All direct recipients should be addressed",
            ValidSchemas: [
                UserSchema::class,
            ]
        ),
    )]
    public string $IndirectRecipients;

    #[SchemaDocumentField(
        Name: "sent_at",
        Existence: SchemaFieldExistence::SHOULD,
        Comment: "The date the message was actually sent, if supplied MUST be in ISO 8601 format",
        MaxSize: 64,
        MimeType: "text/plain",
    )]
    public string $SentAt;
}
