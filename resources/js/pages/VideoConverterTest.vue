<script setup lang="ts">
import { ref } from "vue";
import Layout from "@/components/Layout.vue";
import { FFmpeg } from "@ffmpeg/ffmpeg";
import { fetchFile, toBlobURL } from "@ffmpeg/util";

// 测试相关数据
const testLoaded = ref(false);
const testMessage = ref("点击加载FFmpeg");
const testVideoUrl = ref("");
let testFfmpeg: FFmpeg | null = null;

// 加载测试FFmpeg
const loadTestFfmpeg = async () => {
  if (!testFfmpeg) return;
  
  try {
    testMessage.value = "正在加载FFmpeg...";
    const baseURL = "https://cdn.jsdelivr.net/npm/@ffmpeg/core-mt@0.12.10/dist/esm";
    
    testFfmpeg.on('log', ({ message: msg }) => {
      testMessage.value = msg;
      console.log('[测试FFmpeg]', msg);
    });
    
    await testFfmpeg.load({
      coreURL: await toBlobURL(`${baseURL}/ffmpeg-core.js`, 'text/javascript'),
      wasmURL: await toBlobURL(`${baseURL}/ffmpeg-core.wasm`, 'application/wasm'),
      workerURL: await toBlobURL(`${baseURL}/ffmpeg-core.worker.js`, 'text/javascript'),
    });
    
    testLoaded.value = true;
    testMessage.value = "FFmpeg加载完成，点击开始转换";
  } catch (error) {
    console.error('测试FFmpeg加载失败:', error);
    testMessage.value = `加载失败: ${error.message}`;
  }
};

// 测试转换
const testTranscode = async () => {
  if (!testFfmpeg || !testLoaded.value) return;
  
  try {
    testMessage.value = "开始转换...";
    await testFfmpeg.writeFile('input.webm', await fetchFile('https://raw.githubusercontent.com/ffmpegwasm/testdata/master/Big_Buck_Bunny_180_10s.webm'));
    testMessage.value = "文件写入完成，开始转换...";
    
    await testFfmpeg.exec(['-i', 'input.webm', 'output.mp4']);
    testMessage.value = "转换完成，读取文件...";
    
    const data = await testFfmpeg.readFile('output.mp4');
    testVideoUrl.value = URL.createObjectURL(new Blob([(data as Uint8Array).buffer], {type: 'video/mp4'}));
    testMessage.value = "转换成功！";
  } catch (error) {
    console.error('测试转换失败:', error);
    testMessage.value = `转换失败: ${error.message}`;
  }
};

// 初始化FFmpeg实例
const initTest = () => {
  testFfmpeg = new FFmpeg();
  testLoaded.value = false;
  testMessage.value = "点击加载FFmpeg";
  testVideoUrl.value = "";
};

// 页面加载时初始化
initTest();
</script>

<template>
  <Layout title="FFmpeg转换测试 - 小左子的工具箱">
    <!-- 页面标题区域 -->
    <div class="bg-white dark:bg-gray-800 shadow-sm">
      <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        <div class="flex items-center space-x-4">
          <div class="h-12 w-12 rounded-lg bg-green-600 flex items-center justify-center">
            <svg
              class="h-8 w-8 text-white"
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
          </div>
          <div>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">FFmpeg转换测试</h1>
            <p class="text-gray-600 dark:text-gray-400">测试FFmpeg基本转换功能</p>
          </div>
        </div>
      </div>
    </div>

    <!-- 测试工具区域 -->
    <div class="max-w-4xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-8">
        <!-- 状态消息 -->
        <div class="mb-6 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-md">
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
            <span class="text-blue-800 dark:text-blue-200">{{ testMessage }}</span>
          </div>
        </div>

        <!-- 控制按钮 -->
        <div class="space-y-4 mb-8">
          <button
            v-if="!testLoaded"
            @click="loadTestFfmpeg"
            class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-6 rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
          >
            <svg class="inline-block h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
            </svg>
            加载FFmpeg (~31 MB)
          </button>
          
          <button
            v-if="testLoaded"
            @click="testTranscode"
            class="w-full bg-green-600 hover:bg-green-700 text-white font-medium py-3 px-6 rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500"
          >
            <svg class="inline-block h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h1m4 0h1m-6 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            转换 webm 到 mp4
          </button>
        </div>
        
        <!-- 测试视频播放器 -->
        <div v-if="testVideoUrl" class="mb-8">
          <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">转换结果：</h3>
          <div class="bg-gray-100 dark:bg-gray-700 rounded-lg p-4">
            <video
              :src="testVideoUrl"
              controls
              class="w-full rounded-md"
              preload="metadata"
            ></video>
          </div>
        </div>
        
        <!-- 功能说明 -->
        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-6">
          <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">测试说明</h3>
          <div class="space-y-3 text-sm text-gray-600 dark:text-gray-400">
            <div class="flex items-start">
              <svg class="h-5 w-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
              </svg>
              <span>打开开发者工具 (Ctrl+Shift+I) 查看详细日志</span>
            </div>
            <div class="flex items-start">
              <svg class="h-5 w-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
              </svg>
              <span>测试使用官方示例视频进行转换</span>
            </div>
            <div class="flex items-start">
              <svg class="h-5 w-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
              </svg>
              <span>用于验证FFmpeg基本功能是否正常</span>
            </div>
            <div class="flex items-start">
              <svg class="h-5 w-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
              </svg>
              <span>测试网络连接和CDN是否正常</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </Layout>
</template> 