<?php
require_once __DIR__ . '/Database.php';

/**
 * Repositório de produtos, reaproveitando a tabela PRODUTO já existente.
 */
class Produto
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function buscar(string $termo, int $limite = 20): array
    {
        $termoLike = '%' . $termo . '%';
        return $this->db->fetchAll(
            "SELECT FIRST $limite ID_PRODUTO, NOME, CODIGO_BARRAS, UNIDADE, STATUS
             FROM PRODUTO
             WHERE STATUS = 1
               AND (UPPER(NOME) LIKE UPPER(?)
                    OR CODIGO_BARRAS LIKE ?
                    OR CAST(ID_PRODUTO AS VARCHAR(20)) = ?)
             ORDER BY NOME",
            [$termoLike, $termoLike, $termo]
        );
    }

    public function porId(int $idProduto): ?array
    {
        return $this->db->fetchOne(
            "SELECT ID_PRODUTO, NOME, CODIGO_BARRAS, UNIDADE, STATUS FROM PRODUTO WHERE ID_PRODUTO = ?",
            [$idProduto]
        );
    }

    public function lotesDisponiveis(int $idProduto): array
    {
        return $this->db->fetchAll(
            "SELECT NUMERO_LOTE, SALDO, VENCIMENTO
             FROM PRODUTO_LOTE
             WHERE ID_PRODUTO = ? AND SALDO > 0
             ORDER BY VENCIMENTO",
            [(string) $idProduto]
        );
    }

    /** Busca produto a partir do texto lido no QR (prefixo PRD|id ou PRD|id|lote). */
    public function buscarPorCodigoQr(string $codigoLido): ?array
    {
        if (!str_starts_with($codigoLido, 'PRD|')) {
            return null;
        }
        $partes = explode('|', $codigoLido);
        $idProduto = (int) ($partes[1] ?? 0);
        if ($idProduto <= 0) {
            return null;
        }
        $produto = $this->porId($idProduto);
        if ($produto) {
            $produto['LOTE_QR'] = $partes[2] ?? null;
        }
        return $produto;
    }

    public function textoQr(int $idProduto, ?string $lote = null): string
    {
        return $lote ? "PRD|{$idProduto}|{$lote}" : "PRD|{$idProduto}";
    }
}
