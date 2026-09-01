<?php
/**
 * YourLink 用户个人中心
 * - 显示 Linux.do 用户信息
 * - 列出自己生成的短链接，支持修改和删除
 * - 管理员快捷入口
 */
require __DIR__ . '/auth/session_init.php';
require __DIR__ . '/admin_check.php';

$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$user = $_SESSION['user'] ?? [];
$isAdmin = $isLoggedIn && is_admin_user();

// 未登录跳转
if (!$isLoggedIn) {
    $_SESSION['login_redirect'] = '/profile';
    header('Location: /login');
    exit;
}

$username = $user['username'] ?? '';
$linksFile = __DIR__ . '/links.json';
$data = json_decode(@file_get_contents($linksFile), true);
$allLinks = $data['links'] ?? $data ?? [];

// 处理 POST（编辑/删除）
$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isLoggedIn) {
    $action = $_POST['action'] ?? '';
    $code = preg_replace('/[^A-Za-z0-9]/', '', $_POST['code'] ?? '');
    $target = null;
    if (isset($allLinks[$code])) $target = &$allLinks[$code];
    if ($target !== null && is_array($target)) {
        // 只允许操作自己的链接
        $owner = $target['creator_user'] ?? '';
        if ($owner === $username || $isAdmin) {
            if ($action === 'delete') {
                unset($allLinks[$code]);
                $flash = '🗑️ 已删除';
            } elseif ($action === 'update') {
                $newUrl = trim($_POST['url'] ?? '');
                if ($newUrl !== '') {
                    if (!preg_match('/^https?:\/\//i', $newUrl)) $newUrl = 'https://' . $newUrl;
                    if (filter_var($newUrl, FILTER_VALIDATE_URL)) {
                        $target['url'] = $newUrl;
                        $target['updated_at'] = date('Y-m-d H:i:s');
                        $flash = '✅ 跳转链接已更新';
                    } else {
                        $flash = '❌ 链接格式不合法';
                    }
                }
            }
        }
    }
    // 写回
    if (isset($data['links'])) {
        $data['links'] = $allLinks;
    } else {
        $data = $allLinks;
    }
    $tmp = $linksFile . '.tmp';
    file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    rename($tmp, $linksFile);
}

