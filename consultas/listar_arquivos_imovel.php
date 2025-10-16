<?php
/**
 * Lista os arquivos de um imóvel específico
 * Pasta: loteamentos_quadriculas/imoveis/{imob_id}/
 */

header('Content-Type: application/json');

// Recebe o imob_id
$imob_id = isset($_POST['imob_id']) ? trim($_POST['imob_id']) : '';

if (empty($imob_id)) {
    echo json_encode([
        'success' => false,
        'message' => 'ID do imóvel não informado'
    ]);
    exit;
}

// Define o caminho da pasta
$pasta = "../loteamentos_quadriculas/imoveis/{$imob_id}";

// Verifica se a pasta existe
if (!is_dir($pasta)) {
    echo json_encode([
        'success' => true,
        'arquivos' => [],
        'message' => 'Pasta não existe ainda'
    ]);
    exit;
}

// Lista os arquivos da pasta
$arquivos = [];
$itens = scandir($pasta);

foreach ($itens as $item) {
    // Ignora . e ..
    if ($item === '.' || $item === '..') {
        continue;
    }
    
    $caminhoCompleto = $pasta . '/' . $item;
    
    // Apenas arquivos (não diretórios)
    if (is_file($caminhoCompleto)) {
        $arquivos[] = $item;
    }
}

// Ordena alfabeticamente
sort($arquivos);

echo json_encode([
    'success' => true,
    'arquivos' => $arquivos,
    'pasta' => $pasta
]);

