<?php
/**
 * Infraestrutura comum do DevBridge.
 * As credenciais podem ser substituídas por variáveis de ambiente DEVBRIDGE_DB_*.
 */
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('devbridge_session');
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
    session_start();
}

const DB_HOST = 'localhost';
const DB_PORT = '5432';
const DB_NAME = 'DevBridge';
const DB_USER = 'postgres';
const DB_PASS = 'Freddy123';
const MAX_IMAGE_SIZE = 5242880; // 5 MB
const MAX_FILE_SIZE = 10485760; // 10 MB

function conexao(): PDO
{
    static $db = null;
    if ($db instanceof PDO) {
        return $db;
    }

    $host = getenv('DEVBRIDGE_DB_HOST') ?: DB_HOST;
    $port = getenv('DEVBRIDGE_DB_PORT') ?: DB_PORT;
    $name = getenv('DEVBRIDGE_DB_NAME') ?: DB_NAME;
    $user = getenv('DEVBRIDGE_DB_USER') ?: DB_USER;
    $pass = getenv('DEVBRIDGE_DB_PASS') ?: DB_PASS;

    try {
        $db = new PDO("pgsql:host={$host};port={$port};dbname={$name}", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return $db;
    } catch (PDOException $e) {
        resposta(['erro' => 'Não foi possível ligar à base de dados.'], 500);
    }
}

function resposta(array $dados, int $codigo = 200): void
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function dados(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $json = json_decode((string) file_get_contents('php://input'), true);
        return is_array($json) ? $json : [];
    }
    return $_POST;
}

function texto($valor, int $limite): string
{
    $valor = trim((string) $valor);
    return function_exists('mb_substr') ? mb_substr($valor, 0, $limite, 'UTF-8') : substr($valor, 0, $limite);
}

function inteiro($valor): int
{
    return filter_var($valor, FILTER_VALIDATE_INT) !== false ? (int) $valor : 0;
}

function utilizador(): ?array
{
    return isset($_SESSION['usuario']) && is_array($_SESSION['usuario']) ? $_SESSION['usuario'] : null;
}

function exigirLogin(): array
{
    $usuario = utilizador();
    if (!$usuario) {
        resposta(['erro' => 'É necessário iniciar sessão.'], 401);
    }
    return $usuario;
}

function exigirTipo(array $tipos): array
{
    $usuario = exigirLogin();
    if (!in_array($usuario['tipo'], $tipos, true)) {
        resposta(['erro' => 'Sem permissão para esta área.'], 403);
    }
    return $usuario;
}

function tokenCsrf(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function validarCsrf(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf'] ?? '');
    if (!is_string($token) || !hash_equals(tokenCsrf(), $token)) {
        resposta(['erro' => 'Pedido inválido. Atualize a página e tente novamente.'], 419);
    }
}

function diretorioProjeto(): string
{
    return dirname(__DIR__, 2);
}

function urlFicheiro(?string $caminho): ?string
{
    if (!$caminho) {
        return null;
    }
    // Mantém URLs já guardadas por versões antigas do sistema.
    if (preg_match('#^https?://#i', $caminho)) {
        return $caminho;
    }
    return ltrim($caminho, '/');
}

/** Guarda uma imagem, validando extensão, MIME, tamanho e nome aleatório. */
function guardarImagem(array $ficheiro, string $subdiretorio): array
{
    if (($ficheiro['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Selecione uma imagem válida.');
    }
    if (($ficheiro['size'] ?? 0) < 1 || $ficheiro['size'] > MAX_IMAGE_SIZE) {
        throw new RuntimeException('A imagem deve ter no máximo 5 MB.');
    }
    if (!is_uploaded_file($ficheiro['tmp_name'])) {
        throw new RuntimeException('Upload de imagem inválido.');
    }

    $tipos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($ficheiro['tmp_name']);
    if (!isset($tipos[$mime])) {
        throw new RuntimeException('Use apenas imagens JPG, PNG ou WEBP.');
    }
    $extensaoOriginal = strtolower(pathinfo((string) $ficheiro['name'], PATHINFO_EXTENSION));
    if (!in_array($extensaoOriginal, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        throw new RuntimeException('A extensão da imagem não é permitida.');
    }

    $pastaRelativa = 'uploads/' . trim($subdiretorio, '/');
    $pasta = diretorioProjeto() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $pastaRelativa);
    if (!is_dir($pasta) && !mkdir($pasta, 0755, true) && !is_dir($pasta)) {
        throw new RuntimeException('Não foi possível preparar a pasta de uploads.');
    }
    $nome = bin2hex(random_bytes(18)) . '.' . $tipos[$mime];
    if (!move_uploaded_file($ficheiro['tmp_name'], $pasta . DIRECTORY_SEPARATOR . $nome)) {
        throw new RuntimeException('Não foi possível guardar a imagem.');
    }

    return ['nome' => $nome, 'caminho' => $pastaRelativa . '/' . $nome, 'mime' => $mime, 'tamanho' => (int) $ficheiro['size']];
}

/** Guarda anexos não executáveis: imagens ou PDF. */
function guardarAnexo(array $ficheiro, string $subdiretorio): array
{
    $mimePermitido = ['application/pdf'];
    if (($ficheiro['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Selecione um anexo válido.');
    }
    if (($ficheiro['size'] ?? 0) < 1 || $ficheiro['size'] > MAX_FILE_SIZE || !is_uploaded_file($ficheiro['tmp_name'])) {
        throw new RuntimeException('O anexo é inválido ou excede 10 MB.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($ficheiro['tmp_name']);
    if (str_starts_with($mime, 'image/')) {
        return guardarImagem($ficheiro, $subdiretorio);
    }
    if (!in_array($mime, $mimePermitido, true)) {
        throw new RuntimeException('São aceites apenas imagens ou documentos PDF.');
    }
    $pastaRelativa = 'uploads/' . trim($subdiretorio, '/');
    $pasta = diretorioProjeto() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $pastaRelativa);
    if (!is_dir($pasta) && !mkdir($pasta, 0755, true) && !is_dir($pasta)) {
        throw new RuntimeException('Não foi possível preparar a pasta de uploads.');
    }
    $nome = bin2hex(random_bytes(18)) . '.pdf';
    if (!move_uploaded_file($ficheiro['tmp_name'], $pasta . DIRECTORY_SEPARATOR . $nome)) {
        throw new RuntimeException('Não foi possível guardar o anexo.');
    }
    return ['nome' => $nome, 'caminho' => $pastaRelativa . '/' . $nome, 'mime' => $mime, 'tamanho' => (int) $ficheiro['size']];
}
