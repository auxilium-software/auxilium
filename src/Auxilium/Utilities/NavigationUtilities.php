<?php

namespace Auxilium\Utilities;

use JetBrains\PhpStorm\NoReturn;

class NavigationUtilities
{
    #[NoReturn] public static function Redirect(string $target): void
    {
        header("Location: " . $target);
        die();
    }
}
