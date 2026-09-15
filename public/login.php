<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/Auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$erro = '';
$bp = basePath();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $senha = $_POST['senha'] ?? '';
    if ($usuario !== '' && $senha !== '' && Auth::login($usuario, $senha)) {
        header('Location: ' . $bp . '/index.php');
        exit;
    }
    $erro = 'Usuário ou senha inválidos.';
} elseif (!empty($_GET['expirado'])) {
    $erro = 'Sua sessão expirou. Faça login novamente.';
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Entrar - <?= htmlspecialchars(APP_NAME) ?></title>
<link rel="stylesheet" href="<?= $bp ?>/assets/css/style.css">
</head>
<body>
<main class="content" style="max-width:380px; margin-top:80px;">
  <div class="card">
    <h1>📦 <?= htmlspecialchars(APP_NAME) ?></h1>
    <?php if ($erro): ?><div class="alert alert-erro"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
    <form method="post">
      <label for="usuario">Usuário</label>
      <input type="text" id="usuario" name="usuario" required autofocus>
      <label for="senha">Senha</label>
      <input type="password" id="senha" name="senha" required>
      <button type="submit" style="width:100%">Entrar</button>
    </form>
  </div>
</main>
</body>
</html>
