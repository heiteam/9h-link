# 🚀 云服务器部署指南

本文档提供从零开始在云服务器上部署 9H Link 短链接服务的完整步骤。

## 目录

- [环境要求](#环境要求)
- [宝塔面板部署（推荐新手）](#宝塔面板部署推荐新手)
- [快速部署（一键脚本）](#快速部署)
- [手动部署](#手动部署)
  - [1. 系统初始化](#1-系统初始化)
  - [2. 安装 PHP + Nginx](#2-安装-php--nginx)
  - [3. 上传代码](#3-上传代码)
  - [4. 配置 Nginx](#4-配置-nginx)
  - [5. 配置 SSL](#5-配置-ssl)
  - [6. 配置应用](#6-配置应用)
  - [7. 防火墙设置](#7-防火墙设置)
- [安全加固](#安全加固)
- [维护操作](#维护操作)
- [常见问题](#常见问题)

---

## 环境要求

| 项目 | 最低要求 | 推荐配置 |
|------|---------|---------|
| 操作系统 | Ubuntu 20.04 / CentOS 7 | Ubuntu 22.04 LTS |
| PHP | 7.4+ | 8.2 |
| Nginx | 1.18+ | 1.24+ |
| 内存 | 512MB | 1GB+ |
| 磁盘 | 10GB | 20GB+ |
| 域名 | 已备案（国内）或无需备案（港澳台/海外） | - |

**支持的云平台：**
- ☁️ 阿里云 ECS / 轻量应用服务器
- 🌐 腾讯云 CVM / 轻量应用服务器
- 🔵 华为云 ECS
- 🟢 AWS EC2 / Lightsail
- 🟡 Google Cloud GCE
- 🟠 Azure VM

---

## 宝塔面板部署（推荐新手）

> 宝塔面板提供可视化 Web 界面，适合不熟悉命令行的用户。全程鼠标操作，5 分钟完成部署。

### 1. 安装宝塔面板

```bash
# SSH 登录服务器后执行（Ubuntu/Debian）
curl -sSO https://download.bt.cn/install/install_panel.sh && bash install_panel.sh
```

安装完成后会显示：
```
==================================================================
面板地址:  http://你的IP:8888/安全入口
默认账号:  xxxxxx
默认密码:  xxxxxxxx
==================================================================
```

**⚠️ 请立即复制保存这些信息！**

### 2. 安装运行环境

1. 浏览器打开面板地址并登录
2. 首次登录会弹出 **「推荐安装套件」** 对话框
3. 选择 **LNMP（推荐）**：
   - Nginx 1.24+ ✅
   - PHP 8.2 ✅（必须 7.4 以上）
   - MySQL 不需要，点 ❌ 取消勾选
4. 点击 **「一键安装」**，等待 5-15 分钟

> 💡 如果已经错过弹窗，在左侧菜单 → **软件商店** → 搜索 Nginx 和 PHP 逐个安装。

### 3. 上传代码

**方法 A：通过面板上传（最简单）**

1. 左侧菜单 → **文件** → 进入 `/www/wwwroot/`
2. 点击 **「上传」** 按钮
3. 上传你本地的 9h-link 代码压缩包（zip/tar.gz）
4. 上传后右键 → **解压**

**方法 B：Git 克隆（推荐）**

1. 左侧菜单 → **终端**
2. 执行：
```bash
cd /www/wwwroot/
git clone https://github.com/heiteam/9h-link.git 9h-link
```

**方法 C：SSH 命令（适合有经验的用户）**

```bash
# 在你本地电脑执行
scp -r ./9h-link root@你的服务器IP:/www/wwwroot/9h-link
```

### 4. 添加网站

1. 左侧菜单 → **网站** → **添加站点**
2. 填写：
   - **域名**：`your-domain.com`（你的实际域名）
   - **根目录**：`/www/wwwroot/9h-link`
   - **PHP 版本**：选择 8.2
   - **数据库**：不创建（不需要）
3. 点击 **「提交」**

### 5. 配置站点

进入站点设置 → **修改**（点击站点名）：

**a) 关闭跨站攻击防护（否则 API 调用会被拦截）：**

站点设置 → **安全** → 取消勾选 **「防跨站攻击(open_basedir)」**

**b) 伪静态规则：**

站点设置 → **伪静态** → 选择 **「其他配置」** → 粘贴：

```nginx
# 短链接跳转
location ~ ^/[A-Za-z0-9]{2,12}$ {
    try_files $uri @shortlink;
}
location @shortlink {
    rewrite ^/(.+)$ /api.php?code=$1 last;
}

# 静态页面路由
location /about { try_files /about.html =404; }
location /faq { try_files /faq.html =404; }
location /guide { try_files /guide.html =404; }
location /contact { try_files /contact.html =404; }
location /privacy-policy { try_files /privacy-policy.html =404; }
location /stats { try_files /stats.html =404; }

# 禁止访问数据文件
location ~ ^/(data|blacklist|links) { deny all; }
location ~ /\. { deny all; }
```

**c) 配置 PHP（禁用危险函数）：**

1. 左侧菜单 → **软件商店** → 找到 **PHP 8.2** → **设置**
2. → **禁用函数** → 确认以下函数已禁用：
   - `exec`, `passthru`, `shell_exec`, `system`, `proc_open`, `popen`
   - 如果没有，在输入框中添加后点 **「保存」**
3. → **性能调整** → 开启 **OPcache** → 保存

### 6. 配置应用

```bash
# SSH 进入服务器
cd /www/wwwroot/9h-link

# 复制配置文件
cp config.sample.php config.php

# 编辑配置
vim config.php
```

填入你的域名、OAuth 凭证、SMTP 配置和管理员用户名（详见 README.md）。

### 7. 设置 SSL（HTTPS）

1. 左侧菜单 → **网站** → 点击你的域名 → **SSL**
2. 选择 **「Let's Encrypt」** → 勾选域名 → 点击 **「申请」**
3. 申请成功后，开启 **「强制 HTTPS」**

> 💡 前提：域名 DNS 已正确解析到服务器 IP。如果申请失败，检查防火墙是否放行 80 端口。

### 8. 设置防火墙

1. 左侧菜单 → **安全** → **系统防火墙**
2. 开放端口：
   - **8888**（宝塔面板，可改）
   - **80**（HTTP）
   - **443**（HTTPS）
   - **22**（SSH）
3. **同时在云服务商控制台的安全组中也要放行以上端口！**

### 9. 验证部署

1. 浏览器访问 `https://your-domain.com`
2. 测试短链接生成
3. 测试二维码生成
4. 登录 Linux.do → 访问 `/review` 确认管理员权限

### 宝塔面板常见操作速查

| 操作 | 位置 |
|------|------|
| 查看访问日志 | 站点设置 → 日志 |
| 修改 PHP 版本 | 软件商店 → PHP → 版本切换 |
| 更新 SSL 证书 | 站点设置 → SSL → 续签 |
| 修改文件权限 | 文件管理 → 右键 → 属性 |
| 查看 PHP 错误日志 | 软件商店 → PHP → 错误日志 |
| 重启所有服务 | 首页 → 重启 → Nginx + PHP |
| 宝塔面板密码重置 | SSH 执行 `bt default` 查看 |

### ⚠️ 宝塔面板安全建议

1. **修改面板默认端口**（8888 → 其他端口）
2. **开启面板登录限制 IP**（安全 → 面板设置）
3. **关闭不用的端口**（只开 22/80/443）
4. **定期更新面板**（软件商店 → 宝塔面板 → 更新）

---

## 快速部署

适用于 Ubuntu 22.04 LTS，一键完成所有配置：

```bash
# 1. SSH 登录服务器
ssh root@your-server-ip

# 2. 下载部署脚本
curl -fsSL https://raw.githubusercontent.com/heiteam/9h-link/main/deploy.sh -o deploy.sh

# 3. 编辑配置（域名、OAuth 等）
vim deploy.sh

# 4. 执行部署
chmod +x deploy.sh
sudo ./deploy.sh
```

---

## 手动部署

### 1. 系统初始化

```bash
# 更新系统
sudo apt update && sudo apt upgrade -y

# 设置时区
sudo timedatectl set-timezone Asia/Shanghai

# 创建部署用户（可选但推荐）
sudo adduser deploy
sudo usermod -aG sudo deploy
su - deploy
```

### 2. 安装 PHP + Nginx

**Ubuntu / Debian：**

```bash
# 安装 PHP 8.2 + 常用扩展
sudo apt install -y php8.2-fpm php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip

# 安装 Nginx
sudo apt install -y nginx

# 启动服务
sudo systemctl enable nginx php8.2-fpm
sudo systemctl start nginx php8.2-fpm
```

**CentOS / AlmaLinux：**

```bash
# 安装 EPEL + Remi（PHP 8.2）
sudo dnf install -y epel-release
sudo dnf install -y https://rpms.remirepo.net/enterprise/remi-release-8.rpm
sudo dnf module reset php -y
sudo dnf module enable php:remi-8.2 -y
sudo dnf install -y php-fpm php-mbstring php-xml php-curl php-zip

# 安装 Nginx
sudo dnf install -y nginx

# 启动服务
sudo systemctl enable nginx php-fpm
sudo systemctl start nginx php-fpm
```

### 3. 上传代码

```bash
# 方法一：Git 克隆（推荐）
cd /var/www
sudo git clone https://github.com/heiteam/9h-link.git
sudo chown -R deploy:deploy /var/www/9h-link

# 方法二：SCP 上传
scp -r ./9h-link root@your-server-ip:/var/www/

# 方法三：直接下载
cd /var/www
sudo curl -L https://github.com/heiteam/9h-link/archive/main.tar.gz | sudo tar xz
sudo mv 9h-link-main 9h-link
sudo chown -R deploy:deploy /var/www/9h-link
```

### 4. 配置 Nginx

```bash
sudo vim /etc/nginx/sites-available/9h-link
```

写入以下配置：

```nginx
server {
    listen 80;
    server_name your-domain.com www.your-domain.com;
    
    # HTTP -> HTTPS 重定向（配置 SSL 后取消注释）
    # return 301 https://$host$request_uri;

    root /var/www/9h-link;
    index index.html;

    # === 安全头 ===
    add_header X-Content-Type-Options nosniff always;
    add_header X-Frame-Options DENY always;
    add_header Referrer-Policy strict-origin-when-cross-origin always;
    add_header X-XSS-Protection "1; mode=block" always;

    # === 静态文件缓存 ===
    location ~* \.(ico|svg|png|jpg|jpeg|gif|webp|css|js|woff2|ttf|eot)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    # === 短链接跳转规则 ===
    location ~ ^/[A-Za-z0-9]{2,12}$ {
        try_files $uri @shortlink;
    }

    location @shortlink {
        rewrite ^/(.+)$ /api.php?code=$1 last;
    }

    # === API 路由 ===
    location /api.php {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        
        # 安全：限制请求方法
        limit_except GET POST {
            deny all;
        }
    }

    # === 博客 API ===
    location /blog/api.php {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # === 管理面板（限制访问）===
    location /review {
        # allow your-ip;
        # deny all;
        try_files /review.php =404;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root/review.php;
        include fastcgi_params;
    }

    location /blog/admin {
        # allow your-ip;
        # deny all;
        try_files $uri $uri/ =404;
    }

    # === 静态页面路由 ===
    location /about { try_files /about.html =404; }
    location /faq { try_files /faq.html =404; }
    location /guide { try_files /guide.html =404; }
    location /contact { try_files /contact.html =404; }
    location /privacy-policy { try_files /privacy-policy.html =404; }
    location /stats { try_files /stats.html =404; }

    # === 黑名单文件保护 ===
    location ~ ^/(data|blacklist|links) {
        deny all;
    }

    # === 禁止访问隐藏文件 ===
    location ~ /\. {
        deny all;
    }

    # === 默认处理 ===
    location / {
        try_files $uri $uri/ /index.html;
    }

    # === 错误页面 ===
    error_page 404 /404.html;
    error_page 403 /403.html;

    # === 日志 ===
    access_log /var/log/nginx/9h-link-access.log;
    error_log /var/log/nginx/9h-link-error.log;
}
```

启用站点：

```bash
# 创建软链接
sudo ln -s /etc/nginx/sites-available/9h-link /etc/nginx/sites-enabled/

# 删除默认站点（可选）
sudo rm -f /etc/nginx/sites-enabled/default

# 测试配置
sudo nginx -t

# 重载 Nginx
sudo systemctl reload nginx
```

### 5. 配置 SSL

**方法一：Let's Encrypt（免费，推荐）**

```bash
# 安装 Certbot
sudo apt install -y certbot python3-certbot-nginx

# 申请证书（自动修改 Nginx 配置）
sudo certbot --nginx -d your-domain.com -d www.your-domain.com

# 测试自动续期
sudo certbot renew --dry-run

# 添加自动续期 cron
echo "0 0,12 * * * root certbot renew --quiet --post-hook 'systemctl reload nginx'" | sudo tee /etc/cron.d/certbot-renew
```

**方法二：手动上传证书**

```bash
# 创建证书目录
sudo mkdir -p /etc/nginx/ssl

# 上传证书文件
scp your-domain.com.pem root@server:/etc/nginx/ssl/
scp your-domain.com.key root@server:/etc/nginx/ssl/

# 修改 Nginx 配置，添加：
# listen 443 ssl http2;
# ssl_certificate /etc/nginx/ssl/your-domain.com.pem;
# ssl_certificate_key /etc/nginx/ssl/your-domain.com.key;
# ssl_protocols TLSv1.2 TLSv1.3;
# ssl_ciphers HIGH:!aNULL:!MD5;
```

### 6. 配置应用

```bash
cd /var/www/9h-link

# 复制配置文件
cp config.sample.php config.php

# 编辑配置
vim config.php
```

**config.php 关键配置项：**

```php
<?php
return [
    'domain' => 'your-domain.com',  // 你的域名

    'oauth' => [
        'client_id'     => 'YOUR_CLIENT_ID',      // Linux.do OAuth 应用 ID
        'client_secret' => 'YOUR_CLIENT_SECRET',  // Linux.do OAuth 密钥
        'auth_url'      => 'https://connect.linux.do/oauth2/authorize',
        'token_url'     => 'https://connect.linux.do/oauth2/token',
        'user_url'      => 'https://connect.linux.do/api/user',
    ],

    'smtp' => [
        'host' => 'smtp.your-provider.com',  // SMTP 服务器
        'port' => 465,                        // 端口（465=SSL, 587=STARTTLS）
        'user' => 'noreply@your-domain.com', // SMTP 用户名
        'pass' => 'YOUR_SMTP_PASSWORD',      // SMTP 密码
        'from' => 'noreply@your-domain.com', // 发件人
    ],

    'admin_users' => ['your_username'],  // 管理员 Linux.do 用户名

    'cdn_zone_id' => '',  // 腾讯云 EdgeOne Zone ID（可选）
    'cdn_token'   => '',  // 腾讯云 API Token（可选）
];
```

**Linux.do OAuth 配置步骤：**

1. 访问你的个人资料页查看用户名：`https://linux.do/u/你的用户名`
2. 在 Linux.do 管理后台创建 OAuth 应用
3. 填写：
   - Name: `9H Link`
   - Redirect URI: `https://your-domain.com/auth/callback.php`
4. 将 Client ID 和 Client Secret 填入 config.php

**设置文件权限：**

```bash
# Web 服务可读
sudo chown -R www-data:www-data /var/www/9h-link

# 目录权限
sudo find /var/www/9h-link -type d -exec chmod 755 {} \;

# 文件权限
sudo find /var/www/9h-link -type f -exec chmod 644 {} \;

# 数据目录可写
sudo chmod 777 /var/www/9h-link/data/
sudo touch /var/www/9h-link/links.json
sudo chmod 666 /var/www/9h-link/links.json

# 确保 PHP-FPM 用户可写
sudo chown -R www-data:www-data /var/www/9h-link/data
sudo chown www-data:www-data /var/www/9h-link/links.json
```

### 7. 防火墙设置

**Ubuntu（UFW）：**

```bash
sudo ufw allow 22/tcp    # SSH
sudo ufw allow 80/tcp    # HTTP
sudo ufw allow 443/tcp   # HTTPS
sudo ufw enable
sudo ufw status
```

**CentOS（firewalld）：**

```bash
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --permanent --add-service=https
sudo firewall-cmd --permanent --add-service=ssh
sudo firewall-cmd --reload
```

**云服务商安全组：**

| 端口 | 协议 | 用途 | 推荐策略 |
|------|------|------|---------|
| 22 | TCP | SSH | 限制来源 IP |
| 80 | TCP | HTTP | 0.0.0.0/0 |
| 443 | TCP | HTTPS | 0.0.0.0/0 |
| 8888 | TCP | 宝塔面板（如有） | 限制来源 IP |

---

## 安全加固

### 1. PHP 配置加固

```bash
sudo vim /etc/php/8.2/fpm/php.ini
```

```ini
# 禁用危险函数
disable_functions = exec,passthru,shell_exec,system,proc_open,popen,show_source,highlight_file

# 禁用远程文件访问
allow_url_fopen = Off
allow_url_include = Off

# 显示错误（生产环境关闭）
display_errors = Off
log_errors = On

# 限制上传
file_uploads = On
upload_max_filesize = 2M
post_max_size = 8M
```

```bash
sudo systemctl restart php8.2-fpm
```

### 2. Nginx 限流

在 Nginx 配置的 `server` 块中添加：

```nginx
# 请求频率限制
limit_req_zone $binary_remote_addr zone=api:10m rate=10r/s;
limit_req_zone $binary_remote_addr zone=shortlink:10m rate=30r/s;

# API 限流
location /api.php {
    limit_req zone=api burst=5 nodelay;
    # ... fastcgi 配置
}

# 短链接跳转限流
location @shortlink {
    limit_req zone=shortlink burst=50 nodelay;
    # ... rewrite 规则
}
```

### 3. Fail2Ban（防暴力破解）

```bash
sudo apt install -y fail2ban

# 创建 Nginx 限流规则
sudo vim /etc/fail2ban/filter.d/nginx-limit-req.conf
```

```ini
[Definition]
failregex = limiting requests, excess: .* by zone .*, client: <HOST>
ignoreregex =
```

```bash
sudo systemctl enable fail2ban
sudo systemctl start fail2ban
```

---

## 维护操作

### 查看日志

```bash
# Nginx 访问日志
tail -f /var/log/nginx/9h-link-access.log

# Nginx 错误日志
tail -f /var/log/nginx/9h-link-error.log

# PHP-FPM 错误日志
tail -f /var/log/php8.2-fpm.log
```

### 更新代码

```bash
cd /var/www/9h-link
git pull origin main
sudo systemctl reload nginx
```

### 备份数据

```bash
# 备份链接数据
tar czf /backup/9h-link-$(date +%Y%m%d).tar.gz \
  /var/www/9h-link/links.json \
  /var/www/9h-link/data/ \
  /var/www/9h-link/stats/

# 自动备份（添加 cron）
echo "0 3 * * * tar czf /backup/9h-link-$(date +\%Y\%m\%d).tar.gz /var/www/9h-link/links.json /var/www/9h-link/data/ /var/www/9h-link/stats/" | sudo tee /etc/cron.d/9h-backup
```

### 性能优化

```bash
# 启用 Nginx Gzip
# 在 http 块中添加：
gzip on;
gzip_types text/plain text/css application/json application/javascript text/xml;
gzip_min_length 1024;

# 启用 OPcache（PHP 加速）
# 在 php.ini 中：
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=10000
```

---

## 常见问题

### Q: 短链接跳转 404？

检查 Nginx 的 `try_files` 和 `rewrite` 规则是否正确：

```bash
sudo nginx -t
sudo tail -20 /var/log/nginx/9h-link-error.log
```

### Q: 创建短链接返回 403？

1. 检查 `config.php` 是否存在
2. 检查 CSRF 校验中的域名是否与实际域名一致
3. 检查 Nginx 的 `fastcgi_param` 配置

### Q: PHP 文件 500 错误？

```bash
# 查看 PHP 错误日志
tail -50 /var/log/php8.2-fpm.log

# 检查文件权限
ls -la /var/www/9h-link/
```

### Q: OAuth 登录失败？

1. 确认 Linux.do OAuth 应用的 Redirect URI 与实际回调地址一致
2. 确认服务器能访问 `connect.linux.do`
3. 检查 config.php 中的 client_id 和 client_secret

### Q: 如何切换到 MySQL？

当前版本使用 JSON 文件存储。如需更高并发，建议：

1. 创建 MySQL 数据库和 `links` 表
2. 修改 `api.php` 中的 `load_links` / `save_links` / `record_click` 函数
3. 使用 PDO 连接数据库

---

## 架构图

```
用户浏览器
    │
    ├─ HTTPS ─→ Nginx (443)
    │               │
    │               ├── 静态文件 → /var/www/9h-link/
    │               │
    │               └── /api.php → PHP-FPM
    │                                  │
    │                                  ├── links.json (数据)
    │                                  ├── data/ (配额/配置)
    │                                  └── stats/ (统计)
    │
    └── DNS ─→ 你的域名
```

---

*最后更新：2026-09-01*
