<?php
/**
 * TEMPLATE de configuração — copie este arquivo para config.php e preencha
 * com os dados reais do seu ambiente. O config.php real NUNCA deve ir para
 * o Git (já está no .gitignore) porque contém a senha do banco.
 */

// --- Conexão com o Firebird (banco Geagro) ---------------------------------
define('DB_HOST', '172.16.10.90');
define('DB_PORT', '3050');
define('DB_PATH', 'C:\\Sync\\Banco\\Geagro\\BANCO.FDB'); // caminho no SERVIDOR firebird
define('DB_USER', 'SYSDBA');
define('DB_PASS', 'TROQUE_ESTA_SENHA');
define('DB_CHARSET', 'WIN1252');

// --- Sessão / segurança ------------------------------------------------------
define('APP_NAME', 'WMS Geagro');
define('SESSION_TIMEOUT_MINUTES', 480);

// --- Regras de endereçamento --------------------------------------------------
define('MASCARA_ENDERECO', 'AR%A-CR%C-PR%P-N%N-P%S');

date_default_timezone_set('America/Sao_Paulo');

error_reporting(E_ALL);
ini_set('display_errors', '0'); // mude para '1' temporariamente se precisar depurar

/**
 * Calcula automaticamente o "prefixo" de URL sob o qual as páginas de
 * public/ estão sendo servidas (funciona em qualquer estrutura de pastas).
 */
function basePath(): string
{
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = str_replace('\\', '/', dirname($scriptName));
    return $dir === '/' ? '' : rtrim($dir, '/');
}
