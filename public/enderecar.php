<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/Recebimento.php';

$pendentesRecebimento = (new Recebimento())->itensPendentesEnderecamento();
?>

<h1>📲 Endereçar produto</h1>
<p style="color:#556;">Passo 1: aponte a câmera para o QR da <strong>posição</strong>. Passo 2: aponte para o QR do <strong>produto</strong> (ou busque manualmente, ou escolha um item pendente de recebimento abaixo). Passo 3: informe quantidade e lote.</p>

<?php if (!empty($pendentesRecebimento)): ?>
<div class="card">
  <h2>📥 Pendentes de recebimento (já conferidos, faltando guardar)</h2>
  <table>
    <thead><tr><th>Documento</th><th>Fornecedor</th><th>Produto</th><th>Lote</th><th>Pendente</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($pendentesRecebimento as $p): ?>
      <tr>
        <td><?= htmlspecialchars($p['NUMERO_DOCUMENTO']) ?></td>
        <td><?= htmlspecialchars($p['FORNECEDOR']) ?></td>
        <td><?= htmlspecialchars($p['PRODUTO_NOME'] ?? ('#' . $p['ID_PRODUTO'])) ?></td>
        <td><?= htmlspecialchars($p['LOTE'] ?? '-') ?></td>
        <td><?= htmlspecialchars($p['QUANTIDADE_PENDENTE']) ?></td>
        <td>
          <button type="button" class="secundario" onclick='selecionarItemRecebimento(<?= json_encode([
              "ID_ITEM" => (int) $p["ID_ITEM"],
              "ID_PRODUTO" => (int) $p["ID_PRODUTO"],
              "NOME" => $p["PRODUTO_NOME"] ?? ("#" . $p["ID_PRODUTO"]),
              "LOTE" => $p["LOTE"],
              "QUANTIDADE_PENDENTE" => (float) $p["QUANTIDADE_PENDENTE"],
          ]) ?>)'>Selecionar</button>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<div class="grid grid-2">

  <div class="card">
    <h2>1. Posição</h2>
    <div id="bloco-scan-posicao" class="scan-box">
      <video id="video-posicao" autoplay muted playsinline style="display:none"></video>
      <canvas id="canvas-posicao" style="display:none"></canvas>
      <button id="btn-ler-posicao" onclick="lerPosicao()">📷 Ler QR da posição</button>
    </div>
    <div id="resultado-posicao" style="margin-top:10px;"></div>
  </div>

  <div class="card">
    <h2>2. Produto</h2>
    <div id="bloco-scan-produto" class="scan-box">
      <video id="video-produto" autoplay muted playsinline style="display:none"></video>
      <canvas id="canvas-produto" style="display:none"></canvas>
      <button id="btn-ler-produto" onclick="lerProduto()" disabled>📷 Ler QR do produto</button>
    </div>
    <div style="margin-top:10px;">
      <label>...ou busque manualmente</label>
      <input type="text" id="busca-produto" placeholder="Nome, código de barras ou ID" oninput="buscarProdutoManual(this.value)">
      <div id="resultados-busca"></div>
    </div>
    <div id="resultado-produto" style="margin-top:10px;"></div>
  </div>

</div>

<div class="card" id="bloco-quantidade" style="display:none;">
  <h2>3. Quantidade e lote</h2>
  <form id="form-enderecar">
    <div class="grid grid-3">
      <div>
        <label>Quantidade</label>
        <input type="number" step="0.0001" id="quantidade" required>
      </div>
      <div>
        <label>Lote (opcional)</label>
        <input type="text" id="lote">
      </div>
      <div>
        <label>Validade (opcional)</label>
        <input type="date" id="validade">
      </div>
    </div>
    <button type="submit">✅ Confirmar endereçamento</button>
  </form>
  <div id="resultado-final" style="margin-top:12px;"></div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jsQR/1.4.0/jsQR.js"></script>
<script src="<?= $bp ?>/assets/js/scanner.js"></script>
<script>
let posicaoAtual = null;
let produtoAtual = null;
let idItemRecebimentoAtual = null;

function selecionarItemRecebimento(item) {
  selecionarProduto({ ID_PRODUTO: item.ID_PRODUTO, NOME: item.NOME }, item.ID_ITEM);
  if (item.LOTE) document.getElementById('lote').value = item.LOTE;
  document.getElementById('quantidade').value = item.QUANTIDADE_PENDENTE;
}

