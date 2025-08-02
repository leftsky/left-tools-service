# Vue3 + Vite + TailwindCSS 工具箱

一个基于现代前端技术栈构建的在线工具集合，包含视频格式转换器等实用工具。

## 技术栈

- **Vue 3** - 渐进式JavaScript框架
- **Vite** - 下一代前端构建工具
- **TypeScript** - JavaScript的超集，提供类型安全
- **TailwindCSS** - 实用优先的CSS框架
- **Vue Router** - Vue.js官方路由管理器
- **Pinia** - Vue的状态管理库
- **FFmpeg.wasm** - 基于WebAssembly的FFmpeg

## 功能特性

### 🎥 视频格式转换器
- 支持多种视频格式转换（MP4、AVI、MOV、MKV、WMV、WebM等）
- 在线转换，无需下载软件
- 支持自定义分辨率、帧率、视频质量
- 基于FFmpeg.wasm，功能强大且安全
- 拖拽上传，操作便捷

## 快速开始

### 安装依赖
```bash
npm install
# 或
pnpm install
```

### 启动开发服务器
```bash
npm run dev
# 或
pnpm dev
```

### 构建生产版本
```bash
npm run build
# 或
pnpm build
```

### 预览生产版本
```bash
npm run preview
# 或
pnpm preview
```

## 项目结构

```
vue3-vite-tailwind/
├── src/
│   ├── assets/          # 静态资源
│   ├── components/      # Vue组件
│   ├── router/          # 路由配置
│   ├── stores/          # Pinia状态管理
│   ├── views/           # 页面组件
│   │   ├── HomeView.vue           # 首页
│   │   ├── AboutView.vue          # 关于页面
│   │   └── VideoConverter.vue     # 视频转换器
│   ├── App.vue          # 根组件
│   └── main.ts          # 入口文件
├── public/              # 公共资源
├── index.html           # HTML模板
├── package.json         # 项目配置
├── vite.config.ts       # Vite配置
├── tailwind.config.js   # TailwindCSS配置
└── tsconfig.json        # TypeScript配置
```

## 使用说明

### 视频格式转换器

1. 访问 `/video-converter` 页面
2. 选择要转换的视频文件（支持拖拽上传）
3. 设置输出格式、质量、分辨率等参数
4. 点击"开始转换"按钮
5. 等待转换完成后下载文件

**支持的输入格式：**
- MP4 (H.264, H.265)
- AVI (Xvid, DivX)
- MOV (QuickTime)
- MKV (Matroska)
- WMV (Windows Media)
- FLV (Flash Video)
- WebM (VP8, VP9, AV1)
- 以及更多格式...

**支持的输出格式：**
- MP4 (H.264, H.265)
- AVI (Xvid)
- MOV (QuickTime)
- MKV (Matroska)
- WMV (Windows Media)
- FLV (Flash Video)
- WebM (VP8, VP9, AV1)
- 以及更多格式...

## 开发说明

### 添加新工具

1. 在 `src/views/` 目录下创建新的页面组件
2. 在 `src/router/index.ts` 中添加路由配置
3. 在 `src/views/HomeView.vue` 中添加导航链接
4. 根据需要添加相关依赖

### 样式开发

项目使用TailwindCSS进行样式开发，支持：
- 响应式设计
- 深色模式
- 自定义组件样式
- 实用优先的CSS类

### 状态管理

使用Pinia进行状态管理，相关文件位于 `src/stores/` 目录。

## 浏览器兼容性

- Chrome 88+
- Firefox 85+
- Safari 14+
- Edge 88+

## 注意事项

1. 视频转换功能需要现代浏览器支持WebAssembly
2. 大文件转换可能需要较长时间，请耐心等待
3. 建议使用Chrome或Firefox浏览器以获得最佳体验

## 许可证

MIT License

## 贡献

欢迎提交Issue和Pull Request来改进这个项目！
