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
    $required_fields = ['pdf', 'loteamento', 'quadriculas'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            throw new Exception("Campo obrigatório: $field");
        }
    }
    
    // Preparar dados
    $pdf = $data['pdf'];
    $rotacao = isset($data['rotacao']) ? (int)$data['rotacao'] : 0;
    $zoom = isset($data['zoom']) ? (float)$data['zoom'] : 1.0;
    $travado = isset($data['travado']) ? (bool)$data['travado'] : false;
    $loteamento = $data['loteamento'];
    $quadriculas = is_array($data['quadriculas']) ? json_encode($data['quadriculas']) : json_encode([$data['quadriculas']]);
    $posicao_x = isset($data['posicao_x']) ? (float)$data['posicao_x'] : 0.0;
    $posicao_y = isset($data['posicao_y']) ? (float)$data['posicao_y'] : 0.0;
    $viewport_transform = isset($data['viewport_transform']) ? json_encode($data['viewport_transform']) : json_encode([1, 0, 0, 1, 0, 0]);
    
    // Verificar se já existe configuração para este PDF e loteamento
    $stmt = $pdo->prepare("SELECT id FROM pdf_configuracoes WHERE pdf = ? AND loteamento = ?");
    $stmt->execute([$pdf, $loteamento]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        // Atualizar configuração existente
        $stmt = $pdo->prepare("
            UPDATE pdf_configuracoes 
            SET rotacao = ?, zoom = ?, travado = ?, quadriculas = ?, 
                posicao_x = ?, posicao_y = ?, viewport_transform = ?, 
                data_atualizacao = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$rotacao, $zoom, $travado, $quadriculas, $posicao_x, $posicao_y, $viewport_transform, $existing['id']]);
    } else {
        // Inserir nova configuração
        $stmt = $pdo->prepare("
            INSERT INTO pdf_configuracoes 
            (pdf, rotacao, zoom, travado, loteamento, quadriculas, posicao_x, posicao_y, viewport_transform) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$pdf, $rotacao, $zoom, $travado, $loteamento, $quadriculas, $posicao_x, $posicao_y, $viewport_transform]);
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Configurações salvas com sucesso',
        'id' => $existing ? $existing['id'] : $pdo->lastInsertId()
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?>