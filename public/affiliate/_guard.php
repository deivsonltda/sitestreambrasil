<?php
session_start();

$cfg = require __DIR__ . '/../../config.php';
$baseAff = rtrim($cfg['BASE_AFF'] ?? '', ''); // ex: https://afiliado.streambrasil.online

// Se NÃO estiver logado, manda pro login
if (empty($_SESSION['affiliate_id'])) {
  // preferir URL absoluta do subdomínio, mas funciona também só /login.php
  if ($baseAff) {
    header("Location: " . $baseAff . "/login.php");
  } else {
    header("Location: /login.php");
  }
  exit;
}

// Se estiver logado, NÃO redireciona. Só deixa a página continuar.