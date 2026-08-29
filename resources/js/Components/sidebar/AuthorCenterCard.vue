<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Card from '@/components/ui/Card.vue';
import CardContent from '@/components/ui/CardContent.vue';

interface AuthorData {
    name: string | null;
    id: number | null;
    bio: string | null;
    joined_at?: string | null;
    articles_count?: number;
}

const props = defineProps<{
    title?: string;
    author?: AuthorData | null;
}>();
</script>

<template>
    <Card v-if="props.author?.name">
        <CardContent class="p-5 text-center">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-primary text-xl font-semibold text-primary-foreground">
                {{ (props.author.name ?? '?').slice(0, 1) }}
            </div>
            <h3 class="mt-3 text-sm font-semibold">{{ props.author.name }}</h3>
            <p class="mt-1 line-clamp-3 text-xs leading-relaxed text-muted-foreground">
                {{ props.author.bio ?? '这位作者还没有填写简介。' }}
            </p>

            <div class="mt-4 grid grid-cols-2 gap-2 border-t pt-4 text-center">
                <div>
                    <p class="text-lg font-bold">{{ props.author.articles_count ?? 0 }}</p>
                    <p class="text-xs text-muted-foreground">文章</p>
                </div>
                <div>
                    <p class="text-lg font-bold">—</p>
                    <p class="text-xs text-muted-foreground">加入于</p>
                    <p class="-mt-1 text-xs">{{ props.author.joined_at ?? '' }}</p>
                </div>
            </div>

            <Link v-if="props.author.id" :href="`/users/${props.author.id}`"
                  class="mt-3 block rounded-md border py-1.5 text-sm transition hover:bg-muted">
                查看主页
            </Link>
        </CardContent>
    </Card>
</template>
