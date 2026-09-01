<?php
/**
 * 9H Link SMTP 邮件发送 v3
 * 修复：函数嵌套导致的重复定义问题，增加错误日志
 */

// 辅助函数：发送 SMTP 命令并读取响应
function smtp_cmd_log($fp, $cmd, &$log) {
    fwrite($fp, $cmd . "\r\n");
    $resp = '';
    do {
        $line = fgets($fp, 512);
        if ($line === false) break;
        $resp .= $line;
    } while (strlen($line) >= 4 && substr($line, 3, 1) === '-');
    $log[] = trim($cmd) . ' -> ' . trim($resp);
    return $resp;
}

function smtp_send($to, $subject, $body, $smtp) {
    if (empty($smtp['host']) || empty($smtp['port'])) return false;
    
    // 从 config 获取域名
    $configFile = __DIR__ . '/config.php';
    $domain = 'localhost';
    if (file_exists($configFile)) {
        $cfg = require $configFile;
        $domain = $cfg['domain'] ?? 'localhost';
    }
    
    $log = [];
    $errno = 0; $errstr = '';
    $prefix = $smtp['encryption'] === 'ssl' ? 'ssl://' : '';
    
    $fp = @stream_socket_client($prefix . $smtp['host'] . ':' . $smtp['port'], $errno, $errstr, 15);
    if (!$fp) { error_log("9H Link SMTP: 连接失败 {$smtp['host']}:{$smtp['port']} - $errstr"); return false; }
    stream_set_timeout($fp, 15);
    
    $from = $smtp['from'] ?? "noreply@{$domain}";
    $fromName = $smtp['from_name'] ?? '9H Link';
    
    // 读欢迎
    fgets($fp, 512);
    
    // EHLO
    $ehlo = smtp_cmd_log($fp, "EHLO {$domain}", $log);
    if (strpos($ehlo, '250') === false) { error_log("9H Link SMTP: EHLO 失败"); fclose($fp); return false; }
    
    // STARTTLS（微软365使用端口587 + STARTTLS）
    if ($smtp['encryption'] === 'tls') {
        $resp = smtp_cmd_log($fp, 'STARTTLS', $log);
        if (strpos($resp, '220') !== false) {
            $ok = @stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if (!$ok) { error_log("9H Link SMTP: TLS 握手失败"); fclose($fp); return false; }
            usleep(300000);
            $ehlo2 = smtp_cmd_log($fp, "EHLO {$domain}", $log);
            if (strpos($ehlo2, '250') === false) { error_log("9H Link SMTP: TLS后 EHLO 失败"); fclose($fp); return false; }
        }
    }
    
    // AUTH LOGIN
    if (!empty($smtp['user']) && !empty($smtp['pass'])) {
        $r1 = smtp_cmd_log($fp, 'AUTH LOGIN', $log);
        if (strpos($r1, '334') === false) { error_log("9H Link SMTP: AUTH LOGIN 不支持的响应: " . trim($r1)); fclose($fp); return false; }
        $r2 = smtp_cmd_log($fp, base64_encode($smtp['user']), $log);
        if (strpos($r2, '334') === false) { error_log("9H Link SMTP: 用户名被拒: " . trim($r2)); fclose($fp); return false; }
        $r3 = smtp_cmd_log($fp, base64_encode($smtp['pass']), $log);
        if (strpos($r3, '235') === false && strpos($r3, '2') !== 0) { 
            error_log("9H Link SMTP: 密码认证失败: " . trim($r3)); 
            fclose($fp); 
            return false; 
        }
    }
    
    // 发件人
    smtp_cmd_log($fp, "MAIL FROM:<{$from}>", $log);
    // 收件人
    $r = smtp_cmd_log($fp, "RCPT TO:<{$to}>", $log);
    if (strpos($r, '250') === false) { error_log("9H Link SMTP: 收件人被拒: " . trim($r)); fclose($fp); return false; }
    
    // DATA
    $r = smtp_cmd_log($fp, 'DATA', $log);
    if (strpos($r, '354') === false) { error_log("9H Link SMTP: DATA 被拒: " . trim($r)); fclose($fp); return false; }
    
    $headers = "From: {$fromName} <{$from}>\r\n";
    $headers .= "Reply-To: {$from}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=utf-8\r\n";
    $headers .= "X-Mailer: 9H Link SMTP\r\n";
    $full = $headers . "\r\n" . $body . "\r\n.";
    fwrite($fp, $full . "\r\n");
    $resp = fgets($fp, 512);
    $log[] = 'DATA -> ' . trim($resp);
    
    smtp_cmd_log($fp, 'QUIT', $log);
    fclose($fp);
    
    // 记录日志
    $lastLine = end($log);
    error_log("9H Link SMTP: " . ($lastLine ?: 'unknown'));
    
    $first = substr(trim($resp), 0, 3);
    return $first === '250' || $first[0] === '2';
}