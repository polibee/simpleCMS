<script setup lang="ts">
/**
 * 文章末尾捐赠按钮（后台"外观→捐赠按钮"配置链接；Glowing CTA 风格）。
 * 链接为外链（donatr.ee 等），仅 <a> 跳转，无服务端请求。
 */
const props = defineProps<{
    url: string;
    text?: string;
}>();
</script>

<template>
    <div class="mt-8 rounded-2xl bg-gradient-to-br from-[#6C5CE7] to-[#A855F7] p-6 text-center shadow-[0_0_24px_rgba(108,92,231,0.35)]">
        <p class="text-sm font-semibold text-white">喜欢这篇文章？</p>
        <p class="mt-1 text-xs text-white/80">你的支持是创作者持续输出的动力</p>
        <a :href="props.url" target="_blank" rel="noopener"
           class="mt-4 inline-block rounded-lg bg-white px-6 py-2 text-sm font-semibold text-[#6C5CE7] transition hover:scale-[1.03]">
            {{ props.text ?? '支持作者' }} ♥
        </a>
    </div>
</template>
