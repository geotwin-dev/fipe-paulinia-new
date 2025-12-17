<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

date_default_timezone_set('America/Sao_Paulo');

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
    
    // Validações
    if ($id_poligono <= 0) {
        throw new Exception('ID do polígono inválido');
    }
    
    // Obter usuário e data/hora para atualização
    $usuario = isset($_SESSION['usuario']) && is_array($_SESSION['usuario']) 
               ? $_SESSION['usuario'][0] 
               : 'desconhecido';
    $dataHoraAtual = date('Y-m-d H:i:s');
    
    // Atualizar polígono - manter quarteirao, definir apenas quadra e lote como NULL
    $sqlPoligono = "UPDATE desenhos SET 
                    quadra = NULL,
                    lote = NULL,
                    cor = :cor,
                    ult_modificacao = :ult_modificacao,
                    user = :user,
                    oque = :oque
                    WHERE id = :id_poligono";
    
    $stmtPoligono = $pdo->prepare($sqlPoligono);
    $resultadoPoligono = $stmtPoligono->execute([
        ':cor' => 'red',
        ':ult_modificacao' => $dataHoraAtual,
        ':user' => $usuario,
        ':oque' => 'desassociação',
        ':id_poligono' => $id_poligono
    ]);
    
    if (!$resultadoPoligono || $stmtPoligono->rowCount() === 0) {
        throw new Exception('Erro ao atualizar polígono no banco de dados');
    }
    
    // Atualizar marcador se houver - manter quarteirao, definir apenas quadra e lote como NULL
    if ($id_marcador && $id_marcador > 0) {
        $sqlMarcador = "UPDATE desenhos SET 
                        quadra = NULL,
                        lote = NULL,
                        cor = :cor,
                        ult_modificacao = :ult_modificacao,
                        user = :user,
                        oque = :oque
                        WHERE id = :id_marcador";
        
        $stmtMarcador = $pdo->prepare($sqlMarcador);
        $resultadoMarcador = $stmtMarcador->execute([
            ':cor' => 'red',
            ':ult_modificacao' => $dataHoraAtual,
            ':user' => $usuario,
            ':oque' => 'desassociação',
            ':id_marcador' => $id_marcador
        ]);
        
        if (!$resultadoMarcador) {
            throw new Exception('Erro ao atualizar marcador no banco de dados');
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Desenho desassociado com sucesso',
        'dados' => [
            'id_poligono' => $id_poligono,
            'id_marcador' => $id_marcador
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
