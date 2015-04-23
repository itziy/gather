<?php
/**
	; 一个针对程序员编写的简单的网页采集工具
	; 目前只支持不需要进行登录及权限认证即可访问的页面资源的采集功能，包括文章、下载资源等
	; 目前暂时不支持IP代理功能，有需要的朋友可以自己进行扩展
	; 目前暂时不支持只提供临时下载地址的资源的采集，即没有做本地化的处理功能
	;
	; 功能描述
	; 1.支持多次跳转的资源下载功能
	; 2.支持采集内容的字符替换功能
	; 3.支持断点继续采集功能，根据设置PAGE_START参数
	;
	; 作者：Rain
	; 联系QQ：563268276
	; 网址：www.94cto.com / www.itziy.com / www.verypan.com
	; 时间：2015-04-23
	; 版本：V2
	; 版权：可以进行任意修改，再发布及自己学习使用，不可用于非法用途，否则后果自负
	;
	; 使用方式
		# 默认的配置文件名称是当前执行目录下的conf.ini
		# php gather.php conf.ini
	
	; 温馨提示：默认情况下，我们认定Linux系统默认命令行编码为utf-8，window系统命令行的编码为GBK编码
*/

$conf_file = dirname(__FILE__).'/conf.ini';

if (isset($argv[1]) && ($argv[1] == 'h' || $argv[1] == 'help' || $argv[1] == '-h' || $argv[1] == '--help'))
	die('usage: php gather.php conf.ini');

if (isset($argv[1]))
	$conf_file = $argv[1];
if (!file_exists($conf_file))
	die('configuration file: '.$conf_file.' not find');

$confArr = parse_ini_file($conf_file, true);
if (!is_array($confArr) || empty($confArr))
	die('configuration file: '.$conf_file.' invalid');

if (!isset($confArr['CONST']) || !isset($confArr['VARIABLE']))
	die('configuration file: '.$conf_file.' invalid');

//init const variable
foreach ($confArr['CONST'] as $ck => $cv)
{
	if (!defined($ck))
		define($ck, $cv);
}

//init global variable
foreach ($confArr['VARIABLE'] as $vk => $vv)
{
	$$vk = $vv;
}

$ga = new Gather();
$ga->run();

class Gather
{
	public function __construct()
	{
		$this->init_check();
	}

