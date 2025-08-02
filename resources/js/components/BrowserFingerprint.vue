<script setup lang="ts">
import { onMounted, ref } from 'vue';
import FingerprintJS from '@fingerprintjs/fingerprintjs';

// 响应式数据
const browserFingerprint = ref<string>('');
const deviceType = ref<string>('unknown');
const screenResolution = ref<string>('');
const isCollecting = ref(false);

// 检测设备类型
const detectDeviceType = (): string => {
  const userAgent = navigator.userAgent.toLowerCase();
  const screenWidth = window.screen.width;
  const screenHeight = window.screen.height;
  
  // 移动设备检测
  const isMobile = /mobile|android|iphone|ipad|phone|blackberry|opera mini|iemobile/i.test(userAgent);
  const isTablet = /tablet|ipad|android(?=.*\b(?!mobile\b)(?:tablet|sdk))/i.test(userAgent);
  
  // 屏幕尺寸检测
  const isSmallScreen = screenWidth <= 768;
  const isMediumScreen = screenWidth > 768 && screenWidth <= 1024;
  
  if (isMobile || isSmallScreen) {
    return 'mobile';
  } else if (isTablet || isMediumScreen) {
    return 'tablet';
  } else {
    return 'desktop';
  }
};

// 获取屏幕分辨率
const getScreenResolution = (): string => {
  return `${window.screen.width}x${window.screen.height}`;
};

// 收集浏览器指纹
const collectFingerprint = async () => {
  try {
    isCollecting.value = true;
    
    // 初始化FingerprintJS
    const fp = await FingerprintJS.load();
    
    // 生成指纹
    const result = await fp.get();
    browserFingerprint.value = result.visitorId;
    
    // 检测设备类型
    deviceType.value = detectDeviceType();
    
    // 获取屏幕分辨率
    screenResolution.value = getScreenResolution();
    
    console.log('浏览器指纹收集完成:', {
      fingerprint: browserFingerprint.value,
      deviceType: deviceType.value,
      screenResolution: screenResolution.value
    });
    
    // 发送到后端
    await sendToBackend();
    
  } catch (error) {
    console.error('收集浏览器指纹失败:', error);
    // 降级处理：使用基础信息
    deviceType.value = detectDeviceType();
    screenResolution.value = getScreenResolution();
  } finally {
    isCollecting.value = false;
  }
};

// 发送信息到后端
const sendToBackend = async () => {
  try {
    const data = {
      browser_fingerprint: browserFingerprint.value,
      device_type: deviceType.value,
      screen_resolution: screenResolution.value,
      url: window.location.pathname,
      referer: document.referrer || null,
      user_agent: navigator.userAgent,
      language: navigator.language,
      timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
      timestamp: new Date().toISOString()
    };
    
    const response = await fetch('/api/access-log', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify(data)
    });
    
    if (response.ok) {
      const result = await response.json();
      if (result.code === 1) {
        console.log('访问日志已记录');
      } else {
        console.warn('访问日志记录失败:', result.message);
      }
    } else {
      console.warn('访问日志记录失败:', response.status);
      // 尝试解析错误信息
      try {
        const errorData = await response.json();
        console.warn('错误详情:', errorData);
      } catch (e) {
        console.warn('无法解析错误信息');
      }
    }
  } catch (error) {
    console.warn('发送访问日志失败:', error);
  }
};

// 组件挂载时收集信息
onMounted(() => {
  // 延迟收集，避免影响页面加载性能
  setTimeout(() => {
    collectFingerprint();
  }, 1000);
});

// 暴露方法供父组件调用
defineExpose({
  collectFingerprint,
  browserFingerprint,
  deviceType,
  screenResolution
});
</script>

<template>
  <!-- 这是一个无UI组件，只负责收集数据 -->
  <div v-if="isCollecting" class="hidden">
    正在收集浏览器信息...
  </div>
</template> 