// 重新加载显示数据
$data = json_decode(@file_get_contents($linksFile), true);
$allLinks = $data['links'] ?? $data ?? [];
$myLinks = [];
foreach ($allLinks as $code => $v) {
    if (!is_array($v)) continue;
    $owner = $v['creator_user'] ?? '';
    if ($owner === $username || ($isAdmin && $owner === '')) {
        $v['code'] = $code;
        $myLinks[] = $v;
    }
}
usort($myLinks, fn($a, $b) => strcmp($b['created'] ?? '', $a['created'] ?? ''));
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>个人中心 · YourLink</title>
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="/css/style.css">
<style>
.body-pg{max-width:720px;width:100%;margin:0 auto;padding:24px 16px 60px}
.card{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:24px;margin-bottom:16px;box-shadow:var(--shadow)}
.user-header{display:flex;align-items:center;gap:16px;margin-bottom:20px}
.user-header img{width:56px;height:56px;border-radius:50%;border:3px solid var(--primary)}
.user-header .name{font-size:18px;font-weight:800;color:var(--text)}
.user-header .sub{font-size:13px;color:var(--text-2);margin-top:2px}
.stat-row{display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap}
.stat-box{flex:1;min-width:100px;text-align:center;padding:12px;background:var(--bg);border-radius:8px;border:1px solid var(--border)}
.stat-box .num{font-size:20px;font-weight:800;color:var(--primary)}
.stat-box .lbl{font-size:11px;color:var(--text-3);margin-top:2px}
.admin-links{display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap}
.admin-links a{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;transition:all .2s}
.admin-links .review{background:rgba(245,158,11,.1);color:#92400e;border:1px solid rgba(245,158,11,.2)}
.admin-links .review:hover{background:rgba(245,158,11,.2)}
.admin-links .blog{background:rgba(102,126,234,.1);color:#3730a3;border:1px solid rgba(102,126,234,.2)}
.admin-links .blog:hover{background:rgba(102,126,234,.2)}
.item{border:1px solid var(--border);border-radius:10px;padding:16px;margin-bottom:12px;background:var(--card)}
.item .code{font-weight:800;font-size:14px;color:var(--primary);font-family:monospace}
.item .url{font-size:13px;color:var(--text-2);word-break:break-all;margin:6px 0;background:var(--bg);padding:8px 10px;border-radius:6px;border:1px solid var(--border)}
.item .meta{font-size:11px;color:var(--text-3);margin-bottom:8px}
.item .meta span{margin-right:12px}
.actions{display:flex;gap:8px;flex-wrap:wrap}
.btn{display:inline-flex;align-items:center;gap:4px;padding:7px 14px;border-radius:8px;border:none;font-size:12px;font-weight:600;cursor:pointer;text-decoration:none;transition:all .2s;font-family:inherit}
.btn-primary{background:var(--primary);color:#fff}
.btn-primary:hover{filter:brightness(1.1)}
.btn-outline{background:#fff;border:1px solid var(--border);color:var(--text-2)}
.btn-outline:hover{border-color:var(--primary);color:var(--primary)}
.btn-danger{color:var(--red);border-color:rgba(239,68,68,.3)}
.btn-danger:hover{background:#fef2f2;border-color:var(--red)}
.edit-form{display:none;margin-top:8px;padding:10px;background:var(--bg);border:1px solid var(--border);border-radius:8px;gap:8px;flex-wrap:wrap}
.edit-form.show{display:flex}
.edit-form input{flex:1;min-width:150px;padding:7px 10px;border:1px solid var(--border);border-radius:6px;font-size:13px;font-family:inherit}
.flash{background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:14px}
.empty{text-align:center;color:var(--text-3);padding:40px 0;font-size:14px}
.site-nav{position:sticky;top:0;z-index:1000;background:rgba(255,255,255,.92);backdrop-filter:blur(12px);border-bottom:1px solid var(--border);padding:0 20px;display:flex;align-items:center;justify-content:space-between;height:52px;margin:0 -24px;width:calc(100% + 48px)}
.site-nav .nav-left{display:flex;align-items:center;gap:24px}
.site-nav .nav-logo{font-size:18px;font-weight:800;color:var(--primary);text-decoration:none;letter-spacing:-.5px}
.site-nav .nav-logo span{color:var(--text)}
.site-nav .nav-links{display:flex;gap:4px}
.site-nav .nav-links a{font-size:13px;font-weight:500;color:var(--text-2);text-decoration:none;padding:6px 12px;border-radius:6px;transition:all .15s}
.site-nav .nav-links a:hover,.site-nav .nav-links a.active{color:var(--primary);background:var(--primary-light)}
.site-nav .nav-right{display:flex;align-items:center;gap:8px}
.site-nav .nav-home{font-size:13px;font-weight:500;color:var(--text-2);text-decoration:none;padding:6px 14px;border:1px solid var(--border);border-radius:8px;transition:all .15s}
.site-nav .nav-home:hover{border-color:var(--primary);color:var(--primary)}
@media(prefers-color-scheme:dark){.site-nav{background:rgba(15,15,15,.92);border-bottom-color:var(--border)}}
</style>
</head>
<body class="body-pg">
<nav class="site-nav">
  <div class="nav-left">
    <a href="/" class="nav-logo">9<span>H</span></a>
    <div class="nav-links">
      <a href="/">首页</a>
      <a href="/blog/">博客</a>
      <a href="/stats">统计</a>
      <a href="/faq">FAQ</a>
      <a href="/guide">教程</a>
      <a href="/about">关于</a>
      <a href="/contact">联系</a>
    </div>
  </div>
  <div class="nav-right">
    <a href="/" class="nav-home">← 返回首页</a>
  </div>
</nav>

<?php if ($flash): ?><div class="flash"><?= htmlspecialchars($flash) ?></div><?php endif; ?>

<div class="card">
  <div class="user-header">
    <img src="<?= htmlspecialchars($user['avatar'] ?? '') ?>" alt="" onerror="this.style.display='none'">
    <div>
      <div class="name"><?= htmlspecialchars($user['name'] ?: $user['username'] ?: '用户') ?></div>
      <div class="sub">@<?= htmlspecialchars($user['username'] ?? '') ?> · ID: <?= (int)($user['id'] ?? 0) ?></div>
    </div>
  </div>

  <?php if ($isAdmin): ?>
  <div class="admin-links">
    <a href="/review" class="review">📋 短链接审核</a>
    <a href="/blog/admin/" class="blog">✏️ 博客后台</a>
  </div>
  <?php endif; ?>

  <div class="stat-row">
    <div class="stat-box"><div class="num"><?= count($myLinks) ?></div><div class="lbl">我的短链接</div></div>
    <div class="stat-box"><div class="num"><?= array_sum(array_column($myLinks, 'clicks')) ?></div><div class="lbl">总点击</div></div>
    <div class="stat-box"><div class="num"><?= $user['trust'] ?? 0 ?></div><div class="lbl">信任等级</div></div>
  </div>
</div>

<h2 style="font-size:16px;font-weight:800;margin:0 0 12px;color:var(--text)">🔗 我的短链接</h2>
<?php if (empty($myLinks)): ?>
  <div class="empty">还没有生成过短链接</div>
<?php else: foreach ($myLinks as $link): ?>
  <div class="item">
    <div class="code"><a href="https://your-domain.com/<?= htmlspecialchars($link['code']) ?>" target="_blank" style="color:var(--primary);text-decoration:none">your-domain.com/<?= htmlspecialchars($link['code']) ?></a></div>
    <div class="url"><?= htmlspecialchars($link['url'] ?? '') ?></div>
    <div class="meta">
      <span>生成时间: <?= htmlspecialchars($link['created'] ?? '') ?></span>
      <span>状态: <?= htmlspecialchars($link['status'] ?? 'approved') ?></span>
      <span>点击: <?= (int)($link['clicks'] ?? 0) ?></span>
    </div>
    <div class="actions">
      <button class="btn btn-outline" onclick="toggleEdit(this)">✏️ 修改跳转</button>
      <form method="POST" style="display:inline" onsubmit="return confirm('确认删除 your-domain.com/<?= htmlspecialchars($link['code']) ?>？')">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="code" value="<?= htmlspecialchars($link['code']) ?>">
        <button type="submit" class="btn btn-outline btn-danger">🗑️ 删除</button>
      </form>
    </div>
    <form method="POST" class="edit-form" id="edit-<?= htmlspecialchars($link['code']) ?>">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="code" value="<?= htmlspecialchars($link['code']) ?>">
      <input type="text" name="url" value="<?= htmlspecialchars($link['url'] ?? '') ?>" placeholder="新跳转链接">
      <button type="submit" class="btn btn-primary" style="font-size:12px;padding:7px 14px">保存</button>
      <button type="button" class="btn btn-outline" style="font-size:12px;padding:7px 14px" onclick="toggleEdit(this.parentNode)">取消</button>
    </form>
  </div>
<?php endforeach; endif; ?>

<p style="text-align:center;font-size:12px;color:var(--text-3);margin-top:24px"><a href="/" style="color:var(--primary)">← 返回首页</a> · <a href="/auth/logout.php" style="color:var(--red)">退出登录</a></p>

<div class="footer" style="max-width:720px;margin:0 auto;padding:0 16px;text-align:center;margin-top:24px;font-size:12px;color:var(--text-3)">
<a href="/blog/">博客</a> · <a href="/faq">FAQ</a> · <a href="/guide">教程</a> · <a href="/stats">统计</a> · <a href="/about">关于</a> · <a href="/contact">联系</a> · <a href="/privacy-policy">隐私</a><br>© 2026 <a href="https://your-domain.com/">YourLink</a>
</div>

<script>
function toggleEdit(el) {
  var form = el.closest('.item') ? el.closest('.item').querySelector('.edit-form') : null;
  if (form) form.classList.toggle('show');
}
</script>
</body>
</html>