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
    
    // Validar dados obrigatórios - apenas PDF
    if (!isset($data['pdf']) || empty($data['pdf'])) {
        throw new Exception("Campo obrigatório: pdf");
    }
    
    $pdf = $data['pdf'];
    
    // Buscar marcadores para este PDF - apenas por PDF e visibilidade = 1
    $stmt = $pdo->prepare("
        SELECT * FROM marcadores_pdf 
        WHERE pdf = ? AND visibilidade = 1
        ORDER BY data_criacao ASC
    ");
    $stmt->execute([$pdf]);
    $marcadores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Preparar dados para retorno
    $marcadores_formatados = [];
    foreach ($marcadores as $marcador) {
        $marcadores_formatados[] = [
            'id' => (int)$marcador['id'],
            'quadricula' => $marcador['quadricula'],
            'loteamento' => $marcador['loteamento'],
            'pdf' => $marcador['pdf'],
            'posicao_x' => (float)$marcador['posicao_x'],
            'posicao_y' => (float)$marcador['posicao_y'],
            'texto' => $marcador['texto'],
            'usuario' => $marcador['usuario'],
            'data_criacao' => $marcador['data_criacao']
        ];
    }
    
    echo json_encode([
        'success' => true, 
        'marcadores' => $marcadores_formatados
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?>
