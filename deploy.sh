#!/usr/bin/env bash
#
# simpleCMS 一键部署脚本（Linux / 宝塔面板通用）
#
# 用法：
#   1. 把 simpleCMS 源码放到服务器 Web 目录（如 /www/wwwroot/your-domain）
#   2. 在该目录执行：  bash deploy.sh
#   3. 按提示填写数据库 / 域名信息，脚本自动完成：
#      composer install → .env 生成 → key:generate → 数据库迁移（内核+插件）
#      → 创建管理员 → 前端构建 → 目录权限 → 启动提示
#
# 要求：PHP >= 8.3、Composer 2.x、Node.js >= 20 已安装并加入 PATH。
# 宝塔面板请先在「软件商店」安装对应版本的 PHP / Node.js。
#
set -euo pipefail

# ---------- 颜色 ----------
GREEN='\033[0;32m'; YELLOW='\033[1;33m'; RED='\033[0;31m'; NC='\033[0m'
ok()   { echo -e "${GREEN}✔${NC} $1"; }
warn() { echo -e "${YELLOW}⚠${NC} $1"; }
fail() { echo -e "${RED}✘${NC} $1"; }

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT"

echo ""
echo "=============================================="
echo "   simpleCMS 一键部署"
echo "=============================================="
echo ""

# ---------- 0. 环境检查 ----------
command -v php >/dev/null 2>&1 || { fail "未找到 php，请先安装 PHP 8.3+（宝塔：软件商店 → PHP）"; exit 1; }
command -v composer >/dev/null 2>&1 || { fail "未找到 composer，请安装 Composer 2.x"; exit 1; }
if command -v node >/dev/null 2>&1; then NODE_OK=1; else NODE_OK=0; warn "未找到 node，将跳过前端构建"; fi

PHP_VER=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
ok "PHP 版本：$PHP_VER"
if [[ "${PHP_VER%%.*}" -lt 8 ]]; then
    fail "PHP 版本过低（需要 8.3+），当前 $PHP_VER"
    exit 1
fi

