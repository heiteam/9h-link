<?php
session_start();
$loggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$user = $_SESSION['user'] ?? [];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>登录 · YourLink短链接</title>
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="/css/style.css">
<style>
:root{--primary:#667eea;--text:#111827;--text-2:#374151;--text-3:#6b7280;--bg:#0f0f0f;--card:#ffffff;--border:#e5e7eb;--ff:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}
.login-body{min-height:100vh;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#0f0f0f 0%,#1a1a2e 50%,#16213e 100%);margin:0}
.login-wrap{width:100%;max-width:420px;padding:0 20px}
.login-card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:40px 36px;box-shadow:0 20px 60px rgba(0,0,0,.4);text-align:center}
.login-logo{width:64px;height:64px;border-radius:16px;background:linear-gradient(135deg,var(--primary),#764ba2);display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:800;color:#fff;margin:0 auto 20px}
.login-card h1{font-size:22px;font-weight:800;color:var(--text);margin:0 0 8px}
.login-card .sub{font-size:13px;color:var(--text-3);margin:0 0 32px}
.btn-ld{display:inline-flex;align-items:center;justify-content:center;gap:10px;width:100%;padding:14px 20px;background:var(--primary);color:#fff;border:none;border-radius:12px;font-size:15px;font-weight:700;cursor:pointer;text-decoration:none;transition:all .25s;font-family:var(--ff)}
.btn-ld:hover{transform:translateY(-2px);box-shadow:0 8px 25px rgba(102,126,234,.4)}
.login-divider{display:flex;align-items:center;gap:12px;margin:20px 0;color:var(--text-3);font-size:12px}
.login-divider::before,.login-divider::after{content:"";flex:1;height:1px;background:var(--border)}
.features{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:24px}
.feature{background:#f9fafb;border:1px solid var(--border);border-radius:8px;padding:12px 8px;font-size:11px;color:var(--text-2)}
.feature .fi{display:block;font-size:18px;margin-bottom:6px}
.back-link{display:block;margin-top:20px;font-size:13px;color:var(--primary);text-decoration:none;font-weight:500}
.back-link:hover{text-decoration:underline}
.user-box{padding:12px}
.user-box img{width:56px;height:56px;border-radius:50%;margin-bottom:10px;border:2px solid var(--border)}
.user-name{font-size:16px;font-weight:700;color:var(--text);margin:0 0 2px}
.user-id{font-size:12px;color:var(--text-3);margin:0 0 16px}
.user-status{font-size:13px;color:var(--text-2);margin-bottom:16px}
.logout-link{display:inline-block;margin-top:12px;font-size:13px;color:var(--text-3);text-decoration:none;padding:6px 16px;border:1px solid var(--border);border-radius:8px;transition:all .2s}
.logout-link:hover{border-color:#dc2626;color:#dc2626}
</style>
</head>
<body class="login-body">
<div class="login-wrap">
  <div class="login-card">
    <?php if ($loggedIn): ?>
      <div class="user-box">
        <?php if (!empty($user['avatar'])): ?>
          <img src="<?= htmlspecialchars($user['avatar']) ?>" alt="avatar">
        <?php endif; ?>
        <p class="user-name"><?= htmlspecialchars($user['name'] ?? $user['username'] ?? '用户') ?></p>
        <p class="user-id">@<?= htmlspecialchars($user['username'] ?? '') ?></p>
        <p class="user-status">✅ 已通过 Linux.do 登录</p>
        <a href="/" class="btn-ld" style="background:var(--text-3)">返回首页</a>
        <a href="/auth/logout.php" class="logout-link">退出登录</a>
      </div>
    <?php else: ?>
      <div class="login-logo">YourLink</div>
      <h1>登录 YourLink短链接</h1>
      <p class="sub">仅支持 Linux.do 账号登录</p>
      <a href="/auth/login.php" class="btn-ld">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
        使用 Linux.do 登录
      </a>
      <div class="login-divider">安全认证</div>
      <div class="features">
        <div class="feature"><span class="fi">🛡️</span>OAuth2 安全认证</div>
        <div class="feature"><span class="fi">🔥</span>无需注册密码</div>
        <div class="feature"><span class="fi">🔓</span>一键授权登录</div>
      </div>
    <?php endif; ?>
    <a href="/" class="back-link">← 返回首页</a>
  </div>
</div>
</body>
</html>