	public function run()
	{
		global $table_mapping, $text_filter, $preDownArr, $downArr, $need_host_check_field_name;

		for ($page = PAGE_START; $page <= PAGE_COUNT; $page++)
		{
			$this->write('开始采集列表第'.$page.'页的内容...');
			$list_content = $this->get(sprintf(WEB_LIST_URL, $page));
			if (empty($list_content))
			{
				$this->write('抓取的列表页的内容为空，所以过滤掉');
				continue;
			}

			$list_content = str_replace("\r", '', $list_content);
			$list_content = str_replace("\n", '', $list_content);

			//精准定位要抓取的模块内容
			if (!preg_match(WEB_LIST_POSTION, $list_content, $list_search))
			{
				$this->write('精准匹配列表页的内容失败，所以过滤掉');
				continue;
			}
			if (isset($list_search[1]))
				$list_content = $list_search[1];
			else
				$list_content = $list_search[0];
			//end

			preg_match_all(WEB_CONTENT_URL_REG, $list_content, $match);
			$this->write('实际抓取的内容记录条数'.count($match[0]));
			if (isset($match[1]) && is_array($match[1]) && !empty($match[1]))
				$match[0] = $match[1];
			if (is_array($match[0]) && !empty($match[0]))
			{
				$this->write('当前的列表页面，总共匹配到：'.count($match[0]).'个内容页');
				foreach ($match[0] as $kval => $val)
				{
					if (strpos($val, 'http:') === false)
					{
						if (substr($val, 0, 1) == '/')
							$val = WEB_HOST.$val;
						else
							$val = WEB_HOST.'/'.$val;
					}
					$list_url = $val;
					$this->write('当前抓取第'.($kval + 1).'条内容记录');
					$web_content = $this->get($val);
					$content_url = $val;

					if (empty($web_content))
					{
						$this->write('抓取的内容页为空,所以过滤掉');
						continue;
					}

					$web_content = str_replace("\r", '', $web_content);
					$web_content = str_replace("\n", '【】', $web_content);

					$sql = "INSERT INTO ".TABLE_NAME."(".implode(', ', array_keys($table_mapping)).")VALUES(";
					foreach ($table_mapping as $field => $reg)
						$sql .= ':'.$field.',';
					$sql = substr($sql ,0, -1);
					$sql .= ')';

					if (IS_DEBUG)
						$this->write('执行SQL '.$sql);

					$dsn = 'mysql:dbname='.DB_NAME.';host='.DB_HOST;
					try {
						$dbh = new PDO($dsn, DB_USER, DB_PWD);
					} catch (PDOException $e) {
						$this->write( 'Connection failed: ' . $e->getMessage(), true);
					}
					$dbh->query("set names 'utf8'");
					$sth = $dbh->prepare($sql);
					$sth->closeCursor();

					foreach ($table_mapping as $field => $reg)
					{
						if (substr($reg, 0, 1) !=  '/')
						{
							$$field = $reg;
						}
						else
						{
							if (!preg_match($reg, $web_content, $tmp_match))
							{
								if (defined('ITEM_IMAGE_FIELD_NAME') && ITEM_IMAGE_FIELD_NAME == $field)
									$$field = ITEM_DEFAULT_IMG;
								else
								{
									$this->write('对不起,匹配字段：'.$field.'失败，过滤此记录');
									continue 2;
								}
							}

							if (isset($tmp_match[1]))
								$$field = $tmp_match[1];
							$$field = $this->closetags($$field);
							$$field = trim($$field);
							
							//删除javascript脚本
							$$field = preg_replace('/<script(.*?)>(.*?)<\/script>/i', '', $$field);

							//将链接删除
							$$field = preg_replace('/<a(.*?)>(.*?)<\/a>/i', '${2}', $$field);

							//图片链接地址绝对地址化
							preg_match_all('/<img.*?src=("|\')+(.*?)("|\')+.*?>/i', $$field, $img_match);
							if (isset($img_match[2]) && is_array($img_match[2]) && !empty($img_match[2]))
							{
								foreach ($img_match[2] as $img_val)
								{
									if (strpos($img_val, 'http:') === false)
									{
										$new_val = $img_val;
										if (substr($new_val, 0, 1) != '/')
											$new_val = '/'.$img_val;
										$new_val = WEB_HOST.$new_val;
										$$field = str_replace($img_val, $new_val, $$field);
									}
								}
							}
							//end

							//针对HTML里面的pre的换行先做一个特殊处理
							$$field = preg_replace('/<pre.*?>(.*?)<\/pre>/i', '<pre class="prettyprint">${1}</pre>', $$field);
							preg_match_all('/<pre>(.*?)<\/pre>/i', $$field, $pre_match);
							if (isset($pre_match[1]) && is_array($pre_match[1]) && !empty($pre_match[1]))
							{
								foreach ($pre_match[1] as $pre_val)
									$$field = str_replace($pre_val, str_replace("【】", "\r\n", $pre_val), $$field);
							}
							//end
						}

						//入库之前，将对应的换行符号都还原回来
						$$field = str_replace('【】', "\r\n", $$field);
						//文本的过滤和替换操作
						if (is_array($text_filter) && !empty($text_filter))
						{
							foreach ($text_filter as $tk => $tv)
								$$field = str_ireplace($tk, $tv, $$field);
						}

						if (defined('ITEM_IMAGE_FIELD_NAME') && ITEM_IMAGE_FIELD_NAME == $field && (empty($$field) || trim($$field) == '#'))
							$$field = ITEM_DEFAULT_IMG;

						if (defined('ITEM_IMAGE_FIELD_NAME') && ITEM_IMAGE_FIELD_NAME == $field && stripos($$field, 'http:') === false)
						{
							if (substr($$field, 0, 1) == '/')
								$$field = WEB_HOST.trim($$field);
							else
								$$field = WEB_HOST.'/'.trim($$field);
						}

						if (is_array($need_host_check_field_name) && !empty($need_host_check_field_name) && in_array($field, $need_host_check_field_name) && stripos($$field, 'http:') === false)
						{
							if (substr($$field, 0, 1) == '/')
								$$field = WEB_HOST.trim($$field);
							else
								$$field = WEB_HOST.'/'.trim($$field);
						}

						if (defined('ITEM_DOWNLOAD_FIELD_NAME') && ITEM_DOWNLOAD_FIELD_NAME == $field)
						{
							if (!empty($preDownArr) && is_array($preDownArr))
							{
								$is_find_pre_down_url = false;
								foreach ($preDownArr as $pdk => $pdv)
								{
									if (stripos($$field, 'http:') === false)
										$pdv .= $$field;

									$pdv_content = $this->get($pdv, array('Referer: '.$content_url), false);
									$pdv_content = str_replace("\r", '', $pdv_content);
									$pdv_content = str_replace("\n", '', $pdv_content);

									if (!preg_match($pdk, $pdv_content, $pdv_match))
										continue;

									$$field = trim(urldecode($pdv_match[1]));
									$is_find_pre_down_url = true;
									break;
								}

								if (!$is_find_pre_down_url)
								{
									$this->write("匹配预下载的地址失败，所以过滤此记录");
									continue 2;
								}
							}
						}

						if (defined('ITEM_DOWNLOAD_FIELD_NAME') && ITEM_DOWNLOAD_FIELD_NAME == $field && stripos($$field, 'http:') === false)
						{
							if (is_array($downArr) && !empty($downArr))
							{
								foreach ($downArr as $d_url)
								{
									if (substr($$field, 0, 1) != '/')
										$$field = '/'.$$field;

									$d_url .= $$field;

									if (defined('ITEM_DOWNLOAD_FIELD_NAME') && ITEM_DOWNLOAD_FIELD_NAME == $field && (stripos($d_url, '&') !== false))
									{
										$find_real_down = false;
										$result_tmp = $this->get($d_url, array('Referer: '.$list_url), true);
										if (stripos($result_tmp, 'Location:')  !== false)
										{
											if (IS_DEBUG)
												$this->write("检查到有跳转的信息\n*****************************************************************************\n".$result_tmp."\n******************************************************************************");
											preg_match('/Location:(.*?)\r/i', $result_tmp, $url_tmp_match);
											if (isset($url_tmp_match[1]))
											{
												$find_real_down = true;
												$$field = trim($url_tmp_match[1]);
												break;
											}
											else
												continue;
										}

										if (!$find_real_down)
										{
											$this->write('下载地址:'.$d_url.'不存在location，所以过滤此记录');
											continue 3;
										}
									}
									else
									{
										$find_real_down = true;
										break;
									}
								}
							}
							else
							{
								if (substr($$field, 0, 1) == '/')
									$$field = WEB_HOST.trim($$field);
								else
									$$field = WEB_HOST.'/'.trim($$field);

								if (defined('ITEM_DOWNLOAD_FIELD_NAME') && ITEM_DOWNLOAD_FIELD_NAME == $field && (stripos($$field, '&') !== false))
								{
									$result_tmp = $this->get($$field, array('Referer: '.$list_url), true);
									if (stripos($result_tmp, 'Location:')  !== false)
									{
										if (IS_DEBUG)
											$this->write("检查到有跳转的信息\n*****************************************************************************\n".$result_tmp."\n******************************************************************************");
										preg_match('/Location:(.*?)\r/i', $result_tmp, $url_tmp_match);
										if (isset($url_tmp_match[1]))
											$$field = trim($url_tmp_match[1]);
									}
								}
							}
						}

						//对于下载的url地址，做最后一次判断
						if (defined('ITEM_DOWNLOAD_FIELD_NAME') && ITEM_DOWNLOAD_FIELD_NAME == $field)
						{
							if (IS_DEBUG)
								$this->write($$field);
							if (!($url_ret = @get_headers($$field, 1)))
							{
								$this->write('下载地址:'.$$field.'无效，所以过滤此记录');
								continue 2;
							}

							if (stripos($url_ret[0], '200 OK') === false)
							{
								$this->write('下载地址:'.$$field.'返回404错误，所以过滤此记录');
								continue 2;
							}

							if (stripos($$field, 'pan.baidu.com') === false)
							{
								if ($url_ret['Content-Length'] < 100)
								{
									$this->write('下载地址:'.$$field.'返回内容大小为'.$url_ret['Content-Length'].'，所以过滤此记录');
									continue 2;
								}
							}
						}

						$$field = trim($$field);

						if (IS_DEBUG)
							$this->write('* '.'字段：'.$field.'  值：'."\n****************************************************************\n".$$field."\n****************************************************************");

						$sth->bindValue(':'.$field, trim($$field));
					}
					if (INSERT_DB)
						$sth->execute();
					$sth->closeCursor();
					//$dbh = null;

					$this->write( '休息，暂停'.SLEEP_TIME.'秒后继续抓取...');
					sleep(SLEEP_TIME);
				}
			}
			else
			{
				$this->write('列表页面没有抓取到内容，所以过滤掉');
			}
		}
		$this->write('', true);
	}

