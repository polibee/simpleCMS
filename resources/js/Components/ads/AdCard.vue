<script setup lang="ts">
import { computed } from 'vue';
import Badge from '@/components/ui/Badge.vue';

/**
 * 文章卡片式广告（ads 插件 type=card）：
 * 外观与文章卡片一致（grid 卡 / list 行两种形态，随当前布局自适应），
 * 图片 object-cover 自动裁剪为卡片比例，右上角/行内"广告"标识。
 */
const props = withDefaults(defineProps<{
    ad: {
        id: number;
        title: string | null;
        text: string | null;
        image_url: string | null;
        link_url: string | null;
        new_tab: boolean;
    };
    /** grid=网格卡片形态 / list=列表行形态（跟随文章流当前布局） */
    layout?: 'grid' | 'list';
    thumbAspect?: string;
}>(), {
    layout: 'grid',
    thumbAspect: 'aspect-video',
});

const linkAttrs = computed(() => props.ad.link_url
    ? {
            href: props.ad.link_url,
            target: props.ad.new_tab ? '_blank' : '_self',
            rel: 'noopener noreferrer nofollow sponsored',
        }
    : { href: 'javascript:;' });
</script>

<template>
    <!-- 网格卡片形态 -->
    <component :is="ad.link_url ? 'a' : 'div'" v-bind="linkAttrs"
               v-if="layout === 'grid'"
               class="group relative block transition hover:-translate-y-0.5 hover:shadow-md">
        <div class="h-full overflow-hidden rounded-xl border bg-card pt-0 text-card-foreground shadow-sm">
            <div class="w-full overflow-hidden rounded-t-xl bg-muted" :class="thumbAspect">
                <img v-if="ad.image_url" :src="ad.image_url" :alt="ad.title ?? '广告'"
                     class="h-full w-full object-cover transition group-hover:scale-105" loading="lazy">
            </div>
            <div class="p-4 pb-3">
                <h3 class="line-clamp-1 font-semibold group-hover:underline">{{ ad.title ?? '推荐' }}</h3>
                <p class="mt-1.5 line-clamp-2 text-sm text-muted-foreground">{{ ad.text }}</p>
            </div>
            <div class="flex items-center justify-between border-t px-4 py-2.5 text-xs text-muted-foreground">
                <span>广告 · 推荐阅读</span>
                <span class="text-primary group-hover:underline">查看详情 →</span>
            </div>
        </div>
    </component>

    <!-- 列表行形态 -->
    <component :is="ad.link_url ? 'a' : 'div'" v-bind="linkAttrs"
               v-else
               class="group relative block transition hover:shadow-md">
        <div class="flex gap-4 overflow-hidden rounded-xl border bg-card p-4 text-card-foreground shadow-sm">
            <div class="hidden h-28 w-44 shrink-0 overflow-hidden rounded-lg bg-muted sm:block">
                <img v-if="ad.image_url" :src="ad.image_url" :alt="ad.title ?? '广告'"
                     class="h-full w-full object-cover transition group-hover:scale-105" loading="lazy">
            </div>
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                    <h3 class="truncate font-semibold group-hover:underline">{{ ad.title ?? '推荐' }}</h3>
                    <Badge variant="secondary">广告</Badge>
                </div>
                <p class="mt-1.5 line-clamp-2 text-sm text-muted-foreground">{{ ad.text }}</p>
                <p class="mt-2.5 text-xs text-muted-foreground">广告 · 推荐阅读 <span class="text-primary group-hover:underline">查看详情 →</span></p>
            </div>
        </div>
    </component>
</template>
