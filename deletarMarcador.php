<?php
header('Content-Type: application/json');
include("connection.php");

try {
    if (!isset($_POST['id'])) {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'ID do marcador não fornecido'
        ]);
        exit;
    }

    $id = $_POST['id'];

    // Prepara a query para deletar o marcador
    $stmt = $pdo->prepare("DELETE FROM desenhos WHERE id = :id AND camada = 'marcador_quadra'");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        $rowsAffected = $stmt->rowCount();
        
        if ($rowsAffected > 0) {
            echo json_encode([
                'status' => 'sucesso',
                'mensagem' => 'Marcador deletado com sucesso',
                'id' => $id
            ]);
        } else {
            echo json_encode([
                'status' => 'erro',
                'mensagem' => 'Marcador não encontrado ou já foi deletado'
            ]);
        }
    } else {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Erro ao executar a operação no banco de dados'
        ]);
    }

} catch (PDOException $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro no banco de dados: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro inesperado: ' . $e->getMessage()
    ]);
}
?>
