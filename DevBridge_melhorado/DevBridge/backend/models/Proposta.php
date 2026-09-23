<?php
declare(strict_types=1);

class Proposta
{
    public static function criar(array $dados, int $profissionalId, int $usuarioId): int
    {
        $db = conexao();
        $stmt = $db->prepare(
            "SELECT id FROM projetos WHERE id = ? AND cliente_id <> ?
             AND status IN ('PUBLICADO', 'RECEBENDO_PROPOSTAS')"
        );
        $stmt->execute([$dados['projeto_id'], $usuarioId]);
        if (!$stmt->fetchColumn()) {
            throw new RuntimeException('Este projeto não está disponível para proposta.');
        }
        $stmt = $db->prepare(
            'INSERT INTO propostas (projeto_id, profissional_id, valor, prazo, mensagem, descricao_solucao)
             VALUES (?, ?, ?, ?, ?, ?) RETURNING id'
        );
        $stmt->execute([
            $dados['projeto_id'], $profissionalId, $dados['valor'], $dados['prazo'], $dados['mensagem'],
            $dados['descricao_solucao'] ?: null,
        ]);
        return (int) $stmt->fetchColumn();
    }

    public static function listar(array $usuario): array
    {
        $db = conexao();
        if ($usuario['tipo'] === 'CLIENTE') {
            $sql = "SELECT pr.*, p.titulo AS projeto_titulo, u.nome AS profissional_nome, u.foto_path,
                    f.tipo_profissional, f.localizacao, f.descricao AS profissional_descricao,
                    COALESCE((SELECT ROUND(AVG(a.nota)::numeric, 1) FROM avaliacoes a WHERE a.avaliado_id = u.id), 0) AS avaliacao,
                    COALESCE((SELECT string_agg(l.nome, ', ') FROM profissional_linguagem pl JOIN linguagens_programacao l ON l.id = pl.linguagem_id WHERE pl.profissional_id = f.id),
                    (SELECT string_agg(e.nome, ', ') FROM profissional_especializacao pe JOIN especializacoes_rede e ON e.id = pe.especializacao_id WHERE pe.profissional_id = f.id), '') AS especialidades
                    FROM propostas pr JOIN projetos p ON p.id = pr.projeto_id JOIN profissionais f ON f.id = pr.profissional_id
                    JOIN usuarios u ON u.id = f.usuario_id WHERE p.cliente_id = ? ORDER BY pr.data_envio DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute([$usuario['id']]);
        } else {
            $sql = 'SELECT pr.*, p.titulo AS projeto_titulo, p.cliente_id FROM propostas pr
                    JOIN profissionais f ON f.id = pr.profissional_id JOIN projetos p ON p.id = pr.projeto_id
                    WHERE f.usuario_id = ? ORDER BY pr.data_envio DESC';
            $stmt = $db->prepare($sql);
            $stmt->execute([$usuario['id']]);
        }
        return $stmt->fetchAll();
    }

    public static function recusar(int $id, int $clienteId): bool
    {
        $stmt = conexao()->prepare(
            "UPDATE propostas pr SET status = 'RECUSADA' FROM projetos p
             WHERE pr.projeto_id = p.id AND pr.id = ? AND p.cliente_id = ?
             AND pr.status IN ('ENVIADA', 'VISUALIZADA', 'EM_NEGOCIACAO')"
        );
        $stmt->execute([$id, $clienteId]);
        return $stmt->rowCount() > 0;
    }

    public static function pertenceAoProfissional(int $propostaId, int $usuarioId): bool
    {
        $stmt = conexao()->prepare(
            'SELECT 1 FROM propostas pr JOIN profissionais p ON p.id = pr.profissional_id
             WHERE pr.id = ? AND p.usuario_id = ?'
        );
        $stmt->execute([$propostaId, $usuarioId]);
        return (bool) $stmt->fetchColumn();
    }

    public static function adicionarAnexo(int $propostaId, array $arquivo): void
    {
        conexao()->prepare(
            'INSERT INTO proposta_anexos (proposta_id, nome_ficheiro, caminho, mime_type, tamanho) VALUES (?, ?, ?, ?, ?)'
        )->execute([$propostaId, $arquivo['nome'], $arquivo['caminho'], $arquivo['mime'], $arquivo['tamanho']]);
    }
}
