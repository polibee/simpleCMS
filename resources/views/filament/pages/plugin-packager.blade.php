<x-filament-panels::page>
    <div class="max-w-xl space-y-4">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">打包插件为 zip 分发包</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                仅打包插件源码目录（自动排除 node_modules / vendor / .git / tests 等本地产物），
                产物同时保留在 <code class="text-xs">storage/app/plugin-builds</code>。
            </p>

            <div class="mt-5 space-y-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300" for="plugin-select">选择插件</label>
                <select id="plugin-select" wire:model="pluginId"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-950 dark:text-gray-200">
                    <option value="">— 请选择要打包的插件 —</option>
                    @foreach ($this->pluginOptions() as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </select>

                <div class="flex items-center gap-3">
                    <button type="button" wire:click="download"
                            class="inline-flex h-9 items-center gap-2 rounded-lg bg-primary-600 px-4 text-sm font-medium text-white shadow-sm transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500">
                        打包并下载
                    </button>
                </div>
            </div>
        </div>

        @php $options = $this->pluginOptions(); @endphp

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">已发现插件（{{ count($options) }}）</h3>
            <ul class="mt-3 space-y-1.5 text-sm text-gray-600 dark:text-gray-400">
                @forelse ($options as $id => $label)
                    <li class="flex items-center gap-2">
                        <span class="inline-block h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                        {{ $label }}
                    </li>
                @empty
                    <li>未发现任何插件，请先在「插件管理」执行发现。</li>
                @endforelse
            </ul>
        </div>
    </div>
</x-filament-panels::page>
