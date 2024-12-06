<?php


require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../Configuration/Configuration/Environment.php';

use Auxilium\Utilities\EncodingTools;
use PHPUnit\Framework\TestCase;

class EncodingToolsTest extends TestCase
{
    public function testURLEncodingBasic()
    {
        $start = "uwu";
        $encodedExpected = "dXd1";

        $encodedActual = EncodingTools::Base64EncodeURLSafe(data: $start);
        self::assertEquals(expected: $encodedExpected, actual: $encodedActual);
        $decodedActual = EncodingTools::Base64DecodeURLSafe(data: $encodedActual);
        self::assertEquals(expected: $start, actual: $decodedActual);
    }
    public function testURLEncodingComplex()
    {
        $start = "`~!@#$%^&*()_+-=[]{}|\;:'\",<>/?\n\t";
        $encodedExpected = "YH4hQCMkJV4mKigpXystPVtde318XDs6JyIsPD4vPwoJ";

        $encodedActual = EncodingTools::Base64EncodeURLSafe(data: $start);
        self::assertEquals(expected: $encodedExpected, actual: $encodedActual);
        $decodedActual = EncodingTools::Base64DecodeURLSafe(data: $encodedActual);
        self::assertEquals(expected: $start, actual: $decodedActual);
    }
}
