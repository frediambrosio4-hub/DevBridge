<?php
declare(strict_types=1);

class Usuario
{
    public static function procurarEmail(string $email): ?array
    {
        $stmt = conexao()->prepare('SELECT * FROM usuarios WHERE lower(email) = lower(?)');
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }

    public static function procurarId(int $id): ?array
    {
        $stmt = conexao()->prepare('SELECT * FROM usuarios WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function criar(string $nome, string $email, string $senha, string $tipo): int
    {
        $stmt = conexao()->prepare(
            'INSERT INTO usuarios (nome, email, senha, tipo) VALUES (?, ?, ?, ?) RETURNING id'
        );
        $stmt->execute([$nome, $email, password_hash($senha, PASSWORD_DEFAULT), $tipo]);
        return (int) $stmt->fetchColumn();
    }

    public static function atualizarBasico(int $id, string $nome, string $descricao, string $localizacao): void
    {
        $stmt = conexao()->prepare('UPDATE usuarios SET nome = ?, descricao = ?, localizacao = ? WHERE id = ?');
        $stmt->execute([$nome, $descricao ?: null, $localizacao ?: null, $id]);
    }

    public static function atualizarFoto(int $id, array $arquivo): void
    {
        $db = conexao();
        $db->beginTransaction();
        try {
            $db->prepare('UPDATE usuarios SET foto_path = ?, foto_url = ? WHERE id = ?')
                ->execute([$arquivo['caminho'], $arquivo['caminho'], $id]);
            $db->prepare(
                'INSERT INTO fotos_perfil (usuario_id, nome_ficheiro, caminho, mime_type, tamanho)
                 VALUES (?, ?, ?, ?, ?)
                 ON CONFLICT (usuario_id) DO UPDATE SET nome_ficheiro = EXCLUDED.nome_ficheiro,
                 caminho = EXCLUDED.caminho, mime_type = EXCLUDED.mime_type, tamanho = EXCLUDED.tamanho,
                 data_upload = CURRENT_TIMESTAMP'
            )->execute([$id, $arquivo['nome'], $arquivo['caminho'], $arquivo['mime'], $arquivo['tamanho']]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function sessao(array $usuario): array
    {
        return [
            'id' => (int) $usuario['id'],
            'nome' => $usuario['nome'],
            'tipo' => $usuario['tipo'],
            'foto_path' => urlFicheiro($usuario['foto_path'] ?? $usuario['foto_url'] ?? null),
        ];
    }
}
