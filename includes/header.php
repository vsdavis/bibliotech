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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
</head>
<body>
