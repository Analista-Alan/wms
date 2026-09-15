<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
Auth::checarSessao();
$paginaAtual = basename($_SERVER['SCRIPT_NAME']);
$bp = basePath();

/**
 * Estrutura do menu lateral: cada grupo tem um rótulo, um ícone e uma lista
 * de páginas (rótulo => arquivo). Um grupo "solto" (sem 'itens') vira link
 * direto, sem submenu — é o caso do Início.
 */
$menu = [
    ['label' => 'Início', 'icon' => '🏠', 'file' => 'index.php'],
    [
        'label' => 'Cadastros', 'icon' => '🏭',
        'itens' => [
            'Endereços' => 'enderecos.php',
            'Etiquetas de Posições' => 'etiquetas_posicoes.php',
            'Etiquetas de Produtos' => 'etiquetas_produtos.php',
        ],
    ],
    [
        'label' => 'Operação', 'icon' => '📦',
        'itens' => [
            'Recebimento' => 'recebimento.php',
            'Endereçar' => 'enderecar.php',
            'Separação' => 'separacao.php',
            'Expedição' => 'expedicao.php',
            'Movimentar' => 'movimentar.php',
            'Inventário' => 'inventario.php',
        ],
    ],
    [
        'label' => 'Consultas', 'icon' => '🔍',
        'itens' => [
            'Consultar Estoque' => 'consulta_estoque.php',
        ],
    ],
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars(APP_NAME) ?></title>
<link rel="stylesheet" href="<?= $bp ?>/assets/css/style.css">
</head>
<body>
<script>const BASE_PATH = <?= json_encode($bp) ?>;</script>
<div class="app-shell">

  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">📦 <?= htmlspecialchars(APP_NAME) ?></div>
    <nav class="sidebar-nav">
      <?php foreach ($menu as $grupo): ?>
        <?php if (isset($grupo['file'])): ?>
          <a href="<?= $bp ?>/<?= $grupo['file'] ?>" class="sidebar-link <?= $paginaAtual === $grupo['file'] ? 'active' : '' ?>">
            <span class="sidebar-icon"><?= $grupo['icon'] ?></span> <?= htmlspecialchars($grupo['label']) ?>
          </a>
        <?php else: ?>
          <?php $grupoAberto = in_array($paginaAtual, $grupo['itens'], true); ?>
          <details class="sidebar-group" <?= $grupoAberto ? 'open' : '' ?>>
            <summary class="sidebar-group-label">
              <span class="sidebar-icon"><?= $grupo['icon'] ?></span> <?= htmlspecialchars($grupo['label']) ?>
              <span class="sidebar-caret">›</span>
            </summary>
            <div class="sidebar-submenu">
              <?php foreach ($grupo['itens'] as $rotulo => $arquivo): ?>
                <a href="<?= $bp ?>/<?= $arquivo ?>" class="sidebar-sublink <?= $paginaAtual === $arquivo ? 'active' : '' ?>">
                  <?= htmlspecialchars($rotulo) ?>
                </a>
              <?php endforeach; ?>
            </div>
          </details>
        <?php endif; ?>
      <?php endforeach; ?>
    </nav>
  </aside>

  <div class="main-wrap">
    <header class="topbar">
      <button type="button" class="sidebar-toggle" onclick="document.getElementById('sidebar').classList.toggle('aberto')" aria-label="Abrir menu">☰</button>
      <div class="topbar-title"><?= htmlspecialchars(APP_NAME) ?></div>
      <div class="topbar-user">
        <?= htmlspecialchars(Auth::usuarioNome()) ?>
        &middot; <a href="<?= $bp ?>/logout.php">Sair</a>
      </div>
    </header>
    <main class="content">
