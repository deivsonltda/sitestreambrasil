<?php
session_start();
require __DIR__ . '/../../src/supabase.php';
require __DIR__ . '/../../src/utils.php';

function set_remember_cookie(string $token): void
{
  $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

  // PHP 7.3+ suporta array com samesite
  setcookie('affiliate_remember', $token, [
    'expires'  => time() + (60 * 60 * 24 * 30), // 30 dias
    'path'     => '/',
    'secure'   => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
}

// Auto-login via "lembrar de mim"
if (empty($_SESSION['affiliate_id']) && !empty($_COOKIE['affiliate_remember'])) {
  $token = (string)$_COOKIE['affiliate_remember'];
  $tokenHash = hash('sha256', $token);

  $rows = sb_request(
    'GET',
    "affiliates?select=id,name,code&remember_token=eq." . urlencode($tokenHash) . "&limit=1",
    null,
    true
  );

  if ($rows) {
    $a = $rows[0];
    $_SESSION['affiliate_id']   = $a['id'];
    $_SESSION['affiliate_name'] = $a['name'];
    $_SESSION['affiliate_code'] = $a['code'];

    header("Location: /affiliate/dashboard.php");
    exit;
  } else {
    // cookie inválido -> apaga
    setcookie('affiliate_remember', '', time() - 3600, '/');
  }
}

if (!empty($_SESSION['affiliate_id'])) {
  header("Location: /affiliate/dashboard.php");
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    $code = trim($_POST['code'] ?? '');
    $pin  = trim($_POST['pin'] ?? '');
    $remember = !empty($_POST['remember']);

    if (!$code || !$pin) throw new Exception("Preencha usuário e senha.");

    $rows = sb_request(
      'GET',
      "affiliates?select=id,name,code,pin_hash&code=eq." . urlencode($code) . "&limit=1",
      null,
      true
    );
    if (!$rows) throw new Exception("Usuário não encontrado.");

    $a = $rows[0];
    if (!pin_verify($pin, $a['pin_hash'] ?? null)) {
      throw new Exception("Senha inválida.");
    }

    // sessão ok
    $_SESSION['affiliate_id']   = $a['id'];
    $_SESSION['affiliate_name'] = $a['name'];
    $_SESSION['affiliate_code'] = $a['code'];

    // lembrar de mim (SÓ DEPOIS DO LOGIN VALIDADO)
    if ($remember) {
      $token = bin2hex(random_bytes(32));       // token puro
      $tokenHash = hash('sha256', $token);      // hash para o banco

      sb_request(
        'PATCH',
        "affiliates?id=eq." . urlencode($a['id']),
        ['remember_token' => $tokenHash],
        true
      );

      set_remember_cookie($token);
    } else {
      // se desmarcar, garante que remove o cookie
      setcookie('affiliate_remember', '', time() - 3600, '/');
    }

    header("Location: /affiliate/dashboard.php");
    exit;
  } catch (Exception $e) {
    $error = $e->getMessage();
  }
}

$recoverUrl = "https://wa.me/5581984521498?text=" . urlencode("Olá! Esqueci minha senha de afiliado.");
?>
<!doctype html>
<html lang="pt-br">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <title>Login Afiliado</title>

  <style>
    :root {
      --primary: #6f6af8;
      --ink: #1f2937;
      --muted: #6b7280;
      --stroke: #e5e7eb;
      --bg: #f9fafb;
    }

    html,
    body {
      height: 100%
    }

    body {
      background: var(--bg);
      display: grid;
      place-items: center;
      font-family: system-ui, -apple-system, BlinkMacSystemFont;
    }

    .login-card {
      width: 100%;
      max-width: 420px;
      background: #fff;
      border: 1px solid var(--stroke);
      border-radius: 14px;
      padding: 28px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, .06);
    }

    .login-title {
      font-size: 1.4rem;
      font-weight: 700;
      text-align: center;
      margin-bottom: 22px;
      color: var(--ink);
    }

    .form-label {
      font-size: .9rem;
      font-weight: 600;
      color: var(--muted);
      margin-bottom: 4px;
    }

    .form-control {
      border-radius: 10px;
      border: 1px solid var(--stroke);
      padding: 10px 12px;
    }

    .form-control:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 .15rem rgba(111, 106, 248, .18);
    }

    /* Checkbox na paleta do projeto */
    .form-check-input {
      cursor: pointer;
    }

    .form-check-input:checked {
      background-color: var(--primary);
      border-color: var(--primary);
    }

    .form-check-input:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 0.15rem rgba(111, 106, 248, 0.35);
    }

    .password-wrap {
      position: relative;
    }

    .password-wrap i {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      color: #9ca3af;
      cursor: pointer;
    }

    .login-actions {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin: 12px 0 18px;
      font-size: .9rem;
    }

    .login-actions label {
      color: var(--muted);
      cursor: pointer;
    }

    .login-actions a {
      text-decoration: none;
      color: var(--primary);
      font-weight: 600;
    }

    .login-actions a:hover {
      text-decoration: underline;
    }

    .btn-primary {
      background: var(--primary);
      border: none;
      border-radius: 10px;
      padding: 10px;
      font-weight: 700;
      transition: background-color .15s ease, box-shadow .15s ease, transform .05s ease;
    }

    .btn-primary:hover,
    .btn-primary:focus {
      background: #5b56f0;
      /* roxo um pouco mais escuro */
      box-shadow: 0 12px 28px rgba(111, 106, 248, 0.35);
    }

    .btn-primary:active {
      transform: translateY(1px);
    }
  </style>
</head>

<body>

  <div class="login-card">
    <div class="login-title">Faça seu login</div>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger mb-3"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
      <div class="mb-3">
        <label class="form-label">Usuário</label>
        <input class="form-control" name="code" required>
      </div>

      <div class="mb-2">
        <label class="form-label">Senha</label>
        <div class="password-wrap">
          <input class="form-control" type="password" name="pin" required>
          <i class="bi bi-eye"></i>
        </div>
      </div>

      <div class="login-actions">
        <label>
          <input type="checkbox" class="form-check-input me-1" name="remember" value="1">
          Lembrar de mim
        </label>

        <a href="<?= htmlspecialchars($recoverUrl) ?>" target="_blank">
          Esqueceu sua senha?
        </a>
      </div>

      <button class="btn btn-primary w-100">Entrar</button>
    </form>
  </div>

</body>

</html>