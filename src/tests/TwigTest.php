<?php

use PHPUnit\Framework\TestCase;

class TwigTest extends TestCase
{
    public function testIntentionalFail()
    {
        $actualResult = Auxilium\MicroTemplate::ui_text(string: "uwu");
        self::assertNotEquals(expected: "uwu2", actual: $actualResult);
    }
}
