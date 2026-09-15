<?php
require_once __DIR__ . '/Database.php';

/**
 * Módulo de Recebimento de Mercadoria.
 *
 * Fluxo: ESBOCO (rascunho sincronizado do SAP B1, filtrado por TIPO_OBJETO)
 *   -> importarDoEsboco() cria WMS_RECEBIMENTO + WMS_RECEBIMENTO_ITEMS
 *   -> confereItem() registra a quantidade realmente recebida (conferência física)
 *   -> concluir() marca o recebimento como concluído, liberando os itens
 *      para endereçamento na tela "Endereçar" (via itensPendentesEnderecamento()).
 */
class Recebimento
{
    /**
     * Valor de ESBOCO.TIPO_OBJETO que identifica um rascunho de RECEBIMENTO
     * DE MERCADORIA (Goods Receipt PO) no SAP Business One.
     *
     * ⚠️ CONFIRME ESTE VALOR no seu ambiente. O código-padrão do SAP B1 para
     * "Recebimento de Mercadoria" (Goods Receipt PO / Purchase Delivery Note)
     * costuma ser 20, mas isso pode variar dependendo de customizações do
     * seu SAP. Para confirmar com certeza, rode no seu Firebird:
     *
     *   SELECT ID_ESBOCO, NUMERO_DOCUMENTO, TIPO_OBJETO, TIPO_ESBOCO
     *   FROM ESBOCO
     *   WHERE NUMERO_DOCUMENTO = <coloque aqui o número de um recebimento que você já sabe que é recebimento>;
     *
     * e veja o valor de TIPO_OBJETO retornado.
     */
    public const TIPO_OBJETO_RECEBIMENTO = 20;

    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** Lista rascunhos do ESBOCO ainda não importados para WMS_RECEBIMENTO. */
    public function listarEsbocosPendentes(int $limite = 50): array
    {
        return $this->db->fetchAll(
            "SELECT e.ID_ESBOCO, e.NUMERO_DOCUMENTO, e.ID_CLIENTE, e.DATA_EMISSAO,
                    e.DATA_NOTA, e.VALOR, e.CHAVE_NFE, e.STATUS
             FROM ESBOCO e
             WHERE e.TIPO_OBJETO = ?
               AND NOT EXISTS (
                    SELECT 1 FROM WMS_RECEBIMENTO r WHERE r.ID_ESBOCO_ORIGEM = e.ID_ESBOCO
               )
             ORDER BY e.DATA_EMISSAO DESC
             ROWS ?",
            [self::TIPO_OBJETO_RECEBIMENTO, $limite]
        );
    }

    /** Itens de um ESBOCO específico (para conferir antes de importar). */
    public function itensDoEsboco(int $idEsboco, ?int $idSap = null): array
    {
        // ESBOCO_ITEMS não tem uma FK direta para ESBOCO; o vínculo é pelo
        // mesmo ID_SAP (padrão de sincronização SAP B1 usado neste banco).
        if ($idSap === null) {
            $cabecalho = $this->db->fetchOne("SELECT ID_SAP FROM ESBOCO WHERE ID_ESBOCO = ?", [$idEsboco]);
            $idSap = $cabecalho['ID_SAP'] ?? null;
        }
        if ($idSap === null) {
            return [];
        }
        return $this->db->fetchAll(
            "SELECT ei.LINHA, ei.ID_PRODUTO, ei.QUANTIDADE, ei.PRECO_UNITARIO,
                    ei.LOTE, ei.LOTE_VENCIMENTO, p.NOME AS PRODUTO_NOME
             FROM ESBOCO_ITEMS ei
             LEFT JOIN PRODUTO p ON p.ID_PRODUTO = CAST(ei.ID_PRODUTO AS INTEGER)
             WHERE ei.ID_SAP = ?
             ORDER BY ei.LINHA",
            [$idSap]
        );
    }

