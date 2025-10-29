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

$admin_nome = $_SESSION['usuario'][0];
$admin_email = $_SESSION['usuario'][1];

// Validar dados recebidos
$chamado_id = $_POST['chamado_id'] ?? null;
$novo_status = trim($_POST['novo_status'] ?? '');
$prazo_solucao = $_POST['prazo_solucao'] ?? null;
$lido = isset($_POST['lido']) ? 1 : 0;
$data_leitura = $_POST['data_leitura'] ?? null;
$parecer = trim($_POST['parecer'] ?? '');

if (!$chamado_id || !$novo_status || !$parecer) {
    echo json_encode(['success' => false, 'message' => 'Campos obrigatórios não preenchidos']);
    exit();
}

try {
    // Buscar dados do chamado original
    $sql_chamado = "SELECT * FROM helpdesk WHERE id = :id";
    $stmt_chamado = $pdo->prepare($sql_chamado);
    $stmt_chamado->bindParam(':id', $chamado_id, PDO::PARAM_INT);
    $stmt_chamado->execute();
    $chamado = $stmt_chamado->fetch(PDO::FETCH_ASSOC);
    
    if (!$chamado) {
        echo json_encode(['success' => false, 'message' => 'Chamado não encontrado']);
        exit();
    }
    
    $status_anterior = $chamado['status'];
    
    // Inserir resposta do admin
    $sql_resposta = "INSERT INTO admin_respostas (chamado_id, admin_nome, admin_email, status_anterior, novo_status, prazo_solucao, data_leitura, lido, parecer) 
                     VALUES (:chamado_id, :admin_nome, :admin_email, :status_anterior, :novo_status, :prazo_solucao, :data_leitura, :lido, :parecer)";
    
    $stmt_resposta = $pdo->prepare($sql_resposta);
    $stmt_resposta->bindParam(':chamado_id', $chamado_id, PDO::PARAM_INT);
    $stmt_resposta->bindParam(':admin_nome', $admin_nome, PDO::PARAM_STR);
    $stmt_resposta->bindParam(':admin_email', $admin_email, PDO::PARAM_STR);
    $stmt_resposta->bindParam(':status_anterior', $status_anterior, PDO::PARAM_STR);
    $stmt_resposta->bindParam(':novo_status', $novo_status, PDO::PARAM_STR);
    $stmt_resposta->bindParam(':prazo_solucao', $prazo_solucao, PDO::PARAM_STR);
    $stmt_resposta->bindParam(':data_leitura', $data_leitura, PDO::PARAM_STR);
    $stmt_resposta->bindParam(':lido', $lido, PDO::PARAM_INT);
    $stmt_resposta->bindParam(':parecer', $parecer, PDO::PARAM_STR);
    $stmt_resposta->execute();
    
    // Atualizar status do chamado
    $sql_update = "UPDATE helpdesk SET status = :status, data_atualizacao = NOW() WHERE id = :id";
    $stmt_update = $pdo->prepare($sql_update);
    $stmt_update->bindParam(':status', $novo_status, PDO::PARAM_STR);
    $stmt_update->bindParam(':id', $chamado_id, PDO::PARAM_INT);
    $stmt_update->execute();
    
    // Enviar emails de notificação
    try {
        enviarEmailResposta($chamado, $admin_nome, $novo_status, $prazo_solucao, $parecer);
    } catch (Exception $e) {
        error_log("Erro ao enviar emails de resposta: " . $e->getMessage());
    }
    
    echo json_encode(['success' => true, 'message' => 'Resposta enviada com sucesso!']);
    
} catch (PDOException $e) {
    error_log("Erro no banco de dados: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar resposta']);
} catch (Exception $e) {
    error_log("Erro geral: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao processar resposta']);
}

function enviarEmailResposta($chamado, $admin_nome, $novo_status, $prazo_solucao, $parecer) {
    try {
        // Verificar se os arquivos existem
        if (!file_exists('../email_sender.php') || !file_exists('../email_config.php')) {
            return;
        }
        
        // Incluir a classe EmailSender
        require_once('../email_sender.php');
        
        // Carregar configurações de email
        require_once('../email_config.php');
        
        // Verificar se a variável global existe
        if (!isset($config_emails_receivers)) {
            return;
        }
        
        // Emails de destino: usuário + emails da configuração
        $emails_destino = array_merge([$chamado['usuario_email']], $config_emails_receivers);
        
        $assunto = "Resposta ao Chamado #{$chamado['id']} - {$chamado['titulo']}";
        $corpo_email = getRespostaEmailTemplate($chamado, $admin_nome, $novo_status, $prazo_solucao, $parecer);
        
        // Criar instância do EmailSender
        $emailSender = new EmailSender();
        
        // Enviar para cada destinatário
        foreach ($emails_destino as $email) {
            try {
                $emailSender->sendEmail($email, $assunto, $corpo_email, true);
            } catch (Exception $e) {
                error_log("Erro ao enviar email para $email: " . $e->getMessage());
            }
        }
        
    } catch (Exception $e) {
        error_log("Erro ao enviar emails de resposta: " . $e->getMessage());
    }
}

function getRespostaEmailTemplate($chamado, $admin_nome, $novo_status, $prazo_solucao, $parecer) {
    $prazo_formatado = $prazo_solucao ? date('d/m/Y', strtotime($prazo_solucao)) : 'Não definido';
    
    return "
    <!DOCTYPE html>
    <html lang='pt-br'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Resposta ao Chamado</title>
        <style>
            body { 
                font-family: Arial, sans-serif; 
                background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 50%, #0a0a0a 100%);
                margin: 0; 
                padding: 20px; 
                color: #333;
            }
            .container { 
                max-width: 600px; 
                margin: 0 auto; 
                background: white; 
                border-radius: 15px; 
                box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                overflow: hidden;
            }
            .header { 
                background: linear-gradient(135deg, #6366f1 0%, #5855eb 100%); 
                color: white; 
                padding: 40px 30px; 
                text-align: center; 
            }
            .header h1 { 
                margin: 0; 
                font-size: 28px; 
                font-weight: 700;
            }
            .header p { 
                margin: 10px 0 0 0; 
                font-size: 16px; 
                opacity: 0.9;
            }
            .content { 
                padding: 40px 30px; 
                color: #333; 
                line-height: 1.6; 
            }
            .info-box { 
                background: #f8f9fa; 
                padding: 20px; 
                margin: 15px 0; 
                border-radius: 12px; 
                border-left: 5px solid #6366f1;
            }
            .info-box h3 {
                margin: 0 0 15px 0;
                color: #6366f1;
                font-size: 18px;
                font-weight: 600;
            }
            .status-badge {
                display: inline-block;
                padding: 5px 15px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: 600;
                text-transform: uppercase;
                color: white;
            }
            .status-aberto { background: #f59e0b; }
            .status-em_andamento { background: #6366f1; }
            .status-resolvido { background: #10b981; }
            .status-fechado { background: #808080; }
            .footer { 
                background: #f8f9fa; 
                padding: 30px; 
                text-align: center; 
                color: #6c757d; 
                font-size: 14px; 
                border-top: 1px solid #e9ecef;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>📧 Resposta ao Chamado</h1>
                <p>Chamado #{$chamado['id']}</p>
            </div>
            <div class='content'>
                <div class='info-box'>
                    <h3>📋 Informações do Chamado</h3>
                    <p><strong>Título:</strong> {$chamado['titulo']}</p>
                    <p><strong>Usuário:</strong> {$chamado['usuario_nome']}</p>
                    <p><strong>Categoria:</strong> {$chamado['categoria']}</p>
                    <p><strong>Novo Status:</strong> <span class='status-badge status-{$novo_status}'>" . ucfirst($novo_status) . "</span></p>
                    <p><strong>Prazo para Solução:</strong> {$prazo_formatado}</p>
                    <p><strong>Data da Resposta:</strong> " . date('d/m/Y H:i') . "</p>
                </div>
                
                <div class='info-box'>
                    <h3>💬 Resposta do Admin</h3>
                    <p><strong>Admin Responsável:</strong> {$admin_nome}</p>
                    <p><strong>Parecer:</strong></p>
                    <div style='background: white; padding: 15px; border-radius: 8px; border: 1px solid #e9ecef;'>
                        <p style='margin: 0; white-space: pre-wrap;'>" . nl2br(htmlspecialchars($parecer)) . "</p>
                    </div>
                </div>
                
                <div class='info-box'>
                    <p><em>Esta é uma resposta automática do sistema de suporte da FIPE Paulínia.</em></p>
                    <p><strong>Acesse o sistema:</strong> <a href='https://moduloautoma.ddns.net/fipe-paulinia/suporte.php'>Central de Suporte</a></p>
                </div>
            </div>
            <div class='footer'>
                <p><strong>FIPE Paulínia</strong> - Sistema de Suporte</p>
                <p>Este é um email automático, não responda.</p>
                <p>© " . date('Y') . " Todos os direitos reservados</p>
            </div>
        </div>
    </body>
    </html>";
}
?>
