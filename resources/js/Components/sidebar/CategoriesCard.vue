<script setup lang="ts">
import Card from '@/components/ui/Card.vue';
import CardContent from '@/components/ui/CardContent.vue';

interface CategoryItem {
    id: number;
    name: string;
    slug: string;
    posts_count?: number;
}

const props = defineProps<{
    title?: string;
    categories?: CategoryItem[];
}>();
</script>

<template>
    <Card>
        <CardContent class="p-5">
            <h3 class="text-sm font-semibold text-muted-foreground">{{ props.title ?? '分类目录' }}</h3>
            <ul v-if="props.categories?.length" class="mt-3 space-y-1">
                <li v-for="cat in props.categories" :key="cat.id">
                    <a :href="`/c/${cat.slug}`"
                       class="flex items-center justify-between rounded-md px-2 py-1.5 text-sm transition hover:bg-muted">
                        <span>{{ cat.name }}</span>
                        <span class="text-xs text-muted-foreground">{{ cat.posts_count ?? 0 }}</span>
                    </a>
                </li>
            </ul>
            <p v-else class="mt-3 px-2 py-1.5 text-sm text-muted-foreground">暂无分类</p>
        </CardContent>
    </Card>
</template>
