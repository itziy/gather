<?php

/**
 * Created by PhpStorm.
 * User: wangruirong
 * Date: 2017/4/13
 * Time: 13:54
 */


include __DIR__.'/function.php';

//类的自动加载
spl_autoload_register(function ($class) {
    include __DIR__. '/core/' . $class . '.php';
});

function work($files = [])
{
    $gatherUrls = [];

    $dataPath = __DIR__.'/data/';
    if (!is_dir($dataPath)) {
        mkDirs($dataPath);
    }
    $gatherUrlFile = $dataPath.'/gatherUrls.txt';

    if (file_exists($gatherUrlFile)) {
        $tmpGatherUrls = explode("\n", file_get_contents($gatherUrlFile));
	if (count($tmpGatherUrls) > SAVE_DATA_RECORDS) {
		$tmpGatherUrls = array_slice($tmpGatherUrls, -SAVE_DATA_RECORDS, SAVE_DATA_RECORDS, true);
	}
	foreach ($tmpGatherUrls as $tgu) {
		$tgu = trim($tgu);
		$gatherUrls[$tgu] = $tgu;
        }
    }

    $log = new Log();
    foreach ($files as $file) {
        $parse = new Parse($file);
        $siteUrl = trim($parse->get('SITE_URL'));

        if (empty($siteUrl) || stripos($siteUrl, 'http') === false) {
            $log->warn('invalid parameter SITE_URL in file: '.$file.', so continue.');
            continue;
        }

        $http = new Http();
        if (stripos($siteUrl, 'https') !== false) {
            $http->setIsHttps(true);
        }
        $data = $http->get($siteUrl);
        $data = str_replace(["\r", "\n"], '', $data);
        if ($http->getErrno() || empty($data) || stripos($data, 'body') === false) {
            // 请求web首页发生异常
            $log->error('get: '.$siteUrl.', error info: '.$http->getError()." . so continue");
            continue;
        }

        // 检查页面的编码类型
        if (!preg_match('/<meta.*?charset=[^\w]?([-\w]+)/i', $data, $charsetMatch)) {
            $log->warn('match charset failed, so continue.');
            continue;
        }

        $charset = trim(strtolower($charsetMatch[1]));

        // 判断是否需要进行编码转换
        if ($charset != 'utf-8') {
            $data = iconv($charset, 'utf-8', $data);
        }

        // 判断内容URL正则
        $contentUrlMatch = trim($parse->get('CONTENT_URL_MATCH'));
        if (empty($contentUrlMatch)) {
            $log->warn('invalid parameter CONTENT_URL_MATCH in file: '.$file.', so continue.');
            continue;
        }

        if (!preg_match_all($contentUrlMatch, $data, $tmpContentUrlMatchs) || !isset($tmpContentUrlMatchs[1]) || !is_array($tmpContentUrlMatchs[1]) || empty($tmpContentUrlMatchs[1])) {
            // 内容页地址匹配失败
            $log->warn('content URL match failed: '.$contentUrlMatch.', so continue.');
            continue;
        }

        // 对得到的内容页面URL地址进行去重处理
        $tmpContentUrlMatchs[1] = array_unique($tmpContentUrlMatchs[1]);

        //解析补全内容页的URL地址
        foreach ($tmpContentUrlMatchs[1] as $contentUrl) {
            $contentUrl = trim($contentUrl);

            // 判断内容是否被采集
            if (isset($gatherUrls[$contentUrl])) {
                continue;
            }
            if (count($gatherUrls) >= SAVE_DATA_RECORDS) {
                array_shift($gatherUrls);
            }
            $gatherUrls[$contentUrl] = $contentUrl;

            if (is_array($gatherUrls) && !empty($gatherUrls)) {
                if (file_exists($gatherUrlFile)) {
                    unlink($gatherUrlFile);
                }
                foreach ($gatherUrls as $urlData) {
		    $urlData = trim($urlData);
                    file_put_contents($gatherUrlFile, $urlData."\n", FILE_APPEND);
                }
            }

            if (substr($contentUrl, 0, 1) == '/') {
                // 去掉开头的斜杠
                $contentUrl = substr($contentUrl, 1);
            }
            if (stripos($contentUrl, 'http') === false) {
                $contentUrl = $siteUrl.$contentUrl;
            }
            $http->setReferer($siteUrl);
            $contentData = $http->get($contentUrl);
            $contentData = str_replace(["\r", "\n"], '^^', $contentData);

            // 判断是否需要进行编码转换
            if ($charset != 'utf-8') {
                $contentData = iconv($charset, 'utf-8', $contentData);
            }

            $fields = $parse->get('fields');
            $defaultValue = $parse->get('defaultValue');
            $formatValue = $parse->get('formatValue');
            if (empty($fields) || !is_array($fields)) {
                $log->warn('no fields need to compare, so continue.');
                continue;
            }

            // 数据库对象数据
            $dbData = [];

            // 正则表达式开始过匹配每个字段
            foreach ($fields as $dbField => $preg) {
                // 没有设置默认值并且正则规则不存在或不合规直接过滤掉
                if ((empty($preg) || strpos($preg, '/') === false) && !isset($defaultValue[$dbField]) ) {
                    $log->warn('url: '.$contentUrl.' , field: '.$dbField.' preg invalid and no default value set , so continue.');
                    continue 2;
                }

                if ((empty($preg) || strpos($preg, '/') === false) && isset($defaultValue[$dbField]) ) {
                    $dbData[$dbField] = trim(getVal($defaultValue[$dbField]));
                    // 调试模式下，输出匹配的内容
                    if ($parse->get('IS_DEBUG')) {
                        $log->info('Match: '.$dbField.' --> '.$dbData[$dbField]);
                    }
                    continue;
                }

                // 如果存在字段未匹配上 过滤这个整个内容匹配并记录日志
                if (!preg_match($preg, $contentData, $fieldMatch) || !isset($fieldMatch[1]) || empty($fieldMatch[1])) {
                    if (!isset($defaultValue[$dbField])) {
                        $log->warn('url: '.$contentUrl.' , field: '.$dbField.' preg: '.$preg.'  not match, so continue.');
                        continue 2;
                    }
                    // 有默认值的情况下 读取默认值
                    $dbData[$dbField] = trim(getVal($defaultValue[$dbField]));
                    // 调试模式下，输出匹配的内容
                    if ($parse->get('IS_DEBUG')) {
                        $log->info('Match: '.$dbField.' --> '.$dbData[$dbField]);
                    }
                    continue;
                }

                // 过滤掉非允许的HTML的标签
                $fieldMatch[1] = strip_tags($fieldMatch[1], $parse->get('ALLOW_HTML_TAGS'));

                // 内容中的链接的处理
                if (preg_match_all('/<a.*?href.*?=("|\'?)(.*?)("|\'|\s|>)/i', $fieldMatch[1], $linkData) && isset($linkData[2]) && !empty($linkData[2])) {
                    foreach ($linkData[2] as $lk => $link) {
                        $link = trim($link);
                        // 过滤站内的超链接
                        if (substr($link, 0, 1) == '/' || stripos($link, $parse->get('SITE_URL')) !== false) {
                            $fieldMatch[1] = str_ireplace($link, '', $fieldMatch[1]);
                        } else {
                            // 站外链接 添加 target="_blank"
                            $fieldMatch[1] = str_ireplace('<a', '<a target="_blank" ', $fieldMatch[1]);
                        }
                    }
                }

		$imgPath = $parse->get('IMG_PATH');
	        $imgPathExt = $parse->get('IMG_PATH_EXT');
	        if (!empty($imgPathExt)) {
		    $imgPath .= date($imgPathExt);
	        }
	        mkDirs($imgPath);

                // 匹配所有的img 做本地化处理
                if (preg_match_all('/<img.*?src.*?=("|\'?)(.*?)("|\'|\s|>|\/>)/i', $fieldMatch[1], $imgData) && isset($imgData[2]) && !empty($imgData[2])) {
                    foreach ($imgData[2] as $img) {
                        $orgImgUrl = $img;
                        $img = trim($img);
                        if (substr($img, 0, 1) == '/') {
                            // 去掉开头的斜杠
                            $img = substr($img, 1);
                        }
                        if (stripos($img, 'http') === false) {
                            $img = $siteUrl.$img;
                        }
                        $imgParseUrl = parse_url($img);
			// 对没有扩展名的图片做处理
			if (!in_array(strtolower(substr($img, -3)), ['gif', 'png', 'jpg', 'peg', 'bmp'])) {
				$imgFile = trim($imgPath.md5($img).'.jpg');
			} else {
				$imgFile = trim($imgPath.md5($img).substr($imgParseUrl['path'], strrpos($imgParseUrl['path'], '.')));
			}
			
                        if (file_exists($imgFile)) {
                            $fieldMatch[1] = str_replace($orgImgUrl, str_replace($parse->get('WEB_ROOT'), '', $imgFile), $fieldMatch[1]);
                            continue;
                        }
                        $http->setBinaryTransfer(true);
                        // 设定 referer为自身 避免因为服务端判断了referer被forbidden
                        $http->setReferer($img);
                        $imgBinaryData = $http->get($img);

                        if (($curlError = $http->getError()) || empty($imgBinaryData)) {
                            $log->warn('本地化图片: '.$img." --> ".$imgFile." 发生异常: ".$curlError.", contentEmpty: ".var_export(empty($imgBinaryData), true)." 过滤此记录");
                            continue 3;
                        }
                        file_put_contents($imgFile, $imgBinaryData);
                        $fieldMatch[1] = str_replace($orgImgUrl, str_replace($parse->get('WEB_ROOT'), '', $imgFile), $fieldMatch[1]);
			sleep(10);
                    }
                }

                // 判断是否存在进一步格式化处理的函数
                if (isset($formatValue[$dbField]) && !empty($formatValue[$dbField])) {
                    $fieldMatch[1] = getFormatVal($formatValue[$dbField], $fieldMatch[1]);
                }

                // 把换行符还原回去
                $fieldMatch[1] = str_replace('^^', "\n", $fieldMatch[1]);

		
		// 如果采集的内容是一个封面的图片 进行本地化处理
		if (in_array(strtolower(substr($fieldMatch[1], -3)), ['gif', 'png', 'jpg', 'peg', 'bmp'])) {
                        $imgParseUrl = parse_url($fieldMatch[1]);
			$imgFile = trim($imgPath.md5($fieldMatch[1]).substr($imgParseUrl['path'], strrpos($imgParseUrl['path'], '.')));
			if (file_exists($imgFile)) {
                            $fieldMatch[1] = str_replace($fieldMatch[1], str_replace($parse->get('WEB_ROOT'), '', $imgFile), $fieldMatch[1]);
                        } else {
				$http->setBinaryTransfer(true);
				// 设定 referer为自身 避免因为服务端判断了referer被forbidden
				$http->setReferer($fieldMatch[1]);
				$imgBinaryData = $http->get($fieldMatch[1]);

				if (($curlError = $http->getError()) || empty($imgBinaryData)) {
				    $log->warn('本地化图片: '.$fieldMatch[1]." --> ".$imgFile." 发生异常: ".$curlError.", contentEmpty: ".var_export(empty($imgBinaryData))." 过滤此记录");
				    continue 2;
				}
				file_put_contents($imgFile, $imgBinaryData);
				$fieldMatch[1] = str_replace($fieldMatch[1], str_replace($parse->get('WEB_ROOT'), '', $imgFile), $fieldMatch[1]);
				sleep(10);
			}
		}

                // 最后一个步骤，过滤掉字符串多余空格
                $dbData[$dbField] = trim($fieldMatch[1]);

                // 调试模式下，输出匹配的内容
                if ($parse->get('IS_DEBUG')) {
                    $log->info('Match: '.$dbField.' --> '.$dbData[$dbField]);
                }
            }

            // 入库操作
            $sql = "INSERT INTO ".$parse->get('TABLE_NAME')."(".implode(', ', array_keys($dbData)).")VALUES(";
            foreach ($dbData as $field => $reg) {
                $sql .= ':'.$field.',';
            }
            $sql = substr($sql ,0, -1).')';
            if ($parse->get('IS_DEBUG')) {
                $log->info('SQL: '.$sql);
            }
            $dsn = 'mysql:dbname='.$parse->get('DB_NAME').';host='.$parse->get('DB_HOST');
            $dbh = null;
            try {
                $dbh = new PDO($dsn, $parse->get('DB_USER'), $parse->get('DB_PWD'));
            } catch (PDOException $e) {
               $log->error( 'Connection failed: ' . $e->getMessage(), true);
            }
            $dbh->query("set names 'utf8'");
            $sth = $dbh->prepare($sql);
            foreach ($dbData as $field => $reg) {
                $sth->bindValue(':'.$field, $reg);
            }
            if (!$parse->get('IS_DEBUG')) {
                $sth->execute();
            }
            $sth->closeCursor();
            $dbh = null;

            // 每次内容页抓取结束后休眠时间 避免采集过于频繁
            sleep($parse->get('SLEEP_EVERY_GATHER_CONTENT_FINISH'));
        }
    }
}

