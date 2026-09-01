<?php
$dataFile = __DIR__ . '/data/articles.json';
$articles = json_decode(@file_get_contents($dataFile) ?: '[]', true);
if (!is_array($articles)) $articles = [];
$articles = array_reverse($articles);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>博客 · YourLink短链接</title>
<meta name="description" content="YourLink短链接博客——短链接使用技巧、二维码营销实战、工具评测与行业动态。">
<meta name="robots" content="index,follow">
<link rel="canonical" href="https://your-domain.com/blog/">
<link rel="stylesheet" href="/css/style.css">
<style>
.blog-nav{position:sticky;top:0;z-index:1000;background:rgba(255,255,255,.92);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);border-bottom:1px solid var(--border);padding:0 20px;display:flex;align-items:center;justify-content:space-between;height:52px}
.blog-nav .nav-left{display:flex;align-items:center;gap:24px}
.blog-nav .nav-logo{font-size:18px;font-weight:800;color:var(--primary);text-decoration:none;letter-spacing:-.5px}
.blog-nav .nav-logo span{color:var(--text)}
.blog-nav .nav-links{display:flex;gap:4px}
.blog-nav .nav-links a{font-size:13px;font-weight:500;color:var(--text-2);text-decoration:none;padding:6px 12px;border-radius:6px;transition:all .15s}
.blog-nav .nav-links a:hover,.blog-nav .nav-links a.active{color:var(--primary);background:var(--primary-light)}
.blog-nav .nav-right{display:flex;align-items:center;gap:8px}
.blog-nav .nav-home{font-size:13px;font-weight:500;color:var(--text-2);text-decoration:none;padding:6px 14px;border:1px solid var(--border);border-radius:8px;transition:all .15s}
.blog-nav .nav-home:hover{border-color:var(--primary);color:var(--primary)}
@media(prefers-color-scheme:dark){.blog-nav{background:rgba(15,15,15,.92);border-bottom-color:var(--border)}}
.body-pg{max-width:720px;width:100%;margin:0 auto;padding:24px 16px 60px}
.breadcrumb{font-size:12px;color:var(--text-3);margin-bottom:16px}
.breadcrumb a{color:var(--primary);text-decoration:none}
.hdr{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:28px 24px;box-shadow:var(--shadow);text-align:center;margin-bottom:24px}
.hdr h1{font-size:22px;font-weight:800;color:var(--text);margin:0 0 4px}
.hdr p{color:var(--text-2);font-size:13px}
.blog-list{display:flex;flex-direction:column;gap:16px}
.blog-card{background:var(--card);border:1px solid var(--border);border-radius:10px;padding:20px 22px;box-shadow:var(--shadow);text-decoration:none;color:inherit;transition:all .2s;display:block}
.blog-card:hover{box-shadow:var(--shadow-md);border-color:var(--primary);transform:translateY(-1px)}
.blog-card .tag{display:inline-block;font-size:11px;font-weight:600;color:var(--primary);background:var(--primary-light);padding:2px 8px;border-radius:4px;margin-bottom:8px}
.blog-card h2{font-size:16px;font-weight:700;color:var(--text);margin:0 0 6px;line-height:1.4}
.blog-card p{font-size:13px;color:var(--text-2);margin:0 0 10px;line-height:1.7}
.blog-card .meta{font-size:11px;color:var(--text-3)}
.related{background:var(--card);border:1px solid var(--border);border-radius:10px;padding:16px 20px;margin-top:24px;text-align:center}
.related h3{font-size:13px;color:var(--text-2);margin:0 0 8px;font-weight:600}
.related a{color:var(--primary);font-size:13px;font-weight:500;text-decoration:none;margin:0 6px}
.mt{color:var(--text-3);font-size:13px;text-align:center;margin-top:16px}
.mt a{color:var(--primary);text-decoration:none}
.empty{text-align:center;color:var(--text-3);font-size:13px;padding:40px 0}

.site-nav{position:sticky;top:0;z-index:1000;background:rgba(255,255,255,.92);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);border-bottom:1px solid var(--border);padding:0 20px;display:flex;align-items:center;justify-content:space-between;height:52px;margin:0 -24px;width:calc(100% + 48px)}
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

@media (max-width: 640px) {
  .site-nav{padding:0 10px;height:44px}
  .nav-logo{font-size:15px}
  .nav-links a{font-size:11px;padding:4px 6px}
  .nav-home{font-size:11px;padding:4px 8px}
  .nav-left{gap:6px}
  .nav-right{gap:4px}
}
@media (max-width: 420px) {
  .nav-links a{font-size:10px;padding:3px 4px}
  .nav-home{font-size:10px;padding:3px 6px}
}

</style>
</head>
<body class="body-pg">
<nav class="site-nav">
  <div class="nav-left">
    <a href="/" class="nav-logo">9<span>H</span></a>
    <div class="nav-links">
      <a href="/">首页</a>
      <a href="/blog/" class="active">博客</a>
      <a href="/stats" >统计</a>
      <a href="/faq" >FAQ</a>
      <a href="/guide" >教程</a>
      <a href="/about" >关于</a>
      <a href="/contact" >联系</a>
    </div>
  </div>
  <div class="nav-right">
    <a href="/" class="nav-home">← 返回首页</a>
  </div>
</nav>

<nav class="breadcrumb"><a href="/">首页</a> &gt; 博客</nav>
<div class="hdr">
  <h1>📝 YourLink 博客</h1>
  <p>短链接技巧 · 二维码营销 · 工具评测</p>
</div>
<div class="blog-list">
<?php if (empty($articles)): ?>
  <div class="empty">暂无文章</div>
<?php else: ?>
  <?php foreach ($articles as $a): ?>
  <a href="/blog/<?= htmlspecialchars($a['slug']) ?>" class="blog-card">
    <span class="tag"><?= htmlspecialchars($a['tag']) ?></span>
    <h2><?= htmlspecialchars($a['title']) ?></h2>
    <p><?= htmlspecialchars($a['summary']) ?></p>
    <div class="meta"><?= $a['created'] ?><?php if (($a['views'] ?? 0) > 0): ?> · <?= $a['views'] ?> 次阅读<?php endif; ?></div>
  </a>
  <?php endforeach; ?>
<?php endif; ?>
</div>
<div class="related">
  <h3>更多页面</h3>
  <a href="/about">关于我们</a> · <a href="/contact">联系我们</a> · <a href="/stats">网站统计</a>
</div>
<p class="mt"><a href="/">← 返回首页</a></p>
<script src="/auth.js"></script>
<div class="footer" style="max-width:720px;margin:0 auto;padding:0 16px"><a href="/blog/" class="active">博客</a> · <a href="/faq">FAQ</a> · <a href="/guide">教程</a> · <a href="/stats">统计</a> · <a href="/about">关于</a> · <a href="/contact">联系</a> · <a href="/privacy-policy">隐私</a><br>© 2026 <a href="https://your-domain.com/">YourLink</a> · Less is more</div>
</body>
</html>
