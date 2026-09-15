<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/Separacao.php';

$separacao = new Separacao();
$mensagem = '';
$erro = '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $acao = $_POST['acao'] ?? '';
        if ($acao === 'importar_pedido') {
            $id = $separacao->importarDePedidoVenda((int) $_POST['id_pedido_venda'], Auth::usuarioId());
            $mensagem = 'Pedido importado. Separação #' . $id . ' criada.';
        } elseif ($acao === 'importar_nota') {
            $id = $separacao->importarDeNotaFiscal((int) $_POST['id_fiscal_nota'], Auth::usuarioId());
            $mensagem = 'Nota fiscal importada. Separação #' . $id . ' criada.';
        } elseif ($acao === 'concluir') {
            $separacao->concluir((int) $_POST['id_separacao'], Auth::usuarioId());
            $mensagem = 'Separação concluída! Já aparece na tela de Expedição.';
        }
    }
} catch (Throwable $e) {
    $erro = 'Erro: ' . $e->getMessage();
}

$termoBusca = trim($_GET['busca'] ?? '');
$pedidosEncontrados = $termoBusca !== '' ? $separacao->buscarPedidosVenda($termoBusca) : [];
$notasEncontradas = $termoBusca !== '' ? $separacao->buscarNotasFiscais($termoBusca) : [];

$idSeparacaoAtual = (int) ($_GET['separacao'] ?? 0);
$abertas = $separacao->listar('AGUARDANDO');
$emSeparacao = $separacao->listar('SEPARANDO');
$itensAtual = $idSeparacaoAtual ? $separacao->itens($idSeparacaoAtual) : null;
$cabecalhoAtual = $idSeparacaoAtual ? $separacao->detalhe($idSeparacaoAtual) : null;
?>
<h1>📋 Separação de Mercadorias</h1>

<?php if ($mensagem): ?><div class="alert alert-ok"><?= htmlspecialchars($mensagem) ?></div><?php endif; ?>
<?php if ($erro): ?><div class="alert alert-erro"><?= htmlspecialchars($erro) ?></div><?php endif; ?>

<div class="card">
  <h2>1. Importar pedido de venda ou nota fiscal</h2>
  <form method="get">
    <label>Buscar por número de documento ou cliente</label>
    <div style="display:flex; gap:10px;">
      <input type="text" name="busca" value="<?= htmlspecialchars($termoBusca) ?>" style="flex:1;" placeholder="Ex: 1050 ou nome do cliente">
      <button type="submit">Buscar</button>
    </div>
  </form>

  <?php if ($termoBusca !== ''): ?>
  <h3 style="margin-top:16px;">Pedidos de venda</h3>
  <table>
    <thead><tr><th>Nº</th><th>Cliente</th><th>Data</th><th>Valor</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($pedidosEncontrados as $p): ?>
      <tr>
        <td><?= htmlspecialchars($p['NUMERO_DOCUMENTO']) ?></td>
        <td><?= htmlspecialchars($p['ID_CLIENTE']) ?></td>
        <td><?= htmlspecialchars($p['DATA_EMISSAO']) ?></td>
        <td><?= htmlspecialchars($p['VALOR']) ?></td>
        <td>
          <form method="post" style="margin:0;">
            <input type="hidden" name="acao" value="importar_pedido">
            <input type="hidden" name="id_pedido_venda" value="<?= (int)$p['ID_PEDIDO_VENDA'] ?>">
            <button type="submit" class="secundario">Importar</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($pedidosEncontrados)): ?><tr><td colspan="5">Nenhum pedido encontrado.</td></tr><?php endif; ?>
    </tbody>
  </table>

  <h3 style="margin-top:16px;">Notas fiscais</h3>
  <table>
    <thead><tr><th>Número</th><th>Destinatário</th><th>Data</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($notasEncontradas as $n): ?>
      <tr>
        <td><?= htmlspecialchars($n['NUMERO']) ?></td>
        <td><?= htmlspecialchars($n['NOME']) ?></td>
        <td><?= htmlspecialchars($n['DATA_EMISSAO']) ?></td>
        <td>
          <form method="post" style="margin:0;">
            <input type="hidden" name="acao" value="importar_nota">
            <input type="hidden" name="id_fiscal_nota" value="<?= (int)$n['ID_FISCAL_NOTA'] ?>">
            <button type="submit" class="secundario">Importar</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($notasEncontradas)): ?><tr><td colspan="4">Nenhuma nota encontrada.</td></tr><?php endif; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<div class="card">
  <h2>2. Separações abertas</h2>
  <table>
    <thead><tr><th>Origem</th><th>Documento</th><th>Cliente</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php foreach (array_merge($abertas, $emSeparacao) as $s): ?>
      <tr>
        <td><?= $s['ORIGEM'] === 'P' ? 'Pedido' : 'Nota Fiscal' ?></td>
        <td><?= htmlspecialchars($s['NUMERO_DOCUMENTO']) ?></td>
        <td><?= htmlspecialchars($s['CLIENTE']) ?></td>
        <td><?= htmlspecialchars($s['STATUS']) ?></td>
        <td><a class="btn secundario" href="?separacao=<?= (int)$s['ID_SEPARACAO'] ?>">Separar</a></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($abertas) && empty($emSeparacao)): ?>
      <tr><td colspan="5">Nenhuma separação aberta.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if ($idSeparacaoAtual && $cabecalhoAtual): ?>
