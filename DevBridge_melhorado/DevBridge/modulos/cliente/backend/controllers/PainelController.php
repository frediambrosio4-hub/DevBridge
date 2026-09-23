<?php
declare(strict_types=1);

class PainelController
{
    public function dashboard(): void
    {
        $usuario = exigirLogin();
        $db = conexao();
        $stats = [];
        if ($usuario['tipo'] === 'CLIENTE') {
            $stats['projetos_publicados'] = $this->contar('SELECT COUNT(*) FROM projetos WHERE cliente_id = ?', [$usuario['id']]);
            $stats['propostas_recebidas'] = $this->contar('SELECT COUNT(*) FROM propostas pr JOIN projetos p ON p.id = pr.projeto_id WHERE p.cliente_id = ?', [$usuario['id']]);
            $stats['contratacoes'] = $this->contar('SELECT COUNT(*) FROM contratacoes WHERE cliente_id = ?', [$usuario['id']]);
            $stats['em_andamento'] = $this->contar("SELECT COUNT(*) FROM projetos WHERE cliente_id = ? AND status = 'EM_ANDAMENTO'", [$usuario['id']]);
        } else {
            $perfil = Profissional::garantir((int) $usuario['id'], $usuario['tipo']);
            $stats['projetos_disponiveis'] = $this->contar("SELECT COUNT(*) FROM projetos WHERE status IN ('PUBLICADO', 'RECEBENDO_PROPOSTAS')", []);
            $stats['propostas_enviadas'] = $this->contar('SELECT COUNT(*) FROM propostas WHERE profissional_id = ?', [$perfil['id']]);
            $stats['contratos'] = $this->contar('SELECT COUNT(*) FROM contratacoes WHERE profissional_id = ?', [$perfil['id']]);
            $stats['avaliacao_media'] = $this->contar('SELECT COALESCE(ROUND(AVG(nota)::numeric, 1), 0) FROM avaliacoes WHERE avaliado_id = ?', [$usuario['id']]);
        }
        resposta(['tipo' => $usuario['tipo'], 'stats' => $stats]);
    }

    public function profissionais(): void
    {
        exigirTipo(['CLIENTE']);
        $f = $_GET;
        $params = [];
        $where = ["u.tipo IN ('PROGRAMADOR', 'TECNICO_REDES')"];
        $tipo = strtoupper(texto($f['tipo'] ?? '', 30));
        if (in_array($tipo, ['PROGRAMADOR', 'TECNICO_REDES'], true)) {
            $where[] = 'u.tipo = ?';
            $params[] = $tipo;
        }
        $localizacao = texto($f['localizacao'] ?? '', 150);
        if ($localizacao !== '') {
            $where[] = "LOWER(COALESCE(p.localizacao, u.localizacao, '')) LIKE LOWER(?)";
            $params[] = '%' . $localizacao . '%';
        }
        $termo = texto($f['q'] ?? $f['competencia'] ?? '', 100);
        if ($termo !== '') {
            $where[] = '(LOWER(u.nome) LIKE LOWER(?) OR EXISTS (SELECT 1 FROM profissional_competencia pc JOIN competencias c ON c.id = pc.competencia_id WHERE pc.profissional_id = p.id AND LOWER(c.nome) LIKE LOWER(?)) OR EXISTS (SELECT 1 FROM profissional_linguagem pl JOIN linguagens_programacao l ON l.id = pl.linguagem_id WHERE pl.profissional_id = p.id AND LOWER(l.nome) LIKE LOWER(?)) OR EXISTS (SELECT 1 FROM profissional_especializacao pe JOIN especializacoes_rede e ON e.id = pe.especializacao_id WHERE pe.profissional_id = p.id AND LOWER(e.nome) LIKE LOWER(?)))';
            for ($i = 0; $i < 4; $i++) {
                $params[] = '%' . $termo . '%';
            }
        }
        $sql = 'SELECT p.id, p.tipo_profissional, p.descricao AS descricao_profissional, p.localizacao, p.experiencia,
                u.id AS usuario_id, u.nome, u.email, COALESCE(u.foto_path, u.foto_url) AS foto_path,
                COALESCE((SELECT ROUND(AVG(a.nota)::numeric, 1) FROM avaliacoes a WHERE a.avaliado_id = u.id), 0) AS avaliacao
                FROM profissionais p JOIN usuarios u ON u.id = p.usuario_id WHERE ' . implode(' AND ', $where) . ' ORDER BY avaliacao DESC, u.nome';
        $stmt = conexao()->prepare($sql);
        $stmt->execute($params);
        resposta(['profissionais' => $stmt->fetchAll()]);
    }

    private function contar(string $sql, array $params): int|float
    {
        $stmt = conexao()->prepare($sql);
        $stmt->execute($params);
        $valor = $stmt->fetchColumn();
        return is_numeric($valor) && floor((float) $valor) !== (float) $valor ? (float) $valor : (int) $valor;
    }
}
