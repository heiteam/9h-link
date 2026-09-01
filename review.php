<?php
/**
 * 9H 短链接管理后台 v4
 * - 显示所有链接（按用户类型区分）
 * - 支持批量通过/拒绝
 * - 支持筛选：全部/登录用户/未登录用户/指定用户
 * - 管理员链接独立标记
 */
require __DIR__ . '/auth/session_init.php';
require __DIR__ . '/admin_check.php';
require_once __DIR__ . '/link_functions.php';
$DOMAIN = get_domain();

// ===== 权限检查 =====
function is_admin() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && is_admin_user();
}

$user = $_SESSION['user'] ?? [];
$adminUsername = $user['username'] ?? '';

// ===== 处理 POST 操作 =====
$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_admin()) {
    $code = preg_replace('/[^A-Za-z0-9]/', '', $_POST['code'] ?? '');
    $action = $_POST['action'] ?? '';
    $linksFile = __DIR__ . '/links.json';
    $data = link_load($linksFile);
    $target = $data[$code] ?? null;

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
            if (isset($data['links'][$code])) unset($data['links'][$code]);
            elseif (isset($data[$code])) unset($data[$code]);
            $flash = "🗑️ 已删除：{$code}";
        } elseif ($action === 'restore') {
            $target['status'] = 'pending';
            $target['reviewed_at'] = '';
            $target['reject_reason'] = '';
            $target['reviewed_by'] = '';
            $flash = "↩️ 已恢复：{$code}";
        }
        link_save($linksFile, $data);
    }
}

// ===== 批量操作处理 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_admin() && isset($_POST['batch_action'])) {
    $batchAction = $_POST['batch_action'];
    $codes = $_POST['batch_codes'] ?? [];
    if (!is_array($codes)) $codes = [];
    $codes = array_map(fn($c) => preg_replace('/[^A-Za-z0-9]/', '', $c), $codes);
    $codes = array_filter($codes);
    
    if (!empty($codes)) {
        $linksFile = __DIR__ . '/links.json';
        $data = link_load($linksFile);
        $adminName = $user['username'] ?? 'admin';
        $count = 0;
        
        foreach ($codes as $code) {
            if (!isset($data[$code]) || !is_array($data[$code])) continue;
            $target = &$data[$code];
            
            if ($batchAction === 'batch_approve') {
                $target['status'] = 'approved';
                $target['reviewed_at'] = date('Y-m-d H:i:s');
                $target['reviewed_by'] = $adminName;
                $count++;
            } elseif ($batchAction === 'batch_reject') {
                $target['status'] = 'rejected';
                $target['reviewed_at'] = date('Y-m-d H:i:s');
                $target['reviewed_by'] = $adminName;
                $target['reject_reason'] = trim($_POST['batch_reason'] ?? '批量拒绝');
                $count++;
            } elseif ($batchAction === 'batch_delete') {
                unset($data[$code]);
                $count++;
            }
        }
        
        link_save($linksFile, $data);
        
        $actionLabel = $batchAction === 'batch_approve' ? '通过' : ($batchAction === 'batch_reject' ? '拒绝' : '删除');
        $flash = "✅ 批量{$actionLabel}：{$count} 条链接";
    } else {
        $flash = "⚠️ 未选中任何链接";
    }
}

// ===== 加载所有链接 =====
$linksFile = __DIR__ . '/links.json';
$allLinksData = link_load($linksFile);
$allLinks = [];
$stats = ['total'=>0, 'pending'=>0, 'approved'=>0, 'rejected'=>0, 'login'=>0, 'anon'=>0, 'admin'=>0];
$users = [];

