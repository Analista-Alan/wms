<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/Inventario.php';
require_once __DIR__ . '/../includes/Endereco.php';

$inventario = new Inventario();
$enderecoRepo = new Endereco();
$mensagem = '';
$erro = '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $acao = $_POST['acao'] ?? '';
        if ($acao === 'abrir') {
            $id = $inventario->abrir(
                $_POST['tipo'],
                $_POST['modo'],
                !empty($_POST['id_armazem']) ? (int) $_POST['id_armazem'] : null,
                !empty($_POST['id_predio']) ? (int) $_POST['id_predio'] : null,
                trim($_POST['descricao'] ?? ''),
                Auth::usuarioId()
            );
            $mensagem = 'Inventário #' . $id . ' aberto com sucesso.';
        } elseif ($acao === 'aplicar_todos') {
            $qtd = $inventario->aplicarTodosAjustes((int) $_POST['id_inventario'], Auth::usuarioId());
            $mensagem = "$qtd ajuste(s) aplicado(s) ao estoque.";
        } elseif ($acao === 'finalizar') {
            $inventario->finalizar((int) $_POST['id_inventario'], Auth::usuarioId());
            $mensagem = 'Inventário finalizado.';
        }
    }
} catch (Throwable $e) {
    $erro = 'Erro: ' . $e->getMessage();
}

$armazens = $enderecoRepo->listarArmazens();
$abertos = $inventario->listar('ABERTO');
$emContagem = $inventario->listar('EM_CONTAGEM');
$idInventarioAtual = (int) ($_GET['inventario'] ?? 0);
$detalheAtual = $idInventarioAtual ? $inventario->detalhe($idInventarioAtual) : null;
$pendentesAtual = $idInventarioAtual ? $inventario->pendentes($idInventarioAtual) : [];
$divergenciasAtual = $idInventarioAtual ? $inventario->divergencias($idInventarioAtual) : [];
?>
<h1>🔢 Inventário</h1>

<?php if ($mensagem): ?><div class="alert alert-ok"><?= htmlspecialchars($mensagem) ?></div><?php endif; ?>
<?php if ($erro): ?><div class="alert alert-erro"><?= htmlspecialchars($erro) ?></div><?php endif; ?>

<div class="card">
  <h2>Abrir novo inventário</h2>
  <form method="post">
    <input type="hidden" name="acao" value="abrir">
    <div class="grid grid-3">
      <div>
        <label>Tipo</label>
        <select name="tipo" id="tipo-inventario" onchange="atualizarCamposTipo()">
          <option value="G">Geral (todo o armazém)</option>
          <option value="C">Cíclico (um prédio específico)</option>
        </select>
      </div>
      <div>
        <label>Modo de contagem</label>
        <select name="modo">
          <option value="C">Cego (não mostra saldo do sistema)</option>
          <option value="V">Visível (mostra saldo do sistema)</option>
        </select>
      </div>
      <div>
        <label>Descrição</label>
        <input type="text" name="descricao" placeholder="Ex: Inventário mensal agosto/2026">
      </div>
    </div>
    <div class="grid grid-2">
      <div>
        <label>Armazém</label>
        <select name="id_armazem" id="select-armazem" onchange="carregarPredios()">
          <option value="">-- todos --</option>
          <?php foreach ($armazens as $a): ?>
          <option value="<?= (int)$a['ID_ARMAZEM'] ?>"><?= htmlspecialchars($a['NOME']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div id="bloco-predio" style="display:none;">
        <label>Prédio (obrigatório se o tipo for Cíclico)</label>
        <select name="id_predio" id="select-predio">
          <option value="">-- selecione o armazém primeiro --</option>
        </select>
      </div>
    </div>
    <button type="submit">📸 Abrir inventário (tira a foto do estoque atual)</button>
  </form>
</div>

<div class="card">
  <h2>Inventários em andamento</h2>
  <table>
    <thead><tr><th>Descrição</th><th>Tipo</th><th>Modo</th><th>Status</th><th>Aberto em</th><th></th></tr></thead>
    <tbody>
      <?php foreach (array_merge($abertos, $emContagem) as $inv): ?>
      <tr>
        <td><?= htmlspecialchars($inv['DESCRICAO']) ?></td>
        <td><?= $inv['TIPO'] === 'G' ? 'Geral' : 'Cíclico' ?></td>
        <td><?= $inv['MODO'] === 'C' ? 'Cego' : 'Visível' ?></td>
        <td><?= htmlspecialchars($inv['STATUS']) ?></td>
        <td><?= htmlspecialchars($inv['DATA_ABERTURA']) ?></td>
        <td><a class="btn secundario" href="?inventario=<?= (int)$inv['ID_INVENTARIO'] ?>">Contar</a></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($abertos) && empty($emContagem)): ?>
      <tr><td colspan="6">Nenhum inventário em andamento.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if ($idInventarioAtual && $detalheAtual): ?>
