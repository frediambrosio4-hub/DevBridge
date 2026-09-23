<?php
declare(strict_types=1);

class UsuarioController
{
    public function registo(): void
    {
        $entrada = dados();
        $nome = texto($entrada['nome'] ?? '', 100);
        $email = strtolower(texto($entrada['email'] ?? '', 150));
        $senha = (string) ($entrada['senha'] ?? '');
        $tipo = strtoupper(texto($entrada['tipo'] ?? '', 30));
        if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($senha) < 8) {
            resposta(['erro' => 'Informe nome, email válido e uma palavra-passe com pelo menos 8 caracteres.'], 422);
        }
        if (!in_array($tipo, ['CLIENTE', 'PROGRAMADOR', 'TECNICO_REDES'], true)) {
            resposta(['erro' => 'Tipo de conta inválido.'], 422);
        }
        if (Usuario::procurarEmail($email)) {
            resposta(['erro' => 'Este email já está registado.'], 409);
        }

        $db = conexao();
        try {
            $db->beginTransaction();
            $id = Usuario::criar($nome, $email, $senha, $tipo);
            if ($tipo !== 'CLIENTE') {
                Profissional::garantir($id, $tipo);
            }
            $db->commit();
            resposta(['id' => $id, 'mensagem' => 'Conta criada com sucesso.'], 201);
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            resposta(['erro' => 'Não foi possível criar a conta.'], 500);
        }
    }

    public function login(): void
    {
        $entrada = dados();
        $email = strtolower(texto($entrada['email'] ?? '', 150));
        $senha = (string) ($entrada['senha'] ?? '');
        $usuario = Usuario::procurarEmail($email);
        if (!$usuario || !password_verify($senha, (string) $usuario['senha'])) {
            resposta(['erro' => 'Email ou palavra-passe incorretos.'], 401);
        }
        if (in_array($usuario['tipo'], ['PROGRAMADOR', 'TECNICO_REDES'], true)) {
            Profissional::garantir((int) $usuario['id'], $usuario['tipo']);
        }
        session_regenerate_id(true);
        $_SESSION['usuario'] = Usuario::sessao($usuario);
        tokenCsrf();
        resposta(['usuario' => $_SESSION['usuario'], 'destino' => self::destino($usuario['tipo'])]);
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $parametros = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $parametros['path'], $parametros['domain'], (bool) $parametros['secure'], (bool) $parametros['httponly']);
        }
        session_destroy();
        resposta(['mensagem' => 'Sessão terminada.']);
    }

    public function me(): void
    {
        $sessao = exigirLogin();
        $atual = Usuario::procurarId((int) $sessao['id']);
        if (!$atual) {
            $_SESSION = [];
            resposta(['erro' => 'Utilizador não encontrado.'], 401);
        }
        $_SESSION['usuario'] = Usuario::sessao($atual);
        resposta(['usuario' => $_SESSION['usuario']]);
    }

    public function csrf(): void
    {
        resposta(['csrf' => tokenCsrf()]);
    }

    private static function destino(string $tipo): string
    {
        $destinos = [
            'CLIENTE' => 'modulos/cliente/frontend/dashboard.html',
            'PROGRAMADOR' => 'modulos/programador/frontend/dashboard.html',
            'TECNICO_REDES' => 'modulos/rede/frontend/dashboard.html',
            'ADMINISTRADOR' => 'modulos/acesso/frontend/index.html',
        ];
        return $destinos[$tipo] ?? $destinos['CLIENTE'];
    }
}
