<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { cn } from '@/lib/utils';

/**
 * 轻量富文本编辑器（零依赖）：contenteditable + execCommand。
 * 输出 HTML（v-model），服务端保存前经 App\Support\HtmlSanitizer 白名单清洗。
 * 链接/图片插入使用页面内居中弹窗（支持选区恢复），不再用浏览器原生 prompt。
 */
const props = defineProps<{
    placeholder?: string;
    minHeight?: string;
    class?: string;
}>();

const model = defineModel<string>({ default: '' });

const editorRef = ref<HTMLDivElement | null>(null);
// selectionchange 触发的格式状态（用于高亮工具按钮）
const states = ref({ bold: false, italic: false, underline: false, strikeThrough: false });

// ---------------- 插入弹窗（链接 / 图片） ----------------
interface InsertModal {
    open: boolean;
    mode: 'link' | 'image';
    url: string;
    text: string;
}
const modal = ref<InsertModal>({ open: false, mode: 'link', url: '', text: '' });
const urlInputRef = ref<HTMLInputElement | null>(null);
// 打开弹窗前保存选区：确认时恢复到原位置插入
let savedRange: Range | null = null;

function openInsert(mode: 'link' | 'image') {
    const el = editorRef.value;
    const sel = window.getSelection();
    savedRange = el && sel && sel.rangeCount > 0 && el.contains(sel.anchorNode)
        ? sel.getRangeAt(0).cloneRange()
        : null;

    // 若选中了文字且是插链接 → 预填链接文本
    const selected = mode === 'link' && savedRange && !savedRange.collapsed ? savedRange.toString() : '';
    modal.value = { open: true, mode, url: '', text: selected };

    nextTick(() => urlInputRef.value?.focus());
}

function closeInsert() {
    modal.value = { ...modal.value, open: false };
}

