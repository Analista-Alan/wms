<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/Expedicao.php';
require_once __DIR__ . '/../includes/Separacao.php';

$expedicao = new Expedicao();
$separacaoRepo = new Separacao();
$mensagem = '';
$erro = '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'expedir') {
        $expedicao->expedir(
            (int) $_POST['id_separacao'],
            !empty($_POST['id_transportadora']) ? (int) $_POST['id_transportadora'] : null,
            !empty($_POST['id_veiculo']) ? (int) $_POST['id_veiculo'] : null,
            !empty($_POST['id_motorista']) ? (int) $_POST['id_motorista'] : null,
            trim($_POST['numero_lacre'] ?? '') ?: null,
            $_POST['peso_total'] !== '' ? (float) $_POST['peso_total'] : null,
            trim($_POST['observacao'] ?? ''),
            Auth::usuarioId()
        );
        $mensagem = 'Expedição registrada com sucesso!';
    }
} catch (Throwable $e) {
    $erro = 'Erro: ' . $e->getMessage();
}

$prontasParaExpedicao = $separacaoRepo->listarProntasParaExpedicao();
$transportadoras = $expedicao->listarTransportadoras();
$veiculos = $expedicao->listarVeiculos();
$motoristas = $expedicao->listarMotoristas();
$historico = $expedicao->listar(20);
?>
<h1>🚚 Expedição</h1>

<?php if ($mensagem): ?><div class="alert alert-ok"><?= htmlspecialchars($mensagem) ?></div><?php endif; ?>
<?php if ($erro): ?><div class="alert alert-erro"><?= htmlspecialchars($erro) ?></div><?php endif; ?>

<div class="card">
  <h2>Separações prontas para expedir</h2>
  <table>
    <thead><tr><th>Documento</th><th>Cliente</th><th>Concluída em</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($prontasParaExpedicao as $s): ?>
      <tr>
        <td><?= htmlspecialchars($s['NUMERO_DOCUMENTO']) ?></td>
        <td><?= htmlspecialchars($s['CLIENTE']) ?></td>
        <td><?= htmlspecialchars($s['DATA_CONCLUSAO']) ?></td>
        <td>
          <button type="button" class="secundario"
            onclick="abrirExpedicao(<?= (int)$s['ID_SEPARACAO'] ?>, <?= json_encode($s['NUMERO_DOCUMENTO'] . ' — ' . $s['CLIENTE']) ?>)">
            Expedir
          </button>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($prontasParaExpedicao)): ?>
      <tr><td colspan="4">Nenhuma separação pronta para expedição.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<div class="card" id="bloco-expedicao" style="display:none;">
  <h2 id="titulo-expedicao">Expedir</h2>
  <form method="post">
    <input type="hidden" name="acao" value="expedir">
    <input type="hidden" name="id_separacao" id="id_separacao_expedicao">
    <div class="grid grid-3">
      <div>
        <label>Transportadora</label>
        <select name="id_transportadora">
          <option value="">-- nenhuma --</option>
          <?php foreach ($transportadoras as $t): ?>
          <option value="<?= (int)$t['ID_TRANSPORTADORA'] ?>"><?= htmlspecialchars($t['NOME']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Veículo</label>
        <select name="id_veiculo">
          <option value="">-- nenhum --</option>
          <?php foreach ($veiculos as $v): ?>
          <option value="<?= (int)$v['ID_VEICULO'] ?>"><?= htmlspecialchars($v['NOME']) ?> (<?= htmlspecialchars($v['PLACA']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Motorista</label>
        <select name="id_motorista">
          <option value="">-- nenhum --</option>
          <?php foreach ($motoristas as $m): ?>
          <option value="<?= (int)$m['ID_MOTORISTA'] ?>"><?= htmlspecialchars($m['NOME']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="grid grid-3">
      <div>
        <label>Número do lacre (opcional)</label>
        <input type="text" name="numero_lacre">
      </div>
      <div>
        <label>Peso total (opcional)</label>
        <input type="number" step="0.0001" name="peso_total">
      </div>
      <div>
        <label>Observação</label>
        <input type="text" name="observacao">
      </div>
    </div>
    <button type="submit">✅ Confirmar expedição</button>
  </form>
</div>

<div class="card">
  <h2>Últimas expedições</h2>
  <table>
    <thead><tr><th>Data</th><th>Documento</th><th>Cliente</th><th>Transportadora</th><th>Veículo</th><th>Motorista</th></tr></thead>
    <tbody>
      <?php foreach ($historico as $h): ?>
      <tr>
        <td><?= htmlspecialchars($h['DATA_EXPEDICAO']) ?></td>
        <td><?= htmlspecialchars($h['NUMERO_DOCUMENTO']) ?></td>
        <td><?= htmlspecialchars($h['CLIENTE']) ?></td>
        <td><?= htmlspecialchars($h['TRANSPORTADORA_NOME'] ?? '-') ?></td>
        <td><?= htmlspecialchars($h['VEICULO_NOME'] ?? '-') ?><?= $h['PLACA'] ? ' (' . htmlspecialchars($h['PLACA']) . ')' : '' ?></td>
        <td><?= htmlspecialchars($h['MOTORISTA_NOME'] ?? '-') ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($historico)): ?>
      <tr><td colspan="6">Nenhuma expedição registrada ainda.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<script>
function abrirExpedicao(idSeparacao, titulo) {
  document.getElementById('id_separacao_expedicao').value = idSeparacao;
  document.getElementById('titulo-expedicao').textContent = 'Expedir: ' + titulo;
  document.getElementById('bloco-expedicao').style.display = 'block';
  document.getElementById('bloco-expedicao').scrollIntoView({ behavior: 'smooth' });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
