<?php

return [
    'lang' => 'zh-CN', #导出语言 与manuscripts中定义的语言目录一致
    
    #导出的格式定义
    #screen.pdf: 用于屏幕阅读的PDF格式，紧凑对称页边距，带书签导航。
    #print.pdf:  用于印刷的PDF格式，非对称页边距，带目录页和书签。
    #website.html:  用于在线网站托管的网页文档，每篇一个html文档，带左侧目录列表页。
    'format' => 'screen.pdf',
    
    #chrome 浏览器路径 使用chrome headless方式输出pdf文档
    'chrome' => 'C:\Program Files\Google\Chrome\Application\chrome.exe',
    
    #文档输出目录
    'output_dir' => __DIR__.'/_build/',
    
    #output_dir的URL映射
    'base_url' => 'http://localhost/Theory-of-Cobond/convert/_build',
    
    #cpdf.exe工具，已经内置，用于合并pdf文档
    'cpdf' => __DIR__.'\vendor\bin\cpdf.exe',
];