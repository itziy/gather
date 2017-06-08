<?php

/**
 * 日志记录
 * Author: Rain
*/
class Log
{
    private $logDir = null;

    const STD_NOTICE = 0;
    const STD_WARN = 1;
    const STD_ERROR = 2;

    private $logLevel = [
        'NOTICE',
        'WARN',
        'ERROR'
    ];

    public function __construct()
    {
	$this->logDir = __DIR__.'/../log/';
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir);
        }
        $this->logDir .= date('Ymd').'/';
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir);
        }
    }

    public function info($content)
    {
        return $this->write($content, static::STD_NOTICE);
    }

    public function warn($content)
    {
        return $this->write($content, static::STD_WARN);
    }

    public function error($content)
    {
        return $this->write($content, static::STD_ERROR);
    }

    private function write($content, $level)
    {
        $f = $this->logDir.$this->logLevel[$level].'.txt';
        $content = date('Y-m-d H:i:s')."\t".$this->logLevel[$level]."\t".$content."\n";
        return file_put_contents($f, $content, FILE_APPEND);
    }
}

