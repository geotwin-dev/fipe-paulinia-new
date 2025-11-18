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
    
    if (!isset($data['acao'])) {
        echo json_encode(['success' => false, 'message' => 'Ação não especificada']);
        exit;
    }
    
    $acao = $data['acao'];
    $id = isset($data['id']) ? (int)$data['id'] : 0;
    
    // Pega o nome do usuário da sessão (que é um array)
    $usuario = 'Sistema';
    if (isset($_SESSION['usuario'])) {
        if (is_array($_SESSION['usuario'])) {
            $usuario = $_SESSION['usuario'][0] ?? 'Sistema'; // [0] é o nome
        } else {
            $usuario = $_SESSION['usuario'];
        }
    }
    
    if ($acao === 'editar') {
        if (!isset($data['nome']) || empty(trim($data['nome']))) {
            echo json_encode(['success' => false, 'message' => 'Nome é obrigatório']);
            exit;
        }
        
        $nome = trim($data['nome']);
        $pertence = isset($data['pertence']) && !empty($data['pertence']) ? (int)$data['pertence'] : null;
        
        // Busca os dados atuais
        $stmtAtual = $pdo->prepare("SELECT * FROM camadas_novas WHERE id = ?");
        $stmtAtual->execute([$id]);
        $atual = $stmtAtual->fetch(PDO::FETCH_ASSOC);
        
        if (!$atual) {
            echo json_encode(['success' => false, 'message' => 'Registro não encontrado']);
            exit;
        }
        
        $tipo = $atual['tipo'];
        
        // Se for subcamada e mudou a camada pai, valida
        if ($tipo === 'subcamada' && $pertence) {
            $stmtValida = $pdo->prepare("SELECT id FROM camadas_novas WHERE id = ? AND tipo = 'camada' AND status = 1");
            $stmtValida->execute([$pertence]);
            if (!$stmtValida->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Camada pai não encontrada']);
                exit;
            }
        }
        
        // Verifica se já existe outra camada/subcamada com o mesmo nome
        if ($tipo === 'subcamada' && $pertence) {
            $stmtExiste = $pdo->prepare("SELECT id FROM camadas_novas WHERE nome = ? AND tipo = 'subcamada' AND pertence = ? AND id != ? AND status = 1");
            $stmtExiste->execute([$nome, $pertence, $id]);
        } else {
            $stmtExiste = $pdo->prepare("SELECT id FROM camadas_novas WHERE nome = ? AND tipo = 'camada' AND (pertence IS NULL OR pertence = 0) AND id != ? AND status = 1");
            $stmtExiste->execute([$nome, $id]);
        }
        
        if ($stmtExiste->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Já existe uma ' . ($tipo === 'subcamada' ? 'subcamada' : 'camada') . ' com este nome']);
            exit;
        }
        
        // Atualiza
        $stmt = $pdo->prepare("
            UPDATE camadas_novas 
            SET nome = ?, pertence = ?, alterado = NOW(), usuario2 = ?
            WHERE id = ?
        ");
        
        $result = $stmt->execute([$nome, $pertence, $usuario, $id]);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Atualizado com sucesso']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao atualizar']);
        }
        
    } elseif ($acao === 'deletar') {
        // Soft delete (muda status para 0)
        $stmt = $pdo->prepare("
            UPDATE camadas_novas 
            SET status = 0, alterado = NOW(), usuario2 = ?
            WHERE id = ?
        ");
        
        $result = $stmt->execute([$usuario, $id]);
        
        if ($result) {
            // Verifica se é uma camada (para deletar subcamadas também)
            $stmtTipo = $pdo->prepare("SELECT tipo FROM camadas_novas WHERE id = ?");
            $stmtTipo->execute([$id]);
            $registro = $stmtTipo->fetch(PDO::FETCH_ASSOC);
            
            $mensagem = 'Deletado com sucesso';
            
            // Se for camada, também desativa as subcamadas
            if ($registro && $registro['tipo'] === 'camada') {
                $stmtSub = $pdo->prepare("
                    UPDATE camadas_novas 
                    SET status = 0, alterado = NOW(), usuario2 = ?
                    WHERE pertence = ? AND tipo = 'subcamada' AND status = 1
                ");
                $stmtSub->execute([$usuario, $id]);
                
                // Conta quantas subcamadas foram deletadas
                $subcamadasDeletadas = $stmtSub->rowCount();
                if ($subcamadasDeletadas > 0) {
                    $mensagem = "Camada e {$subcamadasDeletadas} subcamada(s) deletada(s) com sucesso";
                }
            }
            
            echo json_encode(['success' => true, 'message' => $mensagem]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao deletar']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Ação inválida']);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro: ' . $e->getMessage()
    ]);
}
?>

