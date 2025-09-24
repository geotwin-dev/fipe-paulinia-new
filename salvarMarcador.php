<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

try {
    require_once 'connection.php'; // cria $pdo (PDO)

    // Coleta e valida os campos necessários
    $lat         = isset($_POST['lat']) ? trim($_POST['lat']) : null;
    $lng         = isset($_POST['lng']) ? trim($_POST['lng']) : null;
    $id_quadra   = isset($_POST['id_quadra']) ? trim($_POST['id_quadra']) : null;
    $numero      = isset($_POST['numero']) ? trim($_POST['numero']) : null;
    $quadricula  = isset($_POST['ortofoto']) ? trim($_POST['ortofoto']) : null;
    $quarteirao  = isset($_POST['quarteirao']) ? trim($_POST['quarteirao']) : null;
    $quadra      = isset($_POST['quadra']) ? trim($_POST['quadra']) : null;
    $usuario     = null; // por enquanto
    $cor         = isset($_POST['cor']) ? trim($_POST['cor']) : 'black';

    if (!$lat || !$lng || !$id_quadra || !$quadricula) {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Campos obrigatórios ausentes: lat, lng, id_quadra, ortofoto.'
        ]);
        exit;
    }

    // Monta coordenadas como JSON
    $coordenadas = json_encode([['lat' => floatval($lat), 'lng' => floatval($lng)]]);

    $sql = "INSERT INTO desenhos (data_hora, usuario, quadricula, id_desenho, camada, quarteirao, quadra, lote, tipo, cor, coordenadas)
            VALUES (NOW(), :usuario, :quadricula, :id_desenho, :camada, :quarteirao, :quadra, :lote, :tipo, :cor, :coordenadas)";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':usuario',     $usuario, PDO::PARAM_NULL | PDO::PARAM_STR);
    $stmt->bindValue(':quadricula',  mb_substr($quadricula, 0, 4), PDO::PARAM_STR);
    $stmt->bindValue(':id_desenho',  $id_quadra, PDO::PARAM_STR);
    $stmt->bindValue(':camada',      'marcador_quadra', PDO::PARAM_STR);
    $stmt->bindValue(':quarteirao',  $quarteirao, PDO::PARAM_STR);
    $stmt->bindValue(':quadra',      $quadra, PDO::PARAM_STR);
    $stmt->bindValue(':lote',        $numero, PDO::PARAM_STR);
    $stmt->bindValue(':tipo',        "marcador", PDO::PARAM_STR);
    $stmt->bindValue(':cor',         $cor, PDO::PARAM_STR);
    $stmt->bindValue(':coordenadas', $coordenadas, PDO::PARAM_STR);
    

    $stmt->execute();

    echo json_encode([
        'status' => 'sucesso',
        'id' => $pdo->lastInsertId(),
        'camada' => 'marcador_quadra'
    ]);
    exit;

} catch (Throwable $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Falha ao salvar: ' . $e->getMessage()
    ]);
    exit;
}
