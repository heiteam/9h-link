<?php
/**
 * 短链接审核后台
 * - 管理员权限由 config.php 的 admin_users 控制
 * - 支持通过/拒绝/删除短链接
 */
require __DIR__ . '/auth/session_init.php';
require __DIR__ . '/admin_check.php';

// ===== 权限检查 =====
function is_admin() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true
        && is_admin_user();
}

$user = $_SESSION['user'] ?? [];

// ===== 处理 POST 审核操作 =====
$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_admin()) {
    $code = preg_replace('/[^A-Za-z0-9]/', '', $_POST['code'] ?? '');
    $action = $_POST['action'] ?? '';
    $linksFile = __DIR__ . '/links.json';
    $data = json_decode(@file_get_contents($linksFile), true);
    if (!is_array($data)) $data = [];
    $target = null;
    if (isset($data['links'][$code])) $target = &$data['links'][$code];
    elseif (isset($data[$code])) $target = &$data[$code];

    if ($target !== null && is_array($target)) {
        $adminName = $user['username'] ?? 'admin';
        if ($action === 'approve') {
            $target['status'] = 'approved';
            $target['reviewed_at'] = date('Y-m-d H:i:s');
            $target['reviewed_by'] = $adminName;
            $flash = "✅ 已通过：{$code}";
        } elseif ($action === 'reject') {
            $target['status'] = 'rejected';
            $target['reviewed_at'] = date('Y-m-d H:i:s');
            $target['reviewed_by'] = $adminName;
            $target['reject_reason'] = trim($_POST['reason'] ?? '');
            $flash = "❌ 已拒绝：{$code}";
        } elseif ($action === 'delete') {
            if (isset($data['links'][$code])) {
                unset($data['links'][$code]);
            } elseif (isset($data[$code])) {
                unset($data[$code]);
            }
            $flash = "🗑️ 已删除：{$code}";
        } elseif ($action === 'restore') {
            $target['status'] = 'pending';
            $target['reviewed_at'] = '';
            $target['reject_reason'] = '';
            $target['reviewed_by'] = '';
            $flash = "↩️ 已恢复：{$code}，重新进入待审队列";
        }
        $tmp = $linksFile . '.tmp';
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        file_put_contents($tmp, $json);
        rename($tmp, $linksFile);
    }
}

// ===== 加载链接数据 =====
$pending = []; $approved = []; $rejected = [];
$linksFile = __DIR__ . '/links.json';
$data = json_decode(@file_get_contents($linksFile), true);
if (is_array($data)) {
    $all = $data['links'] ?? $data;
    if (is_array($all)) {
        foreach ($all as $code => $v) {
            if (!is_string($code) || !preg_match('/^[A-Za-z0-9]{2,12}$/', $code)) continue;
            if (!is_array($v)) $v = ['url' => $v];
            $s = $v['status'] ?? 'approved';
            $item = [
                'code' => $code, 'url' => $v['url'] ?? '',
                'created' => $v['created'] ?? '', 'clicks' => $v['clicks'] ?? 0,
                'audit_level' => $v['audit_level'] ?? '', 'audit_reason' => $v['audit_reason'] ?? '',
                'reviewed_at' => $v['reviewed_at'] ?? '',
                'creator_user' => $v['creator_user'] ?? '', 'creator_user_id' => $v['creator_user_id'] ?? '',
                'creator_ip' => $v['creator_ip'] ?? '',
            ];
            if ($s === 'pending') $pending[] = $item;
            elseif ($s === 'rejected') $rejected[] = $item;
            else $approved[] = $item;
        }
    }
}
usort($pending, fn($a, $b) => strcmp($b['created'], $a['created']));

