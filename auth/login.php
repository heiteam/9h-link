<?php
require __DIR__ . '/session_init.php';

$config = require '/www/config/9h_auth.php';
if (empty($config['client_id']) || empty($config['client_secret'])) {
    die('OAuth2 未配置');
}
$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;
$params = http_build_query([
    'client_id' => $config['client_id'],
    'redirect_uri' => $config['redirect_uri'],
    'response_type' => 'code',
    'scope' => $config['scope'],
    'state' => $state,
]);
header('Location: ' . $config['authorize_url'] . '?' . $params);
exit;