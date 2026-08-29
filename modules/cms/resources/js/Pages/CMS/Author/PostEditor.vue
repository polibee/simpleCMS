<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AuthorLayout from './AuthorLayout.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import CardContent from '@/components/ui/CardContent.vue';
import Input from '@/components/ui/Input.vue';
import RichTextEditor from '@/components/cms/RichTextEditor.vue';

interface EditorPost {
    id: number;
    title: string;
    slug: string;
    content: string;
    excerpt: string | null;
    status: string;
    locale: string;
    category_ids: number[];
}

const props = defineProps<{
    post: EditorPost | null;
    categories: { id: number; name: string }[];
}>();

const page = usePage();
const flash = computed(() => (page.props.flash as any) ?? {});
const isEdit = computed(() => props.post !== null);

const form = reactive({
    title: props.post?.title ?? '',
    excerpt: props.post?.excerpt ?? '',
    status: props.post?.status ?? 'published',
    content: props.post?.content ?? '',
    locale: props.post?.locale ?? 'zh',
    category_ids: [...(props.post?.category_ids ?? [])] as number[],
});

const errors = reactive<Record<string, string>>({});
const submitting = ref(false);

function submit(status: 'draft' | 'published') {
    Object.keys(errors).forEach((k) => delete errors[k]);
    form.status = status;

    const payload = {
        title: form.title,
        excerpt: form.excerpt || null,
        status: form.status,
        content: form.content,
        locale: form.locale,
        category_ids: form.category_ids,
    };

    submitting.value = true;

    if (isEdit.value && props.post) {
        router.put(`/studio/posts/${props.post.id}`, payload, {
            onError: (errs) => Object.assign(errors, errs),
            onFinish: () => (submitting.value = false),
        });
    } else {
        router.post('/studio/posts', payload, {
            onError: (errs) => Object.assign(errors, errs),
            onFinish: () => (submitting.value = false),
        });
    }
}

function toggleCategory(id: number) {
    const i = form.category_ids.indexOf(id);
    if (i >= 0) form.category_ids.splice(i, 1);
    else form.category_ids.push(id);
}
</script>

<template>
    <Head :title="isEdit ? '编辑文章' : '写文章'" />
    <AuthorLayout>
        <div class="mb-5 flex items-center justify-between">
            <h1 class="text-xl font-semibold">{{ isEdit ? '编辑文章' : '写新文章' }}</h1>
            <Button variant="ghost" size="sm" as="a" href="/studio/posts">← 返回列表</Button>
        </div>

        <div v-if="flash.success"
             class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300">
            {{ flash.success }}
        </div>

        <!-- 错误汇总 -->
        <div v-if="Object.keys(errors).length"
             class="mb-4 rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">
            <p v-for="(msg, field) in errors" :key="field">{{ msg }}</p>
        </div>

        <div class="space-y-4">
            <Card>
                <CardContent class="space-y-4 p-4">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">标题 <span class="text-destructive">*</span></label>
                        <Input v-model="form.title" placeholder="文章标题" class="text-base font-medium" />
                        <p v-if="errors.title" class="mt-1 text-xs text-destructive">{{ errors.title }}</p>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium">摘要（可选，列表页展示）</label>
                        <textarea v-model="form.excerpt" rows="2"
                                  class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                  placeholder="一句话概括本文内容…" />
                        <p v-if="errors.excerpt" class="mt-1 text-xs text-destructive">{{ errors.excerpt }}</p>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium">文章语言</label>
                        <select v-model="form.locale"
                                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                            <option value="zh">中文</option>
                            <option value="en">English</option>
                        </select>
                        <p class="mt-1 text-xs text-muted-foreground">前台根据浏览器语言优先展示对应语言的文章</p>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium">分类（可多选）</label>
                        <div class="flex flex-wrap gap-2">
                            <button v-for="cat in categories" :key="cat.id" type="button" @click.prevent="toggleCategory(cat.id)"
                                    class="rounded-full border px-3 py-1 text-xs transition"
                                    :class="form.category_ids.includes(cat.id)
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'border-input text-muted-foreground hover:bg-muted hover:text-foreground'">
                                {{ cat.name }}
                            </button>
                            <span v-if="!categories.length" class="text-xs text-muted-foreground">暂无分类（管理员可在后台创建）</span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- 正文富文本 -->
            <RichTextEditor v-model="form.content" class="[&_p]:my-0" />
            <p v-if="errors.content" class="text-xs text-destructive">{{ errors.content }}</p>

            <!-- 底部操作 -->
            <div class="flex items-center gap-3 pb-6">
                <Button size="lg" :disabled="submitting" @click="submit('published')">
                    {{ submitting ? '提交中…' : (isEdit && post?.status === 'published' ? '保存并更新' : '发布') }}
                </Button>
                <Button variant="secondary" size="lg" :disabled="submitting" @click="submit('draft')">存为草稿</Button>
                <span class="text-xs text-muted-foreground">正文支持加粗 / 标题 / 引用 / 列表 / 链接 / 图片等排版</span>
            </div>
        </div>
    </AuthorLayout>
</template>
