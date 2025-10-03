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
    $required_fields = ['quadricula', 'loteamento', 'pdf', 'posicao_x', 'posicao_y', 'texto'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || $data[$field] === '') {
            throw new Exception("Campo obrigatório: $field");
        }
    }
    
    // Obter usuário da sessão
    $usuario = $_SESSION['usuario'][0] ?? 'desconhecido';
    
    // Preparar dados
    $quadricula = $data['quadricula'];
    $loteamento = $data['loteamento'];
    $pdf = $data['pdf'];
    $quarteirao = $data['quarteirao'] ?? null;
    $quadra = $data['quadra'] ?? null;
    $posicao_x = (float)$data['posicao_x'];
    $posicao_y = (float)$data['posicao_y'];
    $texto = $data['texto'];
    
    // Inserir marcador
    $stmt = $pdo->prepare("
        INSERT INTO marcadores_pdf 
        (quadricula, loteamento, pdf, quarteirao, quadra, posicao_x, posicao_y, texto, usuario, visibilidade) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
    ");
    
    $stmt->execute([$quadricula, $loteamento, $pdf, $quarteirao, $quadra, $posicao_x, $posicao_y, $texto, $usuario]);
    
    $marcador_id = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Marcador salvo com sucesso',
        'marcador_id' => $marcador_id
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?>
