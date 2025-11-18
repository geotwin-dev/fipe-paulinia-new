<?php
session_start();
require_once 'connection.php';

header('Content-Type: application/json; charset=utf-8');

if ($pdo === null) {
    echo json_encode(['success' => false, 'message' => 'Erro de conexão com banco de dados']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    // Log para debug (remover em produção se necessário)
    error_log('Dados recebidos: ' . print_r($data, true));
    
    if (!$data) {
        error_log('Erro: dados JSON inválidos. Input recebido: ' . $input);
        echo json_encode(['success' => false, 'message' => 'Dados inválidos recebidos']);
        exit;
    }
    
    if (!isset($data['nome']) || empty(trim($data['nome']))) {
        echo json_encode(['success' => false, 'message' => 'Nome da camada é obrigatório']);
        exit;
    }
    
    $nome = trim($data['nome']);
    $tipo = isset($data['tipo']) ? $data['tipo'] : 'camada';
    $pertence = isset($data['pertence']) && !empty($data['pertence']) ? (int)$data['pertence'] : null;
    
    // Pega o nome do usuário da sessão (que é um array)
    $usuario = 'Sistema';
    if (isset($_SESSION['usuario'])) {
        if (is_array($_SESSION['usuario'])) {
            $usuario = $_SESSION['usuario'][0] ?? 'Sistema'; // [0] é o nome
        } else {
            $usuario = $_SESSION['usuario'];
        }
    }
    
    // Se for subcamada, valida se a camada pai existe
    if ($tipo === 'subcamada' && $pertence) {
        $stmtValida = $pdo->prepare("SELECT id FROM camadas_novas WHERE id = ? AND tipo = 'camada' AND status = 1");
        $stmtValida->execute([$pertence]);
        if (!$stmtValida->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Camada pai não encontrada']);
            exit;
        }
    }
    
    // Verifica se já existe uma camada/subcamada com o mesmo nome
    if ($tipo === 'subcamada' && $pertence) {
        $stmtExiste = $pdo->prepare("SELECT id FROM camadas_novas WHERE nome = ? AND tipo = 'subcamada' AND pertence = ? AND status = 1");
        $stmtExiste->execute([$nome, $pertence]);
    } else {
        $stmtExiste = $pdo->prepare("SELECT id FROM camadas_novas WHERE nome = ? AND tipo = 'camada' AND (pertence IS NULL OR pertence = 0) AND status = 1");
        $stmtExiste->execute([$nome]);
    }
    
    if ($stmtExiste->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Já existe uma ' . ($tipo === 'subcamada' ? 'subcamada' : 'camada') . ' com este nome']);
        exit;
    }
    
    // Insere a nova camada/subcamada
    $stmt = $pdo->prepare("
        INSERT INTO camadas_novas (nome, tipo, pertence, criado, usuario1, status) 
        VALUES (?, ?, ?, NOW(), ?, 1)
    ");
    
    $result = $stmt->execute([$nome, $tipo, $pertence, $usuario]);
    
    if ($result) {
        $id = $pdo->lastInsertId();
        echo json_encode([
            'success' => true,
            'message' => ($tipo === 'subcamada' ? 'Subcamada' : 'Camada') . ' salva com sucesso',
            'id' => $id
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao salvar']);
    }
    
} catch (PDOException $e) {
    error_log('Erro PDO ao salvar camada: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao salvar: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log('Erro geral ao salvar camada: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'message' => 'Erro inesperado: ' . $e->getMessage()
    ]);
}
?>

