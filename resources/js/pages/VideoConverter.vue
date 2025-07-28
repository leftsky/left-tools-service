<script setup lang="ts">
import { ref, onMounted } from 'vue';
import Layout from '@/components/Layout.vue';
import { FFmpeg } from '@ffmpeg/ffmpeg';
import { fetchFile, toBlobURL } from '@ffmpeg/util';

// 响应式数据
const ffmpeg = ref<FFmpeg | null>(null);
const isLoaded = ref(false);
const isConverting = ref(false);
const progress = ref(0);
const selectedFile = ref<File | null>(null);
const convertedBlob = ref<Blob | null>(null);
const downloadUrl = ref<string>('');

// 转换选项
const outputFormat = ref('mp4');
const videoQuality = ref('high');
const resolution = ref('original');
const framerate = ref('original');

// 初始化FFmpeg
onMounted(async () => {
    ffmpeg.value = new FFmpeg();
    
    // 加载FFmpeg
    try {
        await ffmpeg.value.load({
            coreURL: await toBlobURL('https://unpkg.com/@ffmpeg/core@0.12.6/dist/umd/ffmpeg-core.js', 'text/javascript'),
            wasmURL: await toBlobURL('https://unpkg.com/@ffmpeg/core@0.12.6/dist/umd/ffmpeg-core.wasm', 'application/wasm'),
            workerURL: await toBlobURL('https://unpkg.com/@ffmpeg/core@0.12.6/dist/umd/ffmpeg-worker.js', 'text/javascript'),
        });
        isLoaded.value = true;
    } catch (error) {
        console.error('FFmpeg加载失败:', error);
        alert('FFmpeg加载失败，请刷新页面重试');
    }
});

// 文件选择处理
const handleFileSelect = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        selectedFile.value = target.files[0];
        // 重置之前的结果
        convertedBlob.value = null;
        downloadUrl.value = '';
        progress.value = 0;
    }
};

// 拖拽处理
const handleDrop = (event: DragEvent) => {
    event.preventDefault();
    if (event.dataTransfer?.files && event.dataTransfer.files[0]) {
        selectedFile.value = event.dataTransfer.files[0];
        convertedBlob.value = null;
        downloadUrl.value = '';
        progress.value = 0;
    }
};

const handleDragOver = (event: DragEvent) => {
    event.preventDefault();
};

// 转换视频
const convertVideo = async () => {
    if (!selectedFile.value || !ffmpeg.value || !isLoaded.value) {
        alert('请先选择视频文件');
        return;
    }

    isConverting.value = true;
    progress.value = 0;

    try {
        // 写入输入文件
        await ffmpeg.value.writeFile('input.' + getFileExtension(selectedFile.value.name), await fetchFile(selectedFile.value));

        // 构建FFmpeg命令
        const commandParts = buildFFmpegCommand();
        
        // 执行转换
        await ffmpeg.value.exec(commandParts);

        // 读取输出文件
        const data = await ffmpeg.value.readFile(`output.${outputFormat.value}`);
        convertedBlob.value = new Blob([data], { type: `video/${outputFormat.value}` });
        
        // 创建下载链接
        downloadUrl.value = URL.createObjectURL(convertedBlob.value);
        
        progress.value = 100;
    } catch (error) {
        console.error('转换失败:', error);
        alert('视频转换失败，请检查文件格式或重试');
    } finally {
        isConverting.value = false;
    }
};

// 构建FFmpeg命令
const buildFFmpegCommand = () => {
    const inputExt = getFileExtension(selectedFile.value!.name);
    const outputExt = outputFormat.value;
    
    const commandParts = ['-i', `input.${inputExt}`];
    
    // 视频质量设置
    switch (videoQuality.value) {
        case 'high':
            commandParts.push('-crf', '18');
            break;
        case 'medium':
            commandParts.push('-crf', '23');
            break;
        case 'low':
            commandParts.push('-crf', '28');
            break;
    }
    
    // 分辨率设置
    if (resolution.value !== 'original') {
        const resolutions = {
            '4k': '3840:2160',
            '1080p': '1920:1080',
            '720p': '1280:720',
            '480p': '854:480'
        };
        commandParts.push('-vf', `scale=${resolutions[resolution.value as keyof typeof resolutions]}`);
    }
    
    // 帧率设置
    if (framerate.value !== 'original') {
        commandParts.push('-r', framerate.value);
    }
    
    // 输出格式特定设置
    switch (outputExt) {
        case 'mp4':
            commandParts.push('-c:v', 'libx264', '-c:a', 'aac');
            break;
        case 'avi':
            commandParts.push('-c:v', 'libx264', '-c:a', 'mp3');
            break;
        case 'mov':
            commandParts.push('-c:v', 'libx264', '-c:a', 'aac');
            break;
        case 'mkv':
            commandParts.push('-c:v', 'libx264', '-c:a', 'aac');
            break;
        case 'wmv':
            commandParts.push('-c:v', 'wmv2', '-c:a', 'wmav2');
            break;
        case 'flv':
            commandParts.push('-c:v', 'libx264', '-c:a', 'mp3');
            break;
    }
    
    commandParts.push(`output.${outputExt}`);
    return commandParts;
};

