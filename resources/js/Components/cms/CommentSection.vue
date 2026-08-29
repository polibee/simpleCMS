<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import Card from '@/components/ui/Card.vue';
import CardContent from '@/components/ui/CardContent.vue';
import Button from '@/components/ui/Button.vue';
import Badge from '@/components/ui/Badge.vue';
import MarkdownEditor from '@/components/cms/MarkdownEditor.vue';

/**
 * 文章评论区：Markdown 编辑器 + 楼中楼（parent_id 嵌套）+ @回复 + UA 追踪徽标。
 */
interface CommentNode {
    id: number;
    parent_id: number | null;
    author_name: string;
    author_id: number | null;
    html: string;
    created_at: string | null;
    os: string;
    browser: string;
    device: string;
    reply_to?: string | null;
    replies: CommentNode[];
}

const props = defineProps<{
    /** 评论提交地址（按固定链接设置生成） */
    actionUrl: string;
    comments: CommentNode[];
}>();

const page = usePage();
const user = computed(() => page.props.auth?.user);

// 顶层发表
const content = ref('');
const guestName = ref('');
const guestEmail = ref('');
const captchaAnswer = ref('');
const turnstileToken = ref('');
const captcha = ref<{ type: 'math' | 'turnstile'; question?: string; sitekey?: string } | null>(null);
const submitting = ref(false);
// 楼中楼回复目标（评论 id + 作者名）
const replyTo = ref<CommentNode | null>(null);
const replyContent = ref('');

async function refreshCaptcha() {
    const res = await fetch('/comment/captcha', { headers: { Accept: 'application/json' } });
    captcha.value = await res.json();

    // Turnstile：动态渲染 widget，拿 token
    if (captcha.value?.type === 'turnstile') {
        setTimeout(() => {
            const el = document.getElementById('comment-turnstile');
            const w = (window as any).turnstile;
            if (el && w) {
                w.render(el, {
                    sitekey: captcha.value!.sitekey,
                    callback: (token: string) => { turnstileToken.value = token; },
                });
            }
        }, 100);
    }
}

// 游客评论需要验证码：挂载即拉取题目
onMounted(() => {
    if (!user.value) refreshCaptcha();
});

function submit() {
    if (submitting.value || content.value.trim().length < 2) return;
    submitting.value = true;
    router.post(props.actionUrl, {
        content: content.value,
        author_name: guestName.value,
        author_email: guestEmail.value,
        captcha_answer: captchaAnswer.value,
        'cf-turnstile-response': turnstileToken.value,
    }, {
        preserveScroll: true,
        onSuccess: () => { content.value = ''; captchaAnswer.value = ''; refreshCaptcha(); },
        onError: () => { if (captcha.value?.type === 'math') refreshCaptcha(); },
        onFinish: () => { submitting.value = false; },
    });
}

function submitReply() {
    if (submitting.value || !replyTo.value || replyContent.value.trim().length < 2) return;
    submitting.value = true;
    router.post(props.actionUrl, {
        content: replyContent.value,
        parent_id: replyTo.value.id,
    }, {
        preserveScroll: true,
        onSuccess: () => { replyContent.value = ''; replyTo.value = null; },
        onFinish: () => { submitting.value = false; },
    });
}

function startReply(comment: CommentNode) {
    replyTo.value = comment;
}
</script>

