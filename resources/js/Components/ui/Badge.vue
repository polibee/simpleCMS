<script setup lang="ts">
import { cva, type VariantProps } from 'class-variance-authority';
import { cn } from '@/lib/utils';

const badgeVariants = cva(
    'inline-flex items-center rounded-md border px-2.5 py-0.5 text-xs font-semibold transition-colors focus:outline-none',
    {
        variants: {
            variant: {
                default: 'border-transparent bg-primary text-primary-foreground shadow',
                secondary: 'border-transparent bg-secondary text-secondary-foreground',
                destructive: 'border-transparent bg-destructive text-destructive-foreground shadow',
                outline: 'text-foreground',
            },
        },
        defaultVariants: {
            variant: 'default',
        },
    },
);

interface Props {
    variant?: VariantProps<typeof badgeVariants>['variant'];
    class?: string;
}

const props = defineProps<Props>();
</script>

<template>
    <span :class="cn(badgeVariants({ variant: props.variant }), props.class ?? '')">
        <slot />
    </span>
</template>