# ---------- 1. 交互式配置 ----------
echo ""
echo "--- 请配置以下信息（直接回车使用默认值）---"
read -rp "站点域名/URL（如 https://example.com，本地可用 http://localhost）: " APP_URL
APP_URL="${APP_URL:-http://localhost}"
read -rp "数据库主机 [127.0.0.1]: " DB_HOST
DB_HOST="${DB_HOST:-127.0.0.1}"
read -rp "数据库端口 [3306]: " DB_PORT
DB_PORT="${DB_PORT:-3306}"
read -rp "数据库名 [simplecms]: " DB_DATABASE
DB_DATABASE="${DB_DATABASE:-simplecms}"
read -rp "数据库用户名 [root]: " DB_USERNAME
DB_USERNAME="${DB_USERNAME:-root}"
read -rsp "数据库密码（输入不显示）: " DB_PASSWORD
echo ""
read -rp "管理员邮箱 [admin@example.com]: " ADMIN_EMAIL
ADMIN_EMAIL="${ADMIN_EMAIL:-admin@example.com}"
read -rsp "管理员密码（至少 8 位，输入不显示）: " ADMIN_PASSWORD
echo ""
if [[ ${#ADMIN_PASSWORD} -lt 8 ]]; then
    fail "管理员密码至少 8 位，请重新执行"
    exit 1
fi

# ---------- 2. composer 依赖 ----------
echo ""
echo "--- [1/7] 安装 PHP 依赖（composer install）---"
# --ignore-platform-req=ext-pcntl,ext-posix：这两个扩展仅 Horizon/Octane 可选组件需要，
# 缺失时（如部分共享主机）不应阻塞整个部署；Linux 服务器通常自带。
composer install --no-dev --optimize-autoloader --no-interaction \
    --ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix \
    || composer install --no-dev --optimize-autoloader --no-interaction

# ---------- 3. 生成 .env ----------
echo ""
echo "--- [2/7] 生成 .env 并写入数据库/邮件配置 ---"
if [[ ! -f .env ]]; then
    cp .env.example .env
    ok ".env 已从 .env.example 创建"
else
    warn ".env 已存在，保留现有配置（数据库信息不会被覆盖）"
    echo "    如需覆盖数据库配置，请手动编辑 .env"
fi

# 仅在新建 .env 时写入配置（避免覆盖已有配置）
if [[ -f .env ]]; then
    # 用 sed 安全替换（分隔符用 | 避免斜杠冲突）
    sed -i "s|^APP_ENV=.*|APP_ENV=production|" .env
    sed -i "s|^APP_DEBUG=.*|APP_DEBUG=false|" .env
    sed -i "s|^APP_URL=.*|APP_URL=${APP_URL}|" .env
    sed -i "s|^DB_CONNECTION=.*|DB_CONNECTION=mysql|" .env
    sed -i "s|^# DB_HOST=.*|DB_HOST=${DB_HOST}|" .env
    sed -i "s|^DB_HOST=.*|DB_HOST=${DB_HOST}|" .env
    sed -i "s|^# DB_PORT=.*|DB_PORT=${DB_PORT}|" .env
    sed -i "s|^DB_PORT=.*|DB_PORT=${DB_PORT}|" .env
    sed -i "s|^# DB_DATABASE=.*|DB_DATABASE=${DB_DATABASE}|" .env
    sed -i "s|^DB_DATABASE=.*|DB_DATABASE=${DB_DATABASE}|" .env
    sed -i "s|^# DB_USERNAME=.*|DB_USERNAME=${DB_USERNAME}|" .env
    sed -i "s|^DB_USERNAME=.*|DB_USERNAME=${DB_USERNAME}|" .env
    sed -i "s|^# DB_PASSWORD=.*|DB_PASSWORD=${DB_PASSWORD}|" .env
    sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASSWORD}|" .env
    sed -i "s|^ADMIN_DEFAULT_EMAIL=.*|ADMIN_DEFAULT_EMAIL=${ADMIN_EMAIL}|" .env
    sed -i "s|^ADMIN_DEFAULT_PASSWORD=.*|ADMIN_DEFAULT_PASSWORD=${ADMIN_PASSWORD}|" .env
    ok ".env 配置已写入（数据库 ${DB_DATABASE} @ ${DB_HOST}:${DB_PORT}）"
fi

# ---------- 4. 应用密钥 ----------
echo ""
echo "--- [3/7] 生成应用密钥 ---"
php artisan key:generate --force

# ---------- 5. 数据库迁移 ----------
echo ""
echo "--- [4/7] 数据库迁移（内核 + 全部插件）---"
php artisan migrate --force

# 全新部署：按依赖顺序安装并迁移全部插件（安装 = 注册 + 激活）
# 插件迁移直接按路径跑（mks-plugin:migrate 在部分环境存在路径兼容问题）
PLUGINS="user economy quest cms crypto-pay shop invite ads performance"
for plugin in $PLUGINS; do
    php artisan mks-plugin:install "$plugin" 2>/dev/null \
        || warn "插件 $plugin 安装跳过（可能已安装或依赖缺失）"
    if [[ -d "modules/$plugin/database/migrations" ]]; then
        php artisan migrate --path="modules/$plugin/database/migrations" --force \
            || warn "插件 $plugin 迁移失败，请检查日志"
    fi
done
ok "全部插件已安装并迁移"

# ---------- 6. 创建管理员 ----------
echo ""
echo "--- [5/7] 创建管理员账号 ---"
php artisan db:seed --class=AdminAccountSeeder --force || warn "管理员已存在或创建失败，可稍后在后台重置"

# ---------- 7. 前端构建 + 链接存储 ----------
if [[ "${NODE_OK:-0}" == "1" ]]; then
    echo ""
    echo "--- [6/7] 安装前端依赖并构建 ---"
    npm install --ignore-scripts --no-audit --no-fund
    npm run build
else
    warn "未找到 node，跳过前端构建（上传前请先本地 npm run build）"
fi
echo ""
echo "--- [7/7] 存储链接 + 目录权限 ---"
php artisan storage:link --force || true
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

# ---------- 完成 ----------
echo ""
echo "=============================================="
echo -e "  ${GREEN}部署完成！${NC}"
echo "=============================================="
echo ""
echo " 前台地址： ${APP_URL}"
echo " 后台地址： ${APP_URL}/admin"
echo " 管理员账号： ${ADMIN_EMAIL}"
echo ""
echo " 下一步（重要）："
echo "  1. 配置 Web 服务器伪静态（Nginx: try_files → /index.php）"
echo "  2. 生产环境必须开启 HTTPS，并把 .env 里 SESSION_SECURE_COOKIE 设为 true"
echo "  3. 常驻进程（宝塔「进程守护管理器」）:"
echo "     - php artisan schedule:work   （定时发布必需）"
echo "     - php artisan horizon         或 php artisan queue:work --tries=3"
echo "  4. 完成后删除 .env 里的 ADMIN_DEFAULT_* 两行（防止他人用默认密码登录）"
echo ""
echo " 详细说明见 docs/DEPLOYMENT.md 与 docs/BAOTAO-DEPLOY.md"
echo ""
