<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Components/AppLayout.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import CardContent from '@/components/ui/CardContent.vue';

interface MyCode {
    id: number;
    code: string;
    source: string;
    status: string;
    gold_spent: number | null;
    paid_amount: number | null;
    expires_at: string | null;
    created_at: string;
}

const props = defineProps<{
    codes: MyCode[];
    options: {
        allowGold: boolean;
        goldPrice: number;
        allowCrypto: boolean;
        cryptoPrice: number;
        mockMode: boolean;
        payMethods: string[];
    };
}>();

const page = usePage();
const flash = computed(() => (page.props.flash as any) ?? {});
const wallet = computed(() => (page.props.wallet as any));

const showMethodModal = ref(false);
const selectedMethod = ref<string | null>(null);

const methodMeta: Record<string, { label: string; icon: string }> = {
    alipay: { label: '支付宝', icon: '🅰' },
    wxpay: { label: '微信支付', icon: '💬' },
    paypal: { label: 'PayPal', icon: '🌐' },
    crypto: { label: '加密货币', icon: '₿' },
    mock: { label: 'Mock 支付', icon: '🧪' },
};

const methodOptions = computed(() => (props.options.payMethods ?? []).map((m) => ({
    key: m,
    ...(methodMeta[m] ?? { label: m, icon: '💳' }),
})));

const sourceText = (s: string) => ({ admin: '管理员', gold: '金币购买', crypto: '加密购买' })[s] ?? s;
const statusBadge = (s: string) => s === 'active'
    ? { text: '可用', class: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' }
    : s === 'used'
        ? { text: '已使用', class: 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300' }
        : { text: '已禁用', class: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' };

function buyGold() {
    if (!confirm(`花费 ${props.options.goldPrice} 金币购买 1 枚邀请码？`)) return;
    router.post('/invite/purchase/gold');
}

function openCryptoModal() {
    if (!methodOptions.value.length) return;
    selectedMethod.value = methodOptions.value[0]?.key ?? null;
    showMethodModal.value = true;
}

function buyCrypto() {
    showMethodModal.value = false;
    router.post('/invite/purchase/crypto', { method: selectedMethod.value });
}
</script>

<template>
    <Head title="邀请码中心" />
    <AppLayout>
        <div class="mb-5 flex items-center justify-between">
            <h1 class="text-2xl font-semibold tracking-tight">邀请码中心</h1>
        </div>

        <div v-if="flash.success" class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ flash.success }}</div>
        <div v-if="flash.error" class="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ flash.error }}</div>

        <!-- 购买区 -->
        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <Card v-if="options.allowGold">
                <CardContent class="p-5">
                    <h2 class="font-semibold">金币购买</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        价格：<span class="font-medium text-foreground">{{ options.goldPrice }} 金币</span> / 枚
                        <template v-if="wallet">（当前余额 {{ wallet.gold }}）</template>
                    </p>
                    <Button class="mt-4" :disabled="!wallet" @click="buyGold">立即购买</Button>
                    <p v-if="!wallet" class="mt-2 text-xs text-muted-foreground">钱包未就绪（经济模块未启用）</p>
                </CardContent>
            </Card>

            <Card v-if="options.allowCrypto">
                <CardContent class="p-5">
                    <h2 class="font-semibold">在线支付</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        价格：<span class="font-medium text-foreground">${{ options.cryptoPrice }}</span> USD / 枚
                        <template v-if="options.mockMode">（Mock 联调模式）</template>
                    </p>
                    <Button class="mt-4" @click="openCryptoModal">立即购买</Button>
                </CardContent>
            </Card>

            <Card v-if="!options.allowGold && !options.allowCrypto">
                <CardContent class="p-5 text-sm text-muted-foreground">
                    当前未开放购买，请联系管理员获取邀请码。
                </CardContent>
            </Card>
        </div>

        <!-- 我的邀请码 -->
        <Card>
            <CardContent class="p-5">
                <h2 class="text-base font-medium">我的邀请码（{{ codes.length }}）</h2>

                <p v-if="!codes.length" class="mt-3 text-sm text-muted-foreground">还没有邀请码，可通过上方方式购买。</p>

                <table v-else class="mt-3 w-full text-sm">
                    <thead>
                        <tr class="border-b text-left text-xs text-muted-foreground">
                            <th class="py-2">邀请码</th>
                            <th class="py-2">来源</th>
                            <th class="py-2">状态</th>
                            <th class="py-2 hidden sm:table-cell">花费</th>
                            <th class="py-2 hidden sm:table-cell">创建</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="c in codes" :key="c.id" class="border-b last:border-0">
                            <td class="py-2 font-mono text-xs font-medium">{{ c.code }}</td>
                            <td class="py-2 text-muted-foreground">{{ sourceText(c.source) }}</td>
                            <td class="py-2">
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="statusBadge(c.status).class">
                                    {{ statusBadge(c.status).text }}
                                </span>
                            </td>
                            <td class="hidden py-2 text-muted-foreground sm:table-cell">
                                {{ c.gold_spent ? c.gold_spent + ' 金币' : (c.paid_amount ? '$' + c.paid_amount : '—') }}
                            </td>
                            <td class="hidden py-2 text-muted-foreground sm:table-cell">{{ c.created_at }}</td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>
    </AppLayout>

    <!-- 支付方式选择弹窗 -->
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            leave-active-class="transition duration-150 ease-in"
            leave-to-class="opacity-0">
            <div v-if="showMethodModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
                 @click.self="showMethodModal = false">
                <div class="w-full max-w-sm rounded-2xl border bg-card shadow-2xl">
                    <div class="p-6">
                        <p class="text-center text-xs uppercase tracking-widest text-muted-foreground">选择支付方式</p>
                        <p class="mt-2 text-center text-2xl font-bold text-primary">${{ options.cryptoPrice }}</p>
                        <div class="mt-5 space-y-2">
                            <button v-for="m in methodOptions" :key="m.key" type="button"
                                    @click="selectedMethod = m.key"
                                    class="flex w-full items-center gap-3 rounded-xl border px-4 py-3 text-left text-sm transition"
                                    :class="selectedMethod === m.key
                                        ? 'border-primary bg-primary/5 ring-1 ring-primary'
                                        : 'border-input hover:bg-muted'">
                                <span class="text-xl">{{ m.icon }}</span>
                                <span class="font-medium">{{ m.label }}</span>
                                <span v-if="selectedMethod === m.key" class="ml-auto text-primary">✓</span>
                            </button>
                        </div>
                        <div class="mt-5 flex gap-3">
                            <button type="button" @click="showMethodModal = false"
                                    class="flex-1 rounded-xl border border-input px-4 py-2.5 text-sm font-medium transition hover:bg-muted">取消</button>
                            <button type="button" @click="buyCrypto"
                                    class="flex-1 rounded-xl bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground shadow transition hover:bg-primary/90">去支付</button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
