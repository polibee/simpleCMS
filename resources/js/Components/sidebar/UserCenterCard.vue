<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Card from '@/components/ui/Card.vue';
import CardContent from '@/components/ui/CardContent.vue';
import Button from '@/components/ui/Button.vue';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const wallet = computed(() => page.props.wallet);

const props = defineProps<{ title?: string }>();
</script>

<template>
    <Card>
        <CardContent class="p-5">
            <h3 class="text-sm font-semibold text-muted-foreground">{{ props.title ?? '用户中心' }}</h3>

            <!-- 已登录：当前用户资料 + 快捷入口 -->
            <template v-if="user">
                <div class="mt-4 flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-full bg-primary text-base font-semibold text-primary-foreground">
                        {{ user.name.slice(0, 1) }}
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium">{{ user.name }}</p>
                        <p v-if="wallet" class="text-xs text-muted-foreground">金币 {{ wallet.gold }}</p>
                    </div>
                </div>
                <div class="mt-4 flex gap-2">
                    <Button variant="outline" size="sm" as="a" :href="`/users/${user.id}`" class="flex-1">个人主页</Button>
                    <Button variant="ghost" size="sm" as="a" href="/articles" class="flex-1">我的文章</Button>
                </div>
            </template>

            <!-- 未登录 -->
            <template v-else>
                <p class="mt-3 text-sm text-muted-foreground">登录后可签到、发文、参与社区互动。</p>
                <div class="mt-4 flex gap-2">
                    <Button size="sm" as="a" href="/login" class="flex-1">登录</Button>
                    <Button variant="outline" size="sm" as="a" href="/register" class="flex-1">注册</Button>
                </div>
            </template>
        </CardContent>
    </Card>
</template>
