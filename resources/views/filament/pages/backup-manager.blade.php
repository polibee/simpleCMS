<x-filament-panels::page>
    <div class="max-w-3xl space-y-4">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">数据库备份</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        使用 mysqldump 完整导出当前数据库（含结构与数据），保存到 <code class="text-xs">storage/app/backups</code>。
                    </p>
                </div>
                <button type="button" wire:click="backup" wire:loading.attr="disabled"
                        class="inline-flex h-9 items-center gap-2 rounded-lg bg-primary-600 px-4 text-sm font-medium text-white shadow-sm transition hover:bg-primary-700 disabled:opacity-50">
                    立即备份
                </button>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">备份文件</h3>

            @forelse ($this->backups() as $file)
                <div class="mt-3 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-gray-100 px-3 py-2 text-sm dark:border-white/5">
                    <div>
                        <span class="font-mono text-xs">{{ $file['name'] }}</span>
                        <span class="ml-2 text-xs text-gray-400">{{ $file['size'] }} · {{ $file['time'] }}</span>
                    </div>
                    <div class="flex gap-2">
                        <a href="{{ route('backup.download', ['name' => $file['name']]) }}"
                           class="rounded-md border px-2 py-1 text-xs text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-white/5">下载</a>
                        <button type="button" wire:click="remove('{{ $file['name'] }}')" wire:confirm="确定删除该备份文件？"
                                class="rounded-md border px-2 py-1 text-xs text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-white/5">删除</button>
                        <button type="button" wire:click="restore('{{ $file['name'] }}')"
                                wire:confirm="确定用 {{ $file['name'] }} 覆盖当前数据库？该操作不可撤销！"
                                class="rounded-md border border-amber-300 px-2 py-1 text-xs text-amber-700 hover:bg-amber-50 dark:border-amber-500/30 dark:text-amber-400 dark:hover:bg-amber-500/10">
                            恢复
                        </button>
                    </div>
                </div>
            @empty
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">暂无备份文件，点击「立即备份」创建第一个。</p>
            @endforelse
        </div>

        <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300">
            恢复将覆盖当前数据库的全部数据且不可撤销，操作前请确认备份文件正确。
        </div>

        <div class="rounded-xl border border-red-300 bg-red-50 p-6 shadow-sm dark:border-red-500/30 dark:bg-red-500/10">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-red-800 dark:text-red-300">一键清除测试数据</h2>
                    <p class="mt-1 text-sm text-red-600/80 dark:text-red-400/80">
                        清空开发/演示产生的业务数据（文章、评论、商品、订单、邀请码、广告、钱包、签到、单页、轮播图等），
                        <strong>保留</strong>：管理员账号、角色权限、站点设置、分类、媒体库、插件状态。
                    </p>
                </div>
                <button type="button" wire:click="clearTestData"
                        wire:confirm="确定清空全部业务数据？账号、设置、分类、媒体会保留，此操作不可撤销！建议先点击上方「立即备份」。"
                        class="inline-flex h-9 items-center gap-2 rounded-lg bg-red-600 px-4 text-sm font-medium text-white shadow-sm transition hover:bg-red-700">
                    清除测试数据
                </button>
            </div>
        </div>
    </div>
</x-filament-panels::page>
