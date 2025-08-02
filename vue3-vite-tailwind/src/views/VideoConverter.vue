<script setup lang="ts">
import { ref, onMounted, nextTick } from "vue";
import ffmpegConverter, { 
  OUTPUT_FORMATS, 
  VIDEO_QUALITY_OPTIONS, 
  RESOLUTION_OPTIONS, 
  FRAMERATE_OPTIONS 
} from "@/utils/ffmpeg";
import { downloadFile, validateVideoFile } from "@/utils/fileUtils";

// 响应式数据
const isLoaded = ref(false);
const isConverting = ref(false);
const isLoading = ref(false);
const progress = ref(0);
const selectedFile = ref<File | null>(null);
const convertedBlob = ref<Blob | null>(null);
const downloadUrl = ref("");
const message = ref("请选择视频文件开始转换");

// 测试相关数据
const isTesting = ref(false);
const testResults = ref<Array<{
  format: string;
  status: 'success' | 'failed' | 'pending';
  message: string;
  size?: number;
  duration?: number;
}>>([]);
const showTestResults = ref(false);

// 错误弹窗
const showErrorModal = ref(false);
const errorMessage = ref("");

// 转换选项
const outputFormat = ref("mp4");
const videoQuality = ref("high");
const resolution = ref("original");
const framerate = ref("original");

// 设置消息的辅助函数
const setMessage = (msg: string) => {
  message.value = msg;
  console.log(`[VideoConverter] ${msg}`);
};

// 显示错误弹窗
const showErrorDialog = (msg: string) => {
  errorMessage.value = msg;
  showErrorModal.value = true;
};

// 初始化FFmpeg
onMounted(async () => {
  try {
    isLoading.value = true;
    setMessage("正在加载资源...");

    // 模拟加载进度
    const progressInterval = setInterval(() => {
      if (progress.value < 80) {
        progress.value += 5;
        setMessage(`正在加载资源... ${progress.value}%`);
      }
    }, 200);

    await ffmpegConverter.init((msg: string) => {
      setMessage(msg);
    });

    clearInterval(progressInterval);
    progress.value = 100;
    setMessage("资源加载完成！");
    isLoaded.value = true;
    isLoading.value = false;

    // 等待一秒让用户看到加载完成
    await new Promise((resolve) => setTimeout(resolve, 1000));
    setMessage("请选择视频文件开始转换");
    progress.value = 0;
  } catch (error) {
    console.error("FFmpeg加载失败:", error);
    setMessage("资源加载失败，请刷新页面重试");
    isLoading.value = false;
  }
});

// 文件选择处理
const handleFileSelect = async (event: Event) => {
  const target = event.target as HTMLInputElement;
  if (target.files && target.files[0]) {
    try {
      const file = target.files[0];
      validateVideoFile(file);
      
      selectedFile.value = file;
      convertedBlob.value = null;
      downloadUrl.value = "";
      progress.value = 0;
      setMessage(`已选择文件: ${selectedFile.value.name}`);
    } catch (error) {
      showErrorDialog(error instanceof Error ? error.message : "文件选择失败");
    }
  }
};

// 拖拽处理
const handleDrop = async (event: DragEvent) => {
  event.preventDefault();
  if (event.dataTransfer?.files && event.dataTransfer.files[0]) {
    try {
      const file = event.dataTransfer.files[0];
      validateVideoFile(file);
      
      selectedFile.value = file;
      convertedBlob.value = null;
      downloadUrl.value = "";
      progress.value = 0;
      setMessage(`已选择文件: ${selectedFile.value.name}`);
    } catch (error) {
      showErrorDialog(error instanceof Error ? error.message : "文件选择失败");
    }
  }
};

const handleDragOver = (event: DragEvent) => {
  event.preventDefault();
};

