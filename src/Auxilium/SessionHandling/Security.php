<?php

namespace Auxilium\SessionHandling;

class Security
{
    public static function RequireLogin(): void
    {
        if(Session::get_current()->sessionAuthenticated())
        {
            /*
            if (!file_exists(LOCAL_EPHEMERAL_CREDENTIAL_STORE."root-encryption-key.json")) {
                if ($_SERVER["REQUEST_URI"] != "/unlock") {
                    \Auxilium\Utilities\NavigationUtilities::Redirect(target: "/unlock");
                    exit();
                }
            }
            */
        }
        else
        {
            \Auxilium\Utilities\NavigationUtilities::Redirect(target: "/login");
        }
    }
}