<div class="card">
  <h2>Contando: <?= htmlspecialchars($detalheAtual['DESCRICAO']) ?>
    <span class="badge <?= $detalheAtual['MODO'] === 'C' ? 'badge-bloqueado' : 'badge-ativo' ?>">
      <?= $detalheAtual['MODO'] === 'C' ? 'Contagem cega' : 'Saldo visível' ?>
    </span>
  </h2>

  <video id="video-posicao-inv" autoplay muted playsinline style="display:none"></video>
  <canvas id="canvas-posicao-inv" style="display:none"></canvas>
  <button type="button" onclick="lerPosicaoInventario()">📷 Ler QR da posição a contar</button>
  <div id="resultado-posicao-inv" style="margin-top:10px;"></div>

  <div id="itens-posicao-inv" style="margin-top:14px;"></div>

  <hr>
  <h3>Itens ainda não contados (<?= count($pendentesAtual) ?>)</h3>
  <table>
    <thead><tr><th>Posição</th><th>Produto</th><th>Lote</th></tr></thead>
    <tbody>
      <?php foreach (array_slice($pendentesAtual, 0, 30) as $p): ?>
      <tr>
        <td><?= htmlspecialchars($p['CODIGO']) ?></td>
        <td><?= htmlspecialchars($p['PRODUTO_NOME'] ?? ('#' . $p['ID_PRODUTO'])) ?></td>
        <td><?= htmlspecialchars($p['LOTE'] ?? '-') ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($pendentesAtual)): ?>
      <tr><td colspan="3">Tudo contado! Pode ver as divergências abaixo e finalizar.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <h3 style="margin-top:16px;">Divergências encontradas (<?= count($divergenciasAtual) ?>)</h3>
  <table>
    <thead><tr><th>Posição</th><th>Produto</th><th>Sistema</th><th>Contado</th><th>Diferença</th><th>Ajustado?</th></tr></thead>
    <tbody>
      <?php foreach ($divergenciasAtual as $d): ?>
      <tr>
        <td><?= htmlspecialchars($d['CODIGO']) ?></td>
        <td><?= htmlspecialchars($d['PRODUTO_NOME'] ?? ('#' . $d['ID_PRODUTO'])) ?></td>
        <td><?= htmlspecialchars($d['QUANTIDADE_SISTEMA']) ?></td>
        <td><?= htmlspecialchars($d['QUANTIDADE_CONTADA']) ?></td>
        <td><?= htmlspecialchars($d['DIVERGENCIA']) ?></td>
        <td><?= $d['AJUSTADO'] === 'Y' ? '✅' : '—' ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($divergenciasAtual)): ?>
      <tr><td colspan="6">Nenhuma divergência até agora.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <div style="display:flex; gap:10px; margin-top:14px;">
    <form method="post" style="margin:0;">
      <input type="hidden" name="acao" value="aplicar_todos">
      <input type="hidden" name="id_inventario" value="<?= $idInventarioAtual ?>">
      <button type="submit" class="secundario">Aplicar todos os ajustes ao estoque</button>
    </form>
    <form method="post" style="margin:0;">
      <input type="hidden" name="acao" value="finalizar">
      <input type="hidden" name="id_inventario" value="<?= $idInventarioAtual ?>">
      <button type="submit" <?= !empty($pendentesAtual) ? 'disabled title="Ainda há itens não contados"' : '' ?>>
        ✅ Finalizar inventário
      </button>
    </form>
  </div>
</div>
<?php endif; ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jsQR/1.4.0/jsQR.js"></script>
<script src="<?= $bp ?>/assets/js/scanner.js"></script>
<script>
const ID_INVENTARIO_ATUAL = <?= (int) $idInventarioAtual ?>;
let posicaoInvAtual = null;

function atualizarCamposTipo() {
  const tipo = document.getElementById('tipo-inventario').value;
  document.getElementById('bloco-predio').style.display = tipo === 'C' ? 'block' : 'none';
}

function carregarPredios() {
  const idArmazem = document.getElementById('select-armazem').value;
  const selectPredio = document.getElementById('select-predio');
  if (!idArmazem) {
    selectPredio.innerHTML = '<option value="">-- selecione o armazém primeiro --</option>';
    return;
  }
  fetch(BASE_PATH + '/api/listar_predios.php?id_armazem=' + encodeURIComponent(idArmazem))
    .then(r => r.json())
    .then(function (resp) {
      if (resp.ok) {
        selectPredio.innerHTML = '<option value="">-- selecione --</option>' +
          resp.predios.map(p => `<option value="${p.ID_PREDIO}">${p.NOME}</option>`).join('');
      }
    });
}

