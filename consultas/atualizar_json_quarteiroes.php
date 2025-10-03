<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

if (!isset($_POST['quarteirao']) || !isset($_POST['acao']) || !isset($_POST['arquivo'])) {
    echo json_encode(['success' => false, 'message' => 'Parâmetros obrigatórios: quarteirao, acao, arquivo']);
    exit;
}

$quarteirao = $_POST['quarteirao'];
$acao = $_POST['acao']; // 'adicionar' ou 'remover'
$arquivo = $_POST['arquivo'];

// Caminho do arquivo JSON
$caminhoJson = '../loteamentos_quadriculas/quarteiroes.json';

// Carrega o JSON atual
$jsonContent = file_get_contents($caminhoJson);
if ($jsonContent === false) {
    echo json_encode(['success' => false, 'message' => 'Erro ao ler arquivo JSON']);
    exit;
}

$dados = json_decode($jsonContent, true);
if ($dados === null) {
    echo json_encode(['success' => false, 'message' => 'Erro ao decodificar JSON']);
    exit;
}

if ($acao === 'adicionar') {
    // Verifica se já existe um item para este arquivo
    $existeItem = false;
    foreach ($dados as $item) {
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
    
} elseif ($acao === 'remover') {
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
if (file_put_contents($caminhoJson, $jsonAtualizado) === false) {
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar arquivo JSON']);
    exit;
}

echo json_encode(['success' => true, 'message' => 'JSON atualizado com sucesso']);
?>
