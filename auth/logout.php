<?php
/**
 * ProLink - Logout
 * Path: /Prolink/auth/logout.php
 */
session_start();
session_unset();
session_destroy();
$baseUrl = '/Prolink';
if (defined('BASE_URL')) { $baseUrl = rtrim(BASE_URL, '/'); }
header('Location: ' . $baseUrl . '/user/browse-services.php');
exit;
