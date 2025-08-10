<script setup>
import { ref, onMounted, onUnmounted } from "vue";
import Layout from "@/components/Layout.vue";
import FileConversionAPI from "@/services/fileConversionAPI.js";

// 响应式数据
const isConverting = ref(false);
const isUploading = ref(false);
const progress = ref(0);
const selectedFile = ref(null);
const downloadUrl = ref("");
const message = ref("请选择视频文件开始转换");

// 错误弹窗
const showErrorModal = ref(false);
const errorMessage = ref("");

// 文件信息
const fileInfo = ref(null);

// 转换选项
const outputFormat = ref("mp4");
const videoQuality = ref("high");
const resolution = ref("original");
const framerate = ref("original");
const conversionEngine = ref("cloudconvert");
const useDirectUpload = ref(false); // 默认使用直传

// 任务相关
const currentTaskId = ref(null);
const cancelPolling = ref(null);

// 支持的格式
const supportedFormats = ref(null);

// 格式选项常量
const OUTPUT_FORMATS = [
  { value: "mp4", label: "MP4 (H.264)" },
  { value: "avi", label: "AVI" },
  { value: "mov", label: "MOV (QuickTime)" },
  { value: "mkv", label: "MKV (Matroska)" },
  { value: "wmv", label: "WMV (Windows Media)" },
  { value: "flv", label: "FLV (Flash Video)" },
  { value: "webm", label: "WebM (VP8/VP9)" },
  { value: "gif", label: "GIF (动画)" },
  { value: "m4v", label: "M4V (iTunes)" },
  { value: "3gp", label: "3GP (移动设备)" },
  { value: "ogv", label: "OGV (Ogg Video)" },
];

const VIDEO_QUALITY_OPTIONS = [
  { value: "high", label: "高质量" },
  { value: "medium", label: "中等质量" },
  { value: "low", label: "低质量" },
];

const RESOLUTION_OPTIONS = [
  { value: "original", label: "原始分辨率" },
  { value: "1920x1080", label: "1080p (1920x1080)" },
  { value: "1280x720", label: "720p (1280x720)" },
  { value: "854x480", label: "480p (854x480)" },
  { value: "640x360", label: "360p (640x360)" },
];

const FRAMERATE_OPTIONS = [
  { value: "original", label: "原始帧率" },
  { value: "30", label: "30 FPS" },
  { value: "25", label: "25 FPS" },
  { value: "24", label: "24 FPS" },
  { value: "15", label: "15 FPS" },
  { value: "10", label: "10 FPS" },
];

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

// 初始化
onMounted(async () => {
  // 直接设置默认消息，不显示加载过程
  setMessage("请选择视频文件开始转换");

  // 静默加载支持的格式
  try {
    const result = await FileConversionAPI.getSupportedFormats();
    if (result.code === 1) {
      supportedFormats.value = result.data;
    }
  } catch (error) {
    console.error("格式加载失败:", error);
    // 静默处理错误，不影响用户体验
  }
});

// 清理资源
onUnmounted(() => {
  if (cancelPolling.value) {
    cancelPolling.value();
  }
});

// 文件选择处理
const handleFileSelect = async (event) => {
  const target = event.target;
  if (target.files && target.files[0]) {
    selectedFile.value = target.files[0];
    // 重置之前的结果
    downloadUrl.value = "";
    progress.value = 0;
    fileInfo.value = null;
    setMessage(`已选择文件: ${selectedFile.value.name}`);

    // 读取文件信息
    await readFileInfo();
  }
};

// 拖拽处理
const handleDrop = async (event) => {
  event.preventDefault();
  if (event.dataTransfer?.files && event.dataTransfer.files[0]) {
    selectedFile.value = event.dataTransfer.files[0];
    downloadUrl.value = "";
    progress.value = 0;
    fileInfo.value = null;
    setMessage(`已选择文件: ${selectedFile.value.name}`);

    // 读取文件信息
    await readFileInfo();
  }
};

const handleDragOver = (event) => {
  event.preventDefault();
};

// 读取文件信息
const readFileInfo = async () => {
  if (!selectedFile.value) {
    return;
  }

  try {
    setMessage("正在读取文件信息...");

    // 获取文件基本信息
    fileInfo.value = {
      name: selectedFile.value.name,
      size: selectedFile.value.size,
      sizeFormatted: FileConversionAPI.formatFileSize(selectedFile.value.size),
      type: selectedFile.value.type,
      format: selectedFile.value.name.split(".").pop()?.toLowerCase() || "unknown",
      lastModified: new Date(selectedFile.value.lastModified).toLocaleString(),
    };

    setMessage(
      `文件信息读取成功: ${fileInfo.value.sizeFormatted}, ${fileInfo.value.format} 格式`
    );
  } catch (error) {
    console.error("读取文件信息失败:", error);
    setMessage("读取文件信息失败，但可以继续转换");
    fileInfo.value = null;
  }
};