foreach ($allLinksData as $code => $v) {
    if (!is_array($v)) continue;
    $s = $v['status'] ?? 'approved';
    $creatorUser = $v['creator_user'] ?? '';
    $creatorType = $v['creator_type'] ?? ($creatorUser ? 'user' : 'anonymous');
    $isAdminLink = ($creatorUser === $adminUsername);

        $item = [
            'code' => $code,
            'url' => $v['url'] ?? '',
            'created' => $v['created'] ?? '',
            'clicks' => $v['clicks'] ?? 0,
            'status' => $s,
            'audit_level' => $v['audit_level'] ?? '',
            'audit_reason' => $v['audit_reason'] ?? '',
            'reviewed_at' => $v['reviewed_at'] ?? '',
            'creator_user' => $creatorUser,
            'creator_user_id' => $v['creator_user_id'] ?? '',
            'creator_ip' => $v['creator_ip'] ?? '',
            'creator_type' => $creatorType,
            'is_admin_link' => $isAdminLink,
        ];
        $allLinks[] = $item;
        $stats['total']++;
        $stats[$s] = ($stats[$s] ?? 0) + 1;
        if ($creatorType === 'user') $stats['login']++;
        else $stats['anon']++;
        if ($isAdminLink) $stats['admin']++;
        if ($creatorUser && !in_array($creatorUser, $users)) $users[] = $creatorUser;
    }
}

// ===== 筛选逻辑 =====
$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['q'] ?? '');
$statusFilter = $_GET['status'] ?? 'all';
$userFilter = $_GET['user'] ?? '';

$filtered = $allLinks;

// 按状态筛选
if ($statusFilter !== 'all') {
    $filtered = array_filter($filtered, fn($i) => $i['status'] === $statusFilter);
}

// 按用户类型筛选
if ($filter === 'login') {
    $filtered = array_filter($filtered, fn($i) => $i['creator_type'] === 'user');
} elseif ($filter === 'anon') {
    $filtered = array_filter($filtered, fn($i) => $i['creator_type'] !== 'user');
} elseif ($filter === 'admin') {
    $filtered = array_filter($filtered, fn($i) => $i['is_admin_link']);
}

// 按指定用户筛选
if ($userFilter !== '') {
    $filtered = array_filter($filtered, fn($i) => $i['creator_user'] === $userFilter);
}

// 搜索
if ($search !== '') {
    $q = strtolower($search);
    $filtered = array_filter($filtered, fn($i) =>
        stripos($i['code'], $q) !== false ||
        stripos($i['url'], $q) !== false ||
        stripos($i['creator_user'], $q) !== false ||
        stripos($i['creator_ip'], $q) !== false
    );
}

// 按时间倒序
usort($filtered, fn($a, $b) => strcmp($b['created'], $a['created']));
$filtered = array_values($filtered);

// ===== 邮件设置 =====
require_once __DIR__ . '/smtp_mail.php';
$notifyFile = __DIR__ . '/data/notify_config.json';
$notifyConfig = json_decode(@file_get_contents($notifyFile), true);
if (!is_array($notifyConfig)) $notifyConfig = ['enabled'=>false,'email'=>'','level'=>'all','smtp'=>['host'=>'','port'=>465,'user'=>'','pass'=>'','encryption'=>'ssl','from'=>'','from_name'=>'9H']];
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
        $notifyConfig['smtp']['from'] = trim($_POST['smtp_from'] ?? '');
        $notifyConfig['smtp']['from_name'] = trim($_POST['smtp_from_name'] ?? '9H');
        file_put_contents($notifyFile, json_encode($notifyConfig, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
        $notifyFlash = '✅ 邮件设置已保存';
    } elseif ($_POST['action'] === 'test_notify') {
        $testTo = trim($_POST['test_email'] ?? $notifyConfig['email']);
        $testSubject = '9H SMTP 测试 - ' . date('Y-m-d H:i:s');
        $testBody = "这是测试邮件，来自 9H 短链接审核系统。\n\n发送时间: " . date('Y-m-d H:i:s') . "\n收到即正常。";
        $result = smtp_send($testTo, $testSubject, $testBody, $notifyConfig['smtp']);
        $notifyFlash = $result ? "✅ 测试邮件已发送到 {$testTo}" : '❌ 发送失败，请检查 SMTP 配置';
    }
}