<div class="card">
  <h2>Separando: <?= htmlspecialchars($cabecalhoAtual['NUMERO_DOCUMENTO']) ?> — <?= htmlspecialchars($cabecalhoAtual['CLIENTE']) ?></h2>

  <div id="lista-itens">
    <?php foreach ($itensAtual as $item): ?>
    <div class="card" style="background:#f7faf8;">
      <strong><?= htmlspecialchars($item['PRODUTO_NOME'] ?? ('#' . $item['ID_PRODUTO'])) ?></strong>
      — Pedido: <?= htmlspecialchars($item['QUANTIDADE_PEDIDO']) ?>,
      Separado: <?= htmlspecialchars($item['QUANTIDADE_SEPARADA']) ?>,
      Pendente: <strong><?= htmlspecialchars($item['QUANTIDADE_PENDENTE']) ?></strong>
      <?php if ((float)$item['QUANTIDADE_PENDENTE'] > 0): ?>
      <button type="button" class="secundario" style="margin-left:10px;"
        onclick="abrirRetirada(<?= (int)$item['ID_ITEM'] ?>, <?= (int)$item['ID_PRODUTO'] ?>, <?= json_encode($item['PRODUTO_NOME'] ?? '') ?>, <?= (float)$item['QUANTIDADE_PENDENTE'] ?>)">
        Retirar
      </button>
      <?php else: ?>
      <span class="badge badge-ativo" style="margin-left:10px;">Completo</span>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="card" id="bloco-retirada" style="display:none;">
    <h3 id="titulo-retirada">Retirar produto</h3>
    <video id="video-posicao-sep" autoplay muted playsinline style="display:none"></video>
    <canvas id="canvas-posicao-sep" style="display:none"></canvas>
    <button type="button" onclick="lerPosicaoSeparacao()">📷 Ler QR da posição de onde vai retirar</button>
    <div id="resultado-posicao-sep" style="margin-top:10px;"></div>

    <div style="margin-top:10px;">
      <label>Quantidade a retirar</label>
      <input type="number" step="0.0001" id="quantidade-retirada">
      <label>Lote (se aplicável)</label>
      <input type="text" id="lote-retirada">
      <button type="button" onclick="confirmarRetirada()">✅ Confirmar retirada</button>
    </div>
    <div id="resultado-retirada" style="margin-top:10px;"></div>
  </div>

  <form method="post" style="margin-top:14px;">
    <input type="hidden" name="acao" value="concluir">
    <input type="hidden" name="id_separacao" value="<?= $idSeparacaoAtual ?>">
    <button type="submit">✅ Concluir separação</button>
  </form>
</div>
<?php endif; ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jsQR/1.4.0/jsQR.js"></script>
<script src="<?= $bp ?>/assets/js/scanner.js"></script>
<script>
let itemAtualId = null;
let posicaoSepAtual = null;

function abrirRetirada(idItem, idProduto, nomeProduto, pendente) {
  itemAtualId = idItem;
  posicaoSepAtual = null;
  document.getElementById('titulo-retirada').textContent = 'Retirar: ' + nomeProduto + ' (pendente: ' + pendente + ')';
  document.getElementById('quantidade-retirada').value = pendente;
  document.getElementById('resultado-posicao-sep').innerHTML = '';
  document.getElementById('resultado-retirada').innerHTML = '';
  document.getElementById('bloco-retirada').style.display = 'block';
  document.getElementById('bloco-retirada').scrollIntoView({ behavior: 'smooth' });
}

function lerPosicaoSeparacao() {
  document.getElementById('video-posicao-sep').style.display = 'block';
  iniciarLeitorQr('video-posicao-sep', 'canvas-posicao-sep', function (texto) {
    document.getElementById('video-posicao-sep').style.display = 'none';
    fetch(BASE_PATH + '/api/buscar_posicao.php?codigo=' + encodeURIComponent(texto))
      .then(r => r.json())
      .then(function (resp) {
        if (resp.ok) {
          posicaoSepAtual = resp.posicao;
          document.getElementById('resultado-posicao-sep').innerHTML =
            '<div class="alert alert-ok">📍 ' + resp.posicao.CODIGO + '</div>';
        } else {
          document.getElementById('resultado-posicao-sep').innerHTML =
            '<div class="alert alert-erro">Posição não encontrada.</div>';
        }
      });
  });
}

function confirmarRetirada() {
  if (!posicaoSepAtual) {
    document.getElementById('resultado-retirada').innerHTML =
      '<div class="alert alert-erro">Leia a posição antes de confirmar.</div>';
    return;
  }
  const body = new URLSearchParams({
    id_item: itemAtualId,
    id_posicao: posicaoSepAtual.ID_POSICAO,
    quantidade: document.getElementById('quantidade-retirada').value,
    lote: document.getElementById('lote-retirada').value,
  });
  fetch(BASE_PATH + '/api/salvar_retirada_separacao.php', { method: 'POST', body })
    .then(r => r.json())
    .then(function (resp) {
      if (resp.ok) {
        document.getElementById('resultado-retirada').innerHTML =
          '<div class="alert alert-ok">Retirada registrada! Atualizando...</div>';
        setTimeout(() => window.location.reload(), 900);
      } else {
        document.getElementById('resultado-retirada').innerHTML =
          '<div class="alert alert-erro">' + resp.erro + '</div>';
      }
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
