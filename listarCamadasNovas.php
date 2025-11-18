<?php
require_once 'connection.php';

header('Content-Type: application/json; charset=utf-8');

if ($pdo === null) {
    echo json_encode(['success' => false, 'message' => 'Erro de conexão com banco de dados']);
    exit;
}

try {
    // Busca todas as camadas (tipo = 'camada' e pertence IS NULL)
    $stmtCamadas = $pdo->prepare("SELECT * FROM camadas_novas WHERE tipo = 'camada' AND (pertence IS NULL OR pertence = 0) AND status = 1 ORDER BY nome ASC");
    $stmtCamadas->execute();
    $camadas = $stmtCamadas->fetchAll(PDO::FETCH_ASSOC);
    
    // Para cada camada, busca suas subcamadas
    foreach ($camadas as &$camada) {
        $stmtSubcamadas = $pdo->prepare("SELECT * FROM camadas_novas WHERE tipo = 'subcamada' AND pertence = ? AND status = 1 ORDER BY nome ASC");
        $stmtSubcamadas->execute([$camada['id']]);
        $camada['subcamadas'] = $stmtSubcamadas->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode([
        'success' => true,
        'camadas' => $camadas
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar camadas: ' . $e->getMessage()
    ]);
}
?>

