# CMSForum 生产部署手册

> 适用于把 CMSForum 部署到公网服务器（Linux）并达到生产可用。
> 目标读者：站点管理员 / 运维。本文档只讲「生产必备」，开发调试见《项目文档》。
> 界面预览：商城 / 付费阅读 / 后台截图见 [README 首页](../README.md#-界面预览)。

---

## 1. 环境要求

| 组件 | 最低版本 | 说明 |
| --- | --- | --- |
| PHP | 8.3（推荐 8.5） | 需扩展：`pdo_mysql`、`mbstring`、`curl`、`openssl`、`fileinfo`、`intl`、`redis`（用 Redis 时） |
| MySQL / MariaDB | 8.0 / 10.6+ | 生产建议 MySQL 8.x，utf8mb4 |
| Redis | 6.0+ | 缓存 / 队列 / 会话（可选但强烈建议） |
| Nginx | 1.24+ | 或 Apache（配 Laravel 伪静态） |
| Composer | 2.x | 构建用 |
| Node.js | 20+ | 前端构建用（构建后可不在服务器保留） |

> Windows 本地开发：Laragon（PHP 8.5 + MySQL）即可，无需 Nginx/Redis 也能跑通（database 驱动兜底）。

## 2. 上线前配置清单

以下项**不核对完不要上线**：

| 项 | 生产值 | 检查方式 |
| --- | --- | --- |
| `APP_ENV` | `production` | `.env` |
| `APP_DEBUG` | `false` | `.env`，开启会泄露源码与密钥 |
| `APP_KEY` | 随机 32 字节 | `php artisan key:generate` |
| `APP_URL` | `https://你的域名` | 影响路由与邮件链接 |
| `SESSION_SECURE_COOKIE` | `true` | 全站 HTTPS 后必须开 |
| `SESSION_ENCRYPT` | `true` | 会话内容加密，防服务端日志/备份泄露 |
| `SESSION_DRIVER` | `redis` | database 亦可，但 redis 更快 |
| `CACHE_STORE` | `redis` | 整页缓存 / 限流依赖 |
| `QUEUE_CONNECTION` | `redis` | 配合 Horizon |
| `LOG_LEVEL` | `warning` | 生产避免 debug 级日志膨胀 |
| `MAIL_*` | 真实 SMTP / Resend | 注册验证码、密码找回依赖邮件 |
| 管理员账号 | 强密码 | `ADMIN_DEFAULT_*` 建号后建议删除默认值 |

## 3. 部署步骤（Linux / Nginx）

### 3.1 拉取代码并安装依赖

```bash
git clone <你的仓库> /var/www/cmsforum
cd /var/www/cmsforum

composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate

# 编辑 .env 填入数据库/Redis/邮件等，然后：
php artisan migrate --force
php artisan db:seed --class=AdminAccountSeeder   # 创建管理员
php artisan storage:link
```

### 3.2 前端构建

```bash
npm install --ignore-scripts
npm run build
```

### 3.3 Nginx 站点配置

```nginx
server {
    listen 443 ssl http2;
    server_name your-domain.com;

    # SSL 证书（Let's Encrypt 等）
    ssl_certificate     /etc/letsencrypt/live/your-domain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/your-domain.com/privkey.pem;

    root /var/www/cmsforum/public;
    index index.php;

    charset utf-8;
    client_max_body_size 20M;   # 上传媒体

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.5-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # 敏感路径拒绝
    location ~ /\.(?!well-known) {
        deny all;
    }
    location ~ ^/(\.env|storage/app/db-migration) {
        deny all;
    }

    # 静态资源长缓存
    location ~* \.(js|css|png|jpg|jpeg|gif|svg|woff2?)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }
}

# HTTP 跳 HTTPS
server {
    listen 80;
    server_name your-domain.com;
    return 301 https://$host$request_uri;
}
```

### 3.4 常驻进程（关键！）

定时发布、队列、Horizon 依赖以下进程，**必须**用 systemd / supervisor 守护：

```ini
# /etc/systemd/system/cms-scheduler.service
[Unit]
Description=CMSForum Scheduler
After=network.target

[Service]
User=www-data
WorkingDirectory=/var/www/cmsforum
ExecStart=/usr/bin/php artisan schedule:work
Restart=always

[Install]
WantedBy=multi-user.target
```

```ini
# /etc/systemd/system/cms-horizon.service
[Unit]
Description=CMSForum Horizon
After=network.target

[Service]
User=www-data
WorkingDirectory=/var/www/cmsforum
ExecStart=/usr/bin/php artisan horizon
Restart=always

[Install]
WantedBy=multi-user.target
```

```bash
systemctl enable --now cms-scheduler cms-horizon
```

> `schedule:work` 若未运行，**定时发布不生效**（每分钟检查待发布文章）。Horizon 未运行则邮件验证码、导入导出等异步任务堆积在队列。

### 3.5 目录权限

```bash
chown -R www-data:www-data /var/www/cmsforum/storage /var/www/cmsforum/bootstrap/cache
```

## 4. 性能优化（按需启用）

后台「设置 → 性能优化」逐项开启：

| 项 | 说明 | 前提 |
| --- | --- | --- |
| 整页缓存 | 仅缓存游客 GET 200；后台 / 登录 / 动态路径自动排除 | Redis 或 file 驱动 |
| Redis 缓存 | `CACHE_STORE=redis` | 已装 phpredis |
| OPcache | `opcache.enable=1, validate_timestamps=0`（生产） | PHP 扩展 |
| Octane | 常驻进程显著降延迟 | `php artisan octane:install --server=roadrunner` |
| Horizon | 队列面板 + 进程管理 | Redis |

启用整页缓存后，验证响应头出现 `X-Page-Cache: HIT`。缓存头已按白名单过滤（**不会**重放 `Set-Cookie`，游客会话安全）。

## 5. 安全加固清单

- [x] `APP_DEBUG=false`、`APP_ENV=production`、`APP_KEY` 已生成
- [x] 全站 HTTPS，`SESSION_SECURE_COOKIE=true`，`SESSION_ENCRYPT=true`
- [x] 后台启用「登录/注册限流」（默认已开：登录 5 次/分、注册 3 次/分，算术验证码防机器人）
- [x] 注册限制：按需开启「邮箱白名单」或「邀请注册强制」（后台 → 设置 → 站点设置 → 注册限制）
- [x] Turnstile：Cloudflare 控制台创建站点，把 site/secret key 填进后台 → 人机校验升级为验证码
- [x] 支付凭据（Xcash AppID/HMAC、PayPal Client ID、码支付密钥）只存后台设置表，**不要**写入 `.env` 或源码
- [x] 定时备份：后台「备份与恢复」可导出 SQL；备份文件含用户数据，下载后妥善保管
- [ ] 定期 `composer audit` 与 `npm audit` 检查依赖漏洞
- [ ] 服务器层面：防火墙仅开放 80/443，SSH 禁密码登录，fail2ban 可选

## 6. 升级与备份

### 备份

```bash
# 数据库
mysqldump -u cmsforum -p cmsforum > backup-$(date +%F).sql

# 上传媒体与 .env（含密钥，务必加密存储）
tar czf site-files-$(date +%F).tar.gz public/uploads .env storage/app
```

### 升级

```bash
cd /var/www/cmsforum
git pull
composer install --no-dev --optimize-autoloader
npm install --ignore-scripts && npm run build   # 前端有改动时
php artisan migrate --force
php artisan config:cache route:cache view:cache
php artisan horizon:terminate                    # 重启队列进程
```

> 升级前先备份数据库与媒体目录。

## 7. 常见故障排查

| 症状 | 排查方向 |
| --- | --- |
| 首页 500 白屏 | 看 `storage/logs/laravel.log`；确认 `APP_DEBUG=false` 下日志有记录 |
| 定时发布不生效 | `systemctl status cms-scheduler`；`php artisan schedule:list` 应列出 `cms:publish-scheduled` |
| 邮件验证码收不到 | 检查 `MAIL_*` 配置；后台「设置 → 邮件服务」驱动是否为 smtp/resend（log 驱动不发信） |
| 支付后订单一直是 pending | 检查 Webhook 路由是否公网可达（`/crypto-pay/webhook`、`/shop/notify`）；回调地址是否与后台设置一致；看订单 `channel` 与回调验签是否匹配 |
| 商城 /shop 404 | 后台设置 `shop_enabled` 被关闭，改为 `1` |
| 页面缓存不生效 | 确认已登录/后台路径（自动排除）；响应头看 `X-Page-Cache` |
| 登录即 419 | `SESSION_DOMAIN` 与域名不一致；HTTPS 下 `SESSION_SECURE_COOKIE` 未开导致 cookie 未下发 |

## 8. 生产环境不建议做的事

- ❌ 用 `php artisan serve` 当生产服务器（单进程、无并发）
- ❌ 打开 `APP_DEBUG` 排障后忘记关掉
- ❌ 把 `.env`、数据库备份、服务账号 JSON 提交进 git 或公开分享
- ❌ 在非 HTTPS 下启用 `SESSION_SECURE_COOKIE`
- ❌ 跳过 `migrate --force` 直接改库

---

## 附：支付通道对接速查

| 通道 | 凭据位置 | Webhook / 回调地址 |
| --- | --- | --- |
| Xcash（加密） | 后台 → 设置 → 加密支付 → Xcash 凭证 | `https://域名/crypto-pay/webhook`（XC-Signature 验签） |
| PayPal | 后台 → 加密支付 → PayPal 凭证 | 回跳 `/shop/paypal/return`（服务端 capture） |
| 码支付 / 虎皮椒 | 后台 → 加密支付 | 异步通知 `https://域名/shop/notify`（按订单 channel 验签 + 金额校验） |
| CoinPayments | 后台 → 加密支付 | `https://域名/crypto-pay/webhook` |

后台「加密支付」设置页同时提供各渠道注册入口与汇率（USD→CNY）配置。
