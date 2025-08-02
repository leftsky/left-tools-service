<script setup lang="ts">
import TheWelcome from '../components/TheWelcome.vue'
</script>

<template>
  <main>
    <div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
      <div class="text-center mb-12">
        <h1 class="text-4xl font-bold text-gray-900 dark:text-white mb-4">
          Vue3 + Vite + TailwindCSS 项目
        </h1>
        <p class="text-xl text-gray-600 dark:text-gray-400 mb-8">
          一个现代化的前端开发项目
        </p>
        
        <!-- 工具导航 -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 max-w-4xl mx-auto">
          <router-link
            to="/video-converter"
            class="group bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 hover:shadow-xl transition-all duration-300 border border-gray-200 dark:border-gray-700"
          >
            <div class="text-center">
              <div class="mx-auto h-12 w-12 bg-blue-100 dark:bg-blue-900 rounded-lg flex items-center justify-center mb-4 group-hover:bg-blue-200 dark:group-hover:bg-blue-800 transition-colors">
                <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                </svg>
              </div>
              <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
                视频格式转换器
              </h3>
              <p class="text-sm text-gray-600 dark:text-gray-400">
                在线视频格式转换工具，支持MP4、AVI、MOV、MKV等多种格式
              </p>
            </div>
          </router-link>
          
          <div class="bg-gray-100 dark:bg-gray-700 rounded-lg p-6 border border-gray-200 dark:border-gray-600">
            <div class="text-center">
              <div class="mx-auto h-12 w-12 bg-gray-200 dark:bg-gray-600 rounded-lg flex items-center justify-center mb-4">
                <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
              </div>
              <h3 class="text-lg font-semibold text-gray-500 dark:text-gray-400 mb-2">
                更多工具开发中
              </h3>
              <p class="text-sm text-gray-400 dark:text-gray-500">
                敬请期待更多实用工具
              </p>
            </div>
          </div>
          
          <div class="bg-gray-100 dark:bg-gray-700 rounded-lg p-6 border border-gray-200 dark:border-gray-600">
            <div class="text-center">
              <div class="mx-auto h-12 w-12 bg-gray-200 dark:bg-gray-600 rounded-lg flex items-center justify-center mb-4">
                <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
              </div>
              <h3 class="text-lg font-semibold text-gray-500 dark:text-gray-400 mb-2">
                更多工具开发中
              </h3>
              <p class="text-sm text-gray-400 dark:text-gray-500">
                敬请期待更多实用工具
              </p>
            </div>
          </div>
        </div>
      </div>
      
      <TheWelcome />
    </div>
  </main>
</template>
