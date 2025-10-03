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
    $required_fields = ['quadricula', 'loteamento', 'pdf', 'pontos'];
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
    $pontos = $data['pontos']; // JSON string dos pontos
    
    // Inserir polígono
    $stmt = $pdo->prepare("
        INSERT INTO poligonos_pdf 
        (quadricula, loteamento, pdf, quarteirao, quadra, pontos, usuario, visibilidade) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 1)
    ");
    
    $stmt->execute([$quadricula, $loteamento, $pdf, $quarteirao, $quadra, $pontos, $usuario]);
    
    $poligono_id = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Polígono salvo com sucesso',
        'poligono_id' => $poligono_id
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?>
