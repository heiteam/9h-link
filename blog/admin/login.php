<?php
session_start();
// 已通过 OAuth 登录
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: /blog/admin/');
    exit;
}
// Linux.do 登录且为 Boren.liu → 直接进入后台
$ldUser = $_SESSION['user'] ?? [];
$isBoren = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true
    && (($ldUser['username'] ?? '') === 'Boren.liu' || ($ldUser['name'] ?? '') === 'Boren.liu');
if ($isBoren) {
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_user'] = 'Boren.liu';
    header('Location: /blog/admin/');
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>博客后台 · 登录</title>
<link rel="stylesheet" href="/css/style.css">
<style>
.login-wrap{max-width:380px;width:100%;margin:100px auto;padding:0 20px}
.login-card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:40px 32px;box-shadow:var(--shadow);text-align:center}
.login-card h1{font-size:20px;font-weight:800;color:var(--text);margin:0 0 8px}
.login-card p{font-size:13px;color:var(--text-2);margin:0 0 24px}
.btn-ld{display:inline-flex;align-items:center;gap:8px;padding:14px 28px;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;border:none;border-radius:12px;font-size:15px;font-weight:700;cursor:pointer;text-decoration:none;transition:all .25s;font-family:inherit}
.btn-ld:hover{transform:translateY(-2px);box-shadow:0 8px 25px rgba(102,126,234,.4)}
.back{text-align:center;margin-top:20px;font-size:13px}
.back a{color:var(--primary);text-decoration:none}
</style>
</head>
<body style="background:var(--bg)">
<div class="login-wrap">
  <div class="login-card">
    <h1>🔐 博客后台</h1>
    <p>使用 Linux.do 账号登录后管理文章</p>
    <?php $_SESSION["login_redirect"] = "/blog/admin/"; ?><a href="/auth/login.php" class="btn-ld">🔑 使用 Linux.do 登录</a>
    <div class="back"><a href="/blog/">← 返回博客</a></div>
  </div>
</div>
</body>
</html>