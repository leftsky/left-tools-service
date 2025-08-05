<script setup lang="ts">
import { ref } from "vue";
import Layout from "@/components/Layout.vue";
import axios from "axios";

// 响应式数据
const shareText = ref("");
const isParsing = ref(false);
const parsedVideo = ref<{
  url: string;
  title: string;
  video_id: string;
} | null>(null);
const errorMessage = ref("");
const successMessage = ref("");

// 解析视频
const parseVideo = async () => {
  if (!shareText.value.trim()) {
    errorMessage.value = "请输入抖音分享文本或链接";
    return;
  }

  isParsing.value = true;
  errorMessage.value = "";
  successMessage.value = "";
  parsedVideo.value = null;

  try {
    const response = await axios.post("/api/tools/parse-douyin", {
      share_text: shareText.value.trim()
    });

    if (response.data.code === 1) {
      parsedVideo.value = response.data.data;
      successMessage.value = "视频解析成功！";
    } else {
      errorMessage.value = response.data.message || "解析失败";
    }
  } catch (error: any) {
    console.error("解析失败:", error);
    if (error.response?.data?.message) {
      errorMessage.value = error.response.data.message;
    } else {
      errorMessage.value = "网络错误，请稍后重试";
    }
  } finally {
    isParsing.value = false;
  }
};

// 下载视频
const downloadVideo = (url: string, title: string) => {
  const link = document.createElement('a');
  link.href = url;
  link.download = `${title || '抖音视频'}.mp4`;
  link.target = '_blank';
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
};

// 粘贴剪贴板内容
const pasteFromClipboard = async () => {
  try {
    const text = await navigator.clipboard.readText();
    shareText.value = text;
    successMessage.value = "已从剪贴板粘贴内容";
    setTimeout(() => {
      successMessage.value = "";
    }, 2000);
  } catch (error) {
    console.error("粘贴失败:", error);
    errorMessage.value = "粘贴失败，请手动输入";
  }
};

// 清空内容
const clearContent = () => {
  shareText.value = "";
  parsedVideo.value = null;
  errorMessage.value = "";
  successMessage.value = "";
};
</script>

