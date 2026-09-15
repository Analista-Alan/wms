<?php
require_once __DIR__ . '/Database.php';

/**
 * Autenticação simples reaproveitando a tabela USUARIO já existente no Geagro.
 * A tabela tem os campos SENHA (texto/hash legado) e SENHA_MD5 (CHAR(32)).
 * Por segurança, aqui validamos contra SENHA_MD5 = MD5(senha_informada).
 * Se sua base ainda usa senha em texto puro no campo SENHA, ajuste o método
 * verificarSenha() abaixo e planeje migrar para hashes fortes (password_hash).
 */
class Auth
{
    public static function login(string $usuario, string $senha): bool
    {
        $db = Database::getInstance();
        $row = $db->fetchOne(
            "SELECT ID_USUARIO, NOME, USUARIO, SENHA_MD5, ATIVO, ID_ARMAZENS
             FROM USUARIO
             WHERE UPPER(USUARIO) = UPPER(?)",
            [$usuario]
        );

        if (!$row) {
            return false;
        }

        if (isset($row['ATIVO']) && trim((string)$row['ATIVO']) === 'N') {
            return false;
        }

        if (!self::verificarSenha($senha, $row['SENHA_MD5'] ?? '')) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['usuario_id']       = (int) $row['ID_USUARIO'];
        $_SESSION['usuario_nome']     = trim($row['NOME'] ?? '');
        $_SESSION['usuario_login']    = trim($row['USUARIO'] ?? '');
        $_SESSION['usuario_armazens'] = trim($row['ID_ARMAZENS'] ?? '');
        $_SESSION['login_time']       = time();

        return true;
    }

    private static function verificarSenha(string $senhaDigitada, string $hashArmazenado): bool
    {
        $hashArmazenado = trim($hashArmazenado);
        if ($hashArmazenado === '') {
            return false;
        }
        return strtoupper(md5($senhaDigitada)) === strtoupper($hashArmazenado);
    }

    public static function checarSessao(): void
    {
        if (empty($_SESSION['usuario_id'])) {
            header('Location: ' . basePath() . '/login.php');
            exit;
        }

        $timeoutSeconds = SESSION_TIMEOUT_MINUTES * 60;
        if (time() - ($_SESSION['login_time'] ?? 0) > $timeoutSeconds) {
            self::logout();
            header('Location: ' . basePath() . '/login.php?expirado=1');
            exit;
        }
        $_SESSION['login_time'] = time();
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public static function usuarioId(): int
    {
        return (int) ($_SESSION['usuario_id'] ?? 0);
    }

    public static function usuarioNome(): string
    {
        return $_SESSION['usuario_nome'] ?? '';
    }
}
