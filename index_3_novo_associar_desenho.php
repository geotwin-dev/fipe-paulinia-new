<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Inclui o arquivo de conexão
require_once 'connection.php';

try {
    // Verifica se é uma requisição POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido');
    }
    
    // Recebe os dados JSON
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        throw new Exception('Dados JSON inválidos');
    }
    
    $id_poligono = isset($data['id_poligono']) ? intval($data['id_poligono']) : 0;
    $id_marcador = isset($data['id_marcador']) ? intval($data['id_marcador']) : null;
    $quarteirao = isset($data['quarteirao']) ? trim($data['quarteirao']) : '';
    $quadra = isset($data['quadra']) ? trim($data['quadra']) : '';
    $lote = isset($data['lote']) ? trim($data['lote']) : '';
    
    // Validações
    if ($id_poligono <= 0) {
        throw new Exception('ID do polígono inválido');
    }
    
    if (empty($quarteirao) || empty($quadra) || empty($lote)) {
        throw new Exception('Todos os campos são obrigatórios');
    }
    
    // Atualizar polígono
    $sqlPoligono = "UPDATE desenhos SET 
                    quarteirao = :quarteirao,
                    quadra = :quadra,
                    lote = :lote
                    WHERE id = :id_poligono";
    
    $stmtPoligono = $pdo->prepare($sqlPoligono);
    $resultadoPoligono = $stmtPoligono->execute([
        ':quarteirao' => $quarteirao,
        ':quadra' => $quadra,
        ':lote' => $lote,
        ':id_poligono' => $id_poligono
    ]);
    
    if (!$resultadoPoligono || $stmtPoligono->rowCount() === 0) {
        throw new Exception('Erro ao atualizar polígono no banco de dados');
    }
    
    // Atualizar marcador se houver
    if ($id_marcador && $id_marcador > 0) {
        $sqlMarcador = "UPDATE desenhos SET 
                        quarteirao = :quarteirao,
                        quadra = :quadra,
                        lote = :lote
                        WHERE id = :id_marcador";
        
        $stmtMarcador = $pdo->prepare($sqlMarcador);
        $resultadoMarcador = $stmtMarcador->execute([
            ':quarteirao' => $quarteirao,
            ':quadra' => $quadra,
            ':lote' => $lote,
            ':id_marcador' => $id_marcador
        ]);
        
        if (!$resultadoMarcador) {
            throw new Exception('Erro ao atualizar marcador no banco de dados');
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Desenho associado com sucesso',
        'dados' => [
            'id_poligono' => $id_poligono,
            'id_marcador' => $id_marcador,
            'quarteirao' => $quarteirao,
            'quadra' => $quadra,
            'lote' => $lote
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

