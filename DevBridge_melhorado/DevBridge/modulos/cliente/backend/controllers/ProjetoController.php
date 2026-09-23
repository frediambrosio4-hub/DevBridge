<?php
declare(strict_types=1);

class ProjetoController
{
    public function listar(): void
    {
        resposta(['projetos' => Projeto::listar(exigirLogin(), $_GET)]);
    }

    public function detalhes(): void
    {
        exigirLogin();
        $projeto = Projeto::encontrar(inteiro($_GET['id'] ?? 0));
        if (!$projeto) {
            resposta(['erro' => 'Projeto não encontrado.'], 404);
        }
        resposta(['projeto' => $projeto]);
    }

    public function criar(): void
    {
        $usuario = exigirTipo(['CLIENTE']);
        $dadosProjeto = $this->validar(dados());
        try {
            $id = Projeto::criar($dadosProjeto, (int) $usuario['id']);
            resposta(['id' => $id, 'mensagem' => 'Projeto publicado com sucesso.'], 201);
        } catch (Throwable $e) {
            resposta(['erro' => 'Não foi possível publicar o projeto.'], 500);
        }
    }

    public function editar(): void
    {
        $usuario = exigirTipo(['CLIENTE']);
        $id = inteiro($_GET['id'] ?? 0);
        if (!Projeto::editar($id, $this->validar(dados()), (int) $usuario['id'])) {
            resposta(['erro' => 'O projeto não pode ser atualizado neste estado.'], 422);
        }
        resposta(['mensagem' => 'Projeto atualizado.']);
    }

    public function cancelar(): void
    {
        $usuario = exigirTipo(['CLIENTE']);
        if (!Projeto::cancelar(inteiro($_GET['id'] ?? 0), (int) $usuario['id'])) {
            resposta(['erro' => 'O projeto não pode ser cancelado neste estado.'], 422);
        }
        resposta(['mensagem' => 'Projeto cancelado.']);
    }

    public function anexo(): void
    {
        $usuario = exigirTipo(['CLIENTE']);
        $id = inteiro($_POST['projeto_id'] ?? 0);
        if (!Projeto::pertenceAoCliente($id, (int) $usuario['id'])) {
            resposta(['erro' => 'Projeto não encontrado.'], 404);
        }
        try {
            $arquivo = guardarAnexo($_FILES['anexo'] ?? [], 'projetos');
            Projeto::adicionarAnexo($id, $arquivo);
            resposta(['mensagem' => 'Anexo adicionado.', 'arquivo' => $arquivo], 201);
        } catch (RuntimeException $e) {
            resposta(['erro' => $e->getMessage()], 422);
        }
    }

    private function validar(array $entrada): array
    {
        $dados = [
            'titulo' => texto($entrada['titulo'] ?? '', 150),
            'descricao' => texto($entrada['descricao'] ?? '', 5000),
            'categoria' => texto($entrada['categoria'] ?? $entrada['setor'] ?? '', 100),
            'orcamento' => (float) ($entrada['orcamento'] ?? 0),
            'prazo' => texto($entrada['prazo'] ?? '', 10),
            'localizacao' => texto($entrada['localizacao'] ?? '', 150),
            'tipo_trabalho' => strtoupper(texto($entrada['tipo_trabalho'] ?? 'REMOTO', 20)),
            'competencias' => is_array($entrada['competencias'] ?? null) ? $entrada['competencias'] : array_filter(array_map('trim', explode(',', (string) ($entrada['competencias'] ?? $entrada['tecnologias'] ?? '')))),
        ];
        if ($dados['titulo'] === '' || $dados['descricao'] === '' || $dados['categoria'] === '' || $dados['orcamento'] <= 0 ||
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dados['prazo']) || !in_array($dados['tipo_trabalho'], ['REMOTO', 'PRESENCIAL', 'HIBRIDO'], true)) {
            resposta(['erro' => 'Preencha os dados obrigatórios do projeto corretamente.'], 422);
        }
        return $dados;
    }
}
