<?php

require_once __DIR__ . '/../config.php';
/** @var $clientId */
/** @var $secret */
/** @var $tokenFile */
/** @var $spotifyRedirectUri */

require __DIR__ . '/../vendor/autoload.php';

$session = new SpotifyWebAPI\Session(
    $clientId,
    $secret,
    $spotifyRedirectUri
);

if (isset($_GET['code'])) {
    if (!$session->requestAccessToken($_GET['code'])) {
        http_response_code(400);
        echo "Error obtaining code";
        exit;
    }
    file_put_contents($tokenFile, $session->getAccessToken());
    error_log('Token written to ' . $tokenFile);
    echo 'OK';
} else {
    http_response_code(400);
    echo "?code=... missing";
}
