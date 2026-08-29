<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Card from '@/components/ui/Card.vue';
import CardContent from '@/components/ui/CardContent.vue';
import Button from '@/components/ui/Button.vue';

/**
 * 付费文章解锁卡（CryptoPay 插件）。
 * mode=section：正文短码切分，此处显示"继续阅读"锁；mode=full：整篇付费 + 摘要预览。
 */
const props = defineProps<{
    price: number;
    mode?: 'section' | 'full';
    preview?: string | null;
}>();

const page = usePage();
const user = computed(() => page.props.auth?.user);

const submitting = ref(false);

function buy() {
    if (submitting.value) return;
    submitting.value = true;

    router.post('/crypto-pay/checkout', { post_id: (page.props.article as any)?.id }, {
        onFinish: () => { submitting.value = false; },
    });
}
</script>

<template>
    <Card class="my-6 border-2 border-dashed">
        <CardContent class="p-8 text-center">
            <p class="text-4xl">🔒</p>

            <template v-if="props.mode === 'section'">
                <h3 class="mt-3 text-lg font-semibold">以下内容为付费专属</h3>
                <p class="mt-1 text-sm text-muted-foreground">解锁后继续阅读剩余部分</p>
            </template>
            <template v-else>
                <h3 class="mt-3 text-lg font-semibold">本文为付费内容</h3>
                <p v-if="props.preview" class="mx-auto mt-2 max-w-xl text-sm leading-relaxed text-muted-foreground">{{ props.preview }}…</p>
            </template>

            <div class="mt-6 flex items-center justify-center gap-3">
                <template v-if="user">
                    <Button size="lg" :disabled="submitting" @click="buy">
                        {{ submitting ? '正在创建订单…' : `加密货币解锁 · $${props.price.toFixed(2)}` }}
                    </Button>
                </template>
                <template v-else>
                    <!-- 游客可直接购买（绑定当前会话）；提示注册避免丢单 -->
                    <Button size="lg" :disabled="submitting" @click="buy">
                        {{ submitting ? '正在创建订单…' : `游客购买 · $${props.price.toFixed(2)}` }}
                    </Button>
                    <Button variant="outline" as="a" href="/register" size="lg">注册后购买（推荐）</Button>
                </template>
            </div>

            <p v-if="!user" class="mt-3 text-xs text-muted-foreground">
                游客购买后在本浏览器解锁；<a href="/register" class="font-medium text-primary hover:underline">注册账号</a>
                后购买可永久绑定账号，避免清缓存丢单。
            </p>
            <p class="mt-2 text-xs text-muted-foreground">
                支持 BTC / ETH / USDT 等加密货币 ·
                <a href="https://account.nowpayments.io/create-account?link_id=3940543227&utm_source=affiliate_lk&utm_medium=referral"
                   target="_blank" rel="noopener sponsored" class="underline hover:text-foreground">NOWPayments</a>
                /
                <a href="https://dash.xca.sh/register?ref=2GWV5MKT"
                   target="_blank" rel="noopener sponsored" class="underline hover:text-foreground">Xcash</a>
                安全结算
            </p>
        </CardContent>
    </Card>
</template>
