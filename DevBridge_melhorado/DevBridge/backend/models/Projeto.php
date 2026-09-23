<?php
declare(strict_types=1);

class Projeto
{
    public static function listar(array $usuario, array $filtros = []): array
    {
        $db = conexao();
        $params = [];
        $where = [];
        if ($usuario['tipo'] === 'CLIENTE') {
            $where[] = 'p.cliente_id = ?';
            $params[] = $usuario['id'];
        } else {
            $where[] = "p.status IN ('PUBLICADO', 'RECEBENDO_PROPOSTAS')";
        }
        foreach (['categoria' => 'p.categoria', 'localizacao' => 'p.localizacao'] as $chave => $campo) {
            $valor = texto($filtros[$chave] ?? '', 100);
            if ($valor !== '') {
                $where[] = "LOWER(COALESCE({$campo}, '')) LIKE LOWER(?)";
                $params[] = '%' . $valor . '%';
            }
        }
        $busca = texto($filtros['q'] ?? '', 120);
        if ($busca !== '') {
            $where[] = '(LOWER(p.titulo) LIKE LOWER(?) OR LOWER(p.descricao) LIKE LOWER(?))';
            $params[] = '%' . $busca . '%';
            $params[] = '%' . $busca . '%';
        }
        $orcamentoMax = filter_var($filtros['orcamento_max'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($orcamentoMax !== false && $orcamentoMax > 0) {
            $where[] = 'p.orcamento <= ?';
            $params[] = $orcamentoMax;
        }
        $competencia = texto($filtros['competencia'] ?? '', 100);
        if ($competencia !== '') {
            $where[] = 'EXISTS (SELECT 1 FROM projeto_competencia pc JOIN competencias c ON c.id = pc.competencia_id WHERE pc.projeto_id = p.id AND LOWER(c.nome) LIKE LOWER(?))';
            $params[] = '%' . $competencia . '%';
        }

        $sql = "SELECT p.*, u.nome AS cliente_nome, u.foto_path AS cliente_foto,
                (SELECT caminho FROM projeto_anexos pa WHERE pa.projeto_id = p.id AND pa.mime_type LIKE 'image/%' ORDER BY pa.id LIMIT 1) AS imagem_capa,
                (SELECT COUNT(*) FROM propostas pr WHERE pr.projeto_id = p.id) AS total_propostas
                FROM projetos p JOIN usuarios u ON u.id = p.cliente_id
                WHERE " . implode(' AND ', $where) . ' ORDER BY p.data_publicacao DESC';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function encontrar(int $id): ?array
    {
        $db = conexao();
        $stmt = $db->prepare(
            'SELECT p.*, u.nome AS cliente_nome, u.foto_path AS cliente_foto
             FROM projetos p JOIN usuarios u ON u.id = p.cliente_id WHERE p.id = ?'
        );
        $stmt->execute([$id]);
        $projeto = $stmt->fetch() ?: null;
        if (!$projeto) {
            return null;
        }
        $stmt = $db->prepare('SELECT c.nome FROM projeto_competencia pc JOIN competencias c ON c.id = pc.competencia_id WHERE pc.projeto_id = ? ORDER BY c.nome');
        $stmt->execute([$id]);
        $projeto['competencias'] = array_column($stmt->fetchAll(), 'nome');
        $stmt = $db->prepare('SELECT id, nome_ficheiro, caminho, mime_type, tamanho FROM projeto_anexos WHERE projeto_id = ? ORDER BY id');
        $stmt->execute([$id]);
        $projeto['anexos'] = $stmt->fetchAll();
        return $projeto;
    }

    public static function criar(array $dados, int $clienteId): int
    {
        $db = conexao();
        $stmt = $db->prepare(
            "INSERT INTO projetos (cliente_id, titulo, descricao, categoria, setor, orcamento, prazo, localizacao, tipo_trabalho, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'RECEBENDO_PROPOSTAS') RETURNING id"
        );
        $stmt->execute([
            $clienteId, $dados['titulo'], $dados['descricao'], $dados['categoria'], $dados['categoria'], $dados['orcamento'],
            $dados['prazo'], $dados['localizacao'] ?: null, $dados['tipo_trabalho'],
        ]);
        $id = (int) $stmt->fetchColumn();
        self::sincronizarCompetencias($id, $dados['competencias']);
        return $id;
    }

    public static function editar(int $id, array $dados, int $clienteId): bool
    {
        $db = conexao();
        $stmt = $db->prepare(
            "UPDATE projetos SET titulo = ?, descricao = ?, categoria = ?, setor = ?, orcamento = ?, prazo = ?,
             localizacao = ?, tipo_trabalho = ? WHERE id = ? AND cliente_id = ?
             AND status IN ('PUBLICADO', 'RECEBENDO_PROPOSTAS')"
        );
        $stmt->execute([
            $dados['titulo'], $dados['descricao'], $dados['categoria'], $dados['categoria'], $dados['orcamento'], $dados['prazo'],
            $dados['localizacao'] ?: null, $dados['tipo_trabalho'], $id, $clienteId,
        ]);
        if ($stmt->rowCount() < 1) {
            return false;
        }
        self::sincronizarCompetencias($id, $dados['competencias']);
        return true;
    }

    public static function cancelar(int $id, int $clienteId): bool
    {
        $stmt = conexao()->prepare(
            "UPDATE projetos SET status = 'CANCELADO' WHERE id = ? AND cliente_id = ?
             AND status IN ('PUBLICADO', 'RECEBENDO_PROPOSTAS', 'PROFISSIONAL_ESCOLHIDO')"
        );
        $stmt->execute([$id, $clienteId]);
        return $stmt->rowCount() > 0;
    }

    public static function pertenceAoCliente(int $id, int $clienteId): bool
    {
        $stmt = conexao()->prepare('SELECT 1 FROM projetos WHERE id = ? AND cliente_id = ?');
        $stmt->execute([$id, $clienteId]);
        return (bool) $stmt->fetchColumn();
    }

    public static function adicionarAnexo(int $projetoId, array $arquivo): void
    {
        conexao()->prepare(
            'INSERT INTO projeto_anexos (projeto_id, nome_ficheiro, caminho, mime_type, tamanho) VALUES (?, ?, ?, ?, ?)'
        )->execute([$projetoId, $arquivo['nome'], $arquivo['caminho'], $arquivo['mime'], $arquivo['tamanho']]);
    }

    private static function sincronizarCompetencias(int $projetoId, array $competencias): void
    {
        $db = conexao();
        $db->prepare('DELETE FROM projeto_competencia WHERE projeto_id = ?')->execute([$projetoId]);
        $inserir = $db->prepare('INSERT INTO projeto_competencia (projeto_id, competencia_id) VALUES (?, ?)');
        foreach (array_unique($competencias) as $nome) {
            $nome = texto($nome, 100);
            if ($nome === '') {
                continue;
            }
            $buscar = $db->prepare('INSERT INTO competencias (nome) VALUES (?) ON CONFLICT (nome) DO UPDATE SET nome = EXCLUDED.nome RETURNING id');
            $buscar->execute([$nome]);
            $inserir->execute([$projetoId, $buscar->fetchColumn()]);
        }
    }
}
