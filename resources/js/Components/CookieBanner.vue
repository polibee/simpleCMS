<script setup lang="ts">
import { ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Button from '@/components/ui/Button.vue';

/**
 * Cookie 授权横幅（§七十七）：未选择时显示于底部；
 * 授权记录在 localStorage（前端判断）+ cookie cookie_consent=full（服务端脚本注入门控）。
 * 分析/广告脚本仅在「接受全部」后由服务端注入（app.blade.php）。
 */
const page = usePage();
const privacy = (() => (page.props.privacy as any) ?? { cookieBanner: false, effectiveDate: '2026-01-01' })();

const consent = typeof localStorage !== 'undefined' ? localStorage.getItem('cookie_consent') : null;
const visible = ref(privacy.cookieBanner && consent === null);

function decide(choice: 'all' | 'essential') {
    localStorage.setItem('cookie_consent', choice);
    if (choice === 'all') {
        document.cookie = 'cookie_consent=full; path=/; max-age=15552000; samesite=lax';
        // 服务端按 cookie 门控注入脚本 → 刷新一次以生效
        window.location.reload();
        return;
    }
    visible.value = false;
}
</script>

<template>
    <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="translate-y-4 opacity-0"
        leave-active-class="transition duration-150 ease-in"
        leave-to-class="translate-y-4 opacity-0">
        <div v-if="visible" class="fixed inset-x-0 bottom-0 z-50 border-t bg-card/95 p-4 shadow-2xl backdrop-blur">
            <div class="mx-auto flex max-w-5xl flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm leading-relaxed text-muted-foreground">
                    我们使用 Cookie 提供个性化内容、广告与流量统计（含 Google Analytics / Google Ads）。
                    点击「接受全部」即表示同意；你也可以仅允许必要 Cookie。
                    <a href="/privacy" class="underline underline-offset-2 hover:text-foreground">隐私政策（{{ privacy.effectiveDate }} 生效）</a>
                </p>
                <div class="flex shrink-0 gap-2">
                    <Button variant="outline" size="sm" @click="decide('essential')">仅必要</Button>
                    <Button size="sm" @click="decide('all')">接受全部</Button>
                </div>
            </div>
        </div>
    </Transition>
</template>
