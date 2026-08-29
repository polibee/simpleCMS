<?php

namespace Modules\CryptoPay\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\CryptoPay\Models\CryptoOrder;
use Modules\CryptoPay\Services\CoinPayService;
use Modules\CryptoPay\Services\XcashClient;

/**
 * 支付 Webhook 接收端（CoinPayments + Xcash 双通道）。
 * 安全：各自 HMAC 验签（CoinPayments SDK 验证器 / Xcash XC-* 签名）→ 幂等标记支付。
 */
class WebhookController extends Controller
{
    public function handle(Request $request, CoinPayService $pay): Response
    {
        $rawBody = $request->getContent();

        // Xcash 通道（XC-* 签名头）
        if ($request->hasHeader('XC-Signature')) {
            return $this->handleXcash($request, $pay, $rawBody);
        }

        // CoinPayments 通道（SDK HMAC 验签）
        $verified = $pay->verifyWebhook(
            $request->method(),
            $request->fullUrl(),
            $rawBody,
            $request->headers->all(),
        );

        if (! $verified) {
            return response('invalid signature', 401);
        }

        $payload = json_decode($rawBody, true) ?? [];
        $invoiceId = (string) ($payload['invoiceId'] ?? $payload['id'] ?? '');
        $state = strtolower((string) ($payload['state'] ?? ''));

        if ($invoiceId === '') {
            return response('missing invoice id', 400);
        }

        $order = $pay->orderByInvoice($invoiceId);

        if (! $order) {
            return response('unknown invoice', 200); // 已知即成功，避免重放轰炸
        }

        if (in_array($state, ['completed', 'paid'], true)) {
            $pay->markPaid($order, ['source' => 'webhook', 'state' => $state]);
        }

        return response('ok', 200);
    }

    /**
     * Xcash 账单 Webhook：HMAC 验签 → out_no 找单 → completed 且 confirmed 授予。
     */
    private function handleXcash(Request $request, CoinPayService $pay, string $rawBody): Response
    {
        if (! XcashClient::verifyWebhook($rawBody, $request->headers->all())) {
            return response('invalid signature', 401);
        }

        $payload = json_decode($rawBody, true) ?? [];

        if (($payload['type'] ?? '') !== 'invoice') {
            return response('ok', 200); // 非账单事件（如 deposit）忽略
        }

        $data = $payload['data'] ?? [];
        $outNo = (string) ($data['out_no'] ?? '');
        $confirmed = (bool) ($data['confirmed'] ?? false);

        if ($outNo === '') {
            return response('missing out_no', 400);
        }

        $order = CryptoOrder::query()->where('order_no', $outNo)->first();

        if (! $order) {
            return response('unknown order', 200);
        }

        if ($confirmed) {
            $pay->markPaid($order, ['source' => 'xcash-webhook', 'hash' => $data['hash'] ?? null]);
        }

        return response('ok', 200);
    }
}
