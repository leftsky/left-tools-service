<script setup lang="ts">
import Layout from '@/components/Layout.vue';
</script>

<template>
    <Layout title="视频格式转换 - 小左子的工具箱">
            <!-- 页面标题区域 -->
            <div class="bg-white dark:bg-gray-800 shadow-sm">
                <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center space-x-4">
                        <div class="h-12 w-12 rounded-lg bg-blue-600 flex items-center justify-center">
                            <svg class="h-8 w-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">视频格式转换</h1>
                            <p class="text-gray-600 dark:text-gray-400">支持多种格式视频转换，快速高效</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 转换工具区域 -->
            <div class="max-w-4xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-8">
                    <!-- 文件上传区域 -->
                    <div class="mb-8">
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">选择视频文件</h2>
                        <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-8 text-center hover:border-blue-400 dark:hover:border-blue-400 transition-colors">
                            <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <div class="mt-4">
                                <label for="file-upload" class="cursor-pointer bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md transition-colors">
                                    选择文件
                                </label>
                                <input id="file-upload" name="file-upload" type="file" class="sr-only" accept="video/*" />
                                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                    支持 MP4, AVI, MOV, MKV, WMV 等格式
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- 转换选项 -->
                    <div class="mb-8">
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">转换选项</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- 输出格式 -->
                            <div>
                                <label for="output-format" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    输出格式
                                </label>
                                <select id="output-format" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    <option value="mp4">MP4</option>
                                    <option value="avi">AVI</option>
                                    <option value="mov">MOV</option>
                                    <option value="mkv">MKV</option>
                                    <option value="wmv">WMV</option>
                                    <option value="flv">FLV</option>
                                </select>
                            </div>

                            <!-- 视频质量 -->
                            <div>
                                <label for="video-quality" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    视频质量
                                </label>
                                <select id="video-quality" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    <option value="high">高质量</option>
                                    <option value="medium">中等质量</option>
                                    <option value="low">低质量</option>
                                </select>
                            </div>

                            <!-- 分辨率 -->
                            <div>
                                <label for="resolution" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    分辨率
                                </label>
                                <select id="resolution" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    <option value="original">保持原分辨率</option>
                                    <option value="4k">4K (3840x2160)</option>
                                    <option value="1080p">1080p (1920x1080)</option>
                                    <option value="720p">720p (1280x720)</option>
                                    <option value="480p">480p (854x480)</option>
                                </select>
                            </div>

                            <!-- 帧率 -->
                            <div>
                                <label for="framerate" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    帧率
                                </label>
                                <select id="framerate" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    <option value="original">保持原帧率</option>
                                    <option value="60">60 FPS</option>
                                    <option value="30">30 FPS</option>
                                    <option value="25">25 FPS</option>
                                    <option value="24">24 FPS</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- 转换按钮 -->
                    <div class="text-center">
                        <button type="button" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-8 rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <svg class="inline-block h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                            </svg>
                            开始转换
                        </button>
                    </div>

                    <!-- 转换进度 -->
                    <div class="mt-8 hidden">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">转换进度</h3>
                        <div class="bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                            <div class="bg-blue-600 h-2 rounded-full" style="width: 0%"></div>
                        </div>
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">正在转换中，请稍候...</p>
                    </div>
                </div>

                <!-- 功能说明 -->
                <div class="mt-8 bg-white dark:bg-gray-800 rounded-lg shadow-lg p-8">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">功能说明</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">支持的输入格式</h3>
                            <ul class="text-gray-600 dark:text-gray-400 space-y-1">
                                <li>• MP4 (H.264, H.265)</li>
                                <li>• AVI (Xvid, DivX)</li>
                                <li>• MOV (QuickTime)</li>
                                <li>• MKV (Matroska)</li>
                                <li>• WMV (Windows Media)</li>
                                <li>• FLV (Flash Video)</li>
                            </ul>
                        </div>
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">支持的输出格式</h3>
                            <ul class="text-gray-600 dark:text-gray-400 space-y-1">
                                <li>• MP4 (H.264, H.265)</li>
                                <li>• AVI (Xvid)</li>
                                <li>• MOV (QuickTime)</li>
                                <li>• MKV (Matroska)</li>
                                <li>• WMV (Windows Media)</li>
                                <li>• FLV (Flash Video)</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
    </Layout>
</template> 