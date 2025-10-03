<?php
header('Content-Type: application/json');

// Log de debug
error_log("=== UPLOAD DEBUG ===");
error_log("REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
error_log("POST data: " . print_r($_POST, true));
error_log("FILES data: " . print_r($_FILES, true));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

if (!isset($_POST['quarteirao'])) {
    echo json_encode(['success' => false, 'message' => 'Parâmetro quarteirao é obrigatório']);
    exit;
}

if (!isset($_FILES['arquivos'])) {
    echo json_encode(['success' => false, 'message' => 'Nenhum arquivo foi enviado']);
    exit;
}

$quarteirao = $_POST['quarteirao'];
error_log("Quarteirão: " . $quarteirao);

// Diretório de destino - agora com a pasta do quarteirão
$diretorioDestino = '../loteamentos_quadriculas/pdfs_quarteiroes/' . $quarteirao . '/';
error_log("Diretório destino: " . $diretorioDestino);

// Verifica se o diretório existe, se não, cria
if (!is_dir($diretorioDestino)) {
    error_log("Diretório não existe, criando...");
    if (!mkdir($diretorioDestino, 0755, true)) {
        error_log("Erro ao criar diretório");
        echo json_encode(['success' => false, 'message' => 'Erro ao criar diretório']);
        exit;
    } else {
        error_log("Diretório criado com sucesso");
    }
} else {
    error_log("Diretório já existe");
}

$arquivosEnviados = [];
$erros = [];

// Processa cada arquivo enviado
foreach ($_FILES['arquivos']['name'] as $key => $nomeArquivo) {
    error_log("Processando arquivo: " . $nomeArquivo);
    
    $nomeTemporario = $_FILES['arquivos']['tmp_name'][$key];
    $erro = $_FILES['arquivos']['error'][$key];
    $tamanho = $_FILES['arquivos']['size'][$key];
    
    error_log("Arquivo temporário: " . $nomeTemporario);
    error_log("Erro: " . $erro);
    error_log("Tamanho: " . $tamanho);
    
    // Verifica se houve erro no upload
    if ($erro !== UPLOAD_ERR_OK) {
        $mensagemErro = "Erro no upload do arquivo {$nomeArquivo}: " . obterMensagemErro($erro);
        error_log($mensagemErro);
        $erros[] = $mensagemErro;
        continue;
    }
    
    // Verifica o tamanho do arquivo (máximo 10MB)
    if ($tamanho > 10 * 1024 * 1024) {
        $erros[] = "Arquivo {$nomeArquivo} muito grande (máximo 10MB)";
        continue;
    }
    
    // Verifica a extensão do arquivo
    $extensao = strtolower(pathinfo($nomeArquivo, PATHINFO_EXTENSION));
    $extensoesPermitidas = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'gif'];
    
    if (!in_array($extensao, $extensoesPermitidas)) {
        $erros[] = "Extensão não permitida para o arquivo {$nomeArquivo}";
        continue;
    }
    
    // Mantém o nome original do arquivo
    $nomeFinal = $nomeArquivo;
    $caminhoFinal = $diretorioDestino . $nomeFinal;
    
    error_log("Nome final: " . $nomeFinal);
    error_log("Caminho final: " . $caminhoFinal);
    
    // Move o arquivo para o diretório de destino
    if (move_uploaded_file($nomeTemporario, $caminhoFinal)) {
        error_log("Arquivo movido com sucesso para: " . $caminhoFinal);
        $arquivosEnviados[] = $nomeFinal;
        
        // Atualiza o JSON para incluir o arquivo
        $resultadoJson = atualizarJsonQuarteiroes($quarteirao, 'adicionar', $nomeFinal);
        if (!$resultadoJson) {
            error_log("Erro ao atualizar JSON para arquivo: " . $nomeFinal);
        }
    } else {
        $erroMsg = "Erro ao salvar o arquivo {$nomeArquivo}";
        error_log($erroMsg);
        error_log("move_uploaded_file retornou false");
        $erros[] = $erroMsg;
    }
}

// Retorna o resultado
if (empty($arquivosEnviados) && !empty($erros)) {
    echo json_encode([
        'success' => false,
        'message' => 'Nenhum arquivo foi enviado com sucesso',
        'erros' => $erros
    ]);
} else if (!empty($erros)) {
    echo json_encode([
        'success' => true,
        'message' => 'Alguns arquivos foram enviados com sucesso',
        'arquivos' => $arquivosEnviados,
        'erros' => $erros
    ]);
} else {
    echo json_encode([
        'success' => true,
        'message' => 'Todos os arquivos foram enviados com sucesso',
        'arquivos' => $arquivosEnviados
    ]);
}

function obterMensagemErro($erro) {
    switch ($erro) {
        case UPLOAD_ERR_INI_SIZE:
            return 'Arquivo muito grande (limite do servidor)';
        case UPLOAD_ERR_FORM_SIZE:
            return 'Arquivo muito grande (limite do formulário)';
        case UPLOAD_ERR_PARTIAL:
            return 'Upload parcial do arquivo';
        case UPLOAD_ERR_NO_FILE:
            return 'Nenhum arquivo enviado';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Diretório temporário não encontrado';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Erro de escrita no disco';
        case UPLOAD_ERR_EXTENSION:
            return 'Upload bloqueado por extensão';
        default:
            return 'Erro desconhecido';
    }
}

function atualizarJsonQuarteiroes($quarteirao, $acao, $arquivo) {
    $caminhoJson = '../loteamentos_quadriculas/quarteiroes.json';
    
    // Carrega o JSON atual
    $jsonContent = file_get_contents($caminhoJson);
    if ($jsonContent === false) {
        return false;
    }
    
    $dados = json_decode($jsonContent, true);
    if ($dados === null) {
        return false;
    }
    
    if ($acao === 'adicionar') {
        // Verifica se já existe um item para este arquivo
        $existeItem = false;
        foreach ($dados as &$item) {
            if ($item['nome_arquivo'] === $arquivo) {
                $existeItem = true;
                // Verifica se o quarteirão já está na lista
                if (!in_array($quarteirao, $item['quarteiroes'])) {
                    $item['quarteiroes'][] = $quarteirao;
                }
                break;
            }
        }
        
        // Se não existe, cria um novo item
        if (!$existeItem) {
            $dados[] = [
                'nome_arquivo' => $arquivo,
                'quarteiroes' => [$quarteirao]
            ];
        }
    }
    
    // Salva o JSON atualizado
    $jsonAtualizado = json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return file_put_contents($caminhoJson, $jsonAtualizado) !== false;
}
?>
