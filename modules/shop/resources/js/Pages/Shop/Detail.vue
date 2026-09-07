<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Components/AppLayout.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';

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
    // 后端 PaymentGateway::availableMethods() 返回对象数组（key/label/icon/channel）
    payMethods: { key: string; label: string; icon: string; channel: string }[];
}>();

const page = usePage();
const flash = computed(() => (page.props.flash as any) ?? {});
const user = computed(() => (page.props.auth as any)?.user);

const purchasable = computed(() => props.product.stock > 0);
const stockLabel = computed(() => {
    if (!purchasable.value) return { text: '已售罄', variant: 'destructive' as const };
    if (props.product.stock <= 5) return { text: `仅剩 ${props.product.stock} 件`, variant: 'default' as const };
    return { text: `库存 ${props.product.stock} 件`, variant: 'secondary' as const };
});

const methodOptions = computed(() => (props.payMethods ?? []).map((m) => ({
    key: m.key,
    label: m.label,
    icon: m.icon,
    color: m.icon === '🅰' ? 'text-blue-500'
        : m.icon === '💬' ? 'text-green-500'
        : m.icon === '₿' ? 'text-amber-500'
        : m.icon === '🌐' ? 'text-blue-600'
        : m.icon === '🧪' ? 'text-gray-500'
        : 'text-slate-500',
})));

// 支付方式内嵌选择（默认选中第一种）
const selectedMethod = ref<string | null>(null);
selectedMethod.value = methodOptions.value[0]?.key ?? null;

const submitting = ref(false);

function confirmPay() {
    if (submitting.value || !selectedMethod.value) return;
    submitting.value = true;
    router.post('/shop/buy', { slug: props.product.slug, method: selectedMethod.value }, {
        onFinish: () => { submitting.value = false; },
    });
}
</script>

<template>
    <Head :title="product.name" />
    <AppLayout>
        <!-- 面包屑 -->
        <nav class="mb-5 text-sm text-muted-foreground">
            <a href="/shop" class="transition hover:text-foreground">商品商城</a>
            <span class="mx-2">/</span>
            <span class="text-foreground">{{ product.name }}</span>
        </nav>

        <div v-if="flash.error"
             class="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ flash.error }}</div>
        <div v-if="flash.success"
             class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ flash.success }}</div>

        <!-- 左图右文两栏 -->
        <div class="grid grid-cols-1 gap-8 lg:grid-cols-2 lg:gap-12">
            <!-- 图片（桌面端粘性） -->
            <div class="relative overflow-hidden rounded-2xl border bg-muted lg:sticky lg:top-6 lg:self-start">
                <img :src="product.image_url || '/images/product-placeholder.svg'" :alt="product.name"
                     class="aspect-[4/3] w-full object-cover">
                <Badge v-if="!purchasable" variant="destructive"
                       class="absolute left-4 top-4 text-sm backdrop-blur-sm">已售罄</Badge>
            </div>

            <!-- 信息区 -->
            <div class="flex flex-col">
                <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ product.name }}</h1>

                <div class="mt-4 flex items-end justify-between gap-4">
                    <div>
                        <p class="text-xs text-muted-foreground">售价</p>
                        <p class="mt-0.5 text-3xl font-bold text-primary sm:text-4xl">
                            ${{ product.price }}
                            <span class="text-base font-normal text-muted-foreground">{{ product.currency }}</span>
                        </p>
                    </div>
                    <Badge :variant="stockLabel.variant" class="mb-1">{{ stockLabel.text }}</Badge>
                </div>

                <div class="mt-3 flex items-center gap-4 text-sm text-muted-foreground">
                    <span>已售 {{ product.sold }} 件</span>
                    <span v-if="mockMode" class="inline-flex items-center gap-1">
                        <span class="h-1.5 w-1.5 rounded-full bg-amber-500" /> Mock 联调模式
                    </span>
                </div>

                <div class="mt-6 border-t pt-6">
                    <h2 class="text-sm font-semibold text-foreground">商品介绍</h2>
                    <div v-if="product.description"
                         class="prose prose-zinc mt-3 max-w-none text-sm leading-relaxed dark:prose-invert"
                         v-html="product.description" />
                    <p v-else class="mt-3 text-sm text-muted-foreground">该商品暂无详细介绍。</p>
                </div>

                <!-- 购买区 -->
                <div class="mt-8 rounded-2xl border bg-card p-5 sm:p-6">
                    <div v-if="methodOptions.length" class="mb-5">
                        <p class="mb-3 text-sm font-medium text-foreground">选择支付方式</p>
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <button v-for="m in methodOptions" :key="m.key" type="button"
                                    @click="selectedMethod = m.key"
                                    class="flex items-center gap-2.5 rounded-xl border px-3.5 py-2.5 text-left text-sm transition"
                                    :class="selectedMethod === m.key
                                        ? 'border-primary bg-primary/5 ring-1 ring-primary'
                                        : 'border-input hover:border-primary/40 hover:bg-muted/60'">
                                <span class="text-lg leading-none" :class="m.color">{{ m.icon }}</span>
                                <span class="font-medium">{{ m.label }}</span>
                                <span v-if="selectedMethod === m.key" class="ml-auto text-sm text-primary">✓</span>
                            </button>
                        </div>
                    </div>

                    <div class="flex gap-3">
                        <Button size="lg" class="flex-1" :disabled="!purchasable || submitting" @click="confirmPay">
                            <span v-if="submitting">正在创建订单…</span>
                            <template v-else>
                                <span class="mr-1.5">{{ purchasable ? '立即购买' : '已售罄' }}</span>
                            </template>
                        </Button>
                        <Button variant="outline" size="lg" as="a" href="/shop/orders">我的订单</Button>
                    </div>

                    <p v-if="!user" class="mt-3 text-center text-xs text-muted-foreground">
                        购买需要先登录 · <a href="/login" class="font-medium text-primary hover:underline">去登录</a>
                    </p>
                    <p v-else class="mt-3 text-center text-xs text-muted-foreground">
                        支付成功后商品将自动交付到「我的订单」
                    </p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
