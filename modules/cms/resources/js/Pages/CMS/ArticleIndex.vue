<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/Components/AppLayout.vue';
import Button from '@/components/ui/Button.vue';
import SidebarRenderer from '@/components/sidebar/SidebarRenderer.vue';
import ArticleStream from '@/components/cms/ArticleStream.vue';

const props = defineProps<{
    posts: { data: any[]; current_page: number; last_page: number };
    layout: 'grid' | 'list';
    listUrl?: string;
    sidebarCards?: { id: number; type: string; data: Record<string, any> }[];
    categories?: { id: number; name: string; slug: string; posts_count?: number }[];
    latestPosts?: { id: number; title: string; slug: string; published_at: string | null; author: string | null }[];
}>();

const page = usePage();
// 列表地址：全局共享 cms.listUrl（固定链接可配置）；页面级 listUrl 仅作覆盖
const listUrl = computed(
    () => props.listUrl ?? (page.props.cms as any)?.listUrl ?? '/articles',
);
</script>

<template>
    <Head title="文章" />
    <AppLayout>
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-2xl font-semibold tracking-tight">文章</h1>
        </div>

        <!-- 文章流：网格/列表切换（共享组件，列表布局 = 左封面右内容） -->
        <ArticleStream :posts="props.posts.data" :layout="props.layout" :list-url="listUrl" />

        <!-- 分页 -->
        <nav v-if="props.posts.last_page > 1" class="mt-8 flex items-center justify-center gap-1.5">
            <Button v-for="p in props.posts.last_page" :key="p" as="a" :href="listUrl"
                    :data="{ page: p, layout: props.layout }" preserve-state
                    :variant="p === props.posts.current_page ? 'default' : 'outline'" size="sm">{{ p }}</Button>
        </nav>

        <!-- 侧边栏：系统级 Sidebar 引擎下发（后台"侧边栏管理"可配置） -->
        <template #sidebar>
            <SidebarRenderer :cards="props.sidebarCards ?? []"
                             :categories="props.categories ?? []"
                             :latest-posts="props.latestPosts ?? []" />
        </template>
    </AppLayout>
</template>
