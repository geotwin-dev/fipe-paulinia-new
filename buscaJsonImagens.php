<?php

header('Content-Type: application/json');

// Recebe o nome da quadrícula
$quadricula = $_GET['quadricula'] ?? '';

if (empty($quadricula)) {
    http_response_code(400);
    echo json_encode(['erro' => 'Nome da quadrícula não informado']);
    exit;
}

// Sanitiza o nome da quadrícula
$quadricula = preg_replace('/[^a-zA-Z0-9_-]/', '', $quadricula);

// Caminhos configuráveis
$diretorio_jsons_individuais = 'imagens_aereas/';
$arquivo_json_mestre = 'imagens_aereas/quadriculas_p4d.json'; // ← AJUSTE AQUI O CAMINHO DO JSON MESTRE
$arquivo_quadricula = $diretorio_jsons_individuais . $quadricula . '_imagens.json';

// Verifica se o diretório existe, senão cria
if (!is_dir($diretorio_jsons_individuais)) {
    mkdir($diretorio_jsons_individuais, 0777, true);
}

// Função que lê o JSON e devolve os objetos
function lerEDevolverJson($arquivo) {
    if (!file_exists($arquivo) || !is_file($arquivo)) {
        return null;
    }
    
    $conteudo = file_get_contents($arquivo);
    $dados = json_decode($conteudo, true);
    
    if ($dados === null) {
        return [];
    }
    
    return $dados;
}

// CASO 1: Arquivo individual da quadrícula já existe
if (file_exists($arquivo_quadricula)) {
    $objetos = lerEDevolverJson($arquivo_quadricula);
    if ($objetos !== null) {
        echo json_encode($objetos);
        exit;
    }
}

// CASO 2: Arquivo não existe, busca no JSON mestre
if (!file_exists($arquivo_json_mestre)) {
    http_response_code(404);
    echo json_encode(['erro' => 'JSON mestre não encontrado']);
    exit;
}

// Lê o JSON mestre
$json_mestre = file_get_contents($arquivo_json_mestre);
$dados_mestre = json_decode($json_mestre, true);

if ($dados_mestre === null) {
    http_response_code(500);
    echo json_encode(['erro' => 'Erro ao ler JSON mestre']);
    exit;
}

// Busca a quadrícula no JSON mestre
if (!isset($dados_mestre[$quadricula])) {
    http_response_code(404);
    echo json_encode(['erro' => 'Quadrícula não encontrada no JSON mestre']);
    exit;
}

// Pega os dados da quadrícula
$dados_quadricula = $dados_mestre[$quadricula];

// Garante que seja um array
if (!is_array($dados_quadricula)) {
    http_response_code(500);
    echo json_encode(['erro' => 'Dados da quadrícula inválidos']);
    exit;
}

// Filtra para pegar apenas o objeto com maior valor na chave 'tamanho'
// Mas só considera objetos válidos (com pasta_undistorted->arquivos não vazio)
$objeto_selecionado = null;
$maior_tamanho = -INF;

foreach ($dados_quadricula as $objeto) {
    // CRITÉRIO 1: Verifica se tem pasta_undistorted->arquivos válido e não vazio
    $valido = false;
    if (isset($objeto['pasta_undistorted']) && is_array($objeto['pasta_undistorted'])) {
        if (isset($objeto['pasta_undistorted']['arquivos']) && 
            is_array($objeto['pasta_undistorted']['arquivos']) && 
            count($objeto['pasta_undistorted']['arquivos']) > 0) {
            $valido = true;
        }
    }
    
    // Se não for válido, ignora este objeto completamente
    if (!$valido) {
        continue;
    }
    
    // CRITÉRIO 2: Entre os válidos, pega o de maior tamanho
    if (isset($objeto['tamanho']) && is_numeric($objeto['tamanho'])) {
        $tamanho_atual = floatval($objeto['tamanho']);
        if ($tamanho_atual > $maior_tamanho) {
            $maior_tamanho = $tamanho_atual;
            $objeto_selecionado = $objeto;
        }
    }
}

// Se não encontrou nenhum objeto com 'tamanho', pega o primeiro
if ($objeto_selecionado === null && count($dados_quadricula) > 0) {
    $objeto_selecionado = $dados_quadricula[0];
}

// Pega o objeto selecionado (não em array, pois é único)
$dados_final = $objeto_selecionado !== null ? $objeto_selecionado : [];

// Cria o arquivo JSON individual para essa quadrícula
$json_salvo = json_encode($dados_final, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
file_put_contents($arquivo_quadricula, $json_salvo);

// Devolve o objeto
echo json_encode($dados_final);