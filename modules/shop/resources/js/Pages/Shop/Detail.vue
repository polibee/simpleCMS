<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Components/AppLayout.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import CardContent from '@/components/ui/CardContent.vue';

const props = defineProps<{
    product: {
        id: number;
        name: string;
        slug: string;
        description: string | null;
        price: number;
        currency: string;
        stock: number;
        sold: number;
        image_url: string | null;
    };
    mockMode: boolean;
    payMethods: string[];
}>();

const page = usePage();
const flash = computed(() => (page.props.flash as any) ?? {});
const user = computed(() => (page.props.auth as any)?.user);

const purchasable = computed(() => props.product.stock > 0);

// ── 支付方式选择弹窗 ──
const payModalOpen = ref(false);
const selectedMethod = ref<string | null>(null);

/** 支付方式元数据（图标 emoji + 中文名），按 payMethods 过滤。 */
const methodMeta: Record<string, { label: string; icon: string; color: string }> = {
    alipay: { label: '支付宝', icon: '🅰', color: 'text-blue-500' },
    wxpay: { label: '微信支付', icon: '💬', color: 'text-green-500' },
    qqpay: { label: 'QQ 钱包', icon: '🐧', color: 'text-blue-400' },
    paypal: { label: 'PayPal', icon: '🌐', color: 'text-blue-600' },
    crypto: { label: '加密货币', icon: '₿', color: 'text-amber-500' },
    mock: { label: 'Mock 支付', icon: '🧪', color: 'text-gray-500' },
};

const methodOptions = computed(() => (props.payMethods ?? []).map((m) => ({
    key: m,
    ...(methodMeta[m] ?? { label: m, icon: '💳', color: '' }),
})));

function openPayModal() {
    if (!user.value) {
        router.visit('/login');
        return;
    }
    if (!purchasable.value) return;
    // 默认选中第一种支付方式
    selectedMethod.value = methodOptions.value[0]?.key ?? null;
    payModalOpen.value = true;
}

function confirmPay() {
    payModalOpen.value = false;
    router.post('/shop/buy', { slug: props.product.slug, method: selectedMethod.value });
}

function closePayModal() {
    payModalOpen.value = false;
}
</script>

<template>
    <Head :title="product.name" />
    <AppLayout>
        <Card class="overflow-hidden pt-0">
            <div class="aspect-video w-full overflow-hidden bg-muted">
                <img v-if="product.image_url" :src="product.image_url" :alt="product.name" class="h-full w-full object-cover">
            </div>

            <CardContent class="p-6 sm:p-8">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h1 class="text-2xl font-bold tracking-tight">{{ product.name }}</h1>
                    <span class="text-3xl font-bold text-primary">${{ product.price }}</span>
                </div>

                <div class="mt-3 flex items-center gap-2 text-sm text-muted-foreground">
                    <Badge :variant="purchasable ? 'secondary' : 'destructive'">
                        {{ purchasable ? `库存 ${product.stock}` : '已售罄' }}
                    </Badge>
                    <span>已售 {{ product.sold }}</span>
                    <span v-if="mockMode">· Mock 联调模式</span>
                </div>

                <div v-if="product.description" class="prose prose-zinc dark:prose-invert mt-6 max-w-none text-sm leading-relaxed"
                     v-html="product.description" />

                <div class="mt-8 flex gap-3">
                    <Button size="lg" :disabled="!purchasable" @click="openPayModal">
                        {{ purchasable ? '立即购买' : '已售罄' }}
                    </Button>
                    <Button variant="outline" size="lg" as="a" href="/shop/orders">我的订单</Button>
                </div>
                <p v-if="!user" class="mt-3 text-xs text-muted-foreground">购买需要先登录。</p>
            </CardContent>
        </Card>

        <!-- 支付确认弹窗（含支付方式选择） -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0"
                leave-active-class="transition duration-150 ease-in"
                leave-to-class="opacity-0">
                <div v-if="payModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
                     @click.self="closePayModal">
                    <div role="dialog" aria-modal="true" aria-label="确认支付"
                         class="w-full max-w-md rounded-2xl border bg-card shadow-2xl">
                        <div class="p-6 sm:p-8">
                            <div class="text-center">
                                <p class="text-xs uppercase tracking-widest text-muted-foreground">确认订单</p>
                                <h2 class="mt-2 text-xl font-semibold">{{ product.name }}</h2>
                                <p class="mt-3 text-3xl font-bold text-primary">${{ product.price }}
                                    <span class="text-sm font-normal text-muted-foreground">{{ product.currency }}</span>
                                </p>
                            </div>

                            <!-- 支付方式选择 -->
                            <div class="mt-6">
                                <p class="mb-2 text-sm font-medium text-muted-foreground">选择支付方式</p>
                                <div class="space-y-2">
                                    <button v-for="m in methodOptions" :key="m.key" type="button"
                                            @click="selectedMethod = m.key"
                                            class="flex w-full items-center gap-3 rounded-xl border px-4 py-3 text-left text-sm transition"
                                            :class="selectedMethod === m.key
                                                ? 'border-primary bg-primary/5 ring-1 ring-primary'
                                                : 'border-input hover:bg-muted'">
                                        <span class="text-xl" :class="m.color">{{ m.icon }}</span>
                                        <span class="font-medium">{{ m.label }}</span>
                                        <span v-if="selectedMethod === m.key"
                                              class="ml-auto text-primary">✓</span>
                                    </button>
                                </div>
                            </div>

                            <div class="mt-6 flex gap-3">
                                <button type="button" @click="closePayModal"
                                        class="flex-1 rounded-xl border border-input bg-background px-4 py-2.5 text-sm font-medium transition hover:bg-muted">
                                    取消
                                </button>
                                <button type="button" @click="confirmPay"
                                        class="flex-1 rounded-xl bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground shadow transition hover:bg-primary/90">
                                    去支付
                                </button>
                            </div>

                            <p class="mt-4 text-center text-xs text-muted-foreground">
                                点击「去支付」后将跳转至收银台完成付款
                            </p>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </AppLayout>
</template>
