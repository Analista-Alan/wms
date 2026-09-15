<?php
header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../includes/Separacao.php';

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

$idItem = (int) ($_POST['id_item'] ?? 0);
$idPosicao = (int) ($_POST['id_posicao'] ?? 0);
$quantidade = (float) ($_POST['quantidade'] ?? 0);
$lote = trim($_POST['lote'] ?? '') ?: null;

if ($idItem <= 0 || $idPosicao <= 0 || $quantidade <= 0) {
    echo json_encode(['ok' => false, 'erro' => 'Dados incompletos.']);
    exit;
}

try {
    (new Separacao())->retirar($idItem, $idPosicao, $lote, $quantidade, Auth::usuarioId());
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'erro' => $e->getMessage()]);
}
