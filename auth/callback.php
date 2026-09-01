<?php
require __DIR__ . '/session_init.php';

$config = require '/www/config/9h_auth.php';

function errorPage($msg) {
    http_response_code(200);
    ?>
<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>登录失败 · YourLink短链接</title><meta name="robots" content="noindex, nofollow"><style>
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#0f0f0f;min-height:100vh;display:flex;align-items:center;justify-content:center;margin:0}
.card{background:#fff;border-radius:16px;padding:40px 36px;max-width:420px;width:90%;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.4)}
.icon{font-size:48px;margin-bottom:16px;display:block}
h1{font-size:20px;font-weight:800;color:#111827;margin:0 0 8px}
p{font-size:14px;color:#6b7280;margin:0 0 24px;line-height:1.5}
.btn{display:inline-block;padding:12px 28px;background:#667eea;color:#fff;border:none;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;text-decoration:none;transition:all .25s;font-family:inherit}
.btn:hover{transform:translateY(-2px);box-shadow:0 8px 25px rgba(102,126,234,.4)}
</style></head><body><div class="card"><span class="icon">🔐</span>
<h1>登录遇到问题</h1><p><?= htmlspecialchars($msg) ?></p>
<a href="/login" class="btn">重新登录</a>
</div></body></html>
<?php
    exit;
}

if (!isset($_GET['state']) || $_GET['state'] !== ($_SESSION['oauth_state'] ?? '')) {
    errorPage('登录状态已过期，请重新登录。');
}
unset($_SESSION['oauth_state']);

if (isset($_GET['error'])) {
    errorPage('授权失败: ' . ($_GET['error_description'] ?? $_GET['error']));
}

$code = $_GET['code'] ?? '';
if (empty($code)) {
    errorPage('未收到授权码，请重新登录。');
}

// 通过香港服务器代理获取 token
$proxyBase = 'http://YOUR_PROXY_SERVER:8081/token.php?target=';
$target = 'https://connect.linux.do/oauth2/token';
$ch = curl_init($proxyBase . urlencode(base64_encode($target)));
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'grant_type' => 'authorization_code',
        'client_id' => $config['client_id'],
        'client_secret' => $config['client_secret'],
        'code' => $code,
        'redirect_uri' => $config['redirect_uri'],
    ]),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    CURLOPT_TIMEOUT => 20,
]);
$tokenResp = json_decode(curl_exec($ch), true);
curl_close($ch);

if (empty($tokenResp['access_token'])) {
    errorPage('登录授权失败，请重试。');
}

// 通过香港服务器代理获取用户信息
$target = 'https://connect.linux.do/api/user';
$ch = curl_init($proxyBase . urlencode(base64_encode($target)));
curl_setopt_array($ch, [
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $tokenResp['access_token']],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 20,
]);
$output = curl_exec($ch);
$curlError = curl_error($ch);
curl_close($ch);

$userResp = json_decode($output, true);
if (empty($userResp['id'])) {
    errorPage('获取用户信息失败，请重试。');
}

$avatarUrl = '';
if (!empty($userResp['avatar_template'])) {
    $avatarUrl = $userResp['avatar_template'];
    if (strpos($avatarUrl, '//') === 0) $avatarUrl = 'https:' . $avatarUrl;
    $avatarUrl = str_replace('{size}', '100', $avatarUrl);
}

$_SESSION['user'] = [
    'id' => $userResp['id'],
    'username' => $userResp['username'] ?? '',
    'name' => $userResp['name'] ?? '',
    'avatar' => $avatarUrl,
    'trust' => $userResp['trust_level'] ?? 0,
];
$_SESSION['logged_in'] = true;

$redirect = $_SESSION['login_redirect'] ?? '/';
unset($_SESSION['login_redirect']);
header('Location: ' . $redirect);
exit;