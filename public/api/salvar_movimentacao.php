<?php
header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../includes/EstoquePosicao.php';

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

$tipo = $_POST['tipo'] ?? 'T'; // T = transferência, S = saída
$idPosicaoOrigem = (int) ($_POST['id_posicao_origem'] ?? 0);
$idPosicaoDestino = (int) ($_POST['id_posicao_destino'] ?? 0);
$idProduto = (int) ($_POST['id_produto'] ?? 0);
$quantidade = (float) ($_POST['quantidade'] ?? 0);
$lote = trim($_POST['lote'] ?? '') ?: null;
$observacao = trim($_POST['observacao'] ?? '');

if ($idPosicaoOrigem <= 0 || $idProduto <= 0 || $quantidade <= 0) {
    echo json_encode(['ok' => false, 'erro' => 'Dados incompletos.']);
    exit;
}

try {
    $estoquePos = new EstoquePosicao();

    if ($tipo === 'S') {
        $estoquePos->darSaida($idPosicaoOrigem, $idProduto, $lote, $quantidade, Auth::usuarioId(), $observacao);
    } else {
        if ($idPosicaoDestino <= 0) {
            echo json_encode(['ok' => false, 'erro' => 'Informe a posição de destino.']);
            exit;
        }
        $estoquePos->transferir($idPosicaoOrigem, $idPosicaoDestino, $idProduto, $lote, $quantidade, Auth::usuarioId(), $observacao);
    }

    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'erro' => $e->getMessage()]);
}
