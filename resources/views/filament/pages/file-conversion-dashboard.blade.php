<x-filament-panels::page>
    <div class="space-y-6">
        <div class="text-center">
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                文件转换概览
            </h1>
            <p class="mt-2 text-gray-600 dark:text-gray-400">
                监控和管理文件转换任务的整体情况
            </p>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                    快速操作
                </h2>
                <div class="space-y-3">
                    <a href="{{ route('filament.admin.resources.file-conversion-tasks.index') }}" 
                       class="flex items-center p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg hover:bg-blue-100 dark:hover:bg-blue-900/30 transition-colors">
                        <div class="flex-shrink-0">
                            <x-heroicon-m-list-bullet class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-blue-900 dark:text-blue-100">查看所有任务</p>
                            <p class="text-xs text-blue-700 dark:text-blue-300">管理所有文件转换任务</p>
                        </div>
                    </a>

                    <a href="{{ route('filament.admin.resources.file-conversion-tasks.index', ['tableFilters[status][value]' => '3']) }}" 
                       class="flex items-center p-3 bg-red-50 dark:bg-red-900/20 rounded-lg hover:bg-red-100 dark:hover:bg-red-900/30 transition-colors">
                        <div class="flex-shrink-0">
                            <x-heroicon-m-exclamation-triangle class="w-6 h-6 text-red-600 dark:text-red-400" />
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-red-900 dark:text-red-100">查看失败任务</p>
                            <p class="text-xs text-red-700 dark:text-red-300">处理失败的任务</p>
                        </div>
                    </a>

                    <a href="{{ route('filament.admin.resources.file-conversion-tasks.index', ['tableFilters[status][value]' => '1']) }}" 
                       class="flex items-center p-3 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg hover:bg-yellow-100 dark:hover:bg-yellow-900/30 transition-colors">
                        <div class="flex-shrink-0">
                            <x-heroicon-m-arrow-path class="w-6 h-6 text-yellow-600 dark:text-yellow-400" />
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-yellow-900 dark:text-yellow-100">查看进行中任务</p>
                            <p class="text-xs text-yellow-700 dark:text-yellow-300">监控正在转换的任务</p>
                        </div>
                    </a>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                    系统信息
                </h2>
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600 dark:text-gray-400">支持的转换引擎</span>
                        <span class="text-sm font-medium text-gray-900 dark:text-white">Convertio, CloudConvert</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600 dark:text-gray-400">支持的文件格式</span>
                        <span class="text-sm font-medium text-gray-900 dark:text-white">视频、音频、图片</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600 dark:text-gray-400">最大文件大小</span>
                        <span class="text-sm font-medium text-gray-900 dark:text-white">1GB</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600 dark:text-gray-400">转换超时时间</span>
                        <span class="text-sm font-medium text-gray-900 dark:text-white">20分钟</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                使用说明
            </h2>
            <div class="prose dark:prose-invert max-w-none">
                <ul class="space-y-2 text-sm text-gray-600 dark:text-gray-400">
                    <li>• 使用 <strong>转换概览</strong> 查看整体统计信息和趋势</li>
                    <li>• 在 <strong>转换任务</strong> 中管理所有文件转换任务</li>
                    <li>• 转换任务通过 API 接口创建，不支持在后台手动创建</li>
                    <li>• 可以重试失败的任务或取消进行中的任务</li>
                    <li>• 使用筛选器快速找到特定状态或类型的任务</li>
                </ul>
            </div>
        </div>
    </div>
</x-filament-panels::page>
