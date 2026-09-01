#!/bin/bash
# 9H Link - 一键部署脚本 (Ubuntu 22.04 LTS)
# 用法: sudo ./deploy.sh
set -e

echo "========================================="
echo "  9H Link - 短链接 & 二维码生成器 部署"
echo "========================================="

# === 配置区（请修改以下变量）===
DOMAIN="your-domain.com"           # 你的域名
ADMIN_USER="your_username"         # 管理员 Linux.do 用户名
DEPLOY_DIR="/var/www/9h-link"      # 部署目录
PHP_VERSION="8.2"                  # PHP 版本

# === 颜色 ===
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log() { echo -e "${GREEN}[✓]${NC} $1"; }
warn() { echo -e "${YELLOW}[!]${NC} $1"; }
err() { echo -e "${RED}[✗]${NC} $1"; exit 1; }

# === 检查 root ===
if [[ $EUID -ne 0 ]]; then
    err "请使用 sudo 运行此脚本"
fi

# === 交互式配置 ===
read -p "域名 [$DOMAIN]: " input
DOMAIN="${input:-$DOMAIN}"
read -p "管理员 Linux.do 用户名 [$ADMIN_USER]: " input
ADMIN_USER="${input:-$ADMIN_USER}"

echo ""
echo "配置信息："
echo "  域名: $DOMAIN"
echo "  管理员: $ADMIN_USER"
echo "  部署目录: $DEPLOY_DIR"
echo ""
read -p "确认部署？(y/N): " confirm
[[ "$confirm" =~ ^[Yy]$ ]] || exit 0

# === 1. 系统更新 ===
log "更新系统..."
apt update -qq && apt upgrade -y -qq

# === 2. 安装依赖 ===
log "安装 PHP ${PHP_VERSION} + Nginx..."
apt install -y -qq php${PHP_VERSION}-fpm php${PHP_VERSION}-mbstring php${PHP_VERSION}-xml php${PHP_VERSION}-curl php${PHP_VERSION}-zip nginx git curl

# === 3. 克隆代码 ===
log "下载 9H Link..."
mkdir -p "$DEPLOY_DIR"
if [ -d "$DEPLOY_DIR/.git" ]; then
    cd "$DEPLOY_DIR" && git pull
else
    rm -rf "$DEPLOY_DIR"
    git clone https://github.com/heiteam/9h-link.git "$DEPLOY_DIR"
fi

# === 4. 配置文件 ===
if [ ! -f "$DEPLOY_DIR/config.php" ]; then
    log "创建配置文件..."
    cp "$DEPLOY_DIR/config.sample.php" "$DEPLOY_DIR/config.php"
    sed -i "s/your-domain.com/$DOMAIN/g" "$DEPLOY_DIR/config.php"
    sed -i "s/admin_username/$ADMIN_USER/g" "$DEPLOY_DIR/config.php"
    warn "请编辑 $DEPLOY_DIR/config.php 填入 OAuth 和 SMTP 配置"
fi

# === 5. 文件权限 ===
log "设置文件权限..."
chown -R www-data:www-data "$DEPLOY_DIR"
chmod -R 755 "$DEPLOY_DIR"
chmod 777 "$DEPLOY_DIR/data/"
chmod 666 "$DEPLOY_DIR/links.json" 2>/dev/null || touch "$DEPLOY_DIR/links.json" && chmod 666 "$DEPLOY_DIR/links.json"

# === 6. Nginx 配置 ===
log "配置 Nginx..."
cat > /etc/nginx/sites-available/9h-link << NGINX
server {
    listen 80;
    server_name $DOMAIN www.$DOMAIN;
    root $DEPLOY_DIR;
    index index.html;

    add_header X-Content-Type-Options nosniff always;
    add_header X-Frame-Options DENY always;
    add_header Referrer-Policy strict-origin-when-cross-origin always;

    location ~* \.(ico|svg|png|jpg|jpeg|gif|webp|css|js|woff2)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    location ~ ^/[A-Za-z0-9]{2,12}$ {
        try_files \$uri @shortlink;
    }

    location @shortlink {
        rewrite ^/(.+)$ /api.php?code=\$1 last;
    }

    location /api.php {
        fastcgi_pass unix:/run/php/php${PHP_VERSION}-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
        limit_except GET POST { deny all; }
    }

    location /blog/api.php {
        fastcgi_pass unix:/run/php/php${PHP_VERSION}-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location /about { try_files /about.html =404; }
    location /faq { try_files /faq.html =404; }
    location /guide { try_files /guide.html =404; }
    location /contact { try_files /contact.html =404; }
    location /privacy-policy { try_files /privacy-policy.html =404; }
    location /terms { try_files /terms.html =404; }
    location /stats { try_files /stats.html =404; }

    location ~ ^/(data|blacklist|links) { deny all; }
    location ~ /\. { deny all; }

    location / { try_files \$uri \$uri/ /index.html; }
    error_page 404 /404.html;
    error_page 403 /403.html;
}
NGINX

ln -sf /etc/nginx/sites-available/9h-link /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default
nginx -t || err "Nginx 配置测试失败"

# === 7. 重启服务 ===
log "重启服务..."
systemctl reload nginx
systemctl restart php${PHP_VERSION}-fpm

# === 8. SSL（可选）===
echo ""
warn "SSL 证书配置："
echo "  1. 确保域名 DNS 已指向本服务器 IP"
echo "  2. 运行: sudo certbot --nginx -d $DOMAIN -d www.$DOMAIN"
echo "  3. 自动续期已配置（Certbot 安装时自动设置）"
echo ""

log "部署完成！"
echo ""
echo "  网站地址: http://$DOMAIN"
echo "  配置文件: $DEPLOY_DIR/config.php"
echo "  Nginx 配置: /etc/nginx/sites-available/9h-link"
echo ""
echo "  下一步："
echo "  1. 编辑 $DEPLOY_DIR/config.php 填入 OAuth/SMTP 配置"
echo "  2. 配置 SSL: sudo certbot --nginx -d $DOMAIN -d www.$DOMAIN"
echo "  3. 在 Linux.do 创建 OAuth 应用"
echo ""
