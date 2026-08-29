<script setup lang="ts">
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Components/AppLayout.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import SidebarRenderer from '@/components/sidebar/SidebarRenderer.vue';
import ArticleStream from '@/components/cms/ArticleStream.vue';

interface Post {
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

const props = defineProps<{
    category: { id: number; name: string; slug: string; description: string | null };
    posts: { data: Post[]; current_page: number; last_page: number };
    layout: 'grid' | 'list';
    sidebarCards?: { id: number; type: string; data: Record<string, any> }[];
    categories?: { id: number; name: string; slug: string; posts_count?: number }[];
    latestPosts?: { id: number; title: string; slug: string; published_at: string | null; author: string | null }[];
}>();

const page = usePage();
</script>

<template>
    <Head :title="`${props.category.name} · 分类文章`" />
    <AppLayout>
        <div class="mb-6">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-2xl font-semibold tracking-tight">{{ props.category.name }}</h1>
                <Badge variant="secondary">{{ props.posts.total ?? props.posts.data.length }} 篇</Badge>
            </div>
            <p v-if="props.category.description" class="mt-1 text-sm text-muted-foreground">
                {{ props.category.description }}
            </p>
        </div>

        <ArticleStream :posts="props.posts.data" :layout="props.layout" />

        <!-- 分页 -->
        <nav v-if="props.posts.last_page > 1" class="mt-8 flex items-center justify-center gap-1.5">
            <Button v-for="p in props.posts.last_page" :key="p" as="a"
                    :href="`/c/${props.category.slug}?page=${p}&layout=${props.layout}`"
                    :variant="p === props.posts.current_page ? 'default' : 'outline'" size="sm">{{ p }}</Button>
        </nav>

        <template #sidebar>
            <SidebarRenderer :cards="props.sidebarCards ?? []"
                             :categories="props.categories ?? []"
                             :latest-posts="props.latestPosts ?? []" />
        </template>
    </AppLayout>
</template>
