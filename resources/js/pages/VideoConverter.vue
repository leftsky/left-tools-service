<script setup lang="ts">
import { ref, onMounted } from "vue";
import Layout from "@/components/Layout.vue";
import { FFmpeg } from "@ffmpeg/ffmpeg";
import { fetchFile, toBlobURL } from "@ffmpeg/util";

// 响应式数据
const isLoaded = ref(false);
const isConverting = ref(false);
const isLoading = ref(false);
const progress = ref(0);
const selectedFile = ref<File | null>(null);
const convertedBlob = ref<Blob | null>(null);
const downloadUrl = ref<string>("");
const message = ref("请选择视频文件开始转换");

// 测试弹窗相关数据
const showTestModal = ref(false);
const testLoaded = ref(false);
const testMessage = ref("点击加载FFmpeg");
const testVideoUrl = ref("");
let testFfmpeg: FFmpeg | null = null;

// 视频信息
const videoInfo = ref<{
  duration: number;
  fps: number;
  totalFrames: number;
  resolution: string;
  bitrate: string;
  format: string;
} | null>(null);

// 临时存储解析的视频信息
const tempVideoInfo = ref<{
  duration: number;
  fps: number;
  resolution: string;
  bitrate: string;
  videoCodec: string;
  audioCodec: string;
} | null>(null);

// 设置消息的辅助函数，同时打印控制台日志
const setMessage = (msg: string) => {
  message.value = msg;
  console.log(`[VideoConverter] ${msg}`);
};

// 转换选项
const outputFormat = ref("mp4");
const videoQuality = ref("high");
const resolution = ref("original");
const framerate = ref("original");

// FFmpeg CDN配置
const baseURL = "https://cdn.jsdelivr.net/npm/@ffmpeg/core-mt@0.12.10/dist/esm";

// FFmpeg实例
const ffmpeg = new FFmpeg();