    /** Importa um rascunho do ESBOCO para WMS_RECEBIMENTO (cabeçalho + itens). */
    public function importarDoEsboco(int $idEsboco, int $idUsuario): int
    {
        $this->db->beginTransaction();
        try {
            $esboco = $this->db->fetchOne("SELECT * FROM ESBOCO WHERE ID_ESBOCO = ?", [$idEsboco]);
            if (!$esboco) {
                throw new InvalidArgumentException('Rascunho (ESBOCO) não encontrado.');
            }

            $this->db->execute(
                "INSERT INTO WMS_RECEBIMENTO
                    (ID_RECEBIMENTO, ID_ESBOCO_ORIGEM, NUMERO_DOCUMENTO, CHAVE_NFE, FORNECEDOR,
                     DATA_EMISSAO, DATA_NOTA, VALOR, ID_USUARIO_IMPORTACAO)
                 VALUES (GEN_ID(GEN_WMS_RECEBIMENTO_ID, 1), ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $idEsboco,
                    $esboco['NUMERO_DOCUMENTO'],
                    $esboco['CHAVE_NFE'],
                    $esboco['ID_CLIENTE'],
                    $esboco['DATA_EMISSAO'],
                    $esboco['DATA_NOTA'],
                    $esboco['VALOR'],
                    $idUsuario,
                ]
            );
            $idRecebimento = $this->db->currentGeneratorValue('GEN_WMS_RECEBIMENTO_ID');

            $itens = $this->itensDoEsboco($idEsboco, $esboco['ID_SAP']);
            foreach ($itens as $item) {
                $this->db->execute(
                    "INSERT INTO WMS_RECEBIMENTO_ITEMS
                        (ID_ITEM, ID_RECEBIMENTO, ID_PRODUTO, LOTE, VENCIMENTO, QUANTIDADE_NOTA, PRECO_UNITARIO)
                     VALUES (GEN_ID(GEN_WMS_RECEBIMENTO_ITEM_ID, 1), ?, ?, ?, ?, ?, ?)",
                    [
                        $idRecebimento,
                        (int) $item['ID_PRODUTO'],
                        $item['LOTE'],
                        $item['LOTE_VENCIMENTO'],
                        $item['QUANTIDADE'],
                        $item['PRECO_UNITARIO'],
                    ]
                );
            }

            $this->db->execute("UPDATE WMS_RECEBIMENTO SET STATUS = 'CONFERINDO' WHERE ID_RECEBIMENTO = ?", [$idRecebimento]);

            $this->db->commit();
            return $idRecebimento;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function listarRecebimentos(?string $status = null, int $limite = 50): array
    {
        $sql = "SELECT ID_RECEBIMENTO, NUMERO_DOCUMENTO, FORNECEDOR, DATA_EMISSAO, VALOR, STATUS, DATA_IMPORTACAO
                FROM WMS_RECEBIMENTO";
        $params = [];
        if ($status !== null) {
            $sql .= " WHERE STATUS = ?";
            $params[] = $status;
        }
        $sql .= " ORDER BY DATA_IMPORTACAO DESC ROWS $limite";
        return $this->db->fetchAll($sql, $params);
    }

    public function detalhe(int $idRecebimento): ?array
    {
        return $this->db->fetchOne("SELECT * FROM WMS_RECEBIMENTO WHERE ID_RECEBIMENTO = ?", [$idRecebimento]);
    }

    public function itens(int $idRecebimento): array
    {
        return $this->db->fetchAll(
            "SELECT ri.ID_ITEM, ri.ID_PRODUTO, p.NOME AS PRODUTO_NOME, ri.LOTE, ri.VENCIMENTO,
                    ri.QUANTIDADE_NOTA, ri.QUANTIDADE_RECEBIDA, ri.QUANTIDADE_ENDERECADA, ri.OBSERVACAO
             FROM WMS_RECEBIMENTO_ITEMS ri
             LEFT JOIN PRODUTO p ON p.ID_PRODUTO = ri.ID_PRODUTO
             WHERE ri.ID_RECEBIMENTO = ?
             ORDER BY ri.ID_ITEM",
            [$idRecebimento]
        );
    }

    /** Registra a conferência física de um item (quanto realmente chegou). */
    public function conferirItem(int $idItem, float $quantidadeRecebida, string $observacao = ''): void
    {
        $this->db->execute(
            "UPDATE WMS_RECEBIMENTO_ITEMS SET QUANTIDADE_RECEBIDA = ?, OBSERVACAO = ? WHERE ID_ITEM = ?",
            [$quantidadeRecebida, $observacao, $idItem]
        );
    }

    /** Conclui a conferência do recebimento (não impede endereçamento parcial já feito). */
    public function concluir(int $idRecebimento, int $idUsuario): void
    {
        $this->db->execute(
            "UPDATE WMS_RECEBIMENTO SET STATUS = 'CONCLUIDO', ID_USUARIO_CONCLUSAO = ?, DATA_CONCLUSAO = CURRENT_TIMESTAMP
             WHERE ID_RECEBIMENTO = ?",
            [$idUsuario, $idRecebimento]
        );
    }

    /**
     * Itens já conferidos (quantidade recebida > 0) que ainda faltam ser
     * totalmente endereçados (quantidade recebida > quantidade já endereçada).
     * Usado para alimentar a tela de Endereçar com sugestões.
     */
    public function itensPendentesEnderecamento(int $limite = 100): array
    {
        return $this->db->fetchAll(
            "SELECT FIRST $limite ri.ID_ITEM, ri.ID_RECEBIMENTO, r.NUMERO_DOCUMENTO, r.FORNECEDOR,
                    ri.ID_PRODUTO, p.NOME AS PRODUTO_NOME, ri.LOTE, ri.VENCIMENTO,
                    ri.QUANTIDADE_RECEBIDA, ri.QUANTIDADE_ENDERECADA,
                    (ri.QUANTIDADE_RECEBIDA - ri.QUANTIDADE_ENDERECADA) AS QUANTIDADE_PENDENTE
             FROM WMS_RECEBIMENTO_ITEMS ri
             JOIN WMS_RECEBIMENTO r ON r.ID_RECEBIMENTO = ri.ID_RECEBIMENTO
             LEFT JOIN PRODUTO p ON p.ID_PRODUTO = ri.ID_PRODUTO
             WHERE ri.QUANTIDADE_RECEBIDA > ri.QUANTIDADE_ENDERECADA
             ORDER BY r.DATA_IMPORTACAO",
        );
    }

    /** Marca uma quantidade como endereçada (chamado depois que EstoquePosicao::enderecar() é executado com sucesso). */
    public function registrarEnderecamento(int $idItem, float $quantidade): void
    {
        $this->db->execute(
            "UPDATE WMS_RECEBIMENTO_ITEMS SET QUANTIDADE_ENDERECADA = QUANTIDADE_ENDERECADA + ? WHERE ID_ITEM = ?",
            [$quantidade, $idItem]
        );
    }
}
