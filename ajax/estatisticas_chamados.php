<?php
session_start();
include("../connection.php");

header('Content-Type: application/json');

if (!isset($_SESSION['usuario']) || !is_array($_SESSION['usuario']) || count($_SESSION['usuario']) < 2) {
    echo json_encode(['success' => false, 'message' => 'Usuário não logado']);
    exit();
}

$usuario_email = $_SESSION['usuario'][1];

try {
    // Total de chamados
    $sql_total = "SELECT COUNT(*) as total FROM helpdesk WHERE usuario_email = :usuario_email";
    $stmt_total = $pdo->prepare($sql_total);
    $stmt_total->bindParam(':usuario_email', $usuario_email, PDO::PARAM_STR);
    $stmt_total->execute();
    $total = $stmt_total->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Chamados em aberto
    $sql_abertos = "SELECT COUNT(*) as abertos FROM helpdesk WHERE usuario_email = :usuario_email AND status = 'aberto'";
    $stmt_abertos = $pdo->prepare($sql_abertos);
    $stmt_abertos->bindParam(':usuario_email', $usuario_email, PDO::PARAM_STR);
    $stmt_abertos->execute();
    $abertos = $stmt_abertos->fetch(PDO::FETCH_ASSOC)['abertos'];
    
    // Chamados resolvidos
    $sql_resolvidos = "SELECT COUNT(*) as resolvidos FROM helpdesk WHERE usuario_email = :usuario_email AND status = 'resolvido'";
    $stmt_resolvidos = $pdo->prepare($sql_resolvidos);
    $stmt_resolvidos->bindParam(':usuario_email', $usuario_email, PDO::PARAM_STR);
    $stmt_resolvidos->execute();
    $resolvidos = $stmt_resolvidos->fetch(PDO::FETCH_ASSOC)['resolvidos'];
    
    // Chamados fechados
    $sql_fechados = "SELECT COUNT(*) as fechados FROM helpdesk WHERE usuario_email = :usuario_email AND status = 'fechado'";
    $stmt_fechados = $pdo->prepare($sql_fechados);
    $stmt_fechados->bindParam(':usuario_email', $usuario_email, PDO::PARAM_STR);
    $stmt_fechados->execute();
    $fechados = $stmt_fechados->fetch(PDO::FETCH_ASSOC)['fechados'];
    
    echo json_encode([
        'success' => true,
        'data' => [
            'total' => (int)$total,
            'abertos' => (int)$abertos,
            'resolvidos' => (int)$resolvidos,
            'fechados' => (int)$fechados
        ]
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao carregar estatísticas']);
}
?>
