<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <!-- 导航栏 -->
    <header class="relative z-10">
        <nav class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex h-16 items-center justify-between">
                <!-- Logo -->
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <Link href="/" class="flex items-center space-x-2">
                            <div class="h-8 w-8 rounded-lg bg-blue-600 flex items-center justify-center">
                                <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                </svg>
                            </div>
                            <span class="text-xl font-bold text-gray-900 dark:text-white">小左子的工具箱</span>
                        </Link>
                    </div>
                </div>
                
                <!-- 导航链接 -->
                <div class="hidden md:block">
                    <div class="ml-10 flex items-baseline space-x-4">
                        <a href="/#features" class="text-gray-700 hover:text-blue-600 dark:text-gray-300 dark:hover:text-blue-400 px-3 py-2 rounded-md text-sm font-medium transition-colors">
                            功能特色
                        </a>
                        <a href="/#tools" class="text-gray-700 hover:text-blue-600 dark:text-gray-300 dark:hover:text-blue-400 px-3 py-2 rounded-md text-sm font-medium transition-colors">
                            工具列表
                        </a>
                        <a href="/#about" class="text-gray-700 hover:text-blue-600 dark:text-gray-300 dark:hover:text-blue-400 px-3 py-2 rounded-md text-sm font-medium transition-colors">
                            关于我们
                        </a>
                    </div>
                </div>
                
                <!-- 用户菜单 -->
                <div class="flex items-center space-x-4">
                    <!-- 控制台按钮已移除 -->
                </div>
            </div>
        </nav>
    </header>
</template> 