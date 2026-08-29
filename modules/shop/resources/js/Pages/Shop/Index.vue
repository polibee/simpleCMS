<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Components/AppLayout.vue';
import Badge from '@/components/ui/Badge.vue';
import Card from '@/components/ui/Card.vue';
import CardContent from '@/components/ui/CardContent.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';

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
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-2xl font-semibold tracking-tight">商品商城</h1>
            <Button variant="ghost" size="sm" as="a" href="/shop/orders">我的订单</Button>
        </div>

        <div v-if="flash.error"
             class="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ flash.error }}</div>
        <div v-if="flash.success"
             class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ flash.success }}</div>

        <!-- 分类 Tab + 搜索 -->
        <div class="mb-5 flex flex-wrap items-center gap-2">
            <button type="button" @click="activeCategory = null"
                    class="rounded-full px-3 py-1 text-xs font-medium transition"
                    :class="!activeCategory ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground hover:text-foreground'">
                全部
            </button>
            <button v-for="cat in categories" :key="cat" type="button" @click="activeCategory = cat"
                    class="rounded-full px-3 py-1 text-xs font-medium transition"
                    :class="activeCategory === cat ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground hover:text-foreground'">
                {{ cat }}
            </button>
            <div class="ml-auto w-52">
                <input v-model="searchQ" type="text" placeholder="搜索商品…"
                       class="w-full rounded-full border border-input bg-background px-4 py-1.5 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" />
            </div>
        </div>

        <Card v-if="!filtered.length">
            <CardContent class="py-12 text-center text-sm text-muted-foreground">
                {{ searchQ ? '没有匹配的商品' : '商城暂无在售商品，敬请期待。' }}
            </CardContent>
        </Card>

        <div v-else class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3">
            <component :is="'a'" v-for="p in filtered" :key="p.id" :href="p.url"
                       class="group transition hover:-translate-y-0.5 hover:shadow-md">
                <Card class="h-full overflow-hidden pt-0">
                    <div class="aspect-video w-full overflow-hidden rounded-t-xl bg-muted">
                        <img v-if="p.image_url" :src="p.image_url" :alt="p.name"
                             class="h-full w-full object-cover transition group-hover:scale-105" loading="lazy">
                    </div>
                    <CardContent class="pb-3">
                        <div class="flex items-start justify-between gap-2">
                            <h3 class="line-clamp-1 font-semibold group-hover:underline">{{ p.name }}</h3>
                            <Badge v-if="p.category" variant="secondary">{{ p.category }}</Badge>
                        </div>
                    </CardContent>
                    <div class="flex items-center justify-between border-t px-6 py-3">
                        <span class="text-lg font-bold text-primary">${{ p.price }}</span>
                        <Badge :variant="p.stock > 0 ? 'secondary' : 'destructive'">
                            {{ p.stock > 0 ? `库存 ${p.stock}` : '已售罄' }}
                        </Badge>
                    </div>
                </Card>
            </component>
        </div>

        <p v-if="mockMode" class="mt-6 text-center text-xs text-muted-foreground">支付通道为 Mock 联调模式。</p>
    </AppLayout>
</template>
