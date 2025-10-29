<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

try {
    require_once 'connection.php';

    $id_desenho = isset($_POST['id_desenho']) ? intval($_POST['id_desenho']) : 0;
    $revisado = isset($_POST['revisado']) ? intval($_POST['revisado']) : 0;
    $pavimentos = isset($_POST['pavimentos']) ? trim($_POST['pavimentos']) : null;
    $utilizacao = isset($_POST['utilizacao']) ? trim($_POST['utilizacao']) : null;
    $terreo_uso = isset($_POST['terreo_uso']) ? trim($_POST['terreo_uso']) : null;
    $terreo_tipo = isset($_POST['terreo_tipo']) ? trim($_POST['terreo_tipo']) : null;
    $terreo_classificacao = isset($_POST['terreo_classificacao']) ? trim($_POST['terreo_classificacao']) : null;
    $terreo_area = isset($_POST['terreo_area']) ? trim($_POST['terreo_area']) : null;
    $demais_uso = isset($_POST['demais_uso']) ? trim($_POST['demais_uso']) : null;
    $demais_tipo = isset($_POST['demais_tipo']) ? trim($_POST['demais_tipo']) : null;
    $demais_classificacao = isset($_POST['demais_classificacao']) ? trim($_POST['demais_classificacao']) : null;
    $demais_area = isset($_POST['demais_area']) ? trim($_POST['demais_area']) : null;
    $cor = isset($_POST['cor']) ? trim($_POST['cor']) : null;
    $quadricula = isset($_POST['quadricula']) ? trim($_POST['quadricula']) : null;
    
    $usuario = isset($_SESSION['usuario'][0]) ? trim($_SESSION['usuario'][0]) : null;

    if ($id_desenho <= 0) {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'ID do desenho é obrigatório.'
        ]);
        exit;
    }

    // Verifica se já existe registro na tabela informacoes_blocos
    $sqlCheck = "SELECT id FROM informacoes_blocos WHERE id_desenhos = :id_desenho LIMIT 1";
    $stmtCheck = $pdo->prepare($sqlCheck);
    $stmtCheck->bindValue(':id_desenho', $id_desenho, PDO::PARAM_INT);
    $stmtCheck->execute();
    $existe = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if ($existe) {
        // UPDATE
        $sql = "UPDATE informacoes_blocos 
                SET revisado = :revisado,
                    pavimentos = :pavimentos,
                    utilizacao = :utilizacao,
                    terreo_uso = :terreo_uso,
                    terreo_tipo = :terreo_tipo,
                    terreo_classificacao = :terreo_classificacao,
                    terreo_area = :terreo_area,
                    demais_uso = :demais_uso,
                    demais_tipo = :demais_tipo,
                    demais_classificacao = :demais_classificacao,
                    demais_area = :demais_area,
                    usuario = :usuario,
                    quadricula = :quadricula
                WHERE id_desenhos = :id_desenho";
    } else {
        // INSERT
        $sql = "INSERT INTO informacoes_blocos 
                (id_desenhos, revisado, pavimentos, utilizacao, terreo_uso, terreo_tipo, 
                 terreo_classificacao, terreo_area, demais_uso, demais_tipo, 
                 demais_classificacao, demais_area, usuario, quadricula)
                VALUES 
                (:id_desenho, :revisado, :pavimentos, :utilizacao, :terreo_uso, :terreo_tipo,
                 :terreo_classificacao, :terreo_area, :demais_uso, :demais_tipo,
                 :demais_classificacao, :demais_area, :usuario, :quadricula)";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':id_desenho', $id_desenho, PDO::PARAM_INT);
    $stmt->bindValue(':revisado', $revisado, PDO::PARAM_INT);
    $stmt->bindValue(':pavimentos', $pavimentos !== '' ? $pavimentos : null, PDO::PARAM_INT);
    $stmt->bindValue(':utilizacao', $utilizacao !== '' ? $utilizacao : null, PDO::PARAM_STR);
    $stmt->bindValue(':terreo_uso', $terreo_uso !== '' ? $terreo_uso : null, PDO::PARAM_STR);
    $stmt->bindValue(':terreo_tipo', $terreo_tipo !== '' ? $terreo_tipo : null, PDO::PARAM_STR);
    $stmt->bindValue(':terreo_classificacao', $terreo_classificacao !== '' ? $terreo_classificacao : null, PDO::PARAM_STR);
    $stmt->bindValue(':terreo_area', $terreo_area !== '' ? $terreo_area : null, PDO::PARAM_STR);
    $stmt->bindValue(':demais_uso', $demais_uso !== '' ? $demais_uso : null, PDO::PARAM_STR);
    $stmt->bindValue(':demais_tipo', $demais_tipo !== '' ? $demais_tipo : null, PDO::PARAM_STR);
    $stmt->bindValue(':demais_classificacao', $demais_classificacao !== '' ? $demais_classificacao : null, PDO::PARAM_STR);
    $stmt->bindValue(':demais_area', $demais_area !== '' ? $demais_area : null, PDO::PARAM_STR);
    $stmt->bindValue(':usuario', $usuario !== '' ? $usuario : null, PDO::PARAM_STR);
    $stmt->bindValue(':quadricula', $quadricula !== '' ? $quadricula : null, PDO::PARAM_STR);
    $stmt->execute();

    // Atualiza a cor do desenho na tabela desenhos se foi fornecida
    if ($cor) {
        $sqlCor = "UPDATE desenhos SET cor_usuario = :cor WHERE id = :id_desenho";
        $stmtCor = $pdo->prepare($sqlCor);
        $stmtCor->bindValue(':cor', $cor, PDO::PARAM_STR);
        $stmtCor->bindValue(':id_desenho', $id_desenho, PDO::PARAM_INT);
        $stmtCor->execute();
    }

    echo json_encode([
        'status' => 'sucesso',
        'mensagem' => 'Dados salvos com sucesso!'
    ]);
    exit;

} catch (Throwable $e) {
    echo json_encode([
        'status' => 'erro', 
        'mensagem' => 'Falha ao salvar dados: ' . $e->getMessage()
    ]);
    exit;
}
?>

