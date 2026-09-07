<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Components/AppLayout.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';

interface Product {
    id: number;
    name: string;
    slug: string;
    price: number;
    currency: string;
    stock: number;
    sold: number;
    image_url: string | null;
    category: string | null;
    url: string;
}

const props = defineProps<{
    products: Product[];
    categories: string[];
    mockMode: boolean;
}>();

const page = usePage();
const flash = computed(() => (page.props.flash as any) ?? {});
const searchQ = ref('');
const activeCategory = ref<string | null>(null);

const filtered = computed(() => {
    let list = props.products;
    if (activeCategory.value) {
        list = list.filter((p) => p.category === activeCategory.value);
    }
    const q = searchQ.value.trim().toLowerCase();
    if (q) {
        list = list.filter((p) => p.name.toLowerCase().includes(q) || (p.category ?? '').toLowerCase().includes(q));
    }
    return list;
});
</script>

<template>
    <Head title="商品商城" />
    <AppLayout>
        <!-- 页头 -->
        <div class="mb-8 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">商品商城</h1>
                <p class="mt-1 text-sm text-muted-foreground">精选虚拟商品 · 购买后自动交付</p>
            </div>
            <Button variant="outline" size="sm" as="a" href="/shop/orders">
                <span class="mr-1.5 text-sm">🧾</span> 我的订单
            </Button>
        </div>

        <div v-if="flash.error"
             class="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ flash.error }}</div>
        <div v-if="flash.success"
             class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ flash.success }}</div>

        <!-- 分类 + 搜索 -->
        <div class="mb-6 flex flex-wrap items-center gap-2">
            <button type="button" @click="activeCategory = null"
                    class="rounded-full px-3.5 py-1.5 text-sm font-medium transition"
                    :class="!activeCategory ? 'bg-primary text-primary-foreground shadow-sm' : 'bg-muted text-muted-foreground hover:bg-muted/70 hover:text-foreground'">
                全部
            </button>
            <button v-for="cat in categories" :key="cat" type="button" @click="activeCategory = cat"
                    class="rounded-full px-3.5 py-1.5 text-sm font-medium transition"
                    :class="activeCategory === cat ? 'bg-primary text-primary-foreground shadow-sm' : 'bg-muted text-muted-foreground hover:bg-muted/70 hover:text-foreground'">
                {{ cat }}
            </button>
            <div class="relative ml-auto w-full sm:w-56">
                <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-muted-foreground">⌕</span>
                <input v-model="searchQ" type="text" placeholder="搜索商品…"
                       class="w-full rounded-full border border-input bg-background py-2 pl-8 pr-4 text-sm shadow-sm transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" />
            </div>
        </div>

        <!-- 空态 -->
        <div v-if="!filtered.length"
             class="rounded-2xl border border-dashed bg-muted/30 py-16 text-center">
            <p class="text-3xl">🛍️</p>
            <p class="mt-3 text-sm text-muted-foreground">{{ searchQ ? '没有匹配的商品' : '商城暂无在售商品，敬请期待。' }}</p>
        </div>

        <!-- 商品网格 -->
        <div v-else class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <a v-for="p in filtered" :key="p.id" :href="p.url"
               class="group flex flex-col overflow-hidden rounded-2xl border border-input bg-card shadow-sm transition duration-300 hover:-translate-y-1 hover:border-primary/25 hover:shadow-lg">
                <div class="relative aspect-[4/3] overflow-hidden bg-muted">
                    <img :src="p.image_url || '/images/product-placeholder.svg'" :alt="p.name"
                         class="h-full w-full object-cover transition duration-500 group-hover:scale-105" loading="lazy">
                    <Badge v-if="p.stock <= 0" variant="destructive"
                           class="absolute left-3 top-3 backdrop-blur-sm">已售罄</Badge>
                    <span v-if="p.category"
                          class="absolute right-3 top-3 rounded-full bg-background/80 px-2.5 py-0.5 text-xs font-medium text-foreground backdrop-blur-sm">
                        {{ p.category }}
                    </span>
                </div>
                <div class="flex flex-1 flex-col p-4">
                    <h3 class="line-clamp-2 min-h-10 text-sm font-medium leading-snug transition group-hover:text-primary">
                        {{ p.name }}
                    </h3>
                    <div class="mt-auto flex items-center justify-between pt-3">
                        <span class="text-lg font-bold text-primary">${{ p.price }}</span>
                        <span class="text-xs text-muted-foreground">已售 {{ p.sold }}</span>
                    </div>
                </div>
            </a>
        </div>

        <p v-if="mockMode" class="mt-8 text-center text-xs text-muted-foreground">支付通道为 Mock 联调模式。</p>
    </AppLayout>
</template>
