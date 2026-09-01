<?php
/**
 * 链接数据读写共享函数
 * api.php 和 review.php 统一使用此文件，确保数据格式一致
 */

function link_load($file) {
    $raw = @file_get_contents($file);
    $rawLinks = json_decode($raw ?: "[]", true);
    if (!is_array($rawLinks)) { $rawLinks = []; }
    $links = [];
    // 支持两种格式：{"links":{...}} 或 {"code":{...}}
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

function link_save($file, $links) {
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
