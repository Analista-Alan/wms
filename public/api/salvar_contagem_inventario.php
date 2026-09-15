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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'erro' => 'Método não permitido.']);
    exit;
}

$idItem = (int) ($_POST['id_item'] ?? 0);
$quantidade = $_POST['quantidade_contada'] ?? '';

if ($idItem <= 0 || $quantidade === '') {
    echo json_encode(['ok' => false, 'erro' => 'Dados incompletos.']);
    exit;
}

try {
    (new Inventario())->contar($idItem, (float) $quantidade, Auth::usuarioId());
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'erro' => $e->getMessage()]);
}
