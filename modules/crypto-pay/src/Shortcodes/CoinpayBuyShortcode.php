<?php

namespace Modules\CryptoPay\Shortcodes;

use Illuminate\Support\Facades\Auth;
use Miran\Mksine\Core\Shortcodes\ShortcodeContext;
use Miran\Mksine\Core\Shortcodes\ShortcodeHandlerInterface;
use Miran\Mksine\Models\Post;

/**
 * [coinpay_buy] — 输出当前文章的加密支付购买按钮。
 *
 * 两种定价方式（优先级从高到低）：
 *   1. 短码属性： [coinpay_buy price="4.99"]（正文内直接标价，即插即用）
 *   2. 后台设置： crypto_post_prices 表（文章编辑页"付费阅读"区块）
 *
 * 免费文章（无任何价格）时：作者/管理员可见配置提示，普通访客不渲染任何内容。
 * 已解锁 / 未登录 分别渲染不同状态。
 */
class CoinpayBuyShortcode implements ShortcodeHandlerInterface
{
    public function handle(array $attrs, ?string $content, ShortcodeContext $context): string
    {
        $post = $context->post;

        if (! $post instanceof Post) {
            return '';
        }

        $pay = app(\Modules\CryptoPay\Services\CoinPayService::class);

        // 属性价格优先；否则回退表内价格
        $price = null;
        if (isset($attrs['price']) && is_numeric($attrs['price']) && (float) $attrs['price'] > 0) {
            $price = round((float) $attrs['price'], 2);
        }
        if ($price === null) {
            $price = $pay->priceFor((int) $post->id);
        }

        $user = Auth::user();

        // 无价格：非付费文章。仅内容管理者看到配置提示，避免游客看到报错信息。
        if ($price === null) {
            $isManager = $user && method_exists($user, 'hasRole') && (
                $user->hasRole('super_admin')
                || $user->hasRole('admin')
                || $user->hasRole('author')
            );

            if (! $isManager) {
                return '';
            }

            return '<p class="crypto-pay-config-hint" style="border:1px dashed #e2e8f0;border-radius:.5rem;padding:.75rem 1rem;color:#64748b;font-size:.875rem">'
                .'付费阅读未配置：本文未设置价格。请使用 <code>[coinpay_buy price="4.99"]</code> 在正文内标价，'
                .'或在后台文章编辑页的「付费阅读」区块填写价格。</p>';
        }

        if ($pay->hasAccess($user, (int) $post->id)) {
            return '<p class="crypto-pay-owned">✓ 已解锁全文</p>';
        }

        if (! $user) {
            return '<a href="'.e(route('login')).'" class="crypto-pay-login">登录后购买（$'.number_format($price, 2).' 解锁全文）</a>';
        }

        return '<form method="POST" action="'.e(route('crypto-pay.checkout')).'">'
            .csrf_field()
            .'<input type="hidden" name="post_id" value="'.(int) $post->id.'">'
            .'<button type="submit" class="crypto-pay-buy">使用加密货币解锁 · $'.number_format($price, 2).'</button>'
            .'</form>';
    }
}