// ===== 邮件通知设置 =====
require_once __DIR__ . '/smtp_mail.php';
$notifyFile = __DIR__ . '/data/notify_config.json';
$notifyConfig = json_decode(@file_get_contents($notifyFile), true);
if (!is_array($notifyConfig)) $notifyConfig = ['enabled'=>true, 'email'=>'cp@your-domain.com', 'level'=>'all', 'smtp'=>['host'=>'smtp.office365.com','port'=>587,'user'=>'','pass'=>'','encryption'=>'tls','from'=>'noreply@your-domain.com','from_name'=>'YourLink']];

$notifyFlash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && is_admin()) {
    if ($_POST['action'] === 'save_notify') {
        $notifyConfig['enabled'] = !empty($_POST['notify_enabled']);
        $email = trim($_POST['notify_email'] ?? '');
        if ($email !== '') $notifyConfig['email'] = $email;
        $notifyConfig['level'] = in_array($_POST['notify_level'] ?? '', ['all','high']) ? $_POST['notify_level'] : 'all';
        $notifyConfig['smtp']['host'] = trim($_POST['smtp_host'] ?? '');
        $notifyConfig['smtp']['port'] = (int)($_POST['smtp_port'] ?? 465);
        $notifyConfig['smtp']['user'] = trim($_POST['smtp_user'] ?? '');
        if (!empty($_POST['smtp_pass'])) $notifyConfig['smtp']['pass'] = $_POST['smtp_pass'];
        $notifyConfig['smtp']['encryption'] = in_array($_POST['smtp_enc'] ?? '', ['ssl','tls','none']) ? $_POST['smtp_enc'] : 'ssl';
        $notifyConfig['smtp']['from'] = trim($_POST['smtp_from'] ?? 'noreply@your-domain.com');
        $notifyConfig['smtp']['from_name'] = trim($_POST['smtp_from_name'] ?? 'YourLink');
        file_put_contents($notifyFile, json_encode($notifyConfig, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
        $notifyFlash = '✅ 邮件提醒设置已保存';
    } elseif ($_POST['action'] === 'test_notify') {
        $testTo = trim($_POST['test_email'] ?? $notifyConfig['email']);
        $testSubject = 'YourLink SMTP 测试 - ' . date('Y-m-d H:i:s');
        $testBody = "这是一封测试邮件，来自 YourLink 短链接审核系统。

发送时间: " . date('Y-m-d H:i:s') . "
如果你收到这封邮件，说明 SMTP 配置正确。";
        $result = smtp_send($testTo, $testSubject, $testBody, $notifyConfig['smtp']);
        $notifyFlash = $result ? '✅ 测试邮件已发送成功，请检查 ' . htmlspecialchars($testTo) : '❌ 发送失败，请检查 SMTP 配置（确认微软365已开启SMTP AUTH、密码正确）';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>短链接审核 · YourLink管理</title>
<meta name="robots" content="noindex, nofollow">
<style>
:root{--primary:#667eea;--text:#111827;--text-2:#374151;--text-3:#6b7280;--bg:#f9fafb;--card:#fff;--border:#e5e7eb;--green:#10b981;--red:#ef4444;--amber:#f59e0b}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:var(--bg);color:var(--text);min-height:100vh}
.container{max-width:960px;margin:0 auto;padding:24px 16px 60px}
.topbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;padding-bottom:16px;border-bottom:2px solid var(--border)}
.topbar h1{font-size:20px;font-weight:800}
.topbar .sub{font-size:12px;color:var(--text-3);margin-top:2px}
.user-info{display:flex;align-items:center;gap:10px;background:var(--card);border:1px solid var(--border);border-radius:8px;padding:8px 14px;font-size:13px}
.user-info img{width:28px;height:28px;border-radius:50%;border:2px solid var(--primary)}
.card{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:20px;margin-bottom:16px;box-shadow:0 1px 3px rgba(0,0,0,.05)}
.counts{display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap}
.count-box{background:var(--card);border:1px solid var(--border);border-radius:10px;padding:14px 20px;flex:1;min-width:120px;text-align:center}
.count-box .num{font-size:24px;font-weight:800}
.count-box .lbl{font-size:12px;color:var(--text-3);margin-top:4px}
.badge{display:inline-block;font-size:11px;font-weight:600;padding:2px 8px;border-radius:4px}
.badge.pending{background:#fef3c7;color:#92400e}
.badge.approved{background:#d1fae5;color:#065f46}
.badge.rejected{background:#fee2e2;color:#991b1b}
.badge.block{background:#fee2e2;color:#991b1b}
.badge.review{background:#fef3c7;color:#92400e}
.badge.pass{background:#d1fae5;color:#065f46}
.item{border:1px solid var(--border);border-radius:10px;padding:16px;margin-bottom:12px;background:#fff}
.item .code-row{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap}
.item .code{font-weight:800;font-size:15px;color:var(--primary);font-family:monospace}
.item .url{font-size:13px;color:var(--text-2);word-break:break-all;margin:6px 0;background:#f9fafb;padding:8px 10px;border-radius:6px;border:1px solid var(--border)}
.item .meta{font-size:11px;color:var(--text-3);margin-bottom:10px}
.item .meta span{margin-right:12px}
.actions{display:flex;gap:8px;flex-wrap:wrap}
.btn{display:inline-flex;align-items:center;gap:4px;padding:8px 16px;border-radius:8px;border:none;font-size:13px;font-weight:600;cursor:pointer;text-decoration:none;transition:all .2s;font-family:inherit}
.btn-approve{background:var(--green);color:#fff}
.btn-approve:hover{background:#059669}
.btn-reject{background:var(--red);color:#fff}
.btn-reject:hover{background:#dc2626}
.btn-outline{background:#fff;border:1px solid var(--border);color:var(--text-2)}
.btn-outline:hover{border-color:var(--primary);color:var(--primary)}
.btn-primary{background:var(--primary);color:#fff}
.btn-primary:hover{filter:brightness(1.1)}
.reject-box{display:none;margin-top:10px;padding:10px 12px;background:#fef2f2;border:1px solid #fecaca;border-radius:8px}
.reject-box.show{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.reject-box input{flex:1;min-width:150px;padding:8px 10px;border:1px solid var(--border);border-radius:6px;font-size:13px;font-family:inherit}
.reject-box .btn{background:var(--red);color:#fff}
.flash{background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:14px}
.unauth{max-width:480px;margin:80px auto;text-align:center;padding:40px;background:var(--card);border:1px solid var(--border);border-radius:16px;box-shadow:0 10px 40px rgba(0,0,0,.08)}
.unauth .icon{font-size:48px;margin-bottom:16px;display:block}
.unauth h2{font-size:18px;font-weight:800;margin-bottom:6px}
.unauth p{font-size:14px;color:var(--text-2);margin-bottom:20px;line-height:1.6}
.unauth .current-user{font-size:13px;color:var(--text-3);margin-bottom:20px;padding:8px 14px;background:#f3f4f6;border-radius:8px;display:inline-block}
.sec-title{font-size:16px;font-weight:800;margin:24px 0 12px;display:flex;align-items:center;gap:8px}
.toggle-view{background:none;border:none;color:var(--primary);font-size:12px;cursor:pointer;text-decoration:underline;font-family:inherit}
.empty{text-align:center;color:var(--text-3);padding:40px 0;font-size:14px}
</style>
</head>
<body>
<div class="container">
<?php if (!is_admin()): ?>
  <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
    <!-- 已登录但不是管理员 -->
    <div class="unauth">
      <span class="icon">🚫</span>
      <h2>无管理员权限</h2>
      <p>当前账号 <strong><?= htmlspecialchars($user['username'] ?? '?') ?></strong> 不是审核管理员。<br>审核功能仅限指定账号使用。</p>
      <div class="current-user">👤 <?= htmlspecialchars($user['username'] ?? '') ?></div>
      <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap">
        <a href="/" class="btn btn-outline">← 返回首页</a>
        <a href="/auth/logout.php" class="btn btn-outline">退出登录</a>
      </div>
    </div>
  <?php else: ?>
    <!-- 未登录 -->
    <div class="unauth">
      <span class="icon">🔒</span>
      <h2>短链接审核后台</h2>
      <p>请使用 Linux.do 账号登录后进行审核管理。</p>
      <a href="/auth/login.php" class="btn btn-primary" style="padding:12px 32px;font-size:15px">🔑 使用 Linux.do 登录</a>
      <p style="margin-top:16px;font-size:12px;color:var(--text-3)"><a href="/" style="color:var(--primary)">← 返回首页</a></p>
    </div>
    <?php $_SESSION['login_redirect'] = '/review'; ?>
  <?php endif; ?>
<?php else: ?>
  <!-- 管理员界面 -->
  <div class="topbar">
    <div>
      <h1>📋 短链接审核</h1>
      <p class="sub">YourLink · 短链接安全管理</p>
    </div>
    <div class="user-info">
      <img src="<?= htmlspecialchars($user['avatar'] ?? '') ?>" alt="" onerror="this.style.display='none'">
      <span><?= htmlspecialchars($user['username'] ?? '') ?></span>
      <a href="/auth/logout.php" class="btn btn-outline" style="padding:4px 10px;font-size:11px">退出</a>
    </div>
  </div>

  <?php if ($flash): ?><div class="flash"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
  <?php if ($notifyFlash): ?><div class="flash"><?= htmlspecialchars($notifyFlash) ?></div><?php endif; ?>

  <!-- 邮件提醒设置（默认折叠） -->
  <div class="card" style="margin-bottom:16px">
    <div onclick="var e=this.nextElementSibling;e.style.display=e.style.display==='none'?'block':'none';this.querySelector('.toggle-i').textContent=e.style.display==='none'?'▶':'▼'" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;cursor:pointer;user-select:none">
      <div><strong style="font-size:14px">📧 邮件提醒设置</strong>
      <span style="font-size:12px;color:var(--text-3);margin-left:8px"><span class="toggle-i">▶</span> 点击展开</span></div>
    </div>
    <div style="display:none">
    <form method="POST" style="margin-top:12px">
      <input type="hidden" name="action" value="save_notify">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <div>
          <label style="font-size:11px;color:var(--text-3);display:block;margin-bottom:4px">SMTP 服务器</label>
          <input type="text" name="smtp_host" value="<?= htmlspecialchars($notifyConfig['smtp']['host'] ?? '') ?>" placeholder="smtp.office365.com" style="width:100%;padding:6px 10px;border:1px solid var(--border);border-radius:6px;font-size:12px;font-family:inherit">
        </div>
        <div>
          <label style="font-size:11px;color:var(--text-3);display:block;margin-bottom:4px">端口</label>
          <input type="number" name="smtp_port" value="<?= (int)($notifyConfig['smtp']['port'] ?? 465) ?>" style="width:100%;padding:6px 10px;border:1px solid var(--border);border-radius:6px;font-size:12px;font-family:inherit">
        </div>
        <div>
          <label style="font-size:11px;color:var(--text-3);display:block;margin-bottom:4px">用户名</label>
          <input type="text" name="smtp_user" value="<?= htmlspecialchars($notifyConfig['smtp']['user'] ?? '') ?>" placeholder="your@qq.com" style="width:100%;padding:6px 10px;border:1px solid var(--border);border-radius:6px;font-size:12px;font-family:inherit">
        </div>
        <div>
          <label style="font-size:11px;color:var(--text-3);display:block;margin-bottom:4px">密码</label>
          <input type="password" name="smtp_pass" value="" placeholder="(不修改请留空)" style="width:100%;padding:6px 10px;border:1px solid var(--border);border-radius:6px;font-size:12px;font-family:inherit">
        </div>
        <div>
          <label style="font-size:11px;color:var(--text-3);display:block;margin-bottom:4px">加密方式</label>
          <select name="smtp_enc" style="width:100%;padding:6px 10px;border:1px solid var(--border);border-radius:6px;font-size:12px;font-family:inherit">
            <option value="ssl" <?= ($notifyConfig['smtp']['encryption']??'ssl')==='ssl'?'selected':'' ?>>SSL</option>
             <option value="tls" <?= ($notifyConfig['smtp']['encryption']??'')==='tls'?'selected':'' ?>>STARTTLS</option>
            <option value="none" <?= ($notifyConfig['smtp']['encryption']??'')==='none'?'selected':'' ?>>无加密</option>
          </select>
        </div>
        <div>
          <label style="font-size:11px;color:var(--text-3);display:block;margin-bottom:4px">发件人邮箱</label>
          <input type="email" name="smtp_from" value="<?= htmlspecialchars($notifyConfig['smtp']['from'] ?? 'noreply@your-domain.com') ?>" style="width:100%;padding:6px 10px;border:1px solid var(--border);border-radius:6px;font-size:12px;font-family:inherit">
        </div>
      </div>
      <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-top:10px">
        <label style="font-size:12px;color:var(--text-2);display:flex;align-items:center;gap:4px;cursor:pointer">
          <input type="checkbox" name="notify_enabled" value="1" <?= $notifyConfig['enabled'] ? 'checked' : '' ?>>
          启用邮件提醒
        </label>
        <input type="email" name="notify_email" value="<?= htmlspecialchars($notifyConfig['email']) ?>" placeholder="接收通知的邮箱" style="padding:6px 10px;border:1px solid var(--border);border-radius:6px;font-size:12px;font-family:inherit;width:200px">
        <select name="notify_level" style="padding:6px 10px;border:1px solid var(--border);border-radius:6px;font-size:12px;font-family:inherit">
          <option value="all" <?= $notifyConfig['level']==='all'?'selected':'' ?>>所有待审提醒</option>
          <option value="high" <?= $notifyConfig['level']==='high'?'selected':'' ?>>仅高风险待审提醒</option>
        </select>
        <button type="submit" class="btn btn-outline" style="font-size:12px;padding:6px 14px">保存设置</button>
      </div>
    </form>
    <div style="font-size:11px;color:var(--text-3);margin-top:8px">
      当前状态: <?= $notifyConfig['enabled'] ? '✅ 已启用' : '⏸️ 已暂停' ?> · 
      接收邮箱: <?= htmlspecialchars($notifyConfig['email']) ?> · 
      SMTP: <?= htmlspecialchars($notifyConfig['smtp']['host'] ?? '未配置') ?>:<?= (int)($notifyConfig['smtp']['port'] ?? 0) ?>
    </div>
    <!-- 测试按钮 -->
    <form method="POST" style="display:flex;gap:8px;align-items:center;margin-top:10px;padding-top:10px;border-top:1px solid var(--border)">
      <input type="hidden" name="action" value="test_notify">
      <span style="font-size:12px;color:var(--text-2);font-weight:600">📨 测试发送</span>
      <input type="email" name="test_email" value="<?= htmlspecialchars($notifyConfig['email']) ?>" placeholder="测试接收邮箱" style="padding:6px 10px;border:1px solid var(--border);border-radius:6px;font-size:12px;font-family:inherit;width:200px">
      <button type="submit" class="btn btn-primary" style="font-size:12px;padding:6px 14px">发送测试邮件</button>
    </form>
  </div>

  <div class="counts">
    <div class="count-box"><div class="num" style="color:var(--amber)"><?= count($pending) ?></div><div class="lbl">待审核</div></div>
    <div class="count-box"><div class="num" style="color:var(--green)"><?= count($approved) ?></div><div class="lbl">已通过</div></div>
    <div class="count-box"><div class="num" style="color:var(--red)"><?= count($rejected) ?></div><div class="lbl">已拒绝</div></div>
  </div>

  <div class="sec-title">⏳ 待审核 <span style="font-size:13px;color:var(--text-3);font-weight:400"><?= count($pending) ?> 条</span></div>
  <?php if (empty($pending)): ?>
    <div class="empty">没有待审核的短链接 🎉</div>
  <?php else: ?>
    <?php foreach ($pending as $item): ?>
    <div class="item">
      <div class="code-row">
        <div>
          <span class="code"><a href="https://your-domain.com/<?= htmlspecialchars($item['code']) ?>" target="_blank" rel="noopener noreferrer" style="color:var(--primary);text-decoration:none">your-domain.com/<?= htmlspecialchars($item['code']) ?></a> ↗</span>
          <span class="badge <?= $item['audit_level'] ?: 'review' ?>"><?= htmlspecialchars($item['audit_level'] ?: 'waiting') ?></span>
        </div>
        <span class="badge pending">待审</span>
      </div>
      <div class="url"><?= htmlspecialchars($item['url']) ?></div>
      <div class="meta">
        <span>生成时间: <?= htmlspecialchars($item['created']) ?></span>
        <span>IP: <?= htmlspecialchars($item['creator_ip'] ?: '—') ?></span>
        <span>检测: <?= htmlspecialchars($item['audit_reason'] ?: '—') ?></span>
        <?php if ($item['creator_user']): ?>
        <span>用户: <?= htmlspecialchars($item['creator_user']) ?> (ID: <?= (int)$item['creator_user_id'] ?>)</span>
        <?php endif; ?>
      </div>
      <div class="actions">
        <form method="POST" style="display:inline">
          <input type="hidden" name="action" value="approve">
          <input type="hidden" name="code" value="<?= htmlspecialchars($item['code']) ?>">
          <button type="submit" class="btn btn-approve">✅ 通过</button>
        </form>
        <button type="button" class="btn btn-reject" onclick="toggleReject(this)">❌ 拒绝</button>
        <form method="POST" style="display:inline-flex" class="reject-box" id="reject-<?= htmlspecialchars($item['code']) ?>">
          <input type="hidden" name="action" value="reject">
          <input type="hidden" name="code" value="<?= htmlspecialchars($item['code']) ?>">
          <input type="text" name="reason" placeholder="拒绝原因（选填）" style="flex:1;min-width:120px;padding:7px 10px;border:1px solid var(--border);border-radius:6px;font-size:13px;font-family:inherit">
          <button type="submit" class="btn btn-reject" style="font-size:12px;padding:7px 14px">确认拒绝</button>
          <button type="button" class="btn btn-outline" style="font-size:12px;padding:7px 14px" onclick="toggleReject(this.parentNode.previousElementSibling)">取消</button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <div class="sec-title">✅ 已通过 (<?= count($approved) ?>)
    <button class="toggle-view" onclick="toggleList('approved-list')">显示/隐藏</button>
  </div>
  <div id="approved-list" style="display:none">
    <?php if (empty($approved)): ?><div class="empty">暂无</div>
    <?php else: foreach ($approved as $item): ?>
      <div class="item">
        <div class="code-row"><span class="code"><a href="https://your-domain.com/<?= htmlspecialchars($item['code']) ?>" target="_blank" rel="noopener noreferrer" style="color:var(--primary);text-decoration:none">your-domain.com/<?= htmlspecialchars($item['code']) ?></a> ↗</span>
          <span style="font-size:11px;color:var(--text-3)">点击: <?= (int)$item['clicks'] ?></span>
        </div>
        <div class="url"><?= htmlspecialchars($item['url']) ?></div>
        <div class="meta">
          <span>生成时间: <?= htmlspecialchars($item['created']) ?></span>
          <span>IP: <?= htmlspecialchars($item['creator_ip'] ?: '—') ?></span>
          <?php if ($item['creator_user']): ?>
          <span>用户: <?= htmlspecialchars($item['creator_user']) ?> (ID: <?= (int)$item['creator_user_id'] ?>)</span>
          <?php endif; ?>
        </div>
        <div class="actions">
          <form method="POST" style="display:inline">
            <input type="hidden" name="action" value="restore">
            <input type="hidden" name="code" value="<?= htmlspecialchars($item['code']) ?>">
            <button type="submit" class="btn btn-outline" style="color:var(--green);border-color:rgba(16,185,129,.3)">↩️ 恢复</button>
          </form>
          <form method="POST" style="display:inline" onsubmit="return confirm('确认删除短链接 your-domain.com/<?= htmlspecialchars($item['code']) ?>？')">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="code" value="<?= htmlspecialchars($item['code']) ?>">
            <button type="submit" class="btn btn-outline" style="color:var(--red);border-color:rgba(239,68,68,.3)">🗑️ 删除</button>
          </form>
        </div>
      </div>
    <?php endforeach; endif; ?>
  </div>

  <div class="sec-title">❌ 已拒绝 (<?= count($rejected) ?>)
    <button class="toggle-view" onclick="toggleList('rejected-list')">显示/隐藏</button>
  </div>
  <div id="rejected-list" style="display:none">
    <?php if (empty($rejected)): ?><div class="empty">暂无</div>
    <?php else: foreach ($rejected as $item): ?>
      <div class="item">
        <div class="code-row"><span class="code"><a href="https://your-domain.com/<?= htmlspecialchars($item['code']) ?>" target="_blank" rel="noopener noreferrer" style="color:var(--primary);text-decoration:none">your-domain.com/<?= htmlspecialchars($item['code']) ?></a> ↗</span></div>
        <div class="url"><?= htmlspecialchars($item['url']) ?></div>
        <div class="meta">
          <span>生成时间: <?= htmlspecialchars($item['created']) ?></span>
          <span>IP: <?= htmlspecialchars($item['creator_ip'] ?: '—') ?></span>
          <?php if ($item['creator_user']): ?>
          <span>用户: <?= htmlspecialchars($item['creator_user']) ?> (ID: <?= (int)$item['creator_user_id'] ?>)</span>
          <?php endif; ?>
        </div>
        <?php if (!empty($item['reject_reason'])): ?>
        <div class="reason-box" style="background:#fef2f2;border:1px solid #fecaca;border-radius:6px;padding:8px 10px;margin-top:8px;font-size:12px;color:#991b1b">
          <strong>原因：</strong><?= htmlspecialchars($item['reject_reason']) ?>
        </div>
        <?php endif; ?>
        <div class="actions" style="margin-top:8px">
          <form method="POST" style="display:inline">
            <input type="hidden" name="action" value="restore">
            <input type="hidden" name="code" value="<?= htmlspecialchars($item['code']) ?>">
            <button type="submit" class="btn btn-outline" style="color:var(--green);border-color:rgba(16,185,129,.3)">↩️ 恢复</button>
          </form>
          <form method="POST" style="display:inline" onsubmit="return confirm('确认删除短链接 your-domain.com/<?= htmlspecialchars($item['code']) ?>？')">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="code" value="<?= htmlspecialchars($item['code']) ?>">
            <button type="submit" class="btn btn-outline" style="color:var(--red);border-color:rgba(239,68,68,.3)">🗑️ 删除</button>
          </form>
        </div>
      </div>
    <?php endforeach; endif; ?>
  </div>

  <p style="text-align:center;font-size:12px;color:var(--text-3);margin-top:24px"><a href="/" style="color:var(--primary)">← 返回首页</a></p>
<?php endif; ?>
</div>
<script>
function toggleReject(btn) {
  var box = btn.nextElementSibling;
  if (box && box.classList) {
    box.classList.toggle('show');
  }
}
function toggleList(id) {
  var el = document.getElementById(id);
  if (el) el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
</script>
</body>
</html>