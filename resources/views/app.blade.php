<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', '格式转换大王 - 专业在线工具服务平台') }}</title>
        
        <!-- SEO Meta Tags -->
        <meta name="description" content="专业的在线工具服务平台，提供视频格式转换、抖音文案提取、音频处理、文档转换、图片处理等实用工具。支持MP4、AVI、MOV、MKV等多种视频格式转换，免费在线使用。">
        <meta name="keywords" content="视频格式转换,在线视频转换器,MP4转换器,AVI转换器,MOV转换器,MKV转换器,视频转码工具,抖音文案提取,无水印视频下载,音频格式转换,文档转换,图片处理,在线工具,免费工具">
        <meta name="author" content="格式转换大王">
        <meta name="robots" content="index, follow">
        
        <!-- Open Graph Meta Tags -->
        <meta property="og:title" content="格式转换大王 - 专业在线工具服务平台">
        <meta property="og:description" content="提供视频格式转换、抖音文案提取、音频处理等实用工具，支持多种格式转换，免费在线使用。">
        <meta property="og:type" content="website">
        <meta property="og:url" content="{{ request()->url() }}">
        <meta property="og:site_name" content="格式转换大王">
        
        <!-- Canonical URL -->
        <link rel="canonical" href="{{ request()->url() }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link rel="preconnect" href="https://cdn.jsdelivr.net">
        
        <!-- Fonts -->
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

        @routes
        @vite(['resources/js/app.ts'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
