<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/EstoquePosicao.php';
require_once __DIR__ . '/../includes/Endereco.php';
require_once __DIR__ . '/../includes/Produto.php';

$estoquePos = new EstoquePosicao();
$enderecoRepo = new Endereco();
$produtoRepo = new Produto();

$modo = $_GET['modo'] ?? 'posicao';
$resultadosPosicao = null;
$resultadosProduto = null;
$posicaoInfo = null;
$produtoInfo = null;

if ($modo === 'posicao' && !empty($_GET['codigo'])) {
    $posicaoInfo = $enderecoRepo->buscarPorCodigo(trim($_GET['codigo']));
    if ($posicaoInfo) {
        $resultadosPosicao = $estoquePos->conteudoDaPosicao((int) $posicaoInfo['ID_POSICAO']);
    }
} elseif ($modo === 'produto' && !empty($_GET['produto'])) {
    $produtoInfo = $produtoRepo->porId((int) $_GET['produto']);
    if ($produtoInfo) {
        $resultadosProduto = $estoquePos->posicoesDoProduto((int) $produtoInfo['ID_PRODUTO']);
    }
}
?>
<h1>🔍 Consultar estoque</h1>

<div class="card no-print">
  <div style="display:flex; gap:10px; margin-bottom:14px;">
    <a class="btn <?= $modo === 'posicao' ? '' : 'secundario' ?>" href="?modo=posicao">Por posição</a>
    <a class="btn <?= $modo === 'produto' ? '' : 'secundario' ?>" href="?modo=produto">Por produto</a>
  </div>

  <?php if ($modo === 'posicao'): ?>
    <form method="get">
      <input type="hidden" name="modo" value="posicao">
      <label>Código da posição (ou leia o QR e cole aqui)</label>
      <div style="display:flex; gap:10px;">
        <input type="text" name="codigo" value="<?= htmlspecialchars($_GET['codigo'] ?? '') ?>" placeholder="Ex: AR01-CR01-PR01-N01-P05" style="flex:1;">
        <button type="submit">Buscar</button>
      </div>
    </form>
    <video id="video-consulta" autoplay muted playsinline style="display:none; margin-top:10px;"></video>
    <canvas id="canvas-consulta" style="display:none"></canvas>
    <button type="button" onclick="lerParaConsulta()" style="margin-top:10px;">📷 Ler QR da posição</button>
  <?php else: ?>
    <form method="get">
      <input type="hidden" name="modo" value="produto">
      <label>Buscar produto</label>
      <div style="display:flex; gap:10px;">
        <input type="text" id="busca-produto-consulta" placeholder="Nome, código de barras ou ID" style="flex:1;">
        <input type="hidden" name="produto" id="produto-id-consulta" value="<?= htmlspecialchars($_GET['produto'] ?? '') ?>">
      </div>
      <div id="resultados-busca-consulta"></div>
    </form>
  <?php endif; ?>
</div>

<?php if ($modo === 'posicao' && isset($_GET['codigo'])): ?>
  <?php if (!$posicaoInfo): ?>
    <div class="alert alert-erro">Posição não encontrada para o código informado.</div>
  <?php else: ?>
    <div class="card">
      <h2>📍 <?= htmlspecialchars($posicaoInfo['CODIGO']) ?></h2>
      <p style="color:#667;">
        <?= htmlspecialchars($posicaoInfo['ARMAZEM_NOME']) ?> /
        <?= htmlspecialchars($posicaoInfo['CORREDOR_NOME']) ?> /
        <?= htmlspecialchars($posicaoInfo['PREDIO_NOME']) ?> ·
        Nível <?= (int)$posicaoInfo['NIVEL'] ?> · Posição <?= (int)$posicaoInfo['POSICAO'] ?>
      </p>
      <table>
        <thead><tr><th>Produto</th><th>Lote</th><th>Quantidade</th><th>Validade</th></tr></thead>
        <tbody>
          <?php foreach ($resultadosPosicao as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['PRODUTO_NOME']) ?></td>
            <td><?= htmlspecialchars($r['LOTE'] ?? '-') ?></td>
            <td><?= htmlspecialchars($r['QUANTIDADE']) ?></td>
            <td><?= htmlspecialchars($r['DATA_VALIDADE'] ?? '-') ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($resultadosPosicao)): ?>
          <tr><td colspan="4">Esta posição está vazia.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
<?php endif; ?>

<?php if ($modo === 'produto' && $produtoInfo): ?>
  <div class="card">
    <h2>🏷️ <?= htmlspecialchars($produtoInfo['NOME']) ?></h2>
    <table>
      <thead><tr><th>Armazém</th><th>Rua</th><th>Prédio</th><th>Endereço</th><th>Lote</th><th>Quantidade</th><th>Validade</th></tr></thead>
      <tbody>
        <?php foreach ($resultadosProduto as $r): ?>
        <tr>
          <td><?= htmlspecialchars($r['ARMAZEM_NOME']) ?></td>
          <td><?= htmlspecialchars($r['CORREDOR_NOME']) ?></td>
          <td><?= htmlspecialchars($r['PREDIO_NOME']) ?></td>
          <td><?= htmlspecialchars($r['CODIGO']) ?></td>
          <td><?= htmlspecialchars($r['LOTE'] ?? '-') ?></td>
          <td><?= htmlspecialchars($r['QUANTIDADE']) ?></td>
          <td><?= htmlspecialchars($r['DATA_VALIDADE'] ?? '-') ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($resultadosProduto)): ?>
        <tr><td colspan="7">Este produto não está endereçado em nenhuma posição.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jsQR/1.4.0/jsQR.js"></script>
<script src="<?= $bp ?>/assets/js/scanner.js"></script>
<script>
function lerParaConsulta() {
  document.getElementById('video-consulta').style.display = 'block';
  iniciarLeitorQr('video-consulta', 'canvas-consulta', function (texto) {
    const codigo = texto.startsWith('LOC|') ? texto.substring(4) : texto;
    window.location.href = '?modo=posicao&codigo=' + encodeURIComponent(codigo);
  });
}

const buscaEl = document.getElementById('busca-produto-consulta');
if (buscaEl) {
  let t = null;
  buscaEl.addEventListener('input', function () {
    clearTimeout(t);
    const termo = this.value;
    if (termo.length < 2) { document.getElementById('resultados-busca-consulta').innerHTML = ''; return; }
    t = setTimeout(function () {
      fetch(BASE_PATH + '/api/buscar_produto.php?termo=' + encodeURIComponent(termo))
        .then(r => r.json())
        .then(function (resp) {
          const div = document.getElementById('resultados-busca-consulta');
          if (!resp.ok || resp.produtos.length === 0) {
            div.innerHTML = '<p style="font-size:0.85rem;color:#888;">Nenhum resultado.</p>';
            return;
          }
          div.innerHTML = resp.produtos.map(p =>
            `<div class="card" style="padding:8px 10px; cursor:pointer;" onclick="window.location.href='?modo=produto&produto=${p.ID_PRODUTO}'">` +
            `${p.NOME} <small style="color:#888;">#${p.ID_PRODUTO}</small></div>`
          ).join('');
        });
    }, 300);
  });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
