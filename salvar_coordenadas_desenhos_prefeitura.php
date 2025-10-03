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
$required_fields = ['quadricula', 'coordenadas'];
foreach ($required_fields as $field) {
    if (!isset($input[$field]) || empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Campo obrigatório: $field"]);
        exit;
    }
}

$quadricula = $input['quadricula'];
$coordenadas = $input['coordenadas']; // Array de coordenadas dos desenhos

// Validar se coordenadas é array
if (!is_array($coordenadas) || empty($coordenadas)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Coordenadas devem ser um array não vazio']);
    exit;
}

// Caminho do arquivo JSON
$jsonPath = "loteamentos_quadriculas/geojson/lotes_prefeitura_quadricula_{$quadricula}.geojson";

// Verificar se arquivo existe
if (!file_exists($jsonPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Arquivo JSON não encontrado']);
    exit;
}

try {
    // Ler arquivo JSON atual
    $jsonContent = file_get_contents($jsonPath);
    $geojsonData = json_decode($jsonContent, true);
    
    if ($geojsonData === null) {
        throw new Exception('Erro ao decodificar GeoJSON');
    }
    
    // Verificar se tem features
    if (!isset($geojsonData['features']) || !is_array($geojsonData['features'])) {
        throw new Exception('GeoJSON não possui features válidas');
    }
    
    // Atualizar coordenadas de cada feature
    $featuresAtualizadas = 0;
    foreach ($geojsonData['features'] as $index => &$feature) {
        if (isset($coordenadas[$index]) && is_array($coordenadas[$index])) {
            // Converter coordenadas do formato [lat, lng] para [lng, lat] (formato GeoJSON)
            $coordsGeoJSON = [];
            foreach ($coordenadas[$index] as $coord) {
                if (isset($coord['lat']) && isset($coord['lng'])) {
                    $coordsGeoJSON[] = [$coord['lng'], $coord['lat']];
                }
            }
            
            if (!empty($coordsGeoJSON)) {
                // Adicionar primeiro ponto no final para fechar o polígono (se necessário)
                if ($coordsGeoJSON[0] !== end($coordsGeoJSON)) {
                    $coordsGeoJSON[] = $coordsGeoJSON[0];
                }
                
                // Atualizar geometria do polígono
                if (isset($feature['geometry']['coordinates'])) {
                    $feature['geometry']['coordinates'] = [$coordsGeoJSON];
                    $featuresAtualizadas++;
                }
            }
        }
    }
    
    // Adicionar metadados de atualização
    $geojsonData['properties'] = $geojsonData['properties'] ?? [];
    $geojsonData['properties']['ultima_atualizacao'] = date('Y-m-d H:i:s');
    $geojsonData['properties']['usuario_atualizacao'] = $_SESSION['usuario'][0] ?? 'Usuário Desconhecido';
    $geojsonData['properties']['features_atualizadas'] = $featuresAtualizadas;
    
    // Salvar arquivo JSON
    $jsonString = json_encode($geojsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
    if (file_put_contents($jsonPath, $jsonString) === false) {
        throw new Exception('Erro ao salvar arquivo GeoJSON');
    }
    
    // Resposta de sucesso
    echo json_encode([
        'success' => true,
        'message' => 'Coordenadas dos desenhos salvas com sucesso',
        'features_atualizadas' => $featuresAtualizadas,
        'total_features' => count($geojsonData['features']),
        'arquivo' => $jsonPath
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno: ' . $e->getMessage()
    ]);
}
?>
