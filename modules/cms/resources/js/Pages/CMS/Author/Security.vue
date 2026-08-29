<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AuthorLayout from './AuthorLayout.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import CardContent from '@/components/ui/CardContent.vue';

const props = defineProps<{
    emailCodeEnabled: boolean;
    maskedEmail: string;
    codeSent: boolean;
}>();

const page = usePage();
const flash = computed(() => (page.props.flash as any) ?? {});

const form = reactive({
    current_password: '',
    password: '',
    password_confirmation: '',
    email_code: '',
});
const errors = reactive<Record<string, string>>({});
const submitting = ref(false);
const codeSent = ref(props.codeSent);

function sendCode() {
    router.post('/studio/settings/security/code', {}, {
        preserveScroll: true,
        onSuccess: () => (codeSent.value = true),
        onError: (errs) => Object.assign(errors, errs),
    });
}

function submit() {
    Object.keys(errors).forEach((k) => delete errors[k]);
    submitting.value = true;
    router.put('/studio/settings/security', { ...form }, {
        onSuccess: () => {
            form.current_password = '';
            form.password = '';
            form.password_confirmation = '';
            form.email_code = '';
            codeSent.value = false;
        },
        onError: (errs) => Object.assign(errors, errs),
        onFinish: () => (submitting.value = false),
    });
}
</script>

<template>
    <Head title="安全设置" />
    <AuthorLayout>
        <h1 class="mb-5 text-xl font-semibold">安全设置</h1>

        <div v-if="flash.success"
             class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300">
            {{ flash.success }}
        </div>
        <div v-if="flash.error"
             class="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-800 dark:bg-red-950/40 dark:text-red-300">
            {{ flash.error }}
        </div>
        <div v-if="Object.keys(errors).length"
             class="mb-4 rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">
            <p v-for="(msg, field) in errors" :key="field">{{ msg }}</p>
        </div>

        <Card>
            <CardContent class="space-y-4 p-5">
                <h2 class="text-base font-medium">修改密码</h2>

                <div>
                    <label class="mb-1.5 block text-sm font-medium">当前密码 <span class="text-destructive">*</span></label>
                    <input v-model="form.current_password" type="password" autocomplete="current-password"
                           class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" />
                    <p v-if="errors.current_password" class="mt-1 text-xs text-destructive">{{ errors.current_password }}</p>
                </div>

                <!-- 邮箱验证码（后台开关；发送至 {{ maskedEmail }}） -->
                <div v-if="emailCodeEnabled">
                    <label class="mb-1.5 block text-sm font-medium">邮箱验证码 <span class="text-destructive">*</span></label>
                    <div class="flex gap-2">
                        <input v-model="form.email_code" type="text" maxlength="6" inputmode="numeric"
                               class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" />
                        <Button variant="outline" type="button" :disabled="codeSent" @click="sendCode">
                            {{ codeSent ? '已发送' : '发送验证码' }}
                        </Button>
                    </div>
                    <p class="mt-1 text-xs text-muted-foreground">
                        验证码将发送到 {{ maskedEmail }}，10 分钟内有效
                    </p>
                    <p v-if="errors.email_code" class="mt-1 text-xs text-destructive">{{ errors.email_code }}</p>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium">新密码 <span class="text-destructive">*</span></label>
                    <input v-model="form.password" type="password" autocomplete="new-password"
                           class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" />
                    <p class="mt-1 text-xs text-muted-foreground">至少 8 位，建议混合字母、数字与符号。</p>
                    <p v-if="errors.password" class="mt-1 text-xs text-destructive">{{ errors.password }}</p>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium">确认新密码 <span class="text-destructive">*</span></label>
                    <input v-model="form.password_confirmation" type="password" autocomplete="new-password"
                           class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" />
                    <p v-if="errors.password_confirmation" class="mt-1 text-xs text-destructive">{{ errors.password_confirmation }}</p>
                </div>

                <Button :disabled="submitting" @click="submit">{{ submitting ? '提交中…' : '修改密码' }}</Button>
            </CardContent>
        </Card>
    </AuthorLayout>
</template>