// 初始化FFmpeg
onMounted(async () => {
  // 清理之前的事件监听器（如果有的话）
  ffmpeg.off("log");
  ffmpeg.off("progress");
  
    // 设置日志监听
  ffmpeg.on("log", ({ message: msg }: any) => {
    // 只在转换过程中输出详细日志，避免干扰视频信息读取
    if (isConverting.value) {
      console.log(`[FFmpeg转换] ${msg}`);
      setMessage(msg);
    } else {
      // 在非转换状态下，只输出关键信息
      if (msg.includes("Duration:") || msg.includes("Video:") || msg.includes("Audio:")) {
        console.log(`[FFmpeg信息] ${msg}`);
      }
    }

    // 解析视频信息
    if (msg.includes("Duration:")) {
      // 解析时长
      const durationMatch = msg.match(/Duration: (\d{2}):(\d{2}):(\d{2}\.\d{2})/);
      if (durationMatch) {
        const hours = parseInt(durationMatch[1]);
        const minutes = parseInt(durationMatch[2]);
        const seconds = parseFloat(durationMatch[3]);
        const totalSeconds = hours * 3600 + minutes * 60 + seconds;

        if (!tempVideoInfo.value) {
          tempVideoInfo.value = {
            duration: 0,
            fps: 30,
            resolution: "未知",
            bitrate: "未知",
            videoCodec: "未知",
            audioCodec: "未知",
          };
        }
        tempVideoInfo.value.duration = totalSeconds;
      }
    }

    if (msg.includes("Video:")) {
      // 解析视频流信息
      console.log("解析视频信息行:", msg);

      // 更精确的分辨率匹配：在Video行中查找分辨率
      // 分辨率通常出现在类似这样的格式中：Video: hevc (Main) (hev1 / 0x31766568), yuv420p(tv, bt709), 720x1280 [SAR 9:16 DAR 9:16], 30 fps, 30 tbr, 30 tbn, 30 tbc
      const resolutionMatch = msg.match(/(\d{3,4})x(\d{3,4})/);
      const fpsMatch = msg.match(/(\d+) fps/);
      const codecMatch = msg.match(/Video: (\w+)/);
      const bitrateMatch = msg.match(/(\d+) kb\/s/);

      if (!tempVideoInfo.value) {
        tempVideoInfo.value = {
          duration: 0,
          fps: 30,
          resolution: "未知",
          bitrate: "未知",
          videoCodec: "未知",
          audioCodec: "未知",
        };
      }

      if (resolutionMatch) {
        const width = parseInt(resolutionMatch[1]);
        const height = parseInt(resolutionMatch[2]);
        // 验证分辨率是否合理（至少100x100）
        if (width >= 100 && height >= 100) {
          tempVideoInfo.value.resolution = `${width}x${height}`;
          console.log("解析到分辨率:", tempVideoInfo.value.resolution);
        } else {
          console.log("分辨率值不合理，跳过:", width, "x", height);
        }
      }

      if (fpsMatch) {
        tempVideoInfo.value.fps = parseInt(fpsMatch[1]);
        console.log("解析到帧率:", tempVideoInfo.value.fps);
      }

      if (codecMatch) {
        tempVideoInfo.value.videoCodec = codecMatch[1];
        console.log("解析到视频编解码器:", tempVideoInfo.value.videoCodec);
      }

      if (bitrateMatch) {
        tempVideoInfo.value.bitrate = `${bitrateMatch[1]} kb/s`;
        console.log("解析到比特率:", tempVideoInfo.value.bitrate);
      }
    }

    if (msg.includes("Audio:")) {
      // 解析音频流信息
      const audioCodecMatch = msg.match(/Audio: (\w+)/);

      if (!tempVideoInfo.value) {
        tempVideoInfo.value = {
          duration: 0,
          fps: 30,
          resolution: "未知",
          bitrate: "未知",
          videoCodec: "未知",
          audioCodec: "未知",
        };
      }

      if (audioCodecMatch) {
        tempVideoInfo.value.audioCodec = audioCodecMatch[1];
      }
    }

    // 根据日志更新进度
    if (msg.includes("frame=")) {
      // 解析帧信息来更新进度
      const frameMatch = msg.match(/frame=\s*(\d+)/);
      if (frameMatch && isConverting.value) {
        const frame = parseInt(frameMatch[1]);
        // 使用动态计算的帧数，如果没有视频信息则使用默认值
        const totalFrames = videoInfo.value?.totalFrames || 111;
        const frameProgress = Math.min((frame / totalFrames) * 70, 70);
        progress.value = 20 + frameProgress; // 从20%开始，最多到90%
        console.log(
          `[转换进度] ${frame}/${totalFrames} 帧 (${progress.value.toFixed(1)}%) - ${new Date().toLocaleTimeString()}`
        );
      }
    }
    
    // 监控关键转换阶段
    if (isConverting.value) {
      if (msg.includes("Stream mapping:")) {
        console.log("[转换阶段] 开始流映射...");
      } else if (msg.includes("Output #0")) {
        console.log("[转换阶段] 开始输出...");
      } else if (msg.includes("frame=") && msg.includes("fps=")) {
        // 解析FPS信息
        const fpsMatch = msg.match(/fps=\s*(\d+)/);
        if (fpsMatch) {
          console.log(`[转换性能] 当前FPS: ${fpsMatch[1]}`);
        }
      }
    }
  });

  // 设置进度监听
  ffmpeg.on("progress", ({ progress: p, time }: any) => {
    console.log(`[VideoConverter] 转换进度: ${p * 100}%, 时间: ${time}`);
    if (p > 0) {
      progress.value = 20 + p * 70; // 从20%开始，最多到90%
    }
  });

  // 自动加载FFmpeg
  try {
    isLoading.value = true;
    setMessage("正在加载FFmpeg...");

    // 模拟加载进度
    const progressInterval = setInterval(() => {
      if (progress.value < 80) {
        progress.value += 5;
        setMessage(`正在加载FFmpeg... ${progress.value}%`);
      }
    }, 200);

    await ffmpeg.load({
      coreURL: await toBlobURL(`${baseURL}/ffmpeg-core.js`, "text/javascript"),
      wasmURL: await toBlobURL(`${baseURL}/ffmpeg-core.wasm`, "application/wasm"),
      workerURL: await toBlobURL(`${baseURL}/ffmpeg-core.worker.js`, "text/javascript"),
    });

    clearInterval(progressInterval);
    progress.value = 100;
    setMessage("FFmpeg加载完成！");
    isLoaded.value = true;
    isLoading.value = false;

    // 检查ffprobe是否可用
    console.log("检查ffprobe可用性:", typeof ffmpeg.ffprobe);
    console.log("FFmpeg对象:", ffmpeg);

    // 执行FFmpeg命令获取版本和帮助信息
    //   try {
    //     console.log("=== FFmpeg版本信息 ===");
    //     await ffmpeg.exec(['-version']);

    //     console.log("=== FFmpeg帮助信息 ===");
    //     await ffmpeg.exec(['-h']);

    //     console.log("=== FFmpeg支持的格式 ===");
    //     await ffmpeg.exec(['-formats']);

    //     console.log("=== FFmpeg支持的编码器 ===");
    //     await ffmpeg.exec(['-codecs']);

    //     console.log("=== FFmpeg支持的过滤器 ===");
    //     await ffmpeg.exec(['-filters']);

    //     setMessage("FFmpeg初始化完成，已获取详细信息");
    //   } catch (infoError) {
    //     console.warn("获取FFmpeg信息时出错:", infoError);
    //     setMessage("FFmpeg初始化完成");
    //   }

    // 等待一秒让用户看到加载完成
    await new Promise((resolve) => setTimeout(resolve, 1000));
    setMessage("请选择视频文件开始转换");
    progress.value = 0;
  } catch (error) {
    console.error("FFmpeg加载失败:", error);
    setMessage("FFmpeg加载失败，请刷新页面重试");
    isLoading.value = false;
  }
});

