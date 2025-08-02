<script setup lang="ts">
import { ref, onMounted } from "vue";
import Layout from "@/components/Layout.vue";
import ffmpegConverter, {
  OUTPUT_FORMATS,
  VIDEO_QUALITY_OPTIONS,
  RESOLUTION_OPTIONS,
  FRAMERATE_OPTIONS
} from "@/utils/ffmpeg";

// 响应式数据
const isLoaded = ref(false);
const isConverting = ref(false);
const isLoading = ref(false);
const progress = ref(0);
const selectedFile = ref(null);
const convertedBlob = ref(null);
const downloadUrl = ref("");
const message = ref("请选择视频文件开始转换");

// 错误弹窗
const showErrorModal = ref(false);
const errorMessage = ref("");

// 视频信息
const videoInfo = ref(null);

// 临时存储解析的视频信息
const tempVideoInfo = ref({
  duration: 0,
  fps: 30,
  resolution: "未知",
  bitrate: "未知",
  videoCodec: "未知",
  audioCodec: "未知",
});

// 设置消息的辅助函数，同时打印控制台日志
const setMessage = (msg) => {
  message.value = msg;
  console.log(`[VideoConverter] ${msg}`);
};

// 显示错误弹窗
const showErrorDialog = (msg) => {
  errorMessage.value = msg;
  showErrorModal.value = true;
};

// 转换选项
const outputFormat = ref("mp4");
const videoQuality = ref("high");
const resolution = ref("original");
const framerate = ref("original");

