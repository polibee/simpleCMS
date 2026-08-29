<script setup lang="ts">
import Card from '@/components/ui/Card.vue';
import CardContent from '@/components/ui/CardContent.vue';

/**
 * 代码卡片：后台仅超级管理员可配置（权限门控），
 * 内容为可信管理员录入的 HTML/JS，原样输出。
 */
const props = defineProps<{
    title?: string;
    data?: { html?: string };
}>();
</script>

<template>
    <Card>
        <CardContent class="p-0" :class="props.title ? '' : 'p-0'">
            <h3 v-if="props.title" class="p-5 pb-3 text-sm font-semibold text-muted-foreground">{{ props.title }}</h3>
            <div class="overflow-hidden px-5" :class="props.title ? 'pb-5' : 'py-5'" v-html="props.data?.html ?? ''" />
        </CardContent>
    </Card>
</template>