// 执行最终结果返回
function getVal($val)
{
    $ret = '';
    $funcName = $val;
    $parameters = [];

    if (strpos($val, '(') !== false && strpos($val, ')') !== false) {
        $funcName = trim(substr($val, 0, strpos($val, '(')));
        $parameters = explode(',', substr(str_ireplace($funcName.'(', '', $val), 0, -1));
    }

    foreach ($parameters as $pk => $pv) {
        $pv = trim($pv);
        if (strpos($pv, '(') !== false && strpos($pv, ')') !== false) {
            $parameters[$pk] = getVal($pv);
            continue;
        }
    }

    if (function_exists($funcName)) {
        $ret = call_user_func_array($funcName, $parameters);
    } else {
        $ret = $val;
    }

    return $ret;
}

// 获取格式化后的结果
function getFormatVal($val, $orgData)
{
    $ret = '';
    $funcName = $val;
    $parameters = [];

    $val = trim($val);

    if (strpos($val, '(') !== false && strpos($val, ')') !== false) {
        $funcName = trim(substr($val, 0, strpos($val, '(')));
        $parameters = explode(',', substr(str_ireplace($funcName.'(', '', $val), 0, -1));
    }

    foreach ($parameters as $pk => $pv) {
        $pv = trim($pv);
        if ($pv == '$0') {
            $parameters[$pk] = trim($orgData);
        }
        if (strpos($pv, '(') !== false && strpos($pv, ')') !== false) {
            $parameters[$pk] = trim(getFormatVal($pv, $orgData));
            continue;
        }
    }

    if (function_exists($funcName)) {
        $ret = call_user_func_array($funcName, $parameters);
    } else {
        $ret = $orgData;
    }

    return $ret;
}

function mkDirs($dir)
{
    if (!is_dir($dir)) {
        if (!mkDirs(dirname($dir))) {
            return false;
        }
        if (!mkdir($dir,0777)) {
            return false;
        }
    }
    return true;
}

