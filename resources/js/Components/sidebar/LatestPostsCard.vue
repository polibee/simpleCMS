<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Card from '@/components/ui/Card.vue';
import CardContent from '@/components/ui/CardContent.vue';

interface LatestPost {
    id: number;
    title: string;
    slug: string;
    published_at: string | null;
    author: string | null;
}

const props = defineProps<{
    title?: string;
    posts?: LatestPost[];
}>();
</script>

<template>
    <Card>
        <CardContent class="p-5">
            <h3 class="text-sm font-semibold text-muted-foreground">{{ props.title ?? '最新文章' }}</h3>
            <ul class="mt-3 space-y-3">
                <li v-for="post in props.posts ?? []" :key="post.id">
                    <Link :href="post.url"
                          class="line-clamp-2 text-sm font-medium leading-snug transition hover:underline">
                        {{ post.title }}
                    </Link>
                    <p class="mt-0.5 text-xs text-muted-foreground">{{ post.published_at }}</p>
                </li>
            </ul>
        </CardContent>
    </Card>
</template>