	protected function closetags($html)
	{
		// 不需要补全的标签 
		$arr_single_tags = array('meta', 'img', 'br', 'link', 'area');
		// 匹配开始标签 
		preg_match_all('#<([a-z]+)(?: .*)?(?<![/|/ ])>#iU', $html, $result); 
		$openedtags = $result[1]; 
		// 匹配关闭标签 
		preg_match_all('#</([a-z]+)>#iU', $html, $result); 
		$closedtags = $result[1]; 
		// 计算关闭开启标签数量，如果相同就返回html数据 
		$len_opened = count($openedtags); 
		if (count($closedtags) == $len_opened)
			return $html; 

		// 把排序数组，将最后一个开启的标签放在最前面 
		$openedtags = array_reverse($openedtags); 
		// 遍历开启标签数组 
		for ($i = 0; $i < $len_opened; $i++)
		{
			// 如果需要补全的标签 
			if (!in_array($openedtags[$i], $arr_single_tags))
			{
				// 如果这个标签不在关闭的标签中 
				if (!in_array($openedtags[$i], $closedtags))
				{
					// 直接补全闭合标签
					$html .= '</' . $openedtags[$i] . '>';
				}
				else
				{
					unset($closedtags[array_search($openedtags[$i], $closedtags)]);
				}
			}
		}
		return $html;
	}

