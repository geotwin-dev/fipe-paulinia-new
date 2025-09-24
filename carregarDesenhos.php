<?php
header('Content-Type: application/json; charset=utf-8');

try {
    require_once 'connection.php';

    // Aceita 'quadricula' (preferido) ou 'ortofoto' (legado)
    $quadricula = $_GET['ortofoto'];
    
    if (!$quadricula) {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Parâmetro quadricula é obrigatório.'
        ]);
        exit;
    }

    $sql = "SELECT *
            FROM desenhos
            WHERE quadricula = :quadricula";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':quadricula', $quadricula, PDO::PARAM_STR);
    $stmt->execute();

    $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'sucesso', 'dados' => $dados]);
    exit;

} catch (Throwable $e) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Falha ao carregar: ' . $e->getMessage()]);
    exit;
}
