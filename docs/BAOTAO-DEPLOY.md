# simpleCMS 宝塔面板部署教程

> 面向使用 [宝塔面板](https://www.bt.cn)（Linux 图形化服务器管理面板）的站长。
> 本文按宝塔面板的操作路径编写，跟着点即可完成部署。
> 部署前请先阅读 [《生产部署手册》](DEPLOYMENT.md) 了解环境要求与安全配置。

---

## 一、环境准备（宝塔软件商店）

在宝塔面板 → **软件商店** 安装以下软件：

| 软件 | 版本 | 说明 |
| --- | --- | --- |
| Nginx | 1.22+ | Web 服务器 |
| MySQL | 8.0 | 数据库 |
| PHP | 8.3 或 8.5 | 运行环境（**非 7.x**，Laravel 13 要求 PHP 8.3+） |
| Redis | 6.2+ | 缓存/队列（可选但推荐） |
| phpMyAdmin | 最新 | 数据库管理（可选） |

安装 PHP 后，在宝塔 **软件商店 → PHP → 设置 → 安装扩展** 勾选并安装：

```
fileinfo   opcache   redis   intl   exif   gd   bcmath   zip   mysqli   pdo_mysql
```

> `redis` 扩展需先装 Redis 软件；`intl` 对部分多语言/日期功能必要。
> 若提示缺少扩展，按宝塔提示一键安装即可。

## 二、创建站点

1. 宝塔面板 → **网站** → **添加站点**
2. 填写：
   - **域名**：你的域名（如 `www.example.com`）
   - **PHP 版本**：选择刚装的 8.3 / 8.5
   - **数据库**：MySQL，数据库名/用户/密码自动生成（记下来）
   - **其他**：默认即可
3. 创建后，站点根目录通常是 `/www/wwwroot/你的域名/`

## 三、上传代码

方式 A（推荐，Git 拉取）：

```bash
cd /www/wwwroot/你的域名
git clone <你的仓库地址> .
```

方式 B（宝塔上传）：本地打包项目（**排除 vendor/node_modules/.env**）→ 宝塔「文件」→ 上传到站点根目录 → 解压。

> 生产环境建议用 Git 方式，方便后续 `git pull` 升级。

## 四、安装依赖与初始化

打开宝塔 **网站 → 设置 → 终端**（或 SSH 登录），在站点根目录执行：

```bash
# 1. 安装 PHP 依赖（生产模式）
composer install --no-dev --optimize-autoloader

# 2. 创建环境配置
cp .env.example .env

# 3. 生成应用密钥
php artisan key:generate

# 4. 编辑 .env（用宝塔「文件」编辑器打开）
```

`.env` 必改项：

```dotenv
APP_NAME=simpleCMS
APP_ENV=production
APP_DEBUG=false
APP_URL=https://你的域名          # 注意 https

# 数据库（用宝塔创建站点时生成的）
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=你的数据库名
DB_USERNAME=你的数据库用户
DB_PASSWORD=你的数据库密码

# 缓存/队列/会话（装了 Redis 就这样配）
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null

# 邮件（收注册验证码/邮件交付商品必须配）
MAIL_MAILER=smtp
MAIL_HOST=smtp.qq.com          # 示例：QQ 邮箱 SMTP
MAIL_PORT=465
MAIL_USERNAME=你的邮箱
MAIL_PASSWORD=你的授权码        # 邮箱的 SMTP 授权码，非登录密码
MAIL_FROM_ADDRESS=你的邮箱
MAIL_FROM_NAME="${APP_NAME}"
```

保存 `.env` 后继续：

```bash
# 5. 数据库迁移 + 创建管理员
php artisan migrate --force
php artisan db:seed --class=AdminAccountSeeder

# 6. 前端构建
npm install --ignore-scripts
npm run build

# 7. 目录权限（宝塔终端执行）
chown -R www:www storage bootstrap/cache
```

> 管理员账号密码来自 `.env` 的 `ADMIN_DEFAULT_EMAIL` / `ADMIN_DEFAULT_PASSWORD`，**首次登录后请删除这两行**。

## 五、Nginx 站点配置（伪静态 + 防敏感路径）

宝塔 **网站 → 设置 → 配置文件**，在 `server {}` 块内找到 `location /` 并替换：

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

# 防敏感文件直接访问
location ~ /\.(?!well-known) {
    deny all;
}
location ~ ^/(\.env|storage/app/db-migration) {
    deny all;
}

# 静态资源长缓存（可选优化）
location ~* \.(js|css|png|jpg|jpeg|gif|svg|woff2?)$ {
    expires 30d;
    add_header Cache-Control "public, immutable";
    try_files $uri =404;
}
```

保存后点击 **重载配置**。

> 宝塔默认已开启 SSL 的站点，`APP_URL` 必须写成 `https://` 开头；
> 同时把 `.env` 里的 `SESSION_SECURE_COOKIE=true` 加上（HTTPS 站点必配）。

## 六、常驻进程（宝塔「进程守护管理器」）

定时发布、队列、Horizon 依赖常驻进程。宝塔面板 → **软件商店** 安装 **进程守护管理器**（Supervisor），然后：

**添加守护进程 1 —— 定时任务：**

| 配置项 | 值 |
| --- | --- |
| 名称 | `simplecms-scheduler` |
| 启动用户 | `www` |
| 运行目录 | `/www/wwwroot/你的域名` |
| 启动命令 | `php artisan schedule:work` |

**添加守护进程 2 —— 队列：**

| 配置项 | 值 |
| --- | --- |
| 名称 | `simplecms-horizon` |
| 启动用户 | `www` |
| 运行目录 | `/www/wwwroot/你的域名` |
| 启动命令 | `php artisan horizon`（装了 Redis）或 `php artisan queue:work --tries=3`（未装 Redis） |

> 两个守护进程都要设置 **开机自启**。
> 若未运行 `schedule:work`，**定时发布文章不生效**；未运行队列则邮件验证码、导入导出等任务堆积。

## 七、上线后验证清单

- [ ] 访问 `https://你的域名` 显示前台首页
- [ ] 访问 `https://你的域名/admin` 能登录后台
- [ ] 打开 `https://你的域名/up` 返回 `OK`（健康检查）
- [ ] 注册页能收到验证码邮件（验证 SMTP 配置）
- [ ] 宝塔「进程守护管理器」两个守护进程状态为「运行中」
- [ ] 后台「设置 → 站点设置」把 `APP_DEBUG` 对应项核对为生产值

## 八、日常维护

### 升级代码

```bash
cd /www/wwwroot/你的域名
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache route:cache view:cache
php artisan horizon:terminate        # 重启队列进程
# 前端有改动时：
npm install --ignore-scripts && npm run build
```

### 备份

宝塔面板自带 **计划任务**，可添加每日数据库备份 + 网站目录备份：

- 计划任务 → 备份数据库 → 选择数据库 → 每天 03:00
- 计划任务 → 备份网站 → 选择站点 → 每周日 03:30

### 常见问题

| 问题 | 处理 |
| --- | --- |
| 首页 500 / 白屏 | 看 `/www/wwwroot/你的域名/storage/logs/laravel.log`；确认 `.env` 的数据库信息正确 |
| `APP_DEBUG` 开了还是白屏 | `php artisan config:clear` 后重试；确认 `bootstrap/cache` 可写 |
| 登录报 419 | `.env` 的 `SESSION_DOMAIN` 留空；HTTPS 站点确认 `SESSION_SECURE_COOKIE=true` |
| 图片/上传 404 | 执行 `php artisan storage:link` |
| 定时发布不生效 | 检查「进程守护管理器」scheduler 是否运行；`php artisan schedule:list` 应列出任务 |
| 邮件发不出 | 确认 SMTP 授权码正确、端口 465/587 放行；后台「设置 → 邮件服务」选 smtp |
| 宝塔安全组拦截 | 确认 80/443 端口在云服务商安全组已放行 |

---

## 附：常见问题说明

**Q：PHP 项目是不是上传就能用？**
不是。Laravel 项目上传后还需要：安装 Composer 依赖（`composer install`）、生成密钥（`key:generate`）、跑数据库迁移（`migrate`）、构建前端（`npm run build`）、配置 `.env`。按照本文第四步操作即可，宝塔终端一条条执行很快。

**Q：邮件交付商品（账号/激活码/邀请码）怎么配置？**
1. 后台「设置 → 邮件服务」选 `smtp`，填 SMTP 主机/端口/账号/授权码（或 `.env` 配 `MAIL_*`）；
2. 商品编辑页「交付设置 → 交付类型」选 **邮件交付**，交付内容填账号、激活码、邀请码等多行文本；
3. 买家支付成功后，系统自动把交付内容发到买家邮箱（登录用户用注册邮箱，游客用下单时填的收件邮箱）。

**Q：当前项目的测试数据怎么一键清除？**
后台「备份与恢复」页提供**一键清除测试数据**按钮（需超级管理员），点击后清空文章/评论/商品/订单/邀请码/广告等业务表，保留账号、设置、分类与媒体。开发环境想重来也可以用：

```bash
php artisan db:seed --class=DatabaseSeeder --fresh
```

详见《项目文档》运维章节。
