<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

if (!isset($_POST['quarteirao']) || !isset($_POST['arquivo'])) {
    echo json_encode(['success' => false, 'message' => 'Parâmetros quarteirao e arquivo são obrigatórios']);
    exit;
}

$quarteirao = $_POST['quarteirao'];
$nomeArquivo = $_POST['arquivo'];

// Diretório específico do quarteirão
$diretorioQuarteirao = '../loteamentos_quadriculas/pdfs_quarteiroes/' . $quarteirao . '/';
$caminhoCompleto = $diretorioQuarteirao . $nomeArquivo;

// Log de debug
error_log("=== EXCLUSÃO DEBUG ===");
error_log("Quarteirão: " . $quarteirao);
error_log("Nome arquivo: " . $nomeArquivo);
error_log("Diretório: " . $diretorioQuarteirao);
error_log("Caminho completo: " . $caminhoCompleto);
error_log("Arquivo existe: " . (file_exists($caminhoCompleto) ? 'SIM' : 'NÃO'));

// Verifica se o arquivo existe
if (!file_exists($caminhoCompleto)) {
    echo json_encode(['success' => false, 'message' => 'Arquivo não encontrado']);
    exit;
}

// Verificação de segurança removida - agora os arquivos estão em pastas específicas

// Tenta excluir o arquivo
if (unlink($caminhoCompleto)) {
    // Atualiza o JSON para remover o arquivo
    $resultadoJson = atualizarJsonQuarteiroes($quarteirao, 'remover', $nomeArquivo);
    if (!$resultadoJson) {
        echo json_encode(['success' => false, 'message' => 'Arquivo excluído, mas erro ao atualizar JSON']);
        exit;
    }
    
    echo json_encode(['success' => true, 'message' => 'Arquivo excluído com sucesso']);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao excluir arquivo']);
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
    
    if ($acao === 'remover') {
        // Remove o quarteirão da lista do arquivo
        foreach ($dados as $key => $item) {
            if ($item['nome_arquivo'] === $arquivo) {
                $dados[$key]['quarteiroes'] = array_values(array_filter($item['quarteiroes'], function($q) use ($quarteirao) {
                    return $q !== $quarteirao;
                }));
                
                // Se não há mais quarteirões para este arquivo, remove o item
                if (empty($dados[$key]['quarteiroes'])) {
                    unset($dados[$key]);
                    $dados = array_values($dados); // Reindexa o array
                }
                break;
            }
        }
    }
    
    // Salva o JSON atualizado
    $jsonAtualizado = json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return file_put_contents($caminhoJson, $jsonAtualizado) !== false;
}
?>
