<?php
declare(strict_types=1);

class PortfolioController
{
    private function perfil(): array
    {
        $usuario = exigirTipo(['PROGRAMADOR', 'TECNICO_REDES']);
        return [$usuario, Profissional::garantir((int) $usuario['id'], $usuario['tipo'])];
    }

    public function listar(): void
    {
        [, $perfil] = $this->perfil();
        resposta(['portfolio' => Portfolio::listar((int) $perfil['id'])]);
    }

    public function criar(): void
    {
        [, $perfil] = $this->perfil();
        $entrada = dados();
        $titulo = texto($entrada['titulo'] ?? '', 150);
        if ($titulo === '') {
            resposta(['erro' => 'O título do trabalho é obrigatório.'], 422);
        }
        foreach (['link_projeto', 'link_github'] as $campo) {
            if (!empty($entrada[$campo]) && !filter_var($entrada[$campo], FILTER_VALIDATE_URL)) {
                resposta(['erro' => 'Os links do portfólio devem ser URLs válidas.'], 422);
            }
        }
        $id = Portfolio::criar((int) $perfil['id'], [
            'titulo' => $titulo,
            'descricao' => texto($entrada['descricao'] ?? '', 3000),
            'tecnologias' => texto($entrada['tecnologias'] ?? '', 500),
            'area' => texto($entrada['area'] ?? '', 150),
            'equipamentos' => texto($entrada['equipamentos'] ?? '', 500),
            'link_projeto' => texto($entrada['link_projeto'] ?? '', 500),
            'link_github' => texto($entrada['link_github'] ?? '', 500),
            'data_realizacao' => texto($entrada['data_realizacao'] ?? '', 10),
            'observacoes' => texto($entrada['observacoes'] ?? '', 2000),
        ]);
        resposta(['id' => $id, 'mensagem' => 'Item adicionado ao portfólio.'], 201);
    }

    public function imagem(): void
    {
        [$usuario] = $this->perfil();
        $id = inteiro($_POST['portfolio_id'] ?? 0);
        if (!Portfolio::pertenceAoProfissional($id, (int) $usuario['id'])) {
            resposta(['erro' => 'Item de portfólio não encontrado.'], 404);
        }
        try {
            $arquivo = guardarImagem($_FILES['imagem'] ?? [], 'portfolio');
            Portfolio::adicionarImagem($id, $arquivo);
            resposta(['mensagem' => 'Imagem adicionada.', 'arquivo' => $arquivo], 201);
        } catch (RuntimeException $e) {
            resposta(['erro' => $e->getMessage()], 422);
        }
    }

    public function apagar(): void
    {
        [$usuario] = $this->perfil();
        if (!Portfolio::apagar(inteiro($_GET['id'] ?? 0), (int) $usuario['id'])) {
            resposta(['erro' => 'Item de portfólio não encontrado.'], 404);
        }
        resposta(['mensagem' => 'Item removido do portfólio.']);
    }
}
