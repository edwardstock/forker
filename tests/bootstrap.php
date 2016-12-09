<?php
/**
 * forker. 2016
 * Date: 01.12.16
 * Time: 16:56
 */

use edwardstock\forker\log\Logger;

define('FORKER_RPATH', __DIR__ . '/runtime') or defined('FORKER_RPATH');
define('ENV_TEST', true);

if (!is_dir(FORKER_RPATH)) {
    mkdir(FORKER_RPATH, 0775, true);
}

require(__DIR__ . '/../vendor/autoload.php');

$level = Logger::V_NORMAL;
foreach ($GLOBALS['argv'] ?? [] AS $arg) {
    if ($arg === '--debug') {
        $level = Logger::V_DEBUG;
    } else if ($arg === '-v' || $arg === '--verbose') {
        $level = Logger::V_VERBOSE;
    }

}

Logger::setLevel($level);

