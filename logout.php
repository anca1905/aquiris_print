<?php
// logout.php
require_once __DIR__ . '/config/app.php';
logout();
header('Location: login.php');
exit;
