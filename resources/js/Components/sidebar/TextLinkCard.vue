<script setup lang="ts">
import Card from '@/components/ui/Card.vue';
import CardContent from '@/components/ui/CardContent.vue';
import Button from '@/components/ui/Button.vue';

const props = defineProps<{
    title?: string;
    data?: { body?: string; url?: string; button_text?: string; new_tab?: boolean };
}>();
</script>

<template>
    <Card>
        <CardContent class="p-5">
            <h3 class="text-sm font-semibold text-muted-foreground">{{ props.title ?? '推荐' }}</h3>
            <p v-if="props.data?.body" class="mt-2 text-sm leading-relaxed">{{ props.data.body }}</p>
            <Button v-if="props.data?.url" size="sm" as="a" :href="props.data.url"
                    :target="props.data.new_tab ? '_blank' : undefined"
                    :rel="props.data.new_tab ? 'noopener' : undefined"
                    class="mt-3 w-full">
                {{ props.data.button_text ?? '查看详情' }}
            </Button>
        </CardContent>
    </Card>
</template>