<template>
    <Card>
        <CardContent class="p-6">
            <h2 class="text-lg font-semibold">评论 <span class="ml-1 text-sm text-muted-foreground">({{ props.comments.length }})</span></h2>

            <!-- 发表评论（登录用户直接评；游客填昵称+邮箱+验证码） -->
            <div class="mt-4">
                <template v-if="user">
                    <div v-if="replyTo" class="mb-2 flex items-center justify-between rounded-md bg-muted px-3 py-1.5 text-sm">
                        <span>回复 <span class="font-medium">@{{ replyTo.author_name }}</span></span>
                        <button type="button" class="text-xs text-muted-foreground hover:text-foreground"
                                @click="replyTo = null">取消</button>
                    </div>

                    <MarkdownEditor v-if="!replyTo" v-model="content" :rows="4" placeholder="写下你的评论…支持 Markdown" />
                    <MarkdownEditor v-else v-model="replyContent" :rows="3" placeholder="回复内容…" />

                    <div class="mt-2 flex justify-end">
                        <Button size="sm" :disabled="submitting" @click="replyTo ? submitReply() : submit()">
                            {{ submitting ? '提交中…' : (replyTo ? '发表回复' : '发表评论') }}
                        </Button>
                    </div>
                </template>

                <!-- 游客评论表单 -->
                <template v-else>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <input v-model="guestName" type="text" placeholder="昵称 *"
                               class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                        <input v-model="guestEmail" type="email" placeholder="邮箱 *（不公开）"
                               class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                    </div>

                    <MarkdownEditor v-if="!replyTo" v-model="content" class="mt-3" :rows="4" placeholder="写下你的评论…支持 Markdown" />
                    <MarkdownEditor v-else v-model="replyContent" class="mt-3" :rows="3" placeholder="回复内容…" />

                    <div v-if="!replyTo && captcha?.type === 'math'" class="mt-3 flex items-center gap-3">
                        <span class="text-sm text-muted-foreground">验证码：<span class="font-mono text-primary">{{ captcha.question }}</span></span>
                        <input v-model="captchaAnswer" type="number" placeholder="答案" required
                               class="h-9 w-24 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                    </div>
                    <div v-else-if="!replyTo && captcha?.type === 'turnstile'" id="comment-turnstile" class="mt-3" />

                    <div class="mt-2 flex justify-end">
                        <Button size="sm" :disabled="submitting" @click="replyTo ? submitReply() : submit()">
                            {{ submitting ? '提交中…' : (replyTo ? '发表回复' : '发表评论') }}
                        </Button>
                    </div>
                </template>
            </div>

            <!-- 评论列表 -->
            <ul class="mt-6 space-y-6">
                <li v-for="comment in props.comments" :key="comment.id">
                    <!-- 单条评论渲染 -->
                    <div class="flex gap-3">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-secondary text-sm font-semibold text-secondary-foreground">
                            {{ comment.author_name.slice(0, 1) }}
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                                <span class="font-medium">{{ comment.author_name }}</span>
                                <!-- UA 追踪识别徽标 -->
                                <Badge variant="outline" class="px-1.5 py-0 text-[11px] font-normal text-muted-foreground">
                                    {{ comment.os }} · {{ comment.browser }} · {{ comment.device }}
                                </Badge>
                                <span class="text-xs text-muted-foreground">{{ comment.created_at }}</span>
                            </div>

                            <!-- 服务端已 Markdown 渲染并转义 HTML 输入 -->
                            <div class="prose prose-sm prose-zinc mt-1 max-w-none leading-relaxed" v-html="comment.html" />

                            <button v-if="user" type="button"
                                    class="mt-1 text-xs text-muted-foreground transition hover:text-foreground"
                                    @click="startReply(comment)">
                                回复
                            </button>

                            <!-- 楼中楼 -->
                            <div v-if="comment.replies.length" class="mt-3 space-y-3 rounded-lg bg-muted/50 p-3">
                                <div v-for="reply in comment.replies" :key="reply.id" class="flex gap-2.5">
                                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-background text-xs font-semibold">
                                        {{ reply.author_name.slice(0, 1) }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-x-2 text-sm">
                                            <span class="font-medium">{{ reply.author_name }}</span>
                                            <span v-if="reply.reply_to && reply.reply_to !== reply.author_name"
                                                  class="text-xs text-muted-foreground">@{{ reply.reply_to }}</span>
                                            <Badge variant="outline" class="px-1.5 py-0 text-[11px] font-normal text-muted-foreground">
                                                {{ reply.browser }}
                                            </Badge>
                                            <span class="text-xs text-muted-foreground">{{ reply.created_at }}</span>
                                        </div>
                                        <div class="prose prose-sm prose-zinc mt-0.5 max-w-none leading-relaxed" v-html="reply.html" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </li>
            </ul>

            <p v-if="props.comments.length === 0" class="mt-6 rounded-md border border-dashed p-8 text-center text-sm text-muted-foreground">
                还没有评论，来抢沙发～
            </p>
        </CardContent>
    </Card>
</template>
