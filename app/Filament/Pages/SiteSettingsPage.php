<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Miran\Mksine\Filament\Pages\Settings\MksSettingsPage;

/**
 * 站点设置（后台 → 设置集群 → 站点设置）：
 * SEO / 注册限制（邮箱白名单 §九十九）/ Cloudflare Turnstile（§九十八）/
 * 网站统计（Google Analytics §六十七）/ 广告平台验证（§七十六）/ 隐私与 Cookie（§七十七）。
 * GA 服务账号 JSON 在「GA 服务账号」页上传（写入 storage/app/analytics/）。
 */
class SiteSettingsPage extends MksSettingsPage
{
    protected string $view = 'mksine::filament.pages.mks-settings-page';

    public static function getSlug(?\Filament\Panel $panel = null): string
    {
        return 'site-settings';
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if ($user && method_exists($user, 'hasRole') && $user->hasRole('super_admin')) {
            return true;
        }

        return parent::canAccess();
    }

    public static function getNavigationLabel(): string
    {
        return __('站点设置');
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('站点设置');
    }

    /**
     * 邀请注册模式下拉由旧的布尔开关推导（旧数据无模式值时兼容显示）。
     */
    protected function loadStoredSettingsIntoForm(): void
    {
        parent::loadStoredSettingsIntoForm();

        $mode = $this->data['invite_registration_mode'] ?? null;
        if ($mode === null || $mode === '') {
            $this->data['invite_registration_mode'] = \App\Support\SiteSettings::bool('invite_registration_enabled')
                ? 'required'
                : 'off';
        }
    }

    /**
     * 保存时把模式下拉映射回旧布尔键（InviteService 读取 invite_registration_enabled）。
     */
    public function saveData(): void
    {
        $this->validate();

        $state = $this->form->getState();

        // 邀请注册：模式（on/off）→ 布尔开关；只持久化布尔键，避免双键不一致
        if (array_key_exists('invite_registration_mode', $state)) {
            $state['invite_registration_enabled'] = $state['invite_registration_mode'] === 'required' ? '1' : '0';
        }

        foreach ($state as $key => $item) {
            if ($key === 'invite_registration_mode') {
                continue; // 模式键不在 settings 持久化，由布尔键推导
            }

            \Miran\Mksine\Models\Setting::updateOrCreate(
                ['key' => $key],
                ['value' => is_array($item) ? json_encode($item) : $item],
            );
        }

        \Filament\Notifications\Notification::make()
            ->title(__('mksine::settings.save_success'))
            ->success()
            ->send();
    }

