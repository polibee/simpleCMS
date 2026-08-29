<script setup lang="ts">
import { ref } from 'vue';
import { cn } from '@/lib/utils';

/**
 * 轻量 Markdown 编辑器：工具栏插入语法 + textarea。
 * 渲染由服务端完成（league/commonmark，HTML 输入转义防 XSS）。
 */
const props = defineProps<{
    placeholder?: string;
    rows?: number;
    class?: string;
}>();

const model = defineModel<string>({ default: '' });
const textareaRef = ref<HTMLTextAreaElement | null>(null);

interface ToolItem {
    label: string;
    title: string;
    wrap: [string, string];
}

const tools: ToolItem[] = [
    { label: 'B', title: '粗体', wrap: ['**', '**'] },
    { label: 'I', title: '斜体', wrap: ['*', '*'] },
    { label: '~', title: '删除线', wrap: ['~~', '~~'] },
    { label: '`', title: '行内代码', wrap: ['`', '`'] },
];

function insert(before: string, after: string) {
    const el = textareaRef.value;
    if (! el) {
        model.value += before + after;
        return;
    }

    const start = el.selectionStart;
    const end = el.selectionEnd;
    const selected = model.value.slice(start, end) || '';

    model.value = model.value.slice(0, start) + before + selected + after + model.value.slice(end);

    requestAnimationFrame(() => {
        el.focus();
        el.selectionStart = start + before.length;
        el.selectionEnd = start + before.length + selected.length;
    });
}

function insertBlock(prefix: string, placeholder = '') {
    const el = textareaRef.value;
    const atLineStart = !el || el.selectionStart === 0 || model.value[el.selectionStart - 1] === '\n';
    const insertText = (atLineStart ? '' : '\n') + prefix + placeholder;

    if (! el) {
        model.value += insertText;
        return;
    }

    const pos = el.selectionStart;
    model.value = model.value.slice(0, pos) + insertText + model.value.slice(el.selectionEnd);
    requestAnimationFrame(() => {
        el.focus();
        el.selectionStart = el.selectionEnd = pos + insertText.length - placeholder.length;
    });
}
</script>

<template>
    <div :class="cn('rounded-lg border bg-card shadow-sm focus-within:ring-2 focus-within:ring-ring', props.class ?? '')">
        <!-- 工具栏 -->
        <div class="flex items-center gap-0.5 border-b px-2 py-1.5">
            <button v-for="tool in tools" :key="tool.title" type="button" :title="tool.title"
                    @click.prevent="insert(tool.wrap[0], tool.wrap[1])"
                    class="h-7 min-w-7 rounded px-1.5 text-sm font-semibold text-muted-foreground transition hover:bg-muted hover:text-foreground">
                {{ tool.label }}
            </button>
            <span class="mx-1 h-4 w-px bg-border" />
            <button type="button" title="引用" @click.prevent="insertBlock('> ', '引用内容')"
                    class="h-7 rounded px-2 text-sm text-muted-foreground transition hover:bg-muted hover:text-foreground">❝</button>
            <button type="button" title="代码块" @click.prevent="insertBlock('```\n', '\n```')"
                    class="h-7 rounded px-2 text-xs text-muted-foreground transition hover:bg-muted hover:text-foreground">{'{}'}</button>
            <button type="button" title="链接" @click.prevent="insert('[', '](https://)')"
                    class="h-7 rounded px-2 text-sm text-muted-foreground transition hover:bg-muted hover:text-foreground">🔗</button>
        </div>

        <textarea ref="textareaRef" v-model="model" :rows="props.rows ?? 4" :placeholder="props.placeholder ?? '支持 Markdown 语法…'"
                  class="w-full resize-y rounded-b-lg bg-transparent px-3 py-2 text-sm outline-none placeholder:text-muted-foreground" />
    </div>

    <p class="mt-1 text-xs text-muted-foreground">支持 Markdown：**粗体**、*斜体*、`代码`、> 引用、[链接](url)</p>
</template>
