<?php
// /register.php (redirect to auth)
require_once __DIR__ . '/Lib/config.php';
header('Location: ' . rtrim(BASE_URL, '/') . '/auth/register.php'); exit;
