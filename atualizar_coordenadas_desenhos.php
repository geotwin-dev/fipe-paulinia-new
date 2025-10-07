<?php
session_start();
include("connection.php");

header('Content-Type: application/json');

// Recebe os dados via POST
$desenhosJson = isset($_POST['desenhos']) ? $_POST['desenhos'] : '';

// Validação
if (empty($desenhosJson)) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Nenhum desenho informado'
    ]);
    exit;
}

// Decodifica o JSON
$desenhos = json_decode($desenhosJson, true);

if (!is_array($desenhos) || count($desenhos) === 0) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Dados inválidos'
    ]);
    exit;
}

try {
    $pdo->beginTransaction();
    
    $atualizados = 0;
    $erros = [];
    
    foreach ($desenhos as $desenho) {
        $id = isset($desenho['id']) ? intval($desenho['id']) : 0;
        $coordenadas = isset($desenho['coordenadas']) ? $desenho['coordenadas'] : '';
        
        if ($id <= 0 || empty($coordenadas)) {
            $erros[] = "Desenho com ID {$id} possui dados inválidos";
            continue;
        }
        
        // Atualiza as coordenadas do desenho
        $stmt = $pdo->prepare("UPDATE desenhos SET coordenadas = :coordenadas WHERE id = :id");
        $stmt->bindParam(':coordenadas', $coordenadas);
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
            $atualizados++;
        } else {
            $erros[] = "Erro ao atualizar desenho ID {$id}";
        }
    }
    
    $pdo->commit();
    
    if (count($erros) > 0) {
        echo json_encode([
            'status' => 'parcial',
            'mensagem' => 'Alguns desenhos não foram atualizados',
            'atualizados' => $atualizados,
            'erros' => $erros
        ]);
    } else {
        echo json_encode([
            'status' => 'sucesso',
            'mensagem' => 'Todos os desenhos foram atualizados com sucesso',
            'total' => $atualizados
        ]);
    }
    
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro no banco de dados: ' . $e->getMessage()
    ]);
}
?>

