<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/EstoquePosicao.php';

/**
 * Módulo de Inventário.
 * TIPO: G = Geral (todo o armazém), C = Cíclico (um prédio específico)
 * MODO: C = Cego (não mostra saldo do sistema ao operador), V = Visível
 */
class Inventario
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Abre um novo inventário, tirando uma "foto" (snapshot) do estoque
     * endereçado no momento da abertura.
     */
    public function abrir(string $tipo, string $modo, ?int $idArmazem, ?int $idPredio, string $descricao, int $idUsuario): int
    {
        $this->db->beginTransaction();
        try {
            $this->db->execute(
                "INSERT INTO WMS_INVENTARIO (ID_INVENTARIO, TIPO, MODO, ID_ARMAZEM, ID_PREDIO, DESCRICAO, ID_USUARIO_ABERTURA)
                 VALUES (GEN_ID(GEN_WMS_INVENTARIO_ID, 1), ?, ?, ?, ?, ?, ?)",
                [$tipo, $modo, $idArmazem, $idPredio, $descricao, $idUsuario]
            );
            $idInventario = $this->db->currentGeneratorValue('GEN_WMS_INVENTARIO_ID');

            if ($tipo === 'C' && $idPredio) {
                $snapshot = $this->db->fetchAll(
                    "SELECT ep.ID_POSICAO, ep.ID_PRODUTO, ep.LOTE, ep.QUANTIDADE
                     FROM WMS_ESTOQUE_POSICAO ep
                     JOIN WMS_POSICAO wp ON wp.ID_POSICAO = ep.ID_POSICAO
                     WHERE wp.ID_PREDIO = ?",
                    [$idPredio]
                );
            } else {
                $sql = "SELECT ep.ID_POSICAO, ep.ID_PRODUTO, ep.LOTE, ep.QUANTIDADE
                        FROM WMS_ESTOQUE_POSICAO ep";
                $params = [];
                if ($idArmazem) {
                    $sql .= " JOIN WMS_POSICAO wp ON wp.ID_POSICAO = ep.ID_POSICAO
                              JOIN WMS_PREDIO pr ON pr.ID_PREDIO = wp.ID_PREDIO
                              WHERE pr.ID_ARMAZEM = ?";
                    $params[] = $idArmazem;
                }
                $snapshot = $this->db->fetchAll($sql, $params);
            }

            foreach ($snapshot as $linha) {
                $this->db->execute(
                    "INSERT INTO WMS_INVENTARIO_ITEMS (ID_ITEM, ID_INVENTARIO, ID_POSICAO, ID_PRODUTO, LOTE, QUANTIDADE_SISTEMA)
                     VALUES (GEN_ID(GEN_WMS_INVENTARIO_ITEM_ID, 1), ?, ?, ?, ?, ?)",
                    [$idInventario, $linha['ID_POSICAO'], $linha['ID_PRODUTO'], $linha['LOTE'], $linha['QUANTIDADE']]
                );
            }

            $this->db->commit();
            return $idInventario;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function listar(?string $status = null, int $limite = 50): array
    {
        $sql = "SELECT ID_INVENTARIO, TIPO, MODO, DESCRICAO, STATUS, DATA_ABERTURA, DATA_FINALIZACAO
                FROM WMS_INVENTARIO";
        $params = [];
        if ($status !== null) {
            $sql .= " WHERE STATUS = ?";
            $params[] = $status;
        }
        $sql .= " ORDER BY DATA_ABERTURA DESC ROWS $limite";
        return $this->db->fetchAll($sql, $params);
    }

    public function detalhe(int $idInventario): ?array
    {
        return $this->db->fetchOne("SELECT * FROM WMS_INVENTARIO WHERE ID_INVENTARIO = ?", [$idInventario]);
    }

    /**
     * Itens pendentes de contagem numa posição específica (bipada pelo
     * operador). Não retorna QUANTIDADE_SISTEMA quando o inventário é cego —
     * quem decide esconder é este método, não a tela.
     */
    public function itensDaPosicao(int $idInventario, int $idPosicao): array
    {
        $inv = $this->detalhe($idInventario);
        $cego = $inv && $inv['MODO'] === 'C';

        $itens = $this->db->fetchAll(
            "SELECT ii.ID_ITEM, ii.ID_PRODUTO, p.NOME AS PRODUTO_NOME, ii.LOTE,
                    ii.QUANTIDADE_SISTEMA, ii.QUANTIDADE_CONTADA, ii.DATA_CONTAGEM
             FROM WMS_INVENTARIO_ITEMS ii
             LEFT JOIN PRODUTO p ON p.ID_PRODUTO = ii.ID_PRODUTO
             WHERE ii.ID_INVENTARIO = ? AND ii.ID_POSICAO = ?
             ORDER BY p.NOME",
            [$idInventario, $idPosicao]
        );

        if ($cego) {
            foreach ($itens as &$item) {
                unset($item['QUANTIDADE_SISTEMA']);
            }
        }
        return $itens;
    }

    /** Registra a contagem de um item já existente no snapshot. */
    public function contar(int $idItem, float $quantidadeContada, int $idUsuario): void
    {
        $item = $this->db->fetchOne("SELECT QUANTIDADE_SISTEMA FROM WMS_INVENTARIO_ITEMS WHERE ID_ITEM = ?", [$idItem]);
        if (!$item) {
            throw new InvalidArgumentException('Item de inventário não encontrado.');
        }
        $divergencia = $quantidadeContada - (float) $item['QUANTIDADE_SISTEMA'];

        $this->db->execute(
            "UPDATE WMS_INVENTARIO_ITEMS
             SET QUANTIDADE_CONTADA = ?, DIVERGENCIA = ?, ID_USUARIO_CONTAGEM = ?, DATA_CONTAGEM = CURRENT_TIMESTAMP
             WHERE ID_ITEM = ?",
            [$quantidadeContada, $divergencia, $idUsuario, $idItem]
        );
    }

    /**
     * Registra a contagem de um produto/lote que apareceu numa posição mas
     * NÃO estava no snapshot original (achado surpresa durante a contagem).
     */
    public function contarItemExtra(int $idInventario, int $idPosicao, int $idProduto, ?string $lote, float $quantidadeContada, int $idUsuario): void
    {
        $this->db->execute(
            "INSERT INTO WMS_INVENTARIO_ITEMS
                (ID_ITEM, ID_INVENTARIO, ID_POSICAO, ID_PRODUTO, LOTE, QUANTIDADE_SISTEMA,
                 QUANTIDADE_CONTADA, DIVERGENCIA, ID_USUARIO_CONTAGEM, DATA_CONTAGEM)
             VALUES (GEN_ID(GEN_WMS_INVENTARIO_ITEM_ID, 1), ?, ?, ?, ?, 0, ?, ?, ?, CURRENT_TIMESTAMP)",
            [$idInventario, $idPosicao, $idProduto, $lote, $quantidadeContada, $quantidadeContada, $idUsuario]
        );
    }

    /** Todas as divergências de um inventário (pra tela de fechamento). */
    public function divergencias(int $idInventario): array
    {
        return $this->db->fetchAll(
            "SELECT ii.ID_ITEM, wp.CODIGO, ii.ID_PRODUTO, p.NOME AS PRODUTO_NOME, ii.LOTE,
                    ii.QUANTIDADE_SISTEMA, ii.QUANTIDADE_CONTADA, ii.DIVERGENCIA, ii.AJUSTADO
             FROM WMS_INVENTARIO_ITEMS ii
             JOIN WMS_POSICAO wp ON wp.ID_POSICAO = ii.ID_POSICAO
             LEFT JOIN PRODUTO p ON p.ID_PRODUTO = ii.ID_PRODUTO
             WHERE ii.ID_INVENTARIO = ?
               AND ii.QUANTIDADE_CONTADA IS NOT NULL
               AND ii.DIVERGENCIA <> 0
             ORDER BY wp.CODIGO",
            [$idInventario]
        );
    }

    /** Itens ainda não contados (faltando pra poder finalizar). */
    public function pendentes(int $idInventario): array
    {
        return $this->db->fetchAll(
            "SELECT ii.ID_ITEM, wp.CODIGO, ii.ID_PRODUTO, p.NOME AS PRODUTO_NOME, ii.LOTE
             FROM WMS_INVENTARIO_ITEMS ii
             JOIN WMS_POSICAO wp ON wp.ID_POSICAO = ii.ID_POSICAO
             LEFT JOIN PRODUTO p ON p.ID_PRODUTO = ii.ID_PRODUTO
             WHERE ii.ID_INVENTARIO = ? AND ii.QUANTIDADE_CONTADA IS NULL
             ORDER BY wp.CODIGO",
            [$idInventario]
        );
    }

    /**
     * Aplica os ajustes de uma divergência específica ao estoque endereçado
     * (WMS_ESTOQUE_POSICAO), registrando o movimento como tipo 'A' (Ajuste).
     */
    public function aplicarAjuste(int $idItem, int $idUsuario): void
    {
        $item = $this->db->fetchOne("SELECT * FROM WMS_INVENTARIO_ITEMS WHERE ID_ITEM = ?", [$idItem]);
        if (!$item || $item['QUANTIDADE_CONTADA'] === null) {
            throw new InvalidArgumentException('Item ainda não foi contado.');
        }
        if (($item['AJUSTADO'] ?? 'N') === 'Y') {
            return; // já ajustado, não faz de novo
        }

        $this->db->beginTransaction();
        try {
            $estoquePos = new EstoquePosicao();
            $estoquePos->ajustarQuantidade(
                (int) $item['ID_POSICAO'],
                (int) $item['ID_PRODUTO'],
                $item['LOTE'],
                (float) $item['QUANTIDADE_CONTADA'],
                $idUsuario,
                'Ajuste de inventário #' . $item['ID_INVENTARIO']
            );

            $this->db->execute("UPDATE WMS_INVENTARIO_ITEMS SET AJUSTADO = 'Y' WHERE ID_ITEM = ?", [$idItem]);

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /** Aplica todos os ajustes pendentes de um inventário de uma vez. */
    public function aplicarTodosAjustes(int $idInventario, int $idUsuario): int
    {
        $divergentes = $this->divergencias($idInventario);
        $qtd = 0;
        foreach ($divergentes as $d) {
            if (($d['AJUSTADO'] ?? 'N') !== 'Y') {
                $this->aplicarAjuste((int) $d['ID_ITEM'], $idUsuario);
                $qtd++;
            }
        }
        return $qtd;
    }

    /** Finaliza o inventário (só permite se não houver itens pendentes de contagem). */
    public function finalizar(int $idInventario, int $idUsuario): void
    {
        $pendentes = $this->pendentes($idInventario);
        if (count($pendentes) > 0) {
            throw new RuntimeException('Ainda há ' . count($pendentes) . ' item(ns) não contado(s).');
        }

        $this->db->execute(
            "UPDATE WMS_INVENTARIO SET STATUS = 'FINALIZADO', ID_USUARIO_FINALIZACAO = ?, DATA_FINALIZACAO = CURRENT_TIMESTAMP
             WHERE ID_INVENTARIO = ?",
            [$idUsuario, $idInventario]
        );
    }
}
