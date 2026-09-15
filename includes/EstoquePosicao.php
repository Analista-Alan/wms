<?php
require_once __DIR__ . '/Database.php';

/**
 * Regras de negócio do estoque endereçado (WMS_ESTOQUE_POSICAO) e o log de
 * movimentações (WMS_MOVIMENTACAO). Toda operação que altera saldo passa por
 * aqui, dentro de transação, para manter saldo e log sempre coerentes.
 */
class EstoquePosicao
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** Saldo atual de um produto/lote numa posição específica (ou null se não endereçado ali). */
    private function saldoAtual(int $idPosicao, int $idProduto, ?string $lote): ?array
    {
        $sql = "SELECT ID_ESTOQUE_POSICAO, QUANTIDADE FROM WMS_ESTOQUE_POSICAO
                WHERE ID_POSICAO = ? AND ID_PRODUTO = ? AND " .
                ($lote === null ? "LOTE IS NULL" : "LOTE = ?");
        $params = $lote === null ? [$idPosicao, $idProduto] : [$idPosicao, $idProduto, $lote];
        return $this->db->fetchOne($sql, $params);
    }

    /**
     * Endereça (dá entrada) uma quantidade de produto/lote numa posição.
     * Se já existir saldo desse produto/lote naquela posição, soma; senão, cria a linha.
     */
    public function enderecar(int $idPosicao, int $idProduto, ?string $lote, float $quantidade, ?string $validade, int $idUsuario, ?int $idFilial = null, string $observacao = ''): void
    {
        if ($quantidade <= 0) {
            throw new InvalidArgumentException('Quantidade deve ser maior que zero.');
        }

        $this->db->beginTransaction();
        try {
            $existente = $this->saldoAtual($idPosicao, $idProduto, $lote);

            if ($existente) {
                $this->db->execute(
                    "UPDATE WMS_ESTOQUE_POSICAO SET QUANTIDADE = QUANTIDADE + ? WHERE ID_ESTOQUE_POSICAO = ?",
                    [$quantidade, $existente['ID_ESTOQUE_POSICAO']]
                );
            } else {
                $this->db->execute(
                    "INSERT INTO WMS_ESTOQUE_POSICAO
                        (ID_ESTOQUE_POSICAO, ID_POSICAO, ID_PRODUTO, LOTE, QUANTIDADE, DATA_VALIDADE, ID_FILIAL, ID_USUARIO_INCLUSAO)
                     VALUES (GEN_ID(GEN_WMS_ESTOQUE_POSICAO_ID, 1), ?, ?, ?, ?, ?, ?, ?)",
                    [$idPosicao, $idProduto, $lote, $quantidade, $validade, $idFilial, $idUsuario]
                );
            }

            $this->db->execute(
                "INSERT INTO WMS_MOVIMENTACAO
                    (ID_MOVIMENTACAO, TIPO, ID_PRODUTO, LOTE, QUANTIDADE, ID_POSICAO_ORIGEM, ID_POSICAO_DESTINO, ID_USUARIO, OBSERVACAO)
                 VALUES (GEN_ID(GEN_WMS_MOVIMENTACAO_ID, 1), 'E', ?, ?, ?, NULL, ?, ?, ?)",
                [$idProduto, $lote, $quantidade, $idPosicao, $idUsuario, $observacao]
            );

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Transfere quantidade de uma posição de origem para uma de destino.
     * Valida saldo suficiente na origem antes de mover.
     */
    public function transferir(int $idPosicaoOrigem, int $idPosicaoDestino, int $idProduto, ?string $lote, float $quantidade, int $idUsuario, string $observacao = ''): void
    {
        if ($idPosicaoOrigem === $idPosicaoDestino) {
            throw new InvalidArgumentException('Posição de origem e destino não podem ser iguais.');
        }
        if ($quantidade <= 0) {
            throw new InvalidArgumentException('Quantidade deve ser maior que zero.');
        }

        $this->db->beginTransaction();
        try {
            $origem = $this->saldoAtual($idPosicaoOrigem, $idProduto, $lote);
            if (!$origem || (float) $origem['QUANTIDADE'] < $quantidade) {
                throw new RuntimeException('Saldo insuficiente na posição de origem para essa transferência.');
            }

            $novoSaldoOrigem = (float) $origem['QUANTIDADE'] - $quantidade;
            if ($novoSaldoOrigem > 0) {
                $this->db->execute(
                    "UPDATE WMS_ESTOQUE_POSICAO SET QUANTIDADE = ? WHERE ID_ESTOQUE_POSICAO = ?",
                    [$novoSaldoOrigem, $origem['ID_ESTOQUE_POSICAO']]
                );
            } else {
                $this->db->execute(
                    "DELETE FROM WMS_ESTOQUE_POSICAO WHERE ID_ESTOQUE_POSICAO = ?",
                    [$origem['ID_ESTOQUE_POSICAO']]
                );
            }

            $destino = $this->saldoAtual($idPosicaoDestino, $idProduto, $lote);
            if ($destino) {
                $this->db->execute(
                    "UPDATE WMS_ESTOQUE_POSICAO SET QUANTIDADE = QUANTIDADE + ? WHERE ID_ESTOQUE_POSICAO = ?",
                    [$quantidade, $destino['ID_ESTOQUE_POSICAO']]
                );
            } else {
                $this->db->execute(
                    "INSERT INTO WMS_ESTOQUE_POSICAO
                        (ID_ESTOQUE_POSICAO, ID_POSICAO, ID_PRODUTO, LOTE, QUANTIDADE, ID_USUARIO_INCLUSAO)
                     VALUES (GEN_ID(GEN_WMS_ESTOQUE_POSICAO_ID, 1), ?, ?, ?, ?, ?)",
                    [$idPosicaoDestino, $idProduto, $lote, $quantidade, $idUsuario]
                );
            }

            $this->db->execute(
                "INSERT INTO WMS_MOVIMENTACAO
                    (ID_MOVIMENTACAO, TIPO, ID_PRODUTO, LOTE, QUANTIDADE, ID_POSICAO_ORIGEM, ID_POSICAO_DESTINO, ID_USUARIO, OBSERVACAO)
                 VALUES (GEN_ID(GEN_WMS_MOVIMENTACAO_ID, 1), 'T', ?, ?, ?, ?, ?, ?, ?)",
                [$idProduto, $lote, $quantidade, $idPosicaoOrigem, $idPosicaoDestino, $idUsuario, $observacao]
            );

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /** Baixa (saída/consumo) de estoque de uma posição, sem destino (ex.: expedição, perda). */
    public function darSaida(int $idPosicao, int $idProduto, ?string $lote, float $quantidade, int $idUsuario, string $observacao = ''): void
    {
        $this->db->beginTransaction();
        try {
            $origem = $this->saldoAtual($idPosicao, $idProduto, $lote);
            if (!$origem || (float) $origem['QUANTIDADE'] < $quantidade) {
                throw new RuntimeException('Saldo insuficiente para dar saída.');
            }

            $novoSaldo = (float) $origem['QUANTIDADE'] - $quantidade;
            if ($novoSaldo > 0) {
                $this->db->execute(
                    "UPDATE WMS_ESTOQUE_POSICAO SET QUANTIDADE = ? WHERE ID_ESTOQUE_POSICAO = ?",
                    [$novoSaldo, $origem['ID_ESTOQUE_POSICAO']]
                );
            } else {
                $this->db->execute(
                    "DELETE FROM WMS_ESTOQUE_POSICAO WHERE ID_ESTOQUE_POSICAO = ?",
                    [$origem['ID_ESTOQUE_POSICAO']]
                );
            }

            $this->db->execute(
                "INSERT INTO WMS_MOVIMENTACAO
                    (ID_MOVIMENTACAO, TIPO, ID_PRODUTO, LOTE, QUANTIDADE, ID_POSICAO_ORIGEM, ID_POSICAO_DESTINO, ID_USUARIO, OBSERVACAO)
                 VALUES (GEN_ID(GEN_WMS_MOVIMENTACAO_ID, 1), 'S', ?, ?, ?, ?, NULL, ?, ?)",
                [$idProduto, $lote, $quantidade, $idPosicao, $idUsuario, $observacao]
            );

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Ajusta o saldo de um produto/lote numa posição para um valor absoluto
     * (usado pelo módulo de Inventário, depois de uma contagem). Registra o
     * ajuste no log de movimentações como tipo 'A'.
     */
    public function ajustarQuantidade(int $idPosicao, int $idProduto, ?string $lote, float $quantidadeFinal, int $idUsuario, string $observacao = ''): void
    {
        $this->db->beginTransaction();
        try {
            $existente = $this->saldoAtual($idPosicao, $idProduto, $lote);
            $saldoAnterior = $existente ? (float) $existente['QUANTIDADE'] : 0.0;
            $diferenca = $quantidadeFinal - $saldoAnterior;

            if ($existente) {
                if ($quantidadeFinal > 0) {
                    $this->db->execute(
                        "UPDATE WMS_ESTOQUE_POSICAO SET QUANTIDADE = ? WHERE ID_ESTOQUE_POSICAO = ?",
                        [$quantidadeFinal, $existente['ID_ESTOQUE_POSICAO']]
                    );
                } else {
                    $this->db->execute(
                        "DELETE FROM WMS_ESTOQUE_POSICAO WHERE ID_ESTOQUE_POSICAO = ?",
                        [$existente['ID_ESTOQUE_POSICAO']]
                    );
                }
            } elseif ($quantidadeFinal > 0) {
                $this->db->execute(
                    "INSERT INTO WMS_ESTOQUE_POSICAO (ID_ESTOQUE_POSICAO, ID_POSICAO, ID_PRODUTO, LOTE, QUANTIDADE, ID_USUARIO_INCLUSAO)
                     VALUES (GEN_ID(GEN_WMS_ESTOQUE_POSICAO_ID, 1), ?, ?, ?, ?, ?)",
                    [$idPosicao, $idProduto, $lote, $quantidadeFinal, $idUsuario]
                );
            }

            if ($diferenca != 0) {
                $this->db->execute(
                    "INSERT INTO WMS_MOVIMENTACAO
                        (ID_MOVIMENTACAO, TIPO, ID_PRODUTO, LOTE, QUANTIDADE, ID_POSICAO_ORIGEM, ID_POSICAO_DESTINO, ID_USUARIO, OBSERVACAO)
                     VALUES (GEN_ID(GEN_WMS_MOVIMENTACAO_ID, 1), 'A', ?, ?, ?, ?, ?, ?, ?)",
                    [$idProduto, $lote, abs($diferenca), $idPosicao, $idPosicao, $idUsuario, $observacao . ' (de ' . $saldoAnterior . ' para ' . $quantidadeFinal . ')']
                );
            }

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /** Conteúdo (todos produtos/lotes) de uma posição. */
    public function conteudoDaPosicao(int $idPosicao): array
    {
        return $this->db->fetchAll(
            "SELECT ep.ID_ESTOQUE_POSICAO, ep.ID_PRODUTO, p.NOME AS PRODUTO_NOME,
                    ep.LOTE, ep.QUANTIDADE, ep.DATA_VALIDADE
             FROM WMS_ESTOQUE_POSICAO ep
             JOIN PRODUTO p ON p.ID_PRODUTO = ep.ID_PRODUTO
             WHERE ep.ID_POSICAO = ?
             ORDER BY p.NOME",
            [$idPosicao]
        );
    }

    /** Todas as posições onde um produto está endereçado (para localizar rapidamente). */
    public function posicoesDoProduto(int $idProduto): array
    {
        return $this->db->fetchAll(
            "SELECT ep.LOTE, ep.QUANTIDADE, ep.DATA_VALIDADE,
                    wp.CODIGO, wp.NIVEL, wp.POSICAO,
                    pr.NOME AS PREDIO_NOME, cr.NOME AS CORREDOR_NOME, ar.NOME AS ARMAZEM_NOME
             FROM WMS_ESTOQUE_POSICAO ep
             JOIN WMS_POSICAO wp ON wp.ID_POSICAO = ep.ID_POSICAO
             JOIN WMS_PREDIO pr ON pr.ID_PREDIO = wp.ID_PREDIO
             JOIN WMS_CORREDOR cr ON cr.ID_CORREDOR = pr.ID_CORREDOR
             JOIN WMS_ARMAZEM ar ON ar.ID_ARMAZEM = pr.ID_ARMAZEM
             WHERE ep.ID_PRODUTO = ?
             ORDER BY ar.NOME, cr.NOME, pr.NOME, wp.NIVEL, wp.POSICAO",
            [$idProduto]
        );
    }

    /** Últimas movimentações, para tela de auditoria/histórico. */
    public function ultimasMovimentacoes(int $limite = 100): array
    {
        return $this->db->fetchAll(
            "SELECT FIRST $limite m.ID_MOVIMENTACAO, m.TIPO, m.QUANTIDADE, m.LOTE, m.DATA_MOVIMENTACAO,
                    p.NOME AS PRODUTO_NOME,
                    wo.CODIGO AS CODIGO_ORIGEM, wd.CODIGO AS CODIGO_DESTINO,
                    u.NOME AS USUARIO_NOME
             FROM WMS_MOVIMENTACAO m
             JOIN PRODUTO p ON p.ID_PRODUTO = m.ID_PRODUTO
             LEFT JOIN WMS_POSICAO wo ON wo.ID_POSICAO = m.ID_POSICAO_ORIGEM
             LEFT JOIN WMS_POSICAO wd ON wd.ID_POSICAO = m.ID_POSICAO_DESTINO
             LEFT JOIN USUARIO u ON u.ID_USUARIO = m.ID_USUARIO
             ORDER BY m.DATA_MOVIMENTACAO DESC"
        );
    }
}
