<?php
header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../includes/Produto.php';

if (empty($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'erro' => 'Sessão expirada.']);
    exit;
}

$produtoRepo = new Produto();

try {
    if (!empty($_GET['codigo_qr'])) {
        $produto = $produtoRepo->buscarPorCodigoQr(trim($_GET['codigo_qr']));
        if (!$produto) {
            echo json_encode(['ok' => false, 'erro' => 'Produto não encontrado para este QR.']);
            exit;
        }
        echo json_encode(['ok' => true, 'produto' => $produto]);
        exit;
    }

    if (!empty($_GET['termo'])) {
        $produtos = $produtoRepo->buscar(trim($_GET['termo']), 15);
        echo json_encode(['ok' => true, 'produtos' => $produtos]);
        exit;
    }

    echo json_encode(['ok' => false, 'erro' => 'Informe "codigo_qr" ou "termo".']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'erro' => 'Erro interno: ' . $e->getMessage()]);
}
