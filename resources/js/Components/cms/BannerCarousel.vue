<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * 轻量首页轮播：自动播放（5s）+ 左右箭头 + 圆点指示器 + hover 暂停。
 * 无第三方依赖；无 banner 时由父组件回退静态 Hero。
 */
interface Banner {
    id: number;
    title: string | null;
    image_url: string;
    link_url: string | null;
    new_tab?: boolean;
}

const props = defineProps<{ banners: Banner[] }>();

const current = ref(0);
let timer: ReturnType<typeof setInterval> | null = null;
const paused = ref(false);

function go(index: number) {
    const total = props.banners.length;
    if (total === 0) return;
    current.value = ((index % total) + total) % total;
}

function next() { go(current.value + 1); }
function prev() { go(current.value - 1); }

function startTimer() {
    stopTimer();
    if (props.banners.length > 1) {
        timer = setInterval(() => {
            if (! paused.value) next();
        }, 5000);
    }
}

function stopTimer() {
    if (timer) { clearInterval(timer); timer = null; }
}

onMounted(startTimer);
onBeforeUnmount(stopTimer);

function onMouseEnter() { paused.value = true; }
function onMouseLeave() { paused.value = false; }
</script>

<template>
    <div class="group relative overflow-hidden rounded-2xl bg-zinc-900"
         @mouseenter="onMouseEnter" @mouseleave="onMouseLeave">
        <!-- 轮播图 -->
        <div class="relative aspect-[21/8] w-full sm:aspect-[21/7]">
            <template v-for="(banner, i) in props.banners" :key="banner.id">
                <a :href="banner.link_url ?? '#'"
                   :target="banner.new_tab ? '_blank' : undefined"
                   :rel="banner.new_tab ? 'noopener' : undefined"
                   class="absolute inset-0 transition-opacity duration-700"
                   :class="i === current ? 'opacity-100' : 'pointer-events-none opacity-0'"
                   :aria-hidden="i !== current">
                    <img :src="banner.image_url" :alt="banner.title ?? ''"
                         class="h-full w-full object-cover" loading="eager">
                    <span v-if="banner.title"
                          class="absolute bottom-4 left-4 rounded-md bg-black/50 px-3 py-1.5 text-sm font-medium text-white backdrop-blur">
                        {{ banner.title }}
                    </span>
                </a>
            </template>
        </div>

        <!-- 左右箭头 -->
        <button v-if="props.banners.length > 1" type="button" aria-label="上一张" @click.prevent="prev()"
                class="absolute left-3 top-1/2 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full bg-black/40 text-white opacity-0 transition group-hover:opacity-100 hover:bg-black/60">
            ‹
        </button>
        <button v-if="props.banners.length > 1" type="button" aria-label="下一张" @click.prevent="next()"
                class="absolute right-3 top-1/2 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full bg-black/40 text-white opacity-0 transition group-hover:opacity-100 hover:bg-black/60">
            ›
        </button>

        <!-- 圆点指示器 -->
        <div v-if="props.banners.length > 1" class="absolute bottom-3 left-1/2 flex -translate-x-1/2 gap-1.5">
            <button v-for="(banner, i) in props.banners" :key="`dot-${banner.id}`" type="button"
                    :aria-label="`第 ${i + 1} 张`" @click.prevent="go(i)"
                    :class="[
                        'h-1.5 rounded-full transition-all',
                        i === current ? 'w-6 bg-white' : 'w-1.5 bg-white/50 hover:bg-white/80',
                    ]" />
        </div>
    </div>
</template>
