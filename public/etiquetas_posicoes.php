<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/Endereco.php';

$endereco = new Endereco();
$armazens = $endereco->listarArmazens();
$idArmazem = (int) ($_GET['armazem'] ?? ($armazens[0]['ID_ARMAZEM'] ?? 0));
$corredores = $idArmazem ? $endereco->listarCorredores($idArmazem) : [];
$idCorredor = (int) ($_GET['corredor'] ?? 0);
$predios = $idCorredor ? $endereco->listarPredios($idCorredor) : [];
$idPredio = (int) ($_GET['predio'] ?? 0);
$posicoes = $idPredio ? $endereco->listarPosicoes($idPredio) : [];
?>
<h1>Etiquetas de posições (QR Code)</h1>

<div class="card no-print">
  <form method="get">
    <div class="grid grid-3">
      <div>
        <label>Armazém</label>
        <select name="armazem" onchange="this.form.submit()">
          <?php foreach ($armazens as $a): ?>
          <option value="<?= (int)$a['ID_ARMAZEM'] ?>" <?= $idArmazem === (int)$a['ID_ARMAZEM'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($a['NOME']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Rua / Corredor</label>
        <select name="corredor" onchange="this.form.submit()">
          <option value="">-- selecione --</option>
          <?php foreach ($corredores as $c): ?>
          <option value="<?= (int)$c['ID_CORREDOR'] ?>" <?= $idCorredor === (int)$c['ID_CORREDOR'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($c['NOME']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Prédio / Bloco</label>
        <select name="predio" onchange="this.form.submit()">
          <option value="">-- selecione --</option>
          <?php foreach ($predios as $p): ?>
          <option value="<?= (int)$p['ID_PREDIO'] ?>" <?= $idPredio === (int)$p['ID_PREDIO'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($p['NOME']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </form>
  <?php if (!empty($posicoes)): ?>
  <button onclick="window.print()">🖨️ Imprimir etiquetas (<?= count($posicoes) ?>)</button>
  <?php endif; ?>
</div>

<?php if ($idPredio && empty($posicoes)): ?>
  <div class="alert alert-erro">Este prédio ainda não tem posições geradas. Volte em <a href="<?= $bp ?>/enderecos.php">Endereços</a> e clique em "Gerar".</div>
<?php endif; ?>

<?php if (!empty($posicoes)): ?>
<div class="etiquetas-grid">
  <?php foreach ($posicoes as $i => $p): ?>
  <div class="etiqueta">
    <div id="qr-<?= $i ?>" class="qr-canvas"></div>
    <div class="codigo-texto"><?= htmlspecialchars($p['CODIGO']) ?></div>
    <div class="subtitulo">Nível <?= (int)$p['NIVEL'] ?> · Posição <?= (int)$p['POSICAO'] ?></div>
  </div>
  <?php endforeach; ?>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script src="<?= $bp ?>/assets/js/scanner.js"></script>
<script>
  <?php foreach ($posicoes as $i => $p): ?>
  gerarQrEm('qr-<?= $i ?>', <?= json_encode('LOC|' . $p['CODIGO']) ?>, 110);
  <?php endforeach; ?>
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
