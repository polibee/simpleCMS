<div class="space-y-3 text-sm leading-relaxed text-gray-600">
    <p><strong>两种开启付费阅读的方式（任选其一）：</strong></p>
    <ol class="list-decimal space-y-1 pl-5">
        <li><strong>后台设置价格</strong>：在下方列表找到文章 → 「设置价格」→ 填解锁价（美元）。</li>
        <li><strong>正文内联标价</strong>：编辑文章时，在正文「免费部分」和「付费部分」之间插入短码
            <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs">[coinpay_buy price="4.99"]</code>，
            插入点之前的内容免费展示，之后的内容付费解锁。未写 <code>price</code> 属性时，使用后台设置的默认价格。</li>
    </ol>
    <p><strong>解锁流程</strong>：读者点击购买 → 加密支付收银台（Xcash / CoinPayments / PayPal 等按后台启用通道）→ 支付成功 → 文章自动解锁。</p>
    <p class="text-xs text-gray-400">已支付订单与访问权限绑定账号（游客绑定浏览器会话），同一文章不会重复扣费。</p>
</div>
