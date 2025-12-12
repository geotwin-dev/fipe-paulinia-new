<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

try {
    require_once 'connection.php'; // cria $pdo (PDO)

    // Coleta e valida os campos necessários
    $lat         = isset($_POST['lat']) ? trim($_POST['lat']) : null;
    $lng         = isset($_POST['lng']) ? trim($_POST['lng']) : null;
    $numero      = isset($_POST['numero']) ? trim($_POST['numero']) : null;
    $quadricula  = isset($_POST['ortofoto']) ? trim($_POST['ortofoto']) : null;
    $quarteirao  = isset($_POST['quarteirao']) ? trim($_POST['quarteirao']) : null;
    $cor         = isset($_POST['cor']) ? trim($_POST['cor']) : 'black';
    $usuario     = isset($_SESSION['usuario'][0]) ? trim($_SESSION['usuario'][0]) : null;

    // Valida campos obrigatórios
    if (!$lat || !$lng || !$quadricula) {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Campos obrigatórios ausentes: lat, lng, ortofoto.'
        ]);
        exit;
    }

    // A coluna quadricula é varchar(4) — garante no máximo 4 caracteres
    $quadricula = mb_substr($quadricula, 0, 4);

    // Monta coordenadas como JSON
    $coordenadas = json_encode([['lat' => floatval($lat), 'lng' => floatval($lng)]]);

    // INSERT seguindo o mesmo padrão do salvarDesenho.php
    // id_desenho sempre null, quadra não é salva, status=1, ult_modificacao=NOW(), user=usuario, oque='INSERT'
    $sql = "INSERT INTO desenhos (data_hora, usuario, quadricula, id_desenho, camada, quarteirao, lote, tipo, cor, coordenadas, status, ult_modificacao, user, oque)
            VALUES (NOW(), :usuario, :quadricula, NULL, :camada, :quarteirao, :lote, :tipo, :cor, :coordenadas, 1, NOW(), :user, 'INSERT')";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':usuario',     $usuario !== '' ? $usuario : null, PDO::PARAM_NULL | PDO::PARAM_STR);
    $stmt->bindValue(':quadricula',  $quadricula, PDO::PARAM_STR);
    $stmt->bindValue(':camada',      'marcador_quadra', PDO::PARAM_STR);
    $stmt->bindValue(':quarteirao',  $quarteirao !== '' ? $quarteirao : null, PDO::PARAM_NULL | PDO::PARAM_STR);
    $stmt->bindValue(':lote',        $numero !== '' ? $numero : null, PDO::PARAM_NULL | PDO::PARAM_STR);
    $stmt->bindValue(':tipo',        "marcador", PDO::PARAM_STR);
    $stmt->bindValue(':cor',         $cor, PDO::PARAM_STR);
    $stmt->bindValue(':coordenadas', $coordenadas, PDO::PARAM_STR);
    $stmt->bindValue(':user',        $usuario !== '' ? $usuario : null, PDO::PARAM_NULL | PDO::PARAM_STR);

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