// 转换视频
const convertVideo = async () => {
  if (!selectedFile.value) {
    showErrorDialog("请先选择视频文件");
    return;
  }

  isConverting.value = true;
  progress.value = 0;
  setMessage("开始转换...");

  try {
    const options = {
      outputFormat: outputFormat.value,
      videoQuality: videoQuality.value,
      resolution: resolution.value,
      framerate: framerate.value
    };

    const result = await ffmpegConverter.convert(
      selectedFile.value,
      options,
      (msg: string, progressValue?: number) => {
        setMessage(msg);
        if (progressValue !== undefined) {
          progress.value = progressValue;
        }
      }
    );

    convertedBlob.value = result.blob;
    downloadUrl.value = URL.createObjectURL(result.blob);
    progress.value = 100;
    setMessage("转换完成！");

  } catch (error) {
    console.error("转换失败:", error);
    setMessage("转换失败，请检查文件格式或重试");
    showErrorDialog(error instanceof Error ? error.message : "转换失败，请检查文件格式或重试");
  } finally {
    isConverting.value = false;
    isLoading.value = false;
  }
};

// 下载转换后的文件
const handleDownload = () => {
  if (convertedBlob.value && selectedFile.value) {
    const originalName = selectedFile.value.name;
    const nameWithoutExt = originalName.substring(0, originalName.lastIndexOf("."));
    const filename = `${nameWithoutExt}.${outputFormat.value}`;
    
    downloadFile(convertedBlob.value, filename);
  }
};

  // 批量测试转换
  const runFormatTest = async () => {
    if (!selectedFile.value) {
      showErrorDialog("请先选择视频文件");
      return;
    }

    isTesting.value = true;
    showTestResults.value = true;
    testResults.value = [];
    
    // 初始化测试结果
    const testFormats = OUTPUT_FORMATS.slice(0, 10); // 限制测试前10个格式，避免时间过长
    testResults.value = testFormats.map(format => ({
      format: format.value,
      status: 'pending' as const,
      message: '等待测试...'
    }));

    setMessage("开始批量格式测试...");

    // 串行执行测试，避免并发冲突
    for (let i = 0; i < testFormats.length; i++) {
      const format = testFormats[i];
      const startTime = Date.now();
      const currentFormat = format.value;
      const currentIndex = i;
      
      try {
        // 更新当前测试状态
        testResults.value[currentIndex].status = 'pending';
        testResults.value[currentIndex].message = '正在转换...';
        
        setMessage(`正在测试 ${format.label} 格式...`);

        const options = {
          outputFormat: format.value,
          videoQuality: 'medium',
          resolution: 'original',
          framerate: 'original'
        };

        // 为当前测试创建独立的进度回调，使用闭包捕获当前索引
        const progressCallback = (msg: string, progressValue?: number) => {
          const progress = progressValue !== undefined ? progressValue.toFixed(2) : '0.00';
          const message = `${msg} (${progress}%)`;
          
          // 直接更新当前测试项，避免索引混乱
          nextTick(() => {
            if (testResults.value[currentIndex] && 
                testResults.value[currentIndex].format === currentFormat) {
              testResults.value[currentIndex].message = message;
            }
          });
        };

        const result = await ffmpegConverter.convert(
          selectedFile.value,
          options,
          progressCallback
        );

        const duration = Date.now() - startTime;
        
        // 确保更新正确的项
        if (testResults.value[currentIndex] && testResults.value[currentIndex].format === currentFormat) {
          testResults.value[currentIndex].status = 'success';
          testResults.value[currentIndex].message = `转换成功 (${duration}ms)`;
          testResults.value[currentIndex].size = result.size;
          testResults.value[currentIndex].duration = duration;
        }

        setMessage(`${format.label} 测试完成`);

      } catch (error) {
        const duration = Date.now() - startTime;
        
        // 确保更新正确的项
        if (testResults.value[currentIndex] && testResults.value[currentIndex].format === currentFormat) {
          testResults.value[currentIndex].status = 'failed';
          testResults.value[currentIndex].message = `转换失败: ${error instanceof Error ? error.message : '未知错误'} (${duration}ms)`;
          testResults.value[currentIndex].duration = duration;
        }

        setMessage(`${format.label} 测试失败`);
      }

      // 增加延迟，确保FFmpeg实例完全清理
      await new Promise(resolve => setTimeout(resolve, 1000));
    }

    isTesting.value = false;
    setMessage("批量格式测试完成！");
  };


</script>

