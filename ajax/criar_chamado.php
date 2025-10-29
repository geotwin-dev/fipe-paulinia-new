<?php
session_start();
include("../connection.php");

header('Content-Type: application/json');

if (!isset($_SESSION['usuario']) || !is_array($_SESSION['usuario']) || count($_SESSION['usuario']) < 2) {
    echo json_encode(['success' => false, 'message' => 'Usuário não logado']);
    exit();
}

$usuario_nome = $_SESSION['usuario'][0];
$usuario_email = $_SESSION['usuario'][1];

// Validar dados recebidos
$titulo = trim($_POST['titulo'] ?? '');
$categoria = trim($_POST['categoria'] ?? '');
$prioridade = trim($_POST['prioridade'] ?? '');
$descricao = trim($_POST['descricao'] ?? '');

if (empty($titulo) || empty($categoria) || empty($prioridade) || empty($descricao)) {
    echo json_encode(['success' => false, 'message' => 'Todos os campos obrigatórios devem ser preenchidos']);
    exit();
}

try {
    // Verificar conexão com banco de dados
    if (!$pdo) {
        echo json_encode(['success' => false, 'message' => 'Erro de conexão com banco de dados']);
        exit();
    }
    
    // Processar arquivos anexos se enviados
    $arquivos_anexos = [];
    if (isset($_FILES['anexo']) && !empty($_FILES['anexo']['name'][0])) {
        $upload_base_dir = '../uploads/helpdesk/';
        
        // Criar pasta base se não existir
        if (!is_dir($upload_base_dir)) {
            mkdir($upload_base_dir, 0755, true);
        }
        
        // Criar pasta específica do usuário (por email)
        $email_sanitized = preg_replace('/[^a-zA-Z0-9@._-]/', '_', $usuario_email);
        $user_upload_dir = $upload_base_dir . $email_sanitized . '/';
        
        if (!is_dir($user_upload_dir)) {
            mkdir($user_upload_dir, 0755, true);
        }
        
        // Processar múltiplos arquivos
        $file_count = count($_FILES['anexo']['name']);
        for ($i = 0; $i < $file_count; $i++) {
            if ($_FILES['anexo']['error'][$i] === UPLOAD_ERR_OK) {
                $original_name = $_FILES['anexo']['name'][$i];
                $safe_filename = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $original_name);
                $upload_path = $user_upload_dir . $safe_filename;
                
                if (move_uploaded_file($_FILES['anexo']['tmp_name'][$i], $upload_path)) {
                    $arquivos_anexos[] = $safe_filename;
                }
            }
        }
    }
    
    // Inserir chamado no banco de dados
    $arquivo_anexo_json = !empty($arquivos_anexos) ? json_encode($arquivos_anexos) : null;
    
    $sql = "INSERT INTO helpdesk (usuario_nome, usuario_email, titulo, descricao, prioridade, categoria, arquivo_anexo) 
            VALUES (:usuario_nome, :usuario_email, :titulo, :descricao, :prioridade, :categoria, :arquivo_anexo)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':usuario_nome', $usuario_nome, PDO::PARAM_STR);
    $stmt->bindParam(':usuario_email', $usuario_email, PDO::PARAM_STR);
    $stmt->bindParam(':titulo', $titulo, PDO::PARAM_STR);
    $stmt->bindParam(':descricao', $descricao, PDO::PARAM_STR);
    $stmt->bindParam(':prioridade', $prioridade, PDO::PARAM_STR);
    $stmt->bindParam(':categoria', $categoria, PDO::PARAM_STR);
    $stmt->bindParam(':arquivo_anexo', $arquivo_anexo_json, PDO::PARAM_STR);
    
    $stmt->execute();
    $chamado_id = $pdo->lastInsertId();
    
    // Enviar emails (não falha se email não for enviado)
    try {
        enviarEmailsChamado($chamado_id, $titulo, $descricao, $categoria, $prioridade, $usuario_nome, $usuario_email, $arquivos_anexos);
    } catch (Exception $e) {
        error_log("Erro ao enviar emails, mas chamado foi criado: " . $e->getMessage());
    } catch (Error $e) {
        error_log("Erro fatal ao enviar emails: " . $e->getMessage());
    }
    
    echo json_encode(['success' => true, 'message' => 'Chamado criado com sucesso!']);
    
} catch (PDOException $e) {
    error_log("Erro no banco de dados: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao criar chamado: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("Erro geral: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao criar chamado: ' . $e->getMessage()]);
}

function enviarEmailsChamado($chamado_id, $titulo, $descricao, $categoria, $prioridade, $usuario_nome, $usuario_email, $arquivos_anexos) {
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
        $emails_destino = array_merge([$usuario_email], $config_emails_receivers);
        
        $assunto = "Novo Chamado #{$chamado_id} - {$titulo}";
        $corpo_email = getHelpdeskEmailTemplate($chamado_id, $titulo, $descricao, $categoria, $prioridade, $usuario_nome, $usuario_email, $arquivos_anexos);
        
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
        error_log("Erro ao enviar emails: " . $e->getMessage());
    }
}