    protected function settingsSchema(): array
    {
        return [
            Section::make('SEO 站点设置')
                ->description('站点级 SEO 默认值；文章页优先使用文章自身的 meta 字段。')
                ->schema([
                    TextInput::make('site_seo_title')
                        ->label('站点标题（Title）')
                        ->maxLength(120)
                        ->placeholder('CMSForum · 内容社区'),
                    Textarea::make('site_seo_description')
                        ->label('站点描述（Description）')
                        ->rows(2)
                        ->maxLength(300),
                    TextInput::make('site_seo_keywords')
                        ->label('关键词（Keywords，逗号分隔）')
                        ->maxLength(300),
                ])
                ->columns(1),

            Section::make('注册限制')
                ->description('§九十九：注册门槛控制。邮箱白名单只允许主流邮箱防灌水；邀请注册开启后新用户必须持有效邀请码才能注册。')
                ->schema([
                    Toggle::make('user_reg_whitelist_enabled')
                        ->label('启用邮箱白名单')
                        ->default(false)
                        ->columnSpanFull(),
                    TextInput::make('user_reg_whitelist_domains')
                        ->label('允许的邮箱域名（逗号分隔）')
                        ->placeholder('gmail.com, outlook.com, hotmail.com, yahoo.com, icloud.com, qq.com, 163.com, 126.com, foxmail.com')
                        ->columnSpanFull()
                        ->helperText('示例见输入框提示；列表外的域名注册将被拒绝'),
                    \Filament\Forms\Components\Select::make('invite_registration_mode')
                        ->label('邀请注册')
                        ->options([
                            'off' => '不使用（任意用户可注册）',
                            'required' => '强制（注册必须提供有效邀请码）',
                        ])
                        ->default(fn () => \App\Support\SiteSettings::bool('invite_registration_enabled') ? 'required' : 'off')
                        ->columnSpanFull()
                        ->helperText('强制模式下注册页邀请码必填，管理员在"内容→邀请码"生成；需同时启用 invite 模块'),
                ])
                ->columns(1),

            Section::make('Cloudflare 验证码（Turnstile）')
                ->description('§九十八：生产环境注册/登录人机校验。Cloudflare 控制台 → Turnstile → Add Site 获取 site/secret key；启用后算术验证码自动让位。')
                ->schema([
                    Toggle::make('captcha_turnstile_enabled')
                        ->label('启用 Turnstile')
                        ->default(false)
                        ->helperText('需同时填写 site key 与 secret key；关闭或未配置时回退算术验证码'),
                    TextInput::make('captcha_turnstile_site_key')
                        ->label('Site Key')
                        ->maxLength(200),
                    TextInput::make('captcha_turnstile_secret_key')
                        ->label('Secret Key')
                        ->password()
                        ->revealable()
                        ->maxLength(200),
                ])
                ->columns(1),

            Section::make('网页统计分析设置（统计代码配置中心）')
                ->description('§六十七：填写任意统计服务后自动在前台注入对应脚本（受 Cookie 授权门控）。可同时启用多家。')
                ->schema([
                    TextInput::make('ga_property_id')
                        ->label('Google Analytics（GA4 衡量 ID）')
                        ->placeholder('G-XXXXXXXXXX')
                        ->maxLength(50)
                        ->helperText('Google Analytics → 数据流 → 衡量 ID'),
                    Textarea::make('ga_service_account_json')
                        ->label('GA 服务账号 JSON（后台报表用）')
                        ->rows(5)
                        ->columnSpanFull()
                        ->helperText('粘贴服务账号 JSON 全文（GA4 属性中授予该账号查看者权限）；填写后后台自动出现「Google Analytics」统计仪表盘'),
                    TextInput::make('microsoft_clarity_id')
                        ->label('Microsoft Clarity（项目 ID）')
                        ->placeholder('xxxxxxxxxx')
                        ->maxLength(50)
                        ->helperText('Clarity → 设置 → 项目 ID'),
                    TextInput::make('baidu_tongji_code')
                        ->label('百度统计（站点代码）')
                        ->placeholder('例如：a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6')
                        ->maxLength(64)
                        ->helperText('百度统计 → 代码获取 → hm.js? 后面的字符串'),
                    TextInput::make('plausible_domain')
                        ->label('Plausible（站点域名）')
                        ->placeholder('example.com')
                        ->maxLength(200)
                        ->helperText('Plausible 后台添加的站点域名'),
                    Textarea::make('custom_analytics_html')
                        ->label('其他统计代码（原样注入 head）')
                        ->rows(4)
                        ->maxLength(3000)
                        ->columnSpanFull()
                        ->helperText('任意第三方统计脚本（51la、umami、Matomo 等），将原样输出到前台 <head>；仅超级管理员可编辑'),
                ])
                ->columns(1),

            Section::make('广告平台验证')
                ->description('§七十六：广告联盟所有权验证（meta 标签或验证脚本），原样注入页面 <head>。')
                ->schema([
                    Textarea::make('ads_verification_code')
                        ->label('验证代码（meta / script）')
                        ->rows(4)
                        ->maxLength(2000)
                        ->placeholder('<meta name="google-adsense-account" content="ca-pub-xxxx">')
                        ->columnSpanFull()
                        ->helperText('仅超级管理员可编辑；将原样输出到所有前台页面 <head>'),
                ])
                ->columns(1),

            Section::make('邮件服务（SMTP / Resend）')
                ->description('§邮件功能：注册验证码、修改密码验证码经此发送。驱动 log=写日志（本地联调）/ smtp=通用 SMTP / resend=Resend API。')
                ->schema([
                    \Filament\Forms\Components\Select::make('mail_driver')
                        ->label('发信驱动')
                        ->options([
                            'log' => 'log（写日志，本地联调）',
                            'smtp' => 'SMTP（通用）',
                            'resend' => 'Resend（API）',
                        ])
                        ->default('log')
                        ->live(),
                    TextInput::make('mail_from_address')
                        ->label('发件人邮箱')
                        ->email()
                        ->maxLength(200),
                    TextInput::make('mail_from_name')
                        ->label('发件人名称')
                        ->maxLength(100),
                    TextInput::make('smtp_host')
                        ->label('SMTP 主机')
                        ->placeholder('smtp.gmail.com')
                        ->visible(fn ($get) => $get('mail_driver') === 'smtp'),
                    TextInput::make('smtp_port')
                        ->label('SMTP 端口')
                        ->numeric()
                        ->default(587)
                        ->visible(fn ($get) => $get('mail_driver') === 'smtp'),
                    TextInput::make('smtp_username')
                        ->label('SMTP 用户名')
                        ->visible(fn ($get) => $get('mail_driver') === 'smtp'),
                    TextInput::make('smtp_password')
                        ->label('SMTP 密码 / 授权码')
                        ->password()
                        ->revealable()
                        ->visible(fn ($get) => $get('mail_driver') === 'smtp'),
                    \Filament\Forms\Components\Select::make('smtp_encryption')
                        ->label('加密方式')
                        ->options(['tls' => 'TLS（587 常用）', 'ssl' => 'SSL（465 常用）', 'null' => '无'])
                        ->default('tls')
                        ->visible(fn ($get) => $get('mail_driver') === 'smtp'),
                    TextInput::make('resend_api_key')
                        ->label('Resend API Key（re_ 开头）')
                        ->password()
                        ->revealable()
                        ->visible(fn ($get) => $get('mail_driver') === 'resend'),
                ])
                ->columns(1),

            Section::make('邮箱验证码')
                ->description('开启后对应场景需要先发送并填写邮箱验证码；验证码 10 分钟有效、60 秒限发。')
                ->schema([
                    Toggle::make('user_reg_email_code_enabled')
                        ->label('注册使用邮箱验证码')
                        ->default(false),
                    Toggle::make('user_password_email_code_enabled')
                        ->label('修改密码使用邮箱验证码')
                        ->default(false),
                ])
                ->columns(1),

            Section::make('邀请码')
                ->description('邀请码购买方式与定价；管理员在"内容→邀请码"手动生成。注册是否必须邀请码请在「注册限制」卡片配置。')
                ->schema([
                    Toggle::make('invite_center_enabled')
                        ->label('启用邀请码中心页面（/invite）')
                        ->default(true)
                        ->helperText('关闭后前台购买页面返回 404，仅保留后台生成与注册核销'),
                    Toggle::make('invite_allow_gold')
                        ->label('开放金币购买')
                        ->default(false),
                    TextInput::make('invite_gold_price')
                        ->label('金币单价（枚）')
                        ->numeric()
                        ->default(100)
                        ->minValue(0),
                    Toggle::make('invite_allow_crypto')
                        ->label('开放加密货币购买')
                        ->default(false),
                    TextInput::make('invite_crypto_price')
                        ->label('加密单价（USD）')
                        ->numeric()
                        ->step(0.01)
                        ->default(1.99)
                        ->minValue(0),
                ])
                ->columns(1),

            Section::make('性能优化（页面缓存）')
                ->description('WP Super Cache 式整页缓存：仅缓存游客的 GET 200 页面，后台/登录用户/动态路径自动排除。图表见「性能优化中心」。')
                ->schema([
                    Toggle::make('performance_page_cache_enabled')
                        ->label('启用页面缓存')
                        ->default(false),
                    TextInput::make('performance_page_cache_ttl')
                        ->label('缓存有效期（秒）')
                        ->numeric()
                        ->default(3600)
                        ->minValue(60)
                        ->helperText('到期自动重新渲染；后台「性能优化中心」可随时手动清除'),
                    \Filament\Forms\Components\Select::make('performance_cache_store')
                        ->label('缓存存储')
                        ->options([
                            'auto' => 'auto（检测到 Redis 自动使用）',
                            'file' => 'file（文件）',
                            'redis' => 'redis（需 Redis 可达）',
                        ])
                        ->default('auto'),
                    \Filament\Forms\Components\Select::make('queue_connection')
                        ->label('队列驱动')
                        ->options(['sync' => 'sync（同步）', 'database' => 'database', 'redis' => 'redis（推荐配合 Horizon）'])
                        ->default('database'),
                ])
                ->columns(1),

            Section::make('商城设置')
                ->description('商城模块的全局开关；关闭后前台商品页返回 404。')
                ->schema([
                    Toggle::make('shop_enabled')
                        ->label('启用商城')
                        ->default(true),
                ])
                ->columns(1),

            Section::make('隐私与 Cookie')
                ->description('§七十七：Google Ads 合规——EU 用户同意横幅与隐私政策生效日期；Cookie 授权由前端 localStorage 记录，分析/广告脚本仅在「接受全部」后加载。')
                ->schema([
                    Toggle::make('cookie_banner_enabled')
                        ->label('启用 Cookie 授权横幅')
                        ->default(false)
                        ->helperText('开启后未选择的用户会看到底部同意横幅'),
                    DatePicker::make('privacy_policy_updated_at')
                        ->label('隐私政策生效日期')
                        ->native(false)
                        ->displayFormat('Y-m-d')
                        ->default('2026-01-01')
                        ->helperText('显示在隐私政策页顶部；修改隐私政策后请更新此日期'),
                ])
                ->columns(1),
        ];
    }
}
