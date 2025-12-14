<?php
ini_set('display_errors','1'); error_reporting(E_ALL);
require_once __DIR__ . '/../Lib/config.php';
header('Content-Type: text/html; charset=utf-8');
echo "<!doctype html><meta charset='utf-8'><title>probe navbar</title>";
echo "<p>before navbar</p>";
require_once __DIR__ . '/../partials/navbar.php';
echo "<p>after navbar</p>";
