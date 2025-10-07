<?php
session_start();
include("connection.php");

header('Content-Type: application/json');

// Recebe os dados via POST
$id_desenho = isset($_POST['id_desenho']) ? intval($_POST['id_desenho']) : 0;
$cor = isset($_POST['cor']) ? $_POST['cor'] : '';

// Validação
if ($id_desenho <= 0) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'ID do desenho inválido'
    ]);
    exit;
}

if (empty($cor)) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Cor não informada'
    ]);
    exit;
}

try {
    // Atualiza a cor do desenho
    $stmt = $pdo->prepare("UPDATE desenhos SET cor_usuario = :cor WHERE id = :id");
    $stmt->bindParam(':cor', $cor);
    $stmt->bindParam(':id', $id_desenho);
    
    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'sucesso',
            'mensagem' => 'Cor atualizada com sucesso',
            'id_desenho' => $id_desenho,
            'cor' => $cor
        ]);
    } else {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Erro ao atualizar a cor'
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro no banco de dados: ' . $e->getMessage()
    ]);
}
?>

