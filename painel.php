<?php

session_start();
//include("verifica_login.php");
include("connection.php");

// Endpoint simples para salvar/carregar última pesquisa do usuário
if (isset($_GET['searchApi'])) {
    header('Content-Type: application/json');

    // Usar email do usuário logado como identificador único e persistente
    // Se não houver usuário logado, usar session_id como fallback
    $userId = null;
    if (isset($_SESSION['usuario']) && is_array($_SESSION['usuario']) && isset($_SESSION['usuario'][1])) {
        // $_SESSION['usuario'][1] contém o email do usuário
        $userId = md5($_SESSION['usuario'][1]); // MD5 para evitar caracteres especiais no nome do arquivo
    } else {
        // Fallback: usar session_id se não houver usuário logado
        $userId = md5(session_id());
    }

    $dir = __DIR__ . '/jsonPesquisa';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $file = $dir . '/' . $userId . '.json';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $input = [];
        }

        // Adicionar email do usuário ao JSON para identificação
        $userEmail = null;
        if (isset($_SESSION['usuario']) && is_array($_SESSION['usuario']) && isset($_SESSION['usuario'][1])) {
            $userEmail = $_SESSION['usuario'][1];
        }
        $input['usuario_email'] = $userEmail;

        // Se vier com resultados, salvar também (cache)
        // Os resultados podem vir em: input.results ou input.resultados
        // Não salvar se não vier resultados (apenas salvar pesquisa)
        
        file_put_contents($file, json_encode($input, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        echo json_encode(['status' => 'ok']);
        exit;
    }

    if (file_exists($file)) {
        readfile($file);
    } else {
        echo json_encode(new stdClass());
    }
    exit;
}

