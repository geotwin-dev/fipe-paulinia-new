<?php
session_start();
include("../connection.php");

header('Content-Type: application/json');

if (!isset($_SESSION['usuario']) || !is_array($_SESSION['usuario']) || count($_SESSION['usuario']) < 2) {
    echo json_encode(['data' => []]);
    exit();
}

$usuario_email = $_SESSION['usuario'][1];

try {
    $sql = "SELECT id, titulo, categoria, prioridade, status, data_criacao 
            FROM helpdesk 
            WHERE usuario_email = :usuario_email 
            ORDER BY data_criacao DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':usuario_email', $usuario_email, PDO::PARAM_STR);
    $stmt->execute();
    
    $chamados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['data' => $chamados]);
    
} catch (PDOException $e) {
    echo json_encode(['data' => []]);
}
?>
