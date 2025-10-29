<?php
header('Content-Type: application/json; charset=utf-8');

try {
    require_once 'connection.php';

    $id_desenho = isset($_GET['id_desenho']) ? trim($_GET['id_desenho']) : null;
    
    if (!$id_desenho) {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'ID do desenho é obrigatório.'
        ]);
        exit;
    }

    // Busca dados na tabela informacoes_blocos baseado no id_desenhos
    $sql = "SELECT id, id_desenhos, revisado, pavimentos, utilizacao, 
                   terreo_uso, terreo_tipo, terreo_classificacao, terreo_area,
                   demais_uso, demais_tipo, demais_classificacao, demais_area
            FROM informacoes_blocos 
            WHERE id_desenhos = :id_desenho 
            LIMIT 1";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':id_desenho', $id_desenho, PDO::PARAM_INT);
    $stmt->execute();
    
    $dados = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$dados) {
        echo json_encode([
            'status' => 'sucesso',
            'dados' => null,
            'mensagem' => 'Nenhum dado encontrado para este desenho.'
        ]);
        exit;
    }

    echo json_encode([
        'status' => 'sucesso',
        'dados' => $dados
    ]);
    exit;

} catch (Throwable $e) {
    echo json_encode([
        'status' => 'erro', 
        'mensagem' => 'Falha ao buscar dados: ' . $e->getMessage()
    ]);
    exit;
}
?>

