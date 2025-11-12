<?php
header('Content-Type: application/json');

$pastaKMLs = 'camadas';

// Verifica se a pasta existe
if (!is_dir($pastaKMLs)) {
    echo json_encode([
        'success' => false,
        'message' => 'Pasta de camadas não encontrada',
        'arquivos' => []
    ]);
    exit;
}

// Lista todos os arquivos KML na pasta
$arquivos = [];
$items = scandir($pastaKMLs);

foreach ($items as $item) {
    if ($item === '.' || $item === '..') continue;
    
    $caminhoCompleto = $pastaKMLs . '/' . $item;
    
    // Se for um arquivo KML
    if (is_file($caminhoCompleto) && strtolower(pathinfo($item, PATHINFO_EXTENSION)) === 'kml') {
        $arquivos[] = [
            'nome' => $item,
            'caminho' => $caminhoCompleto,
            'tamanho' => filesize($caminhoCompleto)
        ];
    }
}

echo json_encode([
    'success' => true,
    'arquivos' => $arquivos
]);
?>

