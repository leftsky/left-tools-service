import { FFmpeg } from "@ffmpeg/ffmpeg";
import { fetchFile, toBlobURL } from "@ffmpeg/util";

// FFmpeg CDN配置
const baseURL = "https://cdn.jsdelivr.net/npm/@ffmpeg/core-mt@0.12.10/dist/esm";

// 视频格式配置常量
export const OUTPUT_FORMATS = [
    { value: "mp4", label: "MP4 (H.264/H.265)" },
    { value: "avi", label: "AVI (Xvid)" },
    { value: "mov", label: "MOV (QuickTime)" },
    { value: "mkv", label: "MKV (Matroska)" },
    { value: "wmv", label: "WMV (Windows Media)" },
    { value: "flv", label: "FLV (Flash Video)" },
    { value: "webm", label: "WebM (VP8/VP9/AV1)" },
    { value: "m4v", label: "M4V (iTunes)" },
    { value: "3gp", label: "3GP (Mobile)" },
    { value: "ogv", label: "OGV (Ogg Video)" },
    { value: "ts", label: "TS (Transport Stream)" },
    { value: "mts", label: "MTS (AVCHD)" },
    { value: "asf", label: "ASF (Advanced Systems)" },
    { value: "vob", label: "VOB (DVD Video)" },
    { value: "mpg", label: "MPG (MPEG-1/2)" },
    { value: "mpeg", label: "MPEG (MPEG-1/2)" },
    { value: "divx", label: "DIVX (DivX)" },
    { value: "xvid", label: "XVID (Xvid)" },
    { value: "swf", label: "SWF (Flash)" },
    { value: "f4v", label: "F4V (Flash Video)" },
    { value: "m2ts", label: "M2TS (Blu-ray)" },
    { value: "mxf", label: "MXF (Material Exchange)" },
    { value: "gif", label: "GIF (Animated)" },
    { value: "apng", label: "APNG (Animated PNG)" },
    { value: "webp", label: "WebP (Web Picture)" },
    { value: "avif", label: "AVIF (AV1 Image)" },
    { value: "heic", label: "HEIC (HEIF)" },
    { value: "heif", label: "HEIF (High Efficiency)" }
];

// 视频质量选项
export const VIDEO_QUALITY_OPTIONS = [
    { value: "high", label: "高质量" },
    { value: "medium", label: "中等质量" },
    { value: "low", label: "低质量" }
];

// 分辨率选项
export const RESOLUTION_OPTIONS = [
    { value: "original", label: "保持原分辨率" },
    { value: "4k", label: "4K (3840x2160)" },
    { value: "1080p", label: "1080p (1920x1080)" },
    { value: "720p", label: "720p (1280x720)" },
    { value: "480p", label: "480p (854x480)" }
];

// 帧率选项
export const FRAMERATE_OPTIONS = [
    { value: "original", label: "保持原帧率" },
    { value: "60", label: "60 FPS" },
    { value: "30", label: "30 FPS" },
    { value: "25", label: "25 FPS" },
    { value: "24", label: "24 FPS" }
];

// 分辨率映射
export const RESOLUTION_MAP = {
    "4k": "3840:2160",
    "1080p": "1920:1080",
    "720p": "1280:720",
    "480p": "854:480"
};

// 质量CRF值映射
export const QUALITY_CRF_MAP = {
    "high": 18,
    "medium": 23,
    "low": 28
};

// 支持的文件类型
export const SUPPORTED_FILE_TYPES = [
    "video/mp4",
    "video/avi",
    "video/quicktime",
    "video/x-matroska",
    "video/x-ms-wmv",
    "video/x-flv",
    "video/webm",
    "video/x-msvideo",
    "video/3gpp",
    "video/ogg",
    "video/mpeg",
    "video/x-m4v"
];

// 支持的文件扩展名
export const SUPPORTED_EXTENSIONS = [
    "mp4", "avi", "mov", "mkv", "wmv", "flv", "webm",
    "m4v", "3gp", "ogv", "ts", "mts", "rm", "rmvb",
    "asf", "vob", "mpg", "mpeg", "divx", "xvid",
    "swf", "f4v", "m2ts", "mxf", "gif", "apng",
    "webp", "avif", "heic", "heif"
];

// 文件大小限制（100MB）
export const MAX_FILE_SIZE = 100 * 1024 * 1024;

class FFmpegConverter {
    constructor() {
        this.ffmpeg = new FFmpeg();
        this.isLoaded = false;
        this.isLoading = false;
    }