function lerPosicaoInventario() {
  document.getElementById('video-posicao-inv').style.display = 'block';
  iniciarLeitorQr('video-posicao-inv', 'canvas-posicao-inv', function (texto) {
    document.getElementById('video-posicao-inv').style.display = 'none';
    fetch(BASE_PATH + '/api/buscar_posicao.php?codigo=' + encodeURIComponent(texto))
      .then(r => r.json())
      .then(function (resp) {
        if (!resp.ok) {
          document.getElementById('resultado-posicao-inv').innerHTML =
            '<div class="alert alert-erro">Posição não encontrada.</div>';
          return;
        }
        posicaoInvAtual = resp.posicao;
        document.getElementById('resultado-posicao-inv').innerHTML =
          '<div class="alert alert-ok">📍 ' + resp.posicao.CODIGO + '</div>';
        carregarItensDaPosicao();
      });
  });
}

function carregarItensDaPosicao() {
  fetch(BASE_PATH + '/api/itens_inventario_posicao.php?id_inventario=' + ID_INVENTARIO_ATUAL + '&id_posicao=' + posicaoInvAtual.ID_POSICAO)
    .then(r => r.json())
    .then(function (resp) {
      const div = document.getElementById('itens-posicao-inv');
      if (!resp.ok) {
        div.innerHTML = '<div class="alert alert-erro">' + resp.erro + '</div>';
        return;
      }
      let html = '<h3>Itens esperados nesta posição</h3>';
      if (resp.itens.length === 0) {
        html += '<p>Nenhum item esperado aqui (pode ser um achado extra — use o formulário abaixo).</p>';
      }
      resp.itens.forEach(function (item) {
        const jaContado = item.QUANTIDADE_CONTADA !== null;
        html += `<div class="card" style="background:#f7faf8;">
          <strong>${item.PRODUTO_NOME || ('#' + item.ID_PRODUTO)}</strong> ${item.LOTE ? '(lote ' + item.LOTE + ')' : ''}
          ${('QUANTIDADE_SISTEMA' in item) ? '<br><small>Sistema: ' + item.QUANTIDADE_SISTEMA + '</small>' : ''}
          ${jaContado ? '<br><small>Já contado: ' + item.QUANTIDADE_CONTADA + '</small>' : `
            <div style="display:flex; gap:8px; margin-top:6px;">
              <input type="number" step="0.0001" id="contagem-${item.ID_ITEM}" placeholder="Quantidade contada" style="margin:0;">
              <button type="button" class="secundario" onclick="salvarContagem(${item.ID_ITEM})">Salvar</button>
            </div>`}
        </div>`;
      });
      html += `<div class="card">
        <h4>Achou um produto que não estava na lista?</h4>
        <div style="display:flex; gap:8px;">
          <input type="text" id="busca-produto-extra" placeholder="Nome, código de barras ou ID" style="flex:1;">
        </div>
        <div id="resultados-busca-extra"></div>
      </div>`;
      div.innerHTML = html;

      document.getElementById('busca-produto-extra').addEventListener('input', function () {
        const termo = this.value;
        if (termo.length < 2) { document.getElementById('resultados-busca-extra').innerHTML = ''; return; }
        fetch(BASE_PATH + '/api/buscar_produto.php?termo=' + encodeURIComponent(termo))
          .then(r => r.json())
          .then(function (resp2) {
            const divBusca = document.getElementById('resultados-busca-extra');
            if (!resp2.ok || resp2.produtos.length === 0) {
              divBusca.innerHTML = '<p style="font-size:0.85rem;color:#888;">Nenhum resultado.</p>';
              return;
            }
            divBusca.innerHTML = resp2.produtos.map(p =>
              `<div class="card" style="padding:8px 10px; cursor:pointer;" onclick='abrirContagemExtra(${p.ID_PRODUTO}, ${JSON.stringify(p.NOME)})'>` +
              `${p.NOME} <small style="color:#888;">#${p.ID_PRODUTO}</small></div>`
            ).join('');
          });
      });
    });
}

function salvarContagem(idItem) {
  const quantidade = document.getElementById('contagem-' + idItem).value;
  const body = new URLSearchParams({ id_item: idItem, quantidade_contada: quantidade });
  fetch(BASE_PATH + '/api/salvar_contagem_inventario.php', { method: 'POST', body })
    .then(r => r.json())
    .then(function (resp) {
      if (resp.ok) {
        window.location.reload();
      } else {
        alert(resp.erro);
      }
    });
}

function abrirContagemExtra(idProduto, nome) {
  const qtd = prompt('Quantidade contada de "' + nome + '":');
  if (qtd === null || qtd === '') return;
  const body = new URLSearchParams({
    id_inventario: ID_INVENTARIO_ATUAL,
    id_posicao: posicaoInvAtual.ID_POSICAO,
    id_produto: idProduto,
    quantidade_contada: qtd,
  });
  fetch(BASE_PATH + '/api/salvar_contagem_extra_inventario.php', { method: 'POST', body })
    .then(r => r.json())
    .then(function (resp) {
      if (resp.ok) {
        window.location.reload();
      } else {
        alert(resp.erro);
      }
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
