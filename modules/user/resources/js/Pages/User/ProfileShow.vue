<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Components/AppLayout.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import CardContent from '@/components/ui/CardContent.vue';

interface ProfileUser {
    id: number;
    name: string;
    initial: string;
    joined_at: string | null;
    website: string | null;
    bio_markdown: string | null;
    bio_html: string | null;
    signature: string | null;
    donation_url: string | null;
    donation_text: string | null;
}

interface ArticleCard {
    id: number;
    title: string;
    url: string;
    excerpt: string;
    published_at: string | null;
    views_count: number;
    cover_url: string | null;
    categories: { name: string; slug: string }[];
}

const props = defineProps<{
    profileUser: ProfileUser;
    stats: { posts: number; views: number };
    posts: ArticleCard[];
}>();

const page = usePage();
const viewer = computed(() => (page.props.auth as any)?.user);
const isSelf = computed(() => viewer.value?.id === props.profileUser.id);
const canAuthor = computed(() => !!(page.props.auth as any)?.can_author);

const statItems = computed(() => [
    { label: '发布文章', value: props.stats.posts },
    { label: '累计浏览', value: props.stats.views },
]);
</script>

<template>
    <Head :title="profileUser.name" />
    <AppLayout>
        <!-- 简介卡片：渐变封面 + 头像 + 元信息 + Markdown 自我介绍 -->
        <Card class="overflow-hidden pt-0">
            <div class="h-28 w-full bg-gradient-to-r from-indigo-500/80 via-purple-500/70 to-pink-500/60" />

            <CardContent class="-mt-10 pb-6">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div class="flex items-end gap-4">
                        <div class="flex h-20 w-20 items-center justify-center rounded-full border-4 border-card bg-primary text-3xl font-bold text-primary-foreground shadow">
                            {{ profileUser.initial }}
                        </div>
                        <div class="pb-1">
                            <h1 class="text-2xl font-semibold tracking-tight">{{ profileUser.name }}</h1>
                            <p v-if="profileUser.joined_at" class="mt-0.5 text-sm text-muted-foreground">
                                🗓 {{ profileUser.joined_at }} 加入
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 pb-1">
                        <!-- 创作者自定义捐赠按钮（优先于站点默认） -->
                        <Button v-if="profileUser.donation_url" as="a" :href="profileUser.donation_url"
                                target="_blank" rel="noopener noreferrer nofollow" size="sm">
                            ♥ {{ profileUser.donation_text || '支持作者' }}
                        </Button>
                        <Button v-if="isSelf && canAuthor" variant="outline" size="sm" as="a" href="/studio/settings">
                            编辑资料
                        </Button>
                    </div>
                </div>

                <!-- 统计 -->
                <div class="mt-5 flex gap-2">
                    <Badge v-for="s in statItems" :key="s.label" variant="secondary" class="px-3 py-1 text-xs">
                        {{ s.label }} · {{ s.value }}
                    </Badge>
                </div>

                <!-- 关于（Markdown 渲染结果，服务端已转义） -->
                <div v-if="profileUser.bio_html" class="prose prose-zinc dark:prose-invert mt-5 max-w-none text-sm leading-relaxed prose-headings:text-base"
                     v-html="profileUser.bio_html" />
                <p v-else class="mt-5 rounded-lg bg-muted px-4 py-3 text-sm text-muted-foreground">
                    这位用户还没有填写个人简介。
                    <Link v-if="isSelf && canAuthor" href="/studio/settings" class="text-primary underline-offset-2 hover:underline">去创作中心完善 →</Link>
                </p>

                <p v-if="profileUser.signature" class="mt-4 border-t pt-4 text-sm italic text-muted-foreground">
                    ✍ {{ profileUser.signature }}
                </p>
            </CardContent>
        </Card>

        <!-- 发布的文章 -->
        <section class="mt-8">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold tracking-tight">发布的文章</h2>
                <span class="text-sm text-muted-foreground">{{ stats.posts }} 篇</span>
            </div>

            <Card v-if="posts.length === 0">
                <CardContent class="py-10 text-center text-sm text-muted-foreground">暂无公开文章</CardContent>
            </Card>

            <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <Link v-for="p in posts" :key="p.id" :href="p.url"
                      class="group transition hover:-translate-y-0.5 hover:shadow-md">
                    <Card class="h-full overflow-hidden pt-0">
                        <div class="aspect-video w-full overflow-hidden rounded-t-xl bg-muted">
                            <img v-if="p.cover_url" :src="p.cover_url" :alt="p.title"
                                 class="h-full w-full object-cover transition group-hover:scale-105" loading="lazy">
                        </div>
                        <CardContent class="pb-3">
                            <h3 class="font-medium leading-snug group-hover:underline">{{ p.title }}</h3>
                            <p class="mt-1.5 line-clamp-2 text-xs text-muted-foreground">{{ p.excerpt }}</p>
                            <div class="mt-2.5 flex flex-wrap gap-1">
                                <span v-for="c in p.categories.slice(0, 2)" :key="c.slug"
                                      class="rounded-full bg-secondary px-2 py-0.5 text-[11px] text-secondary-foreground">{{ c.name }}</span>
                            </div>
                        </CardContent>
                        <div class="flex items-center justify-between px-6 pb-4 text-[11px] text-muted-foreground">
                            <span>{{ p.published_at ?? '待发布' }}</span>
                            <span>{{ p.views_count }} 浏览</span>
                        </div>
                    </Card>
                </Link>
            </div>
        </section>
    </AppLayout>
</template>
