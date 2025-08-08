<script setup>
import { ref, onMounted } from "vue";
import Layout from "@/components/Layout.vue";
import ffmpegConverter, {
  OUTPUT_FORMATS,
  VIDEO_QUALITY_OPTIONS,
  RESOLUTION_OPTIONS,
  FRAMERATE_OPTIONS,
} from "@/utils/ffmpeg";

// 响应式数据
const isLoaded = ref(false);
const isConverting = ref(false);
const isLoading = ref(false);
const progress = ref(0);
const selectedFile = ref(null);
const convertedBlob = ref(null);
const downloadUrl = ref("");
const message = ref("请选择视频文件开始转换");

// 错误弹窗
const showErrorModal = ref(false);
const errorMessage = ref("");

// 视频信息
const videoInfo = ref(null);

// 设置消息的辅助函数
const setMessage = (msg) => {
  message.value = msg;
  console.log(`[VideoConverter] ${msg}`);
};

// 显示错误弹窗
const showErrorDialog = (msg) => {
  errorMessage.value = msg;
  showErrorModal.value = true;
};

// 转换选项
const outputFormat = ref("mp4");
const videoQuality = ref("high");
const resolution = ref("original");
const framerate = ref("original");

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

    await ffmpegConverter.init((msg) => {
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
const handleFileSelect = async (event) => {
  const target = event.target;
  if (target.files && target.files[0]) {
    selectedFile.value = target.files[0];
    // 重置之前的结果
    convertedBlob.value = null;
    downloadUrl.value = "";
    progress.value = 0;
    videoInfo.value = null;
    setMessage(`已选择文件: ${selectedFile.value.name}`);

    // 读取视频信息
    await readVideoInfo();
  }
};

// 拖拽处理
const handleDrop = async (event) => {
  console.log(event);
  event.preventDefault();
  if (event.dataTransfer?.files && event.dataTransfer.files[0]) {
    selectedFile.value = event.dataTransfer.files[0];
    convertedBlob.value = null;
    downloadUrl.value = "";
    progress.value = 0;
    videoInfo.value = null;
    setMessage(`已选择文件: ${selectedFile.value.name}`);

    // 读取视频信息
    await readVideoInfo();
  }
};

const handleDragOver = (event) => {
  event.preventDefault();
};

// 读取视频信息
const readVideoInfo = async () => {
  if (!selectedFile.value) {
    return;
  }

  if (!isLoaded.value) {
    setMessage("资源尚未加载完成，无法读取视频信息");
    return;
  }

  try {
    setMessage("正在读取视频信息...");

    // 使用 ffmpegConverter.getFileInfo 获取详细的文件信息
    const fileInfo = await ffmpegConverter.getFileInfo(selectedFile.value, (msg) => {
      setMessage(msg);
    });

    // 转换文件信息格式以匹配现有的 videoInfo 结构
    videoInfo.value = {
      duration: fileInfo.duration || 0,
      fps: fileInfo.video?.framerate || 30,
      totalFrames: fileInfo.duration
        ? Math.round(fileInfo.duration * (fileInfo.video?.framerate || 30))
        : 0,
      resolution: fileInfo.video?.resolution || "未知",
      bitrate: fileInfo.bitrateFormatted || "未知",
      format: fileInfo.format,
      // 添加更多详细信息
      size: fileInfo.sizeFormatted,
      videoCodec: fileInfo.video?.codec || "未知",
      audioCodec: fileInfo.audio?.codec || "未知",
      streams: fileInfo.streams,
    };

    setMessage(
      `视频信息读取成功: ${fileInfo.video?.resolution || "未知分辨率"}, ${
        fileInfo.durationFormatted || "未知时长"
      }, ${fileInfo.video?.framerate || "未知"}fps`
    );
  } catch (error) {
    console.error("读取视频信息失败:", error);
    setMessage("读取视频信息失败，但可以继续转换");
    videoInfo.value = null;
  }
};

// 转换视频
const convertVideo = async () => {
  if (!selectedFile.value) {
    alert("请先选择视频文件");
    return;
  }

  // 检查文件大小（限制为100MB）
  const maxSize = 100 * 1024 * 1024; // 100MB
  if (selectedFile.value.size > maxSize) {
    alert("文件过大，请选择小于100MB的文件");
    return;
  }

  console.log("开始转换:", selectedFile.value.name, "→", outputFormat.value);

  isConverting.value = true;
  progress.value = 0;
  setMessage("正在加载资源...");

  try {
    // 检查FFmpeg是否已加载
    if (!isLoaded.value) {
      setMessage("资源尚未加载完成，请稍候...");
      return;
    }

    setMessage("开始转换...");
    progress.value = 0;

    const options = {
      outputFormat: outputFormat.value,
      videoQuality: videoQuality.value,
      resolution: resolution.value,
      framerate: framerate.value,
    };

    const result = await ffmpegConverter.convert(
      selectedFile.value,
      options,
      (msg, progressValue) => {
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
    showErrorDialog(
      error instanceof Error ? error.message : "转换失败，请检查文件格式或重试"
    );
  } finally {
    isConverting.value = false;
    isLoading.value = false;
  }
};

// 记录工具使用
const recordToolUsage = async () => {
  try {
    const response = await fetch("/api/tools/record-usage-public", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      body: JSON.stringify({
        tool_name: "视频转码",
      }),
    });

    if (response.ok) {
      console.log("工具使用记录已保存");
    } else {
      console.warn("工具使用记录失败:", response.status);
    }
  } catch (error) {
    console.warn("记录工具使用时出错:", error);
  }
};

// 下载转换后的文件
const downloadFile = async () => {
  if (convertedBlob.value && downloadUrl.value && selectedFile.value) {
    const a = document.createElement("a");
    a.href = downloadUrl.value;

    // 获取原始文件名（不含扩展名）
    const originalName = selectedFile.value.name;
    const nameWithoutExt = originalName.substring(0, originalName.lastIndexOf("."));

    // 使用原始文件名 + 新的输出格式
    a.download = `${nameWithoutExt}.${outputFormat.value}`;

    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);

    // 记录工具使用
    await recordToolUsage();
  }
};
</script>

<template>
  <Layout title="视频格式转换工具 - 在线MP4、AVI、MOV、MKV转换器 | 格式转换大王">
    <!-- 主要内容区域 -->
    <div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
      <div class="grid grid-cols-1 lg:grid-cols-10 gap-8">
        <!-- 左侧功能说明 -->
        <div class="lg:col-span-4 order-2 lg:order-1">
          <div
            class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 lg:sticky lg:top-8"
          >
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
                  <li>• M4V (iTunes)</li>
                  <li>• 3GP (Mobile)</li>
                  <li>• OGV (Ogg Video)</li>
                  <li>• TS/MTS (Transport Stream)</li>
                  <li>• RM/RMVB (RealMedia)</li>
                  <li>• ASF (Advanced Systems)</li>
                  <li>• VOB (DVD Video)</li>
                  <li>• MPG/MPEG (MPEG-1/2)</li>
                  <li>• SWF/F4V (Flash)</li>
                  <li>• M2TS (Blu-ray)</li>
                  <li>• MXF (Material Exchange)</li>
                  <li>• GIF/APNG (Animated)</li>
                  <li>• WebP/AVIF (Web Images)</li>
                  <li>• HEIC/HEIF (High Efficiency)</li>
                  <li>• 以及更多图片格式...</li>
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
                  <li>• M4V (iTunes)</li>
                  <li>• 3GP (Mobile)</li>
                  <li>• OGV (Ogg Video)</li>
                  <li>• TS/MTS (Transport Stream)</li>
                  <li>• ASF (Advanced Systems)</li>
                  <li>• VOB (DVD Video)</li>
                  <li>• MPG/MPEG (MPEG-1/2)</li>
                  <li>• SWF/F4V (Flash)</li>
                  <li>• M2TS (Blu-ray)</li>
                  <li>• MXF (Material Exchange)</li>
                  <li>• GIF/APNG (Animated)</li>
                  <li>• WebP/AVIF (Web Images)</li>
                  <li>• HEIC/HEIF (High Efficiency)</li>
                  <li>• 以及更多图片格式...</li>
                </ul>
              </div>
            </div>

            <div class="mt-6 space-y-4">
              <div
                class="p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-md"
              >
                <div class="flex items-start">
                  <svg
                    class="h-5 w-5 text-green-400 mr-2 mt-0.5"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                    ></path>
                  </svg>
                  <div>
                    <h4 class="text-sm font-medium text-green-800 dark:text-green-200">
                      智能分离转码
                    </h4>
                    <p class="text-sm text-green-700 dark:text-green-300 mt-1">
                      系统采用分离式转码技术，分别处理视频和音频，有效避免转换卡住问题，提供更稳定的转换体验。
                    </p>
                    <div class="mt-2 text-xs text-green-600 dark:text-green-400">
                      <div>• 自动检测音频流并选择最佳处理方式</div>
                      <div>• 视频和音频独立处理，避免相互影响</div>
                      <div>• 支持多种音频编码器，兼容性更好</div>
                    </div>
                  </div>
                </div>
              </div>

              <div
                class="p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-md"
              >
                <div class="flex items-start">
                  <svg
                    class="h-5 w-5 text-blue-400 mr-2 mt-0.5"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M13 10V3L4 14h7v7l9-11h-7z"
                    ></path>
                  </svg>
                  <div>
                    <h4 class="text-sm font-medium text-blue-800 dark:text-blue-200">
                      强大的格式转换支持
                    </h4>
                    <p class="text-sm text-blue-700 dark:text-blue-300 mt-1">
                      基于融茂软件核心，支持超过50种视频格式和编码器，包括最新的AV1、H.265等先进编码技术。
                    </p>
                    <div class="mt-2 text-xs text-blue-600 dark:text-blue-400">
                      <div>• 支持H.264、H.265、VP8、VP9、AV1等现代编码</div>
                      <div>• 兼容MPEG-1/2、Xvid、DivX等传统格式</div>
                      <div>• 支持GIF、WebP、AVIF等图片和动画格式</div>
                      <div>• 专业级MXF、M2TS等广播级格式支持</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- 右侧转换工具 -->
        <div class="lg:col-span-6 order-1 lg:order-2">
          <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-8">
            <!-- 状态消息 -->
            <div
              class="mb-6 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-md"
            >
              <div class="flex items-center">
                <svg
                  class="h-5 w-5 text-blue-400 mr-2"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                  ></path>
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
                <svg
                  class="mx-auto h-12 w-12 text-gray-400"
                  stroke="currentColor"
                  fill="none"
                  viewBox="0 0 48 48"
                >
                  <path
                    d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  />
                </svg>
                <div class="mt-4">
                  <label
                    for="file-upload"
                    class="cursor-pointer bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md transition-colors"
                  >
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
                    支持 MP4, AVI, MOV, MKV, WMV, WebM, M4V, 3GP, OGV, TS, MTS, RM, RMVB,
                    ASF, VOB, MPG, MPEG, DIVX, XVID, SWF, F4V, M2TS, MXF, GIF, APNG, WebP,
                    AVIF, HEIC, HEIF 等格式
                  </p>
                  <p
                    v-if="selectedFile"
                    class="mt-2 text-sm text-blue-600 dark:text-blue-400"
                  >
                    已选择: {{ selectedFile.name }}
                  </p>
                  <div
                    v-if="videoInfo"
                    class="mt-4 p-3 bg-gray-50 dark:bg-gray-700 rounded-md"
                  >
                    <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-2">
                      视频信息
                    </h4>
                    <div class="space-y-2 text-xs text-gray-600 dark:text-gray-400">
                      <!-- 基本信息 -->
                      <div class="grid grid-cols-2 gap-2">
                        <div>文件大小: {{ videoInfo.size }}</div>
                        <div>格式: {{ videoInfo.format }}</div>
                        <div>
                          时长:
                          {{
                            videoInfo.duration > 0
                              ? videoInfo.duration.toFixed(2) + "秒"
                              : "未知"
                          }}
                        </div>
                        <div>流数量: {{ videoInfo.streams }}</div>
                      </div>

                      <!-- 视频信息 -->
                      <div v-if="videoInfo.resolution !== '未知'" class="border-t pt-2">
                        <div class="font-medium text-gray-700 dark:text-gray-300 mb-1">
                          视频信息
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                          <div>分辨率: {{ videoInfo.resolution }}</div>
                          <div>帧率: {{ videoInfo.fps }}fps</div>
                          <div>编码: {{ videoInfo.videoCodec }}</div>
                          <div>比特率: {{ videoInfo.bitrate }}</div>
                        </div>
                      </div>

                      <!-- 音频信息 -->
                      <div v-if="videoInfo.audioCodec !== '未知'" class="border-t pt-2">
                        <div class="font-medium text-gray-700 dark:text-gray-300 mb-1">
                          音频信息
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                          <div>编码: {{ videoInfo.audioCodec }}</div>
                          <div>声道: 未知</div>
                        </div>
                      </div>
                    </div>
                  </div>
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
                  <label
                    for="output-format"
                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                  >
                    输出格式
                  </label>
                  <select
                    id="output-format"
                    v-model="outputFormat"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                  >
                    <option
                      v-for="format in OUTPUT_FORMATS"
                      :key="format.value"
                      :value="format.value"
                    >
                      {{ format.label }}
                    </option>
                  </select>
                </div>

                <!-- 视频质量 -->
                <div>
                  <label
                    for="video-quality"
                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                  >
                    视频质量
                  </label>
                  <select
                    id="video-quality"
                    v-model="videoQuality"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                  >
                    <option
                      v-for="quality in VIDEO_QUALITY_OPTIONS"
                      :key="quality.value"
                      :value="quality.value"
                    >
                      {{ quality.label }}
                    </option>
                  </select>
                </div>

                <!-- 分辨率 -->
                <div>
                  <label
                    for="resolution"
                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                  >
                    分辨率
                  </label>
                  <select
                    id="resolution"
                    v-model="resolution"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                  >
                    <option
                      v-for="res in RESOLUTION_OPTIONS"
                      :key="res.value"
                      :value="res.value"
                    >
                      {{ res.label }}
                    </option>
                  </select>
                </div>

                <!-- 帧率 -->
                <div>
                  <label
                    for="framerate"
                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                  >
                    帧率
                  </label>
                  <select
                    id="framerate"
                    v-model="framerate"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                  >
                    <option
                      v-for="fps in FRAMERATE_OPTIONS"
                      :key="fps.value"
                      :value="fps.value"
                    >
                      {{ fps.label }}
                    </option>
                  </select>
                </div>
              </div>
            </div>

            <!-- 转换按钮 -->
            <div class="text-center">
              <button
                @click="convertVideo"
                :disabled="!selectedFile || !videoInfo || isConverting || !isLoaded"
                class="bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed text-white font-medium py-3 px-8 rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
              >
                <svg
                  v-if="!isConverting"
                  class="inline-block h-5 w-5 mr-2"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"
                  ></path>
                </svg>
                <svg
                  v-else
                  class="inline-block h-5 w-5 mr-2 animate-spin"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                  ></path>
                </svg>
                {{ isConverting ? "转换中..." : "开始转换" }}
              </button>
            </div>

            <!-- 加载进度 -->
            <div v-if="isLoading" class="mt-8">
              <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                资源加载进度
              </h3>
              <div class="flex items-center justify-between mb-2">
                <span class="text-sm text-gray-600 dark:text-gray-400"
                  >正在加载核心文件，请稍候...</span
                >
                <span class="text-sm font-medium text-blue-600 dark:text-blue-400"
                  >{{ Math.round(progress) }}%</span
                >
              </div>
              <div class="bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                <div
                  class="bg-blue-600 h-2 rounded-full transition-all duration-300"
                  :style="{ width: progress + '%' }"
                ></div>
              </div>
            </div>

            <!-- 转换进度 -->
            <div v-if="isConverting && !isLoading" class="mt-8">
              <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                转换进度
              </h3>
              <div class="flex items-center justify-between mb-2">
                <span class="text-sm text-gray-600 dark:text-gray-400"
                  >正在转换中，请稍候...</span
                >
                <span class="text-sm font-medium text-blue-600 dark:text-blue-400"
                  >{{ Math.round(progress) }}%</span
                >
              </div>
              <div class="bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                <div
                  class="bg-blue-600 h-2 rounded-full transition-all duration-300"
                  :style="{ width: progress + '%' }"
                ></div>
              </div>
            </div>

            <!-- 下载区域 -->
            <div
              v-if="convertedBlob && downloadUrl"
              class="mt-8 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-md"
            >
              <div class="flex items-center justify-between">
                <div class="flex items-center">
                  <svg
                    class="h-5 w-5 text-green-400 mr-2"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                    ></path>
                  </svg>
                  <span class="text-green-800 dark:text-green-200">转换完成！</span>
                </div>
                <button
                  @click="downloadFile"
                  class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-md transition-colors"
                >
                  <svg
                    class="inline-block h-4 w-4 mr-1"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                    ></path>
                  </svg>
                  下载文件
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 错误弹窗 -->
    <div
      v-if="showErrorModal"
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
    >
      <div
        class="bg-white dark:bg-gray-800 rounded-lg p-6 max-w-md w-full mx-4 shadow-xl"
      >
        <div class="flex items-center mb-4">
          <div class="flex-shrink-0">
            <svg
              class="h-6 w-6 text-red-400"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"
              ></path>
            </svg>
          </div>
          <div class="ml-3">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">转换失败</h3>
          </div>
        </div>
        <div class="mt-2">
          <p class="text-sm text-gray-500 dark:text-gray-400 whitespace-pre-line">
            {{ errorMessage }}
          </p>
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
  </Layout>
</template>
