<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/../config/config.php';

/**
 * Repositório para a hierarquia de endereçamento físico:
 *   WMS_ARMAZEM > WMS_CORREDOR (rua) > WMS_PREDIO (bloco) > WMS_POSICAO (nível+posição)
 *
 * WMS_POSICAO é a tabela nova (ver sql/001_wms_enderecos_qrcode.sql), sendo o
 * endereço final que carrega o texto impresso/gravado no QR Code (campo CODIGO).
 */
class Endereco
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ---------------------------------------------------------------
    // Armazém / Corredor / Prédio (tabelas já existentes no Geagro)
    // ---------------------------------------------------------------

    public function listarArmazens(): array
    {
        return $this->db->fetchAll(
            "SELECT ID_ARMAZEM, NOME, STATUS FROM WMS_ARMAZEM ORDER BY NOME"
        );
    }

    public function criarArmazem(string $nome, ?int $idEmpresa, ?int $idFilial): int
    {
        $this->db->execute(
            "INSERT INTO WMS_ARMAZEM (ID_ARMAZEM, NOME, ID_EMPRESA, ID_FILIAL, STATUS)
             VALUES (GEN_ID(GEN_WMS_ARMAZEM_ID, 1), ?, ?, ?, 'A')",
            [$nome, $idEmpresa, $idFilial]
        );
        return $this->db->currentGeneratorValue('GEN_WMS_ARMAZEM_ID');
    }

    public function listarCorredores(int $idArmazem): array
    {
        return $this->db->fetchAll(
            "SELECT ID_CORREDOR, NOME, SENTIDO, STATUS
             FROM WMS_CORREDOR WHERE ID_ARMAZEM = ? ORDER BY NOME",
            [$idArmazem]
        );
    }

    public function criarCorredor(int $idArmazem, string $nome, int $idFilial, string $sentido = 'C'): int
    {
        $this->db->execute(
            "INSERT INTO WMS_CORREDOR (ID_CORREDOR, NOME, SENTIDO, ID_FILIAL, ID_ARMAZEM, STATUS, DATA_INCLUSAO)
             VALUES (GEN_ID(GEN_WMS_CORREDOR_ID, 1), ?, ?, ?, ?, 'A', CURRENT_TIMESTAMP)",
            [$nome, $sentido, $idFilial, $idArmazem]
        );
        return $this->db->currentGeneratorValue('GEN_WMS_CORREDOR_ID');
    }

    public function listarPredios(int $idCorredor): array
    {
        return $this->db->fetchAll(
            "SELECT ID_PREDIO, NOME, QTD_NIVEIS, STATUS
             FROM WMS_PREDIO WHERE ID_CORREDOR = ? ORDER BY NOME",
            [$idCorredor]
        );
    }

    public function criarPredio(int $idArmazem, int $idCorredor, string $nome, int $idFilial, int $qtdNiveis): int
    {
        $this->db->execute(
            "INSERT INTO WMS_PREDIO (ID_PREDIO, NOME, ID_FILIAL, ID_ARMAZEM, ID_CORREDOR, QTD_NIVEIS, STATUS)
             VALUES (GEN_ID(GEN_WMS_PREDIO_ID, 1), ?, ?, ?, ?, ?, 'A')",
            [$nome, $idFilial, $idArmazem, $idCorredor, $qtdNiveis]
        );
        return $this->db->currentGeneratorValue('GEN_WMS_PREDIO_ID');
    }

    // ---------------------------------------------------------------
    // Posição (endereço final, com QR Code)
    // ---------------------------------------------------------------

    /** Detalhe completo de um prédio, incluindo corredor e armazém, para montar o código do QR. */
    public function detalhePredio(int $idPredio): ?array
    {
        return $this->db->fetchOne(
            "SELECT p.ID_PREDIO, p.NOME AS PREDIO_NOME, p.QTD_NIVEIS,
                    c.ID_CORREDOR, c.NOME AS CORREDOR_NOME,
                    a.ID_ARMAZEM, a.NOME AS ARMAZEM_NOME
             FROM WMS_PREDIO p
             JOIN WMS_CORREDOR c ON c.ID_CORREDOR = p.ID_CORREDOR
             JOIN WMS_ARMAZEM a ON a.ID_ARMAZEM = p.ID_ARMAZEM
             WHERE p.ID_PREDIO = ?",
            [$idPredio]
        );
    }

    /**
     * Gera o texto de código/QR de uma posição a partir dos componentes.
     * Ex.: AR01-CR02-PR03-N01-P05
     */
    public function montarCodigo(int $idArmazem, int $idCorredor, int $idPredio, int $nivel, int $posicao): string
    {
        return sprintf(
            'AR%02d-CR%02d-PR%02d-N%02d-P%02d',
            $idArmazem,
            $idCorredor,
            $idPredio,
            $nivel,
            $posicao
        );
    }

    /** Cria uma única posição. */
    public function criarPosicao(int $idPredio, int $nivel, int $posicao, string $codigo, ?float $capacidade = null): int
    {
        $this->db->execute(
            "INSERT INTO WMS_POSICAO (ID_POSICAO, ID_PREDIO, NIVEL, POSICAO, CODIGO, CAPACIDADE_MAXIMA, STATUS)
             VALUES (GEN_ID(GEN_WMS_POSICAO_ID, 1), ?, ?, ?, ?, ?, 'A')",
            [$idPredio, $nivel, $posicao, $codigo, $capacidade]
        );
        return $this->db->currentGeneratorValue('GEN_WMS_POSICAO_ID');
    }

    /**
     * Geração em lote: cria todas as posições de um prédio (níveis x posições por nível)
     * de uma só vez, já com o código pronto para impressão de etiquetas QR.
     * Retorna a lista de IDs criados.
     */
    public function gerarPosicoesEmLote(int $idPredio, int $posicoesPorNivel, ?float $capacidadePadrao = null): array
    {
        $predio = $this->detalhePredio($idPredio);
        if (!$predio) {
            throw new InvalidArgumentException('Prédio não encontrado.');
        }

        $criados = [];
        $this->db->beginTransaction();
        try {
            for ($nivel = 1; $nivel <= (int) $predio['QTD_NIVEIS']; $nivel++) {
                for ($pos = 1; $pos <= $posicoesPorNivel; $pos++) {
                    $codigo = $this->montarCodigo(
                        (int) $predio['ID_ARMAZEM'],
                        (int) $predio['ID_CORREDOR'],
                        (int) $predio['ID_PREDIO'],
                        $nivel,
                        $pos
                    );
                    $criados[] = [
                        'id' => $this->criarPosicao($idPredio, $nivel, $pos, $codigo, $capacidadePadrao),
                        'codigo' => $codigo,
                        'nivel' => $nivel,
                        'posicao' => $pos,
                    ];
                }
            }
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        return $criados;
    }

    public function listarPosicoes(int $idPredio): array
    {
        return $this->db->fetchAll(
            "SELECT ID_POSICAO, NIVEL, POSICAO, CODIGO, CAPACIDADE_MAXIMA, STATUS
             FROM WMS_POSICAO WHERE ID_PREDIO = ? ORDER BY NIVEL, POSICAO",
            [$idPredio]
        );
    }

    /** Busca posição pelo código lido no QR (com ou sem o prefixo LOC|). */
    public function buscarPorCodigo(string $codigoLido): ?array
    {
        $codigo = str_starts_with($codigoLido, 'LOC|') ? substr($codigoLido, 4) : $codigoLido;
        return $this->db->fetchOne(
            "SELECT wp.ID_POSICAO, wp.CODIGO, wp.NIVEL, wp.POSICAO, wp.STATUS, wp.CAPACIDADE_MAXIMA,
                    pr.NOME AS PREDIO_NOME, cr.NOME AS CORREDOR_NOME, ar.NOME AS ARMAZEM_NOME
             FROM WMS_POSICAO wp
             JOIN WMS_PREDIO pr ON pr.ID_PREDIO = wp.ID_PREDIO
             JOIN WMS_CORREDOR cr ON cr.ID_CORREDOR = pr.ID_CORREDOR
             JOIN WMS_ARMAZEM ar ON ar.ID_ARMAZEM = pr.ID_ARMAZEM
             WHERE wp.CODIGO = ?",
            [$codigo]
        );
    }

    /** Texto para gravar no QR Code (prefixo ajuda o leitor a diferenciar de um QR de produto). */
    public function textoQr(string $codigo): string
    {
        return 'LOC|' . $codigo;
    }
}
