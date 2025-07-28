<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Left Tools Service - 专业工具服务平台">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>
    
    <div class="min-h-screen bg-gradient-to-br from-blue-50 via-white to-blue-100 dark:from-gray-900 dark:via-blue-900 dark:to-gray-900">
        <!-- 导航栏 -->
        <header class="relative z-10">
            <nav class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex h-16 items-center justify-between">
                    <!-- Logo -->
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="flex items-center space-x-2">
                                <div class="h-8 w-8 rounded-lg bg-blue-600 flex items-center justify-center">
                                    <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                    </svg>
                                </div>
                                <span class="text-xl font-bold text-gray-900 dark:text-white">Left Tools</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 导航链接 -->
                    <div class="hidden md:block">
                        <div class="ml-10 flex items-baseline space-x-4">
                            <a href="#features" class="text-gray-700 hover:text-blue-600 dark:text-gray-300 dark:hover:text-blue-400 px-3 py-2 rounded-md text-sm font-medium transition-colors">
                                功能特色
                            </a>
                            <a href="#tools" class="text-gray-700 hover:text-blue-600 dark:text-gray-300 dark:hover:text-blue-400 px-3 py-2 rounded-md text-sm font-medium transition-colors">
                                工具列表
                            </a>
                            <a href="#about" class="text-gray-700 hover:text-blue-600 dark:text-gray-300 dark:hover:text-blue-400 px-3 py-2 rounded-md text-sm font-medium transition-colors">
                                关于我们
                            </a>
                        </div>
                    </div>
                    
                    <!-- 用户菜单 -->
                    <div class="flex items-center space-x-4">
                        <Link
                            v-if="$page.props.auth.user"
                            :href="route('dashboard')"
                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors"
                        >
                            控制台
                        </Link>
                    </div>
                </div>
            </nav>
        </header>

        <!-- 主要内容 -->
        <main class="relative">
            <!-- Hero 区域 -->
            <div class="relative overflow-hidden">
                <div class="max-w-7xl mx-auto">
                    <div class="relative z-10 pb-8 sm:pb-16 md:pb-20 lg:max-w-2xl lg:w-full lg:pb-28 xl:pb-32">
                        <div class="mt-10 mx-auto max-w-7xl px-4 sm:mt-12 sm:px-6 md:mt-16 lg:mt-20 lg:px-8 xl:mt-28">
                            <div class="sm:text-center lg:text-left">
                                <h1 class="text-4xl tracking-tight font-extrabold text-gray-900 dark:text-white sm:text-5xl md:text-6xl">
                                    <span class="block">专业工具</span>
                                    <span class="block text-blue-600 dark:text-blue-400">服务平台</span>
                                </h1>
                                <p class="mt-3 text-base text-gray-500 dark:text-gray-400 sm:mt-5 sm:text-lg sm:max-w-xl sm:mx-auto md:mt-5 md:text-xl lg:mx-0">
                                    提供高效、智能的在线工具服务，助力您的工作和生活。从文案提取到数据分析，我们为您提供全方位的解决方案。
                                </p>
                                <div class="mt-5 sm:mt-8 sm:flex sm:justify-center lg:justify-start">
                                    <div class="rounded-md shadow">
                                        <a href="#tools" class="w-full flex items-center justify-center px-8 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 md:py-4 md:text-lg md:px-10 transition-colors">
                                            开始使用
                                        </a>
                                    </div>
                                    <div class="mt-3 sm:mt-0 sm:ml-3">
                                        <a href="#features" class="w-full flex items-center justify-center px-8 py-3 border border-transparent text-base font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200 md:py-4 md:text-lg md:px-10 transition-colors">
                                            了解更多
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- 装饰性背景 -->
                <div class="lg:absolute lg:inset-y-0 lg:right-0 lg:w-1/2">
                    <div class="h-56 w-full bg-gradient-to-r from-blue-400 to-blue-600 sm:h-72 md:h-96 lg:w-full lg:h-full flex items-center justify-center">
                        <div class="text-center text-white">
                            <svg class="mx-auto h-32 w-32 opacity-20" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"></path>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 功能特色区域 -->
            <div id="features" class="py-12 bg-white dark:bg-gray-800">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="lg:text-center">
                        <h2 class="text-base text-blue-600 dark:text-blue-400 font-semibold tracking-wide uppercase">功能特色</h2>
                        <p class="mt-2 text-3xl leading-8 font-extrabold tracking-tight text-gray-900 dark:text-white sm:text-4xl">
                            为什么选择我们
                        </p>
                        <p class="mt-4 max-w-2xl text-xl text-gray-500 dark:text-gray-400 lg:mx-auto">
                            我们提供专业、高效、易用的工具服务，让您的工作更加便捷。
                        </p>
                    </div>

                    <div class="mt-10">
                        <div class="space-y-10 md:space-y-0 md:grid md:grid-cols-2 md:gap-x-8 md:gap-y-10">
                            <div class="relative">
                                <div class="absolute flex items-center justify-center h-12 w-12 rounded-md bg-blue-500 text-white">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                    </svg>
                                </div>
                                <p class="ml-16 text-lg leading-6 font-medium text-gray-900 dark:text-white">高效便捷</p>
                                <p class="mt-2 ml-16 text-base text-gray-500 dark:text-gray-400">
                                    一键操作，快速获取结果，节省您宝贵的时间。
                                </p>
                            </div>

                            <div class="relative">
                                <div class="absolute flex items-center justify-center h-12 w-12 rounded-md bg-blue-500 text-white">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                    </svg>
                                </div>
                                <p class="ml-16 text-lg leading-6 font-medium text-gray-900 dark:text-white">安全可靠</p>
                                <p class="mt-2 ml-16 text-base text-gray-500 dark:text-gray-400">
                                    数据加密传输，保护您的隐私和信息安全。
                                </p>
                            </div>

                            <div class="relative">
                                <div class="absolute flex items-center justify-center h-12 w-12 rounded-md bg-blue-500 text-white">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                                    </svg>
                                </div>
                                <p class="ml-16 text-lg leading-6 font-medium text-gray-900 dark:text-white">智能推荐</p>
                                <p class="mt-2 ml-16 text-base text-gray-500 dark:text-gray-400">
                                    基于使用习惯，智能推荐最适合您的工具。
                                </p>
                            </div>

                            <div class="relative">
                                <div class="absolute flex items-center justify-center h-12 w-12 rounded-md bg-blue-500 text-white">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                    </svg>
                                </div>
                                <p class="ml-16 text-lg leading-6 font-medium text-gray-900 dark:text-white">移动适配</p>
                                <p class="mt-2 ml-16 text-base text-gray-500 dark:text-gray-400">
                                    完美适配各种设备，随时随地使用我们的服务。
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 工具列表区域 -->
            <div id="tools" class="py-12 bg-gray-50 dark:bg-gray-900">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="lg:text-center">
                        <h2 class="text-base text-blue-600 dark:text-blue-400 font-semibold tracking-wide uppercase">工具列表</h2>
                        <p class="mt-2 text-3xl leading-8 font-extrabold tracking-tight text-gray-900 dark:text-white sm:text-4xl">
                            精选工具
                        </p>
                        <p class="mt-4 max-w-2xl text-xl text-gray-500 dark:text-gray-400 lg:mx-auto">
                            我们提供多种实用工具，满足您的不同需求。
                        </p>
                    </div>

                    <div class="mt-10">
                        <div class="grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-3">
                            <!-- 抖音文案提取 -->
                            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                                <div class="p-6">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0">
                                            <div class="h-10 w-10 rounded-md bg-blue-500 flex items-center justify-center">
                                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"></path>
                                                </svg>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <h3 class="text-lg font-medium text-gray-900 dark:text-white">抖音文案提取</h3>
                                            <p class="text-sm text-gray-500 dark:text-gray-400">快速提取抖音视频文案内容</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- 更多工具可以在这里添加 -->
                            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                                <div class="p-6">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0">
                                            <div class="h-10 w-10 rounded-md bg-green-500 flex items-center justify-center">
                                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                                </svg>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <h3 class="text-lg font-medium text-gray-900 dark:text-white">数据分析</h3>
                                            <p class="text-sm text-gray-500 dark:text-gray-400">智能数据分析与可视化</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                                <div class="p-6">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0">
                                            <div class="h-10 w-10 rounded-md bg-purple-500 flex items-center justify-center">
                                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                                </svg>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <h3 class="text-lg font-medium text-gray-900 dark:text-white">图片处理</h3>
                                            <p class="text-sm text-gray-500 dark:text-gray-400">智能图片编辑与优化</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 关于我们区域 -->
            <div id="about" class="py-12 bg-white dark:bg-gray-800">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="lg:text-center">
                        <h2 class="text-base text-blue-600 dark:text-blue-400 font-semibold tracking-wide uppercase">关于我们</h2>
                        <p class="mt-2 text-3xl leading-8 font-extrabold tracking-tight text-gray-900 dark:text-white sm:text-4xl">
                            Left Tools Service
                        </p>
                        <p class="mt-4 max-w-2xl text-xl text-gray-500 dark:text-gray-400 lg:mx-auto">
                            致力于为用户提供高质量、高效率的在线工具服务，让技术更好地服务于生活。
                        </p>
                    </div>

                    <div class="mt-10">
                        <div class="bg-blue-50 dark:bg-gray-700 rounded-lg p-8">
                            <div class="grid grid-cols-1 gap-8 md:grid-cols-2">
                                <div>
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">我们的使命</h3>
                                    <p class="text-gray-600 dark:text-gray-300">
                                        通过技术创新，为用户提供简单易用、功能强大的在线工具，让复杂的工作变得简单，让生活更加便捷。
                                    </p>
                                </div>
                                <div>
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">我们的愿景</h3>
                                    <p class="text-gray-600 dark:text-gray-300">
                                        成为最受用户信赖的在线工具服务平台，持续创新，不断优化用户体验，为用户创造更多价值。
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- 页脚 -->
        <footer class="bg-gray-800 dark:bg-gray-900">
            <div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <div>
                        <div class="flex items-center space-x-2">
                            <div class="h-8 w-8 rounded-lg bg-blue-600 flex items-center justify-center">
                                <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                </svg>
                            </div>
                            <span class="text-xl font-bold text-white">Left Tools</span>
                        </div>
                        <p class="mt-4 text-gray-300">
                            专业工具服务平台，为您提供高效便捷的在线工具服务。
                        </p>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-gray-400 tracking-wider uppercase">产品</h3>
                        <ul class="mt-4 space-y-4">
                            <li><a href="#tools" class="text-base text-gray-300 hover:text-white transition-colors">工具列表</a></li>
                            <li><a href="#features" class="text-base text-gray-300 hover:text-white transition-colors">功能特色</a></li>
                        </ul>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-gray-400 tracking-wider uppercase">支持</h3>
                        <ul class="mt-4 space-y-4">
                            <li><a href="#about" class="text-base text-gray-300 hover:text-white transition-colors">关于我们</a></li>
                            <li><a href="#" class="text-base text-gray-300 hover:text-white transition-colors">联系我们</a></li>
                        </ul>
                    </div>
                </div>
                <div class="mt-8 border-t border-gray-700 pt-8">
                    <p class="text-base text-gray-400 text-center">
                        © 2024 Left Tools Service. 保留所有权利。
                    </p>
                </div>
            </div>
        </footer>
    </div>
</template>