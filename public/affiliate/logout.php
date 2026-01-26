<?php
session_start();
require __DIR__ . '/../../src/supabase.php';

// Se tiver afiliado logado, invalida o remember_token no banco
$affiliateId = $_SESSION['affiliate_id'] ?? null;

if ($affiliateId) {
  try {
    sb_request(
      'PATCH',
      "affiliates?id=eq." . urlencode($affiliateId),
      ['remember_token' => null],
      true
    );
  } catch (Throwable $e) {
    // se der erro no banco, ainda assim vamos deslogar localmente
  }
}

// Apaga o cookie do "lembrar de mim"
setcookie(
  'affiliate_remember',
  '',
  time() - 3600,
  '/',
  '',
  isset($_SERVER['HTTPS']),
  true
);

// Limpa sessão
$_SESSION = [];
if (ini_get("session.use_cookies")) {
  $params = session_get_cookie_params();
  setcookie(session_name(), '', time() - 42000,
    $params["path"], $params["domain"], $params["secure"], $params["httponly"]
  );
}
session_destroy();

// Vai pro login
header("Location: /affiliate/login.php");
exit;