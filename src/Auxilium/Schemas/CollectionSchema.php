<?php

namespace Auxilium\Schemas;

use Darksparrow\AuxiliumSchemaBuilder\Attributes\SchemaDocument;

#[SchemaDocument(
    Name: "collection",
    MaxSize: 0,
    Comment: "This is intended as an inheritable schema used to signify to software that it SHOULD display the numeric properties of this node as an inlined list",
)]
class CollectionSchema
{

}