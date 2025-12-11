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

        // Construir query SQL baseada no tipo de pesquisa
        $sql = "SELECT * FROM cadastro WHERE 1=1";
        $params = [];

        switch ($type) {
            case 'endereco_numero':
                if (!empty($values['endereco'])) {
                    $sql .= " AND logradouro LIKE :endereco";
                    $params[':endereco'] = '%' . $values['endereco'] . '%';
                }
                if (!empty($values['numero'])) {
                    $sql .= " AND numero = :numero";
                    $params[':numero'] = $values['numero'];
                }
                break;

            case 'quarteirao':
                if (!empty($values['quarteirao'])) {
                    $sql .= " AND cara_quarteirao = :quarteirao";
                    $params[':quarteirao'] = $values['quarteirao'];
                }
                break;

            case 'quarteirao_quadra':
                if (!empty($values['quarteirao'])) {
                    $sql .= " AND cara_quarteirao = :quarteirao";
                    $params[':quarteirao'] = $values['quarteirao'];
                }
                if (!empty($values['quadra'])) {
                    $sql .= " AND quadra = :quadra";
                    $params[':quadra'] = $values['quadra'];
                }
                break;

            case 'quarteirao_quadra_lote':
                if (!empty($values['quarteirao'])) {
                    $sql .= " AND cara_quarteirao = :quarteirao";
                    $params[':quarteirao'] = $values['quarteirao'];
                }
                if (!empty($values['quadra'])) {
                    $sql .= " AND quadra = :quadra";
                    $params[':quadra'] = $values['quadra'];
                }
                if (!empty($values['lote'])) {
                    $sql .= " AND lote = :lote";
                    $params[':lote'] = $values['lote'];
                }
                break;

            case 'loteamento':
                if (!empty($values['loteamento'])) {
                    $sql .= " AND nome_loteamento LIKE :loteamento";
                    $params[':loteamento'] = '%' . $values['loteamento'] . '%';
                }
                break;

            case 'loteamento_quadra':
                if (!empty($values['loteamento'])) {
                    $sql .= " AND nome_loteamento LIKE :loteamento";
                    $params[':loteamento'] = '%' . $values['loteamento'] . '%';
                }
                if (!empty($values['quadra'])) {
                    $sql .= " AND quadra = :quadra";
                    $params[':quadra'] = $values['quadra'];
                }
                break;

            case 'loteamento_quadra_lote':
                if (!empty($values['loteamento'])) {
                    $sql .= " AND nome_loteamento LIKE :loteamento";
                    $params[':loteamento'] = '%' . $values['loteamento'] . '%';
                }
                if (!empty($values['quadra'])) {
                    $sql .= " AND quadra = :quadra";
                    $params[':quadra'] = $values['quadra'];
                }
                if (!empty($values['lote'])) {
                    $sql .= " AND lote = :lote";
                    $params[':lote'] = $values['lote'];
                }
                break;

            case 'cnpj':
                if (!empty($values['cnpj'])) {
                    $sql .= " AND cnpj = :cnpj";
                    $params[':cnpj'] = $values['cnpj'];
                }
                break;

            case 'uso_imovel':
                if (!empty($values['uso_imovel'])) {
                    $sql .= " AND uso_imovel LIKE :uso_imovel";
                    $params[':uso_imovel'] = '%' . $values['uso_imovel'] . '%';
                }
                break;

            case 'bairro':
                if (!empty($values['bairro'])) {
                    $sql .= " AND bairro LIKE :bairro";
                    $params[':bairro'] = '%' . $values['bairro'] . '%';
                }
                break;

            case 'inscricao':
                if (!empty($values['inscricao'])) {
                    $sql .= " AND inscricao = :inscricao";
                    $params[':inscricao'] = $values['inscricao'];
                }
                break;

            case 'imob_id':
                if (!empty($values['imob_id'])) {
                    $sql .= " AND imob_id = :imob_id";
                    $params[':imob_id'] = $values['imob_id'];
                }
                break;

            case 'zona':
                if (!empty($values['zona'])) {
                    $sql .= " AND zona = :zona";
                    $params[':zona'] = $values['zona'];
                }
                break;

            case 'cat_via':
                if (!empty($values['cat_via'])) {
                    $sql .= " AND cat_via LIKE :cat_via";
                    $params[':cat_via'] = '%' . $values['cat_via'] . '%';
                }
                break;

            case 'tipo_edificacao':
                if (!empty($values['tipo_edificacao'])) {
                    $sql .= " AND tipo_edificacao LIKE :tipo_edificacao";
                    $params[':tipo_edificacao'] = '%' . $values['tipo_edificacao'] . '%';
                }
                break;

            case 'area_construida':
                if (!empty($values['area_construida_min'])) {
                    $sql .= " AND total_construido >= :area_construida_min";
                    $params[':area_construida_min'] = floatval($values['area_construida_min']);
                }
                if (!empty($values['area_construida_max'])) {
                    $sql .= " AND total_construido <= :area_construida_max";
                    $params[':area_construida_max'] = floatval($values['area_construida_max']);
                }
                break;

            case 'area_terreno':
                if (!empty($values['area_terreno_min'])) {
                    $sql .= " AND area_terreno >= :area_terreno_min";
                    $params[':area_terreno_min'] = floatval($values['area_terreno_min']);
                }
                if (!empty($values['area_terreno_max'])) {
                    $sql .= " AND area_terreno <= :area_terreno_max";
                    $params[':area_terreno_max'] = floatval($values['area_terreno_max']);
                }
                break;

            default:
                echo json_encode(['error' => 'Tipo de pesquisa não implementado']);
                exit;
        }

        // Paginação
        $page = isset($input['page']) && is_numeric($input['page']) ? max(1, intval($input['page'])) : 1;
        $limit = isset($input['limit']) && is_numeric($input['limit']) ? max(1, min(1000, intval($input['limit']))) : 50;
        $offset = ($page - 1) * $limit;

        // Primeiro, contar o total de resultados (sem LIMIT)
        // Extrair apenas a parte WHERE da query original
        $whereClause = str_replace("SELECT * FROM cadastro ", "", $sql);
        $countSql = "SELECT COUNT(*) as total FROM cadastro " . $whereClause;
        $countStmt = $pdo->prepare($countSql);
        foreach ($params as $key => $value) {
            $countStmt->bindValue($key, $value);
        }
        $countStmt->execute();
        $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Executar query com paginação
        $sql .= " LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Buscar desenhos apenas dos resultados da página atual (otimizado com query única)
        $desenhos = [];
        $desenhosPorImobId = []; // Mapa de imob_id => array de desenhos
        
        // Coletar todas as combinações únicas de (quarteirao, quadra, lote) dos resultados da página atual
        $combinacoesComLetras = []; // Array de arrays [quarteirao, quadra, lote, imob_id]
        $combinacoesSemLetras = []; // Array de arrays [quarteirao, quadra, lote, imob_id]
        
        foreach ($results as $resultado) {
            $imob_id = isset($resultado['imob_id']) ? $resultado['imob_id'] : null;
            $cara_quarteirao = isset($resultado['cara_quarteirao']) ? $resultado['cara_quarteirao'] : null;
            $quadra = isset($resultado['quadra']) ? $resultado['quadra'] : null;
            $lote = isset($resultado['lote']) ? $resultado['lote'] : null;
            
            if ($cara_quarteirao && $quadra && $lote) {
                // Verificar se quarteirão contém letras
                $temLetras = preg_match('/[^0-9]/', (string)$cara_quarteirao) === 1;
                
                // Criar chave única para evitar duplicatas
                $key = $cara_quarteirao . '|' . $quadra . '|' . $lote;
                
                if ($temLetras) {
                    if (!isset($combinacoesComLetras[$key])) {
                        $combinacoesComLetras[$key] = [
                            'quarteirao' => $cara_quarteirao,
                            'quadra' => $quadra,
                            'lote' => $lote,
                            'imob_ids' => []
                        ];
                    }
                    if ($imob_id && !in_array($imob_id, $combinacoesComLetras[$key]['imob_ids'])) {
                        $combinacoesComLetras[$key]['imob_ids'][] = $imob_id;
                    }
                } else {
                    if (!isset($combinacoesSemLetras[$key])) {
                        $combinacoesSemLetras[$key] = [
                            'quarteirao' => $cara_quarteirao,
                            'quadra' => $quadra,
                            'lote' => $lote,
                            'imob_ids' => []
                        ];
                    }
                    if ($imob_id && !in_array($imob_id, $combinacoesSemLetras[$key]['imob_ids'])) {
                        $combinacoesSemLetras[$key]['imob_ids'][] = $imob_id;
                    }
                }
            }
        }
        
        // Buscar desenhos com letras (uma única query com múltiplas condições OR)
        if (!empty($combinacoesComLetras)) {
            $conditions = [];
            $paramsDesenhos = [];
            $paramIndex = 0;
            
            foreach ($combinacoesComLetras as $comb) {
                $conditions[] = "(quarteirao = :q{$paramIndex} AND quadra = :quad{$paramIndex} AND lote = :lot{$paramIndex})";
                $paramsDesenhos[":q{$paramIndex}"] = $comb['quarteirao'];
                $paramsDesenhos[":quad{$paramIndex}"] = $comb['quadra'];
                $paramsDesenhos[":lot{$paramIndex}"] = $comb['lote'];
                $paramIndex++;
            }
            
            if (!empty($conditions)) {
                $sqlDesenhos = "SELECT id, coordenadas, cor_usuario, quarteirao, quadra, lote 
                                FROM desenhos 
                                WHERE (" . implode(' OR ', $conditions) . ")
                                AND (camada = 'poligono lote' OR camada = 'poligono_lote')
                                AND status > 0";
                
                try {
                    $stmtDesenhos = $pdo->prepare($sqlDesenhos);
                    $stmtDesenhos->execute($paramsDesenhos);
                    $poligonos = $stmtDesenhos->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Criar mapa de lookup para associação rápida
                    $lookupMap = [];
                    foreach ($combinacoesComLetras as $comb) {
                        $key = $comb['quarteirao'] . '|' . $comb['quadra'] . '|' . $comb['lote'];
                        $lookupMap[$key] = $comb['imob_ids'];
                    }
                    
                    // Associar polígonos aos imob_ids correspondentes
                    foreach ($poligonos as $poligono) {
                        $desenhos[] = $poligono;
                        
                        // Encontrar quais imob_ids correspondem a este polígono usando o mapa
                        $key = $poligono['quarteirao'] . '|' . $poligono['quadra'] . '|' . $poligono['lote'];
                        if (isset($lookupMap[$key])) {
                            foreach ($lookupMap[$key] as $imob_id) {
                                if (!isset($desenhosPorImobId[$imob_id])) {
                                    $desenhosPorImobId[$imob_id] = [];
                                }
                                $desenhosPorImobId[$imob_id][] = $poligono;
                            }
                        }
                    }
                } catch (PDOException $e) {
                    error_log("Erro ao buscar desenhos (com letras): " . $e->getMessage());
                }
            }
        }
        
        // Buscar desenhos sem letras (uma única query com múltiplas condições OR)
        if (!empty($combinacoesSemLetras)) {
            $conditions = [];
            $paramsDesenhos = [];
            $paramIndex = 0;
            
            foreach ($combinacoesSemLetras as $comb) {
                $conditions[] = "(CAST(quarteirao AS UNSIGNED) = CAST(:q{$paramIndex} AS UNSIGNED) AND quadra = :quad{$paramIndex} AND lote = :lot{$paramIndex})";
                $paramsDesenhos[":q{$paramIndex}"] = $comb['quarteirao'];
                $paramsDesenhos[":quad{$paramIndex}"] = $comb['quadra'];
                $paramsDesenhos[":lot{$paramIndex}"] = $comb['lote'];
                $paramIndex++;
            }
            
            if (!empty($conditions)) {
                $sqlDesenhos = "SELECT id, coordenadas, cor_usuario, quarteirao, quadra, lote 
                                FROM desenhos 
                                WHERE (" . implode(' OR ', $conditions) . ")
                                AND (camada = 'poligono lote' OR camada = 'poligono_lote')
                                AND status > 0";
                
                try {
                    $stmtDesenhos = $pdo->prepare($sqlDesenhos);
                    $stmtDesenhos->execute($paramsDesenhos);
                    $poligonos = $stmtDesenhos->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Criar mapa de lookup para associação rápida (usando valores numéricos normalizados)
                    $lookupMap = [];
                    foreach ($combinacoesSemLetras as $comb) {
                        $key = intval($comb['quarteirao']) . '|' . $comb['quadra'] . '|' . $comb['lote'];
                        $lookupMap[$key] = $comb['imob_ids'];
                    }
                    
                    // Associar polígonos aos imob_ids correspondentes
                    foreach ($poligonos as $poligono) {
                        $desenhos[] = $poligono;
                        
                        // Encontrar quais imob_ids correspondem a este polígono usando o mapa
                        $key = intval($poligono['quarteirao']) . '|' . $poligono['quadra'] . '|' . $poligono['lote'];
                        if (isset($lookupMap[$key])) {
                            foreach ($lookupMap[$key] as $imob_id) {
                                if (!isset($desenhosPorImobId[$imob_id])) {
                                    $desenhosPorImobId[$imob_id] = [];
                                }
                                $desenhosPorImobId[$imob_id][] = $poligono;
                            }
                        }
                    }
                } catch (PDOException $e) {
                    error_log("Erro ao buscar desenhos (sem letras): " . $e->getMessage());
                }
            }
        }

        // Calcular informações de paginação
        $totalPages = ceil($totalCount / $limit);

        // Retornar resultados com os desenhos encontrados
        echo json_encode([
            'success' => true,
            'total' => intval($totalCount),
            'page' => $page,
            'limit' => $limit,
            'totalPages' => $totalPages,
            'dados' => $results,
            'desenhos' => $desenhos,
            'desenhosPorImobId' => $desenhosPorImobId // Mapa de imob_id => desenhos
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
                    <span id="resultsCount" class="badge bg-primary ms-2">0</span>
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
                    <div id="paginationInfoBottom" class="text-muted small"></div>
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

        // Opções de pesquisa
        const searchOptions = [{
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
                value: 'cnpj',
                label: 'CNPJ',
                fields: [{
                    name: 'cnpj',
                    placeholder: 'CNPJ'
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
                value: 'bairro',
                label: 'Bairro',
                fields: [{
                    name: 'bairro',
                    placeholder: 'Bairro'
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
                value: 'imob_id',
                label: 'ID Imobiliário',
                fields: [{
                    name: 'imob_id',
                    placeholder: 'ID Imobiliário'
                }]
            },
            {
                value: 'zona',
                label: 'Zona',
                fields: [{
                    name: 'zona',
                    placeholder: 'Zona'
                }]
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
                value: 'tipo_edificacao',
                label: 'Tipo de Edificação',
                fields: [{
                    name: 'tipo_edificacao',
                    placeholder: 'Tipo de Edificação'
                }]
            },
            {
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
                    displaySearchResults(result.dados, result.total, result.page, result.totalPages, result.desenhos || [], result.desenhosPorImobId || {});
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

                // Salvar última pesquisa
                await saveLastSearch(payload);

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
                        displaySearchResults(result.dados, result.total, result.page, result.totalPages, result.desenhos || [], result.desenhosPorImobId || {});
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

                        // Usar cor_usuario do banco ou preto como padrão
                        const cor = desenho.cor_usuario || '#000000';

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

        // Função para exibir resultados da pesquisa
        function displaySearchResults(dados, total, page = 1, totalPages = 1, desenhos = [], desenhosPorImobId = {}) {
            const resultsTableHead = document.getElementById('resultsTableHead');
            const resultsTableBody = document.getElementById('resultsTableBody');
            const resultsCount = document.getElementById('resultsCount');
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
                if (resultsTableHead) {
                    resultsTableHead.innerHTML = '<tr><th colspan="100%" class="text-center">Nenhum resultado encontrado</th></tr>';
                }
                if (resultsTableBody) {
                    resultsTableBody.innerHTML = '';
                }
                if (resultsCount) {
                    resultsCount.textContent = '0';
                }
                // Ocultar paginação e scroll se não houver resultados
                if (searchResultsPaginationBottom) {
                    searchResultsPaginationBottom.style.display = 'none';
                }
                // Não mostrar o quadro se não houver resultados
                if (searchResultsBox) {
                    searchResultsBox.classList.remove('visible');
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

            // Filtrar apenas as colunas que existem nos dados
            const columnArray = columnsOrder.filter(col => {
                return dados.some(row => row.hasOwnProperty(col));
            });

            // Criar cabeçalho com checkbox na primeira coluna
            let headerHTML = '<tr>';
            headerHTML += '<th style="width: 50px; text-align: center;"><input type="checkbox" id="selectAllCheckbox" title="Selecionar todos"></th>';
            columnArray.forEach(col => {
                headerHTML += `<th>${col}</th>`;
            });
            headerHTML += '</tr>';
            resultsTableHead.innerHTML = headerHTML;

            // Criar corpo com checkbox na primeira coluna
            let bodyHTML = '';
            dados.forEach((row, index) => {
                const imobId = row.imob_id || '';
                const temDesenho = imobId && desenhosPorImobId[imobId] && desenhosPorImobId[imobId].length > 0;
                
                bodyHTML += '<tr>';
                // Primeira coluna: checkbox (desabilitado se não tiver desenho)
                if (temDesenho) {
                    bodyHTML += `<td style="text-align: center;"><input type="checkbox" class="row-checkbox" data-index="${index}" data-imob-id="${imobId}" checked></td>`;
                } else {
                    bodyHTML += `<td style="text-align: center;"><input type="checkbox" class="row-checkbox" data-index="${index}" data-imob-id="${imobId}" disabled></td>`;
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
                checkbox.addEventListener('change', function() {
                    const imobId = this.getAttribute('data-imob-id');
                    if (imobId) {
                        togglePolygonVisibility(imobId, this.checked);
                    }
                });
            });

            // Atualizar contador
            resultsCount.textContent = total.toLocaleString('pt-BR');

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
        }
    </script>

</body>

</head>