    /**
     * 初始化FFmpeg
     * @param {Function} onProgress 进度回调函数
     * @returns {Promise<boolean>} 初始化是否成功
     */
    async init(onProgress) {
        try {
            this.isLoading = true;

            if (onProgress) {
                onProgress("正在加载资源...");
            }

            await this.ffmpeg.load({
                coreURL: await toBlobURL(`${baseURL}/ffmpeg-core.js`, "text/javascript"),
                wasmURL: await toBlobURL(`${baseURL}/ffmpeg-core.wasm`, "application/wasm"),
                workerURL: await toBlobURL(`${baseURL}/ffmpeg-core.worker.js`, "text/javascript"),
            });

            this.isLoaded = true;
            this.isLoading = false;

            if (onProgress) {
                onProgress("资源加载完成！");
            }

            return true;
        } catch (error) {
            console.error("FFmpeg加载失败:", error);
            this.isLoading = false;
            throw new Error("资源加载失败，请刷新页面重试");
        }
    }

    /**
     * 获取支持的配置项
     * @returns {Object} 配置项对象
     */
    getSupportedConfigs() {
        return {
            outputFormats: OUTPUT_FORMATS,
            videoQualityOptions: VIDEO_QUALITY_OPTIONS,
            resolutionOptions: RESOLUTION_OPTIONS,
            framerateOptions: FRAMERATE_OPTIONS,
            supportedFileTypes: SUPPORTED_FILE_TYPES,
            supportedExtensions: SUPPORTED_EXTENSIONS,
            maxFileSize: MAX_FILE_SIZE
        };
    }

    /**
     * 转码方法 - 支持文件对象或URL
     * @param {File|string} input 输入源（File对象或URL字符串）
     * @param {Object} options 转换选项
     * @param {Function} onProgress 进度回调函数，接收(message, progress)参数
     * @returns {Promise<Object>} 转换结果
     */
    async convert(input, options, onProgress) {
        if (!this.isLoaded) {
            throw new Error("FFmpeg尚未初始化，请先调用init()方法");
        }

        let inputFile;
        let inputExt;
        let originalFilename;

        // 处理输入源
        if (typeof input === 'string') {
            // URL输入
            try {
                if (onProgress) {
                    onProgress("正在获取在线文件...", 0.00);
                }

                const response = await fetch(input);
                if (!response.ok) {
                    throw new Error(`无法获取文件: ${response.statusText}`);
                }

                const blob = await response.blob();
                inputFile = new File([blob], 'input_video', { type: blob.type });
                inputExt = this.getFileExtensionFromUrl(input) || 'mp4';
                originalFilename = `input_video.${inputExt}`;

                if (onProgress) {
                    onProgress("在线文件获取完成", 5.00);
                }
            } catch (error) {
                throw new Error(`获取在线文件失败: ${error instanceof Error ? error.message : '未知错误'}`);
            }
        } else {
            // File对象输入
            inputFile = input;
            inputExt = this.getFileExtension(input.name);
            originalFilename = input.name;
        }

        // 检查文件大小
        if (inputFile.size > MAX_FILE_SIZE) {
            throw new Error("文件过大，请选择小于100MB的文件");
        }

        const outputExt = options.outputFormat || "mp4";
        console.log("开始转换:", originalFilename, "→", outputExt);

        try {
            if (onProgress) {
                onProgress("正在写入输入文件...", 5.00);
            }

            await this.ffmpeg.writeFile(`input.${inputExt}`, await fetchFile(inputFile));

            if (onProgress) {
                onProgress("输入文件写入完成，开始转码...", 10.00);
            }

            // 使用分离式转码，传入进度回调
            await this.performSeparateTranscode(inputExt, outputExt, options, onProgress);

            if (onProgress) {
                onProgress("正在读取输出文件...", 90.00);
            }

            // 读取输出文件
            const data = await this.ffmpeg.readFile(`output.${outputExt}`);

            if (!data || data.length === 0) {
                throw new Error("输出文件为空或无效");
            }

            // 创建Blob对象
            const uint8Array = data;
            const buffer = uint8Array.buffer.slice(uint8Array.byteOffset, uint8Array.byteOffset + uint8Array.byteLength);
            const blob = new Blob([buffer], {
                type: `video/${outputExt}`,
            });

            if (onProgress) {
                onProgress("转换完成！", 100.00);
            }

            // 清理临时文件
            await this.cleanupFiles(inputExt, outputExt);

            // 生成输出文件名
            const nameWithoutExt = originalFilename.substring(0, originalFilename.lastIndexOf("."));
            const outputFilename = `${nameWithoutExt}.${outputExt}`;

            return {
                blob,
                filename: outputFilename,
                size: uint8Array.length
            };

        } catch (error) {
            console.error("转换失败:", error);
            await this.cleanupFiles(inputExt, outputExt);
            throw error;
        }
    }

