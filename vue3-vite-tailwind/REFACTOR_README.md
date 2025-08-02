# 视频转换器重构说明

## 重构概述

本次重构将VideoConverter.vue组件中的FFmpeg相关逻辑提取到独立的工具类中，实现了关注点分离和代码复用。

## 重构后的项目结构

```
src/
├── utils/
│   ├── ffmpeg.ts          # FFmpeg转换器工具类
│   └── fileUtils.ts       # 文件操作工具函数
├── constants/
│   └── videoFormats.ts    # 视频格式配置常量
└── views/
    └── VideoConverter.vue # 重构后的视频转换器组件
```

## 重构内容

### 1. FFmpeg工具类 (`src/utils/ffmpeg.ts`)

**功能：**
- FFmpeg实例管理和初始化
- 视频转换核心逻辑
- 命令构建和参数处理
- 临时文件清理

**主要方法：**
- `load(onProgress?)`: 初始化FFmpeg
- `convertVideo(file, options, onProgress?)`: 执行视频转换
- `buildCommand()`: 构建FFmpeg命令
- `cleanupFiles()`: 清理临时文件
- `isReady()`: 检查是否准备就绪

**类型定义：**
```typescript
interface ConversionOptions {
  outputFormat?: string;
  videoQuality?: string;
  resolution?: string;
  framerate?: string;
}

interface ConversionResult {
  blob: Blob;
  filename: string;
  size: number;
}

type ProgressCallback = (message: string, progress?: number) => void;
```

### 2. 文件操作工具 (`src/utils/fileUtils.ts`)

**功能：**
- 文件下载处理
- 文件类型验证
- 文件大小格式化
- 拖拽区域创建

**主要函数：**
- `downloadFile(blob, filename)`: 下载文件
- `validateVideoFile(file)`: 验证视频文件
- `formatFileSize(bytes)`: 格式化文件大小
- `createDropZone(element, onDrop, onDragOver)`: 创建拖拽区域

### 3. 视频格式配置 (`src/constants/videoFormats.ts`)

**功能：**
- 定义所有支持的视频格式
- 配置转换选项
- 提供类型安全的常量

**主要内容：**
- `OUTPUT_FORMATS`: 支持的输出格式列表
- `VIDEO_QUALITY_OPTIONS`: 视频质量选项
- `RESOLUTION_OPTIONS`: 分辨率选项
- `FRAMERATE_OPTIONS`: 帧率选项
- `SUPPORTED_EXTENSIONS`: 支持的文件扩展名

### 4. 重构后的组件 (`src/views/VideoConverter.vue`)

**简化内容：**
- 移除了FFmpeg相关的复杂逻辑
- 专注于UI交互和状态管理
- 使用工具类和常量配置

**主要改进：**
- 代码更简洁易读
- 关注点分离清晰
- 错误处理更统一
- 类型安全性更好

## 重构优势

### 1. 关注点分离
- **UI层**: 专注于用户交互和界面展示
- **业务层**: 处理视频转换逻辑
- **工具层**: 提供通用功能函数
- **配置层**: 管理常量和配置

### 2. 代码复用
- FFmpeg工具类可以在其他组件中复用
- 文件操作工具函数具有通用性
- 配置常量可以在多个地方使用

### 3. 可维护性
- 逻辑分离，便于单独测试和调试
- 类型安全，减少运行时错误
- 模块化设计，便于扩展

### 4. 可扩展性
- 易于添加新的视频格式支持
- 可以轻松扩展转换选项
- 支持添加新的工具功能

## 使用示例

### 在组件中使用FFmpeg工具类

```typescript
import ffmpegConverter from "@/utils/ffmpeg";

// 初始化
await ffmpegConverter.load((msg) => {
  console.log(msg);
});

// 转换视频
const result = await ffmpegConverter.convertVideo(
  file,
  { outputFormat: "mp4", videoQuality: "high" },
  (msg, progress) => {
    console.log(msg, progress);
  }
);
```

### 使用文件操作工具

```typescript
import { validateVideoFile, downloadFile } from "@/utils/fileUtils";

// 验证文件
try {
  validateVideoFile(file);
} catch (error) {
  console.error(error.message);
}

// 下载文件
downloadFile(blob, "output.mp4");
```

### 使用配置常量

```typescript
import { OUTPUT_FORMATS, VIDEO_QUALITY_OPTIONS } from "@/constants/videoFormats";

// 在模板中使用
<select v-model="outputFormat">
  <option v-for="format in OUTPUT_FORMATS" :key="format.value" :value="format.value">
    {{ format.label }}
  </option>
</select>
```

## 后续扩展建议

1. **添加更多转换选项**: 如音频编码、比特率控制等
2. **支持批量转换**: 处理多个文件
3. **添加转换预设**: 预定义的转换配置
4. **实现转换历史**: 记录转换记录
5. **添加更多工具**: 如视频压缩、格式检测等

## 总结

通过这次重构，我们实现了：
- ✅ 代码逻辑分离
- ✅ 提高可维护性
- ✅ 增强可扩展性
- ✅ 改善类型安全
- ✅ 提升代码复用性

重构后的代码结构更清晰，功能更模块化，为后续的功能扩展和维护奠定了良好的基础。 