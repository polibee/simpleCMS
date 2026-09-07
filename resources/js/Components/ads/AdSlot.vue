<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { cn } from '@/lib/utils';
import Card from '@/components/ui/Card.vue';
import CardContent from '@/components/ui/CardContent.vue';

/**
 * 通用广告位（Ads 插件）：按 position 渲染该位置启用的广告。
 * type=text 文本卡 / image 图片超链 / html 自定义 HTML/JS（v-html 不执行 <script>，
 * 挂载后手动重建脚本节点以支持 JS 广告代码）。
 */
export interface AdDto {
    id: number;
    type: 'text' | 'image' | 'combo' | 'card' | 'html';
    title: string | null;
    text: string | null;
    image_url: string | null;
    link_url: string | null;
    html: string | null;
    new_tab: boolean;
}

const props = withDefaults(defineProps<{
    position: string;
    class?: string;
}>(), {});

const page = usePage();

const containerRef = ref<HTMLElement | null>(null);

const positionAds = (): AdDto[] => {
    const map = (page.props.ads as Record<string, AdDto[]>) ?? {};

    return map[props.position] ?? [];
};

const hasAds = (): boolean => positionAds().length > 0;

/** v-html 里的 <script> 不执行：挂载后把脚本节点重建 append（浏览器才会执行）。 */
function runScripts(root: HTMLElement) {
    const scripts = root.querySelectorAll('script');
    scripts.forEach((old) => {
        const s = document.createElement('script');
        for (const attr of Array.from(old.attributes)) {
            s.setAttribute(attr.name, attr.value);
        }
        s.textContent = old.textContent ?? '';
        old.replaceWith(s);
    });
}

function activateHtmlAds() {
    nextTickRun(() => {
        containerRef.value?.querySelectorAll<HTMLElement>('[data-ad-html]').forEach((el) => runScripts(el));
    });
}

function nextTickRun(fn: () => void) {
    requestAnimationFrame(() => fn());
}

let adsStop: (() => void) | null = null;
onMounted(() => {
    adsStop = watch(() => (page.props.ads as any), activateHtmlAds, { deep: true });
    activateHtmlAds();
});
onBeforeUnmount(() => adsStop?.());

const linkAttrs = (ad: AdDto) => ({
    href: ad.link_url ?? '#',
    ...(ad.link_url ? { target: ad.new_tab ? '_blank' : '_self', rel: 'noopener noreferrer nofollow sponsored' } : {}),
});
</script>

<template>
    <div v-if="hasAds()" ref="containerRef" :class="cn('space-y-3', props.class ?? '')">
        <template v-for="ad in positionAds()" :key="ad.id">
            <!-- 文本广告：仅内容 + 广告标识 -->
            <Card v-if="ad.type === 'text'">
                <CardContent class="p-4">
                    <component :is="ad.link_url ? 'a' : 'div'" v-bind="ad.link_url ? linkAttrs(ad) : {}"
                               class="block text-sm">
                        <span class="block leading-relaxed text-muted-foreground">{{ ad.text ?? ad.title }}</span>
                    </component>
                    <span class="mt-2 inline-block rounded bg-muted px-1.5 py-0.5 text-[10px] text-muted-foreground">广告</span>
                </CardContent>
            </Card>

            <!-- 图片广告：仅图片 + 广告标识（title 仅作 alt） -->
            <div v-else-if="ad.type === 'image'" class="relative">
                <component :is="ad.link_url ? 'a' : 'div'" v-bind="ad.link_url ? linkAttrs(ad) : {}" class="block">
                    <img v-if="ad.image_url" :src="ad.image_url" :alt="ad.title ?? '广告'"
                         class="w-full rounded-xl border object-cover shadow-sm" loading="lazy">
                </component>
                <span class="absolute right-2 top-2 rounded bg-black/50 px-1.5 py-0.5 text-[10px] text-white">广告</span>
            </div>

            <!-- 图文结合广告 -->
            <Card v-else-if="ad.type === 'combo'" class="relative overflow-hidden pt-0">
                <component :is="ad.link_url ? 'a' : 'div'" v-bind="ad.link_url ? linkAttrs(ad) : {}" class="block">
                    <img v-if="ad.image_url" :src="ad.image_url" :alt="ad.title ?? '广告'"
                         class="aspect-video w-full object-cover" loading="lazy">
                    <div class="p-4">
                        <p v-if="ad.title" class="font-semibold text-foreground">{{ ad.title }}</p>
                        <p class="mt-1 text-sm leading-relaxed text-muted-foreground">{{ ad.text }}</p>
                    </div>
                </component>
                <span class="absolute right-2 top-2 rounded bg-black/50 px-1.5 py-0.5 text-[10px] text-white">广告</span>
            </Card>

            <!-- 文章卡片式广告（图 + 标题 + 文案，与站内文章卡片同风格） -->
            <Card v-else-if="ad.type === 'card'" class="relative overflow-hidden pt-0">
                <component :is="ad.link_url ? 'a' : 'div'" v-bind="ad.link_url ? linkAttrs(ad) : {}" class="block">
                    <img v-if="ad.image_url" :src="ad.image_url" :alt="ad.title ?? '广告'"
                         class="aspect-video w-full object-cover" loading="lazy">
                    <div class="p-4">
                        <p v-if="ad.title" class="font-semibold text-foreground">{{ ad.title }}</p>
                        <p v-if="ad.text" class="mt-1 text-sm leading-relaxed text-muted-foreground">{{ ad.text }}</p>
                    </div>
                </component>
                <span class="absolute right-2 top-2 rounded bg-muted px-1.5 py-0.5 text-[10px] text-muted-foreground">赞助内容</span>
            </Card>

            <!-- HTML/JS 广告代码（管理端可信输入） -->
            <div v-else-if="ad.type === 'html'" class="relative">
                <div data-ad-html v-html="ad.html ?? ''" />
                <span class="mt-1 inline-block rounded bg-muted px-1.5 py-0.5 text-[10px] text-muted-foreground">广告</span>
            </div>
        </template>
    </div>
</template>
