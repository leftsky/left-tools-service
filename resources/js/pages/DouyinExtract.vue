<script setup lang="ts">
import { ref } from "vue";
import Layout from "@/components/Layout.vue";
import axios from "axios";

// 响应式数据
const shareUrl = ref("");
const isExtracting = ref(false);
const extractedContent = ref("");
const errorMessage = ref("");
const successMessage = ref("");

// 提取文案
const extractContent = async () => {
  if (!shareUrl.value.trim()) {
    errorMessage.value = "请输入抖音分享链接";
    return;
  }

  // 验证链接格式
  if (!shareUrl.value.includes("douyin.com")) {
    errorMessage.value = "请输入有效的抖音分享链接";
    return;
  }

  isExtracting.value = true;
  errorMessage.value = "";
  successMessage.value = "";
  extractedContent.value = "";

  try {
    const response = await axios.post("/api/tools/extract-douyin", {
      share_url: shareUrl.value.trim()
    });

    if (response.data.code === 1) {
      extractedContent.value = response.data.data.content;
      successMessage.value = "文案提取成功！";
      
      // 记录使用
      try {
        await axios.post("/api/tools/record-usage-public", {
          tool_name: "抖音提取文案"
        });
      } catch (error) {
        console.log("记录使用失败:", error);
      }
    } else {
      errorMessage.value = response.data.message || "提取失败";
    }
  } catch (error: any) {
    console.error("提取失败:", error);
    if (error.response?.data?.message) {
      errorMessage.value = error.response.data.message;
    } else {
      errorMessage.value = "网络错误，请稍后重试";
    }
  } finally {
    isExtracting.value = false;
  }
};

// 复制文案
const copyContent = async () => {
  if (!extractedContent.value) return;
  
  try {
    await navigator.clipboard.writeText(extractedContent.value);
    successMessage.value = "文案已复制到剪贴板！";
    setTimeout(() => {
      successMessage.value = "";
    }, 2000);
  } catch (error) {
    console.error("复制失败:", error);
    errorMessage.value = "复制失败，请手动复制";
  }
};

// 清空内容
const clearContent = () => {
  shareUrl.value = "";
  extractedContent.value = "";
  errorMessage.value = "";
  successMessage.value = "";
};
</script>

<template>
  <Layout title="抖音文案提取 - 格式转换大王">
    <div class="min-h-screen bg-gray-50 dark:bg-gray-900 py-12">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- 页面标题 -->
        <div class="text-center mb-12">
          <h1 class="text-4xl font-bold text-gray-900 dark:text-white mb-4">
            抖音文案提取
          </h1>
          <p class="text-xl text-gray-600 dark:text-gray-400">
            快速提取抖音视频的文案内容，支持分享链接解析
          </p>
        </div>

        <!-- 主要功能区域 -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-8">
          <!-- 输入区域 -->
          <div class="mb-8">
            <label
              for="shareUrl"
              class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
            >
              抖音分享链接
            </label>
            <div class="flex space-x-4">
              <input
                id="shareUrl"
                v-model="shareUrl"
                type="text"
                placeholder="请输入抖音分享链接，例如：https://v.douyin.com/xxxxx/"
                class="flex-1 px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                :disabled="isExtracting"
              />
              <button
                @click="extractContent"
                :disabled="isExtracting || !shareUrl.trim()"
                class="bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed text-white font-medium py-3 px-6 rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
              >
                <svg
                  v-if="!isExtracting"
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
                {{ isExtracting ? "提取中..." : "提取文案" }}
              </button>
            </div>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
              支持抖音分享链接格式，系统会自动解析并提取视频文案
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

          <!-- 提取结果 -->
          <div v-if="extractedContent" class="mb-6">
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                提取结果
              </h3>
              <div class="flex space-x-2">
                <button
                  @click="copyContent"
                  class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500"
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
                      d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"
                    ></path>
                  </svg>
                  复制文案
                </button>
                <button
                  @click="clearContent"
                  class="bg-gray-600 hover:bg-gray-700 text-white font-medium py-2 px-4 rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500"
                >
                  清空
                </button>
              </div>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700 rounded-md p-4">
              <pre class="whitespace-pre-wrap text-gray-900 dark:text-gray-100 text-sm leading-relaxed">{{ extractedContent }}</pre>
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
                <p>复制抖音视频的分享链接</p>
              </div>
              <div class="flex items-start">
                <span class="flex-shrink-0 w-6 h-6 bg-blue-200 dark:bg-blue-800 rounded-full flex items-center justify-center text-blue-600 dark:text-blue-400 text-sm font-medium mr-3 mt-0.5">
                  2
                </span>
                <p>粘贴到输入框中，点击"提取文案"按钮</p>
              </div>
              <div class="flex items-start">
                <span class="flex-shrink-0 w-6 h-6 bg-blue-200 dark:bg-blue-800 rounded-full flex items-center justify-center text-blue-600 dark:text-blue-400 text-sm font-medium mr-3 mt-0.5">
                  3
                </span>
                <p>等待系统解析，提取完成后可复制文案内容</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </Layout>
</template> 