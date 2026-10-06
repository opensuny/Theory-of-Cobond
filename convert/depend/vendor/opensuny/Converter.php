<?php

namespace opensuny ;

use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Extension\Table\TableExtension;
use setasign\Fpdi\Tcpdf\Fpdi;

class Converter  {
        
    private $lang;
    private $format;
    private $url;
    private $chrome;
    private $page_num_offset = 1;
    private $paper_size = 'A4';    
    private $catalog = 1;
    private $page_bleed = 0;
    
    private $preface_pages = 6;
    private $catalog_pages = 7;
    
    private $odd_page_start = 1;
    
    private $page_break_global = 0;
    
    private $page_break_exclude = 0;
    
    private $page_break_include = 0;
    
    private $page_break_catalog = 0;
    
    private $title = '';
    
    private $seglen = 6;
    
    private $cpdf;
    
    private $root_dir;
    private $out_dir;
    private $doc_dir;
    
    public function __construct(string $config, $rootdir) {        
        
        $this->root_dir = $rootdir;
        $this->parseConfig($config);
    }
    
    protected function parseConfig($file) {
        if(empty($file) || !is_file($file)) {
            exit("$file is not exists.");
        }
        
        $this->cpdf = realpath($this->root_dir.'/depend/vendor/bin/cpdf.exe');
        
        foreach(file($file) as $line) {
            $line = trim($line);
            preg_match('/^(\w+):\s+(.+)/i', $line, $matches);            
            
            if(empty($matches)) continue;
            
            $this->{$matches[1]} = $matches[2];
        }
                
        $this->doc_dir = realpath($this->root_dir.'/../manuscripts/'.$this->lang);
        $this->out_dir = $this->root_dir. '/'.$this->lang;
        
        is_dir($this->out_dir) or mkdir($this->out_dir);
        
        if(!$this->doc_dir || !is_dir($this->doc_dir)) {
            exit("$this->doc_dir is not exsits.");
        }
        
        if(!in_array($this->format, ['screen.pdf', 'print.pdf', 'website.html', 'single.html'])) {
            exit("format: screen.pdf  | print.pdf | website.html | single.html");
        }
        
        $resp = file_get_contents($this->url.'/assets/normalize.css');
        if(!$this->url || !$resp){
            exit("{$this->url} is invalid.");
        }
        
        $this->url .= '/' . $this->lang ;
        
        if(empty($this->chrome) || !is_executable($this->chrome)) {
            exit("chrome {$this->chrome} is invalid.");
        }
        
        if(empty($this->cpdf) || !is_executable($this->cpdf)) {
            exit("{$this->cpdf} invalid.");
        }
        
        if($this->seglen < 3) {
            exit("seglen must greater than 3. ");
        }
        
        $this->page_break_exclude = preg_split('/\D+/', $this->page_break_exclude);
        
        $this->page_break_include = preg_split('/\D+/', $this->page_break_include);
        
    }
    
    public function __set($name, $value) {
        exit("unkown item $name");
    }
    
    public function run(){
        
        $this->recursiveCopy(dirname($this->out_dir).'/assets', $this->out_dir . '/assets', false);
        if(is_file($this->doc_dir.'/style.css') && !is_file($this->out_dir . '/assets/style.css') ) {
            copy($this->doc_dir.'/style.css', $this->out_dir . '/assets/style.css');
        }
        
        if(in_array($this->format, ['single.html', 'website.html'])) {
            return $this->mergeHtml( $this->format === 'single.html');
        }
        
        $htmlFile = $this->mergeHtml( true );
        $printFile = $this->out_dir.'/book.print.pdf';
        
        $this->printPDF($this->url.'/'.basename($htmlFile), $printFile);
        
        $bookmark = $this->out_dir.'/bookmark.txt';
        $this->saveBookmark($printFile, $bookmark);
        
        $pagedFile = $this->out_dir.'/book.paged.pdf';
        $this->addPageNum($printFile, $pagedFile);
        
        $bookFile = $this->out_dir.'/book.pdf';
        
        $this->addBookmark($pagedFile, $bookmark, $bookFile);
        
        unlink($printFile);
        unlink($pagedFile);
    }
    
