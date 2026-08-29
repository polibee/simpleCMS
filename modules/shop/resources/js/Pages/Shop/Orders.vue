<script setup lang="ts">
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Components/AppLayout.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import CardContent from '@/components/ui/CardContent.vue';

interface Order {
    id: number;
    order_no: string;
    product: string;
    amount: number;
    currency: string;
    channel: string;
    status: string;
    paid_at: string | null;
    created_at: string;
    delivery: { type: string; url?: string; code?: string; content?: string; error?: string } | null;
}

const props = defineProps<{ orders: Order[] }>();

const page = usePage();
const flash = computed(() => (page.props.flash as any) ?? {});

const statusBadge = (s: string) => s === 'paid'
    ? { text: '已支付', class: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' }
    : s === 'pending'
        ? { text: '待支付', class: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' }
        : { text: '失败', class: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' };

const channelText = (c: string) => ({ mock: 'Mock', xcash: 'Xcash', paypal: 'PayPal' })[c] ?? c;
</script>

<template>
    <Head title="我的订单" />
    <AppLayout>
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-2xl font-semibold tracking-tight">我的订单</h1>
            <Button variant="ghost" size="sm" as="a" href="/shop">← 返回商城</Button>
        </div>

        <div v-if="flash.success" class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ flash.success }}</div>
        <div v-if="flash.error" class="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ flash.error }}</div>

        <Card v-if="!orders.length">
            <CardContent class="py-12 text-center text-sm text-muted-foreground">
                还没有订单，去 <a href="/shop" class="text-primary hover:underline">商城</a> 逛逛吧。
            </CardContent>
        </Card>

        <div v-else class="space-y-3">
            <Card v-for="o in orders" :key="o.id">
                <CardContent class="flex flex-col gap-2 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="truncate font-medium">{{ o.product }}</span>
                            <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium" :class="statusBadge(o.status).class">
                                {{ statusBadge(o.status).text }}
                            </span>
                        </div>
                        <p class="mt-1 truncate font-mono text-xs text-muted-foreground">{{ o.order_no }}</p>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            {{ o.amount }} {{ o.currency }} · {{ channelText(o.channel) }} · {{ o.paid_at ?? o.created_at }}
                        </p>

                        <!-- 交付内容 -->
                        <div v-if="o.status === 'paid' && o.delivery" class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 dark:border-emerald-800 dark:bg-emerald-950/30">
                            <p class="text-xs font-medium text-emerald-700 dark:text-emerald-300">📦 交付内容</p>

                            <!-- 下载链接 -->
                            <a v-if="o.delivery.type === 'download' && o.delivery.url"
                               :href="o.delivery.url" target="_blank" rel="noopener"
                               class="mt-1.5 inline-flex items-center gap-1.5 text-sm font-medium text-emerald-700 underline dark:text-emerald-300">
                                ⬇ 下载 {{ o.product }}
                            </a>

                            <!-- 邀请码 -->
                            <p v-else-if="o.delivery.type === 'invite_code' && o.delivery.code"
                               class="mt-1.5 font-mono text-sm font-bold text-emerald-800 dark:text-emerald-200">
                                {{ o.delivery.code }}
                            </p>

                            <!-- 文本内容 -->
                            <p v-else-if="o.delivery.type === 'content' && o.delivery.content"
                               class="mt-1.5 whitespace-pre-wrap font-mono text-xs text-emerald-800 dark:text-emerald-200">
                                {{ o.delivery.content }}
                            </p>

                            <p v-else-if="o.delivery.error"
                               class="mt-1 text-xs text-red-600">{{ o.delivery.error }}</p>
                        </div>
                    </div>
                    <Button v-if="o.status === 'pending'" variant="outline" size="sm" as="a"
                            :href="`/shop/mock/${o.order_no}`" class="shrink-0">
                        继续支付
                    </Button>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
