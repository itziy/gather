<<<<<<< HEAD
<?php
/**
 * Created by PhpStorm.
 * User: wangruirong
 * Date: 2017/4/13
 * Time: 13:58
 */

// 存储采集URL地址，避免重复采集 仅保留后 n 条记录
define('SAVE_DATA_RECORDS', 100000);

include __DIR__.'/boot.php';

$dir = __DIR__.'/ini/';

$log = new Log();

$files = [];

$handle = opendir($dir);
if (!$handle) {
    $log->error('error in open: '.$dir);
    die;
}


while (false !== ($file = readdir($handle))) {
    if ($file != "." && $file != ".." && strpos($file, '.ini') !== false) {
        array_push($files, $dir.$file);
    }
}
closedir($handle);

if (empty($files)) {
    $log->info('no config need to execute so exit');
}

// for test
//work($files);

$daemon = new DaemonCommand(true, 'nginx', '/tmp/DaemonCommand_std_output.txt');
$daemon->daemonize();
$daemon->setJobs(array('function' => 'work', 'argv' =>  [$files], 'runtime' => 1000));
// 目前只支持单进程
$daemon->start(1);
=======
<?php
/**
 * Created by PhpStorm.
 * User: wangruirong
 * Date: 2017/4/13
 * Time: 13:58
 */

// 存储采集URL地址，避免重复采集 仅保留后 n 条记录
define('SAVE_DATA_RECORDS', 100000);

include __DIR__.'/boot.php';

$dir = __DIR__.'/ini/';

$log = new Log();

$files = [];

$handle = opendir($dir);
if (!$handle) {
    $log->error('error in open: '.$dir);
    die;
}


while (false !== ($file = readdir($handle))) {
    if ($file != "." && $file != ".." && strpos($file, '.ini') !== false) {
        array_push($files, $dir.$file);
    }
}
closedir($handle);

if (empty($files)) {
    $log->info('no config need to execute so exit');
}

// for test
//work($files);

$daemon = new DaemonCommand(true, 'nginx', '/tmp/DaemonCommand_std_output.txt');
$daemon->daemonize();
$daemon->setJobs(array('function' => 'work', 'argv' =>  [$files], 'runtime' => 1000));
// 目前只支持单进程
$daemon->start(1);
>>>>>>> 2cb60c05ebd0aa660fb3ee7525cc77a9f127d040
