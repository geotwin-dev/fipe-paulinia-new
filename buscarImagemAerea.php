<?php
// Script para buscar imagens aéreas de fora do XAMPP
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

$caminho = $_GET['caminho'] ?? '';

// Segurança básica: remove tentativas de directory traversal
$caminho = str_replace(['..', "\0"], '', $caminho);

// Verifica se o arquivo existe
if (!file_exists($caminho) || !is_file($caminho)) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode([
        'erro' => 'Arquivo não encontrado'
    ]);
    exit;
}

// Detecta o tipo MIME da imagem
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $caminho);
finfo_close($finfo);

// Valida se é uma imagem
$tiposPermitidos = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/bmp', 'image/tiff'];
if (!in_array($mimeType, $tiposPermitidos)) {
    http_response_code(415);
    header('Content-Type: application/json');
    echo json_encode([
        'erro' => 'Tipo de arquivo não suportado',
        'tipo' => $mimeType
    ]);
    exit;
}

// Define headers para a imagem
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($caminho));
header('Cache-Control: public, max-age=31536000'); // Cache por 1 ano
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');

// Envia o arquivo
readfile($caminho);
exit;
