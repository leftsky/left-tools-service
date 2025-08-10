import axios from 'axios';

/**
 * 文件转换 API 服务
 */

// API 基础配置
const API_BASE = '/api/file-conversion';

// 创建 axios 实例
const apiClient = axios.create({
    baseURL: API_BASE,
    timeout: 30000, // 30秒超时
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
    }
});

// 重试配置
const retryConfig = {
    retries: 3,
    retryDelay: 1000,
    retryCondition: (error) => {
        // 只在网络错误或5xx服务器错误时重试
        return !error.response || (error.response.status >= 500 && error.response.status < 600);
    }
};

// 重试函数
const retryRequest = async (requestFn, retries = retryConfig.retries) => {
    try {
        return await requestFn();
    } catch (error) {
        if (retries > 0 && retryConfig.retryCondition(error)) {
            await new Promise(resolve => setTimeout(resolve, retryConfig.retryDelay));
            return retryRequest(requestFn, retries - 1);
        }
        throw error;
    }
};

// 请求拦截器
apiClient.interceptors.request.use(
    (config) => {
        // 确保 CSRF token 是最新的
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (token) {
            config.headers['X-CSRF-TOKEN'] = token;
        }
        return config;
    },
    (error) => {
        return Promise.reject(error);
    }
);

// 响应拦截器
apiClient.interceptors.response.use(
    (response) => {
        // 检查业务逻辑错误
        if (response.data && response.data.code === 0) {
            return Promise.reject(new Error(response.data.message || '请求失败'));
        }
        return response;
    },
    (error) => {
        // 处理网络错误
        if (error.response) {
            // 服务器返回错误状态码
            const message = error.response.data?.message || `请求失败 (${error.response.status})`;
            return Promise.reject(new Error(message));
        } else if (error.request) {
            // 请求已发出但没有收到响应
            return Promise.reject(new Error('网络连接失败，请检查网络连接'));
        } else {
            // 其他错误
            return Promise.reject(new Error(error.message || '请求失败'));
        }
    }
);

/**
 * 文件转换 API 类
 */
class FileConversionAPI {
    /**
     * 上传文件
     * @param {File} file - 要上传的文件
     * @param {Object} options - 上传选项
     * @returns {Promise<Object>}
     */
    static async uploadFile(file, options = {}) {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('folder', options.folder || 'uploads');

        try {
            const response = await apiClient.post('/upload', formData, {
                headers: {
                    'Content-Type': 'multipart/form-data',
                }
            });
            return response.data;
        } catch (error) {
            throw new Error(error.message || '上传失败');
        }
    }

    /**
     * 通过URL开始转换
     * @param {string} fileUrl - 文件URL
     * @param {Object} options - 转换选项
     * @returns {Promise<Object>}
     */
    static async convertFromUrl(fileUrl, options = {}) {
        const conversionParams = {};
        
        if (options.conversionOptions && Array.isArray(options.conversionOptions)) {
            options.conversionOptions.forEach(option => {
                if (option.key && option.value !== undefined) {
                    conversionParams[option.key] = option.value;
                }
            });
        }

        try {
            const response = await apiClient.post('/convert', {
                file_url: fileUrl,
                output_format: options.outputFormat || 'mp4',
                conversion_params: conversionParams
            });
            return response.data;
        } catch (error) {
            throw new Error(error.message || '转换失败');
        }
    }

    /**
     * 上传文件并开始转换（统一接口，内部使用分离的上传+转换）
     * @param {File} file - 要转换的文件
     * @param {Object} options - 转换选项
     * @returns {Promise<Object>}
     */
    static async uploadAndConvert(file, options = {}) {
        try {
            // 1. 先上传文件
            const uploadResult = await this.uploadFile(file, { folder: 'conversions' });
            
            if (uploadResult.code !== 1 || !uploadResult.data?.url) {
                throw new Error(uploadResult.message || '文件上传失败');
            }

            // 2. 使用上传后的URL进行转换
            const convertResult = await this.convertFromUrl(uploadResult.data.url, options);
            
            return convertResult;
        } catch (error) {
            throw new Error(error.message || '上传转换失败');
        }
    }

