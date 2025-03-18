<?php

namespace Auxilium\Schemas;

use Darksparrow\AuxiliumSchemaBuilder\Attributes\SchemaDocument;

#[SchemaDocument(
    Name   : "enum",
    Comment: "This is intended as an inheritable schema used to signify to software that it SHOULD be interpreted as a enumerable choice value that can be decoded based on its schema",
)]
class EnumSchema
{

}
