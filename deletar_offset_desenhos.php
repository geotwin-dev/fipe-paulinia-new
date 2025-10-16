<?php
/**
 * DELETAR OFFSET DE DESENHOS
 * 
 * Este arquivo deleta o arquivo _offset.json de uma quadrícula específica
 * fazendo com que os desenhos voltem para as coordenadas originais do GeoJSON
 */

header('Content-Type: application/json');

// Recebe os dados JSON do POST
$input = file_get_contents('php://input');
$dados = json_decode($input, true);

// Validação básica
if (!isset($dados['quadricula'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Quadrícula não informada.'
    ]);
    exit;
}

$quadricula = $dados['quadricula'];

// Valida o nome da quadrícula (apenas letras e números)
if (!preg_match('/^[A-Z0-9]+$/', $quadricula)) {
    echo json_encode([
        'success' => false,
        'message' => 'Nome de quadrícula inválido.'
    ]);
    exit;
}

// Define o caminho do arquivo
$diretorio = 'cartografia_prefeitura/';
$nomeArquivo = $quadricula . '_offset.json';
$caminhoCompleto = $diretorio . $nomeArquivo;

// Verifica se o arquivo existe
if (!file_exists($caminhoCompleto)) {
    echo json_encode([
        'success' => true,
        'message' => 'Nenhum offset salvo para deletar.',
        'arquivo_existia' => false
    ]);
    exit;
}

// Tenta deletar o arquivo
if (unlink($caminhoCompleto)) {
    echo json_encode([
        'success' => true,
        'message' => 'Offset deletado com sucesso!',
        'arquivo' => $nomeArquivo,
        'arquivo_existia' => true
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao deletar arquivo de offset.'
    ]);
}
?>

