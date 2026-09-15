<?php
header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../includes/EstoquePosicao.php';
require_once __DIR__ . '/../../includes/Recebimento.php';

if (empty($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'erro' => 'Sessão expirada.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'erro' => 'Método não permitido.']);
    exit;
}

$idPosicao = (int) ($_POST['id_posicao'] ?? 0);
$idProduto = (int) ($_POST['id_produto'] ?? 0);
$quantidade = (float) ($_POST['quantidade'] ?? 0);
$lote = trim($_POST['lote'] ?? '') ?: null;
$validade = trim($_POST['validade'] ?? '') ?: null;
$idItemRecebimento = (int) ($_POST['id_item_recebimento'] ?? 0); // opcional: veio da tela de Recebimento

if ($idPosicao <= 0 || $idProduto <= 0 || $quantidade <= 0) {
    echo json_encode(['ok' => false, 'erro' => 'Dados incompletos: posição, produto e quantidade são obrigatórios.']);
    exit;
}

try {
    $estoquePos = new EstoquePosicao();
    $estoquePos->enderecar(
        $idPosicao,
        $idProduto,
        $lote,
        $quantidade,
        $validade,
        Auth::usuarioId(),
        null,
        'Endereçamento via leitura de QR'
    );

    if ($idItemRecebimento > 0) {
        (new Recebimento())->registrarEnderecamento($idItemRecebimento, $quantidade);
    }

    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'erro' => $e->getMessage()]);
}