<template>
  <Layout title="抖音视频去水印 - 小左子的工具箱">
    <div class="min-h-screen bg-gray-50 dark:bg-gray-900 py-12">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- 页面标题 -->
        <div class="text-center mb-12">
          <h1 class="text-4xl font-bold text-gray-900 dark:text-white mb-4">
            抖音视频去水印
          </h1>
          <p class="text-xl text-gray-600 dark:text-gray-400">
            解析抖音分享链接，下载无水印视频
          </p>
        </div>

        <!-- 主要功能区域 -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-8">
          <!-- 输入区域 -->
          <div class="mb-8">
            <label
              for="shareText"
              class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
            >
              抖音分享文本或链接
            </label>
            <div class="flex space-x-4">
              <textarea
                id="shareText"
                v-model="shareText"
                rows="3"
                placeholder="请输入抖音分享文本或链接，例如：https://v.douyin.com/xxxxx/ 或者完整的分享文本"
                class="flex-1 px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white resize-none"
                :disabled="isParsing"
              ></textarea>
              <div class="flex flex-col space-y-2 self-start">
                <button
                  @click="pasteFromClipboard"
                  class="bg-gray-600 hover:bg-gray-700 text-white font-medium py-3 px-6 rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500"
                >
                  <svg
                    class="inline-block h-5 w-5 mr-2"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
                    ></path>
                  </svg>
                  粘贴
                </button>
                <button
                  @click="parseVideo"
                  :disabled="isParsing || !shareText.trim()"
                  class="bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed text-white font-medium py-3 px-6 rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                >
                  <svg
                    v-if="!isParsing"
                    class="inline-block h-5 w-5 mr-2"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
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
                  {{ isParsing ? "解析中..." : "解析视频" }}
                </button>
              </div>
            </div>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
              支持抖音分享链接或完整的分享文本，系统会自动解析并提供下载
            </p>
          </div>

          <!-- 错误消息 -->
          <div
            v-if="errorMessage"
            class="mb-6 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md"
          >
            <div class="flex items-center">
              <svg
                class="h-5 w-5 text-red-400 mr-2"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                ></path>
              </svg>
              <span class="text-red-800 dark:text-red-200">{{ errorMessage }}</span>
            </div>
          </div>

          <!-- 成功消息 -->
          <div
            v-if="successMessage"
            class="mb-6 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-md"
          >
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
              <span class="text-green-800 dark:text-green-200">{{ successMessage }}</span>
            </div>
          </div>

          <!-- 解析结果 -->
          <div v-if="parsedVideo" class="mb-6">
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                解析结果
              </h3>
              <button
                @click="clearContent"
                class="bg-gray-600 hover:bg-gray-700 text-white font-medium py-2 px-4 rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500"
              >
                清空
              </button>
            </div>
            
            <div class="bg-gray-50 dark:bg-gray-700 rounded-md p-6">
              <div class="space-y-4">
                <!-- 视频标题 -->
                <div v-if="parsedVideo.title">
                  <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    视频标题
                  </h4>
                  <p class="text-gray-900 dark:text-gray-100">{{ parsedVideo.title }}</p>
                </div>

                <!-- 视频ID -->
                <div v-if="parsedVideo.video_id">
                  <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    视频ID
                  </h4>
                  <p class="text-gray-900 dark:text-gray-100 font-mono text-sm">{{ parsedVideo.video_id }}</p>
                </div>

                <!-- 下载按钮 -->
                <div>
                  <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    无水印视频下载
                  </h4>
                  <div class="text-center">
                    <button
                      @click="downloadVideo(parsedVideo.url, parsedVideo.title)"
                      class="bg-green-600 hover:bg-green-700 text-white font-medium py-3 px-8 rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 text-lg"
                    >
                      <svg
                        class="inline-block h-6 w-6 mr-2"
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
                      下载无水印视频
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- 使用说明 -->
          <div class="bg-blue-50 dark:bg-blue-900/20 rounded-md p-6">
            <h3 class="text-lg font-medium text-blue-900 dark:text-blue-100 mb-4">
              使用说明
            </h3>
            <div class="space-y-3 text-blue-800 dark:text-blue-200">
              <div class="flex items-start">
                <span class="flex-shrink-0 w-6 h-6 bg-blue-200 dark:bg-blue-800 rounded-full flex items-center justify-center text-blue-600 dark:text-blue-400 text-sm font-medium mr-3 mt-0.5">
                  1
                </span>
                <p>复制抖音视频的分享文本或链接</p>
              </div>
              <div class="flex items-start">
                <span class="flex-shrink-0 w-6 h-6 bg-blue-200 dark:bg-blue-800 rounded-full flex items-center justify-center text-blue-600 dark:text-blue-400 text-sm font-medium mr-3 mt-0.5">
                  2
                </span>
                <p>点击"粘贴"按钮或手动粘贴到输入框中</p>
              </div>
              <div class="flex items-start">
                <span class="flex-shrink-0 w-6 h-6 bg-blue-200 dark:bg-blue-800 rounded-full flex items-center justify-center text-blue-600 dark:text-blue-400 text-sm font-medium mr-3 mt-0.5">
                  3
                </span>
                <p>点击"解析视频"按钮，等待系统解析</p>
              </div>
              <div class="flex items-start">
                <span class="flex-shrink-0 w-6 h-6 bg-blue-200 dark:bg-blue-800 rounded-full flex items-center justify-center text-blue-600 dark:text-blue-400 text-sm font-medium mr-3 mt-0.5">
                  4
                </span>
                <p>点击"下载无水印视频"按钮保存到本地</p>
              </div>
            </div>
          </div>

          <!-- 注意事项 -->
          <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-md p-6 mt-6">
            <h3 class="text-lg font-medium text-yellow-900 dark:text-yellow-100 mb-4">
              注意事项
            </h3>
            <div class="space-y-2 text-yellow-800 dark:text-yellow-200 text-sm">
              <p>• 本工具仅用于个人学习和研究用途</p>
              <p>• 请尊重原创作者的版权，不要用于商业用途</p>
              <p>• 下载的视频仅供个人使用，请勿传播或二次发布</p>
              <p>• 如遇到解析失败，可能是链接已失效或格式不支持</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </Layout>
</template> 