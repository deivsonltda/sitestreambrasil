<?php
session_start();

// Se já estiver logado como afiliado, vai direto pro painel
if (!empty($_SESSION['affiliate_id'])) {
  header("Location: /dashboard.php");
  exit;
}

// Senão, vai pro login do afiliado
header("Location: /login.php");
exit;