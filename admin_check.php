<?php
/**
 * 管理员权限检查工具
 * 
 * 使用方式：
 *   require_once __DIR__ . '/admin_check.php';
 *   if (is_admin_user()) { ... }
 * 
 * 权限配置在 config.php 的 admin_users 数组中
 */

function load_config() {
    static $config = null;
    if ($config === null) {
        $configFile = __DIR__ . '/config.php';
        if (file_exists($configFile)) {
            $config = require $configFile;
        } else {
            $config = [];
        }
    }
    return $config;
}

function is_admin_user($username = null) {
    $config = load_config();
    $adminUsers = $config['admin_users'] ?? [];
    
    if ($username === null) {
        // 从 session 获取当前登录用户名
        $username = $_SESSION['user']['username'] ?? '';
    }
    
    return in_array($username, $adminUsers, true);
}

function get_domain() {
    $config = load_config();
    return $config['domain'] ?? 'your-domain.com';
}
