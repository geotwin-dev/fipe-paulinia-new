<?php
session_start();
date_default_timezone_set('America/Sao_Paulo');

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
    // 1. ATUALIZAR TODOS OS DESENHOS ORIGINAIS (polígonos e marcadores) - Status = 0
    // ========================================================================
    
    $sqlUpdate = "UPDATE desenhos 
                  SET status = 0,
                      ult_modificacao = :ult_modificacao,
                      user = :user,
                      oque = 'unificado'
                  WHERE id = :id";
    
    $stmtUpdate = $pdo->prepare($sqlUpdate);
    
    // Atualizar todos os polígonos originais
    foreach ($dados['poligonos_originais'] as $poligono) {
        $stmtUpdate->execute([
            'id' => $poligono['id'],
            'ult_modificacao' => $dataHoraAtual,
            'user' => $usuario
        ]);
    }
    
    // Atualizar todos os marcadores originais
    foreach ($dados['marcadores_originais'] as $marcador) {
        if ($marcador['id']) {
            $stmtUpdate->execute([
                'id' => $marcador['id'],
                'ult_modificacao' => $dataHoraAtual,
                'user' => $usuario
            ]);
        }
    }

    // ========================================================================
    // 2. INSERIR NOVO POLÍGONO UNIFICADO
    // ========================================================================
    
    $primeiroPoligono = $dados['poligonos_originais'][0];
    
    $sqlInsertPoly = "INSERT INTO desenhos 
                      (data_hora, usuario, quadricula, camada, quarteirao, tipo, cor, coordenadas, status, ult_modificacao, user, oque)
                      VALUES 
                      (:data_hora, :usuario, :quadricula, :camada, :quarteirao, :tipo, :cor, :coordenadas, 1, :ult_modificacao, :user, 'novo_unificacao')";
    
    $stmtInsertPoly = $pdo->prepare($sqlInsertPoly);
    $stmtInsertPoly->execute([
        'data_hora' => $dataHoraAtual,
        'usuario' => $usuario,
        'quadricula' => $primeiroPoligono['quadricula'],
        'camada' => 'poligono_lote',
        'quarteirao' => $primeiroPoligono['quarteirao'],
        'tipo' => 'poligono',
        'cor' => 'red',
        'coordenadas' => json_encode($dados['novo_poligono']['coordenadas']),
        'ult_modificacao' => $dataHoraAtual,
        'user' => $usuario
    ]);
    
    $idNovoPoligono = $pdo->lastInsertId();

    // ========================================================================
    // 3. INSERIR NOVO MARCADOR UNIFICADO
    // ========================================================================
    
    $primeiroMarcador = $dados['marcadores_originais'][0] ?? null;
    
    $sqlInsertMarc = "INSERT INTO desenhos 
                      (data_hora, usuario, quadricula, camada, quarteirao, tipo, cor, coordenadas, status, ult_modificacao, user, oque)
                      VALUES 
                      (:data_hora, :usuario, :quadricula, :camada, :quarteirao, :tipo, :cor, :coordenadas, 1, :ult_modificacao, :user, 'novo_unificacao')";
    
    $stmtInsertMarc = $pdo->prepare($sqlInsertMarc);
    $stmtInsertMarc->execute([
        'data_hora' => $dataHoraAtual,
        'usuario' => $usuario,
        'quadricula' => $primeiroPoligono['quadricula'],
        'camada' => 'marcador_quadra',
        'quarteirao' => $primeiroPoligono['quarteirao'],
        'tipo' => 'marcador',
        'cor' => 'red',
        'coordenadas' => json_encode($dados['novo_marcador']['coordenadas']),
        'ult_modificacao' => $dataHoraAtual,
        'user' => $usuario
    ]);
    
    $idNovoMarcador = $pdo->lastInsertId();

    // ========================================================================
    // 4. INSERIR NA TABELA DESDOBROS_UNIFICACOES
    // ========================================================================
    
    $sqlInsertDesdobro = "INSERT INTO desdobros_unificacoes 
                          (tipo, datahora, usuario, quadricula, quarteirao, id_desenho_anterior, id_desenho_posterior, tipo_desenho)
                          VALUES 
                          (:tipo, :datahora, :usuario, :quadricula, :quarteirao, :id_anterior, :id_posterior, :tipo_desenho)";
    
    $stmtInsertDesdobro = $pdo->prepare($sqlInsertDesdobro);
    
    // Inserir linha para cada polígono antigo → polígono novo
    foreach ($dados['poligonos_originais'] as $poligono) {
        $stmtInsertDesdobro->execute([
            'tipo' => 'unificacao',
            'datahora' => $dataHoraAtual,
            'usuario' => $usuario,
            'quadricula' => $primeiroPoligono['quadricula'],
            'quarteirao' => $primeiroPoligono['quarteirao'],
            'id_anterior' => $poligono['id'],
            'id_posterior' => $idNovoPoligono,
            'tipo_desenho' => 'poligono'
        ]);
    }
    
    // Inserir linha para cada marcador antigo → marcador novo
    foreach ($dados['marcadores_originais'] as $marcador) {
        if ($marcador['id']) {
            $stmtInsertDesdobro->execute([
                'tipo' => 'unificacao',
                'datahora' => $dataHoraAtual,
                'usuario' => $usuario,
                'quadricula' => $primeiroPoligono['quadricula'],
                'quarteirao' => $primeiroPoligono['quarteirao'],
                'id_anterior' => $marcador['id'],
                'id_posterior' => $idNovoMarcador,
                'tipo_desenho' => 'marcador'
            ]);
        }
    }

    // Commit da transação
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Unificação realizada com sucesso',
        'novo_poligono' => $idNovoPoligono,
        'novo_marcador' => $idNovoMarcador
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