    protected function addBookMark($srcFile, $catalog, $outFile) {
        $cmd = escapeshellarg($this->cpdf). " -add-bookmarks ". escapeshellarg($catalog) .' ' . escapeshellarg($srcFile). ' -o '. escapeshellarg($outFile);
        
        echo "$cmd \r\n ";
        echo exec($cmd, $outputLines, $returnCode);
    }
    
    protected function saveBookmark($srcFile, $outFile) {
        $cmd = escapeshellarg($this->cpdf). " -list-bookmarks -utf8 " . escapeshellarg($srcFile) . " > ". escapeshellarg($outFile);
        echo "\r\nOutput Bookmark $outFile \r\n";
        echo "$cmd \r\n";
        
        echo shell_exec($cmd);
        
        $lines = file($outFile);
        $list = [];
        
        foreach( $lines as $i => $line) {
            $list[$i] = $line;
            preg_match('/^(\d\s)"(.+?)"([\s\S]+)/i', $line, $res);
            
            $t = $res[2];
            $len = strlen($t);
            if($len <= $this->seglen ) continue;
            $a = substr($t, 0, $this->seglen);
            $pos = strrpos($t, $a, $this->seglen);
            if($pos === false) continue;
            $n = substr($t, $pos);
            
            $list[$i] = $res[1].'"'.$n.'"'.$res[3];
        }
        
        file_put_contents($outFile, implode('', $list) );
        
    }
    
    
    protected function addPageNum($srcFile, $outFile) {
        
        $pdf = new Fpdi();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetFooterMargin(0);
        $pdf->SetAutoPageBreak(false, 0);
        
        $pageCount = $pdf->setSourceFile($srcFile);
        $bodyStartPage = $this->page_num_offset + 1 ; // 页码偏移
        
        $pdf->setTitle($this->title);
        
        for ($i = 1; $i <= $pageCount; $i++) {
            $tpl = $pdf->importPage($i);
            $size = $pdf->getTemplateSize($tpl);
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($tpl, 0, 0, $size['width'], $size['height']);
            
            if ($i < $bodyStartPage) continue;
            
            $pageNum = $i - $bodyStartPage + 1;
            $footerY = $size['height'] - 15;
            
            $pdf->SetAbsXY(0, $footerY);
            $text = "$pageNum / ". ($pageCount - $bodyStartPage + 1 );
            $pdf->Cell($size['width'], 10, $text, 0, 0, 'C');
        }
        
        $pdf->Output($outFile, 'F');
    }
    
    protected function mergeHtml($single = false) {
        
        echo "\r\nMerging documents: $this->doc_dir \r\n";
        $docs = glob($this->doc_dir.'/*.md');
        $converter = new CommonMarkConverter([]);
        $converter->getEnvironment()->addExtension(new TableExtension());
        
        $output = '';
        
        $counter = 0;
        
        //封面页
        $pages = 1;
        
        $catatree = [];
        
        foreach($docs as $i => $file) {
            //if($i > 2 ) continue;
            $content = file_get_contents($file);
            
            $title = '';
            
            $content =  preg_replace('/^(\d+)\.\s/m', '$1.', $content);
            $content =  preg_replace('/^(<div class=")(note|story|captain)(">[\r\n]+)/m', '$1$2">', $content);
            
            $content = $converter->convert( $content );
            
            $content =  preg_replace('/^<p>(\d+)\.(\S)/m', '<p>$1. $2', $content);
            $content =  preg_replace('/^(\d+)\.(\S)/m', '$1. $2', $content);
            
            $content = str_replace(["\r\n", "\r"], "\n", $content);
            $content = str_replace("\n", "\r\n", $content);
            
            $content =  preg_replace('/<table>/m', '<table class="table table-bordered">', $content);
            
            //如果全局分页 且未在排除列表中
            if($this->page_break_global && !in_array($i, $this->page_break_exclude) ) {
                $content = $content . '<p class="page-break"></p>';
            }
            
            //如果全局不分页，且在分页列表中
            if(!$this->page_break_global && in_array($i, $this->page_break_include)) {
                $content = $content . '<p class="page-break"></p>';
            }
            
            $content = "<div class=\"part part-{$i}\">" .  $content . '</div>';
            
            preg_match('|<h1.*?>(.+?)</h1>?|', $content, $matches);
            if(!empty($matches[1])) {
                $title = $matches[1];
            }
            
            //目录页位于术语表之后
            if($i === 2 && $this->catalog) {
                $content = $content . $this->addCatalog();
            }
            
            $output .= $content;
        }
        
        $output = str_replace(["\r\n", "\r"], "\n", $output);
        $output = str_replace("\n", "\r\n", $output);
        
        $content = str_replace(['{title}', '{content}'], [$this->title, $output], file_get_contents($this->doc_dir.'/page.html'));
        $file = "{$this->out_dir}/book.html";
        
        file_put_contents($file, $content);
        echo "Meged $file, $counter parts. \r\n";
        
        return $file;        
    }
    
