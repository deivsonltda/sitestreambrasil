<?php
function require_login(): void {
  session_start();
  if (empty($_SESSION['agent_id'])) {
    header('Location: /crm/public/login.php');
    exit;
  }
}

function agent_id(): string {
  return $_SESSION['agent_id'] ?? '';
}

function agent_name(): string {
  return $_SESSION['agent_name'] ?? '';
}

function require_admin(): void {
  require_login();
  $role = $_SESSION['agent_role'] ?? 'agent';
  if ($role !== 'admin') {
    http_response_code(403);
    echo "Acesso negado (admin).";
    exit;
  }
}