// 文件选择处理
const handleFileSelect = async (event: Event) => {
  const target = event.target as HTMLInputElement;
  if (target.files && target.files[0]) {
    selectedFile.value = target.files[0];
    // 重置之前的结果
    convertedBlob.value = null;
    downloadUrl.value = "";
    progress.value = 0;
    videoInfo.value = null;
    setMessage(`已选择文件: ${selectedFile.value.name}`);

    // 读取视频信息
    await readVideoInfo();
  }
};

// 拖拽处理
const handleDrop = async (event: DragEvent) => {
  console.log(event);
  event.preventDefault();
  if (event.dataTransfer?.files && event.dataTransfer.files[0]) {
    selectedFile.value = event.dataTransfer.files[0];
    convertedBlob.value = null;
    downloadUrl.value = "";
    progress.value = 0;
    videoInfo.value = null;
    setMessage(`已选择文件: ${selectedFile.value.name}`);

    // 读取视频信息
    await readVideoInfo();
  }
};

const handleDragOver = (event: DragEvent) => {
  event.preventDefault();
};

// 读取视频信息
const readVideoInfo = async () => {
  if (!selectedFile.value) {
    return;
  }

  if (!isLoaded.value) {
    setMessage("FFmpeg尚未加载完成，无法读取视频信息");
    return;
  }

  try {
    setMessage("正在读取视频信息...");

    // 重置临时视频信息
    tempVideoInfo.value = null;

    // 获取文件扩展名
    const inputExt = getFileExtension(selectedFile.value.name);

    // 写入临时文件
    await ffmpeg.writeFile(`temp_input.${inputExt}`, await fetchFile(selectedFile.value));

    // 使用exec命令获取视频信息
    console.log("尝试使用exec获取视频信息...");
    try {
      await ffmpeg.exec(["-i", `temp_input.${inputExt}`]);

      // 检查是否成功解析到视频信息
      if (tempVideoInfo.value && tempVideoInfo.value.duration > 0) {
        // 计算总帧数
        const totalFrames = Math.round(
          tempVideoInfo.value.duration * tempVideoInfo.value.fps
        );

        videoInfo.value = {
          duration: tempVideoInfo.value.duration,
          fps: tempVideoInfo.value.fps,
          totalFrames: totalFrames,
          resolution: tempVideoInfo.value.resolution,
          bitrate: tempVideoInfo.value.bitrate,
          format: inputExt,
        };

        setMessage(
          `视频信息读取成功: ${
            tempVideoInfo.value.resolution
          }, ${tempVideoInfo.value.duration.toFixed(2)}秒, ${tempVideoInfo.value.fps}fps`
        );
        console.log("解析到的视频信息:", tempVideoInfo.value);
      } else {
        // 如果没有解析到有效信息，说明文件可能有问题
        throw new Error("无法解析视频信息");
      }
    } catch (execError) {
      console.error("exec命令失败:", execError);
      throw new Error("无法读取视频信息");
    }

    // 清理临时文件
    await ffmpeg.deleteFile(`temp_input.${inputExt}`);
  } catch (error) {
    console.error("读取视频信息失败:", error);
    setMessage("读取视频信息失败，无法继续转换");
    videoInfo.value = null;
    selectedFile.value = null;
  }
};

