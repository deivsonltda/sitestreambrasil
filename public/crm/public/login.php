<?php
session_start();
if (!empty($_SESSION['agent_id'])) {
  header('Location: /public/solicitacoes.php'); exit;
}
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="/public/assets/app.css" />
  <title>CRM - Login</title>
</head>
<body class="login-body">
  <div class="login-card">
    <div class="login-title">CRM Atendimento</div>
    <div class="login-sub">Entre com seu usuário</div>

    <form id="loginForm" class="login-form">
      <label>Usuário</label>
      <input name="username" autocomplete="username" required />
      <label>Senha</label>
      <input name="password" type="password" autocomplete="current-password" required />
      <button type="submit">Entrar</button>
      <div id="loginErr" class="login-err"></div>
    </form>

    <div class="login-hint">
      Dica: rode <code>/api/seed_agent.php</code> uma vez pra criar o admin (e apague depois).
    </div>
  </div>

<script>
document.getElementById('loginForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const fd = new FormData(e.target);
  const payload = { username: fd.get('username'), password: fd.get('password') };

  const res = await fetch('/api/login.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify(payload)
  });

  const json = await res.json().catch(()=>({ok:false}));
  const el = document.getElementById('loginErr');
  if (!json.ok) {
    el.textContent = 'Login inválido.';
    return;
  }
  location.href = '/public/solicitacoes.php';
});
</script>
</body>
</html>