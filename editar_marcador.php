<?php
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
    
    // Recebe os dados via POST
    $id_marcador = isset($_POST['id_marcador']) ? intval($_POST['id_marcador']) : 0;
    $quarteirao = isset($_POST['quarteirao']) ? trim($_POST['quarteirao']) : '';
    $quadra = isset($_POST['quadra']) ? trim($_POST['quadra']) : '';
    $lote = isset($_POST['lote']) ? trim($_POST['lote']) : '';
    $cor = isset($_POST['cor']) ? trim($_POST['cor']) : '#32CD32';
    
    // Validações
    if ($id_marcador <= 0) {
        throw new Exception('ID do marcador inválido');
    }
    
    if (empty($quarteirao) || empty($quadra) || empty($lote)) {
        throw new Exception('Todos os campos são obrigatórios');
    }
    
    // Prepara a query de atualização
    $sql = "UPDATE desenhos SET 
            quarteirao = :quarteirao,
            quadra = :quadra,
            lote = :lote,
            cor = :cor
            WHERE id = :id_marcador";
    
    $stmt = $pdo->prepare($sql);
    
    // Executa a atualização
    $resultado = $stmt->execute([
        ':quarteirao' => $quarteirao,
        ':quadra' => $quadra,
        ':lote' => $lote,
        ':cor' => $cor,
        ':id_marcador' => $id_marcador
    ]);
    
    if ($resultado) {
        // Verifica se alguma linha foi afetada
        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'status' => 'sucesso',
                'mensagem' => 'Marcador editado com sucesso',
                'dados' => [
                    'id' => $id_marcador,
                    'quarteirao' => $quarteirao,
                    'quadra' => $quadra,
                    'lote' => $lote
                ]
            ]);
        } else {
            throw new Exception('Nenhum marcador foi encontrado com o ID fornecido');
        }
    } else {
        throw new Exception('Erro ao executar a atualização no banco de dados');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => $e->getMessage()
    ]);
}
?>
