# 编写协同说明

为便于国际协作，本书稿使用markdown格式编写，并开发了最终格式导出工具。
最终格式是指用于阅读或印刷的格式，支持格式如下：

screen.pdf: 用于屏幕阅读的PDF格式，紧凑对称页边距，带导航书签(已支持)。
print.pdf:  用于印刷的PDF格式，非对称页边距，带目录页和导航书签（开发中）。
website.html:  用于在线网站托管的网页文档，每篇一个html文档，带左侧目录列表页（开发中）。
single.html:   单个网页，带左侧导航栏（开发中）。

pdf的生成是通过预先将markdown解析为html, 再调用chrome浏览器输出为PDF格式，故可以通过CSS样式调整最终输出样式。

# 目录说明
manuscripts中子目录为语言名称，例如zh-CN, en-US，包含以下文件：

cover.html: 封面内容
page.html:  生成html或pdf文件时使用的页面结构。
style.css:  当前语言版本的自定义CSS样式。
*.md:		正文内容，按数字前缀字典排序。

# PDF导出指引
导出顺序：生成html -> 调用chrome浏览器输出PDF, 这些流程已经编程实现自动化处理，但需要一些计算机环境配置（以Windows 10为例说明）。
环境要求：
	php 7.4+: 使用php命令行调用相关工具，请将php.exe所在目录添加到系统PATH环境变量中（以便在cmd下使用），必须。
	HTTP Server: 为chrome浏览器提供文件读取，推荐Apache HTTP Server、IIS。
	cpdf.exe: 已经内置，无须下载。

操作流程：
假设书稿的本机HTTP访问地址为：http://localhost/Theory-of-Cobond.
1. 启动windows cmd命令提示符, 并进入convert目录。
2. 输入: php main.php zh-CN screen.pdf http://localhost/Theory-of-Cobond/convert "C:\Program Files\Google\Chrome\Application\chrome.exe"。
 即可生成zh-CN/book.pdf，即为最终文档。zh-CN/assets为同步导出的样式文件目录（重复导出时，不会被覆盖），book.html在每次导出时会被覆盖。
 可以通过在浏览器中访问http://localhost/Theory-of-Cobond/convert/zh-CN/book.html，调整预览样式。
3. 上述命令参数有4个：
	语言名称：即manuscripts目录中定义的语言目录。
	输出格式：参见本文第一节 *编写协同说明* 。
	访问地址：convert目录的HTTP形式访问地址。
	chrome: 指定chrome浏览器程序的全路径。

