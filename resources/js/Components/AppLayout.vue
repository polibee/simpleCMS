<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed, useSlots } from 'vue';
import Button from '@/components/ui/Button.vue';
import CookieBanner from '@/components/CookieBanner.vue';

interface MenuItem {
    label?: string;
    title?: string;
    url?: string;
    children?: MenuItem[];
}

interface MenuTree {
    name: string;
    items: MenuItem[];
}

interface FooterColumn {
    id: number;
    title: string;
    items: MenuItem[];
}

const page = usePage();
const user = computed(() => page.props.auth?.user);
// 创作中心入口：管理员授予 author/super_admin 角色后才显示
const canAuthor = computed(() => !!(page.props.auth as any)?.can_author);
const siteName = computed(() => page.props.site?.name ?? 'CMSForum');

// 后台"菜单"系统配置的导航；未分配时回退默认
const headerMenu = computed<MenuTree | null>(() => (page.props.menus as any)?.header ?? null);
const footerMenu = computed<MenuTree | null>(() => (page.props.menus as any)?.footer ?? null);
// 页脚多列（后台"外观→页脚导航列"；每列 = 标题 + 菜单项）
const footerColumns = computed<FooterColumn[]>(() => (page.props.footerColumns as any) ?? []);

const articlesUrl = computed(() => (page.props.cms as any)?.listUrl ?? '/articles');

const headerItems = computed<MenuItem[]>(() =>
    headerMenu.value?.items?.length ? headerMenu.value.items : [{ label: '文章', url: articlesUrl.value }],
);
const footerItems = computed<MenuItem[]>(() =>
    footerMenu.value?.items?.length
        ? footerMenu.value.items
        : [
            { label: '文章', url: articlesUrl.value },
            { label: '注册', url: '/register' },
        ],
);

const year = new Date().getFullYear();

// 页面是否提供侧边栏内容：没有时主栏全宽（商城/单页等页面比例正常）
const slots = useSlots();
const hasSidebar = computed(() => !!slots.sidebar);

/** 页眉项是否有子菜单 */
const hasChildren = (item: MenuItem) => Array.isArray(item.children) && item.children.length > 0;
</script>

<template>
    <div class="flex min-h-screen flex-col bg-background text-foreground">
        <!-- 页眉：站点名 + 可配置导航菜单 -->
        <header class="sticky top-0 z-40 border-b bg-card/80 backdrop-blur supports-[backdrop-filter]:bg-card/60">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4">
                <Link href="/" class="text-lg font-bold tracking-tight">{{ siteName }}</Link>

                <nav class="flex items-center gap-1.5">
                    <template v-for="(item, i) in headerItems" :key="`h-${i}`">
                        <!-- 有子菜单：下拉（悬停/聚焦展开） -->
                        <div v-if="hasChildren(item)" class="group relative">
                            <button type="button"
                                    class="inline-flex h-9 items-center justify-center gap-1 rounded-md px-4 py-2 text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground">
                                {{ item.label ?? item.title }}
                                <svg class="h-3.5 w-3.5 transition-transform group-hover:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="m6 9 6 6 6-6" />
                                </svg>
                            </button>
                            <div class="invisible absolute right-0 z-50 mt-1 w-48 rounded-lg border bg-card py-1 opacity-0 shadow-lg transition-all group-hover:visible group-hover:opacity-100 group-focus-within:visible group-focus-within:opacity-100">
                                <template v-for="(child, ci) in item.children" :key="`h-${i}-${ci}`">
                                    <a :href="child.url ?? '#'"
                                       class="block rounded-md px-3 py-2 text-sm text-foreground/80 transition hover:bg-muted hover:text-foreground">
                                        {{ child.label ?? child.title }}
                                    </a>
                                </template>
                            </div>
                        </div>
                        <Button v-else variant="ghost" as="a" :href="item.url ?? '#'">{{ item.label ?? item.title }}</Button>
                    </template>

                    <span v-if="headerItems.length" class="mx-1.5 h-5 w-px bg-border" />

                    <!-- 站内搜索 -->
                    <form method="GET" action="/search" class="hidden md:block">
                        <input type="text" name="q" placeholder="搜索…"
                               class="h-8 w-36 rounded-full border border-input bg-background px-3 text-xs transition focus:w-48 focus:outline-none focus:ring-1 focus:ring-ring" />
                    </form>

                    <template v-if="user">
                        <Button v-if="canAuthor" variant="ghost" as="a" href="/studio/posts">创作中心</Button>
                        <Button variant="ghost" as="a" :href="`/users/${user.id}`">{{ user.name }}</Button>
                    </template>
                    <template v-else>
                        <Button variant="ghost" as="a" href="/login">登录</Button>
                        <Button as="a" href="/register">注册</Button>
                    </template>
                </nav>
            </div>
        </header>

        <!-- 两栏（有侧边栏）或全宽（无侧边栏：商城/单页等） -->
        <main class="mx-auto w-full max-w-7xl flex-1 px-4 py-8">
            <div v-if="hasSidebar" class="grid grid-cols-1 gap-8 lg:grid-cols-12">
                <section class="lg:col-span-8">
                    <slot />
                </section>

                <aside class="space-y-4 lg:col-span-4">
                    <slot name="sidebar" />
                </aside>
            </div>
            <div v-else class="mx-auto max-w-7xl">
                <slot />
            </div>
        </main>

        <!-- 页脚：多列导航（后台可配列/绑菜单）；未配置时回退单行菜单 -->
        <footer class="border-t bg-card">
            <!-- 多列模板 -->
            <div v-if="footerColumns.length" class="mx-auto max-w-7xl px-4 py-10">
                <div class="grid grid-cols-2 gap-8 sm:grid-cols-3 lg:grid-cols-4">
                    <div v-for="col in footerColumns" :key="col.id">
                        <h3 class="text-sm font-semibold">{{ col.title }}</h3>
                        <ul class="mt-3 space-y-2">
                            <li v-for="(item, i) in col.items" :key="`fc-${col.id}-${i}`">
                                <a :href="item.url ?? '#'" class="text-sm text-muted-foreground transition hover:text-foreground">
                                    {{ item.label ?? item.title }}
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="mt-8 border-t pt-5 text-center text-sm text-muted-foreground">
                    © {{ year }} {{ siteName }}
                </div>
            </div>

            <!-- 单行回退 -->
            <div v-else class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-3 px-4 py-6 sm:flex-row">
                <nav class="flex flex-wrap items-center gap-4 text-sm text-muted-foreground">
                    <template v-for="(item, i) in footerItems" :key="`f-${i}`">
                        <a :href="item.url ?? '#'" class="transition hover:text-foreground">{{ item.label ?? item.title }}</a>
                    </template>
                </nav>
                <p class="text-sm text-muted-foreground">© {{ year }} {{ siteName }}</p>
            </div>
        </footer>

        <!-- Cookie 授权横幅（§七十七，站点设置可开关） -->
        <CookieBanner />
    </div>
</template>
