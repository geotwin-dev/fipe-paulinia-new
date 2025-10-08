<?php
$caminho = $_GET['caminho'] ?? '';
$caminho = str_replace(['..', "\0"], '', $caminho); // segurança básica

if (!file_exists($caminho) || !is_file($caminho)) {
    http_response_code(404);
    echo json_encode(['erro' => 'Arquivo não encontrado']);
    exit;
}

// Lê todas as linhas
$linhas = file($caminho, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

if (count($linhas) < 2) {
    echo json_encode([]); // nada para processar
    exit;
}

// Cabeçalho
$cabecalho = preg_split('/\s+/', array_shift($linhas)); // primeira linha

$objetos = [];

foreach ($linhas as $linha) {
    $valores = preg_split('/\s+/', $linha);
    $obj = [];
    foreach ($cabecalho as $i => $col) {
        // converte para número se possível, senão deixa string
        $obj[$col] = is_numeric($valores[$i]) ? floatval($valores[$i]) : $valores[$i];
    }
    $objetos[] = $obj;
}

// Retorna JSON pronto
header('Content-Type: application/json');
echo json_encode($objetos);