<?php
declare(strict_types=1);

class Avaliacao
{
    public static function criar(array $usuario, int $contratacaoId, int $nota, string $comentario): int
    {
        $db = conexao();
        $stmt = $db->prepare(
            "SELECT c.*, p.usuario_id AS profissional_usuario_id FROM contratacoes c
             JOIN profissionais p ON p.id = c.profissional_id
             WHERE c.id = ? AND c.status = 'CONCLUIDA'"
        );
        $stmt->execute([$contratacaoId]);
        $contrato = $stmt->fetch();
        if (!$contrato || !in_array($usuario['id'], [(int) $contrato['cliente_id'], (int) $contrato['profissional_usuario_id']], true)) {
            throw new RuntimeException('A avaliação só pode ser feita por participantes de uma contratação concluída.');
        }
        $avaliado = ((int) $contrato['cliente_id'] === (int) $usuario['id'])
            ? (int) $contrato['profissional_usuario_id'] : (int) $contrato['cliente_id'];
        $stmt = $db->prepare(
            'INSERT INTO avaliacoes (contratacao_id, avaliador_id, avaliado_id, nota, comentario) VALUES (?, ?, ?, ?, ?) RETURNING id'
        );
        $stmt->execute([$contratacaoId, $usuario['id'], $avaliado, $nota, $comentario ?: null]);
        return (int) $stmt->fetchColumn();
    }

    public static function doUsuario(int $usuarioId): array
    {
        $stmt = conexao()->prepare(
            'SELECT a.*, u.nome AS avaliador_nome, u.foto_path AS avaliador_foto, p.titulo AS projeto_titulo
             FROM avaliacoes a JOIN usuarios u ON u.id = a.avaliador_id JOIN contratacoes c ON c.id = a.contratacao_id
             JOIN projetos p ON p.id = c.projeto_id WHERE a.avaliado_id = ? ORDER BY a.data_avaliacao DESC'
        );
        $stmt->execute([$usuarioId]);
        return $stmt->fetchAll();
    }
}
