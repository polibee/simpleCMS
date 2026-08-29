<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Button from '@/components/ui/Button.vue';

/**
 * 创作中心布局：左侧功能导航 + 右侧内容区。
 * 仅 author/super_admin 可见（服务端中间件已拦截，未授权不会渲染此页）。
 */
const page = usePage();
const user = computed(() => (page.props.auth as any)?.user);

const nav = [
    { label: '我的文章', href: '/studio/posts' },
    { label: '写文章', href: '/studio/posts/create' },
    { label: '个人设置', href: '/studio/settings' },
    { label: '安全设置', href: '/studio/settings/security' },
];

const isActive = (item: (typeof nav)[number]) => {
    const path = window.location.pathname;
    if (item.href === '/studio/posts') {
        return path === '/studio/posts' || /^\/studio\/posts\/\d+\/edit$/.test(path);
    }

    return path.startsWith(item.href);
};
</script>

<template>
    <div class="flex min-h-screen flex-col bg-background text-foreground">
        <!-- 顶栏 -->
        <header class="sticky top-0 z-40 border-b bg-card/80 backdrop-blur supports-[backdrop-filter]:bg-card/60">
            <div class="mx-auto flex h-14 max-w-6xl items-center justify-between px-4">
                <div class="flex items-center gap-3">
                    <Link href="/" class="text-base font-bold tracking-tight">{{ (page.props.site as any)?.name ?? 'CMSForum' }}</Link>
                    <span class="text-sm text-muted-foreground">/ 创作中心</span>
                </div>
                <div v-if="user" class="flex items-center gap-2 text-sm text-muted-foreground">
                    {{ user.name }}
                    <Button variant="ghost" size="sm" as="a" href="/">返回网站</Button>
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-8">
            <div class="grid grid-cols-1 gap-8 md:grid-cols-[180px_1fr]">
                <!-- 功能导航 -->
                <nav class="space-y-1 md:self-start">
                    <Link v-for="item in nav" :key="item.href" :href="item.href"
                          class="block rounded-md px-3 py-2 text-sm transition"
                          :class="isActive(item) ? 'bg-accent font-medium text-accent-foreground' : 'text-muted-foreground hover:bg-muted hover:text-foreground'">
                        {{ item.label }}
                    </Link>
                </nav>

                <!-- 内容区 -->
                <section class="min-w-0">
                    <slot />
                </section>
            </div>
        </main>
    </div>
</template>
