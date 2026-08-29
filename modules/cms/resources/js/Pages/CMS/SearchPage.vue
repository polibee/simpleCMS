<script setup lang="ts">
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Components/AppLayout.vue';
import Badge from '@/components/ui/Badge.vue';
import Card from '@/components/ui/Card.vue';
import CardContent from '@/components/ui/CardContent.vue';
import Input from '@/components/ui/Input.vue';

interface SearchResult {
    id: number;
    title: string;
    url: string;
    excerpt: string;
    published_at: string | null;
    author: string | null;
    categories: { name: string; slug: string }[];
}

const props = defineProps<{
    q: string;
    posts: SearchResult[];
}>();

const page = usePage();
const siteName = computed(() => (page.props.site as any)?.name ?? 'CMSForum');
</script>

<template>
    <Head :title="`搜索：${q} · ${siteName}`" />
    <AppLayout>
        <div class="mb-6">
            <h1 class="text-2xl font-semibold tracking-tight">搜索结果</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                关键词「{{ q }}」— 共 {{ posts.length }} 条结果
            </p>
        </div>

        <!-- 搜索框 -->
        <div class="mb-6">
            <form method="GET" action="/search" class="flex gap-2">
                <input type="text" name="q" :value="q" placeholder="输入关键词搜索文章…"
                       class="w-full rounded-lg border border-input bg-background px-4 py-2.5 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" />
                <button type="submit"
                        class="shrink-0 rounded-lg bg-primary px-5 py-2.5 text-sm font-medium text-primary-foreground shadow transition hover:bg-primary/90">
                    搜索
                </button>
            </form>
        </div>

        <Card v-if="!posts.length">
            <CardContent class="py-12 text-center text-sm text-muted-foreground">
                没有找到相关文章，试试其他关键词。
            </CardContent>
        </Card>

        <div v-else class="space-y-4">
            <component :is="'a'" v-for="p in posts" :key="p.id" :href="p.url"
                       class="group block transition hover:shadow-md">
                <Card class="p-4">
                    <div class="flex items-start justify-between gap-2">
                        <h2 class="font-semibold leading-snug group-hover:underline">{{ p.title }}</h2>
                        <span class="shrink-0 text-xs text-muted-foreground">{{ p.published_at }}</span>
                    </div>
                    <p class="mt-1.5 text-sm text-muted-foreground">{{ p.excerpt }}</p>
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        <Badge v-for="c in p.categories.slice(0, 2)" :key="c.slug" variant="secondary">{{ c.name }}</Badge>
                        <span class="text-xs text-muted-foreground">{{ p.author }}</span>
                    </div>
                </Card>
            </component>
        </div>
    </AppLayout>
</template>
