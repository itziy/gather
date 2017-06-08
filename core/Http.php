<?php

/**
 * Http 请求类
 * Author: Rain
*/
class Http
{
    /**
     * 浏览器标记
     */
    private $agent = 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/56.0.2924.87 Safari/537.36';

    /**
     * 来源地址
    */
    private $referer = '';

    /**
     * 请求的URL地址
    */
    private $url = '';

    /**
     * 请求的cookie信息
    */
    private $cookie = '';

    /**
     * 是否支持自动跳转
    */
    private $location = false;

    /**
     * 是否开启原始输出
    */
    private $binaryTransfer = false;


    /**
     * CURL http 句柄
    */
    private $httpHandel = null;

    /**
     * 是否返回HTTP 头信息
    */
    private $showHeader = true;

    /**
     * 是否返回HTTP Body信息
    */
    private $showBody = true;

    /**
     * CURL 错误信息
    */
    private $error = null;

    /**
     * CURL 错误号
    */
    private $errno = 0;

    /**
     * 是否为POST请求
    */
    private $isPost = false;

    /**
     * HTTP POST DATA
    */
    private $postData = [];

    /**
     * 设置请求配置数组
    */
    private $optArray = [];

    /**
     * 设置请求代理
    */
    private $proxy = null;

    /**
     * 设置执行超时时间：秒
    */
    private $execTimeout = 20;

    /**
     * 设置链接超时时间：秒
    */
    private $connTimeout = 5;

     /**
     * @return mixed
     */
    public function getBinaryTransfer()
    {
        return $this->binaryTransfer;
    }

    /**
     * @param mixed $binaryTransfer
     */
    public function setBinaryTransfer($binaryTransfer)
    {
        $this->binaryTransfer = $binaryTransfer;
    }


    /**
     * 链接结束保存cookie的文件
    */
    private $cookieJar = null;

    /**
     * 设定是否为HTTPS
    */
    private $isHttps = false;

    /**
     * @return mixed
     */
    public function getLocation()
    {
        return $this->location;
    }

    /**
     * @param mixed $location
     */
    public function setLocation($location)
    {
        $this->location = $location;
    }

    /**
     * @return mixed
     */
    public function getIsHttps()
    {
        return $this->isHttps;
    }

