<?php


require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../environment.php';

use Auxilium\Schemas\CaseSchema;
use Auxilium\Schemas\CollectionSchema;
use Auxilium\Schemas\DocumentSchema;
use Auxilium\Schemas\EnumSchema;
use Auxilium\Schemas\MessageSchema;
use Auxilium\Schemas\OrganisationSchema;
use Auxilium\Schemas\UserSchema;
use Darksparrow\AuxiliumSchemaBuilder\SchemaBuilder\SchemaBuilder;
use Darksparrow\AuxiliumSchemaBuilder\Utilities\URLHandling;
use PHPUnit\Framework\TestCase;

class SchemaGenerationTest extends TestCase
{
    public function testCaseSchema()
    {
        self::assertEquals(
            expected: json_decode(file_get_contents(URLHandling::GetURLForSchema(CaseSchema::class)), true),
            actual: SchemaBuilder::GenerateSchema(CaseSchema::class),
        );
    }
    public function testCollectionSchema()
    {
        self::assertEquals(
            expected: json_decode(file_get_contents(URLHandling::GetURLForSchema(CollectionSchema::class)), true),
            actual: SchemaBuilder::GenerateSchema(CollectionSchema::class),
        );
    }
    public function testDocumentSchema()
    {
        self::assertEquals(
            expected: json_decode(file_get_contents(URLHandling::GetURLForSchema(DocumentSchema::class)), true),
            actual: SchemaBuilder::GenerateSchema(DocumentSchema::class),
        );
    }
    public function testEnumSchema()
    {
        self::assertEquals(
            expected: json_decode(file_get_contents(URLHandling::GetURLForSchema(EnumSchema::class)), true),
            actual: SchemaBuilder::GenerateSchema(EnumSchema::class),
        );
    }
    public function testMessageSchema()
    {
        self::assertEquals(
            expected: json_decode(file_get_contents(URLHandling::GetURLForSchema(MessageSchema::class)), true),
            actual: SchemaBuilder::GenerateSchema(MessageSchema::class),
        );
    }
    public function testOrganisationSchema()
    {
        self::assertEquals(
            expected: json_decode(file_get_contents(URLHandling::GetURLForSchema(OrganisationSchema::class)), true),
            actual: SchemaBuilder::GenerateSchema(OrganisationSchema::class),
        );
    }
    public function testUserSchema()
    {
        self::assertEquals(
            expected: json_decode(file_get_contents(URLHandling::GetURLForSchema(UserSchema::class)), true),
            actual: SchemaBuilder::GenerateSchema(UserSchema::class),
        );
    }
}
