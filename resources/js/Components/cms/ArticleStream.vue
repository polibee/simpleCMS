<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import CardContent from '@/components/ui/CardContent.vue';
import AdCard from '@/components/ads/AdCard.vue';

/**
 * 文章流组件（网格 + 列表双布局，可切换）。
 * 首页与文章列表页共用；listUrl 提供时切换同步到 URL（?layout=），
 * 未提供时仅本地切换（首页场景）。
 * 支持文章卡片式广告（上/中/下插入，形态随布局自适应）。
 */
export interface PostCard {
    id: number;
    title: string;
    url: string;
    slug?: string;
    excerpt: string;
    published_at: string | null;
    author?: string | null;
    categories: { name: string; slug: string }[];
    thumbnail_url: string | null;
    comments_count?: number;
    views_count?: number;
}

interface CardAd {
    id: number;
    title: string | null;
    text: string | null;
    image_url: string | null;
    link_url: string | null;
    new_tab: boolean;
    paragraph: number | null;
}

const props = withDefaults(defineProps<{
    posts: PostCard[];
    layout: 'grid' | 'list';
    listUrl?: string;
    gridCols?: string;   // 网格列数类，首页用 2 列、列表页用 3 列
    thumbAspect?: string;
    /** 卡片式广告（type=card）：top / middle（paragraph=N）/ bottom */
    cardAds?: { top?: CardAd[]; middle?: CardAd[]; bottom?: CardAd[] };
}>(), {
    gridCols: 'sm:grid-cols-2 xl:grid-cols-3',
    thumbAspect: 'aspect-video',
    cardAds: () => ({}),
});

const page = usePage();
const localLayout = ref<'grid' | 'list'>(props.layout);
const current = computed<'grid' | 'list'>(() => (props.listUrl ? props.layout : localLayout.value));

function switchLayout(next: 'grid' | 'list') {
    if (current.value === next) return;
    if (props.listUrl) {
        router.get(props.listUrl, { layout: next }, { preserveScroll: true });
        return;
    }
    localLayout.value = next;
}

/** 合成渲染序列：普通卡片 + 顶部/中部/底部广告交错 */
const timeline = computed(() => {
    type Item = { kind: 'post'; post: PostCard } | { kind: 'ad'; ad: CardAd };
    const items: Item[] = [];
    const topAds = props.cardAds?.top ?? [];
    const midAds = props.cardAds?.middle ?? [];
    const bottomAds = props.cardAds?.bottom ?? [];

    topAds.forEach((ad) => items.push({ kind: 'ad', ad }));

    const after = Math.max(1, midAds[0]?.paragraph ?? 0); // 0/1 = 插在最前由 top 处理不到时兜底
    props.posts.forEach((post, i) => {
        if (midAds.length && i === after) {
            midAds.forEach((ad) => items.push({ kind: 'ad', ad }));
        }
        items.push({ kind: 'post', post });
    });

    bottomAds.forEach((ad) => items.push({ kind: 'ad', ad }));

    return items;
});

const hasCardAds = computed(() => timeline.value.some((i) => i.kind === 'ad'));

function pageHref(p: number): string {
    const base = props.listUrl ?? (page.props.cms as any)?.listUrl ?? '/';
    return base;
}
</script>