    /**
     * 获取任务状态
     * @param {number} taskId - 任务ID
     * @returns {Promise<Object>}
     */
    static async getStatus(taskId) {
        try {
            const response = await retryRequest(() =>
                apiClient.get('/status', {
                    params: { task_id: taskId }
                })
            );
            return response.data;
        } catch (error) {
            throw new Error(error.message || '获取状态失败');
        }
    }



    /**
     * 获取支持的格式
     * @returns {Promise<Object>}
     */
    static async getSupportedFormats() {
        try {
            const response = await apiClient.get('/formats');
            return response.data;
        } catch (error) {
            throw new Error(error.message || '获取格式失败');
        }
    }

    /**
     * 创建客户端直传任务
     * @param {string} filename - 文件名
     * @param {Object} options - 转换选项
     * @returns {Promise<Object>}
     */
    static async createDirectUpload(filename, options = {}) {
        try {
            const response = await apiClient.post('/direct-upload', {
                filename: filename,
                output_format: options.outputFormat || 'mp4',
                engine: options.engine || 'cloudconvert',
                options: options.conversionOptions || []
            });
            return response.data;
        } catch (error) {
            throw new Error(error.message || '创建直传任务失败');
        }
    }

    /**
     * 确认客户端直传完成
     * @param {number} taskId - 任务ID
     * @returns {Promise<Object>}
     */
    static async confirmDirectUpload(taskId) {
        try {
            const response = await apiClient.post('/confirm-direct-upload', {
                task_id: taskId
            });
            return response.data;
        } catch (error) {
            throw new Error(error.message || '确认直传失败');
        }
    }

    /**
     * 客户端直传文件到 CloudConvert
     * @param {File} file - 文件对象
     * @param {Object} options - 转换选项
     * @returns {Promise<Object>}
     */
    static async directUploadToCloudConvert(file, options = {}) {
        try {
            // 1. 创建直传任务
            const createResult = await this.createDirectUpload(file.name, options);
            
            if (createResult.code !== 1) {
                throw new Error(createResult.message || '创建直传任务失败');
            }

            const { task_id, upload_url, form_data } = createResult.data;

            // 2. 构建上传表单数据
            const uploadFormData = new FormData();
            
            // 添加文件
            uploadFormData.append('file', file);
            
            // 添加 CloudConvert 需要的表单字段
            if (form_data) {
                Object.keys(form_data).forEach(key => {
                    if (key !== 'file') { // 文件字段已经添加过了
                        uploadFormData.append(key, form_data[key]);
                    }
                });
            }

            // 3. 直接上传到 CloudConvert
            await axios.post(upload_url, uploadFormData, {
                headers: {
                    'Content-Type': 'multipart/form-data',
                },
                timeout: 60000, // 上传可能需要更长时间
                onUploadProgress: (progressEvent) => {
                    if (options.onProgress) {
                        const percentCompleted = Math.round((progressEvent.loaded * 100) / progressEvent.total);
                        options.onProgress(percentCompleted);
                    }
                }
            });

            // 4. 确认直传完成
            const confirmResult = await this.confirmDirectUpload(task_id);
            
            if (confirmResult.code !== 1) {
                throw new Error(confirmResult.message || '确认直传失败');
            }

            return {
                task_id: task_id,
                status: 'uploaded',
                message: '文件直传成功，开始转换'
            };

        } catch (error) {
            throw new Error(`直传失败: ${error.message}`);
        }
    }

    /**
     * 获取转换历史
     * @param {Object} params - 查询参数
     * @returns {Promise<Object>}
     */
    static async getHistory(params = {}) {
        try {
            const response = await apiClient.get('/history', {
                params: {
                    limit: params.limit || 20,
                    page: params.page || 1,
                    ...params
                }
            });
            return response.data;
        } catch (error) {
            throw new Error(error.message || '获取历史失败');
        }
    }

