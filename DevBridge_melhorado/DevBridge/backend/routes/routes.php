<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
foreach (['Usuario', 'Profissional', 'Projeto', 'Proposta', 'Contratacao', 'Portfolio', 'Avaliacao'] as $modelo) {
    require_once __DIR__ . '/../models/' . $modelo . '.php';
}
foreach ([
    '../../modulos/acesso/backend/controllers/UsuarioController.php',
    '../../modulos/programador/backend/controllers/PerfilController.php',
    '../../modulos/programador/backend/controllers/PortfolioController.php',
    '../../modulos/cliente/backend/controllers/ProjetoController.php',
    '../../modulos/cliente/backend/controllers/PropostaController.php',
    '../../modulos/cliente/backend/controllers/ContratacaoController.php',
    '../../modulos/cliente/backend/controllers/PainelController.php',
    '../../modulos/cliente/backend/controllers/AvaliacaoController.php',
] as $controlador) {
    require_once __DIR__ . '/' . $controlador;
}

$rota = trim((string) ($_GET['rota'] ?? ''), '/');
$metodo = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

$rotas = [
    'GET:csrf' => [UsuarioController::class, 'csrf'],
    'POST:registo' => [UsuarioController::class, 'registo'],
    'POST:login' => [UsuarioController::class, 'login'],
    'POST:logout' => [UsuarioController::class, 'logout'],
    'GET:me' => [UsuarioController::class, 'me'],

    'GET:dashboard' => [PainelController::class, 'dashboard'],
    'GET:perfil' => [PerfilController::class, 'ver'],
    'GET:perfil/opcoes' => [PerfilController::class, 'opcoes'],
    'PUT:perfil' => [PerfilController::class, 'atualizar'],
    'POST:perfil/foto' => [PerfilController::class, 'foto'],

    'GET:portfolio' => [PortfolioController::class, 'listar'],
    'POST:portfolio' => [PortfolioController::class, 'criar'],
    'POST:portfolio/imagem' => [PortfolioController::class, 'imagem'],
    'DELETE:portfolio' => [PortfolioController::class, 'apagar'],

    'GET:projetos' => [ProjetoController::class, 'listar'],
    'GET:projeto' => [ProjetoController::class, 'detalhes'],
    'POST:projetos' => [ProjetoController::class, 'criar'],
    'PUT:projetos' => [ProjetoController::class, 'editar'],
    'DELETE:projetos' => [ProjetoController::class, 'cancelar'],
    'POST:projetos/anexo' => [ProjetoController::class, 'anexo'],

    'GET:propostas' => [PropostaController::class, 'listar'],
    'POST:propostas' => [PropostaController::class, 'criar'],
    'DELETE:propostas' => [PropostaController::class, 'recusar'],
    'POST:propostas/anexo' => [PropostaController::class, 'anexo'],

    'GET:contratacoes' => [ContratacaoController::class, 'listar'],
    'POST:contratacoes' => [ContratacaoController::class, 'criar'],
    'PUT:contratacoes' => [ContratacaoController::class, 'status'],

    'GET:profissionais' => [PainelController::class, 'profissionais'],
    'GET:avaliacoes' => [AvaliacaoController::class, 'listar'],
    'POST:avaliacoes' => [AvaliacaoController::class, 'criar'],
];

$chave = $metodo . ':' . $rota;
if (!isset($rotas[$chave])) {
    resposta(['erro' => 'Rota não encontrada.'], 404);
}
if (!in_array($metodo, ['GET', 'HEAD'], true)) {
    validarCsrf();
}

[$classe, $acao] = $rotas[$chave];
(new $classe())->$acao();
