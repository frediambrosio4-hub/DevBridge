<?php
declare(strict_types=1);

class Profissional
{
    public static function procurarPorUsuario(int $usuarioId): ?array
    {
        $stmt = conexao()->prepare('SELECT * FROM profissionais WHERE usuario_id = ?');
        $stmt->execute([$usuarioId]);
        return $stmt->fetch() ?: null;
    }

    public static function garantir(int $usuarioId, string $tipo): array
    {
        $perfil = self::procurarPorUsuario($usuarioId);
        if ($perfil) {
            return $perfil;
        }
        $stmt = conexao()->prepare(
            'INSERT INTO profissionais (usuario_id, tipo_profissional) VALUES (?, ?) RETURNING *'
        );
        $stmt->execute([$usuarioId, $tipo]);
        return $stmt->fetch();
    }

    public static function linguagens(int $profissionalId): array
    {
        $stmt = conexao()->prepare(
            'SELECT l.id, l.nome, l.icone, l.logo_url, pl.nivel
             FROM profissional_linguagem pl JOIN linguagens_programacao l ON l.id = pl.linguagem_id
             WHERE pl.profissional_id = ? ORDER BY l.nome'
        );
        $stmt->execute([$profissionalId]);
        return $stmt->fetchAll();
    }

    public static function especializacoes(int $profissionalId): array
    {
        $stmt = conexao()->prepare(
            'SELECT e.id, e.nome, e.icone FROM profissional_especializacao pe
             JOIN especializacoes_rede e ON e.id = pe.especializacao_id
             WHERE pe.profissional_id = ? ORDER BY e.nome'
        );
        $stmt->execute([$profissionalId]);
        return $stmt->fetchAll();
    }

    public static function competencias(int $profissionalId): array
    {
        $stmt = conexao()->prepare(
            'SELECT c.id, c.nome FROM profissional_competencia pc
             JOIN competencias c ON c.id = pc.competencia_id
             WHERE pc.profissional_id = ? ORDER BY c.nome'
        );
        $stmt->execute([$profissionalId]);
        return $stmt->fetchAll();
    }

    public static function atualizar(int $usuarioId, string $tipo, array $dados): void
    {
        $db = conexao();
        $perfil = self::garantir($usuarioId, $tipo);
        $db->prepare(
            'UPDATE profissionais SET descricao = ?, localizacao = ?, experiencia = ? WHERE id = ?'
        )->execute([$dados['descricao_profissional'] ?: null, $dados['localizacao'] ?: null, $dados['experiencia'] ?: null, $perfil['id']]);

        if ($tipo === 'PROGRAMADOR') {
            $db->prepare('DELETE FROM profissional_linguagem WHERE profissional_id = ?')->execute([$perfil['id']]);
            $insert = $db->prepare('INSERT INTO profissional_linguagem (profissional_id, linguagem_id, nivel) VALUES (?, ?, ?)');
            foreach ($dados['linguagens'] as $item) {
                $id = inteiro(is_array($item) ? ($item['id'] ?? 0) : $item);
                $nivel = is_array($item) ? texto($item['nivel'] ?? 'Intermédio', 30) : 'Intermédio';
                if ($id > 0 && in_array($nivel, ['Iniciante', 'Intermédio', 'Avançado'], true)) {
                    $insert->execute([$perfil['id'], $id, $nivel]);
                }
            }
        }
        if ($tipo === 'TECNICO_REDES') {
            $db->prepare('DELETE FROM profissional_especializacao WHERE profissional_id = ?')->execute([$perfil['id']]);
            $insert = $db->prepare('INSERT INTO profissional_especializacao (profissional_id, especializacao_id) VALUES (?, ?)');
            foreach ($dados['especializacoes'] as $id) {
                $id = inteiro($id);
                if ($id > 0) {
                    $insert->execute([$perfil['id'], $id]);
                }
            }
        }

        $db->prepare('DELETE FROM profissional_competencia WHERE profissional_id = ?')->execute([$perfil['id']]);
        $insertCompetencia = $db->prepare('INSERT INTO profissional_competencia (profissional_id, competencia_id) VALUES (?, ?)');
        foreach ($dados['competencias'] as $nome) {
            $nome = texto($nome, 100);
            if ($nome === '') {
                continue;
            }
            $stmt = $db->prepare('INSERT INTO competencias (nome) VALUES (?) ON CONFLICT (nome) DO UPDATE SET nome = EXCLUDED.nome RETURNING id');
            $stmt->execute([$nome]);
            $insertCompetencia->execute([$perfil['id'], $stmt->fetchColumn()]);
        }
    }
}
