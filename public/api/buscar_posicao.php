<?php
header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../includes/Endereco.php';

if (empty($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'erro' => 'Sessão expirada.']);
    exit;
}

$codigo = trim($_GET['codigo'] ?? '');
if ($codigo === '') {
    echo json_encode(['ok' => false, 'erro' => 'Código vazio.']);
    exit;
}

try {
    $endereco = new Endereco();
    $posicao = $endereco->buscarPorCodigo($codigo);
    if (!$posicao) {
        echo json_encode(['ok' => false, 'erro' => 'Posição não encontrada.']);
        exit;
    }
    echo json_encode(['ok' => true, 'posicao' => $posicao]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'erro' => 'Erro interno: ' . $e->getMessage()]);
}
