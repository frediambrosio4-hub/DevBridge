<?php
declare(strict_types=1);

class ContratacaoController
{
    public function criar(): void
    {
        $usuario = exigirTipo(['CLIENTE']);
        try {
            $id = Contratacao::criar(inteiro(dados()['proposta_id'] ?? 0), (int) $usuario['id']);
            resposta(['id' => $id, 'mensagem' => 'Profissional escolhido com sucesso.'], 201);
        } catch (RuntimeException $e) {
            resposta(['erro' => $e->getMessage()], 422);
        }
    }

    public function listar(): void
    {
        resposta(['contratacoes' => Contratacao::listar(exigirLogin())]);
    }

    public function status(): void
    {
        $usuario = exigirLogin();
        $status = strtoupper(texto(dados()['status'] ?? '', 30));
        try {
            Contratacao::atualizarStatus(inteiro($_GET['id'] ?? 0), $status, $usuario);
            resposta(['mensagem' => 'Estado atualizado.']);
        } catch (RuntimeException $e) {
            resposta(['erro' => $e->getMessage()], 422);
        }
    }
}
