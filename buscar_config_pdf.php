<?php
session_start();
include("verifica_login.php");
include("connection.php");

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

try {
    // Obter dados do POST
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        throw new Exception('Dados inválidos');
    }
    
    // Validar dados obrigatórios
    $required_fields = ['pdf', 'loteamento', 'quadricula'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            throw new Exception("Campo obrigatório: $field");
        }
    }
    
    $pdf = $data['pdf'];
    $quadricula = $data['quadricula'];
    
    // Log para debug
    error_log("Buscando configuração para PDF: " . $pdf);
    
    // Buscar configuração para este PDF e loteamento
    $stmt = $pdo->prepare("
        SELECT * FROM pdf_configuracoes 
        WHERE pdf = ?
        LIMIT 1
    ");
    $stmt->execute([$pdf]);
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Log para debug
    error_log("Configuração encontrada: " . ($config ? 'SIM' : 'NÃO'));
    
    if (!$config) {
        error_log("Nenhuma configuração encontrada para PDF: " . $pdf);
        echo json_encode([
            'success' => false, 
            'message' => 'Nenhuma configuração encontrada'
        ]);
        exit;
    }
    
    // Decodificar quadrículas salvas
    $quadriculas_salvas = json_decode($config['quadriculas'], true);
    
    // Preparar dados para retorno
    $configuracoes = [
        'id' => (int)$config['id'],
        'pdf' => $config['pdf'],
        'rotacao' => (int)$config['rotacao'],
        'zoom' => (float)$config['zoom'],
        'travado' => (bool)$config['travado'],
        'loteamento' => $config['loteamento'],
        'quadriculas' => $quadriculas_salvas,
        'posicao_x' => (float)$config['posicao_x'],
        'posicao_y' => (float)$config['posicao_y'],
        'viewport_transform' => json_decode($config['viewport_transform'], true),
        'data_travamento' => $config['data_travamento'],
        'data_atualizacao' => $config['data_atualizacao']
    ];
    
    // Log para debug
    error_log("Retornando configurações: travado=" . ($configuracoes['travado'] ? 'SIM' : 'NÃO'));
    
    echo json_encode([
        'success' => true, 
        'configuracoes' => $configuracoes
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?>