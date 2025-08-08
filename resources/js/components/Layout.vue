<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Header from '@/components/Header.vue';
import Footer from '@/components/Footer.vue';
import BrowserFingerprint from '@/components/BrowserFingerprint.vue';

interface Props {
    title?: string;
}

withDefaults(defineProps<Props>(), {
    title: '格式转换大王'
});
</script>

<template>
    <Head :title="title">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>
    
    <div class="min-h-screen bg-gradient-to-br from-blue-50 via-white to-blue-100 dark:from-gray-900 dark:via-blue-900 dark:to-gray-900">
        <!-- 页眉 -->
        <Header />
        
        <!-- 主要内容 -->
        <main class="relative">
            <slot />
        </main>

        <!-- 页脚 -->
        <Footer />
        
        <!-- 浏览器指纹收集组件 -->
        <BrowserFingerprint />
    </div>
</template> 