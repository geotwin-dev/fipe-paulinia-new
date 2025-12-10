<?php
session_start();
include("connection.php");

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Método não permitido'
    ]);
    exit;
}

// Recebe os dados via POST
$marcadoresJson = isset($_POST['marcadores']) ? $_POST['marcadores'] : '';

// Validação
if (empty($marcadoresJson)) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Nenhum marcador informado'
    ]);
    exit;
}

// Decodifica o JSON
$marcadores = json_decode($marcadoresJson, true);

if (!is_array($marcadores) || count($marcadores) === 0) {
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
    
    foreach ($marcadores as $marcador) {
        $id = isset($marcador['id']) ? intval($marcador['id']) : 0;
        $lat = isset($marcador['lat']) ? floatval($marcador['lat']) : 0;
        $lng = isset($marcador['lng']) ? floatval($marcador['lng']) : 0;
        
        if ($id <= 0 || $lat == 0 || $lng == 0) {
            $erros[] = "Marcador com ID {$id} possui dados inválidos";
            continue;
        }
        
        // Monta coordenadas como JSON (formato usado na tabela desenhos)
        $coordenadas = json_encode([['lat' => $lat, 'lng' => $lng]]);
        
        // Atualiza as coordenadas do marcador
        $stmt = $pdo->prepare("UPDATE desenhos SET coordenadas = :coordenadas WHERE id = :id AND tipo = 'marcador'");
        $stmt->bindParam(':coordenadas', $coordenadas);
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
            if ($stmt->rowCount() > 0) {
                $atualizados++;
            } else {
                $erros[] = "Marcador ID {$id} não encontrado ou não é do tipo marcador";
            }
        } else {
            $erros[] = "Erro ao atualizar marcador ID {$id}";
        }
    }
    
    $pdo->commit();
    
    if (count($erros) > 0) {
        echo json_encode([
            'status' => 'parcial',
            'mensagem' => 'Alguns marcadores não foram atualizados',
            'atualizados' => $atualizados,
            'erros' => $erros
        ]);
    } else {
        echo json_encode([
            'status' => 'sucesso',
            'mensagem' => 'Todos os marcadores foram atualizados com sucesso',
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


