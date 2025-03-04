<?php

namespace Auxilium\SessionHandling;

use Auxilium\Utilities\NavigationUtilities;

class Security
{
    /**
     * If called, this function will check to see if the user is logged in.
     * If they are not, they are redirected to the login page.
     */
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
            NavigationUtilities::Redirect(target: "/login");
        }
    }
}
