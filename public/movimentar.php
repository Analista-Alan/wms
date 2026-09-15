<?php require_once __DIR__ . '/../includes/header.php'; ?>

<h1>🔄 Movimentar / Transferir estoque</h1>

<div class="card no-print">
  <label>Tipo de operação</label>
  <select id="tipo-operacao" onchange="alternarTipo()">
    <option value="T">Transferência entre posições</option>
    <option value="S">Saída (expedição / baixa)</option>
  </select>
</div>

<div class="grid grid-2">
  <div class="card">
    <h2>Posição de origem</h2>
    <video id="video-origem" autoplay muted playsinline style="display:none"></video>
    <canvas id="canvas-origem" style="display:none"></canvas>
    <button onclick="lerOrigem()">📷 Ler QR da posição de origem</button>
    <div id="resultado-origem" style="margin-top:10px;"></div>
  </div>

  <div class="card" id="bloco-destino">
    <h2>Posição de destino</h2>
    <video id="video-destino" autoplay muted playsinline style="display:none"></video>
    <canvas id="canvas-destino" style="display:none"></canvas>
    <button onclick="lerDestino()">📷 Ler QR da posição de destino</button>
    <div id="resultado-destino" style="margin-top:10px;"></div>
  </div>
</div>

<div class="card">
  <h2>Produto e quantidade</h2>
  <video id="video-produto" autoplay muted playsinline style="display:none"></video>
  <canvas id="canvas-produto" style="display:none"></canvas>
  <button onclick="lerProduto()">📷 Ler QR do produto</button>
  <div id="resultado-produto" style="margin-top:10px;"></div>

  <label style="margin-top:10px;">...ou busque manualmente</label>
  <input type="text" id="busca-produto" placeholder="Nome, código de barras ou ID" oninput="buscarProdutoManual(this.value)">
  <div id="resultados-busca"></div>

  <form id="form-movimentar" style="margin-top:10px;">
    <div class="grid grid-3">
      <div>
        <label>Quantidade</label>
        <input type="number" step="0.0001" id="quantidade" required>
      </div>
      <div>
        <label>Lote (se aplicável)</label>
        <input type="text" id="lote">
      </div>
      <div>
        <label>Observação</label>
        <input type="text" id="observacao">
      </div>
    </div>
    <button type="submit">✅ Confirmar movimentação</button>
  </form>
  <div id="resultado-final" style="margin-top:12px;"></div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jsQR/1.4.0/jsQR.js"></script>
<script src="<?= $bp ?>/assets/js/scanner.js"></script>
<script>
let origemAtual = null;
let destinoAtual = null;
let produtoAtual = null;

function alternarTipo() {
  const tipo = document.getElementById('tipo-operacao').value;
  document.getElementById('bloco-destino').style.display = tipo === 'S' ? 'none' : 'block';
}

function lerPosicaoGenerico(videoId, canvasId, callback) {
  document.getElementById(videoId).style.display = 'block';
  iniciarLeitorQr(videoId, canvasId, function (texto) {
    document.getElementById(videoId).style.display = 'none';
    fetch(BASE_PATH + '/api/buscar_posicao.php?codigo=' + encodeURIComponent(texto))
      .then(r => r.json())
      .then(function (resp) {
        callback(resp);
      });
  });
}

function lerOrigem() {
  lerPosicaoGenerico('video-origem', 'canvas-origem', function (resp) {
    if (resp.ok) {
      origemAtual = resp.posicao;
      document.getElementById('resultado-origem').innerHTML =
        '<div class="alert alert-ok">📍 ' + resp.posicao.CODIGO + '</div>';
    } else {
      document.getElementById('resultado-origem').innerHTML =
        '<div class="alert alert-erro">Posição não encontrada.</div>';
    }
  });
}

function lerDestino() {
  lerPosicaoGenerico('video-destino', 'canvas-destino', function (resp) {
    if (resp.ok) {
      destinoAtual = resp.posicao;
      document.getElementById('resultado-destino').innerHTML =
        '<div class="alert alert-ok">📍 ' + resp.posicao.CODIGO + '</div>';
    } else {
      document.getElementById('resultado-destino').innerHTML =
        '<div class="alert alert-erro">Posição não encontrada.</div>';
    }
  });
}

function lerProduto() {
  document.getElementById('video-produto').style.display = 'block';
  iniciarLeitorQr('video-produto', 'canvas-produto', function (texto) {
    document.getElementById('video-produto').style.display = 'none';
    fetch(BASE_PATH + '/api/buscar_produto.php?codigo_qr=' + encodeURIComponent(texto))
      .then(r => r.json())
      .then(function (resp) {
        if (resp.ok) selecionarProduto(resp.produto);
        else document.getElementById('resultado-produto').innerHTML =
          '<div class="alert alert-erro">Produto não encontrado.</div>';
      });
  });
}

let buscaTimeout = null;
function buscarProdutoManual(termo) {
  clearTimeout(buscaTimeout);
  if (termo.length < 2) { document.getElementById('resultados-busca').innerHTML = ''; return; }
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

function selecionarProduto(produto) {
  produtoAtual = produto;
  document.getElementById('resultado-produto').innerHTML =
    '<div class="alert alert-ok">🏷️ ' + produto.NOME + ' (#' + produto.ID_PRODUTO + ')</div>';
  document.getElementById('resultados-busca').innerHTML = '';
  document.getElementById('busca-produto').value = '';
}

document.getElementById('form-movimentar').addEventListener('submit', function (e) {
  e.preventDefault();
  const tipo = document.getElementById('tipo-operacao').value;

  if (!origemAtual || !produtoAtual) {
    document.getElementById('resultado-final').innerHTML =
      '<div class="alert alert-erro">Leia a posição de origem e o produto antes de confirmar.</div>';
    return;
  }
  if (tipo === 'T' && !destinoAtual) {
    document.getElementById('resultado-final').innerHTML =
      '<div class="alert alert-erro">Leia a posição de destino antes de confirmar.</div>';
    return;
  }

  const body = new URLSearchParams({
    tipo: tipo,
    id_posicao_origem: origemAtual.ID_POSICAO,
    id_posicao_destino: destinoAtual ? destinoAtual.ID_POSICAO : '',
    id_produto: produtoAtual.ID_PRODUTO,
    quantidade: document.getElementById('quantidade').value,
    lote: document.getElementById('lote').value,
    observacao: document.getElementById('observacao').value,
  });

  fetch(BASE_PATH + '/api/salvar_movimentacao.php', { method: 'POST', body })
    .then(r => r.json())
    .then(function (resp) {
      const div = document.getElementById('resultado-final');
      if (resp.ok) {
        div.innerHTML = '<div class="alert alert-ok">Movimentação registrada com sucesso!</div>';
        origemAtual = null; destinoAtual = null; produtoAtual = null;
        document.getElementById('resultado-origem').innerHTML = '';
        document.getElementById('resultado-destino').innerHTML = '';
        document.getElementById('resultado-produto').innerHTML = '';
        document.getElementById('form-movimentar').reset();
      } else {
        div.innerHTML = '<div class="alert alert-erro">' + resp.erro + '</div>';
      }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