// ===== 构建用户筛选选项 =====
$userOptions = [];
foreach ($users as $u) {
    $count = count(array_filter($allLinks, fn($i) => $i['creator_user'] === $u));
    $userOptions[] = ['name'=>$u, 'count'=>$count];
}
usort($userOptions, fn($a,$b) => $b['count'] - $a['count']);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>链接管理 · 9H</title>
<meta name="robots" content="noindex, nofollow">
<style>
:root{--primary:#667eea;--text:#111827;--text-2:#374151;--text-3:#6b7280;--bg:#f9fafb;--card:#fff;--border:#e5e7eb;--green:#10b981;--red:#ef4444;--amber:#f59e0b;--blue:#3b82f6;--purple:#8b5cf6}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:var(--bg);color:var(--text);min-height:100vh}
.container{max-width:1060px;margin:0 auto;padding:24px 16px 60px}
.topbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;padding-bottom:16px;border-bottom:2px solid var(--border);flex-wrap:wrap;gap:12px}
.topbar h1{font-size:20px;font-weight:800}
.topbar .sub{font-size:12px;color:var(--text-3);margin-top:2px}
.user-info{display:flex;align-items:center;gap:10px;background:var(--card);border:1px solid var(--border);border-radius:8px;padding:8px 14px;font-size:13px}
.user-info img{width:28px;height:28px;border-radius:50%;border:2px solid var(--primary)}
.card{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:20px;margin-bottom:16px;box-shadow:0 1px 3px rgba(0,0,0,.05)}
.counts{display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap}
.count-box{background:var(--card);border:1px solid var(--border);border-radius:10px;padding:12px 16px;flex:1;min-width:90px;text-align:center;cursor:pointer;transition:all .2s}
.count-box:hover{border-color:var(--primary);transform:translateY(-1px)}
.count-box.active{border-color:var(--primary);background:rgba(102,126,234,.05)}
.count-box .num{font-size:22px;font-weight:800}
.count-box .lbl{font-size:11px;color:var(--text-3);margin-top:2px}
.badge{display:inline-block;font-size:10px;font-weight:600;padding:2px 7px;border-radius:4px;white-space:nowrap}
.badge.pending{background:#fef3c7;color:#92400e}
.badge.approved{background:#d1fae5;color:#065f46}
.badge.rejected{background:#fee2e2;color:#991b1b}
.badge.user{background:#eff6ff;color:#1e40af}
.badge.anon{background:#f3f4f6;color:#374151}
.badge.admin{background:#faf5ff;color:#7c3aed}
.badge.audit{background:#fef3c7;color:#92400e}
.filter-bar{display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;align-items:center}
.filter-bar select,.filter-bar input{padding:7px 12px;border:1px solid var(--border);border-radius:8px;font-size:13px;font-family:inherit;background:#fff}
.filter-bar input[type="text"]{min-width:200px}
.filter-bar select{min-width:120px}
.filter-tabs{display:flex;gap:4px;margin-bottom:16px;flex-wrap:wrap}
.filter-tab{padding:6px 14px;border:1px solid var(--border);border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;background:#fff;color:var(--text-2);text-decoration:none;transition:all .15s}
.filter-tab:hover{border-color:var(--primary);color:var(--primary)}
.filter-tab.active{background:var(--primary);color:#fff;border-color:var(--primary)}
.item{border:1px solid var(--border);border-radius:10px;padding:14px;margin-bottom:10px;background:#fff;transition:box-shadow .15s}
.item:hover{box-shadow:0 2px 8px rgba(0,0,0,.06)}
.item .code-row{display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap}
.item .code{font-weight:800;font-size:14px;color:var(--primary);font-family:monospace}
.item .url{font-size:12px;color:var(--text-2);word-break:break-all;margin:6px 0;background:#f9fafb;padding:7px 10px;border-radius:6px;border:1px solid var(--border)}
.item .meta{font-size:11px;color:var(--text-3);margin-bottom:8px;display:flex;flex-wrap:wrap;gap:6px 14px}
.item .meta span{white-space:nowrap}
.actions{display:flex;gap:6px;flex-wrap:wrap}
.btn{display:inline-flex;align-items:center;gap:4px;padding:6px 14px;border-radius:7px;border:none;font-size:12px;font-weight:600;cursor:pointer;text-decoration:none;transition:all .15s;font-family:inherit}
.btn-approve{background:var(--green);color:#fff}
.btn-reject{background:var(--red);color:#fff}
.btn-outline{background:#fff;border:1px solid var(--border);color:var(--text-2)}
.btn-outline:hover{border-color:var(--primary);color:var(--primary)}
.btn-primary{background:var(--primary);color:#fff}
.reject-box{display:none;margin-top:8px;padding:8px 10px;background:#fef2f2;border:1px solid #fecaca;border-radius:8px}
.reject-box.show{display:flex;flex-wrap:wrap;gap:6px;align-items:center}
.reject-box input[type="text"]{flex:1;min-width:120px;padding:7px 10px;border:1px solid var(--border);border-radius:6px;font-size:12px;font-family:inherit}
.flash{background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;padding:10px 14px;border-radius:8px;margin-bottom:14px;font-size:13px}
.unauth{max-width:480px;margin:80px auto;text-align:center;padding:40px;background:var(--card);border:1px solid var(--border);border-radius:16px}
.unauth .icon{font-size:48px;margin-bottom:16px;display:block}
.unauth h2{font-size:18px;font-weight:800;margin-bottom:6px}
.unauth p{font-size:14px;color:var(--text-2);margin-bottom:20px;line-height:1.6}
.sec-title{font-size:15px;font-weight:800;margin:20px 0 10px;display:flex;align-items:center;gap:8px}
.empty{text-align:center;color:var(--text-3);padding:30px 0;font-size:13px}
.batch-cb{width:16px;height:16px;accent-color:var(--primary);cursor:pointer}
.reason-box{background:#fef2f2;border:1px solid #fecaca;border-radius:6px;padding:7px 10px;margin-top:6px;font-size:11px;color:#991b1b}
.toggle-view{background:none;border:none;color:var(--primary);font-size:11px;cursor:pointer;text-decoration:underline;font-family:inherit}
.page-info{text-align:center;font-size:12px;color:var(--text-3);margin-top:16px}
</style>
</head>
<body>
<div class="container">
<?php if (!is_admin()): ?>
  <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
    <div class="unauth">
      <span class="icon">🚫</span>
      <h2>无管理员权限</h2>
      <p>当前账号 <strong><?= htmlspecialchars($user['username'] ?? '?') ?></strong> 不在管理员白名单中。</p>
      <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap">
        <a href="/" class="btn btn-outline">← 返回首页</a>
        <a href="/auth/logout.php" class="btn btn-outline">退出登录</a>
      </div>
    </div>
  <?php else: ?>
    <div class="unauth">
      <span class="icon">🔒</span>
      <h2>短链接管理后台</h2>
      <p>请使用 Linux.do 账号登录。</p>
      <a href="/auth/login.php" class="btn btn-primary" style="padding:12px 32px;font-size:15px">🔑 登录</a>
    </div>
    <?php $_SESSION['login_redirect'] = '/review'; ?>
  <?php endif; ?>
<?php else: ?>

  <!-- 管理员界面 -->
  <div class="topbar">
    <div>
      <h1>📋 链接管理</h1>
      <p class="sub">9H · 共 <?= $stats['total'] ?> 条链接</p>
    </div>
    <div class="user-info">
      <img src="<?= htmlspecialchars($user['avatar'] ?? '') ?>" alt="" onerror="this.style.display='none'">
      <span><?= htmlspecialchars($adminUsername) ?></span>
      <a href="/auth/logout.php" class="btn btn-outline" style="padding:4px 10px;font-size:11px">退出</a>
    </div>
  </div>

  <?php if ($flash): ?><div class="flash"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
  <?php if ($notifyFlash): ?><div class="flash"><?= htmlspecialchars($notifyFlash) ?></div><?php endif; ?>

  <!-- 统计概览 -->
  <div class="counts">
    <a href="?filter=all<?= $userFilter ? "&user={$userFilter}" : '' ?>" class="count-box <?= $statusFilter==='all'?'active':'' ?>">
      <div class="num" style="color:var(--primary)"><?= $stats['total'] ?></div><div class="lbl">全部</div>
    </a>
    <a href="?filter=all&status=pending<?= $userFilter ? "&user={$userFilter}" : '' ?>" class="count-box <?= $statusFilter==='pending'?'active':'' ?>">
      <div class="num" style="color:var(--amber)"><?= $stats['pending'] ?></div><div class="lbl">待审核</div>
    </a>
    <a href="?filter=all&status=approved<?= $userFilter ? "&user={$userFilter}" : '' ?>" class="count-box <?= $statusFilter==='approved'?'active':'' ?>">
      <div class="num" style="color:var(--green)"><?= $stats['approved'] ?></div><div class="lbl">已通过</div>
    </a>
    <a href="?filter=all&status=rejected<?= $userFilter ? "&user={$userFilter}" : '' ?>" class="count-box <?= $statusFilter==='rejected'?'active':'' ?>">
      <div class="num" style="color:var(--red)"><?= $stats['rejected'] ?></div><div class="lbl">已拒绝</div>
    </a>
  </div>

  <!-- 用户类型筛选 -->
  <div class="filter-tabs">
    <a href="?filter=all&status=<?= $statusFilter ?><?= $userFilter ? "&user={$userFilter}" : '' ?>" class="filter-tab <?= $filter==='all'?'active':'' ?>">全部</a>
    <a href="?filter=login&status=<?= $statusFilter ?><?= $userFilter ? "&user={$userFilter}" : '' ?>" class="filter-tab <?= $filter==='login'?'active':'' ?>">👤 登录用户 (<?= $stats['login'] ?>)</a>
    <a href="?filter=anon&status=<?= $statusFilter ?><?= $userFilter ? "&user={$userFilter}" : '' ?>" class="filter-tab <?= $filter==='anon'?'active':'' ?>">🔗 未登录 (<?= $stats['anon'] ?>)</a>
    <a href="?filter=admin&status=<?= $statusFilter ?><?= $userFilter ? "&user={$userFilter}" : '' ?>" class="filter-tab <?= $filter==='admin'?'active':'' ?>">⚙️ 管理员 (<?= $stats['admin'] ?>)</a>
  </div>

  <!-- 搜索 + 用户筛选 -->
  <div class="filter-bar">
    <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;width:100%">
      <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
      <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
      <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="搜索短码、URL、用户名、IP...">
      <select name="user">
        <option value="">所有用户</option>
        <?php foreach ($userOptions as $uo): ?>
        <option value="<?= htmlspecialchars($uo['name']) ?>" <?= $userFilter===$uo['name']?'selected':'' ?>><?= htmlspecialchars($uo['name']) ?> (<?= $uo['count'] ?>)</option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-primary" style="font-size:12px">🔍 搜索</button>
      <?php if ($search || $userFilter): ?>
      <a href="?filter=<?= htmlspecialchars($filter) ?>&status=<?= htmlspecialchars($statusFilter) ?>" class="btn btn-outline" style="font-size:12px">✕ 清除</a>
      <?php endif; ?>
    </form>
  </div>

  <!-- 链接列表 -->
  <div class="sec-title">📋 结果 (<?= count($filtered) ?> 条)</div>
  <?php if (empty($filtered)): ?>
    <div class="empty">没有匹配的链接</div>
  <?php else: ?>
  <!-- 批量操作栏 -->
  <form id="batch-form" method="POST" style="background:var(--card);border:1px solid var(--border);border-radius:10px;padding:12px 16px;margin-bottom:12px;display:flex;align-items:center;gap:10px;flex-wrap:wrap">
    <label style="font-size:12px;display:flex;align-items:center;gap:4px;cursor:pointer;font-weight:600">
      <input type="checkbox" id="batch-select-all" onchange="toggleSelectAll(this)"> 全选 (<span id="batch-count">0</span>)
    </label>
    <input type="hidden" name="batch_action" id="batch-action" value="">
    <input type="hidden" name="batch_codes[]" id="batch-codes" value="">
    <button type="button" class="btn btn-approve" onclick="submitBatch('batch_approve')" style="font-size:12px">✅ 批量通过</button>
    <button type="button" class="btn btn-reject" onclick="submitBatch('batch_reject')" style="font-size:12px">❌ 批量拒绝</button>
    <button type="button" class="btn btn-outline" onclick="submitBatch('batch_delete')" style="font-size:12px;color:var(--red);border-color:rgba(239,68,68,.3)" onsubmit="return confirm('确认批量删除选中的链接？')">🗑️ 批量删除</button>
    <input type="text" name="batch_reason" placeholder="批量拒绝原因（选填）" style="padding:6px 10px;border:1px solid var(--border);border-radius:6px;font-size:12px;width:180px">
  </form>
  <?php foreach ($filtered as $item): ?>
    <div class="item">
      <div class="code-row">
        <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">
          <input type="checkbox" class="batch-cb" value="<?= htmlspecialchars($item['code']) ?>" onchange="updateBatchCount()" style="cursor:pointer">
          <span class="code"><a href="https://<?= $DOMAIN ?>/<?= htmlspecialchars($item['code']) ?>" target="_blank" rel="noopener" style="color:var(--primary);text-decoration:none"><?= $DOMAIN ?>/<?= htmlspecialchars($item['code']) ?></a> ↗</span>
          <span class="badge <?= $item['status'] ?>"><?= $item['status']==='pending'?'待审':($item['status']==='rejected'?'已拒绝':'已通过') ?></span>
          <?php if ($item['is_admin_link']): ?>
            <span class="badge admin">管理员</span>
          <?php elseif ($item['creator_type']==='user'): ?>
            <span class="badge user">登录用户</span>
          <?php else: ?>
            <span class="badge anon">未登录</span>
          <?php endif; ?>
          <?php if ($item['audit_level']): ?>
            <span class="badge audit"><?= htmlspecialchars($item['audit_level']) ?></span>
          <?php endif; ?>
        </div>
        <span style="font-size:11px;color:var(--text-3)">点击: <?= (int)$item['clicks'] ?></span>
      </div>
      <div class="url"><?= htmlspecialchars($item['url']) ?></div>
      <div class="meta">
        <span>📅 <?= htmlspecialchars($item['created']) ?></span>
        <span>🌐 <?= htmlspecialchars($item['creator_ip'] ?: '—') ?></span>
        <?php if ($item['creator_user']): ?>
        <span>👤 <?= htmlspecialchars($item['creator_user']) ?></span>
        <?php else: ?>
        <span>👤 匿名</span>
        <?php endif; ?>
        <?php if ($item['audit_reason']): ?>
        <span>🔍 <?= htmlspecialchars($item['audit_reason']) ?></span>
        <?php endif; ?>
      </div>
      <?php if (!empty($item['reject_reason'])): ?>
        <div class="reason-box"><strong>拒绝原因：</strong><?= htmlspecialchars($item['reject_reason']) ?></div>
      <?php endif; ?>
      <div class="actions">
        <?php if ($item['status'] === 'pending'): ?>
          <form method="POST" style="display:inline"><input type="hidden" name="action" value="approve"><input type="hidden" name="code" value="<?= htmlspecialchars($item['code']) ?>"><button class="btn btn-approve">✅ 通过</button></form>
          <button class="btn btn-reject" onclick="toggleReject(this)">❌ 拒绝</button>
          <form method="POST" class="reject-box" style="display:inline-flex">
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="code" value="<?= htmlspecialchars($item['code']) ?>">
            <input type="text" name="reason" placeholder="拒绝原因（选填）">
            <button class="btn btn-reject" style="font-size:11px;padding:6px 12px">确认</button>
            <button type="button" class="btn btn-outline" style="font-size:11px" onclick="toggleReject(this)">取消</button>
          </form>
        <?php endif; ?>
        <?php if ($item['status'] !== 'pending'): ?>
          <form method="POST" style="display:inline"><input type="hidden" name="action" value="restore"><input type="hidden" name="code" value="<?= htmlspecialchars($item['code']) ?>"><button class="btn btn-outline" style="color:var(--amber)">↩️ 恢复待审</button></form>
        <?php endif; ?>
        <form method="POST" style="display:inline" onsubmit="return confirm('确认删除 <?= htmlspecialchars($item['code']) ?>？')"><input type="hidden" name="action" value="delete"><input type="hidden" name="code" value="<?= htmlspecialchars($item['code']) ?>"><button class="btn btn-outline" style="color:var(--red)">🗑️ 删除</button></form>
        <a href="/profile?highlight=<?= htmlspecialchars($item['code']) ?>" class="btn btn-outline" style="font-size:11px" target="_blank">👤 查看用户</a>
      </div>
    </div>
  <?php endforeach; endif; ?>

  <!-- 邮件设置（折叠） -->
  <div class="card" style="margin-top:24px">
    <div onclick="var e=this.nextElementSibling;e.style.display=e.style.display==='none'?'block':'none'" style="display:flex;justify-content:space-between;align-items:center;cursor:pointer;user-select:none">
      <strong style="font-size:14px">📧 邮件提醒设置 <span style="font-size:11px;color:var(--text-3);font-weight:400">▶ 点击展开</span></strong>
    </div>
    <div style="display:none">
    <form method="POST" style="margin-top:12px">
      <input type="hidden" name="action" value="save_notify">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
        <div><label style="font-size:11px;color:var(--text-3);display:block;margin-bottom:3px">SMTP</label><input type="text" name="smtp_host" value="<?= htmlspecialchars($notifyConfig['smtp']['host'] ?? '') ?>" placeholder="smtp.example.com" style="width:100%;padding:6px 10px;border:1px solid var(--border);border-radius:6px;font-size:12px"></div>
        <div><label style="font-size:11px;color:var(--text-3);display:block;margin-bottom:3px">端口</label><input type="number" name="smtp_port" value="<?= (int)($notifyConfig['smtp']['port'] ?? 465) ?>" style="width:100%;padding:6px 10px;border:1px solid var(--border);border-radius:6px;font-size:12px"></div>
        <div><label style="font-size:11px;color:var(--text-3);display:block;margin-bottom:3px">用户名</label><input type="text" name="smtp_user" value="<?= htmlspecialchars($notifyConfig['smtp']['user'] ?? '') ?>" style="width:100%;padding:6px 10px;border:1px solid var(--border);border-radius:6px;font-size:12px"></div>
        <div><label style="font-size:11px;color:var(--text-3);display:block;margin-bottom:3px">密码</label><input type="password" name="smtp_pass" placeholder="不修改留空" style="width:100%;padding:6px 10px;border:1px solid var(--border);border-radius:6px;font-size:12px"></div>
        <div><label style="font-size:11px;color:var(--text-3);display:block;margin-bottom:3px">加密</label><select name="smtp_enc" style="width:100%;padding:6px 10px;border:1px solid var(--border);border-radius:6px;font-size:12px"><option value="ssl" <?= ($notifyConfig['smtp']['encryption']??'')==='ssl'?'selected':'' ?>>SSL</option><option value="tls" <?= ($notifyConfig['smtp']['encryption']??'')==='tls'?'selected':'' ?>>STARTTLS</option><option value="none" <?= ($notifyConfig['smtp']['encryption']??'')==='none'?'selected':'' ?>>无</option></select></div>
        <div><label style="font-size:11px;color:var(--text-3);display:block;margin-bottom:3px">发件人</label><input type="email" name="smtp_from" value="<?= htmlspecialchars($notifyConfig['smtp']['from'] ?? '') ?>" style="width:100%;padding:6px 10px;border:1px solid var(--border);border-radius:6px;font-size:12px"></div>
      </div>
      <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-top:10px">
        <label style="font-size:12px;display:flex;align-items:center;gap:4px"><input type="checkbox" name="notify_enabled" value="1" <?= $notifyConfig['enabled']?'checked':'' ?>> 启用</label>
        <input type="email" name="notify_email" value="<?= htmlspecialchars($notifyConfig['email']) ?>" placeholder="接收邮箱" style="padding:6px 10px;border:1px solid var(--border);border-radius:6px;font-size:12px;width:180px">
        <select name="notify_level" style="padding:6px 10px;border:1px solid var(--border);border-radius:6px;font-size:12px"><option value="all" <?= $notifyConfig['level']==='all'?'selected':'' ?>>全部提醒</option><option value="high" <?= $notifyConfig['level']==='high'?'selected':'' ?>>仅高风险</option></select>
        <button class="btn btn-outline" style="font-size:12px">保存</button>
      </div>
    </form>
    <form method="POST" style="display:flex;gap:6px;align-items:center;margin-top:8px;padding-top:8px;border-top:1px solid var(--border)">
      <input type="hidden" name="action" value="test_notify">
      <input type="email" name="test_email" value="<?= htmlspecialchars($notifyConfig['email']) ?>" placeholder="测试邮箱" style="padding:6px 10px;border:1px solid var(--border);border-radius:6px;font-size:12px;width:180px">
      <button class="btn btn-primary" style="font-size:11px">发送测试</button>
    </form>
    </div>
  </div>

  <p style="text-align:center;font-size:12px;color:var(--text-3);margin-top:20px"><a href="/" style="color:var(--primary)">← 返回首页</a></p>
<?php endif; ?>
</div>
<script>
function toggleReject(btn) {
  var box = btn.nextElementSibling;
  if (box && box.classList) box.classList.toggle('show');
}
function toggleSelectAll(el) {
  document.querySelectorAll('.batch-cb').forEach(function(cb){ cb.checked = el.checked; });
  updateBatchCount();
}
function updateBatchCount() {
  var checked = document.querySelectorAll('.batch-cb:checked').length;
  document.getElementById('batch-count').textContent = checked;
}
function submitBatch(action) {
  var codes = [];
  document.querySelectorAll('.batch-cb:checked').forEach(function(cb){ codes.push(cb.value); });
  if (codes.length === 0) { alert('请先勾选要操作的链接'); return; }
  if (action === 'batch_delete' && !confirm('确认删除选中的 ' + codes.length + ' 条链接？')) return;
  if (action === 'batch_reject' && !confirm('确认拒绝选中的 ' + codes.length + ' 条链接？')) return;
  var input = document.getElementById('batch-codes');
  input.value = codes.join(',');
  // 将 codes 拆分为多个隐藏 input
  var form = document.getElementById('batch-form');
  // 移除旧的 hidden inputs
  form.querySelectorAll('input[name="batch_codes[]"]').forEach(function(el){ if (el.id !== 'batch-codes') el.remove(); });
  // 为每个 code 创建 hidden input
  codes.forEach(function(c){
    var h = document.createElement('input');
    h.type = 'hidden';
    h.name = 'batch_codes[]';
    h.value = c;
    form.appendChild(h);
  });
  document.getElementById('batch-action').value = action;
  form.submit();
}
</script>
</body>
</html>
