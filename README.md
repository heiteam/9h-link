# 9H Link - 短链接 & 二维码生成器

免费、轻量、无需注册的在线短链接生成器和二维码生成工具。

> 一个页面、两个功能，搞定所有链接缩短和二维码生成需求。

## 功能特性

### 🔗 短链接
- 粘贴长网址，一键生成短链接
- 支持自定义短码（2-12 位字母数字）
- 301 永久重定向
- 配额管理（未登录每 12h 5 条，登录后无限）

### 📱 二维码
- 支持 6 种类型：链接、纯文本、WiFi、电话、邮箱、地理位置
- PNG / SVG 双格式下载
- 印刷级高清输出

### 🔐 用户系统
- Linux.do OAuth 登录
- 个人中心管理短链接
- 查看点击量统计

### 🛡️ 安全
- 自动恶意链接检测（黑名单 + 安全域名白名单）
- CSP 安全策略
- Rate limiting

## 技术架构

```
前端：纯静态 HTML/CSS/JS（内联，零依赖）
后端：PHP（仅返回 JSON + 301 跳转）
存储：JSON 文件（links.json）
认证：Linux.do OAuth 2.0
邮件：原生 SMTP（审核通知）
```

## 快速开始

### 1. 环境要求
- PHP 7.4+
- Nginx / Apache
- HTTPS（推荐）

### 2. 配置

```bash
# 复制配置文件
cp config.sample.php config.php

# 编辑配置
vim config.php
```

在 `config.php` 中填入：
- 你的域名
- Linux.do OAuth 应用凭证
- SMTP 邮件配置（可选，用于审核通知）
- 管理员白名单

### 3. 部署

```bash
# 克隆仓库
git clone https://github.com/heiteam/9h-link.git
cd 9h-link

# 配置 Nginx（示例）
server {
    listen 443 ssl http2;
    server_name your-domain.com;
    root /path/to/9h-link;
    index index.html;

    # 短链接重写规则
    location ~ ^/[A-Za-z0-9]{2,12}$ {
        try_files $uri @shortlink;
    }

    location @shortlink {
        rewrite ^/(.+)$ /api.php?code=$1 last;
    }

    # API 路由
    location /api.php {
        fastcgi_pass unix:/run/php/php-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # 静态页面
    location /about { try_files /about.html =404; }
    location /faq { try_files /faq.html =404; }
    location /guide { try_files /guide.html =404; }
    location /contact { try_files /contact.html =404; }
    location /privacy-policy { try_files /privacy-policy.html =404; }
    location /stats { try_files /stats.html =404; }

    # 安全头
    add_header X-Content-Type-Options nosniff;
    add_header X-Frame-Options DENY;
    add_header Referrer-Policy strict-origin-when-cross-origin;
}
```

### 4. 文件权限

```bash
chmod 644 *.php *.html *.json
chmod 755 data/
chmod 666 data/notify_config.json  # 需要 Web 服务写入
```

## 项目结构

```
.
├── index.html          # 首页（短链接 + 二维码生成器）
├── api.php             # 核心 API（创建/跳转/统计）
├── config.sample.php   # 配置文件示例
├── smtp_mail.php       # SMTP 邮件发送
├── blacklist.php       # 黑名单检测
├── auth/               # OAuth 登录
│   ├── login.php       # 登录入口
│   ├── callback.php    # OAuth 回调
│   ├── check.php       # 登录状态检查
│   └── session_init.php
├── blog/               # 博客系统
├── css/style.css       # 全局样式
├── data/               # 运行时数据
├── about.html          # 关于页面
├── faq.html            # 常见问题
├── guide.html          # 使用教程
├── contact.html        # 联系方式
├── privacy-policy.html # 隐私政策
└── ...
```

## 管理功能

- **审核面板** (`review.php`)：审核新提交的短链接
- **个人中心** (`profile.php`)：管理自己的短链接
- **统计页面** (`stats.html`)：全站使用统计

## License

MIT License

## 致谢

- [Linux.do](https://linux.do) OAuth 认证
- [QR Code Generator](https://github.com/davidshimjs/qrcodejs) 二维码生成
