<?php
require __DIR__ . '/../app/auth.php';
require_login();
$cfg = require __DIR__ . '/../../config.php';
header("Location: " . $cfg['BASE_CRM'] . "/login.php");
exit;