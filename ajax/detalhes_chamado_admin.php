<?php
session_start();
include("../connection.php");

header('Content-Type: application/json');

// Verificar se o usuário está logado e é admin
if (!isset($_SESSION['usuario']) || !is_array($_SESSION['usuario']) || count($_SESSION['usuario']) < 4) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autorizado']);
    exit();
}

// Verificar se o usuário é admin (4º item do array = 1)
if ($_SESSION['usuario'][3] != 1) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autorizado']);
    exit();
}

$chamado_id = $_GET['id'] ?? null;

if (!$chamado_id) {
    echo json_encode(['success' => false, 'message' => 'ID do chamado não fornecido']);
    exit();
}

try {
    $sql = "SELECT * FROM helpdesk WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $chamado_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $chamado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$chamado) {
        echo json_encode(['success' => false, 'message' => 'Chamado não encontrado']);
        exit();
    }
    
    // Formatar datas
    $data_criacao = date('d/m/Y H:i', strtotime($chamado['data_criacao']));
    $data_atualizacao = date('d/m/Y H:i', strtotime($chamado['data_atualizacao']));
    $data_resposta = $chamado['data_resposta'] ? date('d/m/Y H:i', strtotime($chamado['data_resposta'])) : null;
    
    // Status badge
    $status_classes = [
        'aberto' => 'status-aberto',
        'em_andamento' => 'status-em_andamento',
        'resolvido' => 'status-resolvido',
        'fechado' => 'status-fechado'
    ];
    
    // Prioridade badge
    $prioridade_classes = [
        'baixa' => 'prioridade-baixa',
        'media' => 'prioridade-media',
        'alta' => 'prioridade-alta',
        'critica' => 'prioridade-critica'
    ];
    
    $html = '
    <div class="row">
        <div class="col-md-12">
            <div class="card border-0 bg-transparent">
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <h4 class="text-white mb-2">' . htmlspecialchars($chamado['titulo']) . '</h4>
                            <p class="text-light mb-0">Chamado #' . $chamado['id'] . '</p>
                        </div>
                        <div class="col-md-4 text-end">
                            <span class="status-badge ' . $status_classes[$chamado['status']] . '">' . 
                                ucfirst(str_replace('_', ' ', $chamado['status'])) . 
                            '</span>
                            <br>
                            <span class="prioridade-badge ' . $prioridade_classes[$chamado['prioridade']] . ' mt-2">' . 
                                ucfirst($chamado['prioridade']) . 
                            '</span>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <strong class="text-white">Usuário:</strong>
                            <p class="text-light mb-0">' . htmlspecialchars($chamado['usuario_nome']) . '</p>
                        </div>
                        <div class="col-md-6">
                            <strong class="text-white">Email:</strong>
                            <p class="text-light mb-0">' . htmlspecialchars($chamado['usuario_email']) . '</p>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <strong class="text-white">Categoria:</strong>
                            <p class="text-light mb-0">' . htmlspecialchars($chamado['categoria']) . '</p>
                        </div>
                        <div class="col-md-6">
                            <strong class="text-white">Data de Criação:</strong>
                            <p class="text-light mb-0">' . $data_criacao . '</p>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <strong class="text-white">Última Atualização:</strong>
                            <p class="text-light mb-0">' . $data_atualizacao . '</p>
                        </div>';
                        
    if ($chamado['arquivo_anexo']) {
        $anexos = json_decode($chamado['arquivo_anexo'], true);
        if ($anexos && is_array($anexos)) {
            $html .= '
                        <div class="col-md-6">
                            <strong class="text-white">Anexos:</strong>
                            <div class="mt-1">';
            
            foreach ($anexos as $anexo) {
                $email_sanitized = preg_replace('/[^a-zA-Z0-9@._-]/', '_', $chamado['usuario_email']);
                $caminho_anexo = "https://moduloautoma.ddns.net/fipe-paulinia/uploads/helpdesk/{$email_sanitized}/{$anexo}";
                $html .= '
                                <p class="text-light mb-1">
                                    <a href="' . $caminho_anexo . '" 
                                       target="_blank" class="text-primary">
                                        <i class="fas fa-paperclip me-1"></i>
                                        ' . htmlspecialchars($anexo) . '
                                    </a>
                                </p>';
            }
            
            $html .= '
                            </div>
                        </div>';
        }
    }
    
    $html .= '
                    </div>
                    
                    <div class="mb-4">
                        <strong class="text-white">Descrição:</strong>
                        <div class="mt-2 p-3 bg-dark rounded">
                            <p class="text-white mb-0" style="white-space: pre-wrap;">' . 
                                htmlspecialchars($chamado['descricao']) . 
                            '</p>
                        </div>
                    </div>';
    
    // Buscar respostas do admin
    $sql_respostas = "SELECT * FROM admin_respostas WHERE chamado_id = :chamado_id ORDER BY data_resposta DESC";
    $stmt_respostas = $pdo->prepare($sql_respostas);
    $stmt_respostas->bindParam(':chamado_id', $chamado_id, PDO::PARAM_INT);
    $stmt_respostas->execute();
    $respostas = $stmt_respostas->fetchAll(PDO::FETCH_ASSOC);
    
    if ($respostas) {
        $html .= '
                    <div class="mb-4">
                        <strong class="text-white">Respostas do Admin:</strong>
                        <div class="mt-2">';
        
        foreach ($respostas as $resposta) {
            $data_resposta_admin = date('d/m/Y H:i', strtotime($resposta['data_resposta']));
            $prazo_formatado = $resposta['prazo_solucao'] ? date('d/m/Y', strtotime($resposta['prazo_solucao'])) : 'Não definido';
            
            $html .= '
                        <div class="p-3 bg-primary bg-opacity-10 rounded border border-primary border-opacity-25 mb-3">
                            <div class="row mb-2">
                                <div class="col-md-6">
                                    <small class="text-light"><strong>Admin:</strong> ' . htmlspecialchars($resposta['admin_nome']) . '</small>
                                </div>
                                <div class="col-md-6">
                                    <small class="text-light"><strong>Data:</strong> ' . $data_resposta_admin . '</small>
                                </div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-md-4">
                                    <small class="text-light"><strong>Status:</strong> ' . ucfirst($resposta['novo_status']) . '</small>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-light"><strong>Prazo:</strong> ' . $prazo_formatado . '</small>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-light"><strong>Lido:</strong> ' . ($resposta['lido'] ? 'Sim' : 'Não') . '</small>
                                </div>
                            </div>
                            <p class="text-white mb-0" style="white-space: pre-wrap;">' . 
                                htmlspecialchars($resposta['parecer']) . 
                            '</p>
                        </div>';
        }
        
        $html .= '
                        </div>
                    </div>';
    }
    
    $html .= '
                </div>
            </div>
        </div>
    </div>';
    
    echo json_encode(['success' => true, 'html' => $html]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao carregar detalhes do chamado']);
}
?>
