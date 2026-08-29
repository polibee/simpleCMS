<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AuthorLayout from './AuthorLayout.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import CardContent from '@/components/ui/CardContent.vue';
import Input from '@/components/ui/Input.vue';
import MarkdownEditor from '@/components/cms/MarkdownEditor.vue';

const props = defineProps<{
    profile: {
        name: string;
        email: string;
        bio: string | null;
        website: string | null;
        donation_url: string | null;
        donation_text: string | null;
    };
}>();

const page = usePage();
const flash = computed(() => (page.props.flash as any) ?? {});

const form = reactive({
    name: props.profile.name,
    email: props.profile.email,
    bio: props.profile.bio ?? '',
    website: props.profile.website ?? '',
    donation_url: props.profile.donation_url ?? '',
    donation_text: props.profile.donation_text ?? '',
});
const errors = reactive<Record<string, string>>({});
const submitting = ref(false);

function submit() {
    Object.keys(errors).forEach((k) => delete errors[k]);
    submitting.value = true;
    router.put('/studio/settings', {
        name: form.name,
        email: form.email,
        bio: form.bio || null,
        website: form.website || null,
        donation_url: form.donation_url || null,
        donation_text: form.donation_text || null,
    }, {
        onError: (errs) => Object.assign(errors, errs),
        onFinish: () => (submitting.value = false),
    });
}
</script>

<template>
    <Head title="个人设置" />
    <AuthorLayout>
        <h1 class="mb-5 text-xl font-semibold">个人设置</h1>

        <div v-if="flash.success"
             class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300">
            {{ flash.success }}
        </div>
        <div v-if="Object.keys(errors).length"
             class="mb-4 rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">
            <p v-for="(msg, field) in errors" :key="field">{{ msg }}</p>
        </div>

        <div class="space-y-4">
            <Card>
                <CardContent class="space-y-4 p-5">
                    <h2 class="text-base font-medium">基础资料</h2>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium">昵称 <span class="text-destructive">*</span></label>
                        <Input v-model="form.name" />
                        <p v-if="errors.name" class="mt-1 text-xs text-destructive">{{ errors.name }}</p>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium">邮箱 <span class="text-destructive">*</span></label>
                        <Input v-model="form.email" type="email" />
                        <p v-if="errors.email" class="mt-1 text-xs text-destructive">{{ errors.email }}</p>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium">个人网站</label>
                        <Input v-model="form.website" type="url" placeholder="https://…" />
                        <p v-if="errors.website" class="mt-1 text-xs text-destructive">{{ errors.website }}</p>
                    </div>
                </CardContent>
            </Card>

            <!-- Markdown 自我介绍：个人主页简介卡片 -->
            <Card>
                <CardContent class="space-y-2 p-5">
                    <h2 class="text-base font-medium">个人简介（Markdown）</h2>
                    <p class="text-xs text-muted-foreground">
                        支持 Markdown 语法：标题、列表、引用、表格、图片、链接等；保存后在你的个人主页展示为精美简介卡片。
                    </p>
                    <MarkdownEditor v-model="form.bio" :rows="8"
                                    placeholder="# 关于我&#10;- 我是谁…&#10;[我的博客](https://…)" class="[&_textarea]:min-h-[160px]" />
                    <p v-if="errors.bio" class="text-xs text-destructive">{{ errors.bio }}</p>
                </CardContent>
            </Card>

            <!-- 创作者捐赠按钮：优先于站点默认配置展示在文章末尾 -->
            <Card>
                <CardContent class="space-y-4 p-5">
                    <h2 class="text-base font-medium">捐赠按钮</h2>
                    <p class="-mt-2 text-xs text-muted-foreground">填写后你的每篇文章末尾会显示专属捐赠按钮；留空则回退站点默认。</p>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium">捐赠链接</label>
                        <Input v-model="form.donation_url" type="url" placeholder="https://donatr.ee/yourname" />
                        <p v-if="errors.donation_url" class="mt-1 text-xs text-destructive">{{ errors.donation_url }}</p>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium">按钮文案</label>
                        <Input v-model="form.donation_text" placeholder="支持作者 / 请我喝杯咖啡…" maxlength="100" />
                        <p v-if="errors.donation_text" class="mt-1 text-xs text-destructive">{{ errors.donation_text }}</p>
                    </div>
                </CardContent>
            </Card>

            <Button :disabled="submitting" @click="submit">{{ submitting ? '保存中…' : '保存修改' }}</Button>
        </div>
    </AuthorLayout>
</template>
