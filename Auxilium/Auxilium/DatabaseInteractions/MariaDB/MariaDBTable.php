<?php

namespace Auxilium\DatabaseInteractions\MariaDB;

enum MariaDBTable: string
{
    case EMAIL_VERIFICATION_CODES = "email_verification_codes";
    case FORM_PERSISTENCE_DATA = "form_persistence_data";
    case INVITE_CODES = "invite_codes";
    case OAUTH_LOGINS = "oauth_logins";
    case PORTAL_SESSIONS = "portal_sessions";
    case STANDARD_LOGINS = "standard_logins";
    case TOTP_SECRETS = "totp_secrets";
    case TOTP_USED_CODES = "totp_used_codes";
    case DATA = "data";
}
