<?php

namespace opensuny ;

use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Extension\Table\TableExtension;


class Converter  {
    
    private $docdir;
    private $prefix;
    private $filename;
    
    private $lang;
    private $format;
    private $cpdf;
    private $chrome;
    private $output_dir;
    private $base_url;
    
    public function __construct(array $config) {
        foreach($config as $name => $value) {
            $this->$name = $value;
        }
        
        $this->docdir = ROOT_DIR.'/manuscripts/'.$config['lang'].'/';
        $this->filename = $config['lang'].'.'.$this->format;
        
        is_dir($this->output_dir) or mkdir($this->output_dir);
    }
    
    public function run() {
        $docs = glob($this->docdir.'/*.md');
        $converter = new CommonMarkConverter([]);
        $converter->getEnvironment()->addExtension(new TableExtension());
        
        $output = '';
        
        echo "$this->docdir/*.md \r\n";
        foreach($docs as $i => $file) {
            //if($i != 29 ) continue;
            $content = file_get_contents($file);            
            $content =  preg_replace('/^(\d+)\.\s/m', '$1.', $content);            
            $content =  preg_replace('/^(<div class=")(note|story|captain)(">[\r\n]+)/m', '$1$2">', $content);
            
            $content = $converter->convert( $content );
            //$content = preg_replace('/<\/strong>\R/', '</strong><br />', $content);
            
            $content =  preg_replace('/^<p>(\d+)\.(\S)/m', '<p>$1. $2', $content);
            $content =  preg_replace('/^(\d+)\.(\S)/m', '$1. $2', $content);
            
            $content =  preg_replace('/<table>/m', '<table class="table table-bordered">', $content);
            
            $output .=  $content . '<p class="page-break"></p>';
        }
             
        $content = str_replace('{content}', $output, file_get_contents($this->docdir.'/page.html')); 
        
        echo "{$this->output_dir}/{$this->filename}.tmp.html generated \r\n";
        file_put_contents("{$this->output_dir}/{$this->filename}.tmp.html", $content);
        
        $url = $this->base_url .'/'."{$this->filename}.tmp.html";
        $pdf = "{$this->output_dir}/{$this->filename}";
        
        $command = escapeshellarg($this->chrome) . ' ' .
            '--headless=new ' .
            '--no-pdf-header-footer ' .
            '--disable-gpu ' .
            '--virtual-time-budget=10000 ' .
            '--no-pdf-header-footer ' .
            '--generate-pdf-document-outline=true ' .
            '--print-to-pdf=' . escapeshellarg($pdf) . ' ' . 
            escapeshellarg($url);
        
            echo "$url:  printing to {$this->output_dir}/{$this->filename} \r\n";
            //echo $output = shell_exec($command);
            
            $merged = "{$this->output_dir}/{$this->filename}.merged.pdf";
            
            if(is_file("{$this->docdir}/cover.pdf")) {
                $command = 'cpdf -merge ' . escapeshellarg("{$this->docdir}/cover.pdf") . ' ' 
                . escapeshellarg($pdf) 
                . '  AND -scale-to-fit "210mm 297mm" -o ' . escapeshellarg($pdf);
                
                echo exec($command, $outputLines, $returnCode);
                
                $pdf = $merged;
            }
            
            //整理书签 待完善   
            return ;
            
            $cmd = "cpdf -list-bookmarks -utf8 " . escapeshellarg($merged) . "> bookmarks.txt";            
            $lines = file('bookmarks.txt');            
            $list = [];
            
            foreach( $lines as $i => $line) {
                $list[$i] = $line;
                preg_match('/^(\d\s)"(.+?)"([\s\S]+)/i', $line, $res);
                
                $t = $res[2];
                $len = strlen($t);
                if($len <= 12 ) continue;
                $a = substr($t, 0, 12);
                $pos = strpos($t, $a, 12);
                if($pos === false) continue;
                $n = substr($t, $pos);
                
                $list[$i] = $res[1].'"'.$n.'"'.$res[3];
            }
            
            file_put_contents('marked.txt', implode('', $list) );
            
            $cmd = "cpdf -add-bookmarks marked.txt ". escapeshellarg($merged). ' -o '. escapeshellarg($pdf);
            
            exec($cmd, $outputLines, $returnCode);
    }
}