<?php
// 统一 session 初始化：确保 cookie 带 SameSite=None; Secure
// OAuth 跨域流程（your-domain.com <-> connect.linux.do）必须这样设置
ini_set('session.cookie_samesite', 'None');
ini_set('session.cookie_secure', '1');
ini_set('session.cookie_httponly', '1');
session_start();

// 已有 cookie 时 session_start() 不会重发 Set-Cookie（cookie 属性保持旧的 Lax 默认），
// 这里检测并补发，确保是 SameSite=None；新 session 时 session_start() 已发（带 None），
// headers_list() 能找到则跳过，避免双 Set-Cookie 头
$hasSetCookie = false;
foreach (headers_list() as $h) {
    if (stripos($h, 'Set-Cookie:') === 0 && strpos($h, session_name()) !== false) {
        $hasSetCookie = true;
        break;
    }
}
if (!$hasSetCookie) {
    setcookie(session_name(), session_id(), [
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'None',
    ]);
}