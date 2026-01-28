<?php
require __DIR__ . '/../app/auth.php';
require_login();
$cfg = require __DIR__ . '/../../config.php';

// Se já estiver logado como afiliado, vai direto pro painel
if (!empty($_SESSION['agent_id'])) {
  header("Location: /solicitacoes.php");
  exit;
}

// Senão, vai pro login do afiliado
header("Location: /login.php");
exit;