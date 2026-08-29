<?php

declare(strict_types=1);

namespace Modules\Shop\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Shop\Models\ShopOrder;
use Modules\Shop\Services\ShopService;

/**
 * Xcash 支付回调（XC-Signature 验签，与 crypto-pay/invite 同模式；无 CSRF）。
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

        $order = ShopOrder::query()->where('order_no', $outNo)->first();

        if ($order) {
            app(ShopService::class)->markPaid($order);
        }

        return response('ok', 200);
    }
}