// 转换视频
const convertVideo = async () => {
  if (!selectedFile.value || !ffmpeg) {
    alert("请先选择视频文件");
    return;
  }

  console.log("=== 开始转换 ===");
  console.log("FFmpeg实例:", ffmpeg);
  console.log("选择文件:", selectedFile.value.name, "大小:", (selectedFile.value.size / 1024 / 1024).toFixed(2), "MB");
  console.log("输出格式:", outputFormat.value);
  console.log("视频质量:", videoQuality.value);
  console.log("分辨率设置:", resolution.value);
  console.log("帧率设置:", framerate.value);

  isConverting.value = true;
  progress.value = 0;
  setMessage("正在加载FFmpeg...");

  try {
    // 检查FFmpeg是否已加载
    if (!isLoaded.value) {
      setMessage("FFmpeg尚未加载完成，请稍候...");
      return;
    }

    setMessage("开始转换...");
    progress.value = 10;

    // 获取文件扩展名
    const inputExt = getFileExtension(selectedFile.value.name);
    const outputExt = outputFormat.value;

    setMessage("正在写入输入文件...");
    console.log("开始写入输入文件...");
    const startTime = Date.now();
    // 写入输入文件
    await ffmpeg.writeFile(`input.${inputExt}`, await fetchFile(selectedFile.value));
    const writeTime = Date.now() - startTime;
    console.log(`文件写入完成，耗时: ${writeTime}ms`);
    progress.value = 20;

    // 构建FFmpeg命令
    const command = buildFFmpegCommand(inputExt, outputExt);

        setMessage("开始转换...");
    console.log("执行FFmpeg命令:", command.join(" "));
    console.log("转换开始时间:", new Date().toLocaleTimeString());
    
    // 添加超时机制
    const timeoutPromise = new Promise((_, reject) => {
      setTimeout(() => {
        reject(new Error("转换超时，请尝试更小的文件或更低的设置"));
      }, 1000 * 60); // 1分钟超时
    });
    
    // 执行转换
    try {
      const convertStartTime = Date.now();
      await Promise.race([ffmpeg.exec(command), timeoutPromise]);
      const convertTime = Date.now() - convertStartTime;
      console.log("FFmpeg转换命令执行完成");
      console.log(`转换耗时: ${convertTime}ms`);
      console.log("转换结束时间:", new Date().toLocaleTimeString());
      progress.value = 90;
    } catch (execError) {
      console.error("FFmpeg执行错误:", execError);
      console.error("错误详情:", execError.stack);
      throw new Error(`转换执行失败: ${execError.message}`);
    }

    // 读取输出文件
    setMessage("正在读取输出文件...");
    const data = await ffmpeg.readFile(`output.${outputExt}`);
    convertedBlob.value = new Blob([(data as Uint8Array).buffer], {
      type: `video/${outputExt}`,
    });

    // 创建下载链接
    downloadUrl.value = URL.createObjectURL(convertedBlob.value);

    progress.value = 100;
    setMessage("转换完成！");

    // 清理临时文件
    try {
      const inputExt = getFileExtension(selectedFile.value?.name || "");
      await ffmpeg.deleteFile(`input.${inputExt}`);
      await ffmpeg.deleteFile(`output.${outputExt}`);
      console.log("转换完成，已清理临时文件");
    } catch (cleanupError) {
      console.warn("清理临时文件失败:", cleanupError);
    }
    } catch (error) {
    console.error("=== 转换失败 ===");
    console.error("错误类型:", error.constructor.name);
    console.error("错误消息:", error.message);
    console.error("错误堆栈:", error.stack);
    
    // 根据错误类型提供不同的建议
    let errorMessage = "转换失败，请检查文件格式或重试";
    if (error.message.includes("超时")) {
      errorMessage = "转换超时，建议：1. 尝试更小的文件 2. 降低视频质量设置 3. 检查网络连接";
    } else if (error.message.includes("编码器")) {
      errorMessage = "编码器错误，建议：1. 尝试不同的输出格式 2. 检查输入文件是否损坏";
    } else if (error.message.includes("内存")) {
      errorMessage = "内存不足，建议：1. 关闭其他程序 2. 尝试更小的文件";
    }
    
    setMessage(errorMessage);
    alert(errorMessage);
    
    // 清理临时文件
    try {
      const inputExt = getFileExtension(selectedFile.value?.name || "");
      const outputExt = outputFormat.value;
      await ffmpeg.deleteFile(`input.${inputExt}`);
      await ffmpeg.deleteFile(`output.${outputExt}`);
      console.log("已清理临时文件");
    } catch (cleanupError) {
      console.warn("清理临时文件失败:", cleanupError);
    }
  } finally {
    isConverting.value = false;
    isLoading.value = false;
    console.log("=== 转换流程结束 ===");
  }
};

