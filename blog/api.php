<?php
header('Content-Type: application/json; charset=utf-8');
$dataFile = __DIR__ . '/data/articles.json';
$articles = json_decode(@file_get_contents($dataFile) ?: '[]', true);
if (!is_array($articles)) $articles = [];

// 获取文章列表
if (isset($_GET['list'])) {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $per = 10;
    $total = count($articles);
    $start = ($page - 1) * $per;
    $list = array_slice($articles, $start, $per);
    // 逆序（最新在前）
    $list = array_reverse($list);
    echo json_encode([
        'code' => 0,
        'data' => $list,
        'total' => $total,
        'page' => $page,
        'pages' => ceil($total / $per)
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 获取单篇文章
if (isset($_GET['slug'])) {
    $slug = preg_replace('/[^a-z0-9-]/', '', $_GET['slug']);
    foreach ($articles as $a) {
        if ($a['slug'] === $slug) {
            // 增加阅读量（原子写入）
            $a['views'] = ($a['views'] ?? 0) + 1;
            foreach ($articles as &$aa) {
                if ($aa['slug'] === $slug) $aa['views'] = $a['views'];
            }
            $tmp = $dataFile . '.tmp';
            file_put_contents($tmp, json_encode($articles, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
            rename($tmp, $dataFile);
            // 读取文章内容（路径穿越防护：仅允许 blog 目录内文件）
            $contentFile = __DIR__ . '/' . ltrim(str_replace('\\', '/', $a['content_file'] ?? ''), '/');
            $realBase = realpath(__DIR__);
            $realFile = realpath($contentFile);
            if ($realFile === false || $realBase === false || strpos($realFile, $realBase . DIRECTORY_SEPARATOR) !== 0) {
                $a['content'] = '';
            } else {
                $a['content'] = @file_get_contents($realFile) ?: '';
            }
            echo json_encode(['code' => 0, 'data' => $a], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    echo json_encode(['code' => 1, 'msg' => '文章不存在'], 404);
    exit;
}

echo json_encode(['code' => 1, 'msg' => '参数错误']);
