<?php
require_once __DIR__ . '/init.php';
Auth::logout();
header('Location: index.php');
exit;
?>