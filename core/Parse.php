<?php
/**
 * Created by PhpStorm.
 * User: wangruirong
 * Date: 2017/4/13
 * Time: 15:45
 */

class Parse
{
    private $iniFile = null;
    private $data = null;

    public function __construct($file)
    {
        if (!file_exists($file)) {
            return false;
        }
        $this->iniFile = $file;
        $this->data = parse_ini_file($this->iniFile, true);
        if (!$this->data) {
            return false;
        }
    }

    public function get($key)
    {
        if (isset($this->data[$key])) {
            if (is_scalar($this->data[$key])) {
                return trim($this->data[$key]);
            } else {
                return $this->data[$key];
            }
        }
        return null;
    }

    public function getData()
    {
        return $this->data;
    }

    public function getIniFile()
    {
        return basename($this->iniFile, '.ini');
    }
}