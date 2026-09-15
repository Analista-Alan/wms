<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/EstoquePosicao.php';

$estoquePos = new EstoquePosicao();
$movs = $estoquePos->ultimasMovimentacoes(15);
?>
<h1>Painel do WMS</h1>

<div class="grid grid-3">
  <a class="card" href="<?= $bp ?>/enderecos.php" style="text-decoration:none; color:inherit;">
    <h2>🏭 Endereços</h2>
    <p>Cadastrar armazéns, ruas (corredores), prédios e gerar posições.</p>
  </a>
  <a class="card" href="<?= $bp ?>/recebimento.php" style="text-decoration:none; color:inherit;">
    <h2>📥 Recebimento</h2>
    <p>Importar nota de rascunho e conferir mercadoria recebida.</p>
  </a>
  <a class="card" href="<?= $bp ?>/enderecar.php" style="text-decoration:none; color:inherit;">
    <h2>📲 Endereçar produto</h2>
    <p>Bipar a posição e o produto para guardar estoque num endereço.</p>
  </a>
  <a class="card" href="<?= $bp ?>/separacao.php" style="text-decoration:none; color:inherit;">
    <h2>📋 Separação</h2>
    <p>Importar pedido/nota fiscal e separar produtos por posição.</p>
  </a>
  <a class="card" href="<?= $bp ?>/expedicao.php" style="text-decoration:none; color:inherit;">
    <h2>🚚 Expedição</h2>
    <p>Vincular transportadora, veículo e motorista, e expedir.</p>
  </a>
  <a class="card" href="<?= $bp ?>/movimentar.php" style="text-decoration:none; color:inherit;">
    <h2>🔄 Movimentar / Transferir</h2>
    <p>Mover produto entre posições ou dar baixa.</p>
  </a>
  <a class="card" href="<?= $bp ?>/inventario.php" style="text-decoration:none; color:inherit;">
    <h2>🔢 Inventário</h2>
    <p>Contagem geral ou cíclica, cega ou com saldo visível.</p>
  </a>
  <a class="card" href="<?= $bp ?>/consulta_estoque.php" style="text-decoration:none; color:inherit;">
    <h2>🔍 Consultar estoque</h2>
    <p>Ver o que há em cada posição, ou onde está um produto.</p>
  </a>
  <a class="card" href="<?= $bp ?>/etiquetas_posicoes.php" style="text-decoration:none; color:inherit;">
    <h2>🖨️ Etiquetas de posições</h2>
    <p>Gerar e imprimir QR Codes das posições do armazém.</p>
  </a>
  <a class="card" href="<?= $bp ?>/etiquetas_produtos.php" style="text-decoration:none; color:inherit;">
    <h2>🏷️ Etiquetas de produtos</h2>
    <p>Gerar e imprimir QR Codes dos produtos/lotes.</p>
  </a>
</div>

<div class="card">
  <h2>Últimas movimentações</h2>
  <table>
    <thead>
      <tr><th>Data</th><th>Tipo</th><th>Produto</th><th>Lote</th><th>Qtd</th><th>Origem</th><th>Destino</th><th>Usuário</th></tr>
    </thead>
    <tbody>
      <?php foreach ($movs as $m): ?>
      <tr>
        <td><?= htmlspecialchars($m['DATA_MOVIMENTACAO']) ?></td>
        <td>
          <?php
            $tipos = ['E' => 'Entrada', 'S' => 'Saída', 'T' => 'Transferência', 'A' => 'Ajuste'];
            echo htmlspecialchars($tipos[$m['TIPO']] ?? $m['TIPO']);
          ?>
        </td>
        <td><?= htmlspecialchars($m['PRODUTO_NOME']) ?></td>
        <td><?= htmlspecialchars($m['LOTE'] ?? '-') ?></td>
        <td><?= htmlspecialchars($m['QUANTIDADE']) ?></td>
        <td><?= htmlspecialchars($m['CODIGO_ORIGEM'] ?? '-') ?></td>
        <td><?= htmlspecialchars($m['CODIGO_DESTINO'] ?? '-') ?></td>
        <td><?= htmlspecialchars($m['USUARIO_NOME'] ?? '-') ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($movs)): ?>
      <tr><td colspan="8">Nenhuma movimentação registrada ainda.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
