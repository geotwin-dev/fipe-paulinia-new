<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

try {
    require_once 'connection.php'; // cria $pdo (PDO)

    if (!isset($_POST['id']) || empty($_POST['id'])) {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'ID do marcador não fornecido'
        ]);
        exit;
    }

    $id = intval($_POST['id']);
    $usuario = isset($_SESSION['usuario'][0]) ? trim($_SESSION['usuario'][0]) : null;

    // Soft delete: atualiza status para 0
    $sql = "UPDATE desenhos 
            SET status = 0, 
                ult_modificacao = NOW(), 
                user = :user,
                oque = 'DELETE'
            WHERE id = :id AND camada = 'marcador_quadra' AND status = 1";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->bindValue(':user', $usuario !== '' ? $usuario : null, PDO::PARAM_NULL | PDO::PARAM_STR);

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

