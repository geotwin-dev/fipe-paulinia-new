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
    $id_cadastro = isset($data['id_cadastro']) ? intval($data['id_cadastro']) : 0;
    $quarteirao = isset($data['quarteirao']) ? trim($data['quarteirao']) : '';
    $quadra = isset($data['quadra']) ? trim($data['quadra']) : '';
    $lote = isset($data['lote']) ? trim($data['lote']) : '';
    
    // Validações
    if ($id_poligono <= 0) {
        throw new Exception('ID do polígono inválido');
    }
    
    if ($id_cadastro <= 0) {
        throw new Exception('ID do cadastro inválido');
    }
    
    if (empty($quarteirao) || empty($quadra) || empty($lote)) {
        throw new Exception('Todos os campos são obrigatórios');
    }
    
    // Obter usuário e data/hora para atualização
    $usuario = isset($_SESSION['usuario']) && is_array($_SESSION['usuario']) 
               ? $_SESSION['usuario'][0] 
               : 'desconhecido';
    $dataHoraAtual = date('Y-m-d H:i:s');
    
    // Atualizar polígono
    $sqlPoligono = "UPDATE desenhos SET 
                    quarteirao = :quarteirao,
                    quadra = :quadra,
                    lote = :lote,
                    cor = :cor,
                    ult_modificacao = :ult_modificacao,
                    user = :user,
                    oque = :oque
                    WHERE id = :id_poligono";
    
    $stmtPoligono = $pdo->prepare($sqlPoligono);
    $resultadoPoligono = $stmtPoligono->execute([
        ':quarteirao' => $quarteirao,
        ':quadra' => $quadra,
        ':lote' => $lote,
        ':cor' => 'lime',
        ':ult_modificacao' => $dataHoraAtual,
        ':user' => $usuario,
        ':oque' => 'associação',
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
                        lote = :lote,
                        cor = :cor,
                        ult_modificacao = :ult_modificacao,
                        user = :user,
                        oque = :oque
                        WHERE id = :id_marcador";
        
        $stmtMarcador = $pdo->prepare($sqlMarcador);
        $resultadoMarcador = $stmtMarcador->execute([
            ':quarteirao' => $quarteirao,
            ':quadra' => $quadra,
            ':lote' => $lote,
            ':cor' => 'lime',
            ':ult_modificacao' => $dataHoraAtual,
            ':user' => $usuario,
            ':oque' => 'associação',
            ':id_marcador' => $id_marcador
        ]);
        
        if (!$resultadoMarcador) {
            throw new Exception('Erro ao atualizar marcador no banco de dados');
        }
    }
    
    // ========================================================================
    // INSERIR NA TABELA ASSOCIACOES
    // ========================================================================
    
    // Converter id_marcador para NULL se não houver
    $id_marcador_final = ($id_marcador && $id_marcador > 0) ? $id_marcador : null;
    
    $sqlInsertAssociacao = "INSERT INTO associacoes 
                          (id_cadastro, id_poligono, id_marcador, data_hora, usuario)
                          VALUES 
                          (:id_cadastro, :id_poligono, :id_marcador, :data_hora, :usuario)";
    
    $stmtInsertAssociacao = $pdo->prepare($sqlInsertAssociacao);
    $resultadoAssociacao = $stmtInsertAssociacao->execute([
        ':id_cadastro' => $id_cadastro,
        ':id_poligono' => $id_poligono,
        ':id_marcador' => $id_marcador_final,
        ':data_hora' => $dataHoraAtual,
        ':usuario' => $usuario
    ]);
    
    if (!$resultadoAssociacao) {
        throw new Exception('Erro ao criar registro na tabela associacoes');
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Desenho associado com sucesso',
        'dados' => [
            'id_poligono' => $id_poligono,
            'id_marcador' => $id_marcador,
            'id_cadastro' => $id_cadastro,
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

