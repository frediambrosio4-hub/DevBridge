<?php
declare(strict_types=1);

class PerfilController
{
    public function ver(): void
    {
        $usuario = exigirLogin();
        $db = conexao();
        $stmt = $db->prepare(
            'SELECT u.id, u.nome, u.email, u.tipo, u.descricao, u.localizacao,
             COALESCE(u.foto_path, u.foto_url) AS foto_path, u.data_cadastro,
             p.id AS profissional_id, p.descricao AS descricao_profissional, p.localizacao AS localizacao_profissional, p.experiencia
             FROM usuarios u LEFT JOIN profissionais p ON p.usuario_id = u.id WHERE u.id = ?'
        );
        $stmt->execute([$usuario['id']]);
        $perfil = $stmt->fetch();
        if (!$perfil) {
            resposta(['erro' => 'Perfil não encontrado.'], 404);
        }
        $perfil['foto_path'] = urlFicheiro($perfil['foto_path']);
        $perfil['linguagens'] = [];
        $perfil['especializacoes'] = [];
        $perfil['competencias'] = [];
        $perfil['portfolio'] = [];
        if ($perfil['profissional_id']) {
            $id = (int) $perfil['profissional_id'];
            $perfil['linguagens'] = Profissional::linguagens($id);
            $perfil['especializacoes'] = Profissional::especializacoes($id);
            $perfil['competencias'] = Profissional::competencias($id);
            $perfil['portfolio'] = Portfolio::listar($id);
        }
        $perfil['avaliacoes'] = Avaliacao::doUsuario((int) $usuario['id']);
        resposta(['perfil' => $perfil]);
    }

    public function opcoes(): void
    {
        $usuario = exigirLogin();
        $db = conexao();
        $linguagens = [];
        $especializacoes = [];
        if ($usuario['tipo'] === 'PROGRAMADOR') {
            $linguagens = $db->query('SELECT id, nome, icone, logo_url FROM linguagens_programacao ORDER BY nome')->fetchAll();
        }
        if ($usuario['tipo'] === 'TECNICO_REDES') {
            $especializacoes = $db->query('SELECT id, nome, icone FROM especializacoes_rede ORDER BY nome')->fetchAll();
        }
        resposta(['linguagens' => $linguagens, 'especializacoes' => $especializacoes]);
    }

    public function atualizar(): void
    {
        $usuario = exigirLogin();
        $entrada = dados();
        $nome = texto($entrada['nome'] ?? '', 100);
        $descricao = texto($entrada['descricao'] ?? '', 1000);
        $localizacao = texto($entrada['localizacao'] ?? '', 150);
        if ($nome === '') {
            resposta(['erro' => 'O nome é obrigatório.'], 422);
        }
        $profissional = in_array($usuario['tipo'], ['PROGRAMADOR', 'TECNICO_REDES'], true);
        $dadosProfissional = [
            'descricao_profissional' => texto($entrada['descricao_profissional'] ?? '', 2000),
            'localizacao' => $localizacao,
            'experiencia' => texto($entrada['experiencia'] ?? '', 1000),
            'linguagens' => is_array($entrada['linguagens'] ?? null) ? $entrada['linguagens'] : [],
            'especializacoes' => is_array($entrada['especializacoes'] ?? null) ? $entrada['especializacoes'] : [],
            'competencias' => is_array($entrada['competencias'] ?? null) ? $entrada['competencias'] : [],
        ];
        if ($profissional && $dadosProfissional['descricao_profissional'] === '') {
            resposta(['erro' => 'Inclua uma descrição profissional.'], 422);
        }

        $db = conexao();
        try {
            $db->beginTransaction();
            Usuario::atualizarBasico((int) $usuario['id'], $nome, $descricao, $localizacao);
            if ($profissional) {
                Profissional::atualizar((int) $usuario['id'], $usuario['tipo'], $dadosProfissional);
            }
            $db->commit();
            $atual = Usuario::procurarId((int) $usuario['id']);
            $_SESSION['usuario'] = Usuario::sessao($atual);
            resposta(['mensagem' => 'Perfil atualizado com sucesso.', 'usuario' => $_SESSION['usuario']]);
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            resposta(['erro' => 'Não foi possível guardar o perfil.'], 500);
        }
    }

    public function foto(): void
    {
        $usuario = exigirLogin();
        try {
            $arquivo = guardarImagem($_FILES['foto'] ?? [], 'perfis');
            Usuario::atualizarFoto((int) $usuario['id'], $arquivo);
            $_SESSION['usuario']['foto_path'] = $arquivo['caminho'];
            resposta(['mensagem' => 'Foto atualizada.', 'foto_path' => $arquivo['caminho']]);
        } catch (RuntimeException $e) {
            resposta(['erro' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            resposta(['erro' => 'Não foi possível atualizar a foto.'], 500);
        }
    }
}
