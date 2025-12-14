<?php
ini_set('display_errors','1'); error_reporting(E_ALL);
header('Content-Type: text/plain; charset=utf-8');

$paths=[__DIR__.'/../Lib/config.php', __DIR__.'/../lib/config.php'];
$loaded=false;
foreach($paths as $p){ if (is_file($p)){ require_once $p; $loaded=true; break; } }

echo "config: ".($loaded?'FOUND':'MISSING')."\n";
echo "BASE_URL: ".(defined('BASE_URL')?BASE_URL:'(not defined)')."\n";
echo "DB_OK: ".(isset($GLOBALS['DB_OK'])?($GLOBALS['DB_OK']?'true':'false'):'(unset)')."\n";
echo "DB_ERR: ".($GLOBALS['DB_ERR_MSG']??'(none)')."\n";