    /**
     * @param mixed $isHttps
     */
    public function setIsHttps($isHttps)
    {
        $this->isHttps = $isHttps;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getAgent()
    {
        return $this->agent;
    }

    /**
     * @param mixed $agent
     */
    public function setAgent($agent)
    {
        $this->agent = $agent;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getReferer()
    {
        return $this->referer;
    }

    /**
     * @param mixed $referer
     */
    public function setReferer($referer)
    {
        $this->referer = $referer;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @param mixed $url
     */
    public function setUrl($url)
    {
        $this->url = $url;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getCookie()
    {
        return $this->cookie;
    }

    /**
     * @param mixed $cookie
     */
    public function setCookie($cookie)
    {
        $this->cookie = $cookie;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getHttpHandel()
    {
        return $this->httpHandel;
    }

    /**
     * @param mixed $httpHandel
     */
    public function setHttpHandel($httpHandel)
    {
        $this->httpHandel = $httpHandel;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getShowHeader()
    {
        return $this->showHeader;
    }

    /**
     * @param mixed $showHeader
     */
    public function setShowHeader($showHeader)
    {
        $this->showHeader = $showHeader;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getShowBody()
    {
        return $this->showBody;
    }

    /**
     * @param mixed $showBody
     */
    public function setShowBody($showBody)
    {
        $this->showBody = $showBody;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * @param mixed $error
     */
    public function setError($error)
    {
        $this->error = $error;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getErrno()
    {
        return $this->errno;
    }

    /**
     * @param mixed $errno
     */
    public function setErrno($errno)
    {
        $this->errno = $errno;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getIsPost()
    {
        return $this->isPost;
    }

    /**
     * @param mixed $isPost
     */
    public function setIsPost($isPost)
    {
        $this->isPost = $isPost;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getPostData()
    {
        return $this->postData;
    }

    /**
     * @param mixed $postData
     */
    public function setPostData($postData)
    {
        $this->postData = $postData;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getOptArray()
    {
        return $this->optArray;
    }

    /**
     * @param mixed $optArray
     */
    public function setOptArray($optArray)
    {
        $this->optArray = $optArray;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getProxy()
    {
        return $this->proxy;
    }

    /**
     * @param mixed $proxy
     */
    public function setProxy($proxy)
    {
        $this->proxy = $proxy;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getExecTimeout()
    {
        return $this->execTimeout;
    }

    /**
     * @param mixed $execTimeout
     */
    public function setExecTimeout($execTimeout)
    {
        $this->execTimeout = $execTimeout;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getConnTimeout()
    {
        return $this->connTimeout;
    }

    /**
     * @param mixed $connTimeout
     */
    public function setConnTimeout($connTimeout)
    {
        $this->connTimeout = $connTimeout;
        return $this;
    }


    /**
     * @return mixed
     */
    public function getCookieJar()
    {
        return $this->cookieJar;
    }

    /**
     * @param mixed $cookieJar
     */
    public function setCookieJar($cookieJar)
    {
        $this->cookieJar = $cookieJar;
        return $this;
    }

    /**
     * HTTP GET REQUEST
    */
    public function get($url = null)
    {
        if (!is_null($url)) {
            $this->setUrl($url);
        }
        $this->initOpt();
        $this->initCurl();
        return $this->exec();
    }

    /**
     * HTTP POST REQUEST
    */
    public function post($url = null, $data = [])
    {
        if (!is_null($url)) {
            $this->setUrl($url);
        }
        $this->setIsPost(true);
        $this->setPostData($data);
        $this->initOpt();
        $this->initCurl();
        return $this->exec();
    }

    /**
     * 初始化CURL的请求句柄
    */
    private function initCurl()
    {
        $this->httpHandel = curl_init();
        return $this->httpHandel;
    }

    /**
     * 执行CURL请求
    */
    private function exec()
    {
        curl_setopt_array($this->getHttpHandel(), $this->getOptArray());
        $ret = curl_exec($this->getHttpHandel());
        $this->errno = curl_errno($this->getHttpHandel());
        if ($this->errno) {
            $this->error = curl_strerror($this->errno).', detail: '.curl_error($this->getHttpHandel());
        }
        curl_close($this->httpHandel);
        $this->setOptArray([]);
        $this->setHttpHandel(null);
        return $ret;
    }

    /**
     * 初始化请求类型
    */
    private function initOpt()
    {
        if ($this->getBinaryTransfer()) {
            $opt[CURLOPT_BINARYTRANSFER] = true;
            // 启用原始输出默认不输出http头信息
            $this->setShowHeader(false);
        }

        $opt[CURLOPT_USERAGENT] = $this->getAgent();
        $opt[CURLOPT_REFERER] = $this->getReferer();
        $opt[CURLOPT_URL] = $this->getUrl();
        $opt[CURLOPT_COOKIE] = $this->getCookie();
        $opt[CURLOPT_HEADER] = $this->getShowHeader();
        $opt[CURLOPT_NOBODY] = !$this->getShowBody();
        $opt[CURLOPT_POST] = $this->getIsPost();
        if ($this->getIsPost()) {
            $opt[CURLOPT_POSTFIELDS] = $this->getPostData();
        }
        if ($this->getProxy()) {
            $opt[CURLOPT_PROXY] = $this->getProxy();
        }

        if ($this->getIsHttps()) {
            $opt[CURLOPT_SSL_VERIFYPEER] = false;
            $opt[CURLOPT_SSL_VERIFYHOST] = false;
        }

        $opt[CURLOPT_TIMEOUT] = $this->getExecTimeout();
        $opt[CURLOPT_CONNECTTIMEOUT] = $this->getConnTimeout();
        $opt[CURLOPT_FOLLOWLOCATION] = $this->getLocation();
        if (!is_null($this->getCookieJar())) {
            $opt[CURLOPT_COOKIEJAR] = $this->getCookieJar();
        }

        $opt[CURLOPT_RETURNTRANSFER] = true;

        $userOpt = $this->getOptArray();
        if (is_array($userOpt) && !empty($userOpt)) {
            foreach ($userOpt as $uk => $uv) {
                $opt[$uk] = $uv;
            }
        }

        $this->setOptArray($opt);
    }
}
