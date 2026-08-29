<script setup lang="ts">
import { usePage, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Card from '@/components/ui/Card.vue';
import CardContent from '@/components/ui/CardContent.vue';
import Button from '@/components/ui/Button.vue';

const page = usePage();
const wallet = computed(() => page.props.wallet);
const user = computed(() => page.props.auth?.user);

const loading = ref(false);
const message = ref<string | null>(null);

async function checkin() {
    if (loading.value) return;
    loading.value = true;
    try {
        await new Promise<void>((resolve) => {
            router.post('/quest/checkin', {}, {
                preserveScroll: true,
                onFinish: () => resolve(),
            });
        });
        message.value = '签到完成';
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <Card v-if="wallet && user">
        <CardContent class="p-5">
            <h3 class="text-sm font-semibold text-muted-foreground">每日签到</h3>

            <div class="mt-3 flex items-center justify-between">
                <div>
                    <p class="text-2xl font-bold tracking-tight">{{ wallet.gold }}</p>
                    <p class="text-xs text-muted-foreground">金币余额</p>
                </div>

                <Button v-if="!wallet.checked_today" size="sm" :disabled="loading" @click="checkin">
                    {{ loading ? '签到中…' : '签到 +5' }}
                </Button>
                <span v-else class="inline-flex items-center gap-1 rounded-md bg-emerald-500/10 px-2.5 py-1 text-sm font-medium text-emerald-600">
                    ✓ 已签到
                </span>
            </div>

            <p v-if="message" class="mt-2 text-xs text-muted-foreground">{{ message }}</p>
        </CardContent>
    </Card>
</template>
