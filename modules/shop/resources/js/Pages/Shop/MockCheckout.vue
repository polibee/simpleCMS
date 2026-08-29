<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Components/AppLayout.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import CardContent from '@/components/ui/CardContent.vue';

/** Mock 收银台：仅联调环境（crypto_pay_mock_mode 开启）可达。 */
const props = defineProps<{
    order: { order_no: string; amount: number; currency: string; status: string };
}>();

function confirmPay() {
    router.post(`/shop/mock/${props.order.order_no}`);
}
</script>

<template>
    <Head title="模拟支付" />
    <AppLayout>
        <Card class="mx-auto max-w-md">
            <CardContent class="space-y-4 p-6 text-center">
                <p class="text-xs uppercase tracking-wide text-muted-foreground">Mock 支付（联调模式）</p>
                <h1 class="text-2xl font-bold">${{ order.amount }} <span class="text-sm font-normal text-muted-foreground">{{ order.currency }}</span></h1>
                <p class="font-mono text-xs text-muted-foreground">{{ order.order_no }}</p>
                <p class="text-sm">
                    状态：
                    <span :class="order.status === 'paid' ? 'text-emerald-600' : 'text-amber-600'">
                        {{ order.status === 'paid' ? '已支付' : '待支付' }}
                    </span>
                </p>

                <Button v-if="order.status === 'pending'" @click="confirmPay">确认支付（模拟成功）</Button>
                <Button v-else variant="outline" as="a" href="/shop/orders">返回我的订单</Button>
            </CardContent>
        </Card>
    </AppLayout>
</template>
