<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/Recebimento.php';
require_once __DIR__ . '/../includes/Endereco.php';

$recebimento = new Recebimento();
$mensagem = '';
$erro = '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $acao = $_POST['acao'] ?? '';

        if ($acao === 'importar') {
            $recebimento->importarDoEsboco((int) $_POST['id_esboco'], Auth::usuarioId());
            $mensagem = 'Rascunho importado com sucesso. Agora é só conferir os itens abaixo.';
        } elseif ($acao === 'conferir') {
            $recebimento->conferirItem(
                (int) $_POST['id_item'],
                (float) $_POST['quantidade_recebida'],
                trim($_POST['observacao'] ?? '')
            );
            $mensagem = 'Conferência registrada.';
        } elseif ($acao === 'concluir') {
            $recebimento->concluir((int) $_POST['id_recebimento'], Auth::usuarioId());
            $mensagem = 'Recebimento concluído. Os itens já aparecem na tela "Endereçar".';
        }
    }
} catch (Throwable $e) {
    $erro = 'Erro: ' . $e->getMessage();
}

$verDetalhe = (int) ($_GET['recebimento'] ?? 0);
$pendentesEsboco = $recebimento->listarEsbocosPendentes();
$emAndamento = $recebimento->listarRecebimentos('CONFERINDO');
$concluidos = $recebimento->listarRecebimentos('CONCLUIDO', 15);
$detalheItens = $verDetalhe ? $recebimento->itens($verDetalhe) : null;
$detalheCabecalho = $verDetalhe ? $recebimento->detalhe($verDetalhe) : null;
?>
<h1>📥 Recebimento de Mercadoria</h1>

<?php if ($mensagem): ?><div class="alert alert-ok"><?= htmlspecialchars($mensagem) ?></div><?php endif; ?>
<?php if ($erro): ?><div class="alert alert-erro"><?= htmlspecialchars($erro) ?></div><?php endif; ?>

<div class="card">
  <h2>1. Importar rascunho (ESBOCO)</h2>
  <p style="font-size:0.85rem; color:#667;">
    Lista os rascunhos ainda não importados. Se a lista vier vazia, confira se
    <code>Recebimento::TIPO_OBJETO_RECEBIMENTO</code> em <code>includes/Recebimento.php</code>
    está com o valor certo pro seu banco.
  </p>
  <table>
    <thead><tr><th>Documento</th><th>Fornecedor</th><th>Data</th><th>Valor</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($pendentesEsboco as $e): ?>
      <tr>
        <td><?= htmlspecialchars($e['NUMERO_DOCUMENTO']) ?></td>
        <td><?= htmlspecialchars($e['ID_CLIENTE']) ?></td>
        <td><?= htmlspecialchars($e['DATA_EMISSAO']) ?></td>
        <td><?= htmlspecialchars($e['VALOR']) ?></td>
        <td>
          <form method="post" style="margin:0;">
            <input type="hidden" name="acao" value="importar">
            <input type="hidden" name="id_esboco" value="<?= (int)$e['ID_ESBOCO'] ?>">
            <button type="submit" class="secundario">Importar</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($pendentesEsboco)): ?>
      <tr><td colspan="5">Nenhum rascunho pendente de importação.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<div class="card">
  <h2>2. Em conferência</h2>
  <table>
    <thead><tr><th>Documento</th><th>Fornecedor</th><th>Data</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($emAndamento as $r): ?>
      <tr>
        <td><?= htmlspecialchars($r['NUMERO_DOCUMENTO']) ?></td>
        <td><?= htmlspecialchars($r['FORNECEDOR']) ?></td>
        <td><?= htmlspecialchars($r['DATA_EMISSAO']) ?></td>
        <td><a class="btn secundario" href="?recebimento=<?= (int)$r['ID_RECEBIMENTO'] ?>">Conferir itens</a></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($emAndamento)): ?>
      <tr><td colspan="4">Nenhum recebimento em conferência.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if ($verDetalhe && $detalheCabecalho): ?>
<div class="card">
  <h2>Conferindo: <?= htmlspecialchars($detalheCabecalho['NUMERO_DOCUMENTO']) ?></h2>
  <table>
    <thead><tr><th>Produto</th><th>Lote</th><th>Venc.</th><th>Qtd. Nota</th><th>Qtd. Recebida</th><th>Conferir</th></tr></thead>
    <tbody>
      <?php foreach ($detalheItens as $item): ?>
      <tr>
        <td><?= htmlspecialchars($item['PRODUTO_NOME'] ?? ('#' . $item['ID_PRODUTO'])) ?></td>
        <td><?= htmlspecialchars($item['LOTE'] ?? '-') ?></td>
        <td><?= htmlspecialchars($item['VENCIMENTO'] ?? '-') ?></td>
        <td><?= htmlspecialchars($item['QUANTIDADE_NOTA']) ?></td>
        <td><?= htmlspecialchars($item['QUANTIDADE_RECEBIDA']) ?></td>
        <td>
          <form method="post" style="display:flex; gap:6px; align-items:center; margin:0;">
            <input type="hidden" name="acao" value="conferir">
            <input type="hidden" name="id_item" value="<?= (int)$item['ID_ITEM'] ?>">
            <input type="number" step="0.0001" name="quantidade_recebida" style="width:100px; margin:0;"
                   value="<?= htmlspecialchars($item['QUANTIDADE_NOTA']) ?>" required>
            <button type="submit" class="secundario">Salvar</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <form method="post" style="margin-top:12px;">
    <input type="hidden" name="acao" value="concluir">
    <input type="hidden" name="id_recebimento" value="<?= $verDetalhe ?>">
    <button type="submit">✅ Concluir conferência</button>
  </form>
  <p style="font-size:0.85rem; color:#667; margin-top:8px;">
    Depois de concluir, vá em <a href="<?= $bp ?>/enderecar.php">Endereçar</a> pra guardar
    fisicamente esses produtos numa posição do armazém.
  </p>
</div>
<?php endif; ?>

<div class="card">
  <h2>Últimos concluídos</h2>
  <table>
    <thead><tr><th>Documento</th><th>Fornecedor</th><th>Data</th></tr></thead>
    <tbody>
      <?php foreach ($concluidos as $r): ?>
      <tr>
        <td><?= htmlspecialchars($r['NUMERO_DOCUMENTO']) ?></td>
        <td><?= htmlspecialchars($r['FORNECEDOR']) ?></td>
        <td><?= htmlspecialchars($r['DATA_EMISSAO']) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($concluidos)): ?>
      <tr><td colspan="3">Nenhum recebimento concluído ainda.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
