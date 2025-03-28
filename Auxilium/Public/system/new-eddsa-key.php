<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../Configuration/Configuration/Environment.php';

$at = Auxilium\APITools::get_instance();

$keypair = sodium_crypto_sign_keypair();
$secret = sodium_crypto_sign_secretkey($keypair);
$public = sodium_crypto_sign_publickey($keypair);

$at->setVariable("secret", base64_encode($secret));
$at->setVariable("public", base64_encode($public));
$at->output();