// 支持的格式配置
const supportedFormats = {
  // 输入格式
  input: [
    { value: "mp4", label: "MP4 (H.264/H.265)", codecs: ["h264", "h265", "hevc"] },
    { value: "avi", label: "AVI (Xvid/DivX)", codecs: ["xvid", "divx", "h264"] },
    { value: "mov", label: "MOV (QuickTime)", codecs: ["h264", "h265", "prores"] },
    { value: "mkv", label: "MKV (Matroska)", codecs: ["h264", "h265", "vp8", "vp9"] },
    { value: "wmv", label: "WMV (Windows Media)", codecs: ["wmv1", "wmv2", "wmv3"] },
    { value: "flv", label: "FLV (Flash Video)", codecs: ["h264", "vp6"] },
    { value: "webm", label: "WebM (Web Video)", codecs: ["vp8", "vp9", "av1"] },
    { value: "m4v", label: "M4V (iTunes)", codecs: ["h264", "h265"] },
    { value: "3gp", label: "3GP (Mobile)", codecs: ["h264", "h263"] },
    { value: "ogv", label: "OGV (Ogg Video)", codecs: ["theora"] },
    { value: "ts", label: "TS (Transport Stream)", codecs: ["h264", "h265"] },
    { value: "mts", label: "MTS (AVCHD)", codecs: ["h264"] },
    { value: "rm", label: "RM (RealMedia)", codecs: ["rv40", "rv50"] },
    { value: "rmvb", label: "RMVB (RealMedia)", codecs: ["rv40", "rv50"] },
    { value: "asf", label: "ASF (Advanced Systems)", codecs: ["wmv1", "wmv2"] },
    { value: "vob", label: "VOB (DVD Video)", codecs: ["mpeg2", "h264"] },
    { value: "mpg", label: "MPG (MPEG-1/2)", codecs: ["mpeg1", "mpeg2"] },
    { value: "mpeg", label: "MPEG (MPEG-1/2)", codecs: ["mpeg1", "mpeg2"] },
    { value: "divx", label: "DIVX (DivX)", codecs: ["divx", "h264"] },
    { value: "xvid", label: "XVID (Xvid)", codecs: ["xvid"] },
    { value: "swf", label: "SWF (Flash)", codecs: ["vp6", "h264"] },
    { value: "f4v", label: "F4V (Flash Video)", codecs: ["h264"] },
    { value: "m2ts", label: "M2TS (Blu-ray)", codecs: ["h264", "h265"] },
    {
      value: "mxf",
      label: "MXF (Material Exchange)",
      codecs: ["h264", "h265", "prores"],
    },
    { value: "gif", label: "GIF (Animated)", codecs: ["gif"] },
    { value: "apng", label: "APNG (Animated PNG)", codecs: ["apng"] },
    { value: "bmp", label: "BMP (Bitmap)", codecs: ["bmp"] },
    { value: "png", label: "PNG (Portable Network)", codecs: ["png"] },
    { value: "jpg", label: "JPEG (Joint Photographic)", codecs: ["mjpeg"] },
    { value: "jpeg", label: "JPEG (Joint Photographic)", codecs: ["mjpeg"] },
    { value: "tiff", label: "TIFF (Tagged Image)", codecs: ["tiff"] },
    { value: "tga", label: "TGA (Targa)", codecs: ["tga"] },
    { value: "ico", label: "ICO (Icon)", codecs: ["ico"] },
    { value: "pcx", label: "PCX (PC Paintbrush)", codecs: ["pcx"] },
    { value: "ppm", label: "PPM (Portable Pixmap)", codecs: ["ppm"] },
    { value: "pgm", label: "PGM (Portable Graymap)", codecs: ["pgm"] },
    { value: "pbm", label: "PBM (Portable Bitmap)", codecs: ["pbm"] },
    { value: "sgi", label: "SGI (Silicon Graphics)", codecs: ["sgi"] },
    { value: "cin", label: "CIN (Cineon)", codecs: ["cin"] },
    { value: "dpx", label: "DPX (Digital Picture)", codecs: ["dpx"] },
    { value: "exr", label: "EXR (OpenEXR)", codecs: ["exr"] },
    { value: "hdr", label: "HDR (High Dynamic Range)", codecs: ["hdr"] },
    { value: "webp", label: "WebP (Web Picture)", codecs: ["webp"] },
    { value: "avif", label: "AVIF (AV1 Image)", codecs: ["av1"] },
    { value: "heic", label: "HEIC (HEIF)", codecs: ["hevc"] },
    { value: "heif", label: "HEIF (High Efficiency)", codecs: ["hevc"] },
  ],
  // 输出格式
  output: [
    {
      value: "mp4",
      label: "MP4 (H.264/H.265)",
      videoCodec: "libx264",
      audioCodec: "aac",
    },
    { value: "avi", label: "AVI (Xvid)", videoCodec: "libxvid", audioCodec: "mp3" },
    { value: "mov", label: "MOV (QuickTime)", videoCodec: "libx264", audioCodec: "aac" },
    { value: "mkv", label: "MKV (Matroska)", videoCodec: "libx264", audioCodec: "aac" },
    {
      value: "wmv",
      label: "WMV (Windows Media)",
      videoCodec: "wmv2",
      audioCodec: "wmav2",
    },
    {
      value: "flv",
      label: "FLV (Flash Video)",
      videoCodec: "libx264",
      audioCodec: "mp3",
    },
    {
      value: "webm",
      label: "WebM (Web Video)",
      videoCodec: "libvpx",
      audioCodec: "libvorbis",
    },
    { value: "m4v", label: "M4V (iTunes)", videoCodec: "libx264", audioCodec: "aac" },
    { value: "3gp", label: "3GP (Mobile)", videoCodec: "libx264", audioCodec: "aac" },
    {
      value: "ogv",
      label: "OGV (Ogg Video)",
      videoCodec: "libtheora",
      audioCodec: "libvorbis",
    },
    {
      value: "ts",
      label: "TS (Transport Stream)",
      videoCodec: "libx264",
      audioCodec: "aac",
    },
    { value: "mts", label: "MTS (AVCHD)", videoCodec: "libx264", audioCodec: "aac" },
    {
      value: "asf",
      label: "ASF (Advanced Systems)",
      videoCodec: "wmv2",
      audioCodec: "wmav2",
    },
    {
      value: "vob",
      label: "VOB (DVD Video)",
      videoCodec: "mpeg2video",
      audioCodec: "mp2",
    },
    {
      value: "mpg",
      label: "MPG (MPEG-1/2)",
      videoCodec: "mpeg2video",
      audioCodec: "mp2",
    },
    {
      value: "mpeg",
      label: "MPEG (MPEG-1/2)",
      videoCodec: "mpeg2video",
      audioCodec: "mp2",
    },
    { value: "divx", label: "DIVX (DivX)", videoCodec: "libxvid", audioCodec: "mp3" },
    { value: "xvid", label: "XVID (Xvid)", videoCodec: "libxvid", audioCodec: "mp3" },
    { value: "swf", label: "SWF (Flash)", videoCodec: "flv", audioCodec: "mp3" },
    {
      value: "f4v",
      label: "F4V (Flash Video)",
      videoCodec: "libx264",
      audioCodec: "aac",
    },
    { value: "m2ts", label: "M2TS (Blu-ray)", videoCodec: "libx264", audioCodec: "aac" },
    {
      value: "mxf",
      label: "MXF (Material Exchange)",
      videoCodec: "libx264",
      audioCodec: "aac",
    },
    { value: "gif", label: "GIF (Animated)", videoCodec: "gif", audioCodec: null },
    { value: "apng", label: "APNG (Animated PNG)", videoCodec: "apng", audioCodec: null },
    {
      value: "webp",
      label: "WebP (Web Picture)",
      videoCodec: "libwebp",
      audioCodec: null,
    },
    {
      value: "avif",
      label: "AVIF (AV1 Image)",
      videoCodec: "libaom-av1",
      audioCodec: null,
    },
    { value: "heic", label: "HEIC (HEIF)", videoCodec: "libx265", audioCodec: null },
    {
      value: "heif",
      label: "HEIF (High Efficiency)",
      videoCodec: "libx265",
      audioCodec: null,
    },
  ],
};



