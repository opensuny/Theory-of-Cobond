<?php

namespace opensuny ;

use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Extension\Table\TableExtension;
use setasign\Fpdi\Tcpdf\Fpdi;


class Converter  {
        
    private $lang;
    private $format;
    private $url;
    private $cpdf;
    private $chrome;
    private $out_dir;
    private $doc_dir;
    
    private $tag;
    
    public function __construct(array $config) {
        foreach($config as $name => $value) {
            $this->$name = $value;
        }
        
        $this->tag = uniqid(date('mdHis_'));
    }
    
    public function run(){
        is_dir($this->out_dir) or mkdir($this->out_dir);
        $this->recursiveCopy(dirname($this->out_dir).'/assets', $this->out_dir . '/assets');
        
        if(in_array($this->format, ['single.html', 'website.html'])) {
            return $this->html( $this->format === 'single.html');
        }
        
        $htmlFile = $this->html( true );
        $printFile = $this->out_dir.'/book.print.pdf';
        
        $this->printPDF($this->url.'/'.basename($htmlFile), $printFile);
        
        $bookmark = $this->out_dir.'/bookmark.txt';
        $this->saveBookmark($printFile, $bookmark);
        
        $pagedFile = $this->out_dir.'/book.paged.pdf';
        $this->addPageNum($printFile, $pagedFile);
        
        $bookFile = $this->out_dir.'/book.pdf';
        
        $this->addBookmark($pagedFile, $bookmark, $bookFile);
    }
    
    protected function addBookMark($srcFile, $catalog, $outFile) {
        $cmd = escapeshellarg($this->cpdf). " -add-bookmarks ". escapeshellarg($catalog) .' ' . escapeshellarg($srcFile). ' -o '. escapeshellarg($outFile);
        
        echo "$cmd \r\n ";
        echo exec($cmd, $outputLines, $returnCode);
    }
    
    protected function saveBookmark($srcFile, $outFile) {
        $cmd = escapeshellarg($this->cpdf). " -list-bookmarks -utf8 " . escapeshellarg($srcFile) . " > ". escapeshellarg($outFile);
        echo "$cmd \r\n";
        echo shell_exec($cmd);
        
        $lines = file($outFile);
        $list = [];
        
        foreach( $lines as $i => $line) {
            $list[$i] = $line;
            preg_match('/^(\d\s)"(.+?)"([\s\S]+)/i', $line, $res);
            
            $t = $res[2];
            $len = strlen($t);
            if($len <= 9 ) continue;
            $a = substr($t, 0, 9);
            $pos = strrpos($t, $a, 9);
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
        $bodyStartPage = 7; // 页码开始位置
        
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
    
    protected function html($single = false) {
        
        $docs = glob($this->doc_dir.'/*.md');
        $converter = new CommonMarkConverter([]);
        $converter->getEnvironment()->addExtension(new TableExtension());
        
        $output = file_get_contents($this->doc_dir.'/page.html');
        
        $output = str_replace('{content}', $output, file_get_contents($this->doc_dir.'/cover.html'));;
        
        echo "$this->doc_dir/*.md  scanning ... \r\n";
        foreach($docs as $i => $file) {
            //if($i > 2 ) continue;
            $content = file_get_contents($file);
            
            $content =  preg_replace('/^(\d+)\.\s/m', '$1.', $content);
            $content =  preg_replace('/^(<div class=")(note|story|captain)(">[\r\n]+)/m', '$1$2">', $content);
            
            $content = $converter->convert( $content );
            //$content = preg_replace('/<\/strong>\R/', '</strong><br />', $content);
            
            $content =  preg_replace('/^<p>(\d+)\.(\S)/m', '<p>$1. $2', $content);
            $content =  preg_replace('/^(\d+)\.(\S)/m', '$1. $2', $content);
            
            $content =  preg_replace('/<table>/m', '<table class="table table-bordered">', $content);
            
            //if($single)
            
            $content = $content . '<p class="page-break"></p>';
            
            //if($i !== 0) {
            $content = "<div class=\"part part-{$i}\">" .  $content . '</div>';
            //}
            
            if(!$single) {
                
            }
            
            $output .= $content;
        }
        
        $output = str_replace(["\r\n", "\r"], "\n", $output);
        $output = str_replace("\n", "\r\n", $output);
        
        $content = str_replace('{content}', $output, file_get_contents($this->doc_dir.'/page.html'));
        $file = "{$this->out_dir}/book.html";
        
        echo "$file created \r\n";
        file_put_contents($file, $content);
        
        return $file;        
    }
    
    public function printPDF($url, $outfile) {
        $command = escapeshellarg($this->chrome) . ' ' .
            '--headless=new ' .
            '--no-pdf-header-footer ' .
            '--disable-gpu ' .
            '--virtual-time-budget=10000 ' .
            '--no-pdf-header-footer ' .
            '--generate-pdf-document-outline=true ' .
            '--print-to-pdf=' . escapeshellarg($outfile) . ' ' .
            escapeshellarg($url);
            
            echo "$url: printing {$outfile} \r\n";
            echo $output = shell_exec($command);
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
            if($len <= 9 ) continue;
            $a = substr($t, 0, 9);
            $pos = strrpos($t, $a, 9);
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
    
    public function recursiveCopy($source, $dest) {
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
            } else if(!is_file($dstPath)) {
                copy($srcPath, $dstPath);
            }
        }
        
        return true;
    }
}