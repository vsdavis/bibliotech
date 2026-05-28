<?php
require_once __DIR__ . '/helpers.php';

// Cada página pode definir seu próprio título antes de incluir este arquivo.
$titulo_pagina = $titulo_pagina ?? 'BiblioTech';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sistema de Gerenciamento de Biblioteca Escolar">
    <meta name="theme-color" content="#22543D">
    <title><?= e($titulo_pagina) ?> · BiblioTech</title>
    <link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/style.css">
</head>
<body>
