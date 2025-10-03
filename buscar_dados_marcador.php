<?php
header('Content-Type: application/json; charset=utf-8');

try {
    require_once 'connection.php';

    $idMarcador = $_GET['id_marcador'];
    
    if (!$idMarcador) {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'ID do marcador é obrigatório.'
        ]);
        exit;
    }

    // Busca dados do marcador na tabela desenhos
    $sqlDesenhos = "SELECT id, quarteirao, quadra, lote, id_desenho, quadricula 
                    FROM desenhos 
                    WHERE id = :id_marcador 
                    AND camada = 'marcador_quadra' 
                    AND status = 1";
    
    $stmtDesenhos = $pdo->prepare($sqlDesenhos);
    $stmtDesenhos->bindValue(':id_marcador', $idMarcador, PDO::PARAM_INT);
    $stmtDesenhos->execute();
    
    $dadosDesenhos = $stmtDesenhos->fetch(PDO::FETCH_ASSOC);
    
    if (!$dadosDesenhos) {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Marcador não encontrado.'
        ]);
        exit;
    }

    // Busca dados correspondentes na tabela cadastro
    $sqlCadastro = "SELECT * 
                    FROM cadastro 
                    WHERE cara_quarteirao = :quarteirao 
                    AND quadra = :quadra 
                    AND lote = :lote 
                    LIMIT 1";
    
    $stmtCadastro = $pdo->prepare($sqlCadastro);
    $stmtCadastro->bindValue(':quarteirao', $dadosDesenhos['quarteirao'], PDO::PARAM_STR);
    $stmtCadastro->bindValue(':quadra', $dadosDesenhos['quadra'], PDO::PARAM_STR);
    $stmtCadastro->bindValue(':lote', $dadosDesenhos['lote'], PDO::PARAM_STR);
    $stmtCadastro->execute();
    
    $dadosCadastro = $stmtCadastro->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'sucesso',
        'dados' => [
            'desenhos' => $dadosDesenhos,
            'cadastro' => $dadosCadastro ?: null
        ]
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
