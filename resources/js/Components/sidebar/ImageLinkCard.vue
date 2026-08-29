<script setup lang="ts">
import Card from '@/components/ui/Card.vue';
import CardContent from '@/components/ui/CardContent.vue';

const props = defineProps<{
    title?: string;
    data?: { image_url?: string; url?: string; alt?: string; new_tab?: boolean };
}>();
</script>

<template>
    <Card class="overflow-hidden pt-0">
        <a :href="props.data?.url ?? '#'"
           :target="props.data?.new_tab ? '_blank' : undefined"
           :rel="props.data?.new_tab ? 'noopener' : undefined">
            <img v-if="props.data?.image_url" :src="props.data.image_url" :alt="props.data?.alt ?? ''"
                 loading="lazy" class="w-full object-cover transition duration-300 hover:scale-[1.02]">
        </a>
    </Card>
</template>
