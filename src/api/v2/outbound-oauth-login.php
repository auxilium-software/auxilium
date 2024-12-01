<?php

require_once "../../environment.php";

//$at = \auxilium\APITools::get_instance();
//$at->setVariable("req", );
//$at->output();

$nonce = \auxilium\EncodingTools::base64_encode_url_safe(openssl_random_pseudo_bytes(16));

$uri_components = explode("/", $_SERVER["REQUEST_URI"]);
$last_uri_component = explode("?", end($uri_components))[0];

$openid_config = null;

foreach (INSTANCE_CREDENTIAL_OPENID_SOURCES as &$openid_candidate_config) {
    if ($openid_candidate_config["unique_name"] == $last_uri_component) {
        $openid_config = $openid_candidate_config;
    }
}

if ($openid_config == null) {
    $at = \auxilium\APITools::get_instance();
    $at->setErrorText("Invalid OpenID provider.");
    $at->output();
    exit();
}


$token_builder = (new \Lcobucci\JWT\Token\Builder(new \Lcobucci\JWT\Encoding\JoseEncoder(), \Lcobucci\JWT\Encoding\ChainedFormatter::default()));
$algorithm = new \Lcobucci\JWT\Signer\Eddsa();
$signing_key = \Lcobucci\JWT\Signer\Key\InMemory::base64Encoded(INSTANCE_CREDENTIAL_AUTH_JWT_EDDSA_PRIVATE_KEY);

$now = new DateTimeImmutable();
$token_builder
    ->issuedBy(INSTANCE_DOMAIN_NAME)
    ->permittedFor(INSTANCE_DOMAIN_NAME)
    ->issuedAt($now)
    ->canOnlyBeUsedAfter($now->modify("-5 minute"))
    ->withClaim("nonce", $nonce)
    ->withClaim("intent", "LOGIN")
    ->expiresAt($now->modify("+1 hour"));
    
$token = $token_builder->getToken($algorithm, $signing_key);

    //->withClaim("sub", $uri_components[0])
    
$jwt = $token->toString();

$redirect_uri = $openid_config["openid_login_uri"]."&redirect_uri=https%3A%2F%2F".INSTANCE_DOMAIN_NAME."%2Fapi%2Fv2%2Finbound-oauth-login&state=$jwt&nonce=$nonce";
header("Location: ".$redirect_uri);
exit();

?>