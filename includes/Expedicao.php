<?php
require_once __DIR__ . '/Database.php';

/**
 * Módulo de Expedição — fecha uma separação já concluída, vinculando
 * transportadora / veículo / motorista já cadastrados no Geagro.
 */
class Expedicao
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function listarTransportadoras(): array
    {
        return $this->db->fetchAll("SELECT ID_TRANSPORTADORA, NOME FROM TRANSPORTADORA ORDER BY NOME");
    }

    public function listarVeiculos(): array
    {
        // Não filtra por ATIVO aqui de propósito: não temos certeza se o
        // valor usado no seu banco é 'S'/'N', '1'/'0' ou outra convenção.
        // Se quiser esconder veículos inativos, ajuste o WHERE conforme o
        // valor real da coluna VEICULO.ATIVO no seu banco.
        return $this->db->fetchAll("SELECT ID_VEICULO, NOME, PLACA FROM VEICULO ORDER BY NOME");
    }

    public function listarMotoristas(): array
    {
        return $this->db->fetchAll("SELECT ID_MOTORISTA, NOME FROM MOTORISTA ORDER BY NOME");
    }

    /** Cria a expedição de uma separação já concluída (status SEPARADO). */
    public function expedir(
        int $idSeparacao,
        ?int $idTransportadora,
        ?int $idVeiculo,
        ?int $idMotorista,
        ?string $numeroLacre,
        ?float $pesoTotal,
        string $observacao,
        int $idUsuario
    ): int {
        $this->db->beginTransaction();
        try {
            $separacao = $this->db->fetchOne("SELECT * FROM WMS_SEPARACAO WHERE ID_SEPARACAO = ?", [$idSeparacao]);
            if (!$separacao) {
                throw new InvalidArgumentException('Separação não encontrada.');
            }
            if ($separacao['STATUS'] !== 'SEPARADO') {
                throw new RuntimeException('Esta separação ainda não está concluída.');
            }

            $jaExpedida = $this->db->fetchOne("SELECT ID_EXPEDICAO FROM WMS_EXPEDICAO WHERE ID_SEPARACAO = ?", [$idSeparacao]);
            if ($jaExpedida) {
                throw new RuntimeException('Esta separação já foi expedida.');
            }

            $this->db->execute(
                "INSERT INTO WMS_EXPEDICAO
                    (ID_EXPEDICAO, ID_SEPARACAO, ID_TRANSPORTADORA, ID_VEICULO, ID_MOTORISTA, NUMERO_LACRE,
                     PESO_TOTAL, OBSERVACAO, ID_USUARIO, STATUS, DATA_EXPEDICAO)
                 VALUES (GEN_ID(GEN_WMS_EXPEDICAO_ID, 1), ?, ?, ?, ?, ?, ?, ?, ?, 'EXPEDIDO', CURRENT_TIMESTAMP)",
                [$idSeparacao, $idTransportadora, $idVeiculo, $idMotorista, $numeroLacre, $pesoTotal, $observacao, $idUsuario]
            );
            $idExpedicao = $this->db->currentGeneratorValue('GEN_WMS_EXPEDICAO_ID');

            $this->db->execute("UPDATE WMS_SEPARACAO SET STATUS = 'EXPEDIDO' WHERE ID_SEPARACAO = ?", [$idSeparacao]);

            $this->db->commit();
            return $idExpedicao;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function listar(int $limite = 50): array
    {
        return $this->db->fetchAll(
            "SELECT FIRST $limite e.ID_EXPEDICAO, e.DATA_EXPEDICAO, e.STATUS, e.NUMERO_LACRE, e.PESO_TOTAL,
                    s.NUMERO_DOCUMENTO, s.CLIENTE,
                    t.NOME AS TRANSPORTADORA_NOME, v.NOME AS VEICULO_NOME, v.PLACA, m.NOME AS MOTORISTA_NOME
             FROM WMS_EXPEDICAO e
             JOIN WMS_SEPARACAO s ON s.ID_SEPARACAO = e.ID_SEPARACAO
             LEFT JOIN TRANSPORTADORA t ON t.ID_TRANSPORTADORA = e.ID_TRANSPORTADORA
             LEFT JOIN VEICULO v ON v.ID_VEICULO = e.ID_VEICULO
             LEFT JOIN MOTORISTA m ON m.ID_MOTORISTA = e.ID_MOTORISTA
             ORDER BY e.DATA_EXPEDICAO DESC"
        );
    }
}
