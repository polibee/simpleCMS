<script setup lang="ts">
import { computed, type Component } from 'vue';
import CheckinCard from '@/components/sidebar/CheckinCard.vue';
import CategoriesCard from '@/components/sidebar/CategoriesCard.vue';
import LatestPostsCard from '@/components/sidebar/LatestPostsCard.vue';
import UserCenterCard from '@/components/sidebar/UserCenterCard.vue';
import AuthorCenterCard from '@/components/sidebar/AuthorCenterCard.vue';
import TextLinkCard from '@/components/sidebar/TextLinkCard.vue';
import ImageLinkCard from '@/components/sidebar/ImageLinkCard.vue';
import HtmlCard from '@/components/sidebar/HtmlCard.vue';
import DonationCard from '@/components/sidebar/DonationCard.vue';
import AdSlotCard from '@/components/sidebar/cards/ad_slot.vue';

/**
 * 系统级侧边栏渲染器（ADR-010）。
 *
 * 内置类型 → 组件映射；主题可通过 themes/{id}/vue/cards/{type}.vue 同名覆盖
 * （覆盖映射由当前主题注入 window.__THEME_CARDS__）。
 */
const builtinComponents: Record<string, Component> = {
    checkin: CheckinCard,
    categories: CategoriesCard,
    latest_posts: LatestPostsCard,
    user_center: UserCenterCard,
    author_center: AuthorCenterCard,
    text_link: TextLinkCard,
    image_link: ImageLinkCard,
    html: HtmlCard,
    donation: DonationCard,
    ad_slot: AdSlotCard,
};

interface SidebarCard {
    id: number;
    type: string;
    data: Record<string, any>;
    author?: Record<string, any>;
}

interface CategoryItem {
    id: number;
    name: string;
    slug: string;
    posts_count?: number;
}

interface LatestPost {
    id: number;
    title: string;
    slug: string;
    published_at: string | null;
    author: string | null;
}

const props = defineProps<{
    /** 引擎下发的卡片实例（已按 sort 排序、仅启用） */
    cards?: SidebarCard[];
    /** 页面级数据：供 categories / latest_posts 数据型卡片消费 */
    categories?: CategoryItem[];
    latestPosts?: LatestPost[];
}>();

// 主题覆盖：window.__THEME_CARDS__ 由当前主题注入（type → 组件）
const themeComponents = (typeof window !== 'undefined' ? (window as any).__THEME_CARDS__ : null) ?? {};

function componentFor(type: string): Component | undefined {
    return themeComponents[type] ?? builtinComponents[type];
}

const resolved = computed(() =>
    (props.cards ?? [])
        .filter((card) => componentFor(card.type))
        .map((card) => ({
            ...card,
            component: componentFor(card.type)!,
            limit: Number(card.data?.limit ?? 5),
        })),
);
</script>

<template>
    <div class="space-y-4">
        <template v-for="card in resolved" :key="`${card.type}-${card.id}`">
            <!-- 数据驱动型卡片：从页面数据切片 -->
            <CategoriesCard v-if="card.type === 'categories'"
                            :title="card.data?.cardTitle"
                            :categories="(props.categories ?? []).slice(0, card.limit)" />

            <LatestPostsCard v-else-if="card.type === 'latest_posts'"
                             :title="card.data?.cardTitle"
                             :posts="(props.latestPosts ?? []).slice(0, card.limit)" />

            <!-- 作者中心：详情页注入 author 数据；无数据则不渲染 -->
            <AuthorCenterCard v-else-if="card.type === 'author_center' && card.author"
                              :title="card.data?.cardTitle"
                              :author="card.author" />

            <!-- 其余类型：配置自包含，直接渲染（含主题覆盖后的自定义组件） -->
            <component v-else :is="card.component" :title="card.data?.cardTitle" :data="card.data" />
        </template>
    </div>
</template>
