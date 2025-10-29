<?php
session_start();
include("../connection.php");

header('Content-Type: application/json');

// Verificar se o usuário está logado e é admin
if (!isset($_SESSION['usuario']) || !is_array($_SESSION['usuario']) || count($_SESSION['usuario']) < 4) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autorizado']);
    exit();
}

// Verificar se o usuário é admin (4º item do array = 1)
if ($_SESSION['usuario'][3] != 1) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autorizado']);
    exit();
}

try {
    // Total de chamados
    $sql_total = "SELECT COUNT(*) as total FROM helpdesk";
    $stmt_total = $pdo->prepare($sql_total);
    $stmt_total->execute();
    $total = $stmt_total->fetch(PDO::FETCH_ASSOC)['total'];

    // Chamados abertos
    $sql_abertos = "SELECT COUNT(*) as abertos FROM helpdesk WHERE status = 'aberto'";
    $stmt_abertos = $pdo->prepare($sql_abertos);
    $stmt_abertos->execute();
    $abertos = $stmt_abertos->fetch(PDO::FETCH_ASSOC)['abertos'];

    // Chamados resolvidos
    $sql_resolvidos = "SELECT COUNT(*) as resolvidos FROM helpdesk WHERE status = 'resolvido'";
    $stmt_resolvidos = $pdo->prepare($sql_resolvidos);
    $stmt_resolvidos->execute();
    $resolvidos = $stmt_resolvidos->fetch(PDO::FETCH_ASSOC)['resolvidos'];

    // Chamados fechados
    $sql_fechados = "SELECT COUNT(*) as fechados FROM helpdesk WHERE status = 'fechado'";
    $stmt_fechados = $pdo->prepare($sql_fechados);
    $stmt_fechados->execute();
    $fechados = $stmt_fechados->fetch(PDO::FETCH_ASSOC)['fechados'];

    echo json_encode([
        'success' => true,
        'data' => [
            'total' => $total,
            'abertos' => $abertos,
            'resolvidos' => $resolvidos,
            'fechados' => $fechados
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao carregar estatísticas']);
}
?>
