<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

if (!isset($_POST['quarteirao'])) {
    echo json_encode(['success' => false, 'message' => 'Parâmetro quarteirao é obrigatório']);
    exit;
}

$quarteirao = $_POST['quarteirao'];

// Função para normalizar quarteirão (garantir 4 dígitos com zeros à esquerda)
function normalizarQuarteiraoParaPasta($quarteirao) {
    // Remove espaços e converte para string
    $quarteirao = trim((string)$quarteirao);
    
    // Remove zeros à esquerda para obter o número base
    $numeroBase = ltrim($quarteirao, '0');
    
    // Se ficou vazio, significa que era só zeros, retorna '0000'
    if ($numeroBase === '') {
        return '0000';
    }
    
    // Preenche com zeros à esquerda até ter 4 dígitos
    return str_pad($numeroBase, 4, '0', STR_PAD_LEFT);
}

// Normalizar quarteirão para busca de pasta
$quarteiraoNormalizado = normalizarQuarteiraoParaPasta($quarteirao);

// Diretório específico do quarteirão
$diretorioQuarteirao = '../loteamentos_quadriculas/pdfs_quarteiroes/' . $quarteiraoNormalizado . '/';

// Verifica se o diretório específico do quarteirão existe
if (!is_dir($diretorioQuarteirao)) {
    echo json_encode(['success' => true, 'arquivos' => []]);
    exit;
}

// Lista arquivos do diretório específico do quarteirão
$arquivos = [];
$diretorio = opendir($diretorioQuarteirao);

while (($arquivo = readdir($diretorio)) !== false) {
    // Ignora diretórios e arquivos ocultos
    if ($arquivo !== '.' && $arquivo !== '..' && !is_dir($diretorioQuarteirao . $arquivo)) {
        // Verifica se é um arquivo válido
        $extensao = strtolower(pathinfo($arquivo, PATHINFO_EXTENSION));
        if (in_array($extensao, ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'gif'])) {
            $arquivos[] = $arquivo;
        }
    }
}

closedir($diretorio);

// Ordena os arquivos alfabeticamente
sort($arquivos);

echo json_encode([
    'success' => true,
    'arquivos' => array_values($arquivos)
]);
?>
