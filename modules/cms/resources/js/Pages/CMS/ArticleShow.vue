<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { nextTick, onMounted, ref } from 'vue';
import AppLayout from '@/Components/AppLayout.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import CardContent from '@/components/ui/CardContent.vue';
import SidebarRenderer from '@/components/sidebar/SidebarRenderer.vue';
import CommentSection from '@/components/cms/CommentSection.vue';
import PaywallCard from '@/components/cms/PaywallCard.vue';
import DonateCard from '@/components/cms/DonateCard.vue';
import AdSlot from '@/components/ads/AdSlot.vue';

interface Article {
    id: number;
    title: string;
    slug: string;
    url: string;
    body: string;
    published_at: string | null;
    views_count?: number;
    author: string | null;
    author_id: number | null;
    categories: { name: string; slug: string }[];
    cover_url: string | null;
    prev?: { title: string; slug: string; url: string } | null;
    next?: { title: string; slug: string; url: string } | null;
}

interface RelatedPost {
    id: number;
    title: string;
    slug: string;
    url: string;
    published_at: string | null;
    author: string | null;
}

const props = defineProps<{
    article: Article;
    sidebarCards?: { id: number; type: string; data: Record<string, any>; author?: Record<string, any> }[];
    latestPosts?: { id: number; title: string; slug: string; url?: string; published_at: string | null; author: string | null }[];
    categories?: { id: number; name: string; slug: string; posts_count?: number }[];
    relatedPosts?: RelatedPost[];
    comments?: any[];
    paywall?: { price: number; unlocked: boolean; mode?: 'section' | 'full'; preview?: string | null } | null;
    donation?: { url: string; text?: string } | null;
}>();

const page = usePage();

/** 某位置是否有启用广告（无广告时 AdSlot 不渲染占位） */
function hasAds(position: string): boolean {
    return (((page.props.ads as any) ?? {})[position] ?? []).length > 0;
}

// ---- 段落间广告注入：把锚点插到正文第 N 个顶层元素后，再 Teleport 广告进去 ----
const bodyRef = ref<HTMLElement | null>(null);
const inlineSlotReady = ref(false);

function injectInlineAd() {
    const inlineAds = (((page.props.ads as any) ?? {})['article_inline'] ?? []) as { paragraph?: number }[];
    if (!inlineAds.length || !bodyRef.value) {
        inlineSlotReady.value = true; // 无广告也要置 ready，避免永远不渲染
        return;
    }
    const nth = Math.max(1, inlineAds[0]?.paragraph ?? 1);
    const anchor = bodyRef.value.children[nth - 1];
    if (anchor && !document.getElementById('ad-inline-slot')) {
        const slot = document.createElement('div');
        slot.id = 'ad-inline-slot';
        anchor.after(slot);
    }
    inlineSlotReady.value = true;
}

onMounted(() => nextTick(injectInlineAd));
</script>