    // 私有方法

    getFileExtension(filename) {
        return filename.split(".").pop()?.toLowerCase() || "mp4";
    }

    getFileExtensionFromUrl(url) {
        try {
            const urlObj = new URL(url);
            const pathname = urlObj.pathname;
            return this.getFileExtension(pathname);
        } catch {
            return "mp4";
        }
    }

    // 分离式转码：分别处理视频和音频
    async performSeparateTranscode(inputExt, outputExt, options, onProgress) {
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
            // 清理之前的事件监听器，避免进度串扰
            this.ffmpeg.off('log');

            // 设置FFmpeg进度监听
            if (onProgress) {
                const logHandler = ({ message }) => {
                    // 解析FFmpeg日志中的进度信息
                    if (message.includes('frame=')) {
                        const frameMatch = message.match(/frame=\s*(\d+)/);
                        if (frameMatch) {
                            const frame = parseInt(frameMatch[1]);
                            // 估算进度：视频转码占40%，音频转码占30%，合并占20%
                            const currentProgress = Math.min(10 + (frame / 1000) * 30, 40);
                            onProgress(`正在转码视频... (帧: ${frame})`, parseFloat(currentProgress.toFixed(2)));
                        }
                    }
                };

                // 存储监听器引用，以便后续清理
                this.ffmpeg.on('log', logHandler);
            }

            // 第一步：转码视频（无音频）
            if (onProgress) {
                onProgress("开始转码视频...", 10.00);
            }

            const videoCommand = this.buildVideoCommand(inputExt, outputExt, options);
            const videoStartTime = Date.now();
            await Promise.race([this.ffmpeg.exec(videoCommand), timeoutPromise]);
            videoTime = Date.now() - videoStartTime;

            if (onProgress) {
                onProgress("视频转码完成，开始处理音频...", 40.00);
            }

            // 第二步：提取并转码音频
            const audioCommand = this.buildAudioCommand(inputExt, outputExt);
            const audioStartTime = Date.now();
            await Promise.race([this.ffmpeg.exec(audioCommand), timeoutPromise]);
            audioTime = Date.now() - audioStartTime;

            if (onProgress) {
                onProgress("音频处理完成，开始合并...", 70.00);
            }

            // 第三步：重新组合视频和音频
            const mergeCommand = this.buildMergeCommand(outputExt);
            const mergeStartTime = Date.now();
            await Promise.race([this.ffmpeg.exec(mergeCommand), timeoutPromise]);
            mergeTime = Date.now() - mergeStartTime;

            if (onProgress) {
                onProgress("合并完成", 90.00);
            }

            console.log("转码完成，总耗时:", videoTime + audioTime + mergeTime, "ms");

            // 转换完成后立即清理监听器
            if (onProgress) {
                this.ffmpeg.off('log');
            }
        } catch (error) {
            console.error("转码失败:", error);

            // 出错时也要清理监听器
            if (onProgress) {
                this.ffmpeg.off('log');
            }
            throw error;
        }
    }

    // 构建视频转码命令
    buildVideoCommand(inputExt, outputExt, options) {
        const command = ["-i", `input.${inputExt}`];

        // 分辨率设置
        if (options.resolution && options.resolution !== "original") {
            const resolution = RESOLUTION_MAP[options.resolution];
            if (resolution) {
                command.push("-vf", `scale=${resolution}`);
            }
        }

        // 帧率设置
        if (options.framerate && options.framerate !== "original") {
            command.push("-r", options.framerate);
        }

        // 视频编码设置
        const crf = QUALITY_CRF_MAP[options.videoQuality || "medium"] || 23;
        command.push("-c:v", "libx264", "-preset", "ultrafast", "-crf", crf.toString());

        // 跳过音频
        command.push("-an");

        // 输出文件名
        command.push("-y", `video_only.${outputExt}`);

        return command;
    }

    // 构建音频转码命令
    buildAudioCommand(inputExt, outputExt) {
        const command = ["-i", `input.${inputExt}`];

        // 跳过视频
        command.push("-vn");

        // 根据输出格式选择音频编码
        if (outputExt === "avi") {
            // AVI 格式使用 MP3 音频编码
            command.push("-c:a", "mp3", "-b:a", "128k", "-ar", "44100");
            command.push("-y", "audio.mp3");
        } else {
            // 其他格式使用 AAC 音频编码
            command.push("-c:a", "aac", "-b:a", "128k", "-ar", "48000");
            command.push("-y", "audio.aac");
        }

        return command;
    }

    // 构建合并命令
    buildMergeCommand(outputExt) {
        const audioFile = outputExt === "avi" ? "audio.mp3" : "audio.aac";

        const command = [
            "-i", `video_only.${outputExt}`,
            "-i", audioFile,
            "-c:v", "copy",
            "-c:a", "copy",
            "-shortest",
            "-y", `output.${outputExt}`
        ];

        return command;
    }

    // 清理临时文件
    async cleanupFiles(inputExt, outputExt) {
        try {
            let filesToDelete = [`input.${inputExt}`];

            // 如果是转码操作，清理所有临时文件
            if (outputExt) {
                const audioFile = outputExt === "avi" ? "audio.mp3" : "audio.aac";
                filesToDelete = [
                    `input.${inputExt}`,
                    `video_only.${outputExt}`,
                    audioFile,
                    `output.${outputExt}`,
                ];
            }

            for (const file of filesToDelete) {
                try {
                    await this.ffmpeg.deleteFile(file);
                } catch (error) {
                    console.warn(`删除临时文件失败:`, error);
                }
            }
        } catch (error) {
            console.warn("清理临时文件失败:", error);
        }
    }

    // 检查是否已加载
    isReady() {
        return this.isLoaded && !this.isLoading;
    }

    // 获取加载状态
    getLoadingState() {
        return {
            isLoaded: this.isLoaded,
            isLoading: this.isLoading
        };
    }

    /**
     * 获取文件信息
     * @param {File|string} input 输入源（File对象或URL字符串）
     * @param {Function} onProgress 进度回调函数
     * @returns {Promise<Object>} 文件信息对象
     */
    async getFileInfo(input, onProgress) {
        if (!this.isLoaded) {
            throw new Error("FFmpeg尚未初始化，请先调用init()方法");
        }

        let inputFile;
        let inputExt;
        let originalFilename;

        // 处理输入源
        if (typeof input === 'string') {
            // URL输入
            try {
                if (onProgress) {
                    onProgress("正在获取在线文件信息...");
                }

                const response = await fetch(input);
                if (!response.ok) {
                    throw new Error(`无法获取文件: ${response.statusText}`);
                }

                const blob = await response.blob();
                inputFile = new File([blob], 'input_video', { type: blob.type });
                inputExt = this.getFileExtensionFromUrl(input) || 'mp4';
                originalFilename = `input_video.${inputExt}`;

                if (onProgress) {
                    onProgress("在线文件获取完成");
                }
            } catch (error) {
                throw new Error(`获取在线文件失败: ${error instanceof Error ? error.message : '未知错误'}`);
            }
        } else {
            // File对象输入
            inputFile = input;
            inputExt = this.getFileExtension(input.name);
            originalFilename = input.name;
        }

        // 检查文件大小
        if (inputFile.size > MAX_FILE_SIZE) {
            throw new Error("文件过大，请选择小于100MB的文件");
        }

        try {
            if (onProgress) {
                onProgress("正在分析文件信息...");
            }

            // 写入临时文件
            await this.ffmpeg.writeFile(`input.${inputExt}`, await fetchFile(inputFile));

            // 解析视频信息的临时变量
            let parsedInfo = {
                duration: null,
                resolution: null,
                fps: null,
                videoCodec: null,
                audioCodec: null,
                bitrate: null
            };

            // 设置日志监听器来解析视频信息
            const logHandler = ({ message }) => {
                // 解析时长
                if (message.includes('Duration:')) {
                    const durationMatch = message.match(/Duration: (\d{2}):(\d{2}):(\d{2}\.\d{2})/);
                    if (durationMatch) {
                        const hours = parseInt(durationMatch[1]);
                        const minutes = parseInt(durationMatch[2]);
                        const seconds = parseFloat(durationMatch[3]);
                        parsedInfo.duration = hours * 3600 + minutes * 60 + seconds;
                    }
                }

                // 解析视频信息
                if (message.includes('Video:')) {
                    const resolutionMatch = message.match(/(\d{3,4})x(\d{3,4})/);
                    const fpsMatch = message.match(/(\d+) fps/);
                    const codecMatch = message.match(/Video: (\w+)/);
                    const bitrateMatch = message.match(/(\d+) kb\/s/);

                    if (resolutionMatch) {
                        const width = parseInt(resolutionMatch[1]);
                        const height = parseInt(resolutionMatch[2]);
                        if (width >= 100 && height >= 100) {
                            parsedInfo.resolution = `${width}x${height}`;
                        }
                    }

                    if (fpsMatch) {
                        parsedInfo.fps = parseInt(fpsMatch[1]);
                    }

                    if (codecMatch) {
                        parsedInfo.videoCodec = codecMatch[1];
                    }

                    if (bitrateMatch) {
                        parsedInfo.bitrate = `${bitrateMatch[1]} kb/s`;
                    }
                }

                // 解析音频信息
                if (message.includes('Audio:')) {
                    const audioCodecMatch = message.match(/Audio: (\w+)/);
                    if (audioCodecMatch) {
                        parsedInfo.audioCodec = audioCodecMatch[1];
                    }
                }
            };

            // 添加日志监听器
            this.ffmpeg.on('log', logHandler);

            try {
                // 执行FFmpeg命令获取文件信息
                await this.ffmpeg.exec(['-i', `input.${inputExt}`]);
            } finally {
                // 移除日志监听器
                this.ffmpeg.off('log', logHandler);
            }

            // 清理临时文件
            await this.cleanupFiles(inputExt, null);

            // 构建结果对象
            const result = {
                filename: originalFilename,
                size: inputFile.size,
                sizeFormatted: this.formatFileSize(inputFile.size),
                duration: parsedInfo.duration,
                durationFormatted: parsedInfo.duration ? this.formatDuration(parsedInfo.duration) : null,
                format: inputExt,
                bitrate: parsedInfo.bitrate ? parsedInfo.bitrate.replace(' kb/s', '') : null,
                bitrateFormatted: parsedInfo.bitrate,
                video: parsedInfo.resolution ? {
                    codec: parsedInfo.videoCodec || '未知',
                    width: parsedInfo.resolution ? parseInt(parsedInfo.resolution.split('x')[0]) : null,
                    height: parsedInfo.resolution ? parseInt(parsedInfo.resolution.split('x')[1]) : null,
                    resolution: parsedInfo.resolution,
                    framerate: parsedInfo.fps,
                    bitrate: parsedInfo.bitrate ? parsedInfo.bitrate.replace(' kb/s', '') : null,
                    bitrateFormatted: parsedInfo.bitrate
                } : null,
                audio: parsedInfo.audioCodec ? {
                    codec: parsedInfo.audioCodec,
                    channels: null, // 无法从日志中获取
                    sampleRate: null, // 无法从日志中获取
                    bitrate: null,
                    bitrateFormatted: null
                } : null,
                streams: (parsedInfo.resolution ? 1 : 0) + (parsedInfo.audioCodec ? 1 : 0)
            };

            if (onProgress) {
                onProgress("文件信息分析完成");
            }

            return result;

        } catch (error) {
            console.error("获取文件信息失败:", error);
            await this.cleanupFiles(inputExt, null);
            throw new Error(`获取文件信息失败: ${error instanceof Error ? error.message : '未知错误'}`);
        }
    }

    // 格式化文件大小
    formatFileSize(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // 格式化时长
    formatDuration(seconds) {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = Math.floor(seconds % 60);

        if (hours > 0) {
            return `${hours}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
        } else {
            return `${minutes}:${secs.toString().padStart(2, '0')}`;
        }
    }

    // 格式化比特率
    formatBitrate(bps) {
        if (bps === 0) return '0 bps';
        const k = 1000;
        const sizes = ['bps', 'Kbps', 'Mbps', 'Gbps'];
        const i = Math.floor(Math.log(bps) / Math.log(k));
        return parseFloat((bps / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // 解析帧率
    parseFrameRate(frameRate) {
        if (!frameRate) return null;
        const parts = frameRate.split('/');
        if (parts.length === 2) {
            const num = parseInt(parts[0]);
            const den = parseInt(parts[1]);
            return den !== 0 ? (num / den).toFixed(2) : null;
        }
        return parseFloat(frameRate).toFixed(2);
    }
}

// 创建单例实例
const ffmpegConverter = new FFmpegConverter();

// 统一导出
export default ffmpegConverter; 