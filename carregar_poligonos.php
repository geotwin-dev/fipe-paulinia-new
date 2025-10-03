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
    
    // Buscar polígonos para este PDF - apenas por PDF e visibilidade = 1
    $stmt = $pdo->prepare("
        SELECT * FROM poligonos_pdf 
        WHERE pdf = ? AND visibilidade = 1
        ORDER BY data_criacao ASC
    ");
    $stmt->execute([$pdf]);
    $poligonos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Log para debug
    error_log("Polígonos encontrados: " . count($poligonos));
    
    // Preparar dados para retorno
    $poligonos_formatados = [];
    foreach ($poligonos as $poligono) {
        $poligonos_formatados[] = [
            'id' => (int)$poligono['id'],
            'quadricula' => $poligono['quadricula'],
            'loteamento' => $poligono['loteamento'],
            'pdf' => $poligono['pdf'],
            'quarteirao' => $poligono['quarteirao'],
            'quadra' => $poligono['quadra'],
            'pontos' => json_decode($poligono['pontos'], true),
            'usuario' => $poligono['usuario'],
            'datetime' => $poligono['data_criacao']
        ];
    }
    
    echo json_encode([
        'success' => true, 
        'poligonos' => $poligonos_formatados
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?>
