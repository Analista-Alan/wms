<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/EstoquePosicao.php';

/**
 * Módulo de Separação de Mercadorias (Picking).
 *
 * Separação avulsa: importa os itens de um PEDIDO_VENDA ou de uma NOTA
 * (fiscal) já existente no Geagro para uma lista de separação própria do
 * WMS. O operador bipa a posição de onde está retirando o produto; a baixa
 * do estoque endereçado usa a mesma EstoquePosicao::darSaida() já usada em
 * outras partes do sistema.
 */
class Separacao
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** Busca pedidos de venda por número/cliente, ainda não importados para separação. */
    public function buscarPedidosVenda(string $termo, int $limite = 20): array
    {
        $termoLike = '%' . $termo . '%';
        return $this->db->fetchAll(
            "SELECT FIRST $limite pv.ID_PEDIDO_VENDA, pv.NUMERO_DOCUMENTO, pv.ID_CLIENTE, pv.DATA_EMISSAO, pv.VALOR
             FROM PEDIDO_VENDA pv
             WHERE (CAST(pv.NUMERO_DOCUMENTO AS VARCHAR(20)) LIKE ? OR pv.ID_CLIENTE LIKE ?)
               AND NOT EXISTS (
                    SELECT 1 FROM WMS_SEPARACAO s WHERE s.ORIGEM = 'P' AND s.ID_ORIGEM = pv.ID_PEDIDO_VENDA
               )
             ORDER BY pv.DATA_EMISSAO DESC",
            [$termoLike, $termoLike]
        );
    }

    /** Busca notas fiscais por número/destinatário, ainda não importadas para separação. */
    public function buscarNotasFiscais(string $termo, int $limite = 20): array
    {
        $termoLike = '%' . $termo . '%';
        return $this->db->fetchAll(
            "SELECT FIRST $limite fn.ID_FISCAL_NOTA, fn.NUMERO, fn.NOME, fn.DATA_EMISSAO, fn.CHAVE
             FROM FISCAL_NOTA fn
             WHERE (fn.NUMERO LIKE ? OR UPPER(fn.NOME) LIKE UPPER(?))
               AND NOT EXISTS (
                    SELECT 1 FROM WMS_SEPARACAO s WHERE s.ORIGEM = 'N' AND s.ID_ORIGEM = fn.ID_FISCAL_NOTA
               )
             ORDER BY fn.DATA_EMISSAO DESC",
            [$termoLike, $termoLike]
        );
    }

    /** Importa um pedido de venda para uma lista de separação. */
    public function importarDePedidoVenda(int $idPedidoVenda, int $idUsuario): int
    {
        $this->db->beginTransaction();
        try {
            $pedido = $this->db->fetchOne("SELECT * FROM PEDIDO_VENDA WHERE ID_PEDIDO_VENDA = ?", [$idPedidoVenda]);
            if (!$pedido) {
                throw new InvalidArgumentException('Pedido de venda não encontrado.');
            }

            $this->db->execute(
                "INSERT INTO WMS_SEPARACAO
                    (ID_SEPARACAO, ORIGEM, ID_ORIGEM, NUMERO_DOCUMENTO, CLIENTE, DATA_PEDIDO, ID_USUARIO_IMPORTACAO)
                 VALUES (GEN_ID(GEN_WMS_SEPARACAO_ID, 1), 'P', ?, ?, ?, ?, ?)",
                [$idPedidoVenda, $pedido['NUMERO_DOCUMENTO'], $pedido['ID_CLIENTE'], $pedido['DATA_EMISSAO'], $idUsuario]
            );
            $idSeparacao = $this->db->currentGeneratorValue('GEN_WMS_SEPARACAO_ID');

            $itens = $this->db->fetchAll(
                "SELECT ID_PRODUTO, QUANTIDADE FROM PEDIDO_VENDA_ITEMS WHERE ID_SAP = ?",
                [$pedido['ID_SAP']]
            );
            foreach ($itens as $item) {
                $idProduto = (int) trim((string) $item['ID_PRODUTO']);
                if ($idProduto <= 0) {
                    continue;
                }
                $this->db->execute(
                    "INSERT INTO WMS_SEPARACAO_ITEMS (ID_ITEM, ID_SEPARACAO, ID_PRODUTO, QUANTIDADE_PEDIDO)
                     VALUES (GEN_ID(GEN_WMS_SEPARACAO_ITEM_ID, 1), ?, ?, ?)",
                    [$idSeparacao, $idProduto, $item['QUANTIDADE']]
                );
            }

            $this->db->commit();
            return $idSeparacao;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /** Importa uma nota fiscal para uma lista de separação. */
    public function importarDeNotaFiscal(int $idFiscalNota, int $idUsuario): int
    {
        $this->db->beginTransaction();
        try {
            $nota = $this->db->fetchOne("SELECT * FROM FISCAL_NOTA WHERE ID_FISCAL_NOTA = ?", [$idFiscalNota]);
            if (!$nota) {
                throw new InvalidArgumentException('Nota fiscal não encontrada.');
            }

            $this->db->execute(
                "INSERT INTO WMS_SEPARACAO
                    (ID_SEPARACAO, ORIGEM, ID_ORIGEM, NUMERO_DOCUMENTO, CLIENTE, DATA_PEDIDO, ID_USUARIO_IMPORTACAO)
                 VALUES (GEN_ID(GEN_WMS_SEPARACAO_ID, 1), 'N', ?, ?, ?, ?, ?)",
                [$idFiscalNota, $nota['NUMERO'], $nota['NOME'], $nota['DATA_EMISSAO'], $idUsuario]
            );
            $idSeparacao = $this->db->currentGeneratorValue('GEN_WMS_SEPARACAO_ID');

            $itens = $this->db->fetchAll(
                "SELECT CODIGO_PRODUTO, QUANTIDADE FROM FISCAL_NOTA_ITENS WHERE CHAVE = ?",
                [$nota['CHAVE']]
            );
            foreach ($itens as $item) {
                $idProduto = (int) trim((string) $item['CODIGO_PRODUTO']);
                if ($idProduto <= 0) {
                    continue;
                }
                $this->db->execute(
                    "INSERT INTO WMS_SEPARACAO_ITEMS (ID_ITEM, ID_SEPARACAO, ID_PRODUTO, QUANTIDADE_PEDIDO)
                     VALUES (GEN_ID(GEN_WMS_SEPARACAO_ITEM_ID, 1), ?, ?, ?)",
                    [$idSeparacao, $idProduto, (float) $item['QUANTIDADE']]
                );
            }

            $this->db->commit();
            return $idSeparacao;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function listar(?string $status = null, int $limite = 50): array
    {
        $sql = "SELECT ID_SEPARACAO, ORIGEM, NUMERO_DOCUMENTO, CLIENTE, DATA_PEDIDO, STATUS, DATA_IMPORTACAO
                FROM WMS_SEPARACAO";
        $params = [];
        if ($status !== null) {
            $sql .= " WHERE STATUS = ?";
            $params[] = $status;
        }
        $sql .= " ORDER BY DATA_IMPORTACAO DESC ROWS $limite";
        return $this->db->fetchAll($sql, $params);
    }

    public function detalhe(int $idSeparacao): ?array
    {
        return $this->db->fetchOne("SELECT * FROM WMS_SEPARACAO WHERE ID_SEPARACAO = ?", [$idSeparacao]);
    }

    public function itens(int $idSeparacao): array
    {
        return $this->db->fetchAll(
            "SELECT si.ID_ITEM, si.ID_PRODUTO, p.NOME AS PRODUTO_NOME, si.QUANTIDADE_PEDIDO,
                    si.QUANTIDADE_SEPARADA, (si.QUANTIDADE_PEDIDO - si.QUANTIDADE_SEPARADA) AS QUANTIDADE_PENDENTE
             FROM WMS_SEPARACAO_ITEMS si
             LEFT JOIN PRODUTO p ON p.ID_PRODUTO = si.ID_PRODUTO
             WHERE si.ID_SEPARACAO = ?
             ORDER BY si.ID_ITEM",
            [$idSeparacao]
        );
    }

    /** Onde o produto está disponível no WMS (pra sugerir de onde retirar). */
    public function posicoesDisponiveis(int $idProduto): array
    {
        return $this->db->fetchAll(
            "SELECT ep.ID_POSICAO, wp.CODIGO, ep.LOTE, ep.QUANTIDADE, ep.DATA_VALIDADE
             FROM WMS_ESTOQUE_POSICAO ep
             JOIN WMS_POSICAO wp ON wp.ID_POSICAO = ep.ID_POSICAO
             WHERE ep.ID_PRODUTO = ? AND ep.QUANTIDADE > 0
             ORDER BY ep.DATA_VALIDADE",
            [$idProduto]
        );
    }

    /**
     * Registra a retirada física de uma quantidade (de uma posição/lote) para
     * separar um item, dá baixa no estoque endereçado e atualiza o progresso.
     */
    public function retirar(int $idItem, int $idPosicao, ?string $lote, float $quantidade, int $idUsuario): void
    {
        $item = $this->db->fetchOne("SELECT * FROM WMS_SEPARACAO_ITEMS WHERE ID_ITEM = ?", [$idItem]);
        if (!$item) {
            throw new InvalidArgumentException('Item de separação não encontrado.');
        }
        $pendente = (float) $item['QUANTIDADE_PEDIDO'] - (float) $item['QUANTIDADE_SEPARADA'];
        if ($quantidade > $pendente) {
            throw new RuntimeException("Quantidade maior que o pendente ({$pendente}) para este item.");
        }

        $this->db->beginTransaction();
        try {
            $estoquePos = new EstoquePosicao();
            $estoquePos->darSaida(
                $idPosicao,
                (int) $item['ID_PRODUTO'],
                $lote,
                $quantidade,
                $idUsuario,
                'Separação #' . $item['ID_SEPARACAO'] . ' item ' . $idItem
            );

            $this->db->execute(
                "INSERT INTO WMS_SEPARACAO_RETIRADAS (ID_RETIRADA, ID_ITEM, ID_POSICAO, LOTE, QUANTIDADE, ID_USUARIO)
                 VALUES (GEN_ID(GEN_WMS_SEPARACAO_RETIRADA_ID, 1), ?, ?, ?, ?, ?)",
                [$idItem, $idPosicao, $lote, $quantidade, $idUsuario]
            );

            $this->db->execute(
                "UPDATE WMS_SEPARACAO_ITEMS SET QUANTIDADE_SEPARADA = QUANTIDADE_SEPARADA + ? WHERE ID_ITEM = ?",
                [$quantidade, $idItem]
            );

            $this->db->execute(
                "UPDATE WMS_SEPARACAO SET STATUS = 'SEPARANDO' WHERE ID_SEPARACAO = ? AND STATUS = 'AGUARDANDO'",
                [$item['ID_SEPARACAO']]
            );

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /** Marca a separação como concluída (só permite se todos os itens estiverem completos). */
    public function concluir(int $idSeparacao, int $idUsuario): void
    {
        $pendentes = $this->db->fetchOne(
            "SELECT COUNT(*) AS QTD FROM WMS_SEPARACAO_ITEMS
             WHERE ID_SEPARACAO = ? AND QUANTIDADE_SEPARADA < QUANTIDADE_PEDIDO",
            [$idSeparacao]
        );
        if ((int) ($pendentes['QTD'] ?? 0) > 0) {
            throw new RuntimeException('Ainda há itens não totalmente separados.');
        }

        $this->db->execute(
            "UPDATE WMS_SEPARACAO SET STATUS = 'SEPARADO', ID_USUARIO_CONCLUSAO = ?, DATA_CONCLUSAO = CURRENT_TIMESTAMP
             WHERE ID_SEPARACAO = ?",
            [$idUsuario, $idSeparacao]
        );
    }

    /** Separações concluídas, aguardando expedição (ainda sem registro em WMS_EXPEDICAO). */
    public function listarProntasParaExpedicao(): array
    {
        return $this->db->fetchAll(
            "SELECT s.ID_SEPARACAO, s.NUMERO_DOCUMENTO, s.CLIENTE, s.DATA_CONCLUSAO
             FROM WMS_SEPARACAO s
             WHERE s.STATUS = 'SEPARADO'
               AND NOT EXISTS (SELECT 1 FROM WMS_EXPEDICAO e WHERE e.ID_SEPARACAO = s.ID_SEPARACAO)
             ORDER BY s.DATA_CONCLUSAO"
        );
    }
}