function lerPosicao() {
  document.getElementById('video-posicao').style.display = 'block';
  iniciarLeitorQr('video-posicao', 'canvas-posicao', function (texto) {
    document.getElementById('video-posicao').style.display = 'none';
    fetch(BASE_PATH + '/api/buscar_posicao.php?codigo=' + encodeURIComponent(texto))
      .then(r => r.json())
      .then(function (resp) {
        if (resp.ok) {
          posicaoAtual = resp.posicao;
          document.getElementById('resultado-posicao').innerHTML =
            '<div class="alert alert-ok">📍 ' + resp.posicao.CODIGO + '<br><small>' +
            resp.posicao.ARMAZEM_NOME + ' / ' + resp.posicao.CORREDOR_NOME + ' / ' + resp.posicao.PREDIO_NOME +
            ' · Nível ' + resp.posicao.NIVEL + ' · Posição ' + resp.posicao.POSICAO + '</small></div>';
          document.getElementById('btn-ler-produto').disabled = false;
          atualizarBlocoQuantidade();
        } else {
          document.getElementById('resultado-posicao').innerHTML =
            '<div class="alert alert-erro">QR não reconhecido como posição válida.</div>';
        }
      });
  });
}

function lerProduto() {
  document.getElementById('video-produto').style.display = 'block';
  iniciarLeitorQr('video-produto', 'canvas-produto', function (texto) {
    document.getElementById('video-produto').style.display = 'none';
    fetch(BASE_PATH + '/api/buscar_produto.php?codigo_qr=' + encodeURIComponent(texto))
      .then(r => r.json())
      .then(function (resp) {
        if (resp.ok) {
          selecionarProduto(resp.produto);
        } else {
          document.getElementById('resultado-produto').innerHTML =
            '<div class="alert alert-erro">QR não reconhecido como produto válido.</div>';
        }
      });
  });
}

let buscaTimeout = null;
function buscarProdutoManual(termo) {
  clearTimeout(buscaTimeout);
  if (termo.length < 2) {
    document.getElementById('resultados-busca').innerHTML = '';
    return;
  }
  buscaTimeout = setTimeout(function () {
    fetch(BASE_PATH + '/api/buscar_produto.php?termo=' + encodeURIComponent(termo))
      .then(r => r.json())
      .then(function (resp) {
        const div = document.getElementById('resultados-busca');
        if (!resp.ok || resp.produtos.length === 0) {
          div.innerHTML = '<p style="font-size:0.85rem;color:#888;">Nenhum resultado.</p>';
          return;
        }
        div.innerHTML = resp.produtos.map(p =>
          `<div class="card" style="padding:8px 10px; cursor:pointer;" onclick='selecionarProduto(${JSON.stringify(p)})'>` +
          `${p.NOME} <small style="color:#888;">#${p.ID_PRODUTO}</small></div>`
        ).join('');
      });
  }, 300);
}

function selecionarProduto(produto, idItemRecebimento) {
  produtoAtual = produto;
  idItemRecebimentoAtual = idItemRecebimento || null;
  document.getElementById('resultado-produto').innerHTML =
    '<div class="alert alert-ok">🏷️ ' + produto.NOME + ' <small>(#' + produto.ID_PRODUTO + ')</small></div>';
  document.getElementById('resultados-busca').innerHTML = '';
  document.getElementById('busca-produto').value = '';
  atualizarBlocoQuantidade();
}

function atualizarBlocoQuantidade() {
  document.getElementById('bloco-quantidade').style.display =
    (posicaoAtual && produtoAtual) ? 'block' : 'none';
}

document.getElementById('form-enderecar').addEventListener('submit', function (e) {
  e.preventDefault();
  const body = new URLSearchParams({
    id_posicao: posicaoAtual.ID_POSICAO,
    id_produto: produtoAtual.ID_PRODUTO,
    quantidade: document.getElementById('quantidade').value,
    lote: document.getElementById('lote').value,
    validade: document.getElementById('validade').value,
    id_item_recebimento: idItemRecebimentoAtual || '',
  });
  fetch(BASE_PATH + '/api/salvar_enderecamento.php', { method: 'POST', body })
    .then(r => r.json())
    .then(function (resp) {
      const div = document.getElementById('resultado-final');
      if (resp.ok) {
        div.innerHTML = '<div class="alert alert-ok">Endereçado com sucesso! Atualizando a página...</div>';
        setTimeout(() => window.location.reload(), 900);
      } else {
        div.innerHTML = '<div class="alert alert-erro">' + resp.erro + '</div>';
      }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
