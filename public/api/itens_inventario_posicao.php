<?php
header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../includes/Inventario.php';

if (empty($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'erro' => 'Sessão expirada.']);
    exit;
}

$idInventario = (int) ($_GET['id_inventario'] ?? 0);
$idPosicao = (int) ($_GET['id_posicao'] ?? 0);

if ($idInventario <= 0 || $idPosicao <= 0) {
    echo json_encode(['ok' => false, 'erro' => 'Informe id_inventario e id_posicao.']);
    exit;
}

try {
    $itens = (new Inventario())->itensDaPosicao($idInventario, $idPosicao);
    echo json_encode(['ok' => true, 'itens' => $itens]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'erro' => $e->getMessage()]);
}
