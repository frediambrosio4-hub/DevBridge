<?php
declare(strict_types=1);

class AvaliacaoController
{
    public function listar(): void
    {
        $usuario = exigirLogin();
        resposta(['avaliacoes' => Avaliacao::doUsuario((int) $usuario['id'])]);
    }

    public function criar(): void
    {
        $usuario = exigirLogin();
        $dados = dados();
        $nota = inteiro($dados['nota'] ?? 0);
        if ($nota < 1 || $nota > 5) {
            resposta(['erro' => 'A classificação deve estar entre 1 e 5 estrelas.'], 422);
        }
        try {
            $id = Avaliacao::criar($usuario, inteiro($dados['contratacao_id'] ?? 0), $nota, texto($dados['comentario'] ?? '', 2000));
            resposta(['id' => $id, 'mensagem' => 'Avaliação registada.'], 201);
        } catch (RuntimeException $e) {
            resposta(['erro' => $e->getMessage()], 422);
        } catch (PDOException $e) {
            resposta(['erro' => 'Já avaliou esta contratação.'], 409);
        }
    }
}