// 转换视频
const convertVideo = async () => {
  if (!selectedFile.value) {
    alert("请先选择视频文件");
    return;
  }

  // 检查文件大小（限制为1GB）
  const maxSize = 1024 * 1024 * 1024; // 1GB
  if (selectedFile.value.size > maxSize) {
    alert("文件过大，请选择小于1GB的文件");
    return;
  }

  console.log("开始转换:", selectedFile.value.name, "→", outputFormat.value);

  isConverting.value = true;
  isUploading.value = true;
  progress.value = 0;
  setMessage("正在上传文件...");

  try {
    // 准备转换选项
    const conversionOptions = [];

    if (videoQuality.value !== "high") {
      conversionOptions.push({ key: "quality", value: videoQuality.value });
    }
    if (resolution.value !== "original") {
      conversionOptions.push({ key: "resolution", value: resolution.value });
    }
    if (framerate.value !== "original") {
      conversionOptions.push({ key: "fps", value: parseInt(framerate.value) });
    }

    let result;

    // 根据设置选择上传方式
    if (useDirectUpload.value && conversionEngine.value === "cloudconvert") {
      // 使用客户端直传
      setMessage("正在创建直传任务...");

      result = await FileConversionAPI.directUploadToCloudConvert(selectedFile.value, {
        outputFormat: outputFormat.value,
        engine: conversionEngine.value,
        conversionOptions,
        onProgress: (percent) => {
          progress.value = percent;
          setMessage(`正在直传文件... ${percent}%`);
        },
      });

      currentTaskId.value = result.task_id;
      isUploading.value = false;
      setMessage("文件直传完成，开始转换...");
    } else {
      // 使用传统上传方式
      result = await FileConversionAPI.uploadAndConvert(selectedFile.value, {
        outputFormat: outputFormat.value,
        engine: conversionEngine.value,
        conversionOptions,
      });

      if (result.code !== 1) {
        throw new Error(result.message || "转换任务创建失败");
      }

      currentTaskId.value = result.data.task_id;
      isUploading.value = false;
      setMessage("文件上传完成，开始转换...");
    }

    // 开始轮询状态
    cancelPolling.value = FileConversionAPI.pollStatus(
      result.data.task_id,
      // 进度回调
      (data) => {
        progress.value = data.step_percent || 0;
        setMessage(`转换中... ${data.step_percent || 0}%`);
      },
      // 完成回调
      (data) => {
        progress.value = 100;
        setMessage("转换完成！");
        isConverting.value = false;

        // 如果有输出URL，设置下载链接
        if (data.output_url) {
          downloadUrl.value = data.output_url;
        }

        // 记录工具使用
        recordToolUsage();
      },
      // 错误回调
      (error) => {
        console.error("转换失败:", error);
        setMessage("转换失败，请检查文件格式或重试");
        showErrorDialog(error.message || "转换失败，请检查文件格式或重试");
        isConverting.value = false;
      }
    );
  } catch (error) {
    console.error("转换失败:", error);
    setMessage("转换失败，请检查文件格式或重试");
    showErrorDialog(
      error instanceof Error ? error.message : "转换失败，请检查文件格式或重试"
    );
    isConverting.value = false;
    isUploading.value = false;
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
  if (downloadUrl.value && selectedFile.value) {
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
                    v-if="fileInfo"
                    class="mt-4 p-3 bg-gray-50 dark:bg-gray-700 rounded-md"
                  >
                    <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-2">
                      文件信息
                    </h4>
                    <div class="space-y-2 text-xs text-gray-600 dark:text-gray-400">
                      <div class="grid grid-cols-2 gap-2">
                        <div>文件名: {{ fileInfo.name }}</div>
                        <div>文件大小: {{ fileInfo.sizeFormatted }}</div>
                        <div>文件格式: {{ fileInfo.format }}</div>
                        <div>修改时间: {{ fileInfo.lastModified }}</div>
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

              <!-- 直传选项 -->
              <div
                class="hidden mb-6 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-md"
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
                        d="M13 10V3L4 14h7v7l9-11h-7z"
                      ></path>
                    </svg>
                    <div>
                      <h4 class="text-sm font-medium text-green-800 dark:text-green-200">
                        客户端直传（推荐）
                      </h4>
                      <p class="text-sm text-green-700 dark:text-green-300">
                        文件直接从浏览器上传到 CloudConvert，节省服务器带宽
                      </p>
                    </div>
                  </div>
                  <label class="relative inline-flex items-center cursor-pointer">
                    <input
                      type="checkbox"
                      v-model="useDirectUpload"
                      :disabled="conversionEngine !== 'cloudconvert'"
                      class="sr-only peer"
                    />
                    <div
                      class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"
                    ></div>
                  </label>
                </div>
                <div
                  v-if="conversionEngine !== 'cloudconvert'"
                  class="mt-2 text-xs text-green-600 dark:text-green-400"
                >
                  直传功能仅支持 CloudConvert 引擎
                </div>
              </div>

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
            <div class="text-center space-y-4">
              <button
                @click="convertVideo"
                :disabled="!selectedFile || isConverting || isUploading"
                class="bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed text-white font-medium py-3 px-8 rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
              >
                <svg
                  v-if="!isConverting && !isUploading"
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
                {{ isUploading ? "上传中..." : isConverting ? "转换中..." : "开始转换" }}
              </button>


            </div>

            <!-- 上传进度 -->
            <div v-if="isUploading" class="mt-8">
              <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                文件上传进度
              </h3>
              <div class="flex items-center justify-between mb-2">
                <span class="text-sm text-gray-600 dark:text-gray-400"
                  >正在上传文件，请稍候...</span
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
            <div v-if="isConverting && !isUploading" class="mt-8">
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
              v-if="downloadUrl && !isConverting"
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
