<?php
session_start();
$cfg = require __DIR__ . '/../../config.php';
header("Location: " . $cfg['BASE_AFF'] . "/login.php");
exit;