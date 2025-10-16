<?php
/**
 * Upload de arquivos para um imóvel específico
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

// Verifica se há arquivos
if (!isset($_FILES['arquivos']) || empty($_FILES['arquivos']['name'][0])) {
    echo json_encode([
        'success' => false,
        'message' => 'Nenhum arquivo foi enviado'
    ]);
    exit;
}

// Define o caminho da pasta base
$pastaBase = "../loteamentos_quadriculas/imoveis";

// Cria a pasta base se não existir
if (!is_dir($pastaBase)) {
    if (!mkdir($pastaBase, 0755, true)) {
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao criar pasta base de imóveis'
        ]);
        exit;
    }
}

// Define o caminho da pasta do imóvel
$pastaImovel = "{$pastaBase}/{$imob_id}";

// Cria a pasta do imóvel se não existir
if (!is_dir($pastaImovel)) {
    if (!mkdir($pastaImovel, 0755, true)) {
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao criar pasta do imóvel'
        ]);
        exit;
    }
}

// Processa cada arquivo
$arquivosEnviados = [];
$erros = [];

$totalArquivos = count($_FILES['arquivos']['name']);

for ($i = 0; $i < $totalArquivos; $i++) {
    $nomeArquivo = $_FILES['arquivos']['name'][$i];
    $arquivoTmp = $_FILES['arquivos']['tmp_name'][$i];
    $erro = $_FILES['arquivos']['error'][$i];
    
    // Verifica se houve erro no upload
    if ($erro !== UPLOAD_ERR_OK) {
        $erros[] = "Erro ao enviar {$nomeArquivo}";
        continue;
    }
    
    // Sanitiza o nome do arquivo (mantém caracteres especiais do português)
    $nomeArquivo = preg_replace('/[<>:"\/\\|?*]/', '_', $nomeArquivo);
    
    // Define o caminho de destino
    $caminhoDestino = "{$pastaImovel}/{$nomeArquivo}";
    
    // Move o arquivo
    if (move_uploaded_file($arquivoTmp, $caminhoDestino)) {
        $arquivosEnviados[] = $nomeArquivo;
    } else {
        $erros[] = "Erro ao salvar {$nomeArquivo}";
    }
}

// Retorna resultado
if (count($arquivosEnviados) > 0) {
    echo json_encode([
        'success' => true,
        'message' => count($arquivosEnviados) . ' arquivo(s) enviado(s) com sucesso',
        'arquivos' => $arquivosEnviados,
        'erros' => $erros
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Nenhum arquivo foi enviado',
        'erros' => $erros
    ]);
}