// Endpoint para realizar pesquisa no banco de dados
if (isset($_GET['pesquisar'])) {
    header('Content-Type: application/json');

    try {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input) || !isset($input['type']) || !isset($input['values'])) {
            echo json_encode(['error' => 'Dados inválidos']);
            exit;
        }

        $type = $input['type'];
        $values = $input['values'];
        
        // Verificar se há cache de resultados salvos
        $userId = null;
        if (isset($_SESSION['usuario']) && is_array($_SESSION['usuario']) && isset($_SESSION['usuario'][1])) {
            $userId = md5($_SESSION['usuario'][1]);
        } else {
            $userId = md5(session_id());
        }
        
        $dir = __DIR__ . '/jsonPesquisa';
        $file = $dir . '/' . $userId . '.json';
        
        $usarCache = false;
        $cacheData = null;
        $allResults = null;
        $allDesenhosPorImobId = null;
        $marcadoresOrfaos = []; // Array para armazenar marcadores órfãos (apenas para cadastros_nao_desenhados)
        
        if (file_exists($file)) {
            $cacheData = json_decode(file_get_contents($file), true);
            // Verificar se o cache corresponde à pesquisa atual
            if (is_array($cacheData) && 
                isset($cacheData['type']) && $cacheData['type'] === $type &&
                isset($cacheData['values']) && is_array($cacheData['values']) &&
                is_array($values)) {
                
                // Comparar valores de forma mais robusta
                $cacheValuesNormalized = $cacheData['values'];
                $currentValuesNormalized = $values;
                
                // Normalizar arrays para comparação (ordenar chaves e converter tipos)
                ksort($cacheValuesNormalized);
                ksort($currentValuesNormalized);
                
                // Converter valores para string para comparação
                $cacheValuesStr = json_encode($cacheValuesNormalized, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
                $currentValuesStr = json_encode($currentValuesNormalized, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
                
                if ($cacheValuesStr === $currentValuesStr &&
                    isset($cacheData['results']) && 
                    is_array($cacheData['results']) &&
                    isset($cacheData['results']['allResults']) &&
                    is_array($cacheData['results']['allResults']) &&
                    count($cacheData['results']['allResults']) > 0 &&
                    isset($cacheData['results']['allDesenhosPorImobId']) &&
                    is_array($cacheData['results']['allDesenhosPorImobId'])) {
                    // Cache válido encontrado - mesma pesquisa com resultados
                    $usarCache = true;
                    $allResults = $cacheData['results']['allResults'];
                    $allDesenhosPorImobId = $cacheData['results']['allDesenhosPorImobId'];
                    // Carregar marcadores órfãos do cache se existirem (apenas para cadastros_nao_desenhados)
                    if ($type === 'cadastros_nao_desenhados' && isset($cacheData['results']['marcadoresOrfaos'])) {
                        $marcadoresOrfaos = $cacheData['results']['marcadoresOrfaos'];
                    }
                }
            }
        }
        
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        
        // Só construir query SQL se não usar cache (pesquisa diferente ou sem cache)
        if (!$usarCache) {
            // Para cadastros_nao_desenhados, usar query especial sem JOIN
            if ($type === 'cadastros_nao_desenhados') {
                $sql = "SELECT cad.* 
                        FROM cadastro cad 
                        WHERE cad.imob_id = cad.imob_id_principal 
                        AND NOT EXISTS ( 
                            SELECT 1 FROM desenhos des 
                            WHERE des.camada = 'marcador_quadra' 
                            AND des.quarteirao = cad.cara_quarteirao 
                            AND des.quadra = cad.quadra 
                            AND des.lote = cad.lote 
                        )";
                $params = [];
                
                // Adicionar condição de loteamento se preenchido
                if (!empty($values['loteamento'])) {
                    $sql .= " AND cad.nome_loteamento LIKE :loteamento";
                    $params[':loteamento'] = '%' . $values['loteamento'] . '%';
                }
                
                // Adicionar condição de quarteirão se preenchido (tratar como int)
                if (!empty($values['quarteirao'])) {
                    $quarteiraoInt = intval($values['quarteirao']);
                    if ($quarteiraoInt > 0) {
                        $sql .= " AND cad.cara_quarteirao = :quarteirao";
                        $params[':quarteirao'] = $quarteiraoInt;
                    }
                }
                
                $sql .= " ORDER BY cad.nome_loteamento, cad.cara_quarteirao";
                // Não há switch para este tipo, pular direto para execução
            } else {
                // Construir query SQL baseada no tipo de pesquisa usando JOIN otimizado
                // Selecionar todas as colunas do cadastro e colunas necessárias do desenhos
                $sql = "SELECT cad.*, 
                               des.id as desenho_id, 
                               des.tipo as desenho_tipo, 
                               des.coordenadas as desenho_coordenadas, 
                               des.quarteirao as desenho_quarteirao, 
                               des.quadra as desenho_quadra, 
                               des.lote as desenho_lote, 
                               des.cor_usuario as desenho_cor_usuario, 
                               des.cor as desenho_cor
                        FROM cadastro cad 
                        LEFT JOIN desenhos des ON (des.camada = 'poligono lote' OR des.camada = 'poligono_lote') 
                                              AND des.status > 0 
                                              AND des.quarteirao = cad.cara_quarteirao 
                                              AND des.quadra = cad.quadra 
                                              AND des.lote = cad.lote
                        WHERE cad.imob_id = cad.imob_id_principal";
                $params = [];

                switch ($type) {
            case 'endereco_numero':
                if (!empty($values['endereco'])) {
                    $sql .= " AND cad.logradouro LIKE :endereco";
                    $params[':endereco'] = '%' . $values['endereco'] . '%';
                }
                if (!empty($values['numero'])) {
                    $sql .= " AND cad.numero = :numero";
                    $params[':numero'] = $values['numero'];
                }
                break;

            case 'quarteirao':
                if (!empty($values['quarteirao'])) {
                    $sql .= " AND cad.cara_quarteirao = :quarteirao";
                    $params[':quarteirao'] = $values['quarteirao'];
                }
                break;

            case 'quarteirao_quadra':
                if (!empty($values['quarteirao'])) {
                    $sql .= " AND cad.cara_quarteirao = :quarteirao";
                    $params[':quarteirao'] = $values['quarteirao'];
                }
                if (!empty($values['quadra'])) {
                    $sql .= " AND cad.quadra = :quadra";
                    $params[':quadra'] = $values['quadra'];
                }
                break;

            case 'quarteirao_quadra_lote':
                if (!empty($values['quarteirao'])) {
                    $sql .= " AND cad.cara_quarteirao = :quarteirao";
                    $params[':quarteirao'] = $values['quarteirao'];
                }
                if (!empty($values['quadra'])) {
                    $sql .= " AND cad.quadra = :quadra";
                    $params[':quadra'] = $values['quadra'];
                }
                if (!empty($values['lote'])) {
                    $sql .= " AND cad.lote = :lote";
                    $params[':lote'] = $values['lote'];
                }
                break;

            case 'loteamento':
                if (!empty($values['loteamento'])) {
                    $sql .= " AND cad.nome_loteamento LIKE :loteamento";
                    $params[':loteamento'] = '%' . $values['loteamento'] . '%';
                }
                break;

            case 'loteamento_quadra':
                if (!empty($values['loteamento'])) {
                    $sql .= " AND cad.nome_loteamento LIKE :loteamento";
                    $params[':loteamento'] = '%' . $values['loteamento'] . '%';
                }
                if (!empty($values['quadra'])) {
                    $sql .= " AND cad.quadra = :quadra";
                    $params[':quadra'] = $values['quadra'];
                }
                break;

            case 'loteamento_quadra_lote':
                if (!empty($values['loteamento'])) {
                    $sql .= " AND cad.nome_loteamento LIKE :loteamento";
                    $params[':loteamento'] = '%' . $values['loteamento'] . '%';
                }
                if (!empty($values['quadra'])) {
                    $sql .= " AND cad.quadra = :quadra";
                    $params[':quadra'] = $values['quadra'];
                }
                if (!empty($values['lote'])) {
                    $sql .= " AND cad.lote = :lote";
                    $params[':lote'] = $values['lote'];
                }
                break;

            case 'cnpj':
                if (!empty($values['cnpj'])) {
                    $sql .= " AND cad.cnpj = :cnpj";
                    $params[':cnpj'] = $values['cnpj'];
                }
                break;

            case 'uso_imovel':
                if (!empty($values['uso_imovel'])) {
                    $sql .= " AND cad.uso_imovel LIKE :uso_imovel";
                    $params[':uso_imovel'] = '%' . $values['uso_imovel'] . '%';
                }
                break;

            case 'bairro':
                if (!empty($values['bairro'])) {
                    $sql .= " AND cad.bairro LIKE :bairro";
                    $params[':bairro'] = '%' . $values['bairro'] . '%';
                }
                break;

            case 'inscricao':
                if (!empty($values['inscricao'])) {
                    $sql .= " AND cad.inscricao = :inscricao";
                    $params[':inscricao'] = $values['inscricao'];
                }
                break;

            case 'imob_id':
                if (!empty($values['imob_id'])) {
                    $sql .= " AND cad.imob_id = :imob_id";
                    $params[':imob_id'] = $values['imob_id'];
                }
                break;

            case 'zona':
                if (!empty($values['zona'])) {
                    $sql .= " AND cad.zona = :zona";
                    $params[':zona'] = $values['zona'];
                }
                break;

            case 'cat_via':
                if (!empty($values['cat_via'])) {
                    $sql .= " AND cad.cat_via LIKE :cat_via";
                    $params[':cat_via'] = '%' . $values['cat_via'] . '%';
                }
                break;

            case 'tipo_edificacao':
                if (!empty($values['tipo_edificacao'])) {
                    $sql .= " AND cad.tipo_edificacao LIKE :tipo_edificacao";
                    $params[':tipo_edificacao'] = '%' . $values['tipo_edificacao'] . '%';
                }
                break;

            case 'area_construida':
                if (!empty($values['area_construida_min'])) {
                    $sql .= " AND cad.total_construido >= :area_construida_min";
                    $params[':area_construida_min'] = floatval($values['area_construida_min']);
                }
                if (!empty($values['area_construida_max'])) {
                    $sql .= " AND cad.total_construido <= :area_construida_max";
                    $params[':area_construida_max'] = floatval($values['area_construida_max']);
                }
                break;

            case 'area_terreno':
                if (!empty($values['area_terreno_min'])) {
                    $sql .= " AND cad.area_terreno >= :area_terreno_min";
                    $params[':area_terreno_min'] = floatval($values['area_terreno_min']);
                }
                if (!empty($values['area_terreno_max'])) {
                    $sql .= " AND cad.area_terreno <= :area_terreno_max";
                    $params[':area_terreno_max'] = floatval($values['area_terreno_max']);
                }
                break;

            default:
                echo json_encode(['error' => 'Tipo de pesquisa não implementado']);
                exit;
            }
            }
        }

        // Paginação
        $page = isset($input['page']) && is_numeric($input['page']) ? max(1, intval($input['page'])) : 1;
        $limit = isset($input['limit']) && is_numeric($input['limit']) ? max(1, min(1000, intval($input['limit']))) : 50;
        $offset = ($page - 1) * $limit;

        // Se usar cache, buscar do cache
        if ($usarCache && $allResults !== null) {
            // Usar o totalCount salvo no cache (total real do banco)
            $totalCount = isset($cacheData['totalCount']) ? intval($cacheData['totalCount']) : count($allResults);
            
            // Aplicar paginação APENAS na exibição dos dados da tabela
            $results = array_slice($allResults, $offset, $limit);
            
            // IMPORTANTE: Retornar TODOS os desenhos, não apenas os da página atual
            // Os desenhos devem aparecer todos no mapa, independente da paginação
            $desenhosPorImobId = $allDesenhosPorImobId; // TODOS os desenhos de TODOS os resultados
            
            // Coletar TODOS os desenhos (não apenas da página atual)
            $desenhos = [];
            foreach ($allDesenhosPorImobId as $imobDesenhos) {
                $desenhos = array_merge($desenhos, $imobDesenhos);
            }
            
            // Marcadores órfãos já foram carregados do cache acima (se existirem)
        } else {
            // Fazer pesquisa no banco usando JOIN otimizado
            // Primeiro, contar o total de resultados
            // Para cadastros_nao_desenhados, usar NOT EXISTS
            if ($type === 'cadastros_nao_desenhados') {
                $countSql = "SELECT COUNT(*) as total 
                            FROM cadastro cad 
                            WHERE cad.imob_id = cad.imob_id_principal 
                            AND NOT EXISTS ( 
                                SELECT 1 FROM desenhos des 
                                WHERE des.camada = 'marcador_quadra' 
                                AND des.quarteirao = cad.cara_quarteirao 
                                AND des.quadra = cad.quadra 
                                AND des.lote = cad.lote 
                            )";
                
                // Adicionar condição de loteamento se preenchido
                if (!empty($values['loteamento'])) {
                    $countSql .= " AND cad.nome_loteamento LIKE :loteamento";
                }
                
                // Adicionar condição de quarteirão se preenchido (tratar como int)
                if (!empty($values['quarteirao'])) {
                    $quarteiraoInt = intval($values['quarteirao']);
                    if ($quarteiraoInt > 0) {
                        $countSql .= " AND cad.cara_quarteirao = :quarteirao";
                    }
                }
            } else {
                // Extrair a parte WHERE da query (tudo após WHERE até o fim, removendo possíveis ORDER BY)
                $wherePart = '';
                if (preg_match('/WHERE\s+(.+?)(?:\s+ORDER\s+BY|$)/is', $sql, $matches)) {
                    $wherePart = trim($matches[1]);
                } else if (preg_match('/WHERE\s+(.+)$/is', $sql, $matches)) {
                    $wherePart = trim($matches[1]);
                }
                
                if (empty($wherePart)) {
                    $wherePart = '1=1';
                }
                
                $countSql = "SELECT COUNT(*) as total FROM cadastro cad WHERE " . $wherePart;
            }
            
            $countStmt = $pdo->prepare($countSql);
            foreach ($params as $key => $value) {
                $countStmt->bindValue($key, $value);
            }
            $countStmt->execute();
            $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Buscar TODOS os resultados (sem paginação) para cache
            $stmtTodos = $pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmtTodos->bindValue($key, $value);
            }
            $stmtTodos->execute();
            $allRowsWithDesenhos = $stmtTodos->fetchAll(PDO::FETCH_ASSOC);
            
            // Para cadastros_nao_desenhados, os resultados já vêm diretos (sem JOIN)
            if ($type === 'cadastros_nao_desenhados') {
                $allResults = $allRowsWithDesenhos;
                $allDesenhosPorImobId = []; // Não há desenhos para este tipo de pesquisa
                
                // Buscar quarteirões distintos dos marcadores "órfãos" (marcadores com quarteirao mas sem quadra e lote)
                $quarteiroesOrfaosSql = "SELECT DISTINCT quarteirao
                                       FROM desenhos
                                       WHERE camada = 'marcador_quadra'
                                       AND status = 1
                                       AND TRIM(quarteirao) != ''
                                       AND (quadra IS NULL OR quadra = '' OR quadra = '0' OR TRIM(quadra) = '')
                                       AND (lote IS NULL OR lote = '' OR lote = '0' OR TRIM(lote) = '')";
                $quarteiroesOrfaosStmt = $pdo->prepare($quarteiroesOrfaosSql);
                $quarteiroesOrfaosStmt->execute();
                $quarteiroesOrfaos = $quarteiroesOrfaosStmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Converter para array simples de quarteirões (strings)
                $marcadoresOrfaos = array_map(function($row) {
                    return $row['quarteirao'];
                }, $quarteiroesOrfaos);
            } else {
                // Para outros tipos, não há quarteirões órfãos
                $marcadoresOrfaos = [];
                // Processar resultados do JOIN: separar dados do cadastro dos desenhos
                $allResults = []; // Array de registros únicos do cadastro
                $allDesenhosPorImobId = []; // Mapa de imob_id => array de desenhos (TODOS)
                $seenImobIds = []; // Para rastrear quais imob_ids já foram processados
                
                foreach ($allRowsWithDesenhos as $row) {
                $imob_id = isset($row['imob_id']) ? $row['imob_id'] : null;
                
                // Se é a primeira vez que vemos este imob_id, adicionar aos resultados
                if ($imob_id && !isset($seenImobIds[$imob_id])) {
                    // Separar colunas do cadastro (remover prefixo desenho_)
                    $cadastroRow = [];
                    foreach ($row as $key => $value) {
                        if (strpos($key, 'desenho_') !== 0) {
                            $cadastroRow[$key] = $value;
                        }
                    }
                    $allResults[] = $cadastroRow;
                    $seenImobIds[$imob_id] = true;
                    $allDesenhosPorImobId[$imob_id] = [];
                        }
                
                // Se há um desenho associado (desenho_id não é NULL)
                if ($imob_id && isset($row['desenho_id']) && $row['desenho_id'] !== null) {
                    // Construir objeto do desenho no formato esperado
                    $desenho = [
                        'id' => $row['desenho_id'],
                        'tipo' => $row['desenho_tipo'],
                        'coordenadas' => $row['desenho_coordenadas'],
                        'quarteirao' => $row['desenho_quarteirao'],
                        'quadra' => $row['desenho_quadra'],
                        'lote' => $row['desenho_lote'],
                        'cor_usuario' => $row['desenho_cor_usuario'],
                        'cor' => $row['desenho_cor']
                    ];
                    
                    // Adicionar desenho ao imob_id correspondente
                    // Verificar se já não existe (para evitar duplicatas)
                    $desenhoExists = false;
                    if (isset($allDesenhosPorImobId[$imob_id])) {
                        foreach ($allDesenhosPorImobId[$imob_id] as $existingDesenho) {
                            if ($existingDesenho['id'] == $desenho['id']) {
                                $desenhoExists = true;
                                break;
                            }
                        }
                }
                
                    if (!$desenhoExists) {
                        $allDesenhosPorImobId[$imob_id][] = $desenho;
                    }
                }
                }
            }
            
            // Aplicar paginação APENAS na exibição dos dados da tabela
            $results = array_slice($allResults, $offset, $limit);
                            
            // IMPORTANTE: Retornar TODOS os desenhos, não apenas os da página atual
            // Os desenhos devem aparecer todos no mapa, independente da paginação
            $desenhosPorImobId = $allDesenhosPorImobId; // TODOS os desenhos de TODOS os resultados
            
            // Coletar TODOS os desenhos (não apenas da página atual)
            $desenhos = [];
            foreach ($allDesenhosPorImobId as $imobDesenhos) {
                $desenhos = array_merge($desenhos, $imobDesenhos);
            }
        }

        // Calcular informações de paginação
        $totalPages = ceil($totalCount / $limit);

        // Salvar pesquisa apenas se não usou cache (nova pesquisa)
        if (!$usarCache) {
            // Salvar pesquisa e resultados no JSON (apenas quando faz nova pesquisa)
            // Salvar TODOS os resultados e TODOS os desenhos
            $cacheData = [
                'type' => $type,
                'values' => $values,
                'totalCount' => $totalCount, // Salvar o total real do banco
                'usuario_email' => isset($_SESSION['usuario']) && is_array($_SESSION['usuario']) && isset($_SESSION['usuario'][1]) ? $_SESSION['usuario'][1] : null,
                'results' => [
                    'allResults' => $allResults, // TODOS os resultados
                    'allDesenhosPorImobId' => $allDesenhosPorImobId, // TODOS os desenhos
                    'marcadoresOrfaos' => $marcadoresOrfaos // Marcadores órfãos (apenas para cadastros_nao_desenhados)
                ]
            ];
            file_put_contents($file, json_encode($cacheData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }
        
        // Retornar resultados com os desenhos encontrados
        echo json_encode([
            'success' => true,
            'total' => intval($totalCount),
            'page' => $page,
            'limit' => $limit,
            'totalPages' => $totalPages,
            'dados' => $results,
            'desenhos' => $desenhos,
            'desenhosPorImobId' => $desenhosPorImobId, // Mapa de imob_id => desenhos
            'marcadoresOrfaos' => $marcadoresOrfaos // Marcadores órfãos (apenas para cadastros_nao_desenhados)
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'error' => 'Erro ao executar pesquisa: ' . $e->getMessage()
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'error' => 'Erro: ' . $e->getMessage()
        ]);
    }
    exit;
}

?>


<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mapa com Framework</title>

    <!-- jQuery -->
    <script src="jquery.min.js"></script>
    <!-- Bootstrap 5.3 -->
    <script src="bootstrap.bundle.min.js"></script>
    <link href="bootstrap.min.css" rel="stylesheet">

    <!--Conexão com fonts do Google-->
    <link href='bibliotecas/font_Muli.css' rel='stylesheet'>

    <!-- Font Awesome para ícones -->
    <link rel="stylesheet" href="bibliotecas/all.min.css">

    <!--Conexão com biblioteca de BUFFER para poligono-->
    <script src="bibliotecas/turf.min.js" defer></script>

    <script src="bibliotecas/proj4.js" defer></script>

    <!-- Biblioteca para conversão KML para GeoJSON -->
    <script src="bibliotecas/togeojson.js" defer></script>

    <!-- Google Maps API -->
    <script src="apiGoogle.js"></script>

    <style>
        html,
        body {
            width: 100%;
            height: 100vh;
            margin: 0;
            padding: 0;
            background-color: white;
            box-sizing: border-box;
        }

        #map {
            width: 100%;
            height: 100%;
            border-top: 0px solid black;
            border-left: 1px solid black;
            border-right: 1px solid black;
            border-bottom: 1px solid black;
        }

        gmp-internal-camera-control {
            display: none !important;
        }

        .divContainerMap {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            box-sizing: border-box;
        }

        .dropdown-menu {
            padding: 0 30px;
        }

        /* Modal de carregamento */
        .modal-loading {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(3px);
        }

        .modal-content-loading {
            background-color: #fff;
            margin: 15% auto;
            padding: 30px;
            border-radius: 15px;
            width: 400px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            animation: modalSlideIn 0.3s ease-out;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .loading-spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #007bff;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .loading-text {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }

        .loading-subtitle {
            font-size: 14px;
            color: #666;
        }

        /* ==================== QUADRINHO DE PESQUISA ==================== */
        #searchBox {
            position: absolute;
            top: 70px;
            /* Abaixo do navbar */
            left: 50%;
            transform: translateX(-50%);
            /* Centralizar horizontalmente */
            z-index: 999;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            display: inline-block;
            max-width: calc(100% - 30px);
            width: fit-content; /* Largura baseada no conteúdo (searchControls) */
            min-width: fit-content; /* Garantir que sempre seja baseado no conteúdo */
        }

        #searchControls {
            padding: 15px;
            display: flex;
            gap: 10px;
            align-items: center;
            white-space: nowrap;
            width: fit-content;
            /* Largura baseada no conteúdo (inputs) */
        }

        #btnToggleResults {
            height: 25px;
            margin: -15px 0 0 0;
            border-radius: 0 0 8px 8px;
            padding: 0;
            box-sizing: border-box;
            /* Largura será definida via JavaScript para corresponder ao searchControls */
        }

        #searchType {
            min-width: 200px;
        }

        #searchInputs {
            display: flex;
            gap: 10px;
            white-space: nowrap;
        }

        #searchInputs input {
            min-width: 150px;
            max-width: 220px;
        }

        #btnPesquisar {
            white-space: nowrap;
        }

        #btnPesquisar .btn-loading {
            display: none;
        }

        #btnPesquisar.loading .btn-text {
            display: none;
        }

        #btnPesquisar.loading .btn-loading {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        /* Div flutuante para contador de marcadores órfãos */
        #marcadoresOrfaosCounter {
            position: absolute;
            top: 80px;
            left: 15px;
            z-index: 1000;
            background: rgba(255, 0, 0, 0.9);
            color: white;
            padding: 10px 15px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            font-weight: bold;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }

        #marcadoresOrfaosCounter i {
            font-size: 18px;
        }

        /* Container da tabela de resultados - QUADRO SEPARADO */
        #searchResultsBox {
            position: absolute;
            top: 162px;
            /* Posição fixa definida pelo usuário */
            left: 0;
            right: 0;
            z-index: 999;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            display: none;
            /* Oculto por padrão */
            width: calc(100% - 30px);
            margin: 0 auto;
            max-height: calc(100vh - 180px);
            /* Ocupa tela toda a partir da posição */
            overflow: hidden;
        }

        #searchResultsBox.visible {
            display: block;
        }

        #searchResultsHeader {
            background: #343a40;
            color: white;
            padding: 10px 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            user-select: none;
            border-radius: 8px 8px 0 0;
        }

        #searchResultsTitle {
            font-weight: bold;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        #searchResultsBody {
            padding: 0;
            max-height: calc(100vh - 220px);
            /* Altura máxima ocupando tela toda */
            overflow-y: auto;
            overflow-x: hidden; /* Não mostrar scroll horizontal padrão aqui */
            position: relative;
            display: flex;
            flex-direction: column;
        }

        #searchResultsBody .table-responsive {
            position: relative;
            overflow-x: auto; /* Permitir scroll horizontal apenas aqui */
            overflow-y: visible;
            flex: 1;
            padding: 0;
        }

        /* Mostrar scrollbar customizada na tabela */
        #searchResultsBody .table-responsive::-webkit-scrollbar {
            height: 10px;
        }

        #searchResultsBody .table-responsive::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        #searchResultsBody .table-responsive::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 5px;
        }

        #searchResultsBody .table-responsive::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        #searchResultsBody table {
            font-size: 12px;
            margin-bottom: 0;
            width: 100%;
        }

        /* Garantir que o header da caixa não interfira */
        #searchResultsHeader {
            position: relative;
            z-index: 200;
            background: #343a40;
        }

        #searchResultsBody table thead {
            position: sticky;
            top: 0; /* Fixo no topo do container #searchResultsBody (não relativo ao header) */
            z-index: 150;
            background: #343a40 !important;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        /* Garantir que o cabeçalho cubra o conteúdo completamente */
        #searchResultsBody table thead th {
            background: #343a40 !important;
            position: relative;
            z-index: 151;
            border-bottom: 2px solid #495057;
        }

        /* Garantir que as células do corpo não apareçam atrás do cabeçalho */
        #searchResultsBody table tbody td {
            background: white;
        }

        /* Paginação fixa na parte inferior */
        #searchResultsPaginationBottom {
            position: sticky;
            bottom: 0;
            z-index: 200;
            background: white;
            padding: 10px 15px;
            border-top: 1px solid #dee2e6;
            box-shadow: 0 -2px 4px rgba(0, 0, 0, 0.1);
            display: none; /* Será exibida quando necessário */
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }


        #searchResultsBody table th {
            white-space: nowrap;
            padding: 8px 10px;
            font-size: 11px;
        }

        #searchResultsBody table th:first-child {
            width: 50px;
            text-align: center;
            padding: 8px 5px;
        }

        #searchResultsBody table td {
            padding: 6px 10px;
            white-space: nowrap;
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        #searchResultsBody table td:first-child {
            text-align: center;
            padding: 6px 5px;
            width: 50px;
        }

        #searchResultsBody table .row-checkbox {
            cursor: pointer;
        }

        #searchResultsBody table #selectAllCheckbox {
            cursor: pointer;
        }

        /* Estilo para linhas sem desenho */
        #searchResultsBody table tbody tr.no-drawing {
            background-color: #ffe6e6 !important; /* Vermelho bem clarinho */
        }

        #searchResultsBody table tbody tr.no-drawing td {
            color: #dc3545; /* Texto vermelho */
            text-decoration: line-through; /* Texto tachado */
        }

        #searchResultsBody table tbody tr.no-drawing .row-checkbox {
            cursor: not-allowed;
        }

        /* Estilo para linhas clicáveis */
        #searchResultsBody table tbody tr.row-clickable {
            cursor: pointer;
        }
        #searchResultsBody table tbody tr.row-clickable:hover {
            background-color: #f8f9fa;
            transition: background-color 0.2s;
        }
    </style>

