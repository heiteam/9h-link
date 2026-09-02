<?php
/**
 * 9H Link - 配置文件
 * 
 * 使用方法：
 *   1. 复制本文件为 config.php
 *   2. 填入你的实际配置
 *   3. 确保 config.php 不被 Web 访问（.gitignore 已排除）
 * 
 * 管理员设置：
 *   admin_users 数组中的 Linux.do 用户名拥有以下权限：
 *   - /review 审核短链接（通过/拒绝/删除）
 *   - /blog/admin/ 管理博客文章
 *   - /profile 显示管理员快捷入口
 * 
 * 邮件设置：
 *   SMTP 用于审核通知。登录 /review 后可在面板中配置和测试。
 *   授权码 ≠ 登录密码，请到对应邮箱服务商处获取。
 */
return [
    // === 域名 ===
    // 你的域名（不带 https://）
    'domain' => 'your-domain.com',

    // === Linux.do OAuth ===
    // 在 Linux.do 管理后台创建 OAuth 应用
    // 普通用户查看自己的用户名：访问 https://linux.do/u/你的用户名
    'oauth' => [
        'client_id'     => 'YOUR_CLIENT_ID',
        'client_secret' => 'YOUR_CLIENT_SECRET',
        'auth_url'      => 'https://connect.linux.do/oauth2/authorize',
        'token_url'     => 'https://connect.linux.do/oauth2/token',
        'user_url'      => 'https://connect.linux.do/api/user',
    ],

    // === SMTP 邮件（审核通知）===
    // QQ 邮箱：smtp.qq.com:465，授权码在 设置→账户→POP3→生成
    // 163 邮箱：smtp.163.com:465，授权码在 设置→POP3→客户端授权密码
    // Gmail：smtp.gmail.com:587，需要应用专用密码
    'smtp' => [
        'host' => 'smtp.your-provider.com',
        'port' => 465,
        'user' => 'noreply@your-domain.com',
        'pass' => 'YOUR_SMTP_PASSWORD',   // 授权码，不是登录密码！
        'from' => 'noreply@your-domain.com',
    ],

    // === 管理员白名单 ===
    // 填入 Linux.do 用户名（可在 https://linux.do 查看）
    // 多个管理员用逗号分隔
    // 拥有权限：审核短链接(/review)、博客管理(/blog/admin/)
    'admin_users' => ['Boren.liu'],

    // === CDN（可选）===
    // 腾讯云 EdgeOne 或其他 CDN 的刷新接口凭证
    'cdn_zone_id' => '',
    'cdn_token'   => '',
];
