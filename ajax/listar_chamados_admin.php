<?php
session_start();
include("../connection.php");

header('Content-Type: application/json');

// Verificar se o usuário está logado e é admin
if (!isset($_SESSION['usuario']) || !is_array($_SESSION['usuario']) || count($_SESSION['usuario']) < 4) {
    echo json_encode(['data' => []]);
    exit();
}

// Verificar se o usuário é admin (4º item do array = 1)
if ($_SESSION['usuario'][3] != 1) {
    echo json_encode(['data' => []]);
    exit();
}

try {
    $sql = "SELECT id, usuario_nome, usuario_email, titulo, categoria, prioridade, status, data_criacao
            FROM helpdesk
            ORDER BY data_criacao DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $chamados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['data' => $chamados]);
} catch (PDOException $e) {
    echo json_encode(['data' => []]);
}
?>