<template>
  <div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
    <div class="grid grid-cols-1 lg:grid-cols-10 gap-8">
      <!-- 左侧功能说明 -->
      <div class="lg:col-span-4 order-2 lg:order-1">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 lg:sticky lg:top-8">
          <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">
            在线视频格式转换工具
          </h2>
          <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
            专业的在线视频格式转换器，支持MP4、AVI、MOV、MKV等多种格式转换。无需下载软件，免费在线使用，快速高效。
          </p>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">
                支持的输入格式
              </h3>
              <ul class="text-gray-600 dark:text-gray-400 space-y-1 text-sm">
                <li>• MP4 (H.264, H.265)</li>
                <li>• AVI (Xvid, DivX)</li>
                <li>• MOV (QuickTime)</li>
                <li>• MKV (Matroska)</li>
                <li>• WMV (Windows Media)</li>
                <li>• FLV (Flash Video)</li>
                <li>• WebM (VP8, VP9, AV1)</li>
                <li>• 以及更多格式...</li>
              </ul>
            </div>
            <div>
              <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">
                支持的输出格式
              </h3>
              <ul class="text-gray-600 dark:text-gray-400 space-y-1 text-sm">
                <li>• MP4 (H.264, H.265)</li>
                <li>• AVI (Xvid)</li>
                <li>• MOV (QuickTime)</li>
                <li>• MKV (Matroska)</li>
                <li>• WMV (Windows Media)</li>
                <li>• FLV (Flash Video)</li>
                <li>• WebM (VP8, VP9, AV1)</li>
                <li>• 以及更多格式...</li>
              </ul>
            </div>
          </div>
        </div>
      </div>

      <!-- 右侧转换工具 -->
      <div class="lg:col-span-6 order-1 lg:order-2">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-8">
          <!-- 状态消息 -->
          <div class="mb-6 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-md">
            <div class="flex items-center">
              <svg class="h-5 w-5 text-blue-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
              <span class="text-blue-800 dark:text-blue-200">{{ message }}</span>
            </div>
          </div>

          <!-- 文件上传区域 -->
          <div class="mb-8">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">
              选择视频文件
            </h2>
            <div
              @drop="handleDrop"
              @dragover="handleDragOver"
              class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-8 text-center hover:border-blue-400 dark:hover:border-blue-400 transition-colors"
              :class="{
                'border-blue-400 bg-blue-50 dark:bg-blue-900/20': selectedFile,
              }"
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
                  支持 MP4, AVI, MOV, MKV, WMV, WebM, M4V, 3GP, OGV, TS, MTS 等格式
                </p>
                <p v-if="selectedFile" class="mt-2 text-sm text-blue-600 dark:text-blue-400">
                  已选择: {{ selectedFile.name }}
                </p>
              </div>
            </div>
          </div>

          <!-- 转换选项 -->
          <div class="mb-8">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">
              转换选项
            </h2>
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
                  <option v-for="format in OUTPUT_FORMATS" :key="format.value" :value="format.value">
                    {{ format.label }}
                  </option>
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
                  <option v-for="quality in VIDEO_QUALITY_OPTIONS" :key="quality.value" :value="quality.value">
                    {{ quality.label }}
                  </option>
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
                  <option v-for="res in RESOLUTION_OPTIONS" :key="res.value" :value="res.value">
                    {{ res.label }}
                  </option>
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
                  <option v-for="fps in FRAMERATE_OPTIONS" :key="fps.value" :value="fps.value">
                    {{ fps.label }}
                  </option>
                </select>
              </div>
            </div>
          </div>

          <!-- 转换按钮 -->
          <div class="text-center space-y-4">
            <button
              @click="convertVideo"
              :disabled="!selectedFile || isConverting || isTesting || !isLoaded"
              class="bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed text-white font-medium py-3 px-8 rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
            >
              <svg v-if="!isConverting" class="inline-block h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
              </svg>
              <svg v-else class="inline-block h-5 w-5 mr-2 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
              </svg>
              {{ isConverting ? "转换中..." : "开始转换" }}
            </button>

            <!-- 测试按钮 -->
            <div class="border-t pt-4">
              <button
                @click="runFormatTest"
                :disabled="!selectedFile || isConverting || isTesting || !isLoaded"
                class="bg-green-600 hover:bg-green-700 disabled:bg-gray-400 disabled:cursor-not-allowed text-white font-medium py-2 px-6 rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 text-sm"
              >
                <svg v-if="!isTesting" class="inline-block h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <svg v-else class="inline-block h-4 w-4 mr-2 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                {{ isTesting ? "测试中..." : "批量格式测试" }}
              </button>
              <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                测试前10个格式的转换兼容性
              </p>
            </div>
          </div>

          <!-- 加载进度 -->
          <div v-if="isLoading" class="mt-8">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
              资源加载进度
            </h3>
            <div class="flex items-center justify-between mb-2">
              <span class="text-sm text-gray-600 dark:text-gray-400">正在加载核心文件，请稍候...</span>
              <span class="text-sm font-medium text-blue-600 dark:text-blue-400">{{ Math.round(progress) }}%</span>
            </div>
            <div class="bg-gray-200 dark:bg-gray-700 rounded-full h-2">
              <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" :style="{ width: progress + '%' }"></div>
            </div>
          </div>

          <!-- 转换进度 -->
          <div v-if="isConverting && !isLoading" class="mt-8">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
              转换进度
            </h3>
            <div class="flex items-center justify-between mb-2">
              <span class="text-sm text-gray-600 dark:text-gray-400">正在转换中，请稍候...</span>
              <span class="text-sm font-medium text-blue-600 dark:text-blue-400">{{ Math.round(progress) }}%</span>
            </div>
            <div class="bg-gray-200 dark:bg-gray-700 rounded-full h-2">
              <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" :style="{ width: progress + '%' }"></div>
            </div>
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
                @click="handleDownload"
                class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-md transition-colors"
              >
                <svg class="inline-block h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                下载文件
              </button>
            </div>
          </div>

          <!-- 测试结果区域 -->
          <div v-if="showTestResults && testResults.length > 0" class="mt-8">
            <div class="mb-4">
              <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                格式测试结果
              </h3>
            </div>
            
            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 max-h-96 overflow-y-auto">
              <div class="space-y-2">
                <div v-for="(result, index) in testResults" :key="index" 
                     class="flex items-center justify-between p-3 rounded-md border"
                     :class="{
                       'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800': result.status === 'success',
                       'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800': result.status === 'failed',
                       'bg-yellow-50 dark:bg-yellow-900/20 border-yellow-200 dark:border-yellow-800': result.status === 'pending'
                     }">
                  <div class="flex items-center space-x-3">
                    <div class="flex-shrink-0">
                      <svg v-if="result.status === 'success'" class="h-5 w-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                      </svg>
                      <svg v-else-if="result.status === 'failed'" class="h-5 w-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                      </svg>
                      <svg v-else class="h-5 w-5 text-yellow-400 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                      </svg>
                    </div>
                    <div>
                      <div class="font-medium text-gray-900 dark:text-white">
                        {{ result.format.toUpperCase() }}
                      </div>
                      <div class="text-sm text-gray-600 dark:text-gray-400">
                        {{ result.message }}
                      </div>
                    </div>
                  </div>
                  <div class="text-right text-sm text-gray-500 dark:text-gray-400">
                    <div v-if="result.size">大小: {{ (result.size / 1024).toFixed(1) }}KB</div>
                    <div v-if="result.duration">耗时: {{ result.duration }}ms</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 错误弹窗 -->
    <div v-if="showErrorModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white dark:bg-gray-800 rounded-lg p-6 max-w-md w-full mx-4 shadow-xl">
        <div class="flex items-center mb-4">
          <div class="flex-shrink-0">
            <svg class="h-6 w-6 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
            </svg>
          </div>
          <div class="ml-3">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">转换失败</h3>
          </div>
        </div>
        <div class="mt-2">
          <p class="text-sm text-gray-500 dark:text-gray-400 whitespace-pre-line">{{ errorMessage }}</p>
        </div>
        <div class="mt-4 flex justify-end">
          <button
            @click="showErrorModal = false"
            class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md transition-colors"
          >
            确定
          </button>
        </div>
      </div>
    </div>
  </div>
</template> 