// 初始化FFmpeg
onMounted(async () => {
  try {
    isLoading.value = true;
    setMessage("正在加载资源...");

    // 模拟加载进度
    const progressInterval = setInterval(() => {
      if (progress.value < 80) {
        progress.value += 5;
        setMessage(`正在加载资源... ${progress.value}%`);
      }
    }, 200);

    await ffmpegConverter.init((msg: string) => {
      setMessage(msg);
    });

    clearInterval(progressInterval);
    progress.value = 100;
    setMessage("资源加载完成！");
    isLoaded.value = true;
    isLoading.value = false;

    // 等待一秒让用户看到加载完成
    await new Promise((resolve) => setTimeout(resolve, 1000));
    setMessage("请选择视频文件开始转换");
    progress.value = 0;
  } catch (error) {
    console.error("FFmpeg加载失败:", error);
    setMessage("资源加载失败，请刷新页面重试");
    isLoading.value = false;
  }
});

// 文件选择处理
const handleFileSelect = async (event) => {
  const target = event.target;
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
const handleDrop = async (event) => {
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

const handleDragOver = (event) => {
  event.preventDefault();
};

// 读取视频信息
const readVideoInfo = async () => {
  if (!selectedFile.value) {
    return;
  }

  if (!isLoaded.value) {
    setMessage("资源尚未加载完成，无法读取视频信息");
    return;
  }

  try {
    setMessage("正在读取视频信息...");

    // 重置临时视频信息
    tempVideoInfo.value = {
      duration: 0,
      fps: 30,
      resolution: "未知",
      bitrate: "未知",
      videoCodec: "未知",
      audioCodec: "未知",
    };

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
  if (!selectedFile.value) {
    alert("请先选择视频文件");
    return;
  }

  // 检查文件大小（限制为100MB）
  const maxSize = 100 * 1024 * 1024; // 100MB
  if (selectedFile.value.size > maxSize) {
    alert("文件过大，请选择小于100MB的文件");
    return;
  }

  console.log("开始转换:", selectedFile.value.name, "→", outputFormat.value);

  isConverting.value = true;
  progress.value = 0;
  setMessage("正在加载资源...");

  try {
    // 检查FFmpeg是否已加载
    if (!isLoaded.value) {
      setMessage("资源尚未加载完成，请稍候...");
      return;
    }

    setMessage("开始转换...");
    progress.value = 0;

    const options = {
      outputFormat: outputFormat.value,
      videoQuality: videoQuality.value,
      resolution: resolution.value,
      framerate: framerate.value
    };

    const result = await ffmpegConverter.convert(
      selectedFile.value,
      options,
      (msg: string, progressValue?: number) => {
        setMessage(msg);
        if (progressValue !== undefined) {
          progress.value = progressValue;
        }
      }
    );

    convertedBlob.value = result.blob;
    downloadUrl.value = URL.createObjectURL(result.blob);
    progress.value = 100;
    setMessage("转换完成！");

  } catch (error) {
    console.error("转换失败:", error);
    setMessage("转换失败，请检查文件格式或重试");
    showErrorDialog(error instanceof Error ? error.message : "转换失败，请检查文件格式或重试");
  } finally {
    isConverting.value = false;
    isLoading.value = false;
  }
};

// 分离式转码：分别处理视频和音频
const performSeparateTranscode = async (inputExt, outputExt) => {
  let videoTime = 0;
  let audioTime = 0;
  let mergeTime = 0;

  // 超时机制
  const timeoutPromise = new Promise((_, reject) => {
    setTimeout(() => {
      reject(new Error("转换超时，请尝试更小的文件或更低的设置"));
    }, 1000 * 60); // 1分钟超时
  });

  try {
    // 检查是否为纯图片格式
    const outputFormat = supportedFormats.output.find((f) => f.value === outputExt);
    const isImageFormat = !outputFormat?.audioCodec;

    // 第一步：转码视频
    setMessage("正在转码视频...");
    progress.value = 0;

    const videoCommand = buildVideoCommand(inputExt, outputExt);
    console.log("视频转码命令:", videoCommand.join(" "));
    const videoStartTime = Date.now();
    await Promise.race([ffmpeg.exec(videoCommand), timeoutPromise]);
    videoTime = Date.now() - videoStartTime;

    // 检查视频文件是否生成成功
    const videoFileExists = await checkFileExists(`video_only.${outputExt}`);
    if (!videoFileExists) {
      throw new Error("视频转码失败：输出文件不存在");
    }
    console.log("视频转码成功，文件大小:", (await ffmpeg.readFile(`video_only.${outputExt}`)).length, "字节");
    progress.value = isImageFormat ? 100 : 40;

    if (!isImageFormat) {
      // 第二步：提取并转码音频
      setMessage("正在处理音频...");
      progress.value = 40;

      const audioCommand = buildAudioCommand(inputExt, outputExt);
      if (audioCommand) {
        console.log("音频转码命令:", audioCommand.join(" "));
        const audioStartTime = Date.now();
        await Promise.race([ffmpeg.exec(audioCommand), timeoutPromise]);
        audioTime = Date.now() - audioStartTime;
        
        // 检查音频文件是否生成成功
        const audioFile = outputExt === "avi" ? "audio.mp3" : "audio.aac";
        const audioFileExists = await checkFileExists(audioFile);
        if (!audioFileExists) {
          throw new Error("音频转码失败：输出文件不存在");
        }
        console.log("音频转码成功，文件大小:", (await ffmpeg.readFile(audioFile)).length, "字节");
      }
      progress.value = 80;

      // 第三步：重新组合视频和音频
      setMessage("正在合并视频和音频...");
      progress.value = 80;

      const mergeCommand = buildMergeCommand(outputExt);
      if (mergeCommand) {
        console.log("合并命令:", mergeCommand.join(" "));
        const mergeStartTime = Date.now();
        await Promise.race([ffmpeg.exec(mergeCommand), timeoutPromise]);
        mergeTime = Date.now() - mergeStartTime;
        
        // 检查合并文件是否生成成功
        const outputFileExists = await checkFileExists(`output.${outputExt}`);
        if (!outputFileExists) {
          throw new Error("合并失败：输出文件不存在");
        }
        console.log("合并成功，文件大小:", (await ffmpeg.readFile(`output.${outputExt}`)).length, "字节");
      }
      progress.value = 100;
    }

    console.log("转码完成，总耗时:", videoTime + audioTime + mergeTime, "ms");
  } catch (error) {
    console.error("转码失败:", error);
    
    // 根据错误类型提供更具体的建议
    if (error.message.includes("视频转码失败")) {
      throw new Error("视频转码失败，可能是编码器不支持或参数错误");
    } else if (error.message.includes("音频转码失败")) {
      throw new Error("音频转码失败，可能是音频流不兼容");
    } else if (error.message.includes("合并失败")) {
      throw new Error("合并失败，可能是AVI格式兼容性问题，建议尝试其他输出格式");
    }
    
    throw error;
  }
};

// 构建视频转码命令
const buildVideoCommand = (inputExt, outputExt) => {
  const command = ["-i", `input.${inputExt}`];

  // 分辨率设置
  if (resolution.value !== "original") {
    const resolutions = {
      "4k": "3840:2160",
      "1080p": "1920:1080",
      "720p": "1280:720",
      "480p": "854:480",
    };
    command.push("-vf", `scale=${resolutions[resolution.value]}`);
  }

  // 帧率设置
  if (framerate.value !== "original") {
    command.push("-r", framerate.value);
  }

  // 根据输出格式选择视频编码器
  const outputFormat = supportedFormats.output.find((f) => f.value === outputExt);
  const videoCodec = outputFormat?.videoCodec || "libx264";

  // 视频编码设置
  const crf =
    videoQuality.value === "high" ? 18 : videoQuality.value === "medium" ? 23 : 28;

  if (videoCodec === "libx264" || videoCodec === "libx265") {
    // 添加内存限制和线程数限制
    command.push(
      "-c:v",
      videoCodec,
      "-preset",
      "ultrafast",
      "-crf",
      crf.toString(),
      "-threads",
      "2",
      "-max_muxing_queue_size",
      "1024"
    );
  } else if (videoCodec === "libvpx") {
    // WebM VP8/VP9 编码
    const quality =
      videoQuality.value === "high"
        ? "good"
        : videoQuality.value === "medium"
        ? "realtime"
        : "realtime";
    command.push("-c:v", videoCodec, "-quality", quality, "-crf", crf.toString());
  } else if (videoCodec === "libtheora") {
    // Ogg Theora 编码
    const quality =
      videoQuality.value === "high" ? "8" : videoQuality.value === "medium" ? "6" : "4";
    command.push("-c:v", videoCodec, "-q:v", quality);
  } else if (videoCodec === "wmv2") {
    // WMV 编码
    const bitrate =
      videoQuality.value === "high"
        ? "2000k"
        : videoQuality.value === "medium"
        ? "1000k"
        : "500k";
    command.push("-c:v", videoCodec, "-b:v", bitrate);
  } else if (videoCodec === "mpeg2video") {
    // MPEG-2 编码
    const bitrate =
      videoQuality.value === "high"
        ? "4000k"
        : videoQuality.value === "medium"
        ? "2000k"
        : "1000k";
    command.push("-c:v", videoCodec, "-b:v", bitrate);
  } else if (videoCodec === "libxvid") {
    // Xvid 编码 - AVI格式特殊处理，使用更兼容的参数
    const qscale =
      videoQuality.value === "high" ? "3" : videoQuality.value === "medium" ? "5" : "7";
    command.push(
      "-c:v",
      videoCodec,
      "-qscale:v",
      qscale,
      "-pix_fmt",
      "yuv420p",
      "-g",
      "30",
      "-bf",
      "2",
      "-threads",
      "1",
      "-max_muxing_queue_size",
      "1024",
      "-avoid_negative_ts",
      "make_zero"
    );
  } else if (videoCodec === "gif" || videoCodec === "apng") {
    // GIF/APNG 编码
    command.push("-c:v", videoCodec);
  } else if (videoCodec === "libwebp") {
    // WebP 编码
    const quality =
      videoQuality.value === "high"
        ? "90"
        : videoQuality.value === "medium"
        ? "70"
        : "50";
    command.push("-c:v", videoCodec, "-quality", quality);
  } else if (videoCodec === "libaom-av1") {
    // AV1 编码
    const crf =
      videoQuality.value === "high"
        ? "20"
        : videoQuality.value === "medium"
        ? "30"
        : "40";
    command.push("-c:v", videoCodec, "-crf", crf);
  } else {
    // 默认使用 H.264
    command.push("-c:v", "libx264", "-preset", "ultrafast", "-crf", crf.toString());
  }

  // 跳过音频（除非是纯图片格式）
  if (
    videoCodec !== "gif" &&
    videoCodec !== "apng" &&
    videoCodec !== "libwebp" &&
    videoCodec !== "libaom-av1"
  ) {
    command.push("-an");
  }

  // 输出文件名
  command.push("-y", `video_only.${outputExt}`);

  return command;
};

// 构建音频转码命令
const buildAudioCommand = (inputExt, outputExt) => {
  const command = ["-i", `input.${inputExt}`];

  // 跳过视频
  command.push("-vn");

  // 根据输出格式选择音频编码
  const outputFormat = supportedFormats.output.find((f) => f.value === outputExt);
  const audioCodec = outputFormat?.audioCodec;

  if (!audioCodec) {
    // 纯图片格式不需要音频
    return null;
  }

  if (audioCodec === "mp3") {
    // AVI格式的MP3音频特殊处理
    command.push("-c:a", "mp3", "-b:a", "128k", "-ar", "44100", "-ac", "2", "-avoid_negative_ts", "make_zero");
    command.push("-y", "audio.mp3");
  } else if (audioCodec === "aac") {
    command.push("-c:a", "aac", "-b:a", "128k", "-ar", "48000");
    command.push("-y", "audio.aac");
  } else if (audioCodec === "libvorbis") {
    command.push("-c:a", "libvorbis", "-b:a", "128k", "-ar", "48000");
    command.push("-y", "audio.ogg");
  } else if (audioCodec === "wmav2") {
    command.push("-c:a", "wmav2", "-b:a", "128k", "-ar", "44100");
    command.push("-y", "audio.wma");
  } else if (audioCodec === "mp2") {
    command.push("-c:a", "mp2", "-b:a", "192k", "-ar", "48000");
    command.push("-y", "audio.mp2");
  } else {
    // 默认使用 AAC
    command.push("-c:a", "aac", "-b:a", "128k", "-ar", "48000");
    command.push("-y", "audio.aac");
  }

  return command;
};

// 构建合并命令
const buildMergeCommand = (outputExt) => {
  const outputFormat = supportedFormats.output.find((f) => f.value === outputExt);
  const audioCodec = outputFormat?.audioCodec;

  if (!audioCodec) {
    // 纯图片格式不需要合并
    return null;
  }

  let audioFile = "audio.aac";
  if (audioCodec === "mp3") {
    audioFile = "audio.mp3";
  } else if (audioCodec === "libvorbis") {
    audioFile = "audio.ogg";
  } else if (audioCodec === "wmav2") {
    audioFile = "audio.wma";
  } else if (audioCodec === "mp2") {
    audioFile = "audio.mp2";
  }

  const command = [
    "-i",
    `video_only.${outputExt}`,
    "-i",
    audioFile,
    "-c:v",
    "copy",
    "-c:a",
    "copy",
    "-shortest",
  ];

  // AVI格式特殊处理
  if (outputExt === "avi") {
    command.push(
      "-pix_fmt", "yuv420p", 
      "-avoid_negative_ts", "make_zero",
      "-ac", "2",  // 确保双声道
      "-ar", "44100",  // 确保采样率
      "-fflags", "+genpts",  // 生成时间戳
      "-max_muxing_queue_size", "1024"
    );
  }

  command.push("-y", `output.${outputExt}`);

  return command;
};

// 清理临时文件
const cleanupTempFiles = async (inputExt, outputExt) => {
  try {
    const outputFormat = supportedFormats.output.find((f) => f.value === outputExt);
    const audioCodec = outputFormat?.audioCodec;

    let audioFile = "audio.aac";
    if (audioCodec === "mp3") {
      audioFile = "audio.mp3";
    } else if (audioCodec === "libvorbis") {
      audioFile = "audio.ogg";
    } else if (audioCodec === "wmav2") {
      audioFile = "audio.wma";
    } else if (audioCodec === "mp2") {
      audioFile = "audio.mp2";
    }

    const filesToDelete = [
      `input.${inputExt}`,
      `video_only.${outputExt}`,
      audioFile,
      `output.${outputExt}`,
    ];

    for (const file of filesToDelete) {
      try {
        await ffmpeg.deleteFile(file);
      } catch (error) {
        console.warn(`删除临时文件失败:`, error);
      }
    }
  } catch (cleanupError) {
    console.warn("清理临时文件失败:", cleanupError);
  }
};

// 获取文件扩展名
const getFileExtension = (filename) => {
  return filename.split(".").pop()?.toLowerCase() || "mp4";
};

// 检查文件是否存在且有效
const checkFileExists = async (filename) => {
  try {
    const data = await ffmpeg.readFile(filename);
    return data && data.length > 0;
  } catch (error) {
    return false;
  }
};

// 记录转换错误日志
const logConversionError = async (error, inputExt, outputExt) => {
  try {
    // 检测设备类型
    const detectDeviceType = () => {
      const userAgent = navigator.userAgent.toLowerCase();
      const screenWidth = window.screen.width;

      const isMobile = /mobile|android|iphone|ipad|phone|blackberry|opera mini|iemobile/i.test(
        userAgent
      );
      const isTablet = /tablet|ipad|android(?=.*\b(?!mobile\b)(?:tablet|sdk))/i.test(
        userAgent
      );

      const isSmallScreen = screenWidth <= 768;
      const isMediumScreen = screenWidth > 768 && screenWidth <= 1024;

      if (isMobile || isSmallScreen) {
        return "mobile";
      } else if (isTablet || isMediumScreen) {
        return "tablet";
      } else {
        return "desktop";
      }
    };

    const errorData = {
      error_type: "video_conversion_failed",
      error_message: error.message || error.toString(),
      input_format: inputExt,
      output_format: outputExt,
      file_size: selectedFile.value?.size || 0,
      user_agent: navigator.userAgent,
      browser_fingerprint: "", // 暂时为空，后续可以从BrowserFingerprint组件获取
      device_type: detectDeviceType(),
      screen_resolution: `${window.screen.width}x${window.screen.height}`,
    };

    const response = await fetch("/api/access-log/error", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      body: JSON.stringify(errorData),
    });

    if (response.ok) {
      const result = await response.json();
      if (result.code === 1) {
        console.log("转换错误日志已记录");
      } else {
        console.warn("记录转换错误失败:", result.message);
      }
    } else {
      console.warn("记录转换错误失败:", response.status);
    }
  } catch (logError) {
    console.warn("记录转换错误时出错:", logError);
  }
};

// 记录工具使用
const recordToolUsage = async () => {
  try {
    const response = await fetch("/api/tools/record-usage-public", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      body: JSON.stringify({
        tool_name: "视频转码",
      }),
    });

    if (response.ok) {
      console.log("工具使用记录已保存");
    } else {
      console.warn("工具使用记录失败:", response.status);
    }
  } catch (error) {
    console.warn("记录工具使用时出错:", error);
  }
};

// 下载转换后的文件
const downloadFile = async () => {
  if (convertedBlob.value && downloadUrl.value && selectedFile.value) {
    const a = document.createElement("a");
    a.href = downloadUrl.value;

    // 获取原始文件名（不含扩展名）
    const originalName = selectedFile.value.name;
    const nameWithoutExt = originalName.substring(0, originalName.lastIndexOf("."));

    // 使用原始文件名 + 新的输出格式
    a.download = `${nameWithoutExt}.${outputFormat.value}`;

    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);

    // 记录工具使用
    await recordToolUsage();
  }
};
</script>

