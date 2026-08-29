<?php

namespace Modules\CryptoPay\Shortcodes;

use Illuminate\Support\Facades\Auth;
use Miran\Mksine\Core\Shortcodes\ShortcodeContext;
use Miran\Mksine\Core\Shortcodes\ShortcodeHandlerInterface;
use Miran\Mksine\Models\Post;

/**
 * [coinpay_buy] — 输出当前文章的加密支付购买按钮。
 * 免费文章/已解锁/未登录 分别渲染不同状态。
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
        $price = $pay->priceFor((int) $post->id);

        if ($price === null) {
            return ''; // 免费文章
        }

        $user = Auth::user();

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
