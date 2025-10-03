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

// Diretório específico do quarteirão
$diretorioQuarteirao = '../loteamentos_quadriculas/pdfs_quarteiroes/' . $quarteirao . '/';

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
