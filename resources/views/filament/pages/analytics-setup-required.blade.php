<x-filament-panels::page>
    <div class="max-w-2xl mx-auto">
        <div class="rounded-xl border border-amber-300 bg-amber-50 p-6 text-center dark:border-amber-500/30 dark:bg-amber-500/10">
            <svg class="mx-auto h-10 w-10 text-amber-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
            </svg>
            <h2 class="mt-3 text-lg font-semibold text-amber-800 dark:text-amber-300">Google Analytics 尚未配置完成</h2>
            <p class="mt-2 text-sm text-amber-700 dark:text-amber-300/80">
                要在此查看网站统计报表，请完成以下两步配置：
            </p>

            <div class="mt-4 space-y-2 text-left text-sm">
                <div class="flex items-start gap-2 rounded-lg bg-white/60 p-3 dark:bg-gray-900/40">
                    <span class="font-bold text-amber-600">1</span>
                    <span>在 <b>站点设置 → 网页统计分析设置</b> 填写 GA4 数字 Property ID，并粘贴服务账号 JSON</span>
                </div>
                <div class="flex items-start gap-2 rounded-lg bg-white/60 p-3 dark:bg-gray-900/40">
                    <span class="font-bold text-amber-600">2</span>
                    <span>确认已在 Google Analytics 后台将该服务账号添加为对应属性的<b>查看者</b></span>
                </div>
            </div>

            <p class="mt-4 text-xs text-amber-600/80 dark:text-amber-400/70">
                配置完成后本页面将自动显示统计报表。
            </p>
        </div>
    </div>
</x-filament-panels::page>