	protected function init_check()
	{
		if (!$this->check_curl_support())
			$this->write('对不起，请先开启CURL的类库的支持，否则无法执行', true);
		$this->check_mysql_connect();
		$this->write('程序初始化检查通过,执行后续的流程...');
	}

	private function get($url, $data = array(), $showhead = false)
	{
		$url = trim(html_entity_decode($url));

		$this->write('开始执行抓取: '.$url);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		//curl_setopt($ch, CURLOPT_USERAGENT, "Baiduspider+(+http://www.baidu.com/search/spider.htm)");
		curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)");
		curl_setopt($ch, CURLOPT_HEADER, $showhead);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $data);
		$ret = curl_exec($ch);
		$error = curl_error($ch);
		curl_close($ch);
		unset($ch);

		if (!empty($error))
		{
			$this->write('程序抓取URL: '.$url.'发生错误，错误信息: '.$error);
			return false;
		}
		if (WEB_CHARSET != 'utf-8')
			$ret = iconv(WEB_CHARSET, 'utf-8', $ret);
		return $ret;
	}

	//when check finish,mysql connect will auto close
	private function check_mysql_connect()
	{
		$con = mysql_connect(DB_HOST, DB_USER, DB_PWD);
		if (!is_resource($con))
			$this->write('程序无法成功链接到数据库,具体的错误信息:'.mysql_error(), true);
		if (!mysql_select_db(DB_NAME, $con))
			$this->write('程序无法链接到数据库: '.DB_NAME.'，具体的错误信息: '.mysql_error(), true);
		mysql_close($con);
	}

	private function check_curl_support()
	{
		if (!extension_loaded('curl') || !function_exists('curl_init'))
			return false;
		return true;
	}

	private function write($str, $end = false)
	{
		if (PATH_SEPARATOR == ':')
			echo $str,PHP_EOL,PHP_EOL;
		else
			echo iconv('UTF-8', 'GBK', $str),PHP_EOL,PHP_EOL;

		if ($end)
			die("program exit");

		sleep(OUTPUT_SPEED);
	}
}
