<?php
session_start();
require_once 'connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Evitar cache do navegador
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

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
    
    // Validar dados obrigatórios para deleção
    $requiredFields = ['quadricula', 'loteamento', 'pdf', 'quarteirao', 'id_quarteirao', 'quadra', 'tipo_desenho', 'coordenadas'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field])) {
            throw new Exception("Campo obrigatório ausente: $field");
        }
    }
    
    $usuario = isset($_SESSION['usuario'][0]) ? trim($_SESSION['usuario'][0]) : null;
    
    // Preparar query de soft delete
    $stmt = $pdo->prepare("
        UPDATE desenhos_pdf 
        SET status = 0, ult_modificacao = NOW(), oque = 'DELETE', user = :user
        WHERE quadricula = :quadricula 
        AND loteamento = :loteamento 
        AND pdf = :pdf 
        AND quarteirao = :quarteirao 
        AND id_quarteirao = :id_quarteirao 
        AND quadra = :quadra 
        AND tipo_desenho = :tipo_desenho 
        AND coordenadas = :coordenadas
    ");
    
    // Executar deleção
    $result = $stmt->execute([
        ':user' => $usuario,
        ':quadricula' => $data['quadricula'],
        ':loteamento' => $data['loteamento'],
        ':pdf' => $data['pdf'],
        ':quarteirao' => $data['quarteirao'],
        ':id_quarteirao' => $data['id_quarteirao'],
        ':quadra' => $data['quadra'],
        ':tipo_desenho' => $data['tipo_desenho'],
        ':coordenadas' => $data['coordenadas']
    ]);
    
    if ($result) {
        $affectedRows = $stmt->rowCount();
        echo json_encode([
            'success' => true, 
            'message' => 'Desenho deletado do banco de dados com sucesso',
            'affected_rows' => $affectedRows
        ]);
    } else {
        throw new Exception('Erro ao deletar do banco de dados');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Erro: ' . $e->getMessage()
    ]);
}
?>
