<?php
declare(strict_types=1);

class Contratacao
{
    public static function criar(int $propostaId, int $clienteId): int
    {
        $db = conexao();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare(
                "SELECT pr.*, p.cliente_id, p.status AS projeto_status FROM propostas pr
                 JOIN projetos p ON p.id = pr.projeto_id WHERE pr.id = ? AND p.cliente_id = ? FOR UPDATE"
            );
            $stmt->execute([$propostaId, $clienteId]);
            $proposta = $stmt->fetch();
            if (!$proposta || $proposta['status'] !== 'ENVIADA') {
                throw new RuntimeException('A proposta não está disponível para contratação.');
            }
            $temContrato = $db->prepare('SELECT 1 FROM contratacoes WHERE projeto_id = ?');
            $temContrato->execute([$proposta['projeto_id']]);
            if ($temContrato->fetchColumn()) {
                throw new RuntimeException('Este projeto já possui um profissional escolhido.');
            }
            $db->prepare("UPDATE propostas SET status = 'ACEITA' WHERE id = ?")->execute([$propostaId]);
            $db->prepare(
                "UPDATE propostas SET status = 'RECUSADA' WHERE projeto_id = ? AND id <> ?
                 AND status IN ('ENVIADA', 'VISUALIZADA', 'EM_NEGOCIACAO')"
            )->execute([$proposta['projeto_id'], $propostaId]);
            $stmt = $db->prepare(
                "INSERT INTO contratacoes (projeto_id, proposta_id, cliente_id, profissional_id, status)
                 VALUES (?, ?, ?, ?, 'AGUARDANDO_INICIO') RETURNING id"
            );
            $stmt->execute([$proposta['projeto_id'], $propostaId, $clienteId, $proposta['profissional_id']]);
            $id = (int) $stmt->fetchColumn();
            $db->prepare("UPDATE projetos SET status = 'PROFISSIONAL_ESCOLHIDO' WHERE id = ?")->execute([$proposta['projeto_id']]);
            $db->commit();
            return $id;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function listar(array $usuario): array
    {
        $db = conexao();
        if ($usuario['tipo'] === 'CLIENTE') {
            $sql = 'SELECT c.*, p.titulo AS projeto_titulo, u.nome AS contraparte_nome, u.foto_path
                    FROM contratacoes c JOIN projetos p ON p.id = c.projeto_id
                    JOIN profissionais f ON f.id = c.profissional_id JOIN usuarios u ON u.id = f.usuario_id
                    WHERE c.cliente_id = ? ORDER BY c.data_inicio DESC';
        } else {
            $sql = 'SELECT c.*, p.titulo AS projeto_titulo, u.nome AS contraparte_nome, u.foto_path
                    FROM contratacoes c JOIN projetos p ON p.id = c.projeto_id JOIN usuarios u ON u.id = c.cliente_id
                    JOIN profissionais f ON f.id = c.profissional_id WHERE f.usuario_id = ? ORDER BY c.data_inicio DESC';
        }
        $stmt = $db->prepare($sql);
        $stmt->execute([$usuario['id']]);
        return $stmt->fetchAll();
    }

    public static function atualizarStatus(int $id, string $status, array $usuario): bool
    {
        $permitidos = ['AGUARDANDO_INICIO', 'EM_ANDAMENTO', 'ENTREGUE', 'CONCLUIDA', 'CANCELADA'];
        if (!in_array($status, $permitidos, true)) {
            throw new RuntimeException('Estado inválido.');
        }
        $db = conexao();
        $db->beginTransaction();
        try {
            if ($usuario['tipo'] === 'CLIENTE') {
                $stmt = $db->prepare('SELECT projeto_id FROM contratacoes WHERE id = ? AND cliente_id = ? FOR UPDATE');
                $stmt->execute([$id, $usuario['id']]);
            } else {
                $stmt = $db->prepare('SELECT c.projeto_id FROM contratacoes c JOIN profissionais p ON p.id = c.profissional_id WHERE c.id = ? AND p.usuario_id = ? FOR UPDATE');
                $stmt->execute([$id, $usuario['id']]);
            }
            $projetoId = $stmt->fetchColumn();
            if (!$projetoId) {
                throw new RuntimeException('Contratação não encontrada.');
            }
            $db->prepare("UPDATE contratacoes SET status = ?, data_conclusao = CASE WHEN ? = 'CONCLUIDA' THEN CURRENT_TIMESTAMP ELSE data_conclusao END WHERE id = ?")
                ->execute([$status, $status, $id]);
            $statusProjeto = ['AGUARDANDO_INICIO' => 'PROFISSIONAL_ESCOLHIDO', 'EM_ANDAMENTO' => 'EM_ANDAMENTO', 'ENTREGUE' => 'ENTREGUE', 'CONCLUIDA' => 'CONCLUIDO', 'CANCELADA' => 'CANCELADO'][$status];
            $db->prepare('UPDATE projetos SET status = ? WHERE id = ?')->execute([$statusProjeto, $projetoId]);
            $db->commit();
            return true;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }
}