// 构建FFmpeg命令
const buildFFmpegCommand = (inputExt: string, outputExt: string) => {
  const command = ["-i", `input.${inputExt}`];

  // 添加更多调试信息和优化参数
  command.push("-y"); // 覆盖输出文件
  command.push("-loglevel", "info"); // 设置日志级别

  // 视频质量设置 - 移除重复的CRF设置，在后面的编码器设置中统一处理

  // 分辨率设置
  if (resolution.value !== "original") {
    const resolutions = {
      "4k": "3840:2160",
      "1080p": "1920:1080",
      "720p": "1280:720",
      "480p": "854:480",
    };
    command.push(
      "-vf",
      `scale=${resolutions[resolution.value as keyof typeof resolutions]}`
    );
  }

  // 帧率设置
  if (framerate.value !== "original") {
    command.push("-r", framerate.value);
  }

  // 编码设置 - 根据质量设置CRF值
  const crf =
    videoQuality.value === "high" ? 18 : videoQuality.value === "medium" ? 23 : 28;

  switch (outputExt) {
    case "mp4":
      command.push(
        "-c:v",
        "libx264",
        "-preset",
        "ultrafast",
        "-crf",
        crf.toString(),
        "-c:a",
        "aac",
        "-b:a",
        "128k"
      );
      break;
    case "avi":
      command.push(
        "-c:v",
        "libx264",
        "-preset",
        "ultrafast",
        "-crf",
        crf.toString(),
        "-c:a",
        "mp3",
        "-b:a",
        "128k"
      );
      break;
    case "mov":
      command.push(
        "-c:v",
        "libx264",
        "-preset",
        "ultrafast",
        "-crf",
        crf.toString(),
        "-c:a",
        "aac",
        "-b:a",
        "128k"
      );
      break;
    case "mkv":
      command.push(
        "-c:v",
        "libx264",
        "-preset",
        "ultrafast",
        "-crf",
        crf.toString(),
        "-c:a",
        "aac",
        "-b:a",
        "128k"
      );
      break;
    case "wmv":
      command.push(
        "-c:v",
        "libx264",
        "-preset",
        "ultrafast",
        "-crf",
        crf.toString(),
        "-c:a",
        "aac",
        "-b:a",
        "128k"
      );
      break;
    case "flv":
      command.push(
        "-c:v",
        "libx264",
        "-preset",
        "ultrafast",
        "-crf",
        crf.toString(),
        "-c:a",
        "aac",
        "-b:a",
        "128k"
      );
      break;
  }

  command.push(`output.${outputExt}`);

  // 打印完整命令用于调试
  console.log("FFmpeg命令:", command.join(" "));

  return command;
};

// 获取文件扩展名
const getFileExtension = (filename: string) => {
  return filename.split(".").pop()?.toLowerCase() || "mp4";
};

// 下载转换后的文件
const downloadFile = () => {
  if (convertedBlob.value && downloadUrl.value) {
    const a = document.createElement("a");
    a.href = downloadUrl.value;
    a.download = `converted.${outputFormat.value}`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
  }
};

// 测试弹窗相关函数
const openTestModal = () => {
  showTestModal.value = true;
  testLoaded.value = false;
  testMessage.value = "点击加载FFmpeg";
  testVideoUrl.value = "";
  testFfmpeg = new FFmpeg();
};

const closeTestModal = () => {
  showTestModal.value = false;
  testFfmpeg = null;
};

