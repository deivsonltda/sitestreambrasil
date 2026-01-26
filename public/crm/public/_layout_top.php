<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

if (empty($_SESSION['agent_id'])) {
  header('Location: /crm/public/login.php');
  exit;
}

$AGENT_ID   = $_SESSION['agent_id'];
$AGENT_NAME = $_SESSION['agent_name'] ?? 'Usuário';
$AGENT_ROLE = $_SESSION['agent_role'] ?? 'agent'; // admin | agent

$current = $current ?? '';

// Páginas permitidas por role
$ALLOW_ADMIN = ['atendentes']; // admin só vê isso
$ALLOW_AGENT = ['solicitacoes', 'conversas', 'concluidas', 'chat']; // agent não vê admin

function h($s)
{
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// Bloqueio TOTAL por role
if ($AGENT_ROLE === 'admin') {
  if (!in_array($current, $ALLOW_ADMIN, true)) {
    http_response_code(403);
    echo '<h2 style="font-family:system-ui">Acesso negado</h2>';
    echo '<p>Usuário admin só pode acessar o painel de atendentes.</p>';
    exit;
  }
} else { // agent
  if (!in_array($current, $ALLOW_AGENT, true)) {
    http_response_code(403);
    echo '<h2 style="font-family:system-ui">Acesso negado</h2>';
    echo '<p>Você não tem permissão para acessar esta página.</p>';
    exit;
  }
}
?>
<!doctype html>
<html lang="pt-br">

<head>
  <meta charset="utf-8">
  <title>CRM Atendimento</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- CSS do CRM -->
  <link rel="stylesheet" href="/crm/public/assets/app.css">

  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body class="app-body">

  <aside class="sidebar">

    <!-- (opcional) brand: o CSS do print pode esconder o texto -->
    <div class="brand">
      <div class="brand-dot"></div>
      <div>
        <div class="brand-title">CRM</div>
        <div class="brand-sub">Atendimento</div>
      </div>
    </div>

    <!-- NAV com ícones (estilo do print) -->
    <nav class="nav">
      <?php if ($AGENT_ROLE === 'admin'): ?>
        <a class="nav-item <?= $current === 'atendentes' ? 'active' : '' ?>"
           href="/crm/public/atendentes.php"
           title="Atendentes">
          <i class="fa-solid fa-users-gear"></i>
        </a>
      <?php else: ?>
        <a class="nav-item <?= $current === 'solicitacoes' ? 'active' : '' ?>"
           href="/crm/public/solicitacoes.php"
           title="Solicitações">
          <i class="fa-regular fa-comment-dots"></i>
        </a>

        <a class="nav-item <?= $current === 'conversas' ? 'active' : '' ?>"
           href="/crm/public/conversas.php"
           title="Conversas">
          <i class="fa-solid fa-headset"></i>
        </a>

        <a class="nav-item <?= $current === 'concluidas' ? 'active' : '' ?>"
           href="/crm/public/concluidas.php"
           title="Concluídas">
          <i class="fa-regular fa-circle-check"></i>
        </a>
      <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
      <!-- Seu CSS pode esconder isso pra ficar idêntico ao print -->
      <div class="me">
        <div class="me-avatar"><?= strtoupper(substr($AGENT_NAME, 0, 1)) ?></div>
        <div class="me-info">
          <div class="me-name"><?= h($AGENT_NAME) ?></div>
          <div class="me-sub"><?= h($AGENT_ROLE) ?></div>
        </div>
      </div>

      <a class="logout" href="/crm/public/logout.php" title="Sair">
        <i class="fa-solid fa-right-from-bracket"></i>
      </a>
    </div>
  </aside>

  <main class="main">