<template>
    <Head :title="props.article.title">
        <meta v-if="(page.props.seo as any)?.description" name="description" :content="(page.props.seo as any).description" />
        <meta v-if="(page.props.seo as any)?.keywords" name="keywords" :content="(page.props.seo as any).keywords" />
    </Head>
    <AppLayout>
        <!-- 侧边栏：系统级 Sidebar 引擎下发（作者中心注入当前文章作者数据） -->
        <template #sidebar>
            <SidebarRenderer :cards="props.sidebarCards ?? []" :latest-posts="props.latestPosts ?? []"
                             :categories="props.categories ?? []" />
        </template>

        <Card class="overflow-hidden pt-0">
            <img v-if="article.cover_url" :src="article.cover_url" :alt="article.title"
                 class="h-64 w-full object-cover">

            <CardContent class="p-6 sm:p-8">
                <div class="flex flex-wrap items-center gap-2">
                    <Badge v-for="cat in article.categories" :key="cat.slug" variant="secondary"
                           as-child>
                        <a :href="`/c/${cat.slug}`" class="transition hover:text-foreground">{{ cat.name }}</a>
                    </Badge>
                    <span class="text-xs text-muted-foreground">{{ article.published_at }}</span>
                </div>

                <h1 class="mt-3 text-3xl font-bold tracking-tight">{{ article.title }}</h1>

                <!-- 广告位：标题下 -->
                <AdSlot position="article_top" class="mt-4" />

                <div class="mt-4 flex items-center gap-2 border-b pb-6">
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-primary text-sm font-medium text-primary-foreground">
                        {{ (article.author ?? '?').slice(0, 1) }}
                    </div>
                    <Link v-if="article.author_id" :href="`/users/${article.author_id}`"
                          class="text-sm font-medium hover:underline">
                        {{ article.author }}
                    </Link>
                    <span v-else class="text-sm text-muted-foreground">{{ article.author }}</span>
                </div>

                <!-- 整篇付费：未解锁时以解锁卡替代正文 -->
                <template v-if="props.paywall && !props.paywall.unlocked && props.paywall.mode === 'full'">
                    <PaywallCard class="mt-6" :price="props.paywall.price" mode="full" :preview="props.paywall.preview" />
                </template>
                <!-- 正文（服务端已渲染短码并清洗；v-html 白名单场景）。
                     分段付费（section）时 body 仅含短码前的免费部分，锁卡在正文之后 -->
                <div v-else ref="bodyRef" class="prose prose-zinc mt-6 max-w-none leading-relaxed prose-headings:font-semibold"
                     v-html="article.body" />
                <!-- 广告位：文章段落间（按设置注入在第 N 段后） -->
                <Teleport v-if="inlineSlotReady && hasAds('article_inline')" :to="'#ad-inline-slot'">
                    <AdSlot position="article_inline" class="not-prose my-6" />
                </Teleport>

                <!-- 分段付费：正文结束处的解锁卡 -->
                <PaywallCard v-if="props.paywall && !props.paywall.unlocked && props.paywall.mode === 'section'"
                             class="mt-6" :price="props.paywall.price" mode="section" />
            </CardContent>

            <!-- 广告位：文章末尾（相关文章下方、上下篇之上） -->
            <AdSlot v-if="hasAds('article_bottom')" position="article_bottom" class="mx-6 mb-6" />

            <!-- 捐赠按钮（正文结束、上下篇之前） -->
            <DonateCard v-if="props.donation?.url" class="mx-6 mb-6" :url="props.donation.url" :text="props.donation.text" />

            <!-- 上一篇 / 下一篇 -->
            <div v-if="article.prev || article.next" class="grid grid-cols-1 gap-3 border-t p-6 sm:grid-cols-2">
                <Link v-if="article.prev" :href="article.prev.url"
                      class="group rounded-lg border p-4 transition hover:bg-muted">
                    <p class="text-xs text-muted-foreground">← 上一篇</p>
                    <p class="mt-1 line-clamp-1 text-sm font-medium group-hover:underline">{{ article.prev.title }}</p>
                </Link>
                <div v-else />
                <Link v-if="article.next" :href="article.next.url"
                      class="group rounded-lg border p-4 text-right transition hover:bg-muted">
                    <p class="text-xs text-muted-foreground">下一篇 →</p>
                    <p class="mt-1 line-clamp-1 text-sm font-medium group-hover:underline">{{ article.next.title }}</p>
                </Link>
            </div>
        </Card>

        <!-- 广告位：相关文章上方 -->
        <AdSlot position="article_before_related" class="mt-8" />

        <!-- 相关文章 -->
        <section v-if="props.relatedPosts && props.relatedPosts.length" class="mt-8">
            <h2 class="mb-4 text-lg font-semibold">相关文章</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <Link v-for="post in props.relatedPosts" :key="post.id" :href="post.url"
                      class="group transition hover:shadow-md">
                    <Card>
                        <CardContent class="p-4">
                            <h3 class="line-clamp-1 text-sm font-medium group-hover:underline">{{ post.title }}</h3>
                            <p class="mt-1.5 text-xs text-muted-foreground">{{ post.author }} · {{ post.published_at }}</p>
                        </CardContent>
                    </Card>
                </Link>
            </div>
        </section>

        <!-- 广告位：评论区上方 / 下方 -->
        <AdSlot position="comments_above" class="mt-8" />

        <!-- 评论区：Markdown 编辑器 + 楼中楼 + UA 追踪 -->
        <section class="mt-8">
            <CommentSection :action-url="`${props.article.url}/comments`" :comments="props.comments ?? []" />
        </section>

        <AdSlot position="comments_below" class="mt-6" />
    </AppLayout>
</template>