<template>
    <div>
        <!-- 布局切换 -->
        <div class="mb-5 flex items-center justify-end">
            <div class="flex items-center rounded-lg border bg-card p-0.5 shadow-sm">
                <Button :variant="current === 'grid' ? 'default' : 'ghost'" size="sm"
                        @click="switchLayout('grid')">网格</Button>
                <Button :variant="current === 'list' ? 'default' : 'ghost'" size="sm"
                        @click="switchLayout('list')">列表</Button>
            </div>
        </div>

        <!-- 网格布局（含卡片式广告交错插入） -->
        <div v-if="current === 'grid' && hasCardAds" class="grid grid-cols-1 gap-5" :class="props.gridCols">
            <template v-for="(item, idx) in timeline" :key="`${item.kind}-${item.kind === 'post' ? item.post.id : item.ad.id}-${idx}`">
                <Link v-if="item.kind === 'post'" :href="item.post.url"
                      class="group transition hover:-translate-y-0.5 hover:shadow-md">
                    <Card class="h-full overflow-hidden pt-0">
                        <div class="w-full overflow-hidden rounded-t-xl bg-muted" :class="props.thumbAspect">
                            <img v-if="item.post.thumbnail_url" :src="item.post.thumbnail_url" :alt="item.post.title"
                                 class="h-full w-full object-cover transition group-hover:scale-105" loading="lazy">
                        </div>
                        <CardContent class="pb-3">
                            <h3 class="line-clamp-1 font-semibold group-hover:underline">{{ item.post.title }}</h3>
                            <p class="mt-1.5 line-clamp-2 text-sm text-muted-foreground">{{ item.post.excerpt }}</p>
                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                <Badge v-for="cat in item.post.categories.slice(0, 2)" :key="cat.slug" variant="secondary">
                                    {{ cat.name }}
                                </Badge>
                            </div>
                        </CardContent>
                        <div class="flex items-center justify-between border-t px-6 py-2.5 text-xs text-muted-foreground">
                            <span>{{ item.post.author ?? '' }}</span>
                            <span>{{ item.post.published_at ?? '' }}
                                <template v-if="item.post.comments_count !== undefined"> · {{ item.post.comments_count }} 评论</template>
                                <template v-if="item.post.views_count !== undefined"> · {{ item.post.views_count }} 浏览</template>
                            </span>
                        </div>
                    </Card>
                </Link>
                <AdCard v-else :ad="item.ad" layout="grid" :thumb-aspect="props.thumbAspect" />
            </template>
        </div>

        <!-- 网格布局（无卡片广告） -->
        <div v-else-if="current === 'grid'" class="grid grid-cols-1 gap-5" :class="props.gridCols">
            <Link v-for="post in posts" :key="post.id" :href="post.url"
                  class="group transition hover:-translate-y-0.5 hover:shadow-md">
                <Card class="h-full overflow-hidden pt-0">
                    <div class="w-full overflow-hidden rounded-t-xl bg-muted" :class="props.thumbAspect">
                        <img v-if="post.thumbnail_url" :src="post.thumbnail_url" :alt="post.title"
                             class="h-full w-full object-cover transition group-hover:scale-105" loading="lazy">
                    </div>
                    <CardContent class="pb-3">
                        <h3 class="line-clamp-1 font-semibold group-hover:underline">{{ post.title }}</h3>
                        <p class="mt-1.5 line-clamp-2 text-sm text-muted-foreground">{{ post.excerpt }}</p>
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <Badge v-for="cat in post.categories.slice(0, 2)" :key="cat.slug" variant="secondary">
                                {{ cat.name }}
                            </Badge>
                        </div>
                    </CardContent>
                    <div class="flex items-center justify-between border-t px-6 py-2.5 text-xs text-muted-foreground">
                        <span>{{ post.author ?? '' }}</span>
                        <span>{{ post.published_at ?? '' }}
                            <template v-if="post.comments_count !== undefined"> · {{ post.comments_count }} 评论</template>
                            <template v-if="post.views_count !== undefined"> · {{ post.views_count }} 浏览</template>
                        </span>
                    </div>
                </Card>
            </Link>
        </div>

        <!-- 列表布局：左封面 + 右内容（含卡片式广告交错插入） -->
        <div v-else-if="hasCardAds" class="space-y-4">
            <template v-for="(item, idx) in timeline" :key="`${item.kind}-${item.kind === 'post' ? item.post.id : item.ad.id}-${idx}`">
                <Link v-if="item.kind === 'post'" :href="item.post.url"
                      class="group block transition hover:shadow-md">
                    <Card class="flex gap-4 overflow-hidden p-4">
                        <div class="hidden h-28 w-44 shrink-0 overflow-hidden rounded-lg bg-muted sm:block">
                            <img v-if="item.post.thumbnail_url" :src="item.post.thumbnail_url" :alt="item.post.title"
                                 class="h-full w-full object-cover transition group-hover:scale-105" loading="lazy">
                        </div>
                        <div class="min-w-0 flex-1">
                            <h3 class="font-semibold leading-snug group-hover:underline">{{ item.post.title }}</h3>
                            <p class="mt-1.5 line-clamp-2 text-sm text-muted-foreground">{{ item.post.excerpt }}</p>
                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                <Badge v-for="cat in item.post.categories.slice(0, 2)" :key="cat.slug" variant="secondary">
                                    {{ cat.name }}
                                </Badge>
                            </div>
                            <div class="mt-2.5 flex flex-wrap items-center gap-x-3 text-xs text-muted-foreground">
                                <span v-if="item.post.author">{{ item.post.author }}</span>
                                <span>{{ item.post.published_at ?? '' }}</span>
                                <span v-if="item.post.comments_count !== undefined">{{ item.post.comments_count }} 评论</span>
                                <span v-if="item.post.views_count !== undefined">{{ item.post.views_count }} 浏览</span>
                            </div>
                        </div>
                    </Card>
                </Link>
                <AdCard v-else :ad="item.ad" layout="list" />
            </template>
        </div>

        <!-- 列表布局（无卡片广告） -->
        <div v-else class="space-y-4">
            <Link v-for="post in posts" :key="post.id" :href="post.url"
                  class="group block transition hover:shadow-md">
                <Card class="flex gap-4 overflow-hidden p-4">
                    <div class="hidden h-28 w-44 shrink-0 overflow-hidden rounded-lg bg-muted sm:block">
                        <img v-if="post.thumbnail_url" :src="post.thumbnail_url" :alt="post.title"
                             class="h-full w-full object-cover transition group-hover:scale-105" loading="lazy">
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="font-semibold leading-snug group-hover:underline">{{ post.title }}</h3>
                        <p class="mt-1.5 line-clamp-2 text-sm text-muted-foreground">{{ post.excerpt }}</p>
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <Badge v-for="cat in post.categories.slice(0, 2)" :key="cat.slug" variant="secondary">
                                {{ cat.name }}
                            </Badge>
                        </div>
                        <div class="mt-2.5 flex flex-wrap items-center gap-x-3 text-xs text-muted-foreground">
                            <span v-if="post.author">{{ post.author }}</span>
                            <span>{{ post.published_at ?? '' }}</span>
                            <span v-if="post.comments_count !== undefined">{{ post.comments_count }} 评论</span>
                            <span v-if="post.views_count !== undefined">{{ post.views_count }} 浏览</span>
                        </div>
                    </div>
                </Card>
            </Link>
        </div>

        <!-- 空状态 -->
        <Card v-if="posts.length === 0">
            <CardContent class="border-dashed p-12 text-center text-muted-foreground">
                还没有发布任何文章
            </CardContent>
        </Card>
    </div>
</template>
