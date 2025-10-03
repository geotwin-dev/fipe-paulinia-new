<?php
header('Content-Type: application/json');

// Permitir CORS se necessário
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Evitar cache do navegador
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

try {
    // Obter dados JSON da requisição
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        throw new Exception('Dados JSON inválidos');
    }
    
    // Validar dados obrigatórios
    if (!isset($data['filePath']) || !isset($data['data'])) {
        throw new Exception('Dados obrigatórios não fornecidos');
    }
    
    $filePath = $data['filePath'];
    $drawingsData = $data['data'];
    
               // Validar estrutura dos dados (nova estrutura hierárquica)
           if (!isset($drawingsData['pdfFile']) || !isset($drawingsData['quarteirao'])) {
               throw new Exception('Estrutura de dados inválida');
           }
           
           // Validar estrutura do quarteirão (agora é uma lista)
           if (!is_array($drawingsData['quarteirao'])) {
               throw new Exception('Estrutura de quarteirão inválida - deve ser uma lista');
           }
           
           // Validar se há pelo menos um quarteirão
           if (empty($drawingsData['quarteirao'])) {
               throw new Exception('Lista de quarteirões vazia');
           }
           
           // Validar estrutura de cada quarteirão
           foreach ($drawingsData['quarteirao'] as $quarteirao) {
               if (!isset($quarteirao['numero']) || 
                   !isset($quarteirao['id']) || 
                   !isset($quarteirao['quadra']) ||
                   !is_array($quarteirao['quadra'])) {
                   throw new Exception('Estrutura de quarteirão inválida');
               }
           }
    
    // Criar diretório se não existir
    $directory = dirname($filePath);
    if (!is_dir($directory)) {
        if (!mkdir($directory, 0755, true)) {
            throw new Exception('Erro ao criar diretório: ' . $directory);
        }
    }
    
    // Validar se o diretório é seguro (dentro da pasta permitida)
    $allowedPath = 'loteamentos_quadriculas/drawings/';
    if (strpos($filePath, $allowedPath) !== 0) {
        throw new Exception('Caminho não permitido');
    }
    
    // Salvar arquivo JSON
    $jsonContent = json_encode($drawingsData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
    if ($jsonContent === false) {
        throw new Exception('Erro ao codificar JSON');
    }
    
    $result = file_put_contents($filePath, $jsonContent);
    
    if ($result === false) {
        throw new Exception('Erro ao salvar arquivo');
    }
    
               // Contar total de desenhos em todas as quadras de todos os quarteirões
           $totalDrawings = 0;
           foreach ($drawingsData['quarteirao'] as $quarteirao) {
               foreach ($quarteirao['quadra'] as $quadra) {
                   if (isset($quadra['drawings']) && is_array($quadra['drawings'])) {
                       $totalDrawings += count($quadra['drawings']);
                   }
               }
           }
           
           // Log de sucesso
           error_log("Desenhos salvos com sucesso: $filePath - $totalDrawings desenhos");
    
               echo json_encode([
               'success' => true,
               'message' => 'Desenhos salvos com sucesso',
               'filePath' => $filePath,
               'totalDrawings' => $totalDrawings,
               'timestamp' => date('Y-m-d H:i:s')
           ]);
    
} catch (Exception $e) {
    error_log("Erro ao salvar desenhos: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>
