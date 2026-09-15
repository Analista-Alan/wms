<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/Produto.php';

$produtoRepo = new Produto();
$termo = trim($_GET['q'] ?? '');
$produtos = $termo !== '' ? $produtoRepo->buscar($termo, 60) : [];
?>
<h1>Etiquetas de produtos (QR Code)</h1>

<div class="card no-print">
  <form method="get">
    <label>Buscar produto por nome, código de barras ou ID</label>
    <div style="display:flex; gap:10px;">
      <input type="text" name="q" value="<?= htmlspecialchars($termo) ?>" placeholder="Ex: Ureia, ou 7891234..." style="flex:1;">
      <button type="submit">Buscar</button>
    </div>
  </form>
  <?php if (!empty($produtos)): ?>
  <button onclick="window.print()">🖨️ Imprimir etiquetas (<?= count($produtos) ?>)</button>
  <?php endif; ?>
</div>

<?php if ($termo !== '' && empty($produtos)): ?>
  <div class="alert alert-erro">Nenhum produto encontrado para "<?= htmlspecialchars($termo) ?>".</div>
<?php endif; ?>

<?php if (!empty($produtos)): ?>
<div class="etiquetas-grid">
  <?php foreach ($produtos as $i => $p): ?>
  <div class="etiqueta">
    <div id="qr-<?= $i ?>" class="qr-canvas"></div>
    <div class="codigo-texto"><?= htmlspecialchars($p['NOME']) ?></div>
    <div class="subtitulo">Cód. interno: <?= (int)$p['ID_PRODUTO'] ?><?= $p['CODIGO_BARRAS'] ? ' · EAN: ' . htmlspecialchars($p['CODIGO_BARRAS']) : '' ?></div>
  </div>
  <?php endforeach; ?>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script src="<?= $bp ?>/assets/js/scanner.js"></script>
<script>
  <?php foreach ($produtos as $i => $p): ?>
  gerarQrEm('qr-<?= $i ?>', <?= json_encode('PRD|' . (int)$p['ID_PRODUTO']) ?>, 110);
  <?php endforeach; ?>
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
