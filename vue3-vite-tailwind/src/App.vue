<script setup lang="ts">
import { RouterLink, RouterView } from 'vue-router'
</script>

<template>
  <div class="min-h-screen bg-gray-50 dark:bg-gray-900">
    <!-- 导航栏 -->
    <nav class="bg-white dark:bg-gray-800 shadow-lg">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
          <div class="flex items-center">
            <RouterLink to="/" class="flex items-center space-x-3">
              <svg class="h-8 w-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4V2a1 1 0 011-1h8a1 1 0 011 1v2m-9 0h10m-10 0a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V6a2 2 0 00-2-2"></path>
              </svg>
              <span class="text-xl font-bold text-gray-900 dark:text-white">工具箱</span>
            </RouterLink>
          </div>
          
          <div class="flex items-center space-x-8">
            <RouterLink 
              to="/" 
              class="text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 px-3 py-2 rounded-md text-sm font-medium transition-colors"
              active-class="text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20"
            >
              首页
            </RouterLink>
            <RouterLink 
              to="/video-converter" 
              class="text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 px-3 py-2 rounded-md text-sm font-medium transition-colors"
              active-class="text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20"
            >
              视频转换器
            </RouterLink>
            <RouterLink 
              to="/about" 
              class="text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 px-3 py-2 rounded-md text-sm font-medium transition-colors"
              active-class="text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20"
            >
              关于
            </RouterLink>
          </div>
        </div>
      </div>
    </nav>

    <!-- 主要内容 -->
    <main>
      <RouterView />
    </main>

    <!-- 页脚 -->
    <footer class="bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 mt-16">
      <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        <div class="text-center">
          <p class="text-gray-600 dark:text-gray-400">
            © 2024 Vue3 + Vite + TailwindCSS 工具箱. 使用现代技术栈构建.
          </p>
        </div>
      </div>
    </footer>
  </div>
</template>
