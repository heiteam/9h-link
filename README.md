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

# 编置配置
vim config.php
```

### 3. 管理员权限设置

管理员权限在 `config.php` 的 `admin_users` 数组中配置：

```php
return [
    // 管理员白名单（Linux.do 用户名）
    // 可设置多个管理员，数组中的用户拥有审核和博客管理权限
    'admin_users' => ['your_username', 'another_admin'],
];
```

**管理员能做什么：**
| 功能 | 入口 | 权限控制 |
|------|------|---------|
| 审核短链接 | `/review` | `admin_users` 白名单 |
| 博客后台 | `/blog/admin/` | `admin_users` 白名单 |
| 个人中心管理入口 | `/profile` | 所有登录用户可见 |

**设置步骤：**
1. 登录 Linux.do，点击右上角头像 → **「个人资料」** 查看你的用户名
2. 或直接访问 `https://linux.do/u/你的用户名` 确认
3. 将用户名填入 `config.php` 的 `admin_users` 数组
4. 登录网站后，审核和博客后台会自动对白名单用户开放

### 4. 邮件发送设置

邮件用于审核通知——当有新短链接提交审核时，自动邮件通知管理员。

**配置方式（config.php）：**

```php
return [
    'smtp' => [
        'host' => 'smtp.qq.com',        // SMTP 服务器
        'port' => 465,                   // 端口（465=SSL，587=STARTTLS）
        'user' => 'your@qq.com',         // SMTP 用户名（通常是邮箱地址）
        'pass' => 'your_smtp_password',  // SMTP 授权码（不是登录密码！）
        'from' => 'your@qq.com',         // 发件人邮箱
    ],
];
```

**SMTP 授权码获取方式：**

| 服务商 | 获取路径 | 说明 |
|--------|---------|------|
| QQ 邮箱 | 设置 → 账户 → POP3/SMTP 服务 → 开启 → 生成授权码 | 16 位授权码 |
| 163 邮箱 | 设置 → POP3/SMTP → 开启 → 客户端授权密码 | 16 位授权码 |
| Gmail | Google 账号 → 安全 → 两步验证 → 应用专用密码 | 16 位应用密码 |
| Outlook | Outlook.com → SMTP → 开启 → 生成应用密码 | - |
| 企业邮箱 | 联系管理员获取 SMTP 凭证 | - |

**在审核后台配置（可选）：**

登录 `/review` 后，管理员可以在审核面板中实时配置邮件通知：
- 开启/关闭通知
- 设置接收邮箱
- 设置通知级别（全部/仅高风险）
- 发送测试邮件验证配置

### 5. 部署

详细部署步骤请参考 [DEPLOY.md](DEPLOY.md)（包含阿里云/腾讯云/AWS 一键部署脚本）。

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