// 获取文件扩展名
const getFileExtension = (filename: string) => {
    return filename.split('.').pop()?.toLowerCase() || 'mp4';
};

// 下载转换后的文件
const downloadFile = () => {
    if (convertedBlob.value && downloadUrl.value) {
        const a = document.createElement('a');
        a.href = downloadUrl.value;
        a.download = `converted.${outputFormat.value}`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }
};


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
                <!-- FFmpeg加载状态 -->
                <div v-if="!isLoaded" class="mb-8 p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-md">
                    <div class="flex items-center">
                        <svg class="h-5 w-5 text-yellow-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span class="text-yellow-800 dark:text-yellow-200">正在加载FFmpeg，请稍候...</span>
                    </div>
                </div>

                <!-- 文件上传区域 -->
                <div class="mb-8">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">选择视频文件</h2>
                    <div 
                        @drop="handleDrop"
                        @dragover="handleDragOver"
                        class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-8 text-center hover:border-blue-400 dark:hover:border-blue-400 transition-colors"
                        :class="{ 'border-blue-400 bg-blue-50 dark:bg-blue-900/20': selectedFile }"
                    >
                        <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <div class="mt-4">
                            <label for="file-upload" class="cursor-pointer bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md transition-colors">
                                选择文件
                            </label>
                            <input 
                                id="file-upload" 
                                name="file-upload" 
                                type="file" 
                                class="sr-only" 
                                accept="video/*" 
                                @change="handleFileSelect"
                            />
                            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                支持 MP4, AVI, MOV, MKV, WMV 等格式
                            </p>
                            <p v-if="selectedFile" class="mt-2 text-sm text-blue-600 dark:text-blue-400">
                                已选择: {{ selectedFile.name }}
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
                            <select 
                                id="output-format" 
                                v-model="outputFormat"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                            >
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
                            <select 
                                id="video-quality" 
                                v-model="videoQuality"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                            >
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
                            <select 
                                id="resolution" 
                                v-model="resolution"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                            >
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
                            <select 
                                id="framerate" 
                                v-model="framerate"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                            >
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
                    <button 
                        @click="convertVideo"
                        :disabled="!selectedFile || !isLoaded || isConverting"
                        class="bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed text-white font-medium py-3 px-8 rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                    >
                        <svg v-if="!isConverting" class="inline-block h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                        </svg>
                        <svg v-else class="inline-block h-5 w-5 mr-2 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        {{ isConverting ? '转换中...' : '开始转换' }}
                    </button>
                </div>

                <!-- 转换进度 -->
                <div v-if="isConverting" class="mt-8">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">转换进度</h3>
                    <div class="bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                        <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" :style="{ width: progress + '%' }"></div>
                    </div>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">正在转换中，请稍候...</p>
                </div>

                <!-- 下载区域 -->
                <div v-if="convertedBlob && downloadUrl" class="mt-8 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-md">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <svg class="h-5 w-5 text-green-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span class="text-green-800 dark:text-green-200">转换完成！</span>
                        </div>
                        <button 
                            @click="downloadFile"
                            class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-md transition-colors"
                        >
                            <svg class="inline-block h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            下载文件
                        </button>
                    </div>
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
                <div class="mt-6 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-md">
                    <div class="flex items-start">
                        <svg class="h-5 w-5 text-blue-400 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <div>
                            <h4 class="text-sm font-medium text-blue-800 dark:text-blue-200">隐私保护</h4>
                            <p class="text-sm text-blue-700 dark:text-blue-300 mt-1">
                                所有视频转换都在您的浏览器本地进行，文件不会上传到服务器，确保您的隐私安全。
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Layout>
</template> 