<template>
  <Layout title="视频格式转换工具 - 在线MP4、AVI、MOV、MKV转换器 | 小左子的工具箱">
    <!-- 主要内容区域 -->
    <div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
      <div class="grid grid-cols-1 lg:grid-cols-10 gap-8">
        <!-- 左侧功能说明 -->
        <div class="lg:col-span-4 order-2 lg:order-1">
          <div
            class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 lg:sticky lg:top-8"
          >
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">
              在线视频格式转换工具
            </h2>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
              专业的在线视频格式转换器，支持MP4、AVI、MOV、MKV等多种格式转换。无需下载软件，免费在线使用，快速高效。
            </p>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">
                  支持的输入格式
                </h3>
                <ul class="text-gray-600 dark:text-gray-400 space-y-1 text-sm">
                  <li>• MP4 (H.264, H.265)</li>
                  <li>• AVI (Xvid, DivX)</li>
                  <li>• MOV (QuickTime)</li>
                  <li>• MKV (Matroska)</li>
                  <li>• WMV (Windows Media)</li>
                  <li>• FLV (Flash Video)</li>
                  <li>• WebM (VP8, VP9, AV1)</li>
                  <li>• M4V (iTunes)</li>
                  <li>• 3GP (Mobile)</li>
                  <li>• OGV (Ogg Video)</li>
                  <li>• TS/MTS (Transport Stream)</li>
                  <li>• RM/RMVB (RealMedia)</li>
                  <li>• ASF (Advanced Systems)</li>
                  <li>• VOB (DVD Video)</li>
                  <li>• MPG/MPEG (MPEG-1/2)</li>
                  <li>• SWF/F4V (Flash)</li>
                  <li>• M2TS (Blu-ray)</li>
                  <li>• MXF (Material Exchange)</li>
                  <li>• GIF/APNG (Animated)</li>
                  <li>• WebP/AVIF (Web Images)</li>
                  <li>• HEIC/HEIF (High Efficiency)</li>
                  <li>• 以及更多图片格式...</li>
                </ul>
              </div>
              <div>
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">
                  支持的输出格式
                </h3>
                <ul class="text-gray-600 dark:text-gray-400 space-y-1 text-sm">
                  <li>• MP4 (H.264, H.265)</li>
                  <li>• AVI (Xvid)</li>
                  <li>• MOV (QuickTime)</li>
                  <li>• MKV (Matroska)</li>
                  <li>• WMV (Windows Media)</li>
                  <li>• FLV (Flash Video)</li>
                  <li>• WebM (VP8, VP9, AV1)</li>
                  <li>• M4V (iTunes)</li>
                  <li>• 3GP (Mobile)</li>
                  <li>• OGV (Ogg Video)</li>
                  <li>• TS/MTS (Transport Stream)</li>
                  <li>• ASF (Advanced Systems)</li>
                  <li>• VOB (DVD Video)</li>
                  <li>• MPG/MPEG (MPEG-1/2)</li>
                  <li>• SWF/F4V (Flash)</li>
                  <li>• M2TS (Blu-ray)</li>
                  <li>• MXF (Material Exchange)</li>
                  <li>• GIF/APNG (Animated)</li>
                  <li>• WebP/AVIF (Web Images)</li>
                  <li>• HEIC/HEIF (High Efficiency)</li>
                  <li>• 以及更多图片格式...</li>
                </ul>
              </div>
            </div>

            <div class="mt-6 space-y-4">
              <div
                class="p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-md"
              >
                <div class="flex items-start">
                  <svg
                    class="h-5 w-5 text-green-400 mr-2 mt-0.5"
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
                  <div>
                    <h4 class="text-sm font-medium text-green-800 dark:text-green-200">
                      智能分离转码
                    </h4>
                    <p class="text-sm text-green-700 dark:text-green-300 mt-1">
                      系统采用分离式转码技术，分别处理视频和音频，有效避免转换卡住问题，提供更稳定的转换体验。
                    </p>
                    <div class="mt-2 text-xs text-green-600 dark:text-green-400">
                      <div>• 自动检测音频流并选择最佳处理方式</div>
                      <div>• 视频和音频独立处理，避免相互影响</div>
                      <div>• 支持多种音频编码器，兼容性更好</div>
                    </div>
                  </div>
                </div>
              </div>

              <div
                class="p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-md"
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
                      d="M13 10V3L4 14h7v7l9-11h-7z"
                    ></path>
                  </svg>
                  <div>
                    <h4 class="text-sm font-medium text-blue-800 dark:text-blue-200">
                      强大的格式转换支持
                    </h4>
                    <p class="text-sm text-blue-700 dark:text-blue-300 mt-1">
                      基于融茂软件核心，支持超过50种视频格式和编码器，包括最新的AV1、H.265等先进编码技术。
                    </p>
                    <div class="mt-2 text-xs text-blue-600 dark:text-blue-400">
                      <div>• 支持H.264、H.265、VP8、VP9、AV1等现代编码</div>
                      <div>• 兼容MPEG-1/2、Xvid、DivX等传统格式</div>
                      <div>• 支持GIF、WebP、AVIF等图片和动画格式</div>
                      <div>• 专业级MXF、M2TS等广播级格式支持</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- 右侧转换工具 -->
        <div class="lg:col-span-6 order-1 lg:order-2">
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
                :class="{
                  'border-blue-400 bg-blue-50 dark:bg-blue-900/20': selectedFile,
                }"
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
                    支持 MP4, AVI, MOV, MKV, WMV, WebM, M4V, 3GP, OGV, TS, MTS, RM, RMVB,
                    ASF, VOB, MPG, MPEG, DIVX, XVID, SWF, F4V, M2TS, MXF, GIF, APNG, WebP,
                    AVIF, HEIC, HEIF 等格式
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
                    <option v-for="format in OUTPUT_FORMATS" :key="format.value" :value="format.value">
                      {{ format.label }}
                    </option>
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
                    <option v-for="quality in VIDEO_QUALITY_OPTIONS" :key="quality.value" :value="quality.value">
                      {{ quality.label }}
                    </option>
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
                    <option v-for="res in RESOLUTION_OPTIONS" :key="res.value" :value="res.value">
                      {{ res.label }}
                    </option>
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
                    <option v-for="fps in FRAMERATE_OPTIONS" :key="fps.value" :value="fps.value">
                      {{ fps.label }}
                    </option>
                  </select>
                </div>
              </div>
            </div>

            <!-- 转换按钮 -->
            <div class="text-center">
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
            </div>

            <!-- 加载进度 -->
            <div v-if="isLoading" class="mt-8">
              <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                资源加载进度
              </h3>
              <div class="flex items-center justify-between mb-2">
                <span class="text-sm text-gray-600 dark:text-gray-400"
                  >正在加载核心文件，请稍候...</span
                >
                <span class="text-sm font-medium text-blue-600 dark:text-blue-400"
                  >{{ Math.round(progress) }}%</span
                >
              </div>
              <div class="bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                <div
                  class="bg-blue-600 h-2 rounded-full transition-all duration-300"
                  :style="{ width: progress + '%' }"
                ></div>
              </div>
            </div>

            <!-- 转换进度 -->
            <div v-if="isConverting && !isLoading" class="mt-8">
              <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                转换进度
              </h3>
              <div class="flex items-center justify-between mb-2">
                <span class="text-sm text-gray-600 dark:text-gray-400"
                  >正在转换中，请稍候...</span
                >
                <span class="text-sm font-medium text-blue-600 dark:text-blue-400"
                  >{{ Math.round(progress) }}%</span
                >
              </div>
              <div class="bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                <div
                  class="bg-blue-600 h-2 rounded-full transition-all duration-300"
                  :style="{ width: progress + '%' }"
                ></div>
              </div>
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
        </div>
      </div>
    </div>

    <!-- 错误弹窗 -->
    <div
      v-if="showErrorModal"
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
    >
      <div
        class="bg-white dark:bg-gray-800 rounded-lg p-6 max-w-md w-full mx-4 shadow-xl"
      >
        <div class="flex items-center mb-4">
          <div class="flex-shrink-0">
            <svg
              class="h-6 w-6 text-red-400"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"
              ></path>
            </svg>
          </div>
          <div class="ml-3">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">转换失败</h3>
          </div>
        </div>
        <div class="mt-2">
          <p class="text-sm text-gray-500 dark:text-gray-400 whitespace-pre-line">
            {{ errorMessage }}
          </p>
        </div>
        <div class="mt-4 flex justify-end">
          <button
            @click="showErrorModal = false"
            class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md transition-colors"
          >
            确定
          </button>
        </div>
      </div>
    </div>
  </Layout>
</template>
