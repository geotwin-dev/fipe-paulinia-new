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
error_log("=== INÍCIO DEBUG BUSCAR_COORDENADAS ===");
error_log("Dados recebidos no buscar_coordenadas.php: " . $input);
error_log("Dados decodificados: " . print_r($data, true));
error_log("Método: " . $_SERVER['REQUEST_METHOD']);
error_log("Usuário logado: " . (isset($_SESSION['usuario']) ? 'SIM' : 'NÃO'));

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
    $poligonos_lotes_quadras = []; // Array para polígonos de lotes e quadras
    $debug_queries = []; // Array para armazenar todas as queries executadas
    $debug_info = []; // Array para informações de debug detalhadas
    $stats = [
        'total_registros' => count($registros),
        'coordenadas_encontradas' => 0,
        'poligonos_encontrados' => 0,
        'tipos_encontrados' => [
            'marcador' => 0,
            'polilinha' => 0,
            'poligono' => 0
        ],
        'camadas_encontradas' => [
            'marcador_quadra' => 0,
            'lote' => 0,
            'quadra' => 0,
            'quarteirao' => 0,
            'outros' => 0
        ],
        'registros_processados' => 0,
        'registros_sem_campos_necessarios' => 0,
        'queries_executadas' => 0
    ];

    // Colunas conhecidas da tabela desenhos baseadas na estrutura fornecida
    $select_sql = 'id, quarteirao, quadra, lote, coordenadas, tipo, cor, status, camada, quadricula';

    // Para cada registro recebido, buscar coordenadas na tabela desenhos
    foreach ($registros as $index => $registro) {
        $stats['registros_processados']++;
        
        // Debug: informações do registro atual
        $debug_registro = [
            'indice' => $index,
            'campos_disponiveis' => array_keys($registro),
            'cara_quarteirao' => isset($registro['cara_quarteirao']) ? $registro['cara_quarteirao'] : 'NÃO ENCONTRADO',
            'quadra' => isset($registro['quadra']) ? $registro['quadra'] : 'NÃO ENCONTRADO',
            'lote' => isset($registro['lote']) ? $registro['lote'] : 'NÃO ENCONTRADO'
        ];
        
        $cara_quarteirao = isset($registro['cara_quarteirao']) ? $registro['cara_quarteirao'] : null;
        $quadra = isset($registro['quadra']) ? $registro['quadra'] : null;
        $lote = isset($registro['lote']) ? $registro['lote'] : null;

        // Debug: verificar campos alternativos se os principais estão vazios
        if (empty($cara_quarteirao)) {
            // Tentar outros campos possíveis
            $campos_alternativos = ['quarteirao', 'quarteirao_cara', 'codigo_quarteirao'];
            foreach ($campos_alternativos as $campo) {
                if (isset($registro[$campo]) && !empty($registro[$campo])) {
                    $cara_quarteirao = $registro[$campo];
                    $debug_registro['cara_quarteirao_alternativo'] = $campo . ': ' . $cara_quarteirao;
                    break;
                }
            }
        }

        // Pular se não tiver os campos necessários
        if (empty($cara_quarteirao) || empty($quadra) || empty($lote)) {
            $stats['registros_sem_campos_necessarios']++;
            $debug_registro['motivo_pulo'] = 'Campos obrigatórios vazios';
            $debug_info[] = $debug_registro;
            continue;
        }

        // ============ NORMALIZAÇÃO COM PADDING DE ZEROS ============
        // Salvar valores originais para debug
        $valores_originais = [
            'cara_quarteirao' => $cara_quarteirao,
            'quadra' => $quadra,
            'lote' => $lote
        ];

        // Aplicar padding de zeros à esquerda (4 dígitos para cada campo)
        $cara_quarteirao = str_pad(trim($cara_quarteirao), 4, '0', STR_PAD_LEFT);


        // Salvar valores normalizados para debug
        $valores_normalizados = [
            'cara_quarteirao' => $cara_quarteirao,
            'quadra' => $quadra,
            'lote' => $lote
        ];

        $debug_registro['valores_originais'] = $valores_originais;
        $debug_registro['valores_normalizados'] = $valores_normalizados;
        // ========================================================

        // Construir consulta SQL
        $sql = "SELECT $select_sql
                FROM desenhos 
                WHERE quarteirao = ? 
                AND quadra = ? 
                AND lote = ?";
        
        // Preparar query para debug (substituir ? pelos valores reais)
        $sql_debug = "SELECT $select_sql
                      FROM desenhos 
                      WHERE quarteirao = '" . addslashes($cara_quarteirao) . "' 
                      AND quadra = '" . $quadra. "' 
                      AND lote = '" . $lote. "'";
        
        // Salvar query no debug
        $query_info = [
            'query_index' => $stats['queries_executadas'],
            'registro_index' => $index,
            'sql_original' => $sql,
            'sql_executavel' => $sql_debug,
            'parametros' => [
                'quarteirao' => $cara_quarteirao,
                'quadra' => $quadra,
                'lote' => $lote
            ],
            'resultados_encontrados' => 0
        ];

        $stmt = $pdo->prepare($sql);
        
        if (!$stmt) {
            error_log("Erro na preparação da consulta PDO");
            $query_info['erro'] = 'Erro na preparação da consulta PDO';
            $debug_queries[] = $query_info;
            continue;
        }

        $stats['queries_executadas']++;
        $stmt->execute([$cara_quarteirao, $quadra, $lote]);
        
        $resultados_desta_query = 0;
        
        while ($desenho = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $resultados_desta_query++;
            
            // Processar coordenadas (usando a coluna correta 'coordenadas')
            $coordenadas_raw = $desenho['coordenadas'];
            $tipo = strtolower(trim($desenho['tipo']));
            
            // Debug das coordenadas encontradas
            $debug_coordenadas = [
                'coordenadas_raw' => $coordenadas_raw,
                'tamanho_coordenadas' => strlen($coordenadas_raw),
                'tipo_coordenadas' => gettype($coordenadas_raw),
                'primeiro_caractere' => substr($coordenadas_raw, 0, 1),
                'ultimo_caractere' => substr($coordenadas_raw, -1, 1)
            ];
            
            // Tentar decodificar as coordenadas (pode estar em JSON)
            $coordenadas_processadas = null;
            
            // Verificar se é JSON
            $coordenadas_json = json_decode($coordenadas_raw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $coordenadas_processadas = $coordenadas_json;
                $debug_coordenadas['parse_resultado'] = 'JSON válido';
                $debug_coordenadas['json_decodificado'] = $coordenadas_json;
            } else {
                // Se não for JSON, tentar processar como string
                $coordenadas_processadas = $coordenadas_raw;
                $debug_coordenadas['parse_resultado'] = 'Não é JSON - ' . json_last_error_msg();
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
                'indice_registro' => $index,
                'debug_coordenadas' => $debug_coordenadas, // Adicionar debug
                'dados_completos_desenho' => $desenho // Dados completos do desenho para debug
            ];

            $coordenadas_encontradas[] = $item_coordenada;
            $stats['coordenadas_encontradas']++;
            
            // Contar tipos
            if (isset($stats['tipos_encontrados'][$tipo])) {
                $stats['tipos_encontrados'][$tipo]++;
            }
            
            // Contar camadas
            $camada = isset($desenho['camada']) ? strtolower(trim($desenho['camada'])) : 'outros';
            if (isset($stats['camadas_encontradas'][$camada])) {
                $stats['camadas_encontradas'][$camada]++;
            } else {
                $stats['camadas_encontradas']['outros']++;
            }
        }
        
        // Finalizar informações da query
        $query_info['resultados_encontrados'] = $resultados_desta_query;
        $debug_registro['coordenadas_encontradas'] = $resultados_desta_query;
        
        // Salvar informações de debug
        $debug_queries[] = $query_info;
        $debug_info[] = $debug_registro;
    }

    // ========= BUSCAR PELA QUADRÍCULA (como index3.php) =========
    // Descobrir quadrícula dos marcadores encontrados
    
    $quadriculas_encontradas = [];
    
    // Coletar quadrículas dos marcadores encontrados
    foreach ($coordenadas_encontradas as $marcador) {
        // Primeiro tentar no marcador direto
        $quadricula = isset($marcador['quadricula']) ? $marcador['quadricula'] : null;
        
        // Se não encontrar, tentar nos dados completos do desenho
        if (!$quadricula && isset($marcador['dados_completos_desenho']['quadricula'])) {
            $quadricula = $marcador['dados_completos_desenho']['quadricula'];
        }
        
        if ($quadricula) {
            $quadriculas_encontradas[$quadricula] = true;
            error_log("QUADRÍCULA COLETADA: " . $quadricula . " do marcador " . $marcador['id_desenho']);
        } else {
            error_log("MARCADOR SEM QUADRÍCULA: " . $marcador['id_desenho']);
        }
    }
    
    error_log("QUADRÍCULAS encontradas nos marcadores: " . implode(', ', array_keys($quadriculas_encontradas)));
    
    // Também verificar se há quadrícula nos registros originais
    foreach ($registros as $registro) {
        if (isset($registro['quadricula']) && !empty($registro['quadricula'])) {
            $quadriculas_encontradas[$registro['quadricula']] = true;
        }
    }
    
    error_log("QUADRÍCULAS FINAIS para busca: " . implode(', ', array_keys($quadriculas_encontradas)));
    
    // Construir consulta POR QUADRÍCULA (exatamente como index3.php)
    $sql_poligonos = null;
    $params_poligonos = [];
    
    if (!empty($quadriculas_encontradas)) {
        $quadriculas_list = array_keys($quadriculas_encontradas);
        
        if (count($quadriculas_list) > 0) {
            // BUSCAR EM TODAS AS QUADRÍCULAS (não apenas a primeira)
            $placeholders = str_repeat('?,', count($quadriculas_list) - 1) . '?';
            
            $sql_poligonos = "SELECT $select_sql
                             FROM desenhos 
                             WHERE quadricula IN ($placeholders)
                             AND tipo = 'poligono'
                             ORDER BY quadricula, id";
            
            $params_poligonos = $quadriculas_list;
            
            error_log("BUSCA POR TODAS AS QUADRÍCULAS: " . implode(', ', $quadriculas_list));
            error_log("SQL PARA MÚLTIPLAS QUADRÍCULAS: " . $sql_poligonos);
            error_log("PARÂMETROS: " . implode(', ', $params_poligonos));
        } else {
            error_log("AVISO: Lista de quadrículas vazia");
        }
    } else {
        error_log("AVISO: Nenhuma quadrícula encontrada nos dados");
    }
    
    $query_poligonos_info = [
        'query_index' => $stats['queries_executadas'],
        'tipo' => 'busca_multiplas_quadriculas',
        'sql_original' => $sql_poligonos,
        'parametros' => $params_poligonos,
        'quadriculas_encontradas' => array_keys($quadriculas_encontradas),
        'total_quadriculas' => count($quadriculas_encontradas),
        'resultados_encontrados' => 0
    ];
    
    // Log detalhado da consulta de polígonos
    error_log("=== CONSULTA POR MÚLTIPLAS QUADRÍCULAS ===");
    error_log("Total de quadrículas: " . count($quadriculas_encontradas));
    foreach (array_keys($quadriculas_encontradas) as $quadricula) {
        error_log("  - Quadrícula: " . $quadricula);
    }
    error_log("SQL gerado: " . ($sql_poligonos ?: 'NULL'));
    error_log("Parâmetros (" . count($params_poligonos) . "): " . implode(', ', $params_poligonos));
    
    // Adicionar SQL e parâmetros ao debug_info para enviar ao frontend
    $query_poligonos_info['sql_debug'] = $sql_poligonos;
    $query_poligonos_info['params_debug'] = $params_poligonos;
    
    // DEBUG: Testar consulta por múltiplas quadrículas
    if (!empty($quadriculas_encontradas)) {
        $quadriculas_list = array_keys($quadriculas_encontradas);
        $total_poligonos = 0;
        
        foreach ($quadriculas_list as $quadricula) {
            $test_sql = "SELECT COUNT(*) as total FROM desenhos WHERE tipo = 'poligono' AND quadricula = ?";
            try {
                $stmt_test = $pdo->prepare($test_sql);
                $stmt_test->execute([$quadricula]);
                $test_result = $stmt_test->fetch(PDO::FETCH_ASSOC);
                $total_poligonos += $test_result['total'];
                error_log("TEST: Quadrícula " . $quadricula . " tem " . $test_result['total'] . " polígonos");
                
                // Testar camadas disponíveis na quadrícula
                $test_sql2 = "SELECT DISTINCT camada, COUNT(*) as total FROM desenhos WHERE tipo = 'poligono' AND quadricula = ? GROUP BY camada";
                $stmt_test2 = $pdo->prepare($test_sql2);
                $stmt_test2->execute([$quadricula]);
                error_log("TEST: Camadas de polígonos na quadrícula " . $quadricula . ":");
                while ($camada_result = $stmt_test2->fetch(PDO::FETCH_ASSOC)) {
                    error_log("  - Camada: " . $camada_result['camada'] . " (" . $camada_result['total'] . " registros)");
                }
            } catch (Exception $e) {
                error_log("TEST: Erro na consulta de teste: " . $e->getMessage());
            }
        }
        
        error_log("TEST: TOTAL DE POLÍGONOS EM TODAS AS QUADRÍCULAS: " . $total_poligonos);
    }
    
    error_log("===================================");
    
    // Só executar query se há SQL válido (otimização)
    if ($sql_poligonos) {
        try {
            $stmt_poligonos = $pdo->prepare($sql_poligonos);
            $stats['queries_executadas']++;
            $stmt_poligonos->execute($params_poligonos);
            
            error_log("Consulta de polígonos executada com " . count($params_poligonos) . " parâmetros");
        
        $resultados_poligonos = 0;
        $poligonos_proximos = 0;
        $poligonos_distantes = 0;
        
        while ($poligono = $stmt_poligonos->fetch(PDO::FETCH_ASSOC)) {
            $resultados_poligonos++;
            
            // Processar coordenadas do polígono
            $coordenadas_raw = $poligono['coordenadas'];
            $camada = strtolower(trim($poligono['camada']));
            
            // Tentar decodificar as coordenadas
            $coordenadas_processadas = null;
            $coordenadas_json = json_decode($coordenadas_raw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $coordenadas_processadas = $coordenadas_json;
            } else {
                $coordenadas_processadas = $coordenadas_raw;
            }
            
            // FILTRO DE PROXIMIDADE: Verificar se polígono está a até 500m dos marcadores
            $relevante = verificarProximidadePoligono($poligono, $coordenadas_encontradas, 500);
            
            if ($relevante) {
                $poligonos_proximos++;
            } else {
                $poligonos_distantes++;
            }
            
            $item_poligono = [
                'id_desenho' => $poligono['id'],
                'quarteirao' => $poligono['quarteirao'],
                'quadra' => $poligono['quadra'],
                'lote' => $poligono['lote'],
                'tipo' => 'poligono',
                'camada' => $camada,
                'coordenadas' => $coordenadas_processadas,
                'coordenadas_raw' => $coordenadas_raw,
                'cor' => isset($poligono['cor']) ? $poligono['cor'] : null,
                'status' => isset($poligono['status']) ? $poligono['status'] : null,
                'quadricula' => isset($poligono['quadricula']) ? $poligono['quadricula'] : null,
                'dados_completos_desenho' => $poligono,
                'relevante' => $relevante // Marcar se é relevante para os marcadores
            ];
            
            $poligonos_lotes_quadras[] = $item_poligono;
            $stats['poligonos_encontrados']++;
            
            // Contar por camada
            if (isset($stats['camadas_encontradas'][$camada])) {
                $stats['camadas_encontradas'][$camada]++;
            } else {
                $stats['camadas_encontradas']['outros']++;
            }
        }
        
            $query_poligonos_info['resultados_encontrados'] = $resultados_poligonos;
            $query_poligonos_info['poligonos_proximos'] = $poligonos_proximos;
            $query_poligonos_info['poligonos_distantes'] = $poligonos_distantes;
            
            error_log("Resultados encontrados na consulta de polígonos: " . $resultados_poligonos);
            error_log("🎯 FILTRO DE PROXIMIDADE (500m):");
            error_log("  - Polígonos próximos (≤500m): " . $poligonos_proximos);
            error_log("  - Polígonos distantes (>500m): " . $poligonos_distantes);
            error_log("  - Taxa de proximidade: " . ($resultados_poligonos > 0 ? round(($poligonos_proximos / $resultados_poligonos) * 100, 1) : 0) . "%");
            
            // Sem fallback necessário - busca por quadrícula já é completa
            // Fallback removido - busca por quadrícula já é completa
            
        } catch (Exception $e) {
            $query_poligonos_info['erro'] = 'Erro na busca de polígonos: ' . $e->getMessage();
            error_log("Erro na consulta de polígonos: " . $e->getMessage());
        }
    } else {
        $query_poligonos_info['aviso'] = 'Query de polígonos não executada - SQL vazio';
        $query_poligonos_info['sql_debug'] = null;
        $query_poligonos_info['params_debug'] = [];
        error_log("❌ AVISO: Query de polígonos NÃO EXECUTADA");
        error_log("❌ SQL está vazio. Quadrículas: " . implode(', ', array_keys($quadriculas_encontradas)));
        error_log("❌ Verificar por que SQL não foi gerado!");
    }
    
    $debug_queries[] = $query_poligonos_info;

    // Adicionar informações extras para debug
    $debug_estrutura_tabela = [];
    try {
        // Obter estrutura da tabela desenhos para debug
        $stmt_describe = $pdo->prepare("DESCRIBE desenhos");
        $stmt_describe->execute();
        $debug_estrutura_tabela = $stmt_describe->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $debug_estrutura_tabela = ['erro' => 'Não foi possível obter estrutura da tabela: ' . $e->getMessage()];
    }

    // Contar total de registros na tabela desenhos para referência
    $total_desenhos_tabela = 0;
    try {
        $stmt_count = $pdo->prepare("SELECT COUNT(*) as total FROM desenhos");
        $stmt_count->execute();
        $total_desenhos_tabela = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
    } catch (Exception $e) {
        $total_desenhos_tabela = 'Erro ao contar: ' . $e->getMessage();
    }

    // Obter alguns exemplos da tabela desenhos para debug
    $exemplos_tabela_desenhos = [];
    try {
        $stmt_exemplos = $pdo->prepare("SELECT id, quarteirao, quadra, lote, tipo, coordenadas FROM desenhos LIMIT 5");
        $stmt_exemplos->execute();
        $exemplos_tabela_desenhos = $stmt_exemplos->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $exemplos_tabela_desenhos = ['erro' => 'Não foi possível obter exemplos: ' . $e->getMessage()];
    }

    // Resposta de sucesso com debug completo
    $response = [
        'success' => true,
        'coordenadas' => $coordenadas_encontradas,
        'poligonos' => $poligonos_lotes_quadras,
        'stats' => $stats,
        'mensagem' => "Encontradas {$stats['coordenadas_encontradas']} coordenadas e {$stats['poligonos_encontrados']} polígonos para {$stats['total_registros']} registros",
        
        // INFORMAÇÕES DE DEBUG
        'debug' => [
            'queries_executadas' => $debug_queries,
            'registros_debug' => $debug_info,
            'estrutura_tabela_desenhos' => $debug_estrutura_tabela,
            'total_registros_tabela_desenhos' => $total_desenhos_tabela,
            'exemplos_tabela_desenhos' => $exemplos_tabela_desenhos,
            'primeiros_registros_recebidos' => array_slice($registros, 0, 3), // Primeiros 3 registros para análise
            'campos_primeiro_registro' => count($registros) > 0 ? array_keys($registros[0]) : [],
            'resumo_execucao' => [
                'total_registros_processados' => $stats['registros_processados'],
                'total_queries_executadas' => $stats['queries_executadas'],
                'registros_sem_campos_necessarios' => $stats['registros_sem_campos_necessarios'],
                'coordenadas_encontradas' => $stats['coordenadas_encontradas']
            ]
        ]
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

/**
 * Calcula a distância em metros entre duas coordenadas GPS
 */
function calcularDistanciaGPS($lat1, $lng1, $lat2, $lng2) {
    $raio_terra = 6371000; // Raio da Terra em metros
    
    $lat1_rad = deg2rad($lat1);
    $lat2_rad = deg2rad($lat2);
    $delta_lat = deg2rad($lat2 - $lat1);
    $delta_lng = deg2rad($lng2 - $lng1);
    
    $a = sin($delta_lat/2) * sin($delta_lat/2) +
         cos($lat1_rad) * cos($lat2_rad) *
         sin($delta_lng/2) * sin($delta_lng/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    
    return $raio_terra * $c; // Distância em metros
}

/**
 * Verifica se um polígono está próximo (até 500m) de algum marcador
 */
function verificarProximidadePoligono($poligono, $coordenadas_marcadores, $distancia_maxima = 500) {
    try {
        // Tentar decodificar as coordenadas do polígono
        $coordenadas_raw = $poligono['coordenadas'];
        $coordenadas_poligono = json_decode($coordenadas_raw, true);
        
        if (!$coordenadas_poligono || !is_array($coordenadas_poligono)) {
            return false;
        }
        
        // Para polígonos, vamos usar o centróide (centro geométrico) ou primeiro ponto
        $lat_poligono = null;
        $lng_poligono = null;
        
        if (is_array($coordenadas_poligono) && count($coordenadas_poligono) > 0) {
            // Se é um array de pontos, calcular centróide
            $total_lat = 0;
            $total_lng = 0;
            $count = 0;
            
            foreach ($coordenadas_poligono as $ponto) {
                if (isset($ponto['lat']) && isset($ponto['lng'])) {
                    $total_lat += $ponto['lat'];
                    $total_lng += $ponto['lng'];
                    $count++;
                }
            }
            
            if ($count > 0) {
                $lat_poligono = $total_lat / $count;
                $lng_poligono = $total_lng / $count;
            }
        }
        
        if ($lat_poligono === null || $lng_poligono === null) {
            return false;
        }
        
        // Verificar distância para cada marcador
        foreach ($coordenadas_marcadores as $marcador) {
            if (isset($marcador['coordenadas']) && is_array($marcador['coordenadas'])) {
                $coord_marcador = $marcador['coordenadas'][0]; // Primeiro ponto do marcador
                
                if (isset($coord_marcador['lat']) && isset($coord_marcador['lng'])) {
                    $distancia = calcularDistanciaGPS(
                        $lat_poligono, 
                        $lng_poligono,
                        $coord_marcador['lat'],
                        $coord_marcador['lng']
                    );
                    
                    if ($distancia <= $distancia_maxima) {
                        return true;
                    }
                }
            }
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("Erro ao verificar proximidade: " . $e->getMessage());
        return false;
    }
}

// PDO não precisa de close() explícito, mas podemos definir como null
$pdo = null;
?>
