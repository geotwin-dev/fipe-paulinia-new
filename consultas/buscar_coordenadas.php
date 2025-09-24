<?php
// Evitar qualquer output antes do JSON
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0); // Não mostrar erros na tela

session_start();

// Verificar se usuário está logado
if (!isset($_SESSION['usuario'])) {
    ob_clean();
    http_response_code(401);
    echo json_encode(['error' => 'Usuário não autenticado']);
    exit();
}

// Incluir conexão com tratamento de erro
try {
    include("../connection.php");
    
    // Verificar se a conexão PDO foi estabelecida
    if (!isset($pdo) || $pdo === null) {
        throw new Exception('Erro de conexão com o banco de dados: PDO não estabelecido');
    }
    
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro de conexão com banco',
        'mensagem' => $e->getMessage()
    ]);
    exit();
}

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit();
}

// Obter dados JSON do POST
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Log para debug
error_log("Dados recebidos no buscar_coordenadas.php: " . $input);
error_log("Dados decodificados: " . print_r($data, true));

if (!$data || !isset($data['registros'])) {
    ob_clean();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Dados inválidos',
        'debug' => [
            'input_length' => strlen($input),
            'json_error' => json_last_error_msg(),
            'data_received' => $data
        ]
    ]);
    exit();
}

$registros = $data['registros'];

try {
    $coordenadas_encontradas = [];
    $stats = [
        'total_registros' => count($registros),
        'coordenadas_encontradas' => 0,
        'tipos_encontrados' => [
            'marcador' => 0,
            'polilinha' => 0,
            'poligono' => 0
        ]
    ];

    // Colunas conhecidas da tabela desenhos baseadas na estrutura fornecida
    $select_sql = 'id, quarteirao, quadra, lote, coordenadas, tipo, cor, status';

    // Para cada registro recebido, buscar coordenadas na tabela desenhos
    foreach ($registros as $index => $registro) {
        $cara_quarteirao = isset($registro['cara_quarteirao']) ? $registro['cara_quarteirao'] : null;
        $quadra = isset($registro['quadra']) ? $registro['quadra'] : null;
        $lote = isset($registro['lote']) ? $registro['lote'] : null;

        // Pular se não tiver os campos necessários
        if (empty($cara_quarteirao) || empty($quadra) || empty($lote)) {
            continue;
        }

        // Consulta SQL para buscar desenhos correspondentes
        $sql = "SELECT $select_sql
                FROM desenhos 
                WHERE quarteirao = ? 
                AND quadra = ? 
                AND lote = ?";

        $stmt = $pdo->prepare($sql);
        
        if (!$stmt) {
            error_log("Erro na preparação da consulta PDO");
            continue;
        }

        $stmt->execute([$cara_quarteirao, $quadra, $lote]);
        
        while ($desenho = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Processar coordenadas (usando a coluna correta 'coordenadas')
            $coordenadas_raw = $desenho['coordenadas'];
            $tipo = strtolower(trim($desenho['tipo']));
            
            // Tentar decodificar as coordenadas (pode estar em JSON)
            $coordenadas_processadas = null;
            
            // Verificar se é JSON
            $coordenadas_json = json_decode($coordenadas_raw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $coordenadas_processadas = $coordenadas_json;
            } else {
                // Se não for JSON, tentar processar como string
                $coordenadas_processadas = $coordenadas_raw;
            }

            $item_coordenada = [
                'id_desenho' => $desenho['id'],
                'quarteirao' => $desenho['quarteirao'],
                'quadra' => $desenho['quadra'],
                'lote' => $desenho['lote'],
                'tipo' => $tipo,
                'coordenadas' => $coordenadas_processadas,
                'coordenadas_raw' => $coordenadas_raw,
                'cor' => isset($desenho['cor']) ? $desenho['cor'] : null,
                'status' => isset($desenho['status']) ? $desenho['status'] : null,
                'registro_origem' => $registro, // Manter referência ao registro original
                'indice_registro' => $index
            ];

            $coordenadas_encontradas[] = $item_coordenada;
            $stats['coordenadas_encontradas']++;
            
            // Contar tipos
            if (isset($stats['tipos_encontrados'][$tipo])) {
                $stats['tipos_encontrados'][$tipo]++;
            }
        }
    }

    // Resposta de sucesso
    $response = [
        'success' => true,
        'coordenadas' => $coordenadas_encontradas,
        'stats' => $stats,
        'mensagem' => "Encontradas {$stats['coordenadas_encontradas']} coordenadas para {$stats['total_registros']} registros"
    ];

    // Limpar qualquer output anterior e enviar JSON
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("Erro ao buscar coordenadas: " . $e->getMessage());
    
    ob_clean();
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Erro interno do servidor',
        'mensagem' => $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
}

// PDO não precisa de close() explícito, mas podemos definir como null
$pdo = null;
?>
