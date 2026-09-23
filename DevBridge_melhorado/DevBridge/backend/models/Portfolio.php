<?php
declare(strict_types=1);

class Portfolio
{
    public static function listar(int $profissionalId): array
    {
        $stmt = conexao()->prepare(
            "SELECT p.*, COALESCE(json_agg(json_build_object('id', pi.id, 'caminho', pi.caminho, 'mime_type', pi.mime_type) ORDER BY pi.id)
             FILTER (WHERE pi.id IS NOT NULL), '[]') AS imagens
             FROM portfolio p LEFT JOIN portfolio_imagens pi ON pi.portfolio_id = p.id
             WHERE p.profissional_id = ? GROUP BY p.id ORDER BY p.data_realizacao DESC NULLS LAST, p.id DESC"
        );
        $stmt->execute([$profissionalId]);
        $items = $stmt->fetchAll();
        foreach ($items as &$item) {
            $item['imagens'] = json_decode($item['imagens'], true) ?: [];
        }
        return $items;
    }

    public static function criar(int $profissionalId, array $dados): int
    {
        $stmt = conexao()->prepare(
            'INSERT INTO portfolio (profissional_id, titulo, descricao, tecnologias, area, equipamentos, link_projeto, link_github, data_realizacao, observacoes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id'
        );
        $stmt->execute([
            $profissionalId, $dados['titulo'], $dados['descricao'] ?: null, $dados['tecnologias'] ?: null,
            $dados['area'] ?: null, $dados['equipamentos'] ?: null, $dados['link_projeto'] ?: null,
            $dados['link_github'] ?: null, $dados['data_realizacao'] ?: null, $dados['observacoes'] ?: null,
        ]);
        return (int) $stmt->fetchColumn();
    }

    public static function pertenceAoProfissional(int $portfolioId, int $usuarioId): bool
    {
        $stmt = conexao()->prepare(
            'SELECT 1 FROM portfolio po JOIN profissionais p ON p.id = po.profissional_id WHERE po.id = ? AND p.usuario_id = ?'
        );
        $stmt->execute([$portfolioId, $usuarioId]);
        return (bool) $stmt->fetchColumn();
    }

    public static function adicionarImagem(int $portfolioId, array $arquivo): void
    {
        conexao()->prepare(
            'INSERT INTO portfolio_imagens (portfolio_id, nome_ficheiro, caminho, mime_type, tamanho) VALUES (?, ?, ?, ?, ?)'
        )->execute([$portfolioId, $arquivo['nome'], $arquivo['caminho'], $arquivo['mime'], $arquivo['tamanho']]);
    }

    public static function apagar(int $portfolioId, int $usuarioId): bool
    {
        $stmt = conexao()->prepare(
            'DELETE FROM portfolio po USING profissionais p WHERE po.profissional_id = p.id AND po.id = ? AND p.usuario_id = ?'
        );
        $stmt->execute([$portfolioId, $usuarioId]);
        return $stmt->rowCount() > 0;
    }
}