    protected function addCatalog() {
        $file = $this->out_dir.'/bookmark.txt';
        if(!is_file($file)) return ;
        
        $list  = [];
        foreach (file($file) as $i => $line) {
            if($i <=3 ) continue;
            preg_match('#^(\d+)\s+"(.+?)"\s+(\d+)#', $line, $res);
            if(empty($res[2])) continue ;
            
            $list[] = [$res[1], $res[2], $res[3] - $this->page_num_offset ];
        }
        
        @ob_clean();
        ob_start();
        include $this->doc_dir.'/catalog.html';
        if($this->page_break_catalog ) {
            echo '<p class="page-break"></p>';
        }
        
        $c = ob_get_clean();
        
        return $c;
    }
    
    public function printPDF($url, $outfile) {
        $begin = microtime(true);
        $command = escapeshellarg($this->chrome) . ' ' .
            '--headless=new ' .
            '--no-pdf-header-footer ' .
            '--disable-gpu ' .
            '--virtual-time-budget=10000 ' .
            '--no-pdf-header-footer ' .
            '--generate-pdf-document-outline=true ' .
            '--print-to-pdf=' . escapeshellarg($outfile) . ' ' .
            escapeshellarg($url);
            
            echo "\r\nPrinting {$url} => $outfile \r\n";
            
            echo shell_exec($command);
            $end = microtime(true);
            
            echo intval($end - $begin), " seconds. \r\n";
    }
    
    public function optimizeBookmarks($srcFile, $outFile) {
        $catalog = $this->out_dir.'/bookmark.txt';
        
        
        $cmd = escapeshellarg($this->cpdf). " -list-bookmarks -utf8 " . escapeshellarg($srcFile) . " > ". escapeshellarg($catalog);
        echo "$cmd \r\n";
        echo shell_exec($cmd);
        
        $lines = file($catalog);
        $list = [];
        
        foreach( $lines as $i => $line) {
            $list[$i] = $line;
            preg_match('/^(\d\s)"(.+?)"([\s\S]+)/i', $line, $res);
            
            $t = $res[2];
            $len = strlen($t);
            if($len <= 6 ) continue;
            $a = substr($t, 0, 6);
            $pos = strrpos($t, $a, 6);
            if($pos === false) continue;
            $n = substr($t, $pos);
            
            $list[$i] = $res[1].'"'.$n.'"'.$res[3];
        }
        
        file_put_contents($catalog, implode('', $list) );
        
        $cmd = escapeshellarg($this->cpdf). " -add-bookmarks ". escapeshellarg($catalog) .' ' . escapeshellarg($srcFile). ' -o '. escapeshellarg($outFile);
        
        echo "$cmd \r\n ";
        echo exec($cmd, $outputLines, $returnCode);
        return $catalog;
    }
    
    public function recursiveCopy($source, $dest, $overWrite=false) {
        if (!is_dir($source)) {
            return false;
        }
        
        if (!is_dir($dest)) {
            mkdir($dest, 0755, true);
        }
        
        $files = scandir($source);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            
            $srcPath = $source . DIRECTORY_SEPARATOR . $file;
            $dstPath = $dest . DIRECTORY_SEPARATOR . $file;
            
            if (is_dir($srcPath)) {
                $this->recursiveCopy($srcPath, $dstPath);
            } else {
                if($overWrite && is_file($dstPath)) unlink($dstPath);                 
                if(!is_file($dstPath)) copy($srcPath, $dstPath);
            }
        }
        
        return true;
    }
}