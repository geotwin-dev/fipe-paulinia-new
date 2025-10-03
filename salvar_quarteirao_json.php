<?php
session_start();

// Verificar se usuário está logado
if (!isset($_SESSION['usuario']) || empty($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

// Verificar se é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Obter dados do POST
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

// Validar campos obrigatórios
$required_fields = ['loteamento', 'nome_quarteirao', 'quadras'];
foreach ($required_fields as $field) {
    if (!isset($input[$field]) || empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Campo obrigatório: $field"]);
        exit;
    }
}

$loteamento = $input['loteamento'];
$nomeQuarteirao = $input['nome_quarteirao'];
$quadras = $input['quadras']; // Array de quadras

// Validar se quadras é array
if (!is_array($quadras) || empty($quadras)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Quadras devem ser um array não vazio']);
    exit;
}

// Caminho do arquivo JSON
$jsonPath = 'correspondencias_quarteiroes/resultado_quarteiroes_loteamentos.json';

// Verificar se arquivo existe
if (!file_exists($jsonPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Arquivo JSON não encontrado']);
    exit;
}

try {
    // Ler arquivo JSON atual
    $jsonContent = file_get_contents($jsonPath);
    $data = json_decode($jsonContent, true);
    
    if ($data === null) {
        throw new Exception('Erro ao decodificar JSON');
    }
    
    // Verificar se loteamento existe
    if (!isset($data[$loteamento])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Loteamento não encontrado']);
        exit;
    }
    
    // Verificar se quarteirão já existe
    $quarteiraoExistente = null;
    $quarteiraoIndex = -1;
    
    if (isset($data[$loteamento]['quarteiroes'])) {
        foreach ($data[$loteamento]['quarteiroes'] as $index => $quarteirao) {
            if ($quarteirao['nome'] === $nomeQuarteirao) {
                $quarteiraoExistente = $quarteirao;
                $quarteiraoIndex = $index;
                break;
            }
        }
    }
    
    // Criar novo quarteirão ou atualizar existente
    if ($quarteiraoExistente) {
        // Quarteirão já existe - verificar quadras novas
        $quadrasExistentes = $quarteiraoExistente['quadras_unicas'] ?? [];
        $quadrasNovas = [];
        
        // Encontrar quadras que não existem
        foreach ($quadras as $quadra) {
            if (!in_array($quadra, $quadrasExistentes)) {
                $quadrasNovas[] = $quadra;
            }
        }
        
        if (empty($quadrasNovas)) {
            // Todas as quadras já existem
            echo json_encode([
                'success' => true,
                'message' => 'Quarteirão já existe com todas essas quadras',
                'quarteirao' => $quarteiraoExistente,
                'quadras_adicionadas' => []
            ]);
            exit;
        }
        
        // Adicionar novas quadras ao quarteirão existente
        $quadrasAtualizadas = array_merge($quadrasExistentes, $quadrasNovas);
        
        // Atualizar quarteirão existente
        $data[$loteamento]['quarteiroes'][$quarteiraoIndex]['quadras_unicas'] = $quadrasAtualizadas;
        
        // Adicionar informação de atualização
        $data[$loteamento]['quarteiroes'][$quarteiraoIndex]['ultima_atualizacao'] = date('Y-m-d H:i:s');
        $data[$loteamento]['quarteiroes'][$quarteiraoIndex]['usuario_atualizacao'] = $_SESSION['usuario'][0] ?? 'Usuário Desconhecido';
        
        $quarteiraoAtualizado = $data[$loteamento]['quarteiroes'][$quarteiraoIndex];
        
    } else {
        // Quarteirão não existe - criar novo
        $novoQuarteirao = [
            'nome' => $nomeQuarteirao,
            'quadras_unicas' => $quadras,
            'data_insercao' => date('Y-m-d H:i:s'),
            'usuario' => $_SESSION['usuario'][0] ?? 'Usuário Desconhecido'
        ];
        
        // Adicionar quarteirão ao loteamento
        if (!isset($data[$loteamento]['quarteiroes'])) {
            $data[$loteamento]['quarteiroes'] = [];
        }
        
        $data[$loteamento]['quarteiroes'][] = $novoQuarteirao;
        $quarteiraoAtualizado = $novoQuarteirao;
    }
    
    // Salvar arquivo JSON
    $jsonString = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
    if (file_put_contents($jsonPath, $jsonString) === false) {
        throw new Exception('Erro ao salvar arquivo JSON');
    }
    
    // Resposta de sucesso
    $response = [
        'success' => true,
        'message' => $quarteiraoExistente ? 
            'Quadras adicionadas ao quarteirão existente' : 
            'Quarteirão criado com sucesso',
        'quarteirao' => $quarteiraoAtualizado
    ];
    
    // Se foi atualização, incluir informações sobre quadras adicionadas
    if ($quarteiraoExistente) {
        $quadrasExistentes = $quarteiraoExistente['quadras_unicas'] ?? [];
        $quadrasNovas = [];
        
        foreach ($quadras as $quadra) {
            if (!in_array($quadra, $quadrasExistentes)) {
                $quadrasNovas[] = $quadra;
            }
        }
        
        $response['quadras_adicionadas'] = $quadrasNovas;
        $response['quadras_existentes'] = $quadrasExistentes;
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno: ' . $e->getMessage()
    ]);
}
?>
