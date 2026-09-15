<?php
header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../includes/Endereco.php';
require_once __DIR__ . '/../../includes/Database.php';

if (empty($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'erro' => 'Sessão expirada.']);
    exit;
}

$idArmazem = (int) ($_GET['id_armazem'] ?? 0);
if ($idArmazem <= 0) {
    echo json_encode(['ok' => false, 'erro' => 'Informe id_armazem.']);
    exit;
}

try {
    $db = Database::getInstance();
    $predios = $db->fetchAll(
        "SELECT p.ID_PREDIO, p.NOME
         FROM WMS_PREDIO p
         WHERE p.ID_ARMAZEM = ?
         ORDER BY p.NOME",
        [$idArmazem]
    );
    echo json_encode(['ok' => true, 'predios' => $predios]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'erro' => $e->getMessage()]);
}
