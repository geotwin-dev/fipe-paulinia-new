<?php
require_once 'connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

try {
    // Receber dados JSON
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        throw new Exception('Dados JSON inválidos');
    }
    
    /*
    // Validar dados obrigatórios
    $requiredFields = ['quadricula', 'loteamento', 'pdf', 'quarteirao', 'id_quarteirao', 'quadra', 'tipo_desenho', 'coordenadas'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            throw new Exception("Campo obrigatório ausente: $field");
        }
    }
    */
    
    // Preparar dados para inserção
    $stmt = $pdo->prepare("
        INSERT INTO desenhos_pdf (
            data, 
            quadricula, 
            loteamento, 
            pdf, 
            rotacao, 
            quarteirao, 
            id_quarteirao, 
            quadra, 
            tipo_desenho, 
            coordenadas,
            coordenadas_canvas,
            status,
            ult_modificacao,
            user,
            oque
        ) VALUES (
            NOW(), 
            :quadricula, 
            :loteamento, 
            :pdf, 
            :rotacao, 
            :quarteirao, 
            :id_quarteirao, 
            :quadra, 
            :tipo_desenho, 
            :coordenadas,
            :coordenadas_canvas,
            1,
            NOW(),
            :user,
            'INSERT'
        )
    ");
    
    // Executar inserção
    $result = $stmt->execute([
        ':quadricula' => $data['quadricula'],
        ':loteamento' => $data['loteamento'],
        ':pdf' => $data['pdf'],
        ':rotacao' => $data['rotacao'],
        ':quarteirao' => $data['quarteirao'],
        ':id_quarteirao' => $data['id_quarteirao'],
        ':quadra' => $data['quadra'],
        ':tipo_desenho' => $data['tipo_desenho'],
        ':coordenadas' => $data['coordenadas'],
        ':coordenadas_canvas' => $data['coordenadas_canvas'],
        ':user' => null // Por enquanto null, será implementado depois
    ]);
    
    if ($result) {
        $lastId = $pdo->lastInsertId();
        echo json_encode([
            'success' => true, 
            'message' => 'Desenho salvo no banco de dados com sucesso',
            'id' => $lastId
        ]);
    } else {
        throw new Exception('Erro ao inserir no banco de dados');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Erro: ' . $e->getMessage()
    ]);
}
?>