    /**
     * 轮询任务状态
     * @param {number} taskId - 任务ID
     * @param {Function} onProgress - 进度回调
     * @param {Function} onComplete - 完成回调
     * @param {Function} onError - 错误回调
     * @param {number} interval - 轮询间隔（毫秒）
     * @returns {Function} 返回取消轮询的函数
     */
    static pollStatus(taskId, onProgress, onComplete, onError, interval = 2000) {
        let isCancelled = false;

        const poll = async () => {
            if (isCancelled) return;

            try {
                const result = await this.getStatus(taskId);
                const data = result.data;

                // 调用进度回调
                if (onProgress) {
                    onProgress(data);
                }

                // 检查是否完成
                if (data.status === 2) { // 已完成
                    if (onComplete) {
                        onComplete(data);
                    }
                    return;
                }

                // 检查是否失败
                if (data.status === 3) { // 失败
                    if (onError) {
                        onError(new Error(data.error_message || '转换失败'));
                    }
                    return;
                }



                // 继续轮询
                setTimeout(poll, interval);
            } catch (error) {
                if (onError) {
                    onError(error);
                }
            }
        };

        // 开始轮询
        poll();

        // 返回取消函数
        return () => {
            isCancelled = true;
        };
    }

    /**
     * 格式化文件大小
     * @param {number} bytes - 字节数
     * @returns {string}
     */
    static formatFileSize(bytes) {
        if (bytes === 0) return '0 B';

        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));

        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    /**
     * 格式化时间
     * @param {number} seconds - 秒数
     * @returns {string}
     */
    static formatTime(seconds) {
        if (seconds < 60) {
            return `${seconds}秒`;
        } else if (seconds < 3600) {
            return `${Math.floor(seconds / 60)}分钟${seconds % 60}秒`;
        } else {
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            return `${hours}小时${minutes}分钟`;
        }
    }

    /**
     * 获取状态文本
     * @param {number} status - 状态码
     * @returns {string}
     */
    static getStatusText(status) {
        const statusMap = {
            0: '等待中',
            1: '转换中',
            2: '已完成',
            3: '失败'
        };
        return statusMap[status] || '未知状态';
    }

    /**
     * 检查文件是否支持转换
     * @param {File} file - 文件对象
     * @returns {boolean}
     */
    static isFileSupported(file) {
        const supportedTypes = [
            'video/mp4', 'video/avi', 'video/mov', 'video/mkv', 'video/wmv',
            'video/flv', 'video/webm', 'video/m4v', 'video/3gp', 'video/ogv',
            'image/gif', 'image/webp', 'image/avif', 'image/heic', 'image/heif'
        ];

        return supportedTypes.includes(file.type) ||
            file.name.match(/\.(mp4|avi|mov|mkv|wmv|flv|webm|m4v|3gp|ogv|gif|webp|avif|heic|heif)$/i);
    }

    /**
     * 获取文件类型信息
     * @param {File} file - 文件对象
     * @returns {Object}
     */
    static getFileInfo(file) {
        return {
            name: file.name,
            size: file.size,
            sizeFormatted: this.formatFileSize(file.size),
            type: file.type,
            extension: file.name.split('.').pop()?.toLowerCase() || 'unknown',
            lastModified: new Date(file.lastModified).toLocaleString(),
            isSupported: this.isFileSupported(file)
        };
    }

    /**
     * 设置请求超时时间
     * @param {number} timeout - 超时时间（毫秒）
     */
    static setTimeout(timeout) {
        apiClient.defaults.timeout = timeout;
    }

    /**
     * 获取当前超时设置
     * @returns {number}
     */
    static getTimeout() {
        return apiClient.defaults.timeout;
    }
}

export default FileConversionAPI;
