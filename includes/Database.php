<?php
require_once __DIR__ . '/../config/config.php';

/**
 * Camada fina de acesso ao Firebird.
 *
 * Prioriza PDO_FIREBIRD (extension=pdo_firebird no php.ini). Caso o PHP do
 * servidor não tenha esse driver disponível, cai para as funções nativas
 * ibase_* (extension=interbase / firebird), que ainda são muito comuns em
 * instalações Windows com o Firebird "clássico".
 *
 * Uso:
 *   $db = Database::getInstance();
 *   $rows = $db->fetchAll("SELECT * FROM WMS_POSICAO WHERE STATUS = ?", ['A']);
 *   $db->execute("INSERT INTO WMS_POSICAO (...) VALUES (...)", [...]);
 *   $id  = $db->lastGeneratorValue('GEN_WMS_POSICAO_ID');
 */
class Database
{
    private static ?Database $instance = null;
    private $conn;
    private string $driver; // 'pdo' ou 'ibase'

    private function __construct()
    {
        if (extension_loaded('pdo_firebird')) {
            $this->driver = 'pdo';
            $dsn = sprintf(
                'firebird:dbname=%s/%s:%s;charset=%s',
                DB_HOST,
                DB_PORT,
                DB_PATH,
                DB_CHARSET
            );
            $this->conn = new PDO($dsn, DB_USER, DB_PASS);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } elseif (extension_loaded('interbase')) {
            $this->driver = 'ibase';
            $connStr = DB_HOST . '/' . DB_PORT . ':' . DB_PATH;
            $this->conn = ibase_connect($connStr, DB_USER, DB_PASS, DB_CHARSET);
            if (!$this->conn) {
                throw new RuntimeException('Falha ao conectar via ibase: ' . ibase_errmsg());
            }
        } else {
            throw new RuntimeException(
                'Nenhuma extensão Firebird disponível no PHP (pdo_firebird ou interbase). ' .
                'Instale uma delas e habilite no php.ini.'
            );
        }
    }

    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /** Retorna todas as linhas de uma consulta como array associativo (chaves em maiúsculo, padrão Firebird). */
    public function fetchAll(string $sql, array $params = []): array
    {
        if ($this->driver === 'pdo') {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $stmt = ibase_prepare($this->conn, $sql);
        $result = empty($params) ? ibase_execute($stmt) : ibase_execute($stmt, ...$params);
        $rows = [];
        while ($row = ibase_fetch_assoc($result, IBASE_FIXED)) {
            $rows[] = array_map(fn($v) => is_string($v) ? rtrim($v) : $v, $row);
        }
        return $rows;
    }

    /** Retorna uma única linha ou null. */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $rows = $this->fetchAll($sql, $params);
        return $rows[0] ?? null;
    }

    /** Executa INSERT/UPDATE/DELETE. Retorna número de linhas afetadas quando disponível. */
    public function execute(string $sql, array $params = []): int
    {
        if ($this->driver === 'pdo') {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        }

        $stmt = ibase_prepare($this->conn, $sql);
        $result = empty($params) ? ibase_execute($stmt) : ibase_execute($stmt, ...$params);
        return $result ? 1 : 0;
    }

    /** Inicia transação. */
    public function beginTransaction(): void
    {
        if ($this->driver === 'pdo') {
            $this->conn->beginTransaction();
        } else {
            $this->conn = ibase_trans(IBASE_DEFAULT, $this->conn);
        }
    }

    public function commit(): void
    {
        if ($this->driver === 'pdo') {
            $this->conn->commit();
        } else {
            ibase_commit($this->conn);
        }
    }

    public function rollBack(): void
    {
        if ($this->driver === 'pdo') {
            $this->conn->rollBack();
        } else {
            ibase_rollback($this->conn);
        }
    }

    /** Lê o valor atual (sem incrementar) de um generator, útil para exibir prévia de código. */
    public function currentGeneratorValue(string $generatorName): int
    {
        $row = $this->fetchOne('SELECT GEN_ID(' . $generatorName . ', 0) AS VAL FROM RDB$DATABASE');
        return (int) ($row['VAL'] ?? 0);
    }
}
