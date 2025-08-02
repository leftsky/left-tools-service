// 文件操作工具函数

// 下载文件
export const downloadFile = (blob: Blob, filename: string): void => {
  if (!blob || !filename) {
    console.error("下载文件失败：缺少必要参数");
    return;
  }

  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = filename;
  
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  
  // 清理URL对象
  URL.revokeObjectURL(url);
};

// 获取文件扩展名
export const getFileExtension = (filename: string): string => {
  return filename.split(".").pop()?.toLowerCase() || "mp4";
};

// 格式化文件大小
export const formatFileSize = (bytes: number): string => {
  if (bytes === 0) return "0 Bytes";
  
  const k = 1024;
  const sizes = ["Bytes", "KB", "MB", "GB"];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i];
};

// 验证文件类型
export const validateVideoFile = (file: File): boolean => {
  const allowedTypes = [
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

  if (!allowedTypes.includes(file.type)) {
    // 检查文件扩展名作为备用方案
    const extension = getFileExtension(file.name);
    const allowedExtensions = [
      "mp4", "avi", "mov", "mkv", "wmv", "flv", "webm", 
      "m4v", "3gp", "ogv", "ts", "mts", "rm", "rmvb", 
      "asf", "vob", "mpg", "mpeg", "divx", "xvid", 
      "swf", "f4v", "m2ts", "mxf", "gif", "apng", 
      "webp", "avif", "heic", "heif"
    ];
    
    if (!allowedExtensions.includes(extension)) {
      throw new Error("不支持的文件格式");
    }
  }

  // 检查文件大小（100MB限制）
  const maxSize = 100 * 1024 * 1024;
  if (file.size > maxSize) {
    throw new Error("文件过大，请选择小于100MB的文件");
  }

  return true;
};

// 创建文件拖拽区域
export const createDropZone = (
  element: HTMLElement | null, 
  onDrop?: (event: DragEvent) => void, 
  onDragOver?: (event: DragEvent) => void
): void => {
  if (!element) return;

  element.addEventListener("dragover", (e) => {
    e.preventDefault();
    if (onDragOver) onDragOver(e);
  });

  element.addEventListener("drop", (e) => {
    e.preventDefault();
    if (onDrop) onDrop(e);
  });
};

// 生成唯一文件名
export const generateUniqueFilename = (originalName: string, newExtension: string): string => {
  const timestamp = Date.now();
  const nameWithoutExt = originalName.substring(0, originalName.lastIndexOf("."));
  return `${nameWithoutExt}_${timestamp}.${newExtension}`;
}; 