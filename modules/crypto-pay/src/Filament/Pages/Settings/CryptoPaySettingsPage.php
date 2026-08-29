<?php

declare(strict_types=1);

namespace Modules\CryptoPay\Filament\Pages\Settings;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Miran\Mksine\Filament\Pages\Settings\MksSettingsPage;
use Modules\CryptoPay\Services\CodePayClient;
use Modules\CryptoPay\Services\CoinPayService;
use Modules\CryptoPay\Services\PayPalClient;
use Modules\CryptoPay\Services\XcashClient;
use Modules\CryptoPay\Services\XunHuPayClient;

/**
 * CryptoPay 设置页：后台 → 设置集群 → 加密支付。
 * 支付通道：CoinPayments / Xcash（管理员按需启用）+ Mock 本地联调。
 */
class CryptoPaySettingsPage extends MksSettingsPage
{
    protected string $view = 'mksine::filament.pages.mks-settings-page';

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
        return __('加密支付');
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('加密支付');
    }

    protected function settingsSchema(): array
    {
        return [
            Section::make('支付通道')
                ->description('可同时启用多个通道，用户结账时自选支付方式；Mock 模式优先于所有通道（本地联调用）')
                ->schema([
                    Toggle::make(CoinPayService::S_MOCK)
                        ->label('Mock 模式（本地联调）')
                        ->helperText('开启后不走真实 API，收银台指向内置模拟支付页'),
                    \Filament\Forms\Components\CheckboxList::make('payment_channels')
                        ->label('启用的支付通道')
                        ->options([
                            'codepay' => '码支付（支付宝/微信/QQ）',
                            'xunhupay' => '虎皮椒（支付宝/微信）',
                            'paypal' => 'PayPal（法币）',
                            'xcash' => 'Xcash（USDT/多链加密）',
                        ])
                        ->columns(2)
                        ->helperText('勾选的通道同时可用，用户结账时自选支付方式'),
                    TextInput::make('payment_fx_rate')
                        ->label('USD→CNY 汇率（码支付/虎皮椒换算用）')
                        ->numeric()
                        ->step(0.01)
                        ->default(7.2)
                        ->minValue(0.01),
                ])
                ->columns(1)
                ->headerActions([
                    \Filament\Actions\Action::make('registerNowpayments')
                        ->label('注册 NOWPayments')
                        ->icon('heroicon-o-arrow-top-right-on-square')
                        ->color('gray')
                        ->url('https://account.nowpayments.io/create-account?link_id=3940543227&utm_source=affiliate_lk&utm_medium=referral', shouldOpenInNewTab: true),
                ]),

            Section::make('CoinPayments 凭证')
                ->description('Webhook 地址填 '.url('/crypto-pay/webhook'))
                ->schema([
                    TextInput::make(CoinPayService::S_CLIENT_ID)
                        ->label('Client ID')
                        ->maxLength(191),
                    TextInput::make(CoinPayService::S_API_PRIVATE)
                        ->label('API Secret')
                        ->password()
                        ->revealable()
                        ->maxLength(191),
                    TextInput::make(CoinPayService::S_HOOK_SIGNING)
                        ->label('Webhook 签名密钥')
                        ->password()
                        ->revealable()
                        ->maxLength(191),
                ])
                ->columns(1),

            Section::make('Xcash 凭证')
                ->description('Webhook 地址填 '.url('/crypto-pay/webhook').'（XC-Signature 头自动识别通道）；自部署网关填你的域名')
                ->schema([
                    TextInput::make(XcashClient::S_GATEWAY)
                        ->label('网关地址')
                        ->url()
                        ->maxLength(191)
                        ->placeholder(XcashClient::OFFICIAL_GATEWAY)
                        ->helperText('默认官方网关 '.XcashClient::OFFICIAL_GATEWAY.'；自部署填 SITE_DOMAIN'),
                    TextInput::make(XcashClient::S_APP_ID)
                        ->label('AppID')
                        ->maxLength(64)
                        ->placeholder('XC-XXXXXXXX'),
                    TextInput::make(XcashClient::S_HMAC)
                        ->label('HMAC 密钥')
                        ->password()
                        ->revealable()
                        ->maxLength(191),
                ])
                ->columns(1)
                ->headerActions([
                    \Filament\Actions\Action::make('registerXcash')
                        ->label('注册 Xcash')
                        ->icon('heroicon-o-arrow-top-right-on-square')
                        ->color('gray')
                        ->url('https://dash.xca.sh/register?ref=2GWV5MKT', shouldOpenInNewTab: true),
                ]),

            Section::make('PayPal 凭证（法币通道）')
                ->description('收款通道选择 PayPal 时生效；沙箱用于联调，生产切换 live。')
                ->schema([
                    TextInput::make(PayPalClient::S_CLIENT_ID)
                        ->label('Client ID')
                        ->maxLength(191)
                        ->columnSpanFull(),
                    TextInput::make(PayPalClient::S_SECRET)
                        ->label('Secret')
                        ->password()
                        ->revealable()
                        ->maxLength(191)
                        ->columnSpanFull(),
                ])
                ->columns(1),

            Section::make('码支付凭证（epay 协议）')
                ->description('收款通道选择码支付时生效；金额按上方汇率由 USD 换算为 CNY。异步通知地址填 '.url('/shop/notify'))
                ->schema([
                    TextInput::make(CodePayClient::S_GATEWAY)
                        ->label('网关地址')
                        ->url()
                        ->maxLength(191)
                        ->placeholder('https://pay.example.com')
                        ->columnSpanFull(),
                    TextInput::make(CodePayClient::S_PID)
                        ->label('商户 ID（pid）')
                        ->maxLength(64),
                    TextInput::make(CodePayClient::S_KEY)
                        ->label('商户密钥（key）')
                        ->password()
                        ->revealable()
                        ->maxLength(191),
                    Select::make(CodePayClient::S_TYPE)
                        ->label('支付方式')
                        ->options(['alipay' => '支付宝', 'wxpay' => '微信支付', 'qqpay' => 'QQ钱包'])
                        ->default('alipay'),
                ])
                ->columns(1),

            Section::make('虎皮椒凭证（XunHuPay）')
                ->description('收款通道选择虎皮椒时生效；金额按上方汇率由 USD 换算为 CNY。异步通知地址填 '.url('/shop/notify'))
                ->schema([
                    TextInput::make(XunHuPayClient::S_APP_ID)
                        ->label('APPID')
                        ->maxLength(64),
                    TextInput::make(XunHuPayClient::S_APP_SECRET)
                        ->label('APPSECRET')
                        ->password()
                        ->revealable()
                        ->maxLength(191),
                    Select::make(XunHuPayClient::S_PLUGIN)
                        ->label('支付插件')
                        ->options(['alipay' => '支付宝', 'wechat' => '微信支付'])
                        ->default('alipay'),
                ])
                ->columns(1),
        ];
    }
}
