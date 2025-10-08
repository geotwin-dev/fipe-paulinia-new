<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

try {
    require_once 'connection.php'; // cria $pdo (PDO)

    // Coleta e valida os campos necessários
    $tipo         = isset($_POST['tipo']) ? trim($_POST['tipo']) : null;               // 'poligono' ou 'linha'
    $cor          = isset($_POST['cor']) ? trim($_POST['cor']) : null;
    $coordenadas  = isset($_POST['coordenadas']) ? trim($_POST['coordenadas']) : null; // JSON string
    $quadricula   = isset($_POST['ortofoto']) ? trim($_POST['ortofoto']) : null;       // usar o 'card' enviado
    $id_desenho   = isset($_POST['identificador']) ? trim($_POST['identificador']) : null; // identificador da quadra/linha
    $usuario      = isset($_SESSION['usuario'][0]) ? trim($_SESSION['usuario'][0]) : null;

    if (!$tipo || !$cor || !$coordenadas || !$quadricula) {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Campos obrigatórios ausentes: tipo, cor, coordenadas, ortofoto (quadricula).',
            'dados' => [$tipo, $cor, $coordenadas, $quadricula, $id_desenho, $usuario]
        ]);
        exit;
    }

    // Se for polígono, exigimos identificador (conforme seu fluxo atual)
    if ($tipo === 'poligono' && ($id_desenho === null || $id_desenho === '')) {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Identificador é obrigatório para polígono.'
        ]);
        exit;
    }

    // A coluna quadricula é varchar(4) — garante no máximo 4 caracteres
    $quadricula = mb_substr($quadricula, 0, 4);

    if($tipo == "poligono"){
        $camada = "quadra";
    }

    if($tipo == "polilinha"){
        $camada = "lote";
    }

    if($tipo != "poligono" && $tipo != "polilinha" ){
        $camada = NULL;
    }

    // INSERT
    $sql = "INSERT INTO desenhos (data_hora, usuario, quadricula, id_desenho, camada, tipo, cor, coordenadas, status, ult_modificacao, user, oque)
            VALUES (NOW(), :usuario, :quadricula, :id_desenho, :camada, :tipo, :cor, :coordenadas, 1, NOW(), :user, 'INSERT')";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':usuario',     $usuario !== '' ? $usuario : null, PDO::PARAM_NULL | PDO::PARAM_STR);
    $stmt->bindValue(':quadricula',  $quadricula, PDO::PARAM_STR);
    $stmt->bindValue(':id_desenho',  $id_desenho !== '' ? $id_desenho : null, PDO::PARAM_NULL | PDO::PARAM_STR);
    $stmt->bindValue(':camada',      $camada, PDO::PARAM_STR);
    $stmt->bindValue(':tipo',        $tipo, PDO::PARAM_STR);
    $stmt->bindValue(':cor',         $cor, PDO::PARAM_STR);
    $stmt->bindValue(':coordenadas', $coordenadas, PDO::PARAM_STR);
    $stmt->bindValue(':user',        $usuario !== '' ? $usuario : null, PDO::PARAM_NULL | PDO::PARAM_STR);

    $stmt->execute();

    echo json_encode([
        'status' => 'sucesso',
        'id' => $pdo->lastInsertId(),
        'camada' => $camada
    ]);
    exit;

} catch (Throwable $e) {
    // Em produção, logue o erro; aqui só devolvemos a msg
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Falha ao salvar: ' . $e->getMessage()
    ]);
    exit;
}
