<?php
/**
 * YourLink Link - 配置文件示例
 * 复制为 config.php 并填入你的实际配置
 */
return [
    // 域名
    'domain' => 'your-domain.com',

    // Linux.do OAuth 配置
    'oauth' => [
        'client_id'     => 'YOUR_CLIENT_ID',
        'client_secret' => 'YOUR_CLIENT_SECRET',
        'auth_url'      => 'https://connect.linux.do/oauth2/authorize',
        'token_url'     => 'https://connect.linux.do/oauth2/token',
        'user_url'      => 'https://connect.linux.do/api/user',
    ],

    // SMTP 邮件配置（审核通知用）
    'smtp' => [
        'host' => 'smtp.your-provider.com',
        'port' => 465,
        'user' => 'noreply@your-domain.com',
        'pass' => 'YOUR_SMTP_PASSWORD',
        'from' => 'noreply@your-domain.com',
    ],

    // 管理员白名单（Linux.do 用户名）
    'admin_users' => ['admin_username'],

    // CDN（可选）
    'cdn_zone_id' => '',
    'cdn_token'   => '',
];
