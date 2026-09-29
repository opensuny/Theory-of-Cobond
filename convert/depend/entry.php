<?php

return [
    'lang' => 'zh-CN', #导出语言 与manuscripts中定义的语言目录一致
    
    #导出的格式定义
    #pdf.screen: 用于屏幕阅读的PDF格式，紧凑对称页边距，带书签导航。
    #pdf.print:  用于印刷的PDF格式，非对称页边距，带目录页和书签。
    #html.site:  用于在线网站托管的网页文档，每篇一个html文档，带左侧目录列表页。
    'format' => 'pdf.screen',
    
    #chrome 浏览器路径 使用chrome headless方式输出pdf文档
    'chrome' => 'C:\Program Files\Google\Chrome\Application\chrome.exe',
    
    #cpdf.exe工具，已经内置，用于合并pdf文档
    'CPDF' => __DIR__.'/vendor/bin/cpdf.exe',
];