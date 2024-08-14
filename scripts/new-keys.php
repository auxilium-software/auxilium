<?php

$jwtKeypair = sodium_crypto_sign_keypair();
$jwtSecret = base64_encode(sodium_crypto_sign_secretkey($jwtKeypair));
$jwtPublic = base64_encode(sodium_crypto_sign_publickey($jwtKeypair));
$mysqlPassword = base64_encode(openssl_random_pseudo_bytes(24));
$deegraphRootToken = base64_encode(openssl_random_pseudo_bytes(48));
$localOnlyApiKey = base64_encode(openssl_random_pseudo_bytes(24));
$urlMetadataJwtSecret = base64_encode(openssl_random_pseudo_bytes(48));

echo json_encode([
    "jwtSecret" => $jwtSecret,
    "jwtPublic" => $jwtPublic,
    "mysqlPassword" => $mysqlPassword,
    "deegraphRootToken" => $deegraphRootToken,
    "localOnlyApiKey" => $localOnlyApiKey,
    "urlMetadataJwtSecret" => $urlMetadataJwtSecret
]);

?>
