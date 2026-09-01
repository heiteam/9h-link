<?php
session_start();
require_once __DIR__ . '/../../admin_check.php';

// 鉴权：支持两种方式进入后台
// 1. admin_logged_in（密码登录）
// 2. Linux.do 登录且在 admin_users 白名单中
$isAdminPass = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$ldUser = $_SESSION['user'] ?? [];
$isAllowed = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true
    && is_admin_user($ldUser['username'] ?? '');
if (!$isAdminPass && !$isAllowed) {
    header('Location: /blog/admin/login.php');
    exit;
}
// CSRF 防护
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$dataFile = __DIR__ . '/../data/articles.json';
$articles = json_decode(@file_get_contents($dataFile) ?: '[]', true);
if (!is_array($articles)) $articles = [];
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF 校验
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($csrfToken, $submittedToken)) {
        http_response_code(403);
        die('CSRF 校验失败，请刷新页面重试');
    }
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $id = count($articles) > 0 ? max(array_column($articles, 'id')) + 1 : 1;
        $slug = trim($_POST['slug'] ?? '');
        if (empty($slug)) $slug = 'article-' . $id;
        $slug = preg_replace('/[^a-z0-9-]/', '', strtolower($slug));
        $content = trim($_POST['content'] ?? '');
        $title = htmlspecialchars(trim($_POST['title'] ?? ''), ENT_QUOTES, 'UTF-8');
        $tag = htmlspecialchars(trim($_POST['tag'] ?? '未分类'), ENT_QUOTES, 'UTF-8');
        $contentFile = __DIR__ . '/../' . $slug . '.html';
        // 包裹完整HTML模板
        $html = '<!DOCTYPE html>' . "\n";
        $html .= '<html lang="zh-CN">' . "\n";
        $html .= '<head>' . "\n";
        $html .= '<meta charset="UTF-8">' . "\n";
        $html .= '<meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n";
        $html .= '<title>' . $title . ' · YourLink博客</title>' . "\n";
        $html .= '<link rel="stylesheet" href="/css/style.css">' . "\n";
        $html .= '<style>';
        $html .= '.body-pg{max-width:720px;width:100%;margin:0 auto;padding:24px 16px 60px}';
        $html .= '.breadcrumb{font-size:12px;color:var(--text-3);margin-bottom:16px}';
        $html .= '.breadcrumb a{color:var(--primary);text-decoration:none}';
        $html .= '.article{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:28px 24px;box-shadow:var(--shadow)}';
        $html .= '.article h1{font-size:22px;font-weight:800;color:var(--text);margin:0 0 8px;line-height:1.4}';
        $html .= '.article .meta{font-size:12px;color:var(--text-3);margin-bottom:20px;padding-bottom:16px;border-bottom:1px solid var(--border)}';
        $html .= '.article h2{font-size:17px;font-weight:700;color:var(--text);margin:28px 0 10px;padding-left:12px;border-left:3px solid var(--primary)}';
        $html .= '.article p{font-size:14px;color:var(--text-2);line-height:1.9;margin:0 0 14px}';
        $html .= '.article ul,.article ol{margin:0 0 14px;padding-left:20px;font-size:14px;color:var(--text-2);line-height:1.9}';
        $html .= '.article li{margin:4px 0}';
        $html .= '.article strong{color:var(--text)}';
        $html .= '.nav-links{display:flex;justify-content:space-between;margin-top:24px;padding-top:16px;border-top:1px solid var(--border)}';
        $html .= '.nav-links a{color:var(--primary);font-size:13px;text-decoration:none;font-weight:500}';
        $html .= '.mt{color:var(--text-3);font-size:13px;text-align:center;margin-top:16px}';
        $html .= '.mt a{color:var(--primary);text-decoration:none}';
        $html .= '</style>' . "\n";
        $html .= '</head>' . "\n";
        $html .= '<body class="body-pg">' . "\n";
        $html .= '<nav class="breadcrumb"><a href="/">首页</a> &gt; <a href="/blog/">博客</a></nav>' . "\n";
        $html .= '<article class="article">' . "\n";
        $html .= $content . "\n";
        $html .= '</article>' . "\n";
        $html .= '<div class="nav-links"><a href="/blog/">← 返回博客</a><a href="/">首页</a></div>' . "\n";
        $html .= '<p class="mt"><a href="/blog/">← 返回博客列表</a></p>' . "\n";
        $html .= '</body>' . "\n";
        $html .= '</html>';
        file_put_contents($contentFile, $html);
        $articles[] = [
            'id' => $id, 'slug' => $slug,
            'title' => trim($_POST['title'] ?? ''),
            'summary' => trim($_POST['summary'] ?? ''),
            'tag' => trim($_POST['tag'] ?? '未分类'),
            'content_file' => $slug . '.html',
            'created' => date('Y-m-d'), 'updated' => date('Y-m-d'), 'views' => 0
        ];
        file_put_contents($dataFile, json_encode($articles, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $msg = '✅ 文章发布成功';
    }
    if ($action === 'delete') {
        $delId = (int)($_POST['id'] ?? 0);
        foreach ($articles as $i => $a) {
            if ($a['id'] === $delId) {
                $cf = __DIR__ . '/../' . $a['content_file'];
                if (file_exists($cf)) unlink($cf);
                array_splice($articles, $i, 1);
                break;
            }
        }
        file_put_contents($dataFile, json_encode($articles, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $msg = '✅ 文章已删除';
    }
    if ($action === 'logout') { session_destroy(); header('Location: /blog/admin/login.php'); exit; }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>博客后台 · 管理面板</title>
<link rel="stylesheet" href="/css/style.css">
<style>
.aw{max-width:800px;width:100%;margin:0 auto;padding:24px 16px}
.ah{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}
.ah h1{font-size:20px;font-weight:800;color:var(--text);margin:0}
.btn{padding:8px 16px;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;font-family:var(--ff)}
.btn-p{background:var(--primary);color:#fff}
.btn-d{background:#dc2626;color:#fff;font-size:11px;padding:4px 10px}
.btn-s{background:var(--bg);color:var(--text-2);border:1px solid var(--border)}
.card{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:24px;box-shadow:var(--shadow);margin-bottom:16px}
.msg{background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:10px 14px;color:#16a34a;font-size:13px;margin-bottom:16px}
.fg{margin-bottom:12px}
.fg label{display:block;font-size:12px;font-weight:600;color:var(--text-2);margin-bottom:4px}
.fg input,.fg textarea{width:100%;padding:8px 12px;border:1px solid var(--border);border-radius:6px;font-size:13px;font-family:var(--ff);outline:none;box-sizing:border-box}
.fg textarea{min-height:200px;resize:vertical;font-family:monospace;font-size:12px;line-height:1.6}
.fg input:focus,.fg textarea:focus{border-color:var(--primary)}
.al{list-style:none;padding:0;margin:0}
.ai{display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid var(--border)}
.ai:last-child{border:none}
.ai .t{font-size:14px;font-weight:600;color:var(--text);margin-bottom:2px}
.ai .m{font-size:11px;color:var(--text-3)}
.tg{display:inline-block;font-size:10px;font-weight:600;color:var(--primary);background:var(--primary-light);padding:1px 6px;border-radius:3px;margin-right:6px}
.tb{display:flex;gap:0;border-bottom:1px solid var(--border);margin-bottom:20px}
.tb button{padding:10px 20px;border:none;background:transparent;color:var(--text-3);font-size:13px;font-weight:600;cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-1px;font-family:var(--ff)}
.tb button.on{color:var(--primary);border-bottom-color:var(--primary)}
.pn{display:none}.pn.on{display:block}
.fr{display:flex;gap:12px;margin-bottom:12px}
.fr .fg{flex:1}
</style>
</head>
<body style="background:var(--bg)">
<div class="aw">
  <div class="ah">
    <h1>📝 博客后台</h1>
    <div style="display:flex;gap:8px">
      <a href="/blog/" class="btn btn-s" target="_blank">查看博客</a>
      <form method="POST" style="display:inline"><input type="hidden" name="action" value="logout"><input type="hidden" name="csrf_token" value="<?= $csrfToken ?>"><button class="btn btn-s">退出</button></form>
    </div>
  </div>
  <?php if ($msg): ?><div class="msg"><?= $msg ?></div><?php endif; ?>
  <div class="tb">
    <button class="on" onclick="st('list')">文章列表 (<?= count($articles) ?>)</button>
    <button onclick="st('create')">新建文章</button>
  </div>
  <div id="tl" class="pn on">
    <div class="card">
      <ul class="al">
        <?php if (empty($articles)): ?><li style="text-align:center;color:var(--text-3);padding:20px;font-size:13px">暂无文章</li><?php endif; ?>
        <?php foreach (array_reverse($articles) as $a): ?>
        <li class="ai">
          <div style="flex:1"><div class="t"><span class="tg"><?= htmlspecialchars($a['tag']) ?></span><?= htmlspecialchars($a['title']) ?></div><div class="m">/<?= htmlspecialchars($a['slug']) ?> · <?= $a['created'] ?> · <?= $a['views'] ?? 0 ?>次阅读</div></div>
          <div style="display:flex;gap:6px"><a href="/blog/<?= htmlspecialchars($a['slug']) ?>" target="_blank" class="btn btn-s" style="font-size:11px;padding:4px 10px">查看</a><form method="POST" style="display:inline" onsubmit="return confirm('确定删除？')"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $a['id'] ?>"><input type="hidden" name="csrf_token" value="<?= $csrfToken ?>"><button class="btn btn-d">删除</button></form></div>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
  <div id="tc" class="pn">
    <form method="POST">
      <input type="hidden" name="action" value="create">
      <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
      <div class="card">
        <div class="fr"><div class="fg"><label>文章标题</label><input type="text" name="title" required placeholder="文章标题"></div><div class="fg" style="max-width:150px"><label>标签</label><input type="text" name="tag" value="未分类"></div></div>
        <div class="fg"><label>URL短码</label><input type="text" name="slug" placeholder="留空自动生成"></div>
        <div class="fg"><label>摘要</label><input type="text" name="summary" placeholder="一句话描述"></div>
        <div class="fg"><label>正文 HTML</label><textarea name="content" required placeholder="粘贴文章HTML内容..."></textarea></div>
        <button type="submit" class="btn btn-p" style="padding:10px 24px">发布文章</button>
      </div>
    </form>
  </div>
</div>
<script>
function st(n){
  document.querySelectorAll('.tb button').forEach(function(b,i){b.classList.toggle('on',(n==='list'&&i===0)||(n==='create'&&i===1));});
  document.getElementById('tl').classList.toggle('on',n==='list');
  document.getElementById('tc').classList.toggle('on',n==='create');
}
</script>
</body>
</html>