const loadTestFfmpeg = async () => {
  if (!testFfmpeg) return;
  
  try {
    testMessage.value = "正在加载FFmpeg...";
    const baseURL = 'https://unpkg.com/@ffmpeg/core@0.12.6/dist/umd';
    
    testFfmpeg.on('log', ({ message: msg }) => {
      testMessage.value = msg;
      console.log('[测试FFmpeg]', msg);
    });
    
    await testFfmpeg.load({
      coreURL: await toBlobURL(`${baseURL}/ffmpeg-core.js`, 'text/javascript'),
      wasmURL: await toBlobURL(`${baseURL}/ffmpeg-core.wasm`, 'application/wasm'),
    });
    
    testLoaded.value = true;
    testMessage.value = "FFmpeg加载完成，点击开始转换";
  } catch (error) {
    console.error('测试FFmpeg加载失败:', error);
    testMessage.value = `加载失败: ${error.message}`;
  }
};

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
</script>

<template>
  <Layout title="视频格式转换 - 小左子的工具箱">
    <!-- 页面标题区域 -->
    <div class="bg-white dark:bg-gray-800 shadow-sm">
      <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        <div class="flex items-center space-x-4">
          <div class="h-12 w-12 rounded-lg bg-blue-600 flex items-center justify-center">
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
                d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"
              ></path>
            </svg>
          </div>
          <div>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">视频格式转换</h1>
            <p class="text-gray-600 dark:text-gray-400">支持多种格式视频转换，快速高效</p>
          </div>
        </div>
      </div>
    </div>

    <!-- 转换工具区域 -->
    <div class="max-w-4xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
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
            :class="{ 'border-blue-400 bg-blue-50 dark:bg-blue-900/20': selectedFile }"
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
                支持 MP4, AVI, MOV, MKV, WMV 等格式
              </p>
              <p
                v-if="selectedFile"
                class="mt-2 text-sm text-blue-600 dark:text-blue-400"
              >
                已选择: {{ selectedFile.name }}
              </p>
              <div
                v-if="videoInfo"
                class="mt-4 p-3 bg-gray-50 dark:bg-gray-700 rounded-md"
              >
                <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-2">
                  视频信息
                </h4>
                <div
                  class="grid grid-cols-2 gap-2 text-xs text-gray-600 dark:text-gray-400"
                >
                  <div>时长: {{ videoInfo.duration.toFixed(2) }}秒</div>
                  <div>帧率: {{ videoInfo.fps.toFixed(2) }}fps</div>
                  <div>总帧数: {{ videoInfo.totalFrames }}</div>
                  <div>分辨率: {{ videoInfo.resolution }}</div>
                  <div>比特率: {{ videoInfo.bitrate }}</div>
                  <div>格式: {{ videoInfo.format }}</div>
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
                <option value="mp4">MP4</option>
                <option value="avi">AVI</option>
                <option value="mov">MOV</option>
                <option value="mkv">MKV</option>
                <option value="wmv">WMV</option>
                <option value="flv">FLV</option>
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
                <option value="high">高质量</option>
                <option value="medium">中等质量</option>
                <option value="low">低质量</option>
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
                <option value="original">保持原分辨率</option>
                <option value="4k">4K (3840x2160)</option>
                <option value="1080p">1080p (1920x1080)</option>
                <option value="720p">720p (1280x720)</option>
                <option value="480p">480p (854x480)</option>
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
                <option value="original">保持原帧率</option>
                <option value="60">60 FPS</option>
                <option value="30">30 FPS</option>
                <option value="25">25 FPS</option>
                <option value="24">24 FPS</option>
              </select>
            </div>
          </div>
        </div>

        <!-- 转换按钮 -->
        <div class="text-center space-y-4">
          <button
            @click="convertVideo"
            :disabled="!selectedFile || !videoInfo || isConverting || !isLoaded"
            class="bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed text-white font-medium py-3 px-8 rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
          >
            <svg
              v-if="!isConverting"
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
            {{ isConverting ? "转换中..." : "开始转换" }}
          </button>
          
          <!-- 测试按钮 -->
          <div>
            <button
              @click="openTestModal"
              class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500"
            >
              🧪 测试FFmpeg转换
            </button>
          </div>
        </div>

        <!-- 加载进度 -->
        <div v-if="isLoading" class="mt-8">
          <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
            FFmpeg加载进度
          </h3>
          <div class="bg-gray-200 dark:bg-gray-700 rounded-full h-2">
            <div
              class="bg-blue-600 h-2 rounded-full transition-all duration-300"
              :style="{ width: progress + '%' }"
            ></div>
          </div>
          <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
            正在加载FFmpeg核心文件，请稍候...
          </p>
        </div>

        <!-- 转换进度 -->
        <div v-if="isConverting && !isLoading" class="mt-8">
          <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">转换进度</h3>
          <div class="bg-gray-200 dark:bg-gray-700 rounded-full h-2">
            <div
              class="bg-blue-600 h-2 rounded-full transition-all duration-300"
              :style="{ width: progress + '%' }"
            ></div>
          </div>
          <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
            正在转换中，请稍候...
          </p>
        </div>

        <!-- 下载区域 -->
        <div
          v-if="convertedBlob && downloadUrl"
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

      <!-- 功能说明 -->
      <div class="mt-8 bg-white dark:bg-gray-800 rounded-lg shadow-lg p-8">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">功能说明</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">
              支持的输入格式
            </h3>
            <ul class="text-gray-600 dark:text-gray-400 space-y-1">
              <li>• MP4 (H.264, H.265)</li>
              <li>• AVI (Xvid, DivX)</li>
              <li>• MOV (QuickTime)</li>
              <li>• MKV (Matroska)</li>
              <li>• WMV (Windows Media)</li>
              <li>• FLV (Flash Video)</li>
            </ul>
          </div>
          <div>
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">
              支持的输出格式
            </h3>
            <ul class="text-gray-600 dark:text-gray-400 space-y-1">
              <li>• MP4 (H.264, H.265)</li>
              <li>• AVI (Xvid)</li>
              <li>• MOV (QuickTime)</li>
              <li>• MKV (Matroska)</li>
              <li>• WMV (Windows Media)</li>
              <li>• FLV (Flash Video)</li>
            </ul>
          </div>
        </div>
        <div
          class="mt-6 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-md"
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
                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
              ></path>
            </svg>
            <div>
              <h4 class="text-sm font-medium text-blue-800 dark:text-blue-200">
                隐私保护
              </h4>
              <p class="text-sm text-blue-700 dark:text-blue-300 mt-1">
                所有视频转换都在您的浏览器本地进行，文件不会上传到服务器，确保您的隐私安全。
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- 测试弹窗 -->
    <div
      v-if="showTestModal"
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
      @click="closeTestModal"
    >
      <div
        class="bg-white dark:bg-gray-800 rounded-lg p-6 max-w-md w-full mx-4"
        @click.stop
      >
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
            🧪 FFmpeg转换测试
          </h3>
          <button
            @click="closeTestModal"
            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
          >
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>
        </div>
        
        <div class="space-y-4">
          <!-- 状态消息 -->
          <div class="p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-md">
            <p class="text-blue-800 dark:text-blue-200 text-sm">{{ testMessage }}</p>
          </div>
          
          <!-- 控制按钮 -->
          <div class="space-y-2">
            <button
              v-if="!testLoaded"
              @click="loadTestFfmpeg"
              class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md transition-colors"
            >
              加载FFmpeg (~31 MB)
            </button>
            
            <button
              v-if="testLoaded"
              @click="testTranscode"
              class="w-full bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-md transition-colors"
            >
              转换 webm 到 mp4
            </button>
          </div>
          
          <!-- 测试视频播放器 -->
          <div v-if="testVideoUrl" class="space-y-2">
            <h4 class="text-sm font-medium text-gray-900 dark:text-white">转换结果：</h4>
            <video
              :src="testVideoUrl"
              controls
              class="w-full rounded-md"
              preload="metadata"
            ></video>
          </div>
          
          <!-- 提示信息 -->
          <div class="text-xs text-gray-500 dark:text-gray-400">
            <p>• 打开开发者工具 (Ctrl+Shift+I) 查看详细日志</p>
            <p>• 测试使用官方示例视频进行转换</p>
            <p>• 用于验证FFmpeg基本功能是否正常</p>
          </div>
        </div>
      </div>
    </div>
  </Layout>
</template>
