<?php
session_start();
session_destroy();
header('Location: /crm/public/login.php');
exit;