# simpleCMS

> **中文** · [English](README.en.md)

以 **Laravel 13 + Filament 5 + Inertia/Vue 3** 构建的插件化内容社区平台（CMS + BBS）。

底层 CMS 能力由 [`miran/mksine`](https://github.com/miransalehi/mksine) v1.4.0 内核提供（文章、分类、媒体库、菜单、设置、插件/主题/钩子/短码系统），业务功能以**可插拔模块**的形式叠加在内核之上。

> 📦 生产部署请直接阅读 **[《生产部署手册》](docs/DEPLOYMENT.md)**（中文） / **[Deployment Guide](docs/DEPLOYMENT.en.md)**（English）。
> 📖 完整项目文档见 **[《项目文档》](docs/项目文档.md)**。

---

## 📸 界面预览

| 商城（前台） | 商品详情 |
| --- | --- |
| ![商城](docs/screenshots/shop-index.png) | ![商品详情](docs/screenshots/shop-detail.png) |

| 付费阅读（前台） | 后台仪表盘 |
| --- | --- |
| ![付费阅读](docs/screenshots/paid-article.png) | ![后台仪表盘](docs/screenshots/admin-dashboard.png) |

| 付费文章管理 | 文章列表 | 文章编辑 |
| --- | --- | --- |
| ![付费文章](docs/screenshots/admin-paid-posts.png) | ![文章列表](docs/screenshots/admin-posts.png) | ![文章编辑](docs/screenshots/admin-post-edit.png) |

---

## ✨ 功能亮点

- **插件化架构**：9 个业务模块可独立安装 / 启用 / 停用 / 卸载（`modules/`）
- **统一支付网关**：码支付 / 虎皮椒 / PayPal / Xcash（加密）/ Mock 多通道并存，用户前台自选
- **付费阅读**：正文插入 `[coinpay_buy price="4.99"]` 即分段付费；后台「内容 → 付费文章」统一定价
- **内容社区**：文章 / 分类 / 评论 / 单页 / 轮播图 / 搜索 / RSS / Sitemap / 定时发布
- **创作中心**：前台写作、富文本编辑、个人设置、作者中心
- **经济系统**：多币种钱包、签到 / 发布奖励、邀请码（金币 / 加密购买 / 注册核销）
- **商城**：商品 / 库存（下单预占防超卖）/ 订单 / 多形态交付（下载 / 内容 / 邀请码）
- **多语言**：系统 + 模块均带 `en` / `zh_CN` 语言包，后台可切换
- **性能**：整页缓存（WP Super Cache 式）、Redis / OPcache / Octane / Horizon 可选接入
- **安全**：邮箱白名单、邀请注册（可强制）、Turnstile / 算术验证码、登录 / 注册 / 评论 / 签到全限流、富文本输出白名单清洗、支付回调幂等

## 🧰 技术栈

| 层 | 组件 |
| --- | --- |
| 后端 | PHP ^8.3 · Laravel 13.27 · Filament 5.7 · Livewire 4.4 · miran/mksine 1.4 · Filament Shield 4.3 · spatie/laravel-permission 8.3 |
| 前台 | Vue 3.5 · Inertia 3.7 · Vite 8 · Tailwind CSS 4 · TypeScript |
| 基础设施 | Horizon 5.48 · Octane 2.19 · Redis（可选）· CoinPayments SDK |

> 🚀 **由 Vast.ai 提供算力支持**（非官方支持，作者个人使用）：本项目开发、构建与线上演示跑在 [Vast.ai](https://cloud.vast.ai/?ref_id=91181) 的 GPU 云服务器上。Vast.ai 是全球最大的去中心化 GPU 市场，按小时租用 RTX 4090 / A100 等显卡实例，适合模型推理、CI 构建、云托管等场景。如果你也需要弹性算力，可以通过 [这个推荐链接](https://cloud.vast.ai/?ref_id=91181) 注册。

## 🌱 基础环境

| 组件 | 版本 | 用途 |
| --- | --- | --- |
| PHP | ^8.3（推荐 8.5） | 运行时；需 pdo_mysql / mbstring / curl / openssl / intl 等扩展 |
| MySQL | 8.0+ | 主数据库（utf8mb4） |
| Redis | 6.0+（可选） | 缓存 / 队列 / 会话，不装则用 database 驱动兜底 |
| Composer | 2.x | 依赖管理 |
| Node.js | 20+ | 前端构建 |

> Windows 本地开发推荐 [Laragon](https://laragon.org)（自带 PHP + MySQL，一条命令启动）。

## 🚀 快速开始（开发环境）

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --force
npm install --ignore-scripts && npm run build

# 创建管理员（账号密码来自 .env）
php artisan db:seed --class=AdminAccountSeeder
```

`.env` 需配置：

```dotenv
ADMIN_DEFAULT_EMAIL=admin@cmsforum.test
ADMIN_DEFAULT_PASSWORD=<your-password>
```

一行启动开发环境（server + queue + logs + vite）：

```bash
composer dev
```

后台入口：`/admin`

## 🧩 模块

业务能力全部模块化，位于 `modules/`，可独立安装 / 启用 / 停用 / 卸载。

| 模块 | 能力 | 依赖 |
| --- | --- | --- |
| `user` | 用户资料、注册流程（邮箱验证码 / 域名白名单 / 邀请注册） | — |
| `cms` | 固定链接、分类页、搜索、RSS、Sitemap、单页、轮播图、评论、创作中心 | `user` |
| `economy` | 多币种钱包（金/银/铜）、幂等流水账 | `user` |
| `quest` | 每日签到、发布奖励 | `user`、`economy` |
| `crypto-pay` | 付费阅读 + 统一支付网关（码支付/虎皮椒/PayPal/Xcash/Mock） | — |
| `shop` | 商品、库存、订单、多种发货方式 | — |
| `invite` | 邀请码生成 / 金币或加密购买 / 注册核销 | — |
| `ads` | 11 个投放位置的广告管理 | — |
| `performance` | 整页缓存、命中统计、Redis/OPcache/Octane/Horizon 探测 | — |

激活顺序建议 `user` → `economy` → `quest` → `cms` → 其余：

```bash
php artisan mks-plugin:discover
php artisan mks-plugin:install user && php artisan mks-plugin:activate user
php artisan mks-plugin:migrate user
```

## ✅ 测试

```bash
php artisan test
```

测试数据库为 MySQL `cmsforum_test`（见 `phpunit.xml`），模块测试基于 `Modules\Tests\ModuleTestCase`（SAVEPOINT 事务回滚，不污染开发库）。

## 🔧 常用命令

```bash
php artisan mks-plugin:list --status=active   # 插件状态
php artisan mks:discover                      # 钩子发现
php artisan schedule:work                     # 定时发布依赖它（每分钟）
php artisan queue:work --tries=3
php artisan test
```

## 📂 目录速览

```
app/        应用层：侧边栏引擎、站点设置、备份/打包、后台页面、策略
modules/    9 个业务插件（plugin.php 清单 + 各自迁移/路由/模型/后台资源/前端页面）
config/     mksine.php 为内核总配置（690 行，含逐段中文注释）
docs/       项目文档、部署手册、设计稿、支付 SDK、安全审计报告、界面截图
```

## 📚 文档

- **[《生产部署手册》](docs/DEPLOYMENT.md)**（中文） — 环境要求、Nginx/HTTPS、队列调度、Redis/Octane、安全加固、升级备份、常见故障
- **[Deployment Guide](docs/DEPLOYMENT.en.md)**（English）
- **[《项目文档》](docs/项目文档.md)** — 架构、模块详解、数据模型、路由清单、权限、支付、性能与缓存、运维、二次开发
- [《安全与代码审计报告》](docs/安全与代码审计报告.md) — 白盒审计结论与修复状态
- [架构设计稿](docs/开发文档.md) · [模块化开发文档](docs/工程化模块化开发文档.md) · [CMS/论坛设计](docs/cms和forum设计.md) · [Xcash 对接协议](docs/xcash.md)

## License

本项目基于 Laravel，遵循 [MIT 许可](https://opensource.org/licenses/MIT)。
