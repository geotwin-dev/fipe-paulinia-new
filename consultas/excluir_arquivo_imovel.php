<?php
/**
 * Exclui um arquivo de um imóvel específico
 * Pasta: loteamentos_quadriculas/imoveis/{imob_id}/
 */

header('Content-Type: application/json');

// Recebe o imob_id e nome do arquivo
$imob_id = isset($_POST['imob_id']) ? trim($_POST['imob_id']) : '';
$arquivo = isset($_POST['arquivo']) ? trim($_POST['arquivo']) : '';

if (empty($imob_id)) {
    echo json_encode([
        'success' => false,
        'message' => 'ID do imóvel não informado'
    ]);
    exit;
}

if (empty($arquivo)) {
    echo json_encode([
        'success' => false,
        'message' => 'Nome do arquivo não informado'
    ]);
    exit;
}

// Define o caminho da pasta
$pasta = "../loteamentos_quadriculas/imoveis/{$imob_id}";

// Verifica se a pasta existe
if (!is_dir($pasta)) {
    echo json_encode([
        'success' => false,
        'message' => 'Pasta do imóvel não existe'
    ]);
    exit;
}

// Define o caminho completo do arquivo
$caminhoArquivo = "{$pasta}/{$arquivo}";

// Verifica se o arquivo existe
if (!file_exists($caminhoArquivo)) {
    echo json_encode([
        'success' => false,
        'message' => 'Arquivo não encontrado'
    ]);
    exit;
}

// Verifica se é um arquivo (não diretório)
if (!is_file($caminhoArquivo)) {
    echo json_encode([
        'success' => false,
        'message' => 'O caminho especificado não é um arquivo'
    ]);
    exit;
}

// Tenta excluir o arquivo
if (unlink($caminhoArquivo)) {
    echo json_encode([
        'success' => true,
        'message' => 'Arquivo excluído com sucesso'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao excluir arquivo'
    ]);
}

