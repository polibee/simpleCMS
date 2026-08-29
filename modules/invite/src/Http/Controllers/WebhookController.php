<?php

declare(strict_types=1);

namespace Modules\Invite\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Invite\Models\InviteOrder;
use Modules\Invite\Services\InviteService;

/**
 * 邀请码加密支付回调（Xcash HMAC 验签，与 crypto-pay 同模式；无 CSRF）。
 */
class WebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        $rawBody = $request->getContent();

        if (! \Modules\CryptoPay\Services\XcashClient::verifyWebhook($rawBody, $request->headers->all())) {
            return response('invalid signature', 401);
        }

        $payload = json_decode($rawBody, true) ?? [];

        if (($payload['type'] ?? '') !== 'invoice') {
            return response('ok', 200);
        }

        $data = $payload['data'] ?? [];
        $outNo = (string) ($data['out_no'] ?? '');
        $confirmed = (bool) ($data['confirmed'] ?? false);

        if ($outNo === '') {
            return response('missing out_no', 400);
        }

        $order = InviteOrder::query()->where('order_no', $outNo)->first();

        if (! $order) {
            return response('unknown order', 200);
        }

        if ($confirmed) {
            app(InviteService::class)->markOrderPaid($order);
        }

        return response('ok', 200);
    }
}
