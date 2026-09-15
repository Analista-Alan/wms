<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/Endereco.php';
require_once __DIR__ . '/../includes/Auth.php';

$endereco = new Endereco();
$mensagem = '';
$erro = '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $acao = $_POST['acao'] ?? '';

        if ($acao === 'criar_armazem') {
            $endereco->criarArmazem(trim($_POST['nome']), null, null);
            $mensagem = 'Armazém criado com sucesso.';
        } elseif ($acao === 'criar_corredor') {
            $endereco->criarCorredor((int) $_POST['id_armazem'], trim($_POST['nome']), 1, $_POST['sentido'] ?? 'C');
            $mensagem = 'Rua/Corredor criado com sucesso.';
        } elseif ($acao === 'criar_predio') {
            $endereco->criarPredio(
                (int) $_POST['id_armazem'],
                (int) $_POST['id_corredor'],
                trim($_POST['nome']),
                1,
                (int) $_POST['qtd_niveis']
            );
            $mensagem = 'Prédio/Bloco criado com sucesso.';
        } elseif ($acao === 'gerar_posicoes') {
            $criados = $endereco->gerarPosicoesEmLote(
                (int) $_POST['id_predio'],
                (int) $_POST['posicoes_por_nivel'],
                $_POST['capacidade'] !== '' ? (float) $_POST['capacidade'] : null
            );
            $mensagem = count($criados) . ' posições geradas com sucesso. Vá em "Etiquetas de posições" para imprimir os QR Codes.';
        }
    }
} catch (Throwable $e) {
    $erro = 'Erro: ' . $e->getMessage();
}

$armazens = $endereco->listarArmazens();
$idArmazemSelecionado = (int) ($_GET['armazem'] ?? ($armazens[0]['ID_ARMAZEM'] ?? 0));
$corredores = $idArmazemSelecionado ? $endereco->listarCorredores($idArmazemSelecionado) : [];
$idCorredorSelecionado = (int) ($_GET['corredor'] ?? ($corredores[0]['ID_CORREDOR'] ?? 0));
$predios = $idCorredorSelecionado ? $endereco->listarPredios($idCorredorSelecionado) : [];
?>
<h1>Endereços do armazém</h1>

<?php if ($mensagem): ?><div class="alert alert-ok"><?= htmlspecialchars($mensagem) ?></div><?php endif; ?>
<?php if ($erro): ?><div class="alert alert-erro"><?= htmlspecialchars($erro) ?></div><?php endif; ?>

<div class="grid grid-2">

  <div class="card">
    <h2>1. Armazéns</h2>
    <form method="post">
      <input type="hidden" name="acao" value="criar_armazem">
      <label>Nome do armazém</label>
      <input type="text" name="nome" required placeholder="Ex: Armazém Central">
      <button type="submit">+ Criar armazém</button>
    </form>
    <hr>
    <table>
      <thead><tr><th>Nome</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($armazens as $a): ?>
        <tr>
          <td><a href="?armazem=<?= (int)$a['ID_ARMAZEM'] ?>"><?= htmlspecialchars($a['NOME']) ?></a></td>
          <td><span class="badge badge-ativo"><?= htmlspecialchars($a['STATUS']) ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="card">
    <h2>2. Ruas / Corredores <?= $idArmazemSelecionado ? '' : '(selecione um armazém)' ?></h2>
    <?php if ($idArmazemSelecionado): ?>
    <form method="post">
      <input type="hidden" name="acao" value="criar_corredor">
      <input type="hidden" name="id_armazem" value="<?= $idArmazemSelecionado ?>">
      <label>Nome da rua/corredor</label>
      <input type="text" name="nome" required placeholder="Ex: Rua 01">
      <label>Sentido</label>
      <select name="sentido">
        <option value="C">Crescente</option>
        <option value="D">Decrescente</option>
      </select>
      <button type="submit">+ Criar rua</button>
    </form>
    <hr>
    <table>
      <thead><tr><th>Nome</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($corredores as $c): ?>
        <tr>
          <td><a href="?armazem=<?= $idArmazemSelecionado ?>&corredor=<?= (int)$c['ID_CORREDOR'] ?>"><?= htmlspecialchars($c['NOME']) ?></a></td>
          <td><span class="badge badge-ativo"><?= htmlspecialchars($c['STATUS']) ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

</div>

<?php if ($idCorredorSelecionado): ?>
<div class="card">
  <h2>3. Prédios / Blocos da rua selecionada</h2>
  <form method="post">
    <input type="hidden" name="acao" value="criar_predio">
    <input type="hidden" name="id_armazem" value="<?= $idArmazemSelecionado ?>">
    <input type="hidden" name="id_corredor" value="<?= $idCorredorSelecionado ?>">
    <div class="grid grid-3">
      <div>
        <label>Nome do prédio/bloco</label>
        <input type="text" name="nome" required placeholder="Ex: Bloco A">
      </div>
      <div>
        <label>Quantidade de níveis (andares)</label>
        <input type="number" name="qtd_niveis" min="1" value="1" required>
      </div>
      <div style="display:flex; align-items:flex-end;">
        <button type="submit" style="width:100%">+ Criar prédio</button>
      </div>
    </div>
  </form>
  <hr>
  <table>
    <thead><tr><th>Nome</th><th>Níveis</th><th>Gerar posições (QR)</th></tr></thead>
    <tbody>
      <?php foreach ($predios as $p): ?>
      <tr>
        <td><?= htmlspecialchars($p['NOME']) ?></td>
        <td><?= (int)$p['QTD_NIVEIS'] ?></td>
        <td>
          <form method="post" style="display:flex; gap:8px; align-items:center; margin:0;">
            <input type="hidden" name="acao" value="gerar_posicoes">
            <input type="hidden" name="id_predio" value="<?= (int)$p['ID_PREDIO'] ?>">
            <input type="number" name="posicoes_por_nivel" min="1" value="5" style="width:80px; margin:0;" title="Posições por nível">
            <input type="number" name="capacidade" step="0.01" placeholder="Capac. (opcional)" style="width:140px; margin:0;">
            <button type="submit" class="secundario">Gerar</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($predios)): ?>
      <tr><td colspan="3">Nenhum prédio cadastrado nesta rua ainda.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
  <p style="font-size:0.85rem; color:#667;">
    "Gerar posições" cria automaticamente todas as posições (nível × posições por nível) do prédio,
    já com um código único pronto para virar QR Code em <a href="<?= $bp ?>/etiquetas_posicoes.php">Etiquetas de posições</a>.
  </p>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