function getAnexosLinks($arquivos_anexos, $usuario_email) {
    $links = [];
    $email_sanitized = preg_replace('/[^a-zA-Z0-9@._-]/', '_', $usuario_email);
    
    foreach ($arquivos_anexos as $arquivo) {
        $url = "https://moduloautoma.ddns.net/fipe-paulinia/uploads/helpdesk/{$email_sanitized}/{$arquivo}";
        $nome_original = preg_replace('/^[a-f0-9]+_/', '', $arquivo);
        $links[] = "<a href='{$url}' target='_blank' style='color: #6366f1; text-decoration: none; background: #f8f9fa; padding: 8px 12px; border-radius: 6px; display: inline-block; margin: 4px 0; border: 1px solid #e9ecef;'>📎 {$nome_original}</a>";
    }
    return implode('<br>', $links);
}

function getHelpdeskEmailTemplate($chamado_id, $titulo, $descricao, $categoria, $prioridade, $usuario_nome, $usuario_email, $arquivos_anexos) {
    return "
    <!DOCTYPE html>
    <html lang='pt-br'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Novo Chamado de Suporte</title>
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
            .priority-badge {
                display: inline-block;
                padding: 5px 15px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: 600;
                text-transform: uppercase;
            }
            .priority-baixa { background: #d1fae5; color: #065f46; }
            .priority-media { background: #fef3c7; color: #92400e; }
            .priority-alta { background: #fee2e2; color: #991b1b; }
            .priority-critica { background: #f3e8ff; color: #7c3aed; }
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
                <h1>🎫 Novo Chamado de Suporte</h1>
                <p>Chamado #{$chamado_id}</p>
            </div>
            <div class='content'>
                <div class='info-box'>
                    <h3>📋 Informações do Chamado</h3>
                    <p><strong>Título:</strong> {$titulo}</p>
                    <p><strong>Usuário:</strong> {$usuario_nome}</p>
                    <p><strong>Categoria:</strong> {$categoria}</p>
                    <p><strong>Prioridade:</strong> <span class='priority-badge priority-{$prioridade}'>" . ucfirst($prioridade) . "</span></p>
                    <p><strong>Data:</strong> " . date('d/m/Y H:i') . "</p>
                </div>
                
                <div class='info-box'>
                    <h3>📝 Descrição</h3>
                    <p>" . nl2br(htmlspecialchars($descricao)) . "</p>
                </div>
                
                " . (!empty($arquivos_anexos) ? "<div class='info-box'><h3>📎 Anexos</h3><p>" . getAnexosLinks($arquivos_anexos, $usuario_email) . "</p></div>" : "") . "
                
                <div class='info-box'>
                    <p><em>Este é um email automático do sistema de suporte da FIPE Paulínia.</em></p>
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
