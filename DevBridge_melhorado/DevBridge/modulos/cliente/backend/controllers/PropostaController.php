<?php
declare(strict_types=1);

class PropostaController
{
    public function criar(): void
    {
        $usuario = exigirTipo(['PROGRAMADOR', 'TECNICO_REDES']);
        $dados = dados();
        $proposta = [
            'projeto_id' => inteiro($dados['projeto_id'] ?? 0),
            'valor' => (float) ($dados['valor'] ?? 0),
            'prazo' => inteiro($dados['prazo'] ?? 0),
            'mensagem' => texto($dados['mensagem'] ?? '', 3000),
            'descricao_solucao' => texto($dados['descricao_solucao'] ?? '', 4000),
        ];
        if ($proposta['projeto_id'] < 1 || $proposta['valor'] <= 0 || $proposta['prazo'] < 1 || $proposta['mensagem'] === '') {
            resposta(['erro' => 'Preencha valor, prazo e mensagem da proposta.'], 422);
        }
        try {
            $perfil = Profissional::garantir((int) $usuario['id'], $usuario['tipo']);
            $id = Proposta::criar($proposta, (int) $perfil['id'], (int) $usuario['id']);
            resposta(['id' => $id, 'mensagem' => 'Proposta enviada com sucesso.'], 201);
        } catch (RuntimeException $e) {
            resposta(['erro' => $e->getMessage()], 422);
        } catch (PDOException $e) {
            resposta(['erro' => 'Já enviou uma proposta para este projeto.'], 409);
        }
    }

    public function listar(): void
    {
        resposta(['propostas' => Proposta::listar(exigirLogin())]);
    }

    public function recusar(): void
    {
        $usuario = exigirTipo(['CLIENTE']);
        if (!Proposta::recusar(inteiro($_GET['id'] ?? 0), (int) $usuario['id'])) {
            resposta(['erro' => 'A proposta não pode ser recusada.'], 422);
        }
        resposta(['mensagem' => 'Proposta recusada.']);
    }

    public function anexo(): void
    {
        $usuario = exigirTipo(['PROGRAMADOR', 'TECNICO_REDES']);
        $id = inteiro($_POST['proposta_id'] ?? 0);
        if (!Proposta::pertenceAoProfissional($id, (int) $usuario['id'])) {
            resposta(['erro' => 'Proposta não encontrada.'], 404);
        }
        try {
            $arquivo = guardarAnexo($_FILES['anexo'] ?? [], 'propostas');
            Proposta::adicionarAnexo($id, $arquivo);
            resposta(['mensagem' => 'Anexo adicionado.', 'arquivo' => $arquivo], 201);
        } catch (RuntimeException $e) {
            resposta(['erro' => $e->getMessage()], 422);
        }
    }
}
