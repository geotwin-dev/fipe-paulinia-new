<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

try {
    // Verificar se é POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido');
    }
    
    // Ler dados JSON do corpo da requisição
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        throw new Exception('Dados JSON inválidos');
    }
    
    // Verificar se tem os campos necessários
    if (!isset($data['config']) || !isset($data['path'])) {
        throw new Exception('Campos obrigatórios não encontrados');
    }
    
    $config = $data['config'];
    $path = $data['path'];
    
    // Verificar se o diretório existe, se não criar
    $directory = dirname($path);
    if (!is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    
    // Salvar configuração no arquivo JSON
    $jsonString = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $result = file_put_contents($path, $jsonString);
    
    if ($result === false) {
        throw new Exception('Erro ao salvar arquivo');
    }
    
    // Resposta de sucesso
    echo json_encode([
        'success' => true,
        'message' => 'Configuração salva com sucesso',
        'path' => $path,
        'size' => $result
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
