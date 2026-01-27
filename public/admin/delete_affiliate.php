<?php
require __DIR__ . '/_guard.php';
require __DIR__ . '/../../src/supabase.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo "method not allowed";
  exit;
}

$affiliateId = $_POST['affiliate_id'] ?? '';
$confirm = trim($_POST['confirm'] ?? '');

if (!$affiliateId) { http_response_code(400); echo "missing affiliate_id"; exit; }
if (mb_strtolower($confirm) !== 'excluir') { http_response_code(400); echo "confirmacao invalida"; exit; }

try {
  // Como suas FK estão com ON DELETE CASCADE, deletar o afiliado apaga regras, links, clicks, customers etc.
  sb_request('DELETE', "affiliates?id=eq.$affiliateId", null, true);

  header("Location: /affiliates.php?deleted=1");
  exit;

} catch (Exception $e) {
  http_response_code(500);
  echo "erro: " . $e->getMessage();
}
