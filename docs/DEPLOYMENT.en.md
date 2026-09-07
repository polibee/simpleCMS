# CMSForum Production Deployment Guide

> For deploying CMSForum to a public server (Linux) and running it at production quality.
> Audience: site administrators / ops. This guide covers production essentials only — see the full project docs for development & debugging.
> Screenshots: see the [README](../README.en.md#-screenshots) for the shop, paid reading and admin panel previews.

---

## 1. Environment Requirements

| Component | Minimum | Notes |
| --- | --- | --- |
| PHP | 8.3 (8.5 recommended) | Extensions: `pdo_mysql`, `mbstring`, `curl`, `openssl`, `fileinfo`, `intl`, `redis` (when using Redis) |
| MySQL / MariaDB | 8.0 / 10.6+ | MySQL 8.x recommended, utf8mb4 |
| Redis | 6.0+ | Cache / queue / sessions (optional but strongly recommended) |
| Nginx | 1.24+ | Or Apache with Laravel-friendly rewrite rules |
| Composer | 2.x | For building |
| Node.js | 20+ | For front-end build (not required on the server afterwards) |

> Windows local development: Laragon (PHP 8.5 + MySQL) works without Nginx/Redis (database drivers are used as fallback).

## 2. Pre-Launch Configuration Checklist

Do **not** go live until every item is verified:

| Item | Production value | How to check |
| --- | --- | --- |
| `APP_ENV` | `production` | `.env` |
| `APP_DEBUG` | `false` | `.env` — leaving it on leaks source code and secrets |
| `APP_KEY` | random 32 bytes | `php artisan key:generate` |
| `APP_URL` | `https://your-domain.com` | affects routes and email links |
| `SESSION_SECURE_COOKIE` | `true` | required once the whole site is HTTPS |
| `SESSION_ENCRYPT` | `true` | encrypts sessions against server log / backup leakage |
| `SESSION_DRIVER` | `redis` | database also works, redis is faster |
| `CACHE_STORE` | `redis` | used by full-page cache and rate limits |
| `QUEUE_CONNECTION` | `redis` | used with Horizon |
| `LOG_LEVEL` | `warning` | avoid debug-level log growth in production |
| `MAIL_*` | real SMTP / Resend | registration codes and password reset rely on email |
| Admin account | strong password | remove `ADMIN_DEFAULT_*` from `.env` after creating the account |

## 3. Deployment Steps (Linux / Nginx)

### 3.1 Clone the code and install dependencies

```bash
git clone <your-repository> /var/www/cmsforum
cd /var/www/cmsforum

composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate

# Edit .env with database / Redis / mail values, then:
php artisan migrate --force
php artisan db:seed --class=AdminAccountSeeder   # create the admin account
php artisan storage:link
```

### 3.2 Front-end build

```bash
npm install --ignore-scripts
npm run build
```

### 3.3 Nginx site configuration

```nginx
server {
    listen 443 ssl http2;
    server_name your-domain.com;

    # SSL certificate (Let's Encrypt etc.)
    ssl_certificate     /etc/letsencrypt/live/your-domain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/your-domain.com/privkey.pem;

    root /var/www/cmsforum/public;
    index index.php;

    charset utf-8;
    client_max_body_size 20M;   # media uploads

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.5-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Deny sensitive paths
    location ~ /\.(?!well-known) {
        deny all;
    }
    location ~ ^/(\.env|storage/app/db-migration) {
        deny all;
    }

    # Long cache for static assets
    location ~* \.(js|css|png|jpg|jpeg|gif|svg|woff2?)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }
}

# HTTP → HTTPS redirect
server {
    listen 80;
    server_name your-domain.com;
    return 301 https://$host$request_uri;
}
```

### 3.4 Long-running processes (critical!)

Scheduled publishing, queues and Horizon depend on these processes. **You must** supervise them with systemd / supervisor:

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

> If `schedule:work` is not running, **scheduled publishing does not work** (it checks for posts to publish every minute). If Horizon is not running, async jobs (email codes, import/export) pile up in the queue.

### 3.5 Directory permissions

```bash
chown -R www-data:www-data /var/www/cmsforum/storage /var/www/cmsforum/bootstrap/cache
```

## 4. Performance Optimizations (optional)

Enable step by step under Admin → Settings → Performance:

| Feature | Description | Prerequisite |
| --- | --- | --- |
| Full-page cache | Caches guest GET 200 pages only; admin / login / dynamic paths are excluded automatically | Redis or file driver |
| Redis cache | `CACHE_STORE=redis` | phpredis installed |
| OPcache | `opcache.enable=1, validate_timestamps=0` (production) | PHP extension |
| Octane | Long-running process, significantly lower latency | `php artisan octane:install --server=roadrunner` |
| Horizon | Queue dashboard + process management | Redis |

After enabling the full-page cache, verify the response header contains `X-Page-Cache: HIT`. Cached headers are whitelist-filtered (**`Set-Cookie` is never replayed**, guest sessions stay safe).

## 5. Security Hardening Checklist

- [x] `APP_DEBUG=false`, `APP_ENV=production`, `APP_KEY` generated
- [x] Full HTTPS; `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true`
- [x] Login/register rate limits enabled in admin (on by default: 5 attempts/min login, 3/min register, math CAPTCHA anti-bot)
- [x] Registration restrictions as needed: email domain whitelist or mandatory invite codes (Admin → Settings → Site Settings → Registration restrictions)
- [x] Turnstile: create a site in the Cloudflare console and paste site/secret keys into the admin panel to upgrade the human check
- [x] Payment credentials (Xcash AppID/HMAC, PayPal Client ID, CodePay secret) are stored **only** in the admin settings table — never in `.env` or source code
- [x] Scheduled backups: the admin "Backup & Restore" page can export SQL; backups contain user data — store downloads securely
- [ ] Run `composer audit` and `npm audit` periodically for dependency vulnerabilities
- [ ] Server-level: firewall open 80/443 only, disable SSH password login, optional fail2ban

## 6. Upgrades & Backups

### Backup

```bash
# Database
mysqldump -u cmsforum -p cmsforum > backup-$(date +%F).sql

# Uploaded media and .env (contains secrets — encrypt before storing)
tar czf site-files-$(date +%F).tar.gz public/uploads .env storage/app
```

### Upgrade

```bash
cd /var/www/cmsforum
git pull
composer install --no-dev --optimize-autoloader
npm install --ignore-scripts && npm run build   # only when front-end changed
php artisan migrate --force
php artisan config:cache route:cache view:cache
php artisan horizon:terminate                    # restart queue processes
```

> Back up the database and media directory before upgrading.

## 7. Troubleshooting

| Symptom | What to check |
| --- | --- |
| Blank 500 on the homepage | Read `storage/logs/laravel.log`; confirm entries exist with `APP_DEBUG=false` |
| Scheduled publishing does not run | `systemctl status cms-scheduler`; `php artisan schedule:list` should show `cms:publish-scheduled` |
| Email codes never arrive | Check `MAIL_*`; admin Settings → Mail service must be smtp/resend (the `log` driver does not send) |
| Order stays `pending` after payment | Check the webhook routes are publicly reachable (`/crypto-pay/webhook`, `/shop/notify`); verify callback URLs match admin settings; check the order `channel` and callback signature |
| `/shop` returns 404 | The `shop_enabled` setting was turned off — set it back to `1` |
| Page cache not effective | Confirm not logged in / not on admin paths (auto-excluded); inspect `X-Page-Cache` response header |
| Login gives 419 | `SESSION_DOMAIN` mismatch; on HTTPS, `SESSION_SECURE_COOKIE` not enabled so the cookie was not set |

## 8. What NOT to Do in Production

- ❌ Use `php artisan serve` as the production server (single process, no concurrency)
- ❌ Leave `APP_DEBUG` on after debugging
- ❌ Commit `.env`, database backups, or service-account JSON into git or share them publicly
- ❌ Enable `SESSION_SECURE_COOKIE` without HTTPS
- ❌ Modify the database directly instead of running `migrate --force`

---

## Appendix: Payment Channel Quick Reference

| Channel | Credentials location | Webhook / callback URL |
| --- | --- | --- |
| Xcash (crypto) | Admin → Settings → Crypto Pay → Xcash credentials | `https://your-domain/crypto-pay/webhook` (XC-Signature verification) |
| PayPal | Admin → Crypto Pay → PayPal credentials | Return `/shop/paypal/return` (server-side capture) |
| CodePay / XunHuPay | Admin → Crypto Pay | Async notify `https://your-domain/shop/notify` (signature by order channel + amount check) |
| CoinPayments | Admin → Crypto Pay | `https://your-domain/crypto-pay/webhook` |

The admin "Crypto Pay" settings page also provides sign-up links for each channel and the USD→CNY rate configuration.
