<?php
header("Content-Type: application/json; charset=utf-8");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("X-Frame-Options: SAMEORIGIN");

$origin = $_SERVER["HTTP_ORIGIN"] ?? "";
if ($origin === "https://your-domain.com" || $origin === "https://www.your-domain.com") {
    header("Access-Control-Allow-Origin: " . $origin);
} else {
    header("Access-Control-Allow-Origin: https://your-domain.com");
}
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Max-Age: 86400");
require_once __DIR__ . "/blacklist.php";
require_once __DIR__ . "/auth/session_init.php";
require_once __DIR__ . "/smtp_mail.php";
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") { http_response_code(204); exit; }

// CSRF protection: validate Origin/Referer for POST
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $origin = $_SERVER["HTTP_ORIGIN"] ?? "";
    $referer = $_SERVER["HTTP_REFERER"] ?? "";
    $valid = false;
    if ($origin !== "") {
        $valid = preg_match("#^(https://)(www\.)?9h\.hk#", $origin);
    } elseif ($referer !== "") {
        $valid = preg_match("#^(https://)(www\.)?9h\.hk#", $referer);
    }
    if (!$valid) {
        http_response_code(403);
        echo json_encode(["code"=>1, "msg"=>"请求来源不合法"], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$linksFile = __DIR__ . "/links.json";
if (!file_exists($linksFile)) { file_put_contents($linksFile, json_encode([], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)); }

function load_links($file) {
    $raw = @file_get_contents($file);
    $rawLinks = json_decode($raw ?: "[]", true);
    if (!is_array($rawLinks)) { $rawLinks = []; }
    $links = [];
    if (isset($rawLinks["links"]) && is_array($rawLinks["links"])) {
        foreach ($rawLinks["links"] as $code => $v) {
            if (!is_string($code) || !preg_match('/^[A-Za-z0-9]{2,12}$/', $code)) continue;
            $url = is_array($v) ? ($v["url"] ?? "") : $v;
            if (is_string($url) && $url !== "") $links[$code] = is_array($v) ? $v : ["url"=>$url];
        }
    }
    foreach ($rawLinks as $k => $v) {
        if ($k === "links" || !is_string($k) || !preg_match('/^[A-Za-z0-9]{2,12}$/', $k)) continue;
        $url = is_array($v) ? ($v["url"] ?? "") : $v;
        if (is_string($url) && $url !== "") $links[$k] = is_array($v) ? $v : ["url"=>$url];
    }
    return $links;
}

function save_links($file, $links) {
    $tmp = $file . ".tmp";
    $json = json_encode($links, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) return false;
    $fp = fopen($tmp, "c");
    if (!$fp) return false;
    if (!flock($fp, LOCK_EX)) { fclose($fp); return false; }
    ftruncate($fp, 0);
    fwrite($fp, $json);
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    return rename($tmp, $file);
}

// 清理过期的被拒绝短链接和过期短链接
function cleanup_expired_rejected($links, $file) {
    $cutoff = strtotime('-3 days');
    $now = time();
    $changed = false;
    foreach ($links as $code => $data) {
        if (is_array($data)) {
            // 清理过期被拒绝链接（3天）
            if (($data['status'] ?? '') === 'rejected') {
                $reviewed = $data['reviewed_at'] ?? '';
                if ($reviewed !== '') {
                    $ts = strtotime($reviewed);
                    if ($ts !== false && $ts < $cutoff) {
                        unset($links[$code]);
                        $changed = true;
                        continue;
                    }
                }
            }
            // 未登录用户链接不再自动过期（已取消过期机制）
        }
    }
    if ($changed) {
        save_links($file, $links);
    }
    return $links;
}

function genCode($len = 4) {
    $chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
    $out = "";
    for ($i=0; $i<$len; $i++) { $out .= $chars[random_int(0, strlen($chars)-1)]; }
    return $out;
}

function json_out($arr, $status=200) {
    http_response_code($status);
    echo json_encode($arr, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$reserved = ["api.php","index.php","index.html","robots.txt","sitemap.xml","og.png","push.js","favicon.ico","404.html","cron_push.sh","links.json","admin","api","www","http","https","privacy-policy","about","contact","review","review.php","audit","login.php","stats","faq","guide"];
$links = load_links($linksFile);
// 每次请求时清理过期被拒绝链接
$links = cleanup_expired_rejected($links, $linksFile);
$path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
$shortCode = trim($path, "/");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $url = trim($_POST["url"] ?? $_GET["url"] ?? "");
    $custom = trim($_POST["custom"] ?? $_GET["custom"] ?? "");
    if ($url === "") json_out(["code"=>1,"msg"=>"请提供目标链接"], 400);
    if (strlen($url) > 2048) json_out(["code"=>1,"msg"=>"链接过长，请控制在2048字符以内"], 400);
    if (!preg_match('/^https?:\/\//i', $url)) { $url = "https://" . $url; }
    if (!filter_var($url, FILTER_VALIDATE_URL)) json_out(["code"=>1,"msg"=>"链接格式不合法"], 400);
    $parts = parse_url($url);
    $scheme = strtolower($parts["scheme"] ?? "");
    $host = strtolower($parts["host"] ?? "");
    if (!in_array($scheme, ["http","https"], true) || $host === "") json_out(["code"=>1,"msg"=>"仅支持 http/https 链接"], 400);
    if (in_array($host, ["localhost","127.0.0.1","0.0.0.0"], true) || str_ends_with($host, ".local")) json_out(["code"=>1,"msg"=>"不支持本地或内网地址"], 400);
    $code = $custom ? preg_replace('/[^A-Za-z0-9]/', '', $custom) : genCode();
    if ($code === "") json_out(["code"=>1,"msg"=>"自定义短码不合法"], 400);
    if (strlen($code) < 2 || strlen($code) > 12) json_out(["code"=>1,"msg"=>"短码长度 2~12"], 400);
    if (in_array(strtolower($code), $reserved, true)) json_out(["code"=>1,"msg"=>"该短码为系统保留字"], 409);
    if (isset($links[$code])) json_out(["code"=>1,"msg"=>"短码已被使用"], 409);
    
    // === 配额检查 ===
    $client_ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    $client_ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $fingerprint = md5($client_ip . $client_ua);
    $is_logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    $now = time();
    $quotaFile = __DIR__ . '/data/quota.json';
    $quotaData = json_decode(@file_get_contents($quotaFile), true);
    if (!is_array($quotaData)) $quotaData = [];
    
    $cooldown = null; // 冷却信息
    
    if (!$is_logged_in) {
        // 未登录用户：12h 周期内 5 条
        $fpKey = 'fp_' . $fingerprint;
        $fpData = $quotaData[$fpKey] ?? ['count'=>0, 'cycle_start'=>0];
        $cycleStart = $fpData['cycle_start'];
        $cycleUsed = $fpData['count'];
        $cycleMax = 5;
        $cycleHours = 12;
        
        // 如果距离周期开始超过 12h，重置
        if ($cycleStart > 0 && ($now - $cycleStart) > $cycleHours * 3600) {
            $cycleUsed = 0;
            $cycleStart = $now;
        }
        // 如果还没开始周期（首次），初始化
        if ($cycleStart === 0) {
            $cycleStart = $now;
            $cycleUsed = 0;
        }
        
        if ($cycleUsed >= $cycleMax) {
            $cooldownEnd = $cycleStart + $cycleHours * 3600;
            $remaining = $cooldownEnd - $now;
            $hours = floor($remaining / 3600);
            $mins = floor(($remaining % 3600) / 60);
            $cooldown = ['remaining_seconds'=>$remaining, 'hours'=>$hours, 'minutes'=>$mins, 'until'=>$cooldownEnd];
            json_out(["code"=>1, "msg"=>"生成已达上限，请 {$hours} 小时 {$mins} 分钟后再试，或登录后无限使用", "quota"=>["used"=>$cycleUsed, "max"=>$cycleMax, "remaining"=>0, "cooldown"=>$cooldown]], 403);
        }
    } else {
        // 登录用户：最多 20 条
        $username = $_SESSION['user']['username'] ?? '';
        $userMax = 20;
        $userUsed = 0;
        foreach ($links as $code2 => $data2) {
            if (is_array($data2) && ($data2['creator_user'] ?? '') === $username) {
                $userUsed++;
            }
        }
        if ($userUsed >= $userMax) {
            json_out(["code"=>1, "msg"=>"每个账号最多可生成 {$userMax} 条短链接，请删除部分链接后继续", "quota"=>["used"=>$userUsed, "max"=>$userMax, "remaining"=>0]], 403);
        }
    }
    
    // === 安全审核：自动检测 + 进入待审队列 ===
    $audit = audit_url($url);
    if ($audit['level'] === 'block') {
        json_out(["code"=>1,"msg"=>"链接未通过安全检查：" . $audit['reason'], "audit"=>$audit], 403);
    }
    // 自动审核三级：auto_approve(直接通过)/review(人工审核)/pass(人工审核)
    if ($audit['level'] === 'auto_approve') {
        $status = 'approved';
        $status_msg = '短链接已生成';
    } else {
        $status = 'pending';
        $status_msg = '已提交审核，审核通过后即可使用';
        // 邮件通知管理员
        $notifyFile = __DIR__ . '/data/notify_config.json';
        $notify = json_decode(@file_get_contents($notifyFile), true);
        if ($notify && !empty($notify['enabled']) && !empty($notify['email'])) {
            $sendLevel = $notify['level'] ?? 'all';
            $shouldSend = ($sendLevel === 'all' || ($sendLevel === 'high' && $audit['level'] === 'review'));
            if ($shouldSend) {
                $to = $notify['email'];
                $host = parse_url($url, PHP_URL_HOST);
        $subject = "YourLink 审核: {$code} → {$host}";
                $message = "新短链接待审核\n\n";
                $message .= "短码: {$code}\n";
                $message .= "目标: {$url}\n";
                $message .= "时间: " . date('Y-m-d H:i:s') . "\n";
                $message .= "IP: {$client_ip}\n";
                if ($is_logged_in) {
                    $message .= "用户: " . ($_SESSION['user']['username'] ?? 'unknown') . "\n";
                }
                $message .= "\n审核地址: https://your-domain.com/review\n";
                smtp_send($to, $subject, $message, $notify["smtp"] ?? []);
            }
        }
    }
    $links[$code] = [
        "url" => $url,
        "created" => date("Y-m-d H:i:s"),
        "status" => $status,
        "audit_level" => $audit['level'],
        "audit_reason" => $audit['reason'],
        "creator_fp" => $fingerprint,
        "creator_ip" => $client_ip,
    ];
    if (!$is_logged_in) {
        // 未登录用户链接不再自动过期（已取消过期机制）
        $links[$code]['creator_type'] = 'anonymous';
        // 更新配额计数
        $fpKey = 'fp_' . $fingerprint;
        $quotaData[$fpKey] = ['count'=>($cycleUsed + 1), 'cycle_start'=>$cycleStart];
        file_put_contents($quotaFile, json_encode($quotaData, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
    } else {
        $links[$code]['creator_type'] = 'user';
    }
    if ($is_logged_in) {
        $links[$code]['creator_user'] = $_SESSION['user']['username'] ?? 'unknown';
        $links[$code]['creator_user_id'] = $_SESSION['user']['id'] ?? 0;
    }
    if (!save_links($linksFile, $links)) json_out(["code"=>1,"msg"=>"保存失败，请稍后重试"], 500);
    $quota = [];
    if (!$is_logged_in) {
        $remaining = $cycleMax - $cycleUsed - 1;
        $quota = ["used"=>$cycleUsed + 1, "max"=>$cycleMax, "remaining"=>$remaining];
    } else {
        $username = $_SESSION['user']['username'] ?? '';
        $userUsed2 = 0;
        foreach ($links as $code2 => $data2) {
            if (is_array($data2) && ($data2['creator_user'] ?? '') === $username) {
                $userUsed2++;
            }
        }
        $quota = ["used"=>$userUsed2, "max"=>$userMax, "remaining"=>$userMax - $userUsed2];
    }
    json_out(["code"=>0,"short_url"=>"https://your-domain.com/".$code,"original"=>$url,"msg"=>$status_msg,"status"=>$status, "quota"=>$quota]);
}

if ($_SERVER["REQUEST_METHOD"] !== "GET") json_out(["code"=>405,"msg"=>"Method Not Allowed"], 405);

if (!empty($shortCode) && !in_array(strtolower($shortCode), $reserved, true) && preg_match('/^[A-Za-z0-9]{2,12}$/', $shortCode)) {
    if (isset($links[$shortCode])) {
        $linkStatus = $links[$shortCode]["status"] ?? "approved";  // 兼容旧数据
        if ($linkStatus !== 'approved') {
            if ($linkStatus === 'pending') {
                header('Content-Type: text/html; charset=utf-8');
                readfile(__DIR__ . '/pending.html');
                exit;
            }
            if ($linkStatus === 'rejected') {
                header('Content-Type: text/html; charset=utf-8');
                $html = file_get_contents(__DIR__ . '/rejected.html');
                $reason = $links[$shortCode]['reject_reason'] ?? '链接内容不符合安全规范';
                $html = str_replace('{{REJECT_REASON}}', htmlspecialchars($reason), $html);
                echo $html;
                exit;
            }
            json_out(["code"=>404,"msg"=>"短链接不可用"], 404);
        }
        $url = $links[$shortCode]["url"] ?? "";
        if (is_string($url) && $url !== "") {
            // 二次校验：防止 links.json 被篡改后造成 open redirect
            $parts2 = parse_url($url);
            $scheme2 = strtolower($parts2["scheme"] ?? "");
            $host2 = strtolower($parts2["host"] ?? "");
            if (!in_array($scheme2, ["http","https"], true) || $host2 === "") {
                http_response_code(400);
                echo json_encode(["code"=>1,"msg"=>"链接无效"], JSON_UNESCAPED_UNICODE);
                exit;
            }
            if (in_array($host2, ["localhost","127.0.0.1","0.0.0.0"], true) || str_ends_with($host2, ".local")) {
                http_response_code(400);
                echo json_encode(["code"=>1,"msg"=>"链接无效"], JSON_UNESCAPED_UNICODE);
                exit;
            }
            header("Cache-Control: no-store");
            record_click($shortCode);
            header("Location: " . $url, true, 301);
            exit;
        }
    }
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/notfound.html');
    exit;
}

$fp = @fopen(__DIR__ . "/index.html", "r");

// === API: 单链接统计 ===
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['stats'])) {
    $code = preg_replace('/[^A-Za-z0-9]/', '', $_GET['stats']);
    if (!isset($links[$code])) json_out(['code'=>1, 'msg'=>'短链接不存在'], 404);
    $statsDir = __DIR__ . '/stats';
    $totalClicks = $links[$code]['clicks'] ?? 0;
    $lastClick = $links[$code]['last_click'] ?? null;
    $created = $links[$code]['created'] ?? null;
    $url = $links[$code]['url'] ?? '';
    $daily = [];
    if (is_dir($statsDir)) {
        $files = glob($statsDir . '/*.jsonl');
        rsort($files);
        foreach (array_slice($files, 0, 30) as $f) {
            $date = basename($f, '.jsonl');
            $count = 0;
            $handle = fopen($f, 'r');
            if ($handle) {
                while (($line = fgets($handle)) !== false) {
                    $entry = json_decode(trim($line), true);
                    if ($entry && $entry['code'] === $code) $count++;
                }
                fclose($handle);
            }
            if ($count > 0) $daily[$date] = $count;
        }
    }
    json_out([
        'code' => 0,
        'data' => [
            'short_code' => $code,
            'short_url' => 'https://your-domain.com/' . $code,
            'original_url' => $url,
            'total_clicks' => $totalClicks,
            'created' => $created,
            'last_click' => $lastClick,
            'daily' => $daily
        ]
    ]);
}

// === API: 主站统计 ===
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['site_stats'])) {
    $statsDir = __DIR__ . '/stats';
    $totalClicks = 0;
    $todayClicks = 0;
    $today = date('Y-m-d');
    foreach ($links as $code => $v) {
        $totalClicks += $v['clicks'] ?? 0;
    }
    $todayFile = $statsDir . '/' . $today . '.jsonl';
    if (file_exists($todayFile)) {
        $lines = file($todayFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $todayClicks = count($lines);
    }
    $visitFile = __DIR__ . '/stats/site_visits.txt';
    $totalVisits = (int)@file_get_contents($visitFile);
    json_out([
        'code' => 0,
        'data' => [
            'total_visits' => $totalVisits,
            'total_links' => count($links),
            'total_clicks' => $totalClicks,
            'today_clicks' => $todayClicks
        ]
    ]);
}

// 记录主站访问量
$vFile = __DIR__ . '/stats/site_visits.txt';
$vCount = (int)@file_get_contents($vFile);
file_put_contents($vFile, ($vCount + 1) . '', LOCK_EX);

// 记录点击统计
function record_click($code) {
    $statsDir = __DIR__ . '/stats';
    if (!is_dir($statsDir)) mkdir($statsDir, 0755, true);
    $date = date('Y-m-d');
    $time = date('H:i:s');
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    $ip = explode(',', $ip)[0];
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    $logFile = $statsDir . '/' . $date . '.jsonl';
    $entry = json_encode(['code'=>$code,'time'=>$time,'ip'=>$ip,'ua'=>$ua,'ref'=>$ref], JSON_UNESCAPED_UNICODE);
    file_put_contents($logFile, $entry . "\n", FILE_APPEND | LOCK_EX);
    // 更新点击计数
    $linksFile = __DIR__ . '/links.json';
    $raw = @file_get_contents($linksFile);
    $data = json_decode($raw ?: '{}', true);
    if (!is_array($data)) $data = [];
    $target = null;
    if (isset($data['links'][$code])) { $target = &$data['links'][$code]; }
    elseif (isset($data[$code])) { $target = &$data[$code]; }
    if ($target !== null) {
        if (!is_array($target)) $target = ['url' => $target];
        $target['clicks'] = ($target['clicks'] ?? 0) + 1;
        $target['last_click'] = $date . ' ' . $time;
    }
    $tmp = $linksFile . '.tmp';
    $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    $fp = fopen($tmp, 'c');
    if ($fp && flock($fp, LOCK_EX)) {
        ftruncate($fp, 0);
        fwrite($fp, $json);
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        rename($tmp, $linksFile);
    }
}

if ($fp) { header("Content-Type: text/html; charset=utf-8"); fpassthru($fp); fclose($fp); exit; }
http_response_code(500); echo "frontend not found";
