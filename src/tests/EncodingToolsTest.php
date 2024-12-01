<?php


require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../environment.php';

use Auxilium\EncodingTools;
use PHPUnit\Framework\TestCase;

class EncodingToolsTest extends TestCase
{
    public function testURLEncodingBasic()
    {
        $start = "uwu";
        $encodedExpected = "dXd1";

        $encodedActual = EncodingTools::base64_encode_url_safe(data: $start);
        self::assertEquals(expected: $encodedExpected, actual: $encodedActual);
        $decodedActual = EncodingTools::base64_decode_url_safe(data: $encodedActual);
        self::assertEquals(expected: $start, actual: $decodedActual);
    }
    public function testURLEncodingComplex()
    {
        $start = "`~!@#$%^&*()_+-=[]{}|\;:'\",<>/?\n\t";
        $encodedExpected = "YH4hQCMkJV4mKigpXystPVtde318XDs6JyIsPD4vPwoJ";

        $encodedActual = EncodingTools::base64_encode_url_safe(data: $start);
        self::assertEquals(expected: $encodedExpected, actual: $encodedActual);
        $decodedActual = EncodingTools::base64_decode_url_safe(data: $encodedActual);
        self::assertEquals(expected: $start, actual: $decodedActual);
    }
}
