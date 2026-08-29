<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Card from '@/components/ui/Card.vue';
import CardContent from '@/components/ui/CardContent.vue';
import AdSlot from '@/components/ads/AdSlot.vue';

/**
 * 侧边栏广告卡位卡片（ads 插件注册的 ad_slot 类型）。
 * 渲染"广告管理"投放到 sidebar 位置的广告。
 */
const props = defineProps<{
    title?: string;
    data?: Record<string, any>;
}>();

const page = usePage();
const count = computed(() => (((page.props.ads as any)?.sidebar ?? [])).length);
</script>

<template>
    <div v-if="count > 0">
        <AdSlot position="sidebar" />
    </div>
</template>
