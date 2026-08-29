<script setup lang="ts">
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Components/AppLayout.vue';
import Card from '@/components/ui/Card.vue';
import CardContent from '@/components/ui/CardContent.vue';

/** 站点单页（后台"单页管理"维护，Markdown 安全渲染）。 */
const props = defineProps<{
    page: { slug: string; title: string; html: string | null; updated_at: string | null };
    effectiveDate: string | null;
}>();

const inertiaPage = usePage();
const siteName = computed(() => (inertiaPage.props.site as any)?.name ?? 'CMSForum');
</script>

<template>
    <Head :title="props.page.title" />
    <AppLayout>
        <Card>
            <CardContent class="p-6 sm:p-8">
                <div v-if="props.page.slug === 'privacy' && effectiveDate"
                     class="rounded-lg bg-muted px-4 py-2 text-sm text-muted-foreground">
                    本政策自 <span class="font-semibold text-foreground">{{ effectiveDate }}</span> 起生效；
                    修改隐私政策后请同步更新此日期（后台 → 站点设置 → 隐私与 Cookie）。
                </div>

                <h1 class="mt-6 text-2xl font-semibold tracking-tight">{{ props.page.title }}</h1>
                <p class="mt-1 text-sm text-muted-foreground">{{ siteName }}</p>

                <div v-if="props.page.html" class="prose prose-zinc dark:prose-invert mt-6 max-w-none text-sm leading-relaxed"
                     v-html="props.page.html" />
                <p v-else class="mt-6 rounded-lg bg-muted px-4 py-3 text-sm text-muted-foreground">
                    页面内容为空，请在后台「单页管理」中编辑。
                </p>
            </CardContent>
        </Card>
    </AppLayout>
</template>
