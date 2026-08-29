<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/Components/AppLayout.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import CardContent from '@/components/ui/CardContent.vue';
import SidebarRenderer from '@/components/sidebar/SidebarRenderer.vue';
import BannerCarousel from '@/components/cms/BannerCarousel.vue';
import ArticleStream from '@/components/cms/ArticleStream.vue';
import AdSlot from '@/components/ads/AdSlot.vue';

interface PostCard {
    id: number;
    title: string;
    slug: string;
    url: string;
    excerpt: string;
    published_at: string | null;
    author: string | null;
    categories: { name: string; slug: string }[];
    thumbnail_url: string | null;
    comments_count: number;
}

interface Banner {
    id: number;
    title: string | null;
    image_url: string;
    link_url: string | null;
    new_tab?: boolean;
}

const page = usePage();
const articlesUrl = computed(() => (page.props.cms as any)?.listUrl ?? '/articles');
const ads = computed<Record<string, any[]>>(() => (page.props.ads as any) ?? {});
// 文章卡片式广告（top / middle / bottom 注入文章流）
const cardAds = computed(() => ({
    top: ads.value['home_cards_top'] ?? [],
    middle: ads.value['home_cards_middle'] ?? [],
    bottom: ads.value['home_cards_bottom'] ?? [],
}));

const props = defineProps<{
    site?: { name?: string };
    seo?: { title?: string; description?: string; keywords?: string };
    posts: { data: PostCard[]; current_page: number; last_page: number };
    banners?: Banner[];
    featured?: { title: string; slug: string; excerpt: string; author: string | null; published_at: string | null } | null;
    sidebarCards?: { id: number; type: string; data: Record<string, any> }[];
    categories?: { id: number; name: string; slug: string; posts_count?: number }[];
    latestPosts?: { id: number; title: string; slug: string; published_at: string | null; author: string | null }[];
}>();
</script>

<template>
    <Head :title="props.seo?.title ?? ((props.site?.name ?? 'CMSForum') + ' · 首页')">
        <meta v-if="props.seo?.description" name="description" :content="props.seo.description" />
        <meta v-if="props.seo?.keywords" name="keywords" :content="props.seo.keywords" />
    </Head>
    <AppLayout>
        <!-- Hero：后台轮播图；未配置时回退静态文案 -->
        <section class="mb-8">
            <BannerCarousel v-if="props.banners && props.banners.length" :banners="props.banners" />

            <div v-else class="rounded-2xl bg-gradient-to-br from-zinc-900 via-zinc-800 to-zinc-900 p-8 text-white sm:p-10">
                <p class="text-sm font-medium text-zinc-400">{{ props.site?.name ?? 'CMSForum' }} · 内容社区</p>
                <h1 class="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">
                    写作、交流，构建你的内容社区
                </h1>
                <p class="mt-3 max-w-2xl text-zinc-300">
                    CMS + Forum 模块化架构，支持主题覆盖与插件扩展。浏览最新文章，或立即加入社区开始创作。
                </p>
                <div class="mt-6 flex flex-wrap gap-3">
                    <Button variant="secondary" as="a" :href="articlesUrl">浏览文章</Button>
                    <Button variant="outline"
                            class="border-white/30 bg-transparent text-white hover:bg-white/10 hover:text-white"
                            as="a" href="/register">注册账号</Button>
                </div>
            </div>

            <!-- 广告位：轮播图下方 -->
            <AdSlot position="banner_below" class="mt-5" />
        </section>

        <!-- 最新文章流（网格/列表可切换） -->
        <div class="mb-5 flex items-center justify-between">
            <h2 class="text-xl font-semibold tracking-tight">最新文章</h2>
            <Button variant="ghost" size="sm" as="a" :href="articlesUrl">全部文章 →</Button>
        </div>

        <ArticleStream :posts="props.posts.data" layout="grid" grid-cols="sm:grid-cols-2" thumb-aspect="aspect-[21/9]"
                       :card-ads="cardAds" />

        <!-- 广告位：首页文章卡片区下方 -->
        <AdSlot position="home_cards_below" class="mt-6" />

        <!-- 分页 -->
        <nav v-if="props.posts.last_page > 1" class="mt-8 flex items-center justify-center gap-1.5">
            <Button v-for="p in props.posts.last_page" :key="p" as="a" href="/" :data="{ page: p }" preserve-state
                    :variant="p === props.posts.current_page ? 'default' : 'outline'" size="sm">{{ p }}</Button>
        </nav>

        <!-- 侧边栏：系统 Sidebar 引擎 -->
        <template #sidebar>
            <SidebarRenderer :cards="props.sidebarCards ?? []"
                             :categories="props.categories ?? []"
                             :latest-posts="props.latestPosts ?? []" />
        </template>
    </AppLayout>
</template>
