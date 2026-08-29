<script setup lang="ts">
import Card from '@/components/ui/Card.vue';
import CardContent from '@/components/ui/CardContent.vue';

/** 侧边栏捐赠卡片（donation 类型，链接在后台卡片实例里配置）。 */
const props = defineProps<{
    title?: string;
    data?: { url?: string; button_text?: string; description?: string };
}>();
</script>

<template>
    <Card class="overflow-hidden pt-0">
        <div class="bg-gradient-to-br from-[#6C5CE7] to-[#A855F7] p-5 text-center text-white">
            <p class="text-sm font-semibold">{{ props.title ?? '支持创作者' }}</p>
            <p class="mt-1 text-xs text-white/80">{{ props.data?.description ?? '你的支持是创作的动力' }}</p>
            <a :href="props.data?.url ?? '#'" target="_blank" rel="noopener"
               class="mt-3 inline-block rounded-lg bg-white px-4 py-1.5 text-xs font-semibold text-[#6C5CE7] transition hover:scale-[1.03]">
                {{ props.data?.button_text ?? '支持作者' }} ♥
            </a>
        </div>
    </Card>
</template>
