<?php

include 'connection.php';

$quarteirao = $_POST['quarteirao'];

try {
    $stmt = $pdo->prepare("SELECT cara_quarteirao, quadraAdicionada, quadra, lote FROM `cadastro` WHERE cara_quarteirao = :a ORDER BY `cadastro`.`quadra` ASC;
");
    $stmt->bindParam(':a', $quarteirao);
    $stmt->execute();

    $listaLotes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $listaLotes = [];
    error_log('Erro ao buscar lotes: ' . $e->getMessage());
}

echo json_encode($listaLotes);

?>