<body>

    <div class="divContainerMap">
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
            <div class="container-fluid">

                <!-- Título -->
                <a class="navbar-brand" href="#">Visão geral das quadrículas</a>

                <!-- Botões -->
                <div class="d-flex align-items-center flex-grow-1 gap-2">
                    <!-- Dropdown de Camadas -->
                    <div class="dropdown">
                        <button class="btn btn-outline-light dropdown-toggle" type="button" id="camadasDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-layer-group"></i> Camadas
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="camadasDropdown">
                            <li>
                                <div class="form-check px-3 py-2">
                                    <input class="form-check-input" type="checkbox" value="" id="checkboxQuadriculas" checked>
                                    <label class="form-check-label" for="checkboxQuadriculas">
                                        Quadrículas
                                    </label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check px-3 py-2">
                                    <input class="form-check-input" type="checkbox" value="" id="checkboxLimiteMunicipio" checked>
                                    <label class="form-check-label" for="checkboxLimiteMunicipio">
                                        Limite do Município
                                    </label>
                                </div>
                            </li>
                        </ul>
                    </div>

                    <!-- Botão de Navegação para Quadrícula Selecionada -->
                    <button class="btn btn-warning" id="btnIrConsulta" onclick="irConsulta()">
                        <i class="fas fa-external-link-alt"></i> Consultas
                    </button>

                    <!-- Botão de Navegação para Quadrícula Selecionada -->
                    <button class="btn btn-primary" id="btnIrQuadricula" style="display: none;">
                        <i class="fas fa-external-link-alt"></i> Ir para Quadrícula
                    </button>
                </div>

                <!-- Botão Sair -->
                <div class="d-flex">
                    <a href="suporte_redirect.php" class="btn btn-primary">Suporte</a>
                    &nbsp;&nbsp;&nbsp;
                    <a href="logout.php" class="btn btn-danger">Sair</a>
                </div>
            </div>
        </nav>

        <div id="map"></div>

        <!-- Div flutuante para contador de quarteirões órfãos -->
        <div id="marcadoresOrfaosCounter" style="display: none;">
            <i class="fas fa-map-marked-alt"></i>
            <span id="marcadoresOrfaosCount">0</span> quarteirões
        </div>

        <!-- Quadrinho de Pesquisa -->
        <div id="searchBox">
            <div id="searchControls">
                <select id="searchType" class="form-select">
                    <option value="">Selecione o tipo de pesquisa</option>
                </select>
                <div id="searchInputs"></div>
                <button class="btn btn-success" id="btnPesquisar">
                    <span class="btn-text">Pesquisar</span>
                    <span class="btn-loading">
                        <span class="spinner-border spinner-border-sm" role="status"></span>
                        Pesquisando...
                    </span>
                </button>
            </div>
            <button class="btn btn-light" id="btnToggleResults" style="display: none;">
            </button>
        </div>

        <!-- Quadrinho de Resultados (Tabela) - SEPARADO -->
        <div id="searchResultsBox">
            <div id="searchResultsHeader">
                <span id="searchResultsTitle">
                    <i class="fas fa-table"></i> Resultados da Pesquisa
                    <span id="resultsCount" class="badge bg-primary ms-2">0 registros</span>
                    <span id="desenhosEncontrados" class="badge bg-success ms-2">0 com desenho</span>
                    <span id="desenhosNaoEncontrados" class="badge bg-danger ms-2">0 sem desenho</span>
                </span>
            </div>
            <div id="searchResultsBody">
                <div class="table-responsive" id="tableContainer">
                    <table class="table table-striped table-hover table-sm">
                        <thead class="table-dark">
                            <tr id="resultsTableHead">
                                <!-- Cabeçalhos serão preenchidos dinamicamente -->
                            </tr>
                        </thead>
                        <tbody id="resultsTableBody">
                            <!-- Dados serão preenchidos dinamicamente -->
                        </tbody>
                    </table>
                </div>
                <div id="searchResultsPaginationBottom" style="display: none;">
                    <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                        <div id="paginationInfoBottom" class="text-muted small"></div>
                        <div style="display: flex; align-items: center; gap: 5px;">
                            <label for="resultsPerPage" class="text-muted small" style="margin: 0; white-space: nowrap;">Resultados por página:</label>
                            <select id="resultsPerPage" class="form-select form-select-sm" style="width: auto; min-width: 80px;">
                                <option value="25">25</option>
                                <option value="50" selected>50</option>
                                <option value="100">100</option>
                                <option value="250">250</option>
                                <option value="500">500</option>
                                <option value="1000">1000</option>
                            </select>
                        </div>
                    </div>
                    <nav aria-label="Paginação">
                        <ul id="paginationControlsBottom" class="pagination pagination-sm mb-0">
                            <!-- Controles de paginação serão preenchidos dinamicamente -->
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de carregamento -->
    <div id="modalLoading" class="modal-loading">
        <div class="modal-content-loading">
            <div class="loading-spinner"></div>
            <div class="loading-text">Carregando Quadrícula...</div>
            <div class="loading-subtitle" id="loadingSubtitle">Aguarde um momento</div>
        </div>
    </div>

    <script>
        // =================== API simples para salvar/carregar pesquisa ===================
        // (implementada no topo do arquivo PHP)

        let coordsLocal = {
            lat: -22.754690200587653,
            lng: -47.157327848672836
        };
        let map;
        let quadriculasPolygons = [];
        let quadriculasRotulos = [];
        let searchResultPolygons = []; // Array para armazenar polígonos dos resultados da pesquisa
        let polygonsByImobId = {}; // Mapa de imob_id => array de polígonos
        let searchResultMarkers = []; // Array para armazenar marcadores órfãos dos resultados da pesquisa
        let quarteiraoPolygon = null; // Polígono do quarteirão pesquisado (do JSON)
        let selectedPolygon = null;
        let selectedQuadricula = null;
        let limitePolyline = null;

        // Inicializar arrays para quadrículas
        if (!quadriculasPolygons) quadriculasPolygons = [];
        if (!quadriculasRotulos) quadriculasRotulos = [];

        // Função para mostrar modal de carregamento
        function showLoadingModal(quadriculaNome) {
            const modal = document.getElementById('modalLoading');
            const subtitle = document.getElementById('loadingSubtitle');

            subtitle.textContent = `Redirecionando para ${quadriculaNome}...`;
            modal.style.display = 'block';

            // Prevenir scroll da página
            document.body.style.overflow = 'hidden';
        }

        // Função para esconder modal de carregamento
        function hideLoadingModal() {
            const modal = document.getElementById('modalLoading');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Função para navegar para quadrícula com modal
        function navigateToQuadricula(quadriculaNome) {
            showLoadingModal(quadriculaNome);

            // Pequeno delay para mostrar o modal antes do redirecionamento
            setTimeout(() => {
                window.location.href = `index_3.php?quadricula=${quadriculaNome}`;
            }, 500);
        }

        // Função para carregar quadrículas do KML
        function carregarQuadriculasKML(urlKML = 'quadriculas_paulinia.kml') {
            if (!window.toGeoJSON) {
                console.error('toGeoJSON não está carregado!');
                return;
            }
            if (!map) {
                console.error('O mapa ainda não foi inicializado!');
                return;
            }

            // Remove quadrículas antigas
            quadriculasPolygons.forEach(obj => {
                if (obj.setMap) obj.setMap(null);
            });
            quadriculasPolygons = [];

            // Remove rótulos antigos
            quadriculasRotulos.forEach(obj => {
                if (obj.setMap) obj.setMap(null);
            });
            quadriculasRotulos = [];

            // Carrega o KML
            fetch(urlKML)
                .then(res => res.text())
                .then(kmlText => {
                    const parser = new DOMParser();
                    const kml = parser.parseFromString(kmlText, 'text/xml');
                    const geojson = toGeoJSON.kml(kml);

                    geojson.features.forEach(f => {
                        let obj = null;
                        let centro = null;

                        if (f.geometry.type === 'Polygon' || f.geometry.type === 'MultiPolygon') {
                            let paths = [];
                            if (f.geometry.type === 'Polygon') {
                                paths = f.geometry.coordinates[0].map(([lng, lat]) => ({
                                    lat,
                                    lng
                                }));
                            } else if (f.geometry.type === 'MultiPolygon') {
                                f.geometry.coordinates.forEach(poly => {
                                    paths = paths.concat(poly[0].map(([lng, lat]) => ({
                                        lat,
                                        lng
                                    })));
                                });
                            }

                            obj = new google.maps.Polygon({
                                paths: paths,
                                strokeColor: '#000000',
                                strokeOpacity: 0.2,
                                strokeWeight: 2,
                                fillColor: 'transparent',
                                fillOpacity: 0,
                                clickable: true,
                                zIndex: 1
                            });

                            // Calcular centro do polígono
                            const bounds = new google.maps.LatLngBounds();
                            paths.forEach(path => bounds.extend(path));
                            centro = bounds.getCenter();
                        }

                        if (obj) {
                            // Armazenar dados da quadrícula no polígono
                            obj.quadriculaData = {
                                nome: f.properties ? f.properties.name : 'Quadrícula',
                                centro: centro
                            };

                            // Adicionar eventos do polígono
                            obj.addListener('mouseover', function() {
                                if (obj !== selectedPolygon) {
                                    obj.setOptions({
                                        strokeWeight: 3,
                                        strokeColor: '#FF0000',
                                        fillColor: '#FF0000',
                                        fillOpacity: 0.5,
                                        zIndex: 3
                                    });
                                }
                            });

                            obj.addListener('mouseout', function() {
                                if (obj !== selectedPolygon) {
                                    obj.setOptions({
                                        strokeWeight: 2,
                                        strokeColor: '#000000',
                                        fillColor: 'transparent',
                                        fillOpacity: 0,
                                        zIndex: 2
                                    });
                                }
                            });

                            obj.addListener('click', function() {
                                // Desselecionar polígono anterior se existir
                                if (selectedPolygon) {
                                    selectedPolygon.setOptions({
                                        strokeWeight: 2,
                                        strokeColor: '#000000',
                                        fillColor: 'transparent',
                                        fillOpacity: 0,
                                        zIndex: 2
                                    });
                                }

                                // Selecionar novo polígono
                                selectedPolygon = obj;
                                selectedQuadricula = obj.quadriculaData;
                                obj.setOptions({
                                    strokeWeight: 3,
                                    strokeColor: '#0066FF',
                                    fillColor: '#0066FF',
                                    fillOpacity: 0.5,
                                    zIndex: 4
                                });

                                // Mostrar botão de navegação
                                showNavigationButton();
                            });

                            // Adicionar evento de duplo clique para navegação direta
                            obj.addListener('dblclick', function(event) {
                                // Prevenir o zoom do mapa no duplo clique
                                event.stop();

                                // Selecionar a quadrícula primeiro
                                if (selectedPolygon) {
                                    selectedPolygon.setOptions({
                                        strokeWeight: 2,
                                        strokeColor: '#000000',
                                        fillColor: 'transparent',
                                        fillOpacity: 0,
                                        zIndex: 2
                                    });
                                }

                                selectedPolygon = obj;
                                selectedQuadricula = obj.quadriculaData;
                                obj.setOptions({
                                    strokeWeight: 3,
                                    strokeColor: '#0066FF',
                                    fillColor: '#0066FF',
                                    fillOpacity: 0.5,
                                    zIndex: 4
                                });

                                // Navegar com modal de carregamento
                                if (selectedQuadricula) {
                                    navigateToQuadricula(selectedQuadricula.nome);
                                }
                            });

                            quadriculasPolygons.push(obj);
                            obj.setMap(map);
                        }

                        // Adiciona rótulo se houver nome e centro
                        if (f.properties && f.properties.name && centro) {
                            const labelDiv = document.createElement('div');
                            labelDiv.style.fontSize = '12px';
                            labelDiv.style.color = 'rgba(0, 0, 0, 0.5)';
                            labelDiv.style.background = 'rgba(255,255,255,0.3)';
                            labelDiv.style.padding = '4px 8px';
                            labelDiv.style.borderRadius = '4px';
                            labelDiv.style.border = '1px solid rgba(0,0,0,0.3)';
                            labelDiv.style.fontWeight = 'bold';
                            labelDiv.style.textAlign = 'center';
                            labelDiv.style.boxShadow = '0 2px 4px rgba(0,0,0,0.3)';
                            labelDiv.style.whiteSpace = 'nowrap';
                            labelDiv.innerText = f.properties.name;

                            let marker;
                            if (google.maps.marker && google.maps.marker.AdvancedMarkerElement) {
                                marker = new google.maps.marker.AdvancedMarkerElement({
                                    position: centro,
                                    content: labelDiv,
                                    gmpClickable: false,
                                    zIndex: 10
                                });
                                marker.setMap(map);
                            } else {
                                marker = new google.maps.Marker({
                                    position: centro,
                                    map: map,
                                    icon: {
                                        url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"><rect width="1" height="1" fill="transparent"/></svg>'),
                                        scaledSize: new google.maps.Size(1, 1)
                                    },
                                    label: {
                                        text: f.properties.name,
                                        color: '#000',
                                        fontSize: '12px',
                                        fontWeight: 'bold'
                                    },
                                    zIndex: 10
                                });
                            }
                            quadriculasRotulos.push(marker);
                        }
                    });

                    console.log('Quadrículas carregadas do KML:', quadriculasPolygons.length);
                })
                .catch(error => {
                    console.error('Erro ao carregar quadrículas KML:', error);
                });
        }


        // Função para mostrar/esconder botão de navegação
        function showNavigationButton() {
            const btn = document.getElementById('btnIrQuadricula');
            if (selectedQuadricula) {
                btn.style.display = 'inline-block';
                btn.innerHTML = `<i class="fas fa-external-link-alt"></i> Ir para ${selectedQuadricula.nome}`;
            } else {
                btn.style.display = 'none';
            }
        }

        function hideNavigationButton() {
            const btn = document.getElementById('btnIrQuadricula');
            btn.style.display = 'none';
        }

        function irConsulta() {
            window.location.href = `consultas`;
        }

        // Função para controlar visibilidade das quadrículas
        function toggleQuadriculas(show) {
            quadriculasPolygons.forEach(polygon => {
                polygon.setVisible(show);
            });
            quadriculasRotulos.forEach(marker => {
                if (show) {
                    marker.map = map;
                } else {
                    marker.map = null;
                }
            });
        }

        // Função para controlar visibilidade do limite do município
        function toggleLimiteMunicipio(show) {
            if (limitePolyline) {
                limitePolyline.setVisible(show);
            }
        }

        // Função para carregar KML e extrair coordenadas
        function loadLimitePolyline() {
            console.log('Carregando KML para extrair coordenadas...');

            $.ajax({
                url: 'limite_paulinia.kml',
                dataType: 'xml',
                success: function(xml) {
                    console.log('KML carregado com sucesso!');

                    // Extrair coordenadas do KML
                    const coordenadas = [];
                    $(xml).find('coordinates').each(function() {
                        const coordText = $(this).text().trim();
                        const coordPairs = coordText.split(/\s+/);

                        coordPairs.forEach(function(coord) {
                            if (coord.trim()) {
                                const parts = coord.split(',');
                                if (parts.length >= 2) {
                                    const lng = parseFloat(parts[0]);
                                    const lat = parseFloat(parts[1]);
                                    if (!isNaN(lat) && !isNaN(lng)) {
                                        coordenadas.push({
                                            lat: lat,
                                            lng: lng
                                        });
                                    }
                                }
                            }
                        });
                    });

                    console.log('Coordenadas extraídas:', coordenadas.length);

                    if (coordenadas.length > 0) {
                        // Criar Polyline com as coordenadas reais
                        limitePolyline = new google.maps.Polyline({
                            path: coordenadas,
                            geodesic: true,
                            strokeColor: '#FF0000', // Vermelho
                            strokeOpacity: 1.0,
                            strokeWeight: 4, // 4px de espessura
                            clickable: false,
                            zIndex: 1
                        });

                        // Adicionar ao mapa
                        limitePolyline.setMap(map);
                        console.log('Limite do município criado com coordenadas reais do KML');
                    } else {
                        console.log('Nenhuma coordenada encontrada no KML');
                    }
                },
                error: function(xhr, status, error) {
                    console.log('Erro ao carregar KML:', error);
                    console.log('Criando limite com coordenadas padrão...');

                    // Fallback com coordenadas aproximadas
                    const coordenadasLimite = [{
                            lat: -22.780000,
                            lng: -47.200000
                        },
                        {
                            lat: -22.780000,
                            lng: -47.100000
                        },
                        {
                            lat: -22.720000,
                            lng: -47.100000
                        },
                        {
                            lat: -22.720000,
                            lng: -47.200000
                        },
                        {
                            lat: -22.780000,
                            lng: -47.200000
                        }
                    ];

                    limitePolyline = new google.maps.Polyline({
                        path: coordenadasLimite,
                        geodesic: true,
                        strokeColor: '#FF0000',
                        strokeOpacity: 1.0,
                        strokeWeight: 4,
                        clickable: false,
                        zIndex: 1
                    });

                    limitePolyline.setMap(map);
                }
            });
        }

        // Event listeners para os checkboxes e botão de navegação
        $(document).ready(function() {
            $('#checkboxQuadriculas').change(function() {
                toggleQuadriculas(this.checked);
            });

            $('#checkboxLimiteMunicipio').change(function() {
                toggleLimiteMunicipio(this.checked);
            });

            // Event listener para o botão de navegação
            $('#btnIrQuadricula').click(function() {
                if (selectedQuadricula) {
                    navigateToQuadricula(selectedQuadricula.nome);
                }
            });

        });

        async function initMap() {

            // Request needed libraries.
            const {
                Map
            } = await google.maps.importLibrary("maps");

            const {
                geometry
            } = await google.maps.importLibrary("geometry");

            const {
                Draw
            } = await google.maps.importLibrary("drawing");

            const {
                AdvancedMarkerElement
            } = await google.maps.importLibrary("marker");

            // The map, centered at Uluru
            map = new Map(document.getElementById("map"), {

                //configuração do botão de mapa e tipo
                mapTypeControl: true,

                //tipo do mapa
                mapTypeId: 'roadmap',

                //esconder o botão de mapa e tipo
                mapTypeControl: false,

                mapTypeControlOptions: {
                    mapTypeIds: ['roadmap', 'satellite']
                },

                //configuração do botão de zoom
                zoomControl: true,
                zoomControlOptions: {
                    position: google.maps.ControlPosition.RIGHT_BOTTOM
                },

                //configuração do botão de escala                      
                scaleControl: true,

                //configuração do botão de tela cheia                                  
                fullscreenControl: true,
                fullscreenControlOptions: {
                    position: google.maps.ControlPosition.RIGHT_BOTTOM
                },

                //configuração do botão de street view  
                streetViewControl: true,
                streetViewControlOptions: {
                    position: google.maps.ControlPosition.RIGHT_BOTTOM
                },

                zoom: 14,
                center: coordsLocal,
                mapId: "mapImovel",
            });

            // Carregar quadrículas do KML após o mapa ser inicializado
            carregarQuadriculasKML();

            // Carregar Polyline do limite do município
            loadLimitePolyline();
        }

        initMap();

        // =================== Pesquisa dinâmica ===================

        // Opções de pesquisa (ordenadas alfabeticamente)
        const searchOptions = [{
                value: 'area_construida',
                label: 'Área Construída',
                fields: [{
                        name: 'area_construida_min',
                        placeholder: 'Área Mínima (m²)'
                    },
                    {
                        name: 'area_construida_max',
                        placeholder: 'Área Máxima (m²)'
                    }
                ]
            },
            {
                value: 'area_terreno',
                label: 'Área do Terreno',
                fields: [{
                        name: 'area_terreno_min',
                        placeholder: 'Área Mínima (m²)'
                    },
                    {
                        name: 'area_terreno_max',
                        placeholder: 'Área Máxima (m²)'
                    }
                ]
            },
            {
                value: 'bairro',
                label: 'Bairro',
                fields: [{
                    name: 'bairro',
                    placeholder: 'Bairro'
                }]
            },
            {
                value: 'cadastros_nao_desenhados',
                label: 'Cadastros não desenhados',
                fields: [
                    {
                        name: 'loteamento',
                        placeholder: 'Loteamento (opcional)'
                    },
                    {
                        name: 'quarteirao',
                        placeholder: 'Quarteirão (opcional)'
                    }
                ]
            },
            {
                value: 'cat_via',
                label: 'Categoria de Via',
                fields: [{
                    name: 'cat_via',
                    placeholder: 'Categoria de Via'
                }]
            },
            {
                value: 'cnpj',
                label: 'CNPJ',
                fields: [{
                    name: 'cnpj',
                    placeholder: 'CNPJ'
                }]
            },
            {
                value: 'endereco_numero',
                label: 'Endereço e Número',
                fields: [{
                        name: 'endereco',
                        placeholder: 'Endereço'
                    },
                    {
                        name: 'numero',
                        placeholder: 'Número'
                    }
                ]
            },
            {
                value: 'imob_id',
                label: 'ID Imobiliário',
                fields: [{
                    name: 'imob_id',
                    placeholder: 'ID Imobiliário'
                }]
            },
            {
                value: 'inscricao',
                label: 'Inscrição',
                fields: [{
                    name: 'inscricao',
                    placeholder: 'Inscrição'
                }]
            },
            {
                value: 'loteamento',
                label: 'Loteamento',
                fields: [{
                    name: 'loteamento',
                    placeholder: 'Loteamento'
                }]
            },
            {
                value: 'loteamento_quadra',
                label: 'Loteamento e Quadra',
                fields: [{
                        name: 'loteamento',
                        placeholder: 'Loteamento'
                    },
                    {
                        name: 'quadra',
                        placeholder: 'Quadra'
                    }
                ]
            },
            {
                value: 'loteamento_quadra_lote',
                label: 'Loteamento, Quadra e Lote',
                fields: [{
                        name: 'loteamento',
                        placeholder: 'Loteamento'
                    },
                    {
                        name: 'quadra',
                        placeholder: 'Quadra'
                    },
                    {
                        name: 'lote',
                        placeholder: 'Lote'
                    }
                ]
            },
            {
                value: 'quarteirao',
                label: 'Quarteirão',
                fields: [{
                    name: 'quarteirao',
                    placeholder: 'Quarteirão'
                }]
            },
            {
                value: 'quarteirao_quadra',
                label: 'Quarteirão e Quadra',
                fields: [{
                        name: 'quarteirao',
                        placeholder: 'Quarteirão'
                    },
                    {
                        name: 'quadra',
                        placeholder: 'Quadra'
                    }
                ]
            },
            {
                value: 'quarteirao_quadra_lote',
                label: 'Quarteirão, Quadra e Lote',
                fields: [{
                        name: 'quarteirao',
                        placeholder: 'Quarteirão'
                    },
                    {
                        name: 'quadra',
                        placeholder: 'Quadra'
                    },
                    {
                        name: 'lote',
                        placeholder: 'Lote'
                    }
                ]
            },
            {
                value: 'tipo_edificacao',
                label: 'Tipo de Edificação',
                fields: [{
                    name: 'tipo_edificacao',
                    placeholder: 'Tipo de Edificação'
                }]
            },
            {
                value: 'uso_imovel',
                label: 'Uso do Imóvel',
                fields: [{
                    name: 'uso_imovel',
                    placeholder: 'Uso do Imóvel'
                }]
            },
            {
                value: 'zona',
                label: 'Zona',
                fields: [{
                    name: 'zona',
                    placeholder: 'Zona'
                }]
            }
        ];

        // Função para sincronizar largura do botão toggle com searchControls
        function syncToggleButtonWidth() {
            const btnToggleResults = document.getElementById('btnToggleResults');
            const searchControls = document.getElementById('searchControls');
            if (btnToggleResults && searchControls) {
                // Usar requestAnimationFrame para garantir que o layout está atualizado
                requestAnimationFrame(() => {
                    const controlsWidth = searchControls.offsetWidth;
                    btnToggleResults.style.width = controlsWidth + 'px';
                });
            }
        }

        // Variáveis globais de paginação
        let currentPage = 1;
        let currentLimit = 50;
        let totalPages = 1;
        let totalResults = 0;
        let currentSearchPayload = null;

        // Event listener para o seletor de resultados por página
        document.addEventListener('DOMContentLoaded', function() {
            const resultsPerPageSelect = document.getElementById('resultsPerPage');
            if (resultsPerPageSelect) {
                resultsPerPageSelect.addEventListener('change', function() {
                    const newLimit = parseInt(this.value);
                    if (newLimit !== currentLimit) {
                        currentLimit = newLimit;
                        // Recalcular página atual baseado no novo limite
                        // Manter o primeiro item visível na mesma posição relativa
                        const startItem = (currentPage - 1) * currentLimit + 1;
                        const newPage = Math.max(1, Math.ceil(startItem / currentLimit));
                        currentPage = newPage;
                        // Recarregar a página
                        if (currentSearchPayload) {
                            loadPage(currentPage);
                        }
                    }
                });
            }
        });

        // Função para carregar página
        async function loadPage(page) {
            if (!currentSearchPayload) return;

            const payload = {
                ...currentSearchPayload,
                page: page,
                limit: currentLimit
            };

            // Ativar loading
            const btnPesquisar = document.getElementById('btnPesquisar');
            if (btnPesquisar) {
                btnPesquisar.classList.add('loading');
                btnPesquisar.disabled = true;
            }

            try {
                const response = await fetch('painel.php?pesquisar=1', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                const result = await response.json();

                if (result.error) {
                    alert('Erro na pesquisa: ' + result.error);
                } else if (result.success) {
                    currentPage = result.page;
                    totalPages = result.totalPages;
                    totalResults = result.total;
                    displaySearchResults(result.dados, result.total, result.page, result.totalPages, result.desenhos || [], result.desenhosPorImobId || {}, result.marcadoresOrfaos || []);
                }
            } catch (err) {
                alert('Erro ao realizar pesquisa: ' + err.message);
            } finally {
                if (btnPesquisar) {
                    btnPesquisar.classList.remove('loading');
                    btnPesquisar.disabled = false;
                }
            }
        }

        // Inicializar pesquisa quando DOM estiver pronto
        document.addEventListener('DOMContentLoaded', function() {
            const select = document.getElementById('searchType');
            const btnPesquisar = document.getElementById('btnPesquisar');

            // Preencher select
            searchOptions.forEach(opt => {
                const option = document.createElement('option');
                option.value = opt.value;
                option.textContent = opt.label;
                select.appendChild(option);
            });

            // Sincronizar largura do botão toggle inicialmente
            syncToggleButtonWidth();

            // Observar mudanças no tamanho do searchControls
            const resizeObserver = new ResizeObserver(() => {
                syncToggleButtonWidth();
            });
            const searchControls = document.getElementById('searchControls');
            if (searchControls) {
                resizeObserver.observe(searchControls);
            }

            // Troca de tipo de pesquisa - resetar largura para voltar ao dinâmico
            select.addEventListener('change', () => {
                const searchBox = document.getElementById('searchBox');
                if (searchBox) {
                    searchBox.style.width = ''; // Resetar para voltar ao dinâmico
                }
                buildSearchInputs(select.value);
                // Sincronizar largura após mudar os inputs
                setTimeout(syncToggleButtonWidth, 0);
                // Limpar marcadores órfãos quando mudar o tipo de pesquisa
                limparMarcadoresOrfaos();
                // Limpar polígono do quarteirão quando mudar o tipo de pesquisa
                limparQuarteiraoPolygon();
            });

            // Botão pesquisar
            btnPesquisar.addEventListener('click', async () => {
                const payload = collectSearchData();
                if (!payload.type) {
                    alert('Selecione um tipo de pesquisa.');
                    return;
                }

                // Resetar para primeira página
                currentPage = 1;
                currentSearchPayload = payload;

                // Ativar loading
                btnPesquisar.classList.add('loading');
                btnPesquisar.disabled = true;

                // NÃO salvar pesquisa aqui - será salva apenas após pesquisa bem-sucedida no backend
                // O backend verifica o cache e só salva se for nova pesquisa

                // Adicionar paginação ao payload
                payload.page = currentPage;
                payload.limit = currentLimit;

                try {
                    const response = await fetch('painel.php?pesquisar=1', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(payload)
                    });

                    const result = await response.json();

                    if (result.error) {
                        alert('Erro na pesquisa: ' + result.error);
                    } else if (result.success) {
                        currentPage = result.page;
                        totalPages = result.totalPages;
                        totalResults = result.total;
                        displaySearchResults(result.dados, result.total, result.page, result.totalPages, result.desenhos || [], result.desenhosPorImobId || {}, result.marcadoresOrfaos || []);
                    }
                } catch (err) {
                    console.error('Erro ao realizar pesquisa:', err);
                    alert('Erro ao realizar pesquisa.');
                } finally {
                    btnPesquisar.classList.remove('loading');
                    btnPesquisar.disabled = false;
                }
            });

            // Botão para mostrar/ocultar resultados (verificar se já foi inicializado)
            const btnToggleResults = document.getElementById('btnToggleResults');
            if (btnToggleResults && !btnToggleResults.dataset.listenerAdded) {
                btnToggleResults.dataset.listenerAdded = 'true';
                const searchResultsBox = document.getElementById('searchResultsBox');

                btnToggleResults.addEventListener('click', () => {
                    // Verificar o estado atual baseado na classe 'visible'
                    const isVisible = searchResultsBox.classList.contains('visible');

                    if (isVisible) {
                        // Se está visível, ocultar
                        searchResultsBox.classList.remove('visible');
                        btnToggleResults.innerHTML = '<i class="fas fa-chevron-down" id="toggleIcon"></i>';
                    } else {
                        // Se está oculto, mostrar
                        searchResultsBox.classList.add('visible');
                        btnToggleResults.innerHTML = '<i class="fas fa-chevron-up" id="toggleIcon"></i>';
                    }
                    // Garantir que a largura está sincronizada após mudar o texto
                    syncToggleButtonWidth();
                });
            }

            // Carregar última pesquisa
            loadLastSearch();
        });

        function buildSearchInputs(selectedValue, savedValues = {}) {
            const container = document.getElementById('searchInputs');
            container.innerHTML = '';

            if (!selectedValue) return;

            const option = searchOptions.find(o => o.value === selectedValue);
            if (!option) return;

            option.fields.forEach(field => {
                const input = document.createElement('input');
                input.className = 'form-control';
                input.name = field.name;
                input.placeholder = field.placeholder;
                input.style.maxWidth = '220px';
                
                // Definir tipo numérico para campos de área
                if (field.name.includes('area_') || field.name.includes('min') || field.name.includes('max')) {
                    input.type = 'number';
                    input.step = '0.01';
                    input.min = '0';
                }
                
                // Definir tipo numérico para campo quarteirão (quando for cadastros_nao_desenhados)
                if (field.name === 'quarteirao' && selectedValue === 'cadastros_nao_desenhados') {
                    input.type = 'number';
                    input.min = '1';
                    input.step = '1';
                }
                
                if (savedValues[field.name]) input.value = savedValues[field.name];
                container.appendChild(input);
            });
        }

        function collectSearchData() {
            const type = document.getElementById('searchType').value;
            const inputs = document.querySelectorAll('#searchInputs input');
            const values = {};
            inputs.forEach(inp => values[inp.name] = inp.value);
            return {
                type,
                values
            };
        }

        async function saveLastSearch(payload) {
            try {
                await fetch('painel.php?searchApi=1', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });
            } catch (err) {
                console.error('Erro ao salvar pesquisa:', err);
            }
        }

        async function loadLastSearch() {
            try {
                const res = await fetch('painel.php?searchApi=1');
                if (!res.ok) return;
                const data = await res.json();
                if (!data || !data.type) return;

                const select = document.getElementById('searchType');
                select.value = data.type;
                buildSearchInputs(data.type, data.values || {});
            } catch (err) {
                console.error('Erro ao carregar última pesquisa:', err);
            }
        }

        // Função para limpar polígonos dos resultados anteriores
        function limparPoligonosPesquisa() {
            searchResultPolygons.forEach(polygon => {
                if (polygon && polygon.setMap) {
                    polygon.setMap(null);
                }
            });
            searchResultPolygons = [];
            polygonsByImobId = {};
        }

        // Função para limpar polígono do quarteirão pesquisado
        function limparQuarteiraoPolygon() {
            if (quarteiraoPolygon && quarteiraoPolygon.setMap) {
                quarteiraoPolygon.setMap(null);
            }
            quarteiraoPolygon = null;
        }

        // Função para limpar marcadores/polígonos órfãos dos resultados anteriores
        function limparMarcadoresOrfaos() {
            searchResultMarkers.forEach(item => {
                if (item && item.setMap) {
                    item.setMap(null);
                }
            });
            searchResultMarkers = [];
            // Ocultar contador quando não houver marcadores
            const counterDiv = document.getElementById('marcadoresOrfaosCounter');
            if (counterDiv) {
                counterDiv.style.display = 'none';
            }
        }

        // Função para limpar polígonos de quarteirões desenhados (não órfãos, mas dos resultados)
        function limparQuarteiroesDoJSON() {
            // Limpar apenas os polígonos que foram adicionados via desenharQuarteiroesDoJSON
            // Eles estão em searchResultMarkers, então limparMarcadoresOrfaos já faz isso
            // Mas vamos garantir que não conflite com o polígono único do quarteirão pesquisado
        }

        // Variável global para armazenar o JSON de quarteirões
        let quarteiroesJSON = null;

        // Função para normalizar número de quarteirão (41 = 0041 = '41' = '0041')
        function normalizarQuarteirao(quarteirao) {
            if (!quarteirao) return null;
            // Converter para string e remover espaços
            let str = String(quarteirao).trim();
            // Remover zeros à esquerda
            let num = parseInt(str, 10);
            if (isNaN(num)) return str; // Se não for número, retornar string original
            return num.toString();
        }

        // Função para buscar quarteirão no JSON (com busca flexível)
        function buscarQuarteiraoNoJSON(quarteirao, jsonData) {
            if (!quarteirao || !jsonData || !jsonData.features) return null;
            
            const quarteiraoNormalizado = normalizarQuarteirao(quarteirao);
            
            // Buscar no JSON
            for (let feature of jsonData.features) {
                if (feature.properties && feature.properties.name) {
                    const nameNormalizado = normalizarQuarteirao(feature.properties.name);
                    if (nameNormalizado === quarteiraoNormalizado) {
                        return feature;
                    }
                }
            }
            return null;
        }

        // Função para carregar JSON de quarteirões
        async function carregarQuarteiroesJSON() {
            if (quarteiroesJSON) {
                return quarteiroesJSON; // Já carregado
            }
            
            try {
                const response = await fetch('painel_quarteiroes.json');
                if (!response.ok) {
                    console.error('Erro ao carregar JSON de quarteirões:', response.statusText);
                    return null;
                }
                quarteiroesJSON = await response.json();
                return quarteiroesJSON;
            } catch (error) {
                console.error('Erro ao carregar JSON de quarteirões:', error);
                return null;
            }
        }

        // Função para desenhar polígonos de quarteirões órfãos no mapa
        async function desenharMarcadoresOrfaos(quarteiroesOrfaos) {
            if (!map || !quarteiroesOrfaos || quarteiroesOrfaos.length === 0) {
                return;
            }

            // Limpar marcadores anteriores (agora são polígonos)
            limparMarcadoresOrfaos();

            // Carregar JSON de quarteirões
            const jsonData = await carregarQuarteiroesJSON();
            if (!jsonData) {
                console.error('Não foi possível carregar o JSON de quarteirões');
                return;
            }

            const bounds = new google.maps.LatLngBounds();
            let poligonosDesenhados = 0;

            // Para cada quarteirão órfão, buscar no JSON e desenhar
            for (let quarteirao of quarteiroesOrfaos) {
                try {
                    // Buscar quarteirão no JSON
                    const feature = buscarQuarteiraoNoJSON(quarteirao, jsonData);
                    
                    if (!feature || !feature.geometry || !feature.geometry.coordinates) {
                        console.warn('Quarteirão não encontrado no JSON:', quarteirao);
                        continue;
                    }

                    // Obter coordenadas (formato GeoJSON: [lng, lat])
                    const coordinates = feature.geometry.coordinates;
                    if (!Array.isArray(coordinates) || !Array.isArray(coordinates[0])) {
                        console.warn('Coordenadas inválidas para quarteirão:', quarteirao);
                        continue;
                    }

                    // Converter coordenadas GeoJSON [lng, lat] para formato Google Maps {lat, lng}
                    const paths = coordinates[0].map(coord => {
                        if (Array.isArray(coord) && coord.length >= 2) {
                            return {
                                lat: parseFloat(coord[1]), // GeoJSON usa [lng, lat]
                                lng: parseFloat(coord[0])
                            };
                        }
                        return null;
                    }).filter(coord => coord !== null && !isNaN(coord.lat) && !isNaN(coord.lng));

                    if (paths.length < 3) {
                        console.warn('Polígono com menos de 3 pontos válidos para quarteirão:', quarteirao);
                        continue;
                    }

                    // Obter cor do JSON (properties.fill_color)
                    let cor = '#FF0000'; // Padrão: vermelho
                    if (feature.properties && feature.properties.fill_color) {
                        cor = String(feature.properties.fill_color).trim();
                        // Garantir que começa com #
                        if (!cor.startsWith('#')) {
                            cor = '#' + cor;
                        }
                    }

                    // Criar polígono
                    const polygon = new google.maps.Polygon({
                        paths: paths,
                        strokeColor: cor,
                        strokeOpacity: 1,
                        strokeWeight: 3,
                        fillColor: cor,
                        fillOpacity: 0.35,
                        map: map,
                        zIndex: 1001, // Z-index alto para ficar acima de outros elementos
                        clickable: false
                    });

                    // Adicionar tooltip ao polígono
                    const quarteiraoNome = feature.properties && feature.properties.name ? feature.properties.name : quarteirao;
                    polygon.quarteirao = quarteiraoNome;

                    // Adicionar ao array (usando searchResultMarkers para manter compatibilidade com limparMarcadoresOrfaos)
                    searchResultMarkers.push(polygon);

                    // Adicionar ao bounds para ajustar o zoom
                    paths.forEach(path => bounds.extend(path));
                    poligonosDesenhados++;

                } catch (error) {
                    console.error('Erro ao desenhar polígono do quarteirão ' + quarteirao + ':', error);
                }
            }

            // Ajustar zoom do mapa para mostrar todos os polígonos (se houver outros elementos, não forçar zoom)
            if (poligonosDesenhados > 0 && !bounds.isEmpty() && searchResultPolygons.length === 0) {
                // Só ajustar zoom se não houver polígonos de pesquisa (para não conflitar)
                map.fitBounds(bounds);
                const padding = 50;
                map.fitBounds(bounds, padding);
            }

            // Atualizar contador de marcadores órfãos (agora são polígonos)
            const counterDiv = document.getElementById('marcadoresOrfaosCounter');
            const countSpan = document.getElementById('marcadoresOrfaosCount');
            if (counterDiv && countSpan) {
                if (poligonosDesenhados > 0) {
                    countSpan.textContent = poligonosDesenhados.toLocaleString('pt-BR');
                    counterDiv.style.display = 'flex';
                } else {
                    counterDiv.style.display = 'none';
                }
            }
        }

        // Função para desenhar polígonos de múltiplos quarteirões (do JSON)
        async function desenharQuarteiroesDoJSON(quarteiroes) {
            if (!map || !quarteiroes || quarteiroes.length === 0) {
                return;
            }

            // Carregar JSON de quarteirões
            const jsonData = await carregarQuarteiroesJSON();
            if (!jsonData) {
                console.error('Não foi possível carregar o JSON de quarteirões');
                return;
            }

            const bounds = new google.maps.LatLngBounds();
            let poligonosDesenhados = 0;

            // Para cada quarteirão, buscar no JSON e desenhar
            for (let quarteirao of quarteiroes) {
                try {
                    // Buscar quarteirão no JSON
                    const feature = buscarQuarteiraoNoJSON(quarteirao, jsonData);
                    
                    if (!feature || !feature.geometry || !feature.geometry.coordinates) {
                        console.warn('Quarteirão não encontrado no JSON:', quarteirao);
                        continue;
                    }

                    // Obter coordenadas (formato GeoJSON: [lng, lat])
                    const coordinates = feature.geometry.coordinates;
                    if (!Array.isArray(coordinates) || !Array.isArray(coordinates[0])) {
                        console.warn('Coordenadas inválidas para quarteirão:', quarteirao);
                        continue;
                    }

                    // Converter coordenadas GeoJSON [lng, lat] para formato Google Maps {lat, lng}
                    const paths = coordinates[0].map(coord => {
                        if (Array.isArray(coord) && coord.length >= 2) {
                            return {
                                lat: parseFloat(coord[1]), // GeoJSON usa [lng, lat]
                                lng: parseFloat(coord[0])
                            };
                        }
                        return null;
                    }).filter(coord => coord !== null && !isNaN(coord.lat) && !isNaN(coord.lng));

                    if (paths.length < 3) {
                        console.warn('Polígono com menos de 3 pontos válidos para quarteirão:', quarteirao);
                        continue;
                    }

                    // Obter cor do JSON (properties.fill_color)
                    let cor = '#4285F4'; // Padrão: azul do Google Maps
                    if (feature.properties && feature.properties.fill_color) {
                        cor = String(feature.properties.fill_color).trim();
                        // Garantir que começa com #
                        if (!cor.startsWith('#')) {
                            cor = '#' + cor;
                        }
                    }

                    // Criar polígono do quarteirão
                    const polygon = new google.maps.Polygon({
                        paths: paths,
                        strokeColor: cor,
                        strokeOpacity: 0.8,
                        strokeWeight: 3,
                        fillColor: cor,
                        fillOpacity: 0.2,
                        map: map,
                        zIndex: 500, // Z-index médio para ficar visível mas não sobrepor outros elementos
                        clickable: false
                    });

                    // Adicionar tooltip ao polígono
                    const quarteiraoNome = feature.properties && feature.properties.name ? feature.properties.name : quarteirao;
                    polygon.quarteirao = quarteiraoNome;

                    // Adicionar ao array de marcadores (para manter compatibilidade)
                    searchResultMarkers.push(polygon);

                    // Adicionar ao bounds para ajustar o zoom
                    paths.forEach(path => bounds.extend(path));
                    poligonosDesenhados++;

                } catch (error) {
                    console.error('Erro ao desenhar polígono do quarteirão ' + quarteirao + ':', error);
                }
            }

            // Ajustar zoom do mapa para mostrar todos os polígonos (se houver outros elementos, não forçar zoom)
            if (poligonosDesenhados > 0 && !bounds.isEmpty() && searchResultPolygons.length === 0) {
                // Só ajustar zoom se não houver polígonos de pesquisa (para não conflitar)
                map.fitBounds(bounds);
                const padding = 50;
                map.fitBounds(bounds, padding);
            }
        }

        // Função para desenhar polígono do quarteirão pesquisado (do JSON)
        async function desenharQuarteiraoPesquisado(quarteirao) {
            if (!map || !quarteirao) {
                return;
            }

            // Limpar polígono anterior
            limparQuarteiraoPolygon();

            // Carregar JSON de quarteirões
            const jsonData = await carregarQuarteiroesJSON();
            if (!jsonData) {
                console.error('Não foi possível carregar o JSON de quarteirões');
                return;
            }

            // Buscar quarteirão no JSON
            const feature = buscarQuarteiraoNoJSON(quarteirao, jsonData);
            
            if (!feature || !feature.geometry || !feature.geometry.coordinates) {
                console.warn('Quarteirão não encontrado no JSON:', quarteirao);
                return;
            }

            try {
                // Obter coordenadas (formato GeoJSON: [lng, lat])
                const coordinates = feature.geometry.coordinates;
                if (!Array.isArray(coordinates) || !Array.isArray(coordinates[0])) {
                    console.warn('Coordenadas inválidas para quarteirão:', quarteirao);
                    return;
                }

                // Converter coordenadas GeoJSON [lng, lat] para formato Google Maps {lat, lng}
                const paths = coordinates[0].map(coord => {
                    if (Array.isArray(coord) && coord.length >= 2) {
                        return {
                            lat: parseFloat(coord[1]), // GeoJSON usa [lng, lat]
                            lng: parseFloat(coord[0])
                        };
                    }
                    return null;
                }).filter(coord => coord !== null && !isNaN(coord.lat) && !isNaN(coord.lng));

                if (paths.length < 3) {
                    console.warn('Polígono com menos de 3 pontos válidos para quarteirão:', quarteirao);
                    return;
                }

                // Obter cor do JSON (properties.fill_color)
                let cor = '#4285F4'; // Padrão: azul do Google Maps
                if (feature.properties && feature.properties.fill_color) {
                    cor = String(feature.properties.fill_color).trim();
                    // Garantir que começa com #
                    if (!cor.startsWith('#')) {
                        cor = '#' + cor;
                    }
                }

                // Criar polígono do quarteirão
                quarteiraoPolygon = new google.maps.Polygon({
                    paths: paths,
                    strokeColor: cor,
                    strokeOpacity: 0.8,
                    strokeWeight: 4,
                    fillColor: cor,
                    fillOpacity: 0.2,
                    map: map,
                    zIndex: 500, // Z-index médio para ficar visível mas não sobrepor outros elementos
                    clickable: false
                });

                // Ajustar zoom para mostrar o quarteirão
                const bounds = new google.maps.LatLngBounds();
                paths.forEach(path => bounds.extend(path));
                if (!bounds.isEmpty()) {
                    map.fitBounds(bounds, { padding: 50 });
                }

            } catch (error) {
                console.error('Erro ao desenhar polígono do quarteirão ' + quarteirao + ':', error);
            }
        }

        // Função para desenhar polígonos no mapa
        function desenharPoligonosPesquisa(desenhosPorImobId) {
            if (!map || !desenhosPorImobId) {
                return;
            }

            // Limpar polígonos anteriores
            limparPoligonosPesquisa();

            const bounds = new google.maps.LatLngBounds();

            // Iterar sobre cada imob_id e seus desenhos
            Object.keys(desenhosPorImobId).forEach(imobId => {
                const desenhos = desenhosPorImobId[imobId];
                
                if (!Array.isArray(desenhos) || desenhos.length === 0) {
                    return;
                }

                // Inicializar array de polígonos para este imob_id
                if (!polygonsByImobId[imobId]) {
                    polygonsByImobId[imobId] = [];
                }

                desenhos.forEach(desenho => {
                    try {
                        let coordenadas = [];
                        
                        // Parse das coordenadas (pode ser JSON string ou já ser objeto)
                        if (typeof desenho.coordenadas === 'string') {
                            coordenadas = JSON.parse(desenho.coordenadas);
                        } else {
                            coordenadas = desenho.coordenadas;
                        }

                        if (!Array.isArray(coordenadas) || coordenadas.length < 3) {
                            console.warn('Coordenadas inválidas para desenho ID:', desenho.id);
                            return;
                        }

                        // Converter coordenadas para formato do Google Maps
                        const paths = coordenadas.map(coord => {
                            if (typeof coord === 'object' && coord.lat && coord.lng) {
                                return { lat: parseFloat(coord.lat), lng: parseFloat(coord.lng) };
                            } else if (Array.isArray(coord) && coord.length >= 2) {
                                return { lat: parseFloat(coord[0]), lng: parseFloat(coord[1]) };
                            }
                            return null;
                        }).filter(coord => coord !== null);

                        if (paths.length < 3) {
                            console.warn('Polígono com menos de 3 pontos válidos, ID:', desenho.id);
                            return;
                        }

                        // Usar cor_usuario, depois cor, depois preto como padrão
                        let cor = '#000000'; // Padrão: preto
                        
                        // Verificar se cor_usuario é válida (não vazia, não null)
                        if (desenho.cor_usuario && 
                            desenho.cor_usuario !== null && 
                            desenho.cor_usuario !== 'null' && 
                            String(desenho.cor_usuario).trim() !== '') {
                            cor = String(desenho.cor_usuario).trim();
                        }
                        // Se cor_usuario não for válida, tentar usar cor
                        else if (desenho.cor && 
                                 desenho.cor !== null && 
                                 desenho.cor !== 'null' && 
                                 String(desenho.cor).trim() !== '') {
                            cor = String(desenho.cor).trim();
                        }

                        // Criar polígono
                        const polygon = new google.maps.Polygon({
                            paths: paths,
                            strokeColor: cor,
                            strokeOpacity: 1,
                            strokeWeight: 3,
                            fillColor: cor,
                            fillOpacity: 0.35,
                            map: map,
                            zIndex: 1000, // Z-index alto para ficar acima de outros elementos
                            clickable: false // Polígonos não clicáveis
                        });

                        // Adicionar ao array geral
                        searchResultPolygons.push(polygon);
                        
                        // Adicionar ao array específico do imob_id
                        polygonsByImobId[imobId].push(polygon);

                        // Adicionar ao bounds para ajustar o zoom
                        paths.forEach(path => bounds.extend(path));

                    } catch (error) {
                        console.error('Erro ao desenhar polígono ID ' + desenho.id + ':', error);
                    }
                });
            });

            // Ajustar zoom do mapa para mostrar todos os polígonos
            if (searchResultPolygons.length > 0 && !bounds.isEmpty()) {
                map.fitBounds(bounds);
                // Adicionar um pouco de padding
                const padding = 50;
                map.fitBounds(bounds, padding);
            }
        }

        // Função para controlar visibilidade dos polígonos por imob_id
        function togglePolygonVisibility(imobId, visible) {
            if (polygonsByImobId[imobId]) {
                polygonsByImobId[imobId].forEach(polygon => {
                    if (polygon && polygon.setMap) {
                        polygon.setMap(visible ? map : null);
                    }
                });
            }
        }

        // Função para fazer pan/zoom no desenho de um imob_id específico
        function zoomToDesenho(imobId) {
            if (!map || !imobId) {
                return false;
            }

            // Verificar se existem polígonos para este imob_id
            if (!polygonsByImobId[imobId] || polygonsByImobId[imobId].length === 0) {
                return false;
            }

            // Criar bounds para todos os polígonos deste imob_id
            const bounds = new google.maps.LatLngBounds();
            let hasValidBounds = false;

            polygonsByImobId[imobId].forEach(polygon => {
                if (polygon && polygon.getPaths) {
                    const paths = polygon.getPaths();
                    paths.forEach(path => {
                        path.getArray().forEach(point => {
                            bounds.extend(point);
                            hasValidBounds = true;
                        });
                    });
                }
            });

            // Se há bounds válidos, fazer zoom
            if (hasValidBounds && !bounds.isEmpty()) {
                map.fitBounds(bounds, {
                    padding: 50 // Padding em pixels
                });
                return true;
            }

            return false;
        }

        // Função para exibir resultados da pesquisa
        function displaySearchResults(dados, total, page = 1, totalPages = 1, desenhos = [], desenhosPorImobId = {}, marcadoresOrfaos = []) {
            const resultsTableHead = document.getElementById('resultsTableHead');
            const resultsTableBody = document.getElementById('resultsTableBody');
            const resultsCount = document.getElementById('resultsCount');
            const desenhosEncontrados = document.getElementById('desenhosEncontrados');
            const desenhosNaoEncontrados = document.getElementById('desenhosNaoEncontrados');
            const searchResultsBox = document.getElementById('searchResultsBox');
            const searchBox = document.getElementById('searchBox');
            const searchControls = document.getElementById('searchControls');
            const btnToggleResults = document.getElementById('btnToggleResults');
            const searchResultsPaginationBottom = document.getElementById('searchResultsPaginationBottom');
            const paginationInfoBottom = document.getElementById('paginationInfoBottom');
            const paginationControlsBottom = document.getElementById('paginationControlsBottom');
            const tableContainer = document.getElementById('tableContainer');

            // Definir largura do botão toggle ANTES de mostrá-lo para evitar mudança no tamanho do searchBox
            if (btnToggleResults && searchControls) {
                // Primeiro sincronizar a largura enquanto o botão está oculto
                syncToggleButtonWidth();
                // Depois mostrar o botão (não afetará o tamanho do searchBox pois a largura já está definida)
                setTimeout(() => {
                    btnToggleResults.style.display = 'block';
                }, 0);
            }

            if (!dados || dados.length === 0) {
                // Criar cabeçalho da tabela vazia
                if (resultsTableHead) {
                    resultsTableHead.innerHTML = '<tr><th colspan="100%" class="text-center">Nenhum resultado encontrado</th></tr>';
                }
                // Manter corpo vazio
                if (resultsTableBody) {
                    resultsTableBody.innerHTML = '';
                }
                // Atualizar contadores para zero com textos descritivos
                if (resultsCount) {
                    resultsCount.textContent = '0 registros';
                }
                if (desenhosEncontrados) {
                    desenhosEncontrados.textContent = '0 com desenho';
                }
                if (desenhosNaoEncontrados) {
                    desenhosNaoEncontrados.textContent = '0 sem desenho';
                }
                // Ocultar paginação se não houver resultados
                if (searchResultsPaginationBottom) {
                    searchResultsPaginationBottom.style.display = 'none';
                }
                // MOSTRAR a tabela mesmo vazia
                if (searchResultsBox) {
                    searchResultsBox.classList.add('visible');
                }
                return;
            }

            // Definir colunas específicas na ordem desejada
            const columnsOrder = [
                'inscricao',
                'imob_id',
                'logradouro',
                'numero',
                'bairro',
                'nome_loteamento',
                'cara_quarteirao',
                'quadra',
                'lote',
                'total_construido',
                'nome_pessoa',
                'cnpj',
                'area_terreno',
                'tipo_edificacao',
                'tipo_utilizacao',
                'zona',
                'cat_via',
                'multiplo',
                'uso_imovel'
            ];

            // Mapeamento de nomes de colunas para nomes bonitos
            const columnNames = {
                'inscricao': 'Inscrição',
                'imob_id': 'Imob ID',
                'logradouro': 'Logradouro',
                'numero': 'Número',
                'bairro': 'Bairro',
                'nome_loteamento': 'Loteamento',
                'cara_quarteirao': 'Quarteirão',
                'quadra': 'Quadra',
                'lote': 'Lote',
                'total_construido': 'Área Construída',
                'nome_pessoa': 'Nome',
                'cnpj': 'CNPJ',
                'area_terreno': 'Área Terreno',
                'tipo_edificacao': 'Tipo Edificação',
                'tipo_utilizacao': 'Tipo Utilização',
                'zona': 'Zona',
                'cat_via': 'Cat. Via',
                'multiplo': 'Cadastros',
                'uso_imovel': 'Uso Imóvel'
            };

            // Filtrar apenas as colunas que existem nos dados
            const columnArray = columnsOrder.filter(col => {
                return dados.some(row => row.hasOwnProperty(col));
            });

            // Criar cabeçalho com checkbox na primeira coluna
            let headerHTML = '<tr>';
            headerHTML += '<th style="width: 50px; text-align: center;"><input type="checkbox" id="selectAllCheckbox" title="Selecionar todos"></th>';
            columnArray.forEach(col => {
                const headerName = columnNames[col] || col;
                headerHTML += `<th>${headerName}</th>`;
            });
            headerHTML += '</tr>';
            resultsTableHead.innerHTML = headerHTML;

            // Criar corpo com checkbox na primeira coluna
            let bodyHTML = '';
            dados.forEach((row, index) => {
                const imobId = row.imob_id || '';
                const temDesenho = imobId && desenhosPorImobId[imobId] && desenhosPorImobId[imobId].length > 0;
                
                // Adicionar classe 'no-drawing' se não tiver desenho
                const rowClass = temDesenho ? 'row-clickable' : 'no-drawing row-clickable';
                // Adicionar atributo data-imob-id para facilitar o clique
                bodyHTML += `<tr class="${rowClass}" data-imob-id="${imobId}">`;
                
                // Primeira coluna: checkbox (desabilitado se não tiver desenho)
                if (temDesenho) {
                    bodyHTML += `<td style="text-align: center;"><input type="checkbox" class="row-checkbox" data-index="${index}" data-imob-id="${imobId}" checked></td>`;
                } else {
                    bodyHTML += `<td style="text-align: center;"><input type="checkbox" class="row-checkbox" data-index="${index}" data-imob-id="${imobId}" disabled title="Desenho não foi encontrado"></td>`;
                }
                // Demais colunas
                columnArray.forEach(col => {
                    const value = row[col] !== null && row[col] !== undefined ? row[col] : '';
                    bodyHTML += `<td title="${value}">${value}</td>`;
                });
                bodyHTML += '</tr>';
            });
            resultsTableBody.innerHTML = bodyHTML;

            // Adicionar funcionalidade ao checkbox "Selecionar todos"
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    const rowCheckboxes = document.querySelectorAll('.row-checkbox:not(:disabled)');
                    rowCheckboxes.forEach(checkbox => {
                        checkbox.checked = this.checked;
                        const imobId = checkbox.getAttribute('data-imob-id');
                        if (imobId) {
                            togglePolygonVisibility(imobId, this.checked);
                        }
                    });
                });
            }

            // Adicionar event listeners aos checkboxes individuais
            document.querySelectorAll('.row-checkbox:not(:disabled)').forEach(checkbox => {
                checkbox.addEventListener('change', function(e) {
                    e.stopPropagation(); // Evitar que o clique no checkbox também dispare o clique na linha
                    const imobId = this.getAttribute('data-imob-id');
                    if (imobId) {
                        togglePolygonVisibility(imobId, this.checked);
                    }
                });
            });

            // Adicionar event listeners aos checkboxes desabilitados (para mostrar alerta)
            document.querySelectorAll('.row-checkbox:disabled').forEach(checkbox => {
                checkbox.addEventListener('click', function(e) {
                    e.stopPropagation(); // Evitar que o clique no checkbox também dispare o clique na linha
                    alert('Desenho não foi encontrado');
                });
            });

            // Adicionar event listener de clique nas linhas da tabela
            document.querySelectorAll('#resultsTableBody tr').forEach(row => {
                row.addEventListener('click', function(e) {
                    // Não fazer nada se o clique foi no checkbox
                    if (e.target.type === 'checkbox') {
                        return;
                    }

                    const imobId = this.getAttribute('data-imob-id');
                    if (!imobId) {
                        return;
                    }

                    // Verificar se a linha tem desenho (não tem classe 'no-drawing' ou tem polígonos)
                    const temDesenho = polygonsByImobId[imobId] && polygonsByImobId[imobId].length > 0;
                    
                    if (temDesenho) {
                        // Fazer zoom no desenho
                        zoomToDesenho(imobId);
                    } else {
                        // Mostrar alerta se não tiver desenho
                        alert('Desenho não foi encontrado');
                    }
                });
            });

            // Calcular quantidades de desenhos encontrados e não encontrados
            // desenhosPorImobId contém TODOS os desenhos de TODOS os resultados (não apenas da página atual)
            // Então podemos contar quantos imob_ids únicos têm desenho
            let qtdEncontrados = 0;
            if (desenhosPorImobId && typeof desenhosPorImobId === 'object') {
                qtdEncontrados = Object.keys(desenhosPorImobId).filter(imobId => {
                    return desenhosPorImobId[imobId] && desenhosPorImobId[imobId].length > 0;
                }).length;
            }
            
            // Total de não encontrados = total de resultados - total de encontrados
            let qtdNaoEncontrados = Math.max(0, total - qtdEncontrados);

            // Atualizar contadores com textos descritivos
            if (resultsCount) {
                resultsCount.textContent = total.toLocaleString('pt-BR') + ' registros';
            }
            if (desenhosEncontrados) {
                desenhosEncontrados.textContent = qtdEncontrados.toLocaleString('pt-BR') + ' com desenho';
            }
            if (desenhosNaoEncontrados) {
                desenhosNaoEncontrados.textContent = qtdNaoEncontrados.toLocaleString('pt-BR') + ' sem desenho';
            }

            // Atualizar informações de paginação (apenas na parte inferior)
            const start = (page - 1) * currentLimit + 1;
            const end = Math.min(page * currentLimit, total);
            const paginationText = `Mostrando ${start.toLocaleString('pt-BR')} a ${end.toLocaleString('pt-BR')} de ${total.toLocaleString('pt-BR')} resultados`;
            
            // Criar controles de paginação
            let paginationHTML = '';
            
            // Botão Anterior
            paginationHTML += `<li class="page-item ${page === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${page - 1}" ${page === 1 ? 'tabindex="-1" aria-disabled="true"' : ''}>
                    <i class="fas fa-chevron-left"></i>
                </a>
            </li>`;
            
            // Números de página
            const maxVisiblePages = 7;
            let startPage = Math.max(1, page - Math.floor(maxVisiblePages / 2));
            let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);
            
            if (endPage - startPage < maxVisiblePages - 1) {
                startPage = Math.max(1, endPage - maxVisiblePages + 1);
            }
            
            if (startPage > 1) {
                paginationHTML += `<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>`;
                if (startPage > 2) {
                    paginationHTML += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
                }
            }
            
            for (let i = startPage; i <= endPage; i++) {
                paginationHTML += `<li class="page-item ${i === page ? 'active' : ''}">
                    <a class="page-link" href="#" data-page="${i}">${i}</a>
                </li>`;
            }
            
            if (endPage < totalPages) {
                if (endPage < totalPages - 1) {
                    paginationHTML += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
                }
                paginationHTML += `<li class="page-item"><a class="page-link" href="#" data-page="${totalPages}">${totalPages}</a></li>`;
            }
            
            // Botão Próximo
            paginationHTML += `<li class="page-item ${page === totalPages ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${page + 1}" ${page === totalPages ? 'tabindex="-1" aria-disabled="true"' : ''}>
                    <i class="fas fa-chevron-right"></i>
                </a>
            </li>`;

            // Atualizar paginação inferior
            if (searchResultsPaginationBottom && paginationInfoBottom && paginationControlsBottom) {
                searchResultsPaginationBottom.style.display = 'flex';
                paginationInfoBottom.textContent = paginationText;
                
                // Atualizar o valor do select de resultados por página
                const resultsPerPageSelect = document.getElementById('resultsPerPage');
                if (resultsPerPageSelect) {
                    resultsPerPageSelect.value = currentLimit;
                }
                
                paginationControlsBottom.innerHTML = paginationHTML;
                
                // Adicionar event listeners para os botões de paginação
                paginationControlsBottom.querySelectorAll('a[data-page]').forEach(link => {
                    link.addEventListener('click', (e) => {
                        e.preventDefault();
                        const targetPage = parseInt(link.getAttribute('data-page'));
                        if (targetPage >= 1 && targetPage <= totalPages && targetPage !== page) {
                            loadPage(targetPage);
                            // Scroll para o topo da tabela
                            const searchResultsBody = document.getElementById('searchResultsBody');
                            if (searchResultsBody) {
                                searchResultsBody.scrollTop = 0;
                            }
                        }
                    });
                });
            }

            // Scroll horizontal agora é feito diretamente no table-responsive

            // Mostrar automaticamente quando houver resultados
            if (searchResultsBox) {
                searchResultsBox.classList.add('visible');
                // Atualizar estado do botão toggle
                const btnToggleResults = document.getElementById('btnToggleResults');
                if (btnToggleResults) {
                    btnToggleResults.innerHTML = '<i class="fas fa-chevron-up" id="toggleIcon"></i>';
                    btnToggleResults.style.display = 'block';
                    syncToggleButtonWidth();
                }
            }

            // Desenhar polígonos no mapa se houver desenhos
            if (desenhosPorImobId && Object.keys(desenhosPorImobId).length > 0) {
                desenharPoligonosPesquisa(desenhosPorImobId);
            } else {
                // Se não houver desenhos, limpar polígonos anteriores
                limparPoligonosPesquisa();
            }

            // Desenhar marcadores órfãos se houver (apenas para pesquisa cadastros_nao_desenhados)
            if (marcadoresOrfaos && Array.isArray(marcadoresOrfaos) && marcadoresOrfaos.length > 0) {
                desenharMarcadoresOrfaos(marcadoresOrfaos);
            } else {
                // Se não houver marcadores órfãos, limpar marcadores anteriores
                limparMarcadoresOrfaos();
            }

            // Desenhar polígono do quarteirão pesquisado (apenas para pesquisa de quarteirão)
            if (currentSearchPayload && currentSearchPayload.type === 'quarteirao' && 
                currentSearchPayload.values && currentSearchPayload.values.quarteirao) {
                desenharQuarteiraoPesquisado(currentSearchPayload.values.quarteirao);
            } else {
                // Se não for pesquisa de quarteirão, limpar polígono do quarteirão
                limparQuarteiraoPolygon();
            }

            // Desenhar polígonos de quarteirões para pesquisa "cadastros_nao_desenhados"
            if (currentSearchPayload && currentSearchPayload.type === 'cadastros_nao_desenhados') {
                const values = currentSearchPayload.values || {};
                
                // Se tiver quarteirão preenchido, desenhar apenas esse quarteirão (prioridade)
                if (values.quarteirao && values.quarteirao !== '' && values.quarteirao !== null) {
                    const quarteiraoInt = parseInt(values.quarteirao);
                    if (quarteiraoInt > 0) {
                        // Limpar polígonos múltiplos antes de desenhar o único
                        limparMarcadoresOrfaos();
                        desenharQuarteiraoPesquisado(quarteiraoInt);
                    }
                } 
                // Se tiver loteamento preenchido (sem quarteirão), desenhar quarteirões dos resultados
                else if (values.loteamento && values.loteamento !== '' && values.loteamento !== null && dados && dados.length > 0) {
                    // Limpar polígono único antes de desenhar múltiplos
                    limparQuarteiraoPolygon();
                    // Extrair quarteirões únicos dos resultados
                    const quarteiroesUnicos = [...new Set(dados
                        .map(row => row.cara_quarteirao)
                        .filter(q => q !== null && q !== undefined && q !== '' && q !== '0')
                        .map(q => {
                            // Converter para número, tratando strings numéricas
                            const num = parseInt(q);
                            return isNaN(num) ? null : num;
                        })
                        .filter(q => q !== null && q > 0)
                    )];
                    
                    if (quarteiroesUnicos.length > 0) {
                        desenharQuarteiroesDoJSON(quarteiroesUnicos);
                    }
                } else {
                    // Se não tiver nenhum campo preenchido, limpar tudo
                    limparQuarteiraoPolygon();
                    limparMarcadoresOrfaos();
                }
            }
        }
    </script>

</body>

</head>