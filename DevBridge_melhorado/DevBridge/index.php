<?php
declare(strict_types=1);

if (isset($_GET['rota'])) {
    require __DIR__ . '/backend/routes/routes.php';
    exit;
}
header('Location: modulos/acesso/frontend/index.html', true, 302);
exit;
