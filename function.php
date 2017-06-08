<?php
/**
 * Created by PhpStorm.
 * User: wangruirong
 * Date: 2017/4/28
 * Time: 12:11
 */

// 若有需要可以编写用户自定义函数  函数可以在配置文件即INI文件中被调用 用于处理复杂的格式化或采集下载器的需要
// 应用于 idaima.com的抓取
function getRealLanguageId($lId)
{
    $lIdArr = [
        12 => 1,
        8 => 3,
        9 => 1,
        4 => 1,
        13 => 1,
        2 => 1,
        5 => 3,
        7 => 3,
        11 => 1,
        3 => 3,
        6 => 1,
        10 => 1,
        16 => 9,
        17 => 7,
        18 => 3,
        15 => 5,
        19 => 3,
        20 => 1,
        22 => 6,
        14 => 6,
        1 => 7,
    ];

    if (isset($lIdArr[$lId])) {
        return $lIdArr[$lId];
    }

    return 7;
}

// 应用于 myjavainn.com的抓取
function getRealTags($tags)
{
    $tags = trim(strip_tags($tags));
    return implode(',', array_unique(explode(' ', $tags)));
}

// 应用于 myjavainn.com的抓取
function myjavainn_languageId($lId)
{
    $lIdArr = [
        '站长推荐' => 1,
        'JAVA基础' => 1,
        'JAVA进阶' => 1,
        'JAVA高级' => 1,
        '后端框架' => 1,
        '常见问题' => 1,
        '面向服务的架构' => 1,
        '分布式系统基础' => 1,
        '互联网安全架构' => 1,
        '系统稳定性控制' => 1,
        '数据分析与处理' => 1,
        '网站优化' => 3,
        '前端框架' => 3,
        'JSP' => 3,
        'Jquery/JS' => 3,
        'Html/CSS' => 3,
        'Html5' => 3,
        'AJAX' => 3,
        '服务器推送技术' => 3,
        '获取网络资源' => 1,
        '爬虫项目实战' => 1,
        '数据库基础' => 5,
        'MySQL' => 5,
        'Oracle' => 5,
        'TIDB' =>  5,
        '数据库连接池' => 5,
        '持久层框架' => 5,
        '大数据基础' => 5,
        'Redis' => 5,
        'Hadoop' => 5,
        '操作系统' => 4,
    ];

    if (isset($lIdArr[$lId])) {
        return $lIdArr[$lId];
    }

    return 7;
}
