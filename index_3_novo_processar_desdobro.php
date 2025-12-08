<?php
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Usuário não autenticado'
    ]);
    exit();
}

header('Content-Type: application/json; charset=utf-8');

// Incluir arquivo de conexão
include("connection.php");

if ($pdo === null) {
    echo json_encode([
        'success' => false,
        'message' => 'Falha na conexão com o banco de dados'
    ]);
    exit();
}

try {
    // Verificar método
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido');
    }

    // Receber dados JSON
    $input = file_get_contents('php://input');
    $dados = json_decode($input, true);

    if (!$dados) {
        throw new Exception('Dados inválidos');
    }

    $usuario = $_SESSION['usuario'][0] ?? 'desconhecido';
    $dataHoraAtual = date('Y-m-d H:i:s');

    // Iniciar transação
    $pdo->beginTransaction();

    // ========================================================================
    // 1. ATUALIZAR DESENHO ORIGINAL (polígono) - Status = 0
    // ========================================================================
    $sqlUpdatePoligono = "UPDATE desenhos 
                          SET status = 0,
                              ult_modificacao = :ult_modificacao,
                              user = :user,
                              oque = 'desdobrado'
                          WHERE id = :id";
    
    $stmtUpdatePoligono = $pdo->prepare($sqlUpdatePoligono);
    $stmtUpdatePoligono->execute([
        'id' => $dados['poligono_original']['id'],
        'ult_modificacao' => $dataHoraAtual,
        'user' => $usuario
    ]);

    // ========================================================================
    // 2. ATUALIZAR MARCADOR ORIGINAL - Status = 0
    // ========================================================================
    if (isset($dados['marcador_original']) && $dados['marcador_original']['id']) {
        $sqlUpdateMarcador = "UPDATE desenhos 
                              SET status = 0,
                                  ult_modificacao = :ult_modificacao,
                                  user = :user,
                                  oque = 'desdobrado'
                              WHERE id = :id";
        
        $stmtUpdateMarcador = $pdo->prepare($sqlUpdateMarcador);
        $stmtUpdateMarcador->execute([
            'id' => $dados['marcador_original']['id'],
            'ult_modificacao' => $dataHoraAtual,
            'user' => $usuario
        ]);
    }

    // ========================================================================
    // 3. INSERIR 2 NOVOS POLÍGONOS
    // ========================================================================
    $novosPoligonos = [];
    
    foreach ($dados['novos_poligonos'] as $novoPoligono) {
        $sqlInsertPoly = "INSERT INTO desenhos 
                          (data_hora, usuario, quadricula, camada, quarteirao, tipo, cor, coordenadas, status, ult_modificacao, user, oque)
                          VALUES 
                          (:data_hora, :usuario, :quadricula, :camada, :quarteirao, :tipo, :cor, :coordenadas, 1, :ult_modificacao, :user, 'novo_desdobro')";
        
        $stmtInsertPoly = $pdo->prepare($sqlInsertPoly);
        $stmtInsertPoly->execute([
            'data_hora' => $dataHoraAtual,
            'usuario' => $usuario,
            'quadricula' => $dados['poligono_original']['quadricula'],
            'camada' => 'poligono_lote',
            'quarteirao' => $dados['poligono_original']['quarteirao'],
            'tipo' => 'poligono',
            'cor' => 'red',
            'coordenadas' => json_encode($novoPoligono['coordenadas']),
            'ult_modificacao' => $dataHoraAtual,
            'user' => $usuario
        ]);
        
        $novosPoligonos[] = $pdo->lastInsertId();
    }

    // ========================================================================
    // 4. INSERIR 2 NOVOS MARCADORES
    // ========================================================================
    $novosMarcadores = [];
    
    foreach ($dados['novos_marcadores'] as $novoMarcador) {
        $sqlInsertMarc = "INSERT INTO desenhos 
                          (data_hora, usuario, quadricula, camada, quarteirao, tipo, cor, coordenadas, status, ult_modificacao, user, oque)
                          VALUES 
                          (:data_hora, :usuario, :quadricula, :camada, :quarteirao, :tipo, :cor, :coordenadas, 1, :ult_modificacao, :user, 'novo_desdobro')";
        
        $stmtInsertMarc = $pdo->prepare($sqlInsertMarc);
        $stmtInsertMarc->execute([
            'data_hora' => $dataHoraAtual,
            'usuario' => $usuario,
            'quadricula' => $dados['poligono_original']['quadricula'],
            'camada' => 'marcador_quadra',
            'quarteirao' => $dados['poligono_original']['quarteirao'],
            'tipo' => 'marcador',
            'cor' => 'red',
            'coordenadas' => json_encode($novoMarcador['coordenadas']),
            'ult_modificacao' => $dataHoraAtual,
            'user' => $usuario
        ]);
        
        $novosMarcadores[] = $pdo->lastInsertId();
    }

    // ========================================================================
    // 5. INSERIR NA TABELA DESDOBROS_UNIFICACOES (4 linhas)
    // ========================================================================
    $sqlInsertDesdobro = "INSERT INTO desdobros_unificacoes 
                          (tipo, datahora, usuario, quadricula, quarteirao, id_desenho_anterior, id_desenho_posterior)
                          VALUES 
                          (:tipo, :datahora, :usuario, :quadricula, :quarteirao, :id_anterior, :id_posterior)";
    
    $stmtInsertDesdobro = $pdo->prepare($sqlInsertDesdobro);
    
    // Linha 1: Polígono antigo → Polígono novo 1
    $stmtInsertDesdobro->execute([
        'tipo' => 'desdobro',
        'datahora' => $dataHoraAtual,
        'usuario' => $usuario,
        'quadricula' => $dados['poligono_original']['quadricula'],
        'quarteirao' => $dados['poligono_original']['quarteirao'],
        'id_anterior' => $dados['poligono_original']['id'],
        'id_posterior' => $novosPoligonos[0]
    ]);
    
    // Linha 2: Polígono antigo → Polígono novo 2
    $stmtInsertDesdobro->execute([
        'tipo' => 'desdobro',
        'datahora' => $dataHoraAtual,
        'usuario' => $usuario,
        'quadricula' => $dados['poligono_original']['quadricula'],
        'quarteirao' => $dados['poligono_original']['quarteirao'],
        'id_anterior' => $dados['poligono_original']['id'],
        'id_posterior' => $novosPoligonos[1]
    ]);
    
    // Linha 3: Marcador antigo → Marcador novo 1
    if (isset($dados['marcador_original']) && $dados['marcador_original']['id']) {
        $stmtInsertDesdobro->execute([
            'tipo' => 'desdobro',
            'datahora' => $dataHoraAtual,
            'usuario' => $usuario,
            'quadricula' => $dados['poligono_original']['quadricula'],
            'quarteirao' => $dados['poligono_original']['quarteirao'],
            'id_anterior' => $dados['marcador_original']['id'],
            'id_posterior' => $novosMarcadores[0]
        ]);
        
        // Linha 4: Marcador antigo → Marcador novo 2
        $stmtInsertDesdobro->execute([
            'tipo' => 'desdobro',
            'datahora' => $dataHoraAtual,
            'usuario' => $usuario,
            'quadricula' => $dados['poligono_original']['quadricula'],
            'quarteirao' => $dados['poligono_original']['quarteirao'],
            'id_anterior' => $dados['marcador_original']['id'],
            'id_posterior' => $novosMarcadores[1]
        ]);
    }

    // Commit da transação
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Desdobro realizado com sucesso',
        'novos_poligonos' => $novosPoligonos,
        'novos_marcadores' => $novosMarcadores
    ]);

} catch (Exception $e) {
    // Rollback em caso de erro
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

