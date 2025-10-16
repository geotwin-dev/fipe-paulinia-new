<?php
/**
 * SALVAR OFFSET DE DESENHOS
 * 
 * Este arquivo salva apenas o OFFSET (deslocamento) dos desenhos da prefeitura
 * ao invés de salvar todas as coordenadas. Isso torna o arquivo minúsculo.
 * 
 * O arquivo _offset.json contém apenas quanto foi movido do original:
 * - offset_lat: quantos metros moveu na latitude
 * - offset_lng: quantos metros moveu na longitude
 */

header('Content-Type: application/json');

// Recebe os dados JSON do POST
$input = file_get_contents('php://input');
$dados = json_decode($input, true);

// Validação básica
if (!isset($dados['quadricula']) || !isset($dados['offset_lat']) || !isset($dados['offset_lng'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Dados inválidos. É necessário enviar quadrícula, offset_lat e offset_lng.'
    ]);
    exit;
}

$quadricula = $dados['quadricula'];
$offsetLat = floatval($dados['offset_lat']);
$offsetLng = floatval($dados['offset_lng']);

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

// Verifica se o diretório existe
if (!is_dir($diretorio)) {
    echo json_encode([
        'success' => false,
        'message' => 'Diretório cartografia_prefeitura não encontrado.'
    ]);
    exit;
}

// Prepara os dados para salvar (apenas o offset!)
$dadosOffset = [
    'quadricula' => $quadricula,
    'data_ajuste' => date('Y-m-d H:i:s'),
    'offset_lat_metros' => $offsetLat,
    'offset_lng_metros' => $offsetLng
];

// Salva o arquivo JSON
$jsonString = json_encode($dadosOffset, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

if (file_put_contents($caminhoCompleto, $jsonString) !== false) {
    $tamanhoBytes = filesize($caminhoCompleto);
    echo json_encode([
        'success' => true,
        'message' => 'Offset salvo com sucesso!',
        'arquivo' => $nomeArquivo,
        'offset_lat' => $offsetLat,
        'offset_lng' => $offsetLng,
        'tamanho_bytes' => $tamanhoBytes,
        'caminho' => $caminhoCompleto
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao salvar arquivo JSON.'
    ]);
}
?>
