<?php

use opensuny\Converter;

error_reporting(E_ALL);

require __DIR__.'/depend/vendor/autoload.php';




##参数: 语言  格式
/**
 * 参数
 * 语言：      manuscripts中定义的语言目录一致
 * 格式：      screen.pdf: 用于屏幕阅读的PDF格式，紧凑对称页边距，带书签导航。
            print.pdf:  用于印刷的PDF格式，非对称页边距，带目录页和书签。
            website.html:  用于在线网站托管的网页文档，每篇一个html文档，带左侧目录列表页。
            single.html:   单个网页
            
 * URL前缀: 当前目录的http url访问路径
 * Chrome路径：chrome.exe的完整路径
 * 
 * 例如：php main.php zh-CN screen.pdf http://localhost/Theory-of-Cobond/convert "C:\Program Files\Google\Chrome\Application\chrome.exe"
 */

if($_SERVER['argc'] < 5) {
    exit('usage: php '. $_SERVER['argv'][0] . ' <lang> <format> <url> <chrome>' );
}

@list($null, $lang, $format, $url, $chrome, $paper) = $_SERVER['argv'];

$docDir = realpath(__DIR__.'/../manuscripts/'.$lang);
if(empty($lang) || !$docDir || !is_dir($docDir)) {
    exit("$lang is not exsits.");
}

if(!in_array($format, ['screen.pdf', 'print.pdf', 'website.html', 'single.html'])) {
    exit("format: screen.pdf  | print.pdf | website.html | single.html");
}

$resp = file_get_contents($url.'/assets/normalize.css');
if(!$url || !$resp){
    exit("url: $url is invalid.");
}

if(empty($chrome) || !is_executable($chrome)) {
    exit("chrome $chrome is invalid.");
}

$config = [
    'lang' => $lang,
    'format' => $format,
    'url' => $url.'/'.$lang,
    'chrome' => $chrome,
    'paper' => $paper,
    'doc_dir' => realpath(__DIR__.'/../manuscripts/'.$lang),
    'out_dir' => realpath(__DIR__). '/'.$lang,    
    'cpdf' => realpath(__DIR__.'/depend/vendor/bin/cpdf.exe'),
];


(new Converter($config))->run();