function normalizeUrl(raw: string): string {
    const url = raw.trim();
    if (!url) return '';
    if (/^(https?:\/\/|mailto:|\/|#)/i.test(url)) return url;

    return `https://${url}`;
}

/** 解析 Markdown 图床链接：![alt](url "title") → { alt, url }；非 markdown 返回 null。 */
function parseMarkdownImage(raw: string): { alt: string; url: string } | null {
    const m = raw.trim().match(/^!\[([^\]]*)\]\(([^)\s]+)(?:\s+"[^"]*")?\)$/);
    return m ? { alt: m[1], url: m[2] } : null;
}

/** 解析 Markdown 链接：[text](url) → { text, url }；非 markdown 返回 null。 */
function parseMarkdownLink(raw: string): { text: string; url: string } | null {
    const m = raw.trim().match(/^\[([^\]]+)\]\(([^)\s]+)(?:\s+"[^"]*")?\)$/);
    return m ? { text: m[1], url: m[2] } : null;
}

function confirmInsert() {
    const el = editorRef.value;

    let url = modal.value.url.trim();
    let text = (modal.value.text || '').trim();

    // 支持 Markdown 图床/链接语法直接粘贴
    if (modal.value.mode === 'image') {
        const md = parseMarkdownImage(url);
        if (md) {
            url = md.url;
            text = md.alt || text;
        }
    } else {
        const md = parseMarkdownLink(url);
        if (md) {
            url = md.url;
            if (!text) text = md.text;
        }
    }

    url = normalizeUrl(url);

    if (!el || !url) {
        closeInsert();

        return;
    }

    // 恢复保存的选区后再执行命令
    el.focus();
    if (savedRange) {
        const sel = window.getSelection();
        sel?.removeAllRanges();
        sel?.addRange(savedRange);
    }

    if (modal.value.mode === 'image') {
        document.execCommand('insertImage', false, url);
        // 图床 alt：插入后补齐（可访问性）
        if (text) {
            const imgs = el.querySelectorAll(`img[src="${url.replace(/"/g, '\\"')}"]`);
            imgs.forEach((img) => { if (!img.getAttribute('alt')) img.setAttribute('alt', text); });
        }
    } else {
        const label = (modal.value.text || '').trim() || url;
        document.execCommand('insertHTML', false,
            `<a href="${url.replace(/"/g, '&quot;')}" target="_blank" rel="noopener noreferrer">${label.replace(/[<>&]/g, '')}</a>&nbsp;`);
    }

    syncModel();
    closeInsert();
}

// ---------------- 基础排版命令 ----------------
interface ToolItem {
    label: string;
    title: string;
    cmd?: string;
    arg?: string;
    block?: string;
    stateKey?: keyof typeof states.value;
}

const tools: ToolItem[] = [
    { label: 'B', title: '粗体', cmd: 'bold', stateKey: 'bold' },
    { label: 'I', title: '斜体', cmd: 'italic', stateKey: 'italic' },
    { label: 'U', title: '下划线', cmd: 'underline', stateKey: 'underline' },
    { label: 'S', title: '删除线', cmd: 'strikeThrough', stateKey: 'strikeThrough' },
];

const blockTools: ToolItem[] = [
    { label: 'H2', title: '二级标题', block: 'h2' },
    { label: 'H3', title: '三级标题', block: 'h3' },
    { label: '❝', title: '引用', block: 'blockquote' },
    { label: '‹›', title: '代码块', block: 'pre' },
    { label: '• 列表', title: '无序列表', cmd: 'insertUnorderedList' },
    { label: '1. 列表', title: '有序列表', cmd: 'insertOrderedList' },
    { label: '—', title: '分隔线', cmd: 'insertHorizontalRule' },
];

function exec(tool: ToolItem) {
    focusPreserving();
    if (tool.block) {
        // angle-bracket 语法：空段落/裸文本块场景下 Chrome 才会执行转换
        document.execCommand('formatBlock', false, `<${tool.block}>`);
    } else if (tool.cmd) {
        document.execCommand(tool.cmd);
    }
    syncModel();
}

function clearFormat() {
    focusPreserving();
    document.execCommand('removeFormat');
    syncModel();
}

/** 执行命令前把选区还给编辑器（工具栏点击会让焦点离开 contenteditable）。 */
function focusPreserving() {
    const el = editorRef.value;
    if (!el) return;
    if (!el.contains(window.getSelection()?.anchorNode ?? null)) {
        el.focus();
        // 无既有选区时移到文末
        const sel = window.getSelection();
        if (sel && sel.isCollapsed && sel.rangeCount === 0) {
            const range = document.createRange();
            range.selectNodeContents(el);
            range.collapse(false);
            sel.removeAllRanges();
            sel.addRange(range);
        }
    }
}

function onInput() {
    syncModel();
}

/** 粘贴 Markdown 图片语法时直接转为图片插入（图床工作流）。 */
function onPaste(e: ClipboardEvent) {
    const text = e.clipboardData?.getData('text/plain') ?? '';
    const md = parseMarkdownImage(text);
    if (md && /^https?:\/\//i.test(md.url)) {
        e.preventDefault();
        focusPreserving();
        const alt = md.alt.replace(/[<>&"]/g, '');
        document.execCommand('insertHTML', false,
            `<img src="${md.url.replace(/"/g, '&quot;')}" alt="${alt}" />&nbsp;`);
        syncModel();
    }
}

function syncModel() {
    model.value = editorRef.value?.innerHTML ?? '';
}

function refreshStates() {
    const el = editorRef.value;
    const sel = window.getSelection();
    const active = !!el && !!sel?.anchorNode && el.contains(sel.anchorNode);
    states.value = {
        bold: active && document.queryCommandState('bold'),
        italic: active && document.queryCommandState('italic'),
        underline: active && document.queryCommandState('underline'),
        strikeThrough: active && document.queryCommandState('strikeThrough'),
    };
}

function onModalKeydown(e: KeyboardEvent) {
    if (e.key === 'Escape') closeInsert();
    if (e.key === 'Enter') {
        e.preventDefault();
        confirmInsert();
    }
}

// 外部赋值（进入编辑页回填）→ 写入 DOM；避免每次输入都重置光标，
// 仅在值与当前 DOM 不一致且焦点不在编辑器内时同步。
watch(model, (val) => {
    const el = editorRef.value;
    if (!el) return;
    if ((el.innerHTML ?? '') !== val && !el.contains(document.activeElement)) {
        el.innerHTML = val ?? '';
    }
});

onMounted(() => {
    if (editorRef.value) {
        editorRef.value.innerHTML = model.value ?? '';
    }
    document.addEventListener('selectionchange', refreshStates);
});

onBeforeUnmount(() => {
    document.removeEventListener('selectionchange', refreshStates);
});
</script>

<template>
    <div :class="cn('rounded-lg border bg-card shadow-sm focus-within:ring-2 focus-within:ring-ring', props.class ?? '')">
        <!-- 工具栏 -->
        <div class="flex flex-wrap items-center gap-0.5 border-b px-2 py-1.5">
            <button v-for="tool in tools" :key="tool.title" type="button" :title="tool.title" :aria-label="tool.title"
                    @mousedown.prevent @click.prevent="exec(tool)"
                    class="h-7 min-w-7 rounded px-1.5 text-sm text-muted-foreground transition hover:bg-muted hover:text-foreground"
                    :class="[tool.stateKey && states[tool.stateKey] ? 'bg-accent font-bold text-foreground' : '', tool.label === 'I' ? 'italic' : '', tool.label === 'U' ? 'underline' : '', tool.label === 'S' ? 'line-through' : '']">
                {{ tool.label }}
            </button>

            <span class="mx-1 h-4 w-px bg-border" />

            <button v-for="block in blockTools" :key="block.title" type="button" :title="block.title" :aria-label="block.title"
                    @mousedown.prevent @click.prevent="exec(block)"
                    class="h-7 rounded px-1.5 text-xs font-medium text-muted-foreground transition hover:bg-muted hover:text-foreground">
                {{ block.label }}
            </button>

            <span class="mx-1 h-4 w-px bg-border" />

            <button type="button" title="插入链接" aria-label="插入链接" @click.prevent.stop="openInsert('link')"
                    class="h-7 rounded px-1.5 text-xs text-muted-foreground transition hover:bg-muted hover:text-foreground">🔗 链接</button>
            <button type="button" title="插入图片" aria-label="插入图片" @click.prevent.stop="openInsert('image')"
                    class="h-7 rounded px-1.5 text-xs text-muted-foreground transition hover:bg-muted hover:text-foreground">🖼 图片</button>
            <button type="button" title="清除格式" aria-label="清除格式" @mousedown.prevent @click.prevent="clearFormat"
                    class="h-7 rounded px-1.5 text-xs text-muted-foreground transition hover:bg-muted hover:text-foreground">✕ 格式</button>
        </div>

        <!-- 编辑区 -->
        <div ref="editorRef" contenteditable="true" spellcheck="false" role="textbox" aria-multiline="true"
             :data-placeholder="placeholder ?? '开始撰写正文…'"
             @input="onInput" @paste="onPaste"
             class="prose prose-zinc max-w-none px-4 py-3 leading-relaxed prose-headings:mt-4 prose-headings:mb-2 focus:outline-none empty:before:text-muted-foreground empty:before:content-[attr(data-placeholder)]"
             :style="{ minHeight: props.minHeight ?? '320px' }" />

        <!-- 链接 / 图片插入弹窗 -->
        <Teleport to="body">
            <div v-if="modal.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm"
                 @click.self="closeInsert">
                <div role="dialog" aria-modal="true" :aria-label="modal.mode === 'link' ? '插入链接' : '插入图片'"
                     class="w-[420px] max-w-[92vw] rounded-xl border bg-card p-5 shadow-xl"
                     @keydown="onModalKeydown">
                    <h3 class="text-base font-semibold">{{ modal.mode === 'link' ? '插入链接' : '插入图片' }}</h3>

                    <label class="mt-4 block text-sm font-medium">{{ modal.mode === 'link' ? '链接地址' : '图片地址' }}</label>
                    <input ref="urlInputRef" v-model="modal.url" type="text" inputmode="url"
                           :placeholder="modal.mode === 'link' ? 'https://example.com/page' : 'https://example.com/image.png'"
                           class="mt-1.5 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" />

                    <template v-if="modal.mode === 'link'">
                        <label class="mt-3 block text-sm font-medium">显示文字（可选，默认选中文字）</label>
                        <input v-model="modal.text" type="text" placeholder="点这里访问…"
                               class="mt-1.5 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" />
                    </template>

                    <p v-if="modal.mode === 'image'" class="mt-3 text-xs text-muted-foreground">
                        支持图床直链或 Markdown 图片语法：<code>![描述](https://图床/图.png)</code>，粘贴到地址框即可自动解析；也可直接粘贴到正文。
                    </p>

                    <div class="mt-5 flex justify-end gap-2">
                        <button type="button" @click="closeInsert"
                                class="inline-flex h-9 items-center rounded-md border border-input bg-background px-4 text-sm font-medium transition hover:bg-muted">
                            取消
                        </button>
                        <button type="button" @click="confirmInsert"
                                class="inline-flex h-9 items-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground shadow transition hover:bg-primary/90 disabled:opacity-50"
                                :disabled="!normalizeUrl(modal.url)">
                            插入
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
