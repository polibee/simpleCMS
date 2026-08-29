<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AuthorLayout from './AuthorLayout.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import CardContent from '@/components/ui/CardContent.vue';

interface MyPost {
    id: number;
    title: string;
    slug: string;
    status: string;
    views_count: number;
    comments_count: number;
    published_at: string | null;
    created_at: string;
    url: string;
    categories: { id: number; name: string }[];
}

const props = defineProps<{ posts: MyPost[] }>();
const page = usePage();
const flash = computed(() => (page.props.flash as any) ?? {});

const statusBadge = (status: string) =>
    status === 'published'
        ? { text: '已发布', class: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' }
        : { text: '草稿', class: 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300' };
</script>

<template>
    <Head title="我的文章" />
    <AuthorLayout>
        <div class="mb-5 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold">我的文章</h1>
                <p class="mt-0.5 text-sm text-muted-foreground">共 {{ posts.length }} 篇（含草稿）</p>
            </div>
            <Button as="a" href="/studio/posts/create">+ 写新文章</Button>
        </div>

        <div v-if="flash.success"
             class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300">
            {{ flash.success }}
        </div>

        <Card v-if="posts.length === 0">
            <CardContent class="py-12 text-center text-sm text-muted-foreground">
                还没有文章，点击右上角「写新文章」开始创作。
            </CardContent>
        </Card>

        <div v-else class="space-y-3">
            <Card v-for="post in posts" :key="post.id">
                <CardContent class="flex flex-col gap-2 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="truncate font-medium">{{ post.title }}</span>
                            <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium" :class="statusBadge(post.status).class">
                                {{ statusBadge(post.status).text }}
                            </span>
                        </div>
                        <p class="mt-1 truncate text-xs text-muted-foreground">
                            创建于 {{ post.created_at }}
                            <template v-if="post.published_at"> · 发布于 {{ post.published_at.slice(0, 10) }}</template>
                            · {{ post.views_count }} 浏览 · {{ post.comments_count }} 评论
                            <template v-if="post.categories.length"> · {{ post.categories.map(c => c.name).join(' / ') }}</template>
                        </p>
                    </div>

                    <div class="flex shrink-0 gap-2">
                        <Button v-if="post.status === 'published'" variant="outline" size="sm" as="a" :href="post.url">查看</Button>
                        <Button variant="secondary" size="sm" as="a" :href="`/studio/posts/${post.id}/edit`">编辑</Button>
                        <Link :href="`/studio/posts/${post.id}`" method="delete"
                              class="inline-flex h-8 items-center rounded-md px-3 text-xs font-medium text-destructive transition hover:bg-destructive/10"
                              @click="(e: MouseEvent) => { if (!confirm(`确定删除「${post.title}」？此操作不可恢复。`)) e.preventDefault(); }">
                            删除
                        </Link>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AuthorLayout>
</template>
