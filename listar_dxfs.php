<?php
header('Content-Type: application/json');

$pastaDXFs = 'camadas_dxf';

// Verifica se a pasta existe
if (!is_dir($pastaDXFs)) {
    echo json_encode([
        'success' => false,
        'message' => 'Pasta de camadas DXF não encontrada',
        'arquivos' => []
    ]);
    exit;
}

// Lista todos os arquivos DXF na pasta
$arquivos = [];
$items = scandir($pastaDXFs);

foreach ($items as $item) {
    if ($item === '.' || $item === '..') continue;
    
    $caminhoCompleto = $pastaDXFs . '/' . $item;
    
    // Se for um arquivo DXF
    if (is_file($caminhoCompleto) && strtolower(pathinfo($item, PATHINFO_EXTENSION)) === 'dxf') {
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
