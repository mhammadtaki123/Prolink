<?php
ini_set('display_errors','1'); error_reporting(E_ALL);
echo "<pre>PHP OK\nProject: ".__DIR__."\n</pre>";
$paths=[__DIR__.'/Lib/config.php', __DIR__.'/lib/config.php'];
foreach($paths as $p){ echo (is_file($p)?"FOUND  ":"MISSING")."  $p\n"; }
