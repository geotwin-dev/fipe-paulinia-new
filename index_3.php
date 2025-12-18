<?php
session_start();

include("verifica_login.php");
include("connection.php");

// Endpoint para buscar IDs dos desenhos filtrados pela pesquisa salva
if (isset($_GET['buscarDesenhosFiltrados'])) {
    header('Content-Type: application/json');

    try {
        // Verificar se quadrícula foi fornecida
        if (!isset($_GET['quadricula']) || empty($_GET['quadricula'])) {
            echo json_encode(['error' => 'Quadrícula não fornecida']);
            exit;
        }

        $quadricula = $_GET['quadricula'];

        // Buscar pesquisa salva (mesmo sistema do painel)
        $userId = null;
        if (isset($_SESSION['usuario']) && is_array($_SESSION['usuario']) && isset($_SESSION['usuario'][1])) {
            $userId = md5($_SESSION['usuario'][1]);
        } else {
            $userId = md5(session_id());
        }

        $dir = __DIR__ . '/jsonPesquisa';
        $file = $dir . '/' . $userId . '.json';

        if (!file_exists($file)) {
            echo json_encode(['ids' => []]);
            exit;
        }

        $pesquisaData = json_decode(file_get_contents($file), true);
        if (!is_array($pesquisaData) || !isset($pesquisaData['type']) || !isset($pesquisaData['values'])) {
            echo json_encode(['ids' => []]);
            exit;
        }

        $type = $pesquisaData['type'];
        $values = $pesquisaData['values'];

        // Verificar se há cache de resultados - mesma pesquisa com resultados
        $usarCache = false;
        $allResults = null;

        if (
            isset($pesquisaData['results']) &&
            is_array($pesquisaData['results']) &&
            isset($pesquisaData['results']['allResults']) &&
            is_array($pesquisaData['results']['allResults']) &&
            count($pesquisaData['results']['allResults']) > 0
        ) {
            // Cache válido encontrado - mesma pesquisa com resultados
            $usarCache = true;
            $allResults = $pesquisaData['results']['allResults'];
        }

        // Se não usar cache, fazer pesquisa no banco (pesquisa diferente ou sem cache)
        if (!$usarCache) {
            // Para cadastros_nao_desenhados, usar query especial com NOT EXISTS
            if ($type === 'cadastros_nao_desenhados') {
                $sql = "SELECT cad.id, cad.inscricao, cad.imob_id,  
                               cad.logradouro, cad.numero, cad.bairro, cad.cara_quarteirao, cad.quadra, 
                               cad.lote, cad.total_construido, cad.nome_pessoa, 
                               cad.cnpj, cad.area_terreno, cad.tipo_edificacao, cad.tipo_utilizacao, 
                               cad.zona, cad.cat_via, cad.nome_loteamento, 
                               cad.imob_id_principal, cad.multiplo, cad.uso_imovel,
                               SUBSTRING_INDEX(REPLACE(cad.historico, '\r\n', '\n'), '\n', -1) AS historico
                        FROM cadastro cad 
                        WHERE cad.imob_id = cad.imob_id_principal 
                        AND NOT EXISTS ( 
                            SELECT 1 FROM desenhos des 
                            WHERE des.camada = 'marcador_quadra' 
                            AND des.quarteirao = cad.cara_quarteirao 
                            AND des.quadra = cad.quadra 
                            AND des.lote = cad.lote 
                        ) 
                        ORDER BY cad.nome_loteamento, cad.cara_quarteirao";
                $params = [];
                // Não há switch para este tipo, pular direto para execução
            } else {
                $sql = "SELECT cad.id, cad.inscricao, cad.imob_id,  
                               cad.logradouro, cad.numero, cad.bairro, cad.cara_quarteirao, cad.quadra, 
                               cad.lote, cad.total_construido, cad.nome_pessoa, 
                               cad.cnpj, cad.area_terreno, cad.tipo_edificacao, cad.tipo_utilizacao, 
                               cad.zona, cad.cat_via, cad.nome_loteamento, 
                               cad.imob_id_principal, cad.multiplo, cad.uso_imovel,
                               SUBSTRING_INDEX(REPLACE(cad.historico, '\r\n', '\n'), '\n', -1) AS historico
                        FROM cadastro cad WHERE 1=1 AND imob_id = imob_id_principal";
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
                    case 'tipo_utilizacao':
                        if (!empty($values['tipo_utilizacao'])) {
                            $sql .= " AND tipo_utilizacao LIKE :tipo_utilizacao";
                            $params[':tipo_utilizacao'] = '%' . $values['tipo_utilizacao'] . '%';
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
                        echo json_encode(['ids' => []]);
                        exit;
                }
            }

            // Executar pesquisa (SEM paginação - todos os resultados)
            $stmt = $pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            $allResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Salvar no cache
            $pesquisaData['results'] = [
                'allResults' => $allResults
            ];
            file_put_contents($file, json_encode($pesquisaData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }

        // Coletar combinações únicas de (quarteirao, quadra, lote) dos resultados (cache ou banco)
        $combinacoesComLetras = [];
        $combinacoesSemLetras = [];

        foreach ($allResults as $resultado) {
            $cara_quarteirao = isset($resultado['cara_quarteirao']) ? $resultado['cara_quarteirao'] : null;
            $quadra = isset($resultado['quadra']) ? $resultado['quadra'] : null;
            $lote = isset($resultado['lote']) ? $resultado['lote'] : null;

            if ($cara_quarteirao && $quadra && $lote) {
                $temLetras = preg_match('/[^0-9]/', (string)$cara_quarteirao) === 1;
                $key = $cara_quarteirao . '|' . $quadra . '|' . $lote;

                if ($temLetras) {
                    if (!isset($combinacoesComLetras[$key])) {
                        $combinacoesComLetras[$key] = [
                            'quarteirao' => $cara_quarteirao,
                            'quadra' => $quadra,
                            'lote' => $lote
                        ];
                    }
                } else {
                    if (!isset($combinacoesSemLetras[$key])) {
                        $combinacoesSemLetras[$key] = [
                            'quarteirao' => $cara_quarteirao,
                            'quadra' => $quadra,
                            'lote' => $lote
                        ];
                    }
                }
            }
        }

        // Buscar IDs dos desenhos FILTRADOS pela quadrícula
        $idsDesenhos = [];

        // Desenhos com letras
        if (!empty($combinacoesComLetras)) {
            $conditions = [];
            $paramsDesenhos = [':quadricula' => $quadricula];
            $paramIndex = 0;

            foreach ($combinacoesComLetras as $comb) {
                $conditions[] = "(quarteirao = :q{$paramIndex} AND quadra = :quad{$paramIndex} AND lote = :lot{$paramIndex})";
                $paramsDesenhos[":q{$paramIndex}"] = $comb['quarteirao'];
                $paramsDesenhos[":quad{$paramIndex}"] = $comb['quadra'];
                $paramsDesenhos[":lot{$paramIndex}"] = $comb['lote'];
                $paramIndex++;
            }

            if (!empty($conditions)) {
                $sqlDesenhos = "SELECT id FROM desenhos 
                                WHERE quadricula = :quadricula
                                AND (" . implode(' OR ', $conditions) . ")
                                AND (camada = 'poligono lote' OR camada = 'poligono_lote')
                                AND status > 0";

                $stmtDesenhos = $pdo->prepare($sqlDesenhos);
                $stmtDesenhos->execute($paramsDesenhos);
                $poligonos = $stmtDesenhos->fetchAll(PDO::FETCH_ASSOC);
                foreach ($poligonos as $p) {
                    $idsDesenhos[] = intval($p['id']);
                }
            }
        }

        // Desenhos sem letras
        if (!empty($combinacoesSemLetras)) {
            $conditions = [];
            $paramsDesenhos = [':quadricula' => $quadricula];
            $paramIndex = 0;

            foreach ($combinacoesSemLetras as $comb) {
                $conditions[] = "(CAST(quarteirao AS UNSIGNED) = CAST(:q{$paramIndex} AS UNSIGNED) AND quadra = :quad{$paramIndex} AND lote = :lot{$paramIndex})";
                $paramsDesenhos[":q{$paramIndex}"] = $comb['quarteirao'];
                $paramsDesenhos[":quad{$paramIndex}"] = $comb['quadra'];
                $paramsDesenhos[":lot{$paramIndex}"] = $comb['lote'];
                $paramIndex++;
            }

            if (!empty($conditions)) {
                $sqlDesenhos = "SELECT id FROM desenhos 
                                WHERE quadricula = :quadricula
                                AND (" . implode(' OR ', $conditions) . ")
                                AND (camada = 'poligono lote' OR camada = 'poligono_lote')
                                AND status > 0";

                $stmtDesenhos = $pdo->prepare($sqlDesenhos);
                $stmtDesenhos->execute($paramsDesenhos);
                $poligonos = $stmtDesenhos->fetchAll(PDO::FETCH_ASSOC);
                foreach ($poligonos as $p) {
                    $id = intval($p['id']);
                    if (!in_array($id, $idsDesenhos)) {
                        $idsDesenhos[] = $id;
                    }
                }
            }
        }

        // Buscar também marcadores (se houver na pesquisa)
        // Por enquanto, retornar apenas IDs de polígonos

        echo json_encode(['ids' => $idsDesenhos]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}

// Endpoint para realizar pesquisa no banco de dados (sem desenhos)
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

        if (file_exists($file)) {
            $cacheData = json_decode(file_get_contents($file), true);
            // Verificar se o cache corresponde à pesquisa atual
            if (
                is_array($cacheData) &&
                isset($cacheData['type']) && $cacheData['type'] === $type &&
                isset($cacheData['values']) && is_array($cacheData['values']) &&
                is_array($values)
            ) {

                // Comparar valores de forma mais robusta
                $cacheValuesNormalized = $cacheData['values'];
                $currentValuesNormalized = $values;

                // Normalizar arrays para comparação (ordenar chaves e converter tipos)
                ksort($cacheValuesNormalized);
                ksort($currentValuesNormalized);

                // Converter valores para string para comparação
                $cacheValuesStr = json_encode($cacheValuesNormalized, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
                $currentValuesStr = json_encode($currentValuesNormalized, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);

                if (
                    $cacheValuesStr === $currentValuesStr &&
                    isset($cacheData['results']) &&
                    is_array($cacheData['results']) &&
                    isset($cacheData['results']['allResults']) &&
                    is_array($cacheData['results']['allResults']) &&
                    count($cacheData['results']['allResults']) > 0
                ) {
                    // Cache válido encontrado - mesma pesquisa com resultados
                    $usarCache = true;
                    $allResults = $cacheData['results']['allResults'];
                }
            }
        }

        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        // Só construir query SQL se não usar cache (pesquisa diferente ou sem cache)
        if (!$usarCache) {
            // Para cadastros_nao_desenhados, usar query especial com NOT EXISTS
            if ($type === 'cadastros_nao_desenhados') {
                $sql = "SELECT cad.id, cad.inscricao, cad.imob_id,  
                               cad.logradouro, cad.numero, cad.bairro, cad.cara_quarteirao, cad.quadra, 
                               cad.lote, cad.total_construido, cad.nome_pessoa, 
                               cad.cnpj, cad.area_terreno, cad.tipo_edificacao, cad.tipo_utilizacao, 
                               cad.zona, cad.cat_via, cad.nome_loteamento, 
                               cad.imob_id_principal, cad.multiplo, cad.uso_imovel,
                               SUBSTRING_INDEX(REPLACE(cad.historico, '\r\n', '\n'), '\n', -1) AS historico
                        FROM cadastro cad 
                        WHERE cad.imob_id = cad.imob_id_principal 
                        AND NOT EXISTS ( 
                            SELECT 1 FROM desenhos des 
                            WHERE des.camada = 'marcador_quadra' 
                            AND des.quarteirao = cad.cara_quarteirao 
                            AND des.quadra = cad.quadra 
                            AND des.lote = cad.lote 
                        ) 
                        ORDER BY cad.nome_loteamento, cad.cara_quarteirao";
                $params = [];
                // Não há switch para este tipo, pular direto para execução
            } else {
                $sql = "SELECT cad.id, cad.inscricao, cad.imob_id,  
                               cad.logradouro, cad.numero, cad.bairro, cad.cara_quarteirao, cad.quadra, 
                               cad.lote, cad.total_construido, cad.nome_pessoa, 
                               cad.cnpj, cad.area_terreno, cad.tipo_edificacao, cad.tipo_utilizacao, 
                               cad.zona, cad.cat_via, cad.nome_loteamento, 
                               cad.imob_id_principal, cad.multiplo, cad.uso_imovel,
                               SUBSTRING_INDEX(REPLACE(cad.historico, '\r\n', '\n'), '\n', -1) AS historico
                        FROM cadastro cad WHERE 1=1 AND imob_id = imob_id_principal";
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
        } else {
            // Fazer pesquisa no banco
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
            } else {
                $wherePart = '';
                if (preg_match('/WHERE\s+(.+?)(?:\s+ORDER\s+BY|$)/is', $sql, $matches)) {
                    $wherePart = trim($matches[1]);
                } else if (preg_match('/WHERE\s+(.+)$/is', $sql, $matches)) {
                    $wherePart = trim($matches[1]);
                }

                if (empty($wherePart)) {
                    $wherePart = '1=1';
                }

                $countSql = "SELECT COUNT(*) as total FROM cadastro WHERE " . $wherePart;
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
            $allResults = $stmtTodos->fetchAll(PDO::FETCH_ASSOC);

            // Aplicar paginação APENAS na exibição dos dados da tabela
            $results = array_slice($allResults, $offset, $limit);
        }

        // Calcular informações de paginação
        $totalPages = ceil($totalCount / $limit);

        // Salvar pesquisa apenas se não usou cache (nova pesquisa)
        if (!$usarCache) {
            // Salvar pesquisa e resultados no JSON (apenas quando faz nova pesquisa)
            // Salvar TODOS os resultados
            $cacheData = [
                'type' => $type,
                'values' => $values,
                'totalCount' => $totalCount, // Salvar o total real do banco
                'usuario_email' => isset($_SESSION['usuario']) && is_array($_SESSION['usuario']) && isset($_SESSION['usuario'][1]) ? $_SESSION['usuario'][1] : null,
                'results' => [
                    'allResults' => $allResults // TODOS os resultados
                ]
            ];
            file_put_contents($file, json_encode($cacheData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }

        // Retornar resultados (SEM desenhos)
        echo json_encode([
            'success' => true,
            'total' => intval($totalCount),
            'page' => $page,
            'limit' => $limit,
            'totalPages' => $totalPages,
            'dados' => $results
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

// Endpoint simples para salvar/carregar última pesquisa do usuário
if (isset($_GET['searchApi'])) {
    header('Content-Type: application/json');

    $userId = null;
    if (isset($_SESSION['usuario']) && is_array($_SESSION['usuario']) && isset($_SESSION['usuario'][1])) {
        $userId = md5($_SESSION['usuario'][1]);
    } else {
        $userId = md5(session_id());
    }

    $dir = __DIR__ . '/jsonPesquisa';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $file = $dir . '/' . $userId . '.json';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (is_array($input) && isset($input['type']) && isset($input['values'])) {
            // Salvar apenas a pesquisa (não os resultados)
            $data = [
                'type' => $input['type'],
                'values' => $input['values']
            ];
            file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Dados inválidos']);
        }
    } else {
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if (is_array($data) && isset($data['type']) && isset($data['values'])) {
                echo json_encode([
                    'status' => 'success',
                    'type' => $data['type'],
                    'values' => $data['values']
                ]);
            } else {
                echo json_encode(['status' => 'not_found']);
            }
        } else {
            echo json_encode(['status' => 'not_found']);
        }
    }
    exit;
}

// Buscar dados da ortofoto (apenas se não for endpoint)
if (isset($_GET['quadricula'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM ortofotos WHERE quadricula = :a");
        $stmt->bindParam(':a', $_GET['quadricula']);
        $stmt->execute();

        $dadosOrto = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $dadosOrto = [];
    }
} else {
    $dadosOrto = [];
}

// Endpoint para buscar historico completo de um registro
if (isset($_GET['buscar_historico'])) {
    header('Content-Type: application/json');
    
    try {
        $imob_id = isset($_GET['imob_id']) ? trim($_GET['imob_id']) : '';
        
        if (empty($imob_id)) {
            echo json_encode(['error' => 'imob_id é obrigatório']);
            exit;
        }
        
        $sql = "SELECT historico FROM cadastro WHERE imob_id = :imob_id LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':imob_id', $imob_id);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            $historico = $result['historico'] ?? '';
            // Dividir por quebras de linha
            $linhas = [];
            if (!empty($historico)) {
                // Normalizar quebras de linha
                $historico = str_replace("\r\n", "\n", $historico);
                $historico = str_replace("\r", "\n", $historico);
                $linhas = explode("\n", $historico);
                // Remover linhas vazias do final
                $linhas = array_filter($linhas, function($linha) {
                    return trim($linha) !== '';
                });
                $linhas = array_values($linhas); // Reindexar
            }
            
            echo json_encode([
                'success' => true,
                'imob_id' => $imob_id,
                'linhas' => $linhas
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'imob_id' => $imob_id,
                'linhas' => []
            ]);
        }
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Erro ao buscar histórico: ' . $e->getMessage()]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Erro: ' . $e->getMessage()]);
    }
    exit;
}

// Endpoint para adicionar nova linha ao historico
if (isset($_GET['adicionar_historico'])) {
    header('Content-Type: application/json');
    
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!is_array($input) || !isset($input['imob_id']) || !isset($input['nova_linha'])) {
            echo json_encode(['error' => 'Dados inválidos']);
            exit;
        }
        
        $imob_id = trim($input['imob_id']);
        $nova_linha = trim($input['nova_linha']);
        
        if (empty($imob_id)) {
            echo json_encode(['error' => 'imob_id é obrigatório']);
            exit;
        }
        
        if (empty($nova_linha)) {
            echo json_encode(['error' => 'Nova linha não pode estar vazia']);
            exit;
        }
        
        // Buscar historico atual
        $sql = "SELECT historico FROM cadastro WHERE imob_id = :imob_id LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':imob_id', $imob_id);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            echo json_encode(['error' => 'Registro não encontrado']);
            exit;
        }
        
        $historico_atual = $result['historico'] ?? '';
        
        // Adicionar nova linha (append)
        if (!empty($historico_atual)) {
            // Normalizar quebras de linha existentes
            $historico_atual = str_replace("\r\n", "\n", $historico_atual);
            $historico_atual = str_replace("\r", "\n", $historico_atual);
            // Remover quebra de linha do final se existir
            $historico_atual = rtrim($historico_atual, "\n");
            // Adicionar nova linha
            $historico_novo = $historico_atual . "\n" . $nova_linha;
        } else {
            $historico_novo = $nova_linha;
        }
        
        // Atualizar no banco
        $sqlUpdate = "UPDATE cadastro SET historico = :historico WHERE imob_id = :imob_id";
        $stmtUpdate = $pdo->prepare($sqlUpdate);
        $stmtUpdate->bindValue(':historico', $historico_novo);
        $stmtUpdate->bindValue(':imob_id', $imob_id);
        $stmtUpdate->execute();
        
        // Retornar todas as linhas atualizadas
        $linhas = [];
        if (!empty($historico_novo)) {
            $historico_novo_normalizado = str_replace("\r\n", "\n", $historico_novo);
            $historico_novo_normalizado = str_replace("\r", "\n", $historico_novo_normalizado);
            $linhas = explode("\n", $historico_novo_normalizado);
            $linhas = array_filter($linhas, function($linha) {
                return trim($linha) !== '';
            });
            $linhas = array_values($linhas);
        }
        
        echo json_encode([
            'success' => true,
            'imob_id' => $imob_id,
            'linhas' => $linhas
        ]);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Erro ao adicionar histórico: ' . $e->getMessage()]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Erro: ' . $e->getMessage()]);
    }
    exit;
}

?>

<script>
    let dadosOrto = <?php echo json_encode($dadosOrto); ?>;
</script>

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

    <!-- Google Maps API -->
    <script src="apiGoogle.js"></script>

    <!-- toGeoJSON -->
    <script src="bibliotecas/togeojson.js" defer></script>
    <!-- DXF Parser -->
    <script src="https://cdn.jsdelivr.net/npm/dxf-parser@1.1.2/dist/dxf-parser.min.js" defer></script>
    <!-- Nosso framework -->
    <script src="framework.js" defer></script>

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
            height: 100vh;
            display: flex;
            flex-direction: column;
        }

        #modalCamada {
            background-color: rgba(0, 0, 0, 0.5)
        }

        #dropCamadas {
            width: 320px;
        }

        .map-label-text {
            font: 700 14px/1.1 Roboto, Arial, sans-serif;
            color: #fff;
            /* texto branco */
            white-space: nowrap;
            pointer-events: none;
            /* não intercepta cliques do mapa */
            background: transparent;
            /* contorno preto usando múltiplas sombras (efeito stroke) */
            text-shadow:
                -1px -1px 0 #000,
                0px -1px 0 #000,
                1px -1px 0 #000,
                -1px 0px 0 #000,
                1px 0px 0 #000,
                -1px 1px 0 #000,
                0px 1px 0 #000,
                1px 1px 0 #000;
        }

        #controleNavegacaoQuadriculas {
            position: absolute;
            top: 60px;
            left: 5px;
            z-index: 1000;
            display: flex;
            gap: 5px;
            flex-direction: column;
            background-color: rgba(0, 0, 0, 0.9);
            padding: 10px;
            border-radius: 10px;
            box-shadow: 0 0 10px 0 rgba(0, 0, 0, 0.5);
            transition: all 0.3s ease;
        }

        #controleNavegacaoQuadriculas.expandido {
            width: auto;
            overflow-y: auto;
        }

        #controleNavegacaoQuadriculas div {
            display: flex;
            gap: 5px;
        }

        .controleNavegacaoQuadriculas-btn {
            width: 30px;
            height: 30px;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
        }

        .controleNavegacaoQuadriculas-btn2 {
            width: 30px;
            height: 30px;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 12px;
        }

        .subDivControle {
            min-width: 30px;
        }

        .divControle {
            min-height: 30px;
        }

        /* Estilos para a grade expandida - Regras mais específicas */
        #controleNavegacaoQuadriculas #gradeExpandida {
            margin-top: 10px !important;
            border-top: 1px solid rgba(255, 255, 255, 0.3) !important;
            padding-top: 10px !important;
            overflow-y: auto !important;
            display: none !important;
        }

        #controleNavegacaoQuadriculas #gradeExpandida.show {
            display: block !important;
        }

        #controleNavegacaoQuadriculas .grade-expandida-linha {
            display: flex !important;
            gap: 3px !important;
            justify-content: center !important;
            margin-bottom: 3px !important;
            align-items: center !important;
            flex-direction: row !important;
        }

        #controleNavegacaoQuadriculas .grade-expandida-celula {
            width: 30px !important;
            height: 30px !important;
            text-align: center !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-size: 10px !important;
            font-weight: bold !important;
            border-radius: 3px !important;
            transition: all 0.2s ease !important;
            box-sizing: border-box !important;
        }

        #controleNavegacaoQuadriculas .grade-expandida-celula.vazia {
            background-color: rgba(255, 255, 255, 0.1) !important;
            color: rgba(255, 255, 255, 0.3) !important;
            cursor: default !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
        }

        /* Cabeçalhos da grade */
        #controleNavegacaoQuadriculas .grade-expandida-celula.cabecalho {
            background-color: rgba(255, 255, 255, 0.2) !important;
            color: rgba(255, 255, 255, 0.9) !important;
            font-weight: bold !important;
            border: 1px solid rgba(255, 255, 255, 0.3) !important;
        }

        /* Estilos para o controle de desenhos da prefeitura */
        #controleDesenhosPrefeitura {
            position: absolute;
            top: 60px;
            left: 5px;
            /* Posicionado ao lado do controle de quadrículas */
            z-index: 1000;
            display: none;
            /* Inicialmente oculto */
            flex-direction: column;
            background-color: rgba(0, 0, 0, 1);
            padding: 10px;
            border-radius: 10px;
            box-shadow: 0 0 10px 0 rgba(0, 0, 0, 0.5);
            transition: all 0.3s ease;
            color: white;
            min-width: 200px;
        }

        #controleDesenhosPrefeitura.show {
            display: flex;
        }

        #controleDesenhosPrefeitura .grade-direcoes {
            display: grid;
            grid-template-columns: 40px 40px 40px;
            grid-template-rows: 30px 30px 30px;
            gap: 3px;
            margin-bottom: 10px;
            justify-content: center;
            align-items: center;
        }

        #controleDesenhosPrefeitura .grade-botoes {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 3px;
            margin-bottom: 10px;
        }

        .controle-desenhos-btn {
            width: 40px;
            height: 30px;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            transition: all 0.2s ease;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        .controle-desenhos-btn:hover {
            background-color: rgba(255, 255, 255, 0.8);
            color: #000;
        }

        .controle-desenhos-btn-direcao {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
        }

        .controle-desenhos-btn-resetar {
            background-color: #ffc107;
            color: #000;
        }

        .controle-desenhos-btn-salvar {
            background-color: #28a745;
            color: white;
        }

        .controle-desenhos-btn-cancelar {
            background-color: #dc3545;
            color: white;
        }

        .controle-desenhos-btn.vazio {
            background-color: transparent;
            cursor: default;
        }

        .controle-desenhos-btn.vazio:hover {
            background-color: transparent;
            color: white;
        }

        #controleDesenhosPrefeitura .selecao-distancia {
            margin-bottom: 10px;
            padding: 8px;
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 5px;
        }

        #controleDesenhosPrefeitura .selecao-distancia label {
            font-size: 12px;
            margin-right: 10px;
            cursor: pointer;
        }

        #controleDesenhosPrefeitura .selecao-distancia input[type="radio"] {
            margin-right: 5px;
        }

        /* Estilos para controles de rotação */
        #controleDesenhosPrefeitura .controles-rotacao {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-bottom: 10px;
        }

        .controle-desenhos-btn-rotacao {
            width: 60px;
            height: 30px;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            transition: all 0.2s ease;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        .controle-desenhos-btn-rotacao-individual {
            background-color: #17a2b8;
            color: white;
            font-size: 10px;
        }

        .controle-desenhos-btn-rotacao-coletiva {
            background-color: #6f42c1;
            color: white;
            font-size: 10px;
        }

        .controle-desenhos-btn-rotacao:hover {
            background-color: rgba(255, 255, 255, 0.8);
            color: #000;
        }

        /* Botão de expansão */
        #controleExpansao {
            text-align: center;
            margin-top: 5px;
        }

        #btnExpandir {
            width: 100%;
            font-size: 10px;
            padding: 4px 8px;
            background-color: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            transition: all 0.2s ease;
        }

        #btnExpandir:hover {
            background-color: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.5);
        }

        #btnExpandir.expandido {
            background-color: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.5);
        }

        #grade3x3 {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        /* Estilos para a div flutuante de cadastro */
        #divCadastro {
            position: absolute;
            top: 70px;
            right: 60px;
            z-index: 1000;
            width: 280px;
            max-height: 300px;
            background-color: white;
            border: 1px solid #ddd;
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            color: #333;
            overflow: hidden;
            display: none;
        }

        #divCadastro2 {
            position: absolute;
            top: 390px;
            right: 60px;
            z-index: 1000;
            width: 280px;
            max-height: 300px;
            background-color: white;
            border: 1px solid #ddd;
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            color: #333;
            overflow: hidden;
            display: none;
        }

        #divCadastro3 {
            position: absolute;
            top: 220px;
            left: 5px;
            z-index: 1000;
            width: 240px;
            max-height: 300px;
            background-color: white;
            border: 1px solid #ddd;
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            color: #333;
            overflow: hidden;
            display: none;
        }

        .div-cadastro-header {
            background-color: #f8f9fa;
            padding: 10px 12px;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .div-cadastro-header h6 {
            margin: 0;
            color: #555;
            font-weight: 600;
            font-size: 13px;
        }

        .btn-close-cadastro {
            background: none;
            border: none;
            color: #666;
            font-size: 14px;
            cursor: pointer;
            padding: 2px;
            border-radius: 3px;
            transition: background-color 0.2s ease;
        }

        .btn-close-cadastro:hover {
            background-color: #e9ecef;
        }

        .div-cadastro-body {
            padding: 10px;
            max-height: calc(300px - 50px);
            overflow-y: auto;
        }

        .div-cadastro-body .opcao-loteamento {
            margin-bottom: 6px;
            padding: 8px 10px;
            background-color: #fafafa;
            border-radius: 4px;
            border: 1px solid #eee;
            transition: background-color 0.2s ease;
        }

        .div-cadastro-body .opcao-loteamento:hover {
            background-color: #f0f0f0;
        }

        .div-cadastro-body .opcao-loteamento input[type="radio"] {
            margin-right: 8px;
        }

        .div-cadastro-body .opcao-loteamento label {
            font-weight: 500;
            color: #333;
            cursor: pointer;
            margin: 0;
            display: block;
            font-size: 13px;
            line-height: 1.3;
        }

        .div-cadastro-body .opcao-loteamento label small {
            color: #666;
            font-weight: normal;
            font-size: 11px;
            display: block;
            margin-top: 2px;
        }

        .div-cadastro-body .submenu-pdfs {
            margin-left: 20px;
            margin-top: 5px;
            display: flex;
            flex-wrap: wrap;
            gap: 3px;
        }

        .div-cadastro-body .submenu-pdfs a {
            display: inline-block;
            color: #666;
            text-decoration: none;
            padding: 2px 6px;
            font-size: 10px;
            background-color: #e9ecef;
            border-radius: 10px;
            transition: background-color 0.2s ease;
        }

        .div-cadastro-body .submenu-pdfs a:hover {
            background-color: #007bff;
            color: white;
        }

        .div-cadastro-body .submenu-pdfs a i {
            margin-right: 3px;
            font-size: 9px;
        }

        /* Estilos para os radio buttons dos PDFs */
        .pdf-option {
            margin: 4px 0;
            display: flex;
            align-items: center;
            padding: 4px 8px;
            border-radius: 3px;
            transition: background-color 0.2s;
        }

        .pdf-option:hover {
            background-color: #f0f0f0;
        }

        .pdf-option input[type="radio"] {
            margin-right: 6px;
            margin: 0;
        }

        .pdf-option label {
            margin: 0 !important;
            font-weight: normal !important;
            cursor: pointer;
            flex: 1;
            font-size: 11px !important;
            line-height: 1.2;
        }

        .pdf-option input[type="radio"]:checked+label {
            color: #007bff;
            font-weight: 600 !important;
        }

        .pdf-option input[type="radio"]:disabled+label {
            color: #999 !important;
            opacity: 0.6;
        }

        .pdf-option input[type="radio"]:disabled {
            opacity: 0.4;
        }

        .div-cadastro-body .opcao-loteamento.selected {
            background-color: #e3f2fd;
            border-color: #007bff;
        }

        .div-cadastro-body .opcao-loteamento.selected label {
            color: #007bff;
        }

        /* Estilos para as opções de quarteirões */
        .div-cadastro-body .opcao-quarteirao {
            margin-bottom: 6px;
            padding: 8px 10px;
            background-color: #fafafa;
            border-radius: 4px;
            border: 1px solid #eee;
            transition: background-color 0.2s ease;
        }

        .div-cadastro-body .opcao-quarteirao:hover {
            background-color: #f0f0f0;
        }

        .div-cadastro-body .opcao-quarteirao input[type="radio"] {
            margin-right: 8px;
        }

        .div-cadastro-body .opcao-quarteirao label {
            font-weight: 500;
            color: #333;
            cursor: pointer;
            margin: 0;
            display: block;
            font-size: 13px;
            line-height: 1.3;
        }

        .div-cadastro-body .opcao-quarteirao label small {
            color: #666;
            font-weight: normal;
            font-size: 11px;
            display: block;
            margin-top: 2px;
        }

        .div-cadastro-body .opcao-quarteirao.selected {
            background-color: #e3f2fd;
            border-color: #007bff;
        }

        .div-cadastro-body .opcao-quarteirao.selected label {
            color: #007bff;
        }

        /* Estilos para as opções de quadras */
        .div-cadastro-body .opcao-quadra {
            margin-bottom: 3px;
            padding-left: 20px;
            cursor: pointer;
        }

        .div-cadastro-body .opcao-quadra input[type="checkbox"] {
            margin-right: 6px;
            margin-top: 0;
        }

        .div-cadastro-body .opcao-quadra label {
            font-weight: normal;
            color: #555;
            cursor: pointer;
            margin: 0;
            display: inline-block;
            font-size: 11px;
            line-height: 1.2;
        }

        .div-cadastro-body .opcao-quadra.selected label {
            color: #007bff;
            font-weight: 500;
        }

        /* Estilos para as opções de lotes */
        .div-cadastro-body .opcao-lote {
            margin-bottom: 6px;
            padding: 8px 10px;
            background-color: #fafafa;
            border-radius: 4px;
            border: 1px solid #eee;
            transition: background-color 0.2s ease;
            cursor: pointer;
        }

        .div-cadastro-body .opcao-lote:hover {
            background-color: #f0f0f0;
        }

        .div-cadastro-body .opcao-lote.selected {
            background-color: #e3f2fd;
            border-color: #007bff;
        }

        .div-cadastro-body .opcao-lote .lote-texto {
            font-weight: 500;
            color: #333;
            font-size: 13px;
            line-height: 1.3;
        }

        .div-cadastro-body .opcao-lote .lote-flecha {
            font-family: monospace;
            font-size: 14px;
        }

        /* Scrollbar simples */
        .div-cadastro-body::-webkit-scrollbar {
            width: 4px;
        }

        .div-cadastro-body::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .div-cadastro-body::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 2px;
        }

        .div-cadastro-body::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }

        /* Estilo para os inputs quando estiverem no modo de inserção */
        #inputLoteAtual.modo-insercao,
        #inputQuadraAtual.modo-insercao {
            background-color: #fff3cd;
            border-color: #ffeaa7;
            box-shadow: 0 0 0 0.2rem rgba(255, 193, 7, 0.25);
        }

        #inputLoteAtual.modo-insercao:focus,
        #inputQuadraAtual.modo-insercao:focus {
            background-color: #fff3cd;
            border-color: #ffeaa7;
            box-shadow: 0 0 0 0.2rem rgba(255, 193, 7, 0.25);
        }

        /* Estilo para lotes inseridos com sucesso */
        .div-cadastro-body .opcao-lote.lote-inserido {
            background-color: #d4edda !important;
            border-color: #c3e6cb !important;
            color: #155724 !important;
        }

        .div-cadastro-body .opcao-lote.lote-inserido:hover {
            background-color: #c3e6cb !important;
        }

        .div-cadastro-body .opcao-lote.lote-inserido .lote-texto {
            color: #155724 !important;
            font-weight: 600 !important;
        }

        /* Estilo para o tooltip do marcador */
        #tooltipMarcador {
            position: absolute;
            background: white;
            border: 1px solid #ccc;
            border-radius: 6px;
            padding: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            z-index: 10000;
            display: none;
            min-width: 120px;
        }

        #tooltipMarcador .tooltip-header {
            font-weight: bold;
            margin-bottom: 8px;
            color: #333;
            font-size: 12px;
        }

        #tooltipMarcador .tooltip-content {
            text-align: center;
        }

        #tooltipMarcador .btn-delete-marcador {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 11px;
            transition: background-color 0.2s;
        }

        #tooltipMarcador .btn-delete-marcador:hover {
            background-color: #c82333;
        }

        .btn-close-tooltip {
            background: none;
            border: none;
            color: #666;
            font-size: 14px;
            cursor: pointer;
        }

        .btn-close-tooltip:hover {
            color: red;
        }

        /* Botão dropdown de filtros de cores */
        #btnFiltroCores {
            position: absolute;
            bottom: 20px;
            left: 5px;
            z-index: 1000;
            background-color: rgba(0, 0, 0, 0.7);
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            display: none;
            transition: all 0.3s ease;
        }

        #btnFiltroCores:hover {
            background-color: rgba(0, 0, 0, 0.9);
        }

        #btnFiltroCores i {
            margin-left: 8px;
            transition: transform 0.3s ease;
        }

        #btnFiltroCores.aberto i {
            transform: rotate(180deg);
        }

        /* Div de filtros de cores */
        #divFiltroCores {
            position: absolute;
            bottom: 70px;
            left: 5px;
            z-index: 1000;
            background-color: rgba(0, 0, 0, 0.7);
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            padding: 15px;
            min-width: 200px;
            display: none;
        }

        #divFiltroCores h6 {
            margin: 0px 0 20px 0;
            color: white;
            font-size: 16px;
            font-weight: 600;
        }

        #divFiltroCores .form-check {
            margin-bottom: 8px;
        }

        #divFiltroCores .form-check-label {
            font-size: 13px;
            color: #333;
            cursor: pointer;
        }

        /* Checkboxes coloridos customizados */
        #divFiltroCores .form-check-input {
            width: 18px;
            height: 18px;
            border: 2px solid #000;
            border-radius: 3px;
            background-color: white;
            position: relative;
            appearance: none;
            -webkit-appearance: none;
            cursor: pointer;
            margin-top: 0;
            vertical-align: middle;
        }

        #divFiltroCores .form-check-input:checked {
            background-color: currentColor;
            border-color: white;
            border-width: 1px;
        }


        #divFiltroCores .form-check-label {
            color: white;
            vertical-align: middle;
            margin-left: 8px;
            line-height: 1.2;
        }

        /* Cores específicas para cada checkbox */
        #chkVermelho {
            color: rgb(255, 0, 0);
        }

        #chkAmarelo {
            color: rgb(255, 234, 0);
        }

        #chkLaranja {
            color: rgb(255, 102, 0);
        }

        #chkVerde {
            color: rgb(43, 160, 43);
        }

        #chkAzul {
            color: rgb(71, 204, 237);
        }

        #chkCinza {
            color: rgb(124, 124, 124);
        }

        .marker-imagem-aerea {
            width: 15px;
            height: 15px;
            cursor: pointer;
            transform: translate(0, 10px);
        }

        /* Estilos para as labels de medição */
        .measurement-label {
            background-color: white;
            padding: 4px 8px;
            border: 2px solid #333;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            color: #333;
            white-space: nowrap;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
            pointer-events: none;
        }

        .measurement-area-label {
            background-color: #4CAF50;
            color: white;
            padding: 6px 12px;
            border: 2px solid #2E7D32;
            border-radius: 6px;
            font-size: 14px;
            font-weight: bold;
            white-space: nowrap;
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.4);
            pointer-events: none;
        }

        .measurement-distance-label {
            background-color: #2196F3;
            color: white;
            padding: 6px 12px;
            border: 2px solid #1565C0;
            border-radius: 6px;
            font-size: 14px;
            font-weight: bold;
            white-space: nowrap;
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.4);
            pointer-events: none;
        }

        #dropCamadas {
            max-height: 600px;
            overflow-y: auto;
        }

        /* Estilos para accordion das camadas dinâmicas */
        .btn-accordion-toggle {
            transition: all 0.3s ease;
        }

        .btn-accordion-toggle:hover {
            color: #333 !important;
            transform: scale(1.1);
        }

        .btn-accordion-toggle i {
            transition: transform 0.3s ease;
        }

        /* Estilos para separar visualmente as camadas dinâmicas */
        #tituloCamadasDinamicas {
            background-color: #f8f9fa;
            margin-top: 5px;
        }

        /* Estilos para o slider de opacidade das camadas dinâmicas */
        #sliderOpacidadeCamadasDinamicas {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }

        #rangeOpacidadeCamadas {
            cursor: pointer;
        }

        #valorOpacidadeCamadas {
            font-weight: 600;
            color: #007bff;
        }

        /* Estilos para separar visualmente as camadas dinâmicas DXF */
        #tituloCamadasDinamicasDXF {
            background-color: #f8f9fa;
            margin-top: 5px;
        }

        /* Estilos para o slider de opacidade das camadas dinâmicas DXF */
        #sliderOpacidadeCamadasDinamicasDXF {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }

        #rangeOpacidadeCamadasDXF {
            cursor: pointer;
        }

        #valorOpacidadeCamadasDXF {
            font-weight: 600;
            color: #007bff;
        }

        /* ==================== QUADRINHO DE PESQUISA ==================== */
        #searchBox {
            position: absolute;
            top: 60px;
            left: 50%;
            transform: translateX(-50%);
            /* Centralizar horizontalmente */
            z-index: 999;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            display: inline-block;
            max-width: calc(100% - 30px);
            width: fit-content;
            /* Largura baseada no conteúdo (searchControls) */
            min-width: fit-content;
            /* Garantir que sempre seja baseado no conteúdo */
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

        /* Container da tabela de resultados */
        #searchResultsBox {
            position: absolute;
            top: 162px;
            left: 0;
            right: 0;
            z-index: 999;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            display: none;
            width: calc(100% - 250px);
            margin: 0 auto;
            max-height: calc(100vh - 180px);
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
            overflow-y: auto;
            overflow-x: hidden;
            position: relative;
            display: flex;
            flex-direction: column;
        }

        #searchResultsBody .table-responsive {
            position: relative;
            overflow-x: auto;
            overflow-y: visible;
            flex: 1;
            padding: 0;
        }

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

        /* Remover text-decoration line-through de linhas sem desenho */
        #searchResultsBody table tbody tr td {
            text-decoration: none !important;
        }

        #searchResultsBody table thead {
            position: sticky;
            top: 0;
            z-index: 150;
            background: #343a40 !important;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        #searchResultsBody table thead th {
            background: #343a40 !important;
            position: relative;
            z-index: 151;
            border-bottom: 2px solid #495057;
            white-space: nowrap;
            padding: 8px 10px;
            font-size: 11px;
        }

        #searchResultsBody table tbody td {
            background: white;
            padding: 6px 10px;
            white-space: nowrap;
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        #searchResultsPaginationBottom {
            position: sticky;
            bottom: 0;
            z-index: 200;
            background: white;
            padding: 10px 15px;
            border-top: 1px solid #dee2e6;
            box-shadow: 0 -2px 4px rgba(0, 0, 0, 0.1);
            display: none;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
    </style>
</head>

<body>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.7); z-index: 9999;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; color: white;">
            <div class="spinner-border" role="status" style="width: 4rem; height: 4rem; margin-bottom: 20px;">
                <span class="visually-hidden">Carregando...</span>
            </div>
            <h4>Salvando alterações...</h4>
            <p>Por favor, aguarde.</p>
        </div>
    </div>

    <!-- Modal personalizado de escolha de camada -->
    <div id="modalCamada" class="modal" style="display:none">
        <div class="modal-dialog">
            <div class="modal-content p-3">
                <label for="inputNumeroQuadra" class="form-label mb-1">Identificador da quadra</label>
                <input id="inputNumeroQuadra" class="form-control mb-3" placeholder="" />
                <div class="d-flex gap-2 justify-content-end">
                    <button id="btnCancelarCamada" class="btn btn-outline-secondary">Cancelar</button>
                    <button id="btnSalvarCamada" class="btn btn-primary">Salvar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Histórico -->
    <div class="modal fade" id="modalHistorico" tabindex="-1" aria-labelledby="modalHistoricoLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalHistoricoLabel">Histórico do Cadastro</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="novaLinhaHistorico" class="form-label">Adicionar nova linha ao histórico:</label>
                        <textarea id="novaLinhaHistorico" class="form-control" rows="3" placeholder="Digite o texto da nova linha..."></textarea>
                    </div>
                    <button type="button" class="btn btn-primary mb-3" id="btnAdicionarHistorico">
                        <i class="fas fa-plus"></i> Adicionar Linha
                    </button>
                    <div class="table-responsive">
                        <table class="table table-striped table-sm" id="tabelaHistorico">
                            <thead class="table-dark">
                                <tr>
                                    <th style="width: 50px;">#</th>
                                    <th>Texto</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyHistorico">
                                <!-- Linhas serão preenchidas dinamicamente -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Div flutuante de Cadastro de Loteamentos -->
    <div id="divCadastro" style="display:none">
        <div class="div-cadastro-header">
            <h6>Loteamentos da Quadrícula <span id="quadriculaAtual"></span></h6>
            <button type="button" class="btn-close-cadastro" id="btnFecharCadastro">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="div-cadastro-body">
            <div id="opcoesLoteamentos">
                <!-- Os botões radio serão criados dinamicamente aqui -->
            </div>
        </div>
    </div>

    <div id="divCadastro2" style="display:none">
        <div class="div-cadastro-header">
            <h6>Quarteirões do <span id="quarteiraoSelecionado"></span></h6>

        </div>
        <div class="div-cadastro-body">
            <div id="opcoesQuarteiroes">
                <!-- Os botões radio serão criados dinamicamente aqui -->
            </div>
        </div>
    </div>

    <div id="divCadastro3" style="display:none">
        <div class="div-cadastro-header">
            <h6>Imóveirs do Quarteirão: <span id="quarteiraoSelecionado2"></span>
                <br>
                Quantidade de Lotes: <span id="qtdLotes"></span>
            </h6>

        </div>
        <div class="div-cadastro-body">
            <div id="opcoesLotes">
                <!-- Os botões radio serão criados dinamicamente aqui -->
            </div>
        </div>
    </div>

    <!-- Aqui vai ter um controle de navegação entre as quadriculas -->
    <div id="controleNavegacaoQuadriculas">
        <!-- Grade 3x3 padrão -->
        <div id="grade3x3">
            <div>
                <div class="subDivControle">
                    <button onclick="MapFramework.navegarQuadricula(this)" data-quadricula="" id="btn_noroeste" class="controleNavegacaoQuadriculas-btn btn btn-light">NO</button>
                </div>
                <div class="subDivControle">
                    <button onclick="MapFramework.navegarQuadricula(this)" data-quadricula="" id="btn_norte" class="controleNavegacaoQuadriculas-btn btn btn-light">N</button>
                </div>
                <div class="subDivControle">
                    <button onclick="MapFramework.navegarQuadricula(this)" data-quadricula="" id="btn_nordeste" class="controleNavegacaoQuadriculas-btn btn btn-light">NE</button>
                </div>
            </div>
            <div>
                <div class="subDivControle">
                    <button onclick="MapFramework.navegarQuadricula(this)" data-quadricula="" id="btn_oeste" class="controleNavegacaoQuadriculas-btn btn btn-light">O</button>
                </div>
                <div class="subDivControle">
                    <button id="btn_centro" class="controleNavegacaoQuadriculas-btn2 btn btn-light">C</button>
                </div>
                <div class="subDivControle">
                    <button onclick="MapFramework.navegarQuadricula(this)" data-quadricula="" id="btn_leste" class="controleNavegacaoQuadriculas-btn btn btn-light">E</button>
                </div>
            </div>
            <div>
                <div class="subDivControle">
                    <button onclick="MapFramework.navegarQuadricula(this)" data-quadricula="" id="btn_sudoeste" class="controleNavegacaoQuadriculas-btn btn btn-light">SW</button>
                </div>
                <div class="subDivControle">
                    <button onclick="MapFramework.navegarQuadricula(this)" data-quadricula="" id="btn_sul" class="controleNavegacaoQuadriculas-btn btn btn-light">S</button>
                </div>
                <div class="subDivControle">
                    <button onclick="MapFramework.navegarQuadricula(this)" data-quadricula="" id="btn_sudeste" class="controleNavegacaoQuadriculas-btn btn btn-light">SE</button>
                </div>
            </div>
        </div>

        <!-- Grade expandida com todas as quadrículas -->
        <div id="gradeExpandida" style="display: none;">
            <!-- Será preenchida dinamicamente pelo JavaScript -->
        </div>

        <!-- Botão de expansão no canto inferior direito -->
        <div id="controleExpansao" style="text-align: center; margin-top: 5px;">
            <button id="btnExpandir" class="btn btn-sm btn-outline-light" style="width: 100%; font-size: 10px;">
                <i class="fas fa-expand"></i> Expandir
            </button>
        </div>
    </div>

    <!-- Tooltip para marcadores -->
    <div id="tooltipMarcador">
        <div class="tooltip-header" style="display: flex; justify-content: space-between; align-items: center;">
            <span id="tooltipNumero"></span>
            <button id="btnCloseTooltip" class="btn-close-tooltip">x</button>
        </div>
        <div class="tooltip-info" style="font-size: 11px; color: #666; margin-bottom: 8px; display: none;">
            Quadra: <span id="tooltipQuadra"></span>
        </div>
        <div class="tooltip-content">
            <button id="btnDeleteMarcador" class="btn-delete-marcador">
                <i class="fas fa-trash"></i> Deletar
            </button>
        </div>
    </div>

    <!-- Botão de filtro de cores -->
    <button id="btnFiltroCores">
        <i class="fas fa-filter"></i> Filtros
    </button>

    <!-- Div de filtros de cores -->
    <div id="divFiltroCores">
        <h6>Visualizar Imóvel</h6>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="chkVermelho" checked>
            <label class="form-check-label" for="chkVermelho">
                Checar cadastro
            </label>
        </div>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="chkAmarelo" checked>
            <label class="form-check-label" for="chkAmarelo">
                A desdobrar
            </label>
        </div>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="chkLaranja" checked>
            <label class="form-check-label" for="chkLaranja">
                A unificar
            </label>
        </div>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="chkCinza" checked>
            <label class="form-check-label" for="chkCinza">
                A cadastrar
            </label>
        </div>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="chkVerde" checked>
            <label class="form-check-label" for="chkVerde">
                Cadastrado (privado)
            </label>
        </div>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="chkAzul" checked>
            <label class="form-check-label" for="chkAzul">
                Próprios Públicos
            </label>
        </div>
    </div>

    <div class="divContainerMap">
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
            <div class="container-fluid">

                <!-- Botao voltar para o painel -->
                <button id="btnVoltarPainel" class="btn btn-light" onclick="voltarParaPainel()">Voltar para o Painel</button>

                <!-- Título -->
                <a class="navbar-brand" style="margin-left: 10px;" href="#">Visualizador</a>

                <!-- Botões -->
                <div id="divBots" class="d-flex align-items-center flex-grow-1 gap-2">

                    <!-- Dropdown Tipo de Mapa -->
                    <div class="btn-group">
                        <button id="btnTipoMapa" class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Mapa
                        </button>
                        <ul class="dropdown-menu" id="dropdownTipoMapa">
                            <li><a class="dropdown-item" href="#" data-tipo="roadmap">Mapa Claro</a></li>
                            <li><a class="dropdown-item" href="#" data-tipo="styled_map">Mapa Escuro</a></li>
                            <li><a class="dropdown-item" href="#" data-tipo="satellite">Satélite</a></li>
                        </ul>
                    </div>

                    <!-- Botão Camadas (Dropdown com Checkboxes) -->
                    <div class="btn-group">
                        <button class="btn btn-light dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                            Camadas
                        </button>
                        <ul id="dropCamadas" class="dropdown-menu p-2">
                            <!-- Checkbox da Ortofoto fixo -->

                            <li id="liFiltradoCheckbox" style="display: none;">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="chkFiltrado">
                                    <label class="form-check-label" for="chkFiltrado">
                                        Filtrado
                                    </label>
                                </div>
                            </li>
                            <li id="liFiltrado" style="display: none;">
                                <hr class="dropdown-divider">
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="chkOrtofoto" checked>
                                    <label class="form-check-label" for="chkOrtofoto">
                                        Ortofoto
                                    </label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="chkQuadras">
                                    <label class="form-check-label" for="chkQuadras">
                                        Quadras
                                    </label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="chkUnidades">
                                    <label class="form-check-label" for="chkUnidades">
                                        Edificações
                                    </label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="chkPiscinas">
                                    <label class="form-check-label" for="chkPiscinas">
                                        Piscinas
                                    </label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="new_checkLotes" checked>
                                    <label class="form-check-label" for="new_checkLotes">
                                        Lotes Prefeitura
                                    </label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="chkPoligono_lote">
                                    <label class="form-check-label" for="chkPoligono_lote">
                                        Lotes Ortofoto
                                    </label>
                                </div>
                                <!--
                                <div class="ms-3 mt-2" id="submenuLotes">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="tipoLotes" id="radioLote_todos" value="todos" checked>
                                        <label id="labelRadioLote_todos" class="form-check-label" for="radioLote_todos">
                                            Todos
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="tipoLotes" id="radioLote_impacto" value="impacto">
                                        <label id="labelRadioLote_impacto" class="form-check-label" for="radioLote_impacto">
                                            Impacto IPTU
                                        </label>
                                    </div>
                                </div>
                                -->
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="chkLotes">
                                    <label class="form-check-label" for="chkLotes">
                                        Cortes dos Lotes
                                    </label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="chkLimite" checked>
                                    <label class="form-check-label" for="chkLimite">
                                        Limite do Município
                                    </label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="chkQuadriculas" checked>
                                    <label class="form-check-label" for="chkQuadriculas">
                                        Limite das Quadriculas
                                    </label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="chkCondominiosVerticais">
                                    <label class="form-check-label" for="chkCondominiosVerticais">
                                        Condomínios Verticais
                                    </label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="chkCondominiosHorizontais">
                                    <label class="form-check-label" for="chkCondominiosHorizontais">
                                        Condomínios Horizontais
                                    </label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="chkAreasPublicas">
                                    <label class="form-check-label" for="chkAreasPublicas">
                                        Áreas Públicas
                                    </label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="chkPrefeitura">
                                    <label class="form-check-label" for="chkPrefeitura">
                                        Cartografia Prefeitura
                                    </label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="chkMarcadores">
                                    <label class="form-check-label" for="chkMarcadores">
                                        Imóveis
                                    </label>
                                </div>
                                <div class="ms-3 mt-2" id="submenuMarcadores">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="tipoMarcador" id="radioLote" value="lote" checked>
                                        <label id="labelRadioLote" class="form-check-label" for="radioLote">
                                            Número do Lote
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="tipoMarcador" id="radioPredial" value="predial">
                                        <label id="labelRadioPredial" class="form-check-label" for="radioPredial">
                                            Número Predial
                                        </label>
                                    </div>
                                </div>
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="chkModoCadastro">
                                    <label class="form-check-label" for="chkModoCadastro">
                                        Loteamentos
                                    </label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="chkQuarteiroes">
                                    <label class="form-check-label" for="chkQuarteiroes">
                                        Quarteirões
                                    </label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="chkImagensAereas">
                                    <label class="form-check-label" for="chkImagensAereas">
                                        Imagens Aéreas
                                    </label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="chkStreetview">
                                    <label class="form-check-label" for="chkStreetview">
                                        Streetview vídeos
                                    </label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="chkStreetviewFotos">
                                    <label class="form-check-label" for="chkStreetviewFotos">
                                        Streetview fotos
                                    </label>
                                </div>
                            </li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li id="tituloCamadasDinamicas" style="display: none;">
                                <div style="padding: 8px 16px; font-weight: 600; color: #6c757d; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">
                                    Camadas dos KML
                                </div>
                            </li>
                            <li id="sliderOpacidadeCamadasDinamicas" style="display: none;">
                                <div style="padding: 8px 16px;">
                                    <label for="rangeOpacidadeCamadas" class="form-label" style="font-size: 12px; font-weight: 500; color: #495057; margin-bottom: 8px; display: block;">
                                        Opacidade: <span id="valorOpacidadeCamadas">0.5</span>
                                    </label>
                                    <input type="range" class="form-range" id="rangeOpacidadeCamadas"
                                        min="0" max="2" step="0.1" value="0.5">
                                </div>
                            </li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li id="tituloCamadasDinamicasDXF" style="display: none;">
                                <div style="padding: 8px 16px; font-weight: 600; color: #6c757d; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">
                                    Camadas dos DXF
                                </div>
                            </li>
                            <li id="sliderOpacidadeCamadasDinamicasDXF" style="display: none;">
                                <div style="padding: 8px 16px;">
                                    <label for="rangeOpacidadeCamadasDXF" class="form-label" style="font-size: 12px; font-weight: 500; color: #495057; margin-bottom: 8px; display: block;">
                                        Opacidade: <span id="valorOpacidadeCamadasDXF">0.5</span>
                                    </label>
                                    <input type="range" class="form-range" id="rangeOpacidadeCamadasDXF"
                                        min="0" max="2" step="0.1" value="0.5">
                                </div>
                            </li>
                        </ul>
                    </div>

                    <!--
                    <button id="btnIncluirPoligono" class="btn btn-primary">Quadra</button>
                    <button id="btnIncluirLinha" class="btn btn-success">Lote</button>-->

                    <!-- Botão para finalizar desenho (aparece quando está em modo de desenho) -->
                    <!--<button id="btnFinalizarDesenho" class="btn btn-secondary d-none">Sair do modo desenho</button>-->

                    <!-- Botão específico para sair do modo inserção de marcadores -->
                    <!--<button id="btnSairModoMarcador" class="btn btn-secondary d-none">Sair do modo marcador</button>-->

                    <!-- Botões condicionais (aparecem se há seleção) -->
                    <!--<button id="btnEditar" class="btn btn-warning d-none">Editar</button>-->
                    <!--<button id="btnExcluir" class="btn btn-danger d-none">Excluir</button>-->

                    <!-- Botão Sair da Edição (aparece quando está em modo de edição) -->
                    <!--<button id="btnSairEdicao" class="btn btn-secondary d-none">Sair da Edição</button>-->

                    <div class="divControle">
                        <input min="0" max="1" step="0.1" type="range" class="form-range" id="customRange1" value="0.3">
                    </div>

                    <!-- Botão de Navegação para Quadrícula Selecionada -->
                    <button class="btn btn-warning" id="btnIrConsulta" onclick="irConsulta()">
                        <i class="fas fa-external-link-alt"></i> Consultas
                    </button>

                    <!-- Botão Pesquisa (TEMPORÁRIO) 
                    <button class="btn btn-info" id="btnPesquisa">
                        <i class="fas fa-search"></i> Pesquisa
                    </button>-->

                    <!-- Botão Sair do Modo Pesquisa (TEMPORÁRIO - aparece quando em modo pesquisa) 
                    <button class="btn btn-secondary d-none" id="btnSairModoPesquisa">
                        <i class="fas fa-times"></i> Sair do Modo Pesquisa
                    </button>-->

                    <!-- Botão para editar trajetos de Streetview Fotos 
                    <button class="btn btn-info d-none" id="btnEditarStreetviewFotos">
                        <i class="fas fa-edit"></i> Corrigir Trajeto
                    </button>-->

                    <!-- Botão Crop -->
                    <button id="btnModoCrop" class="btn btn-danger">
                        <i class="fas fa-crop"></i> Crop
                    </button>

                    <!-- Botão Régua -->
                    <div class="btn-group">
                        <button class="btn btn-light dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-ruler-combined"></i> Régua
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" onclick="iniciarMedicaoArea(); event.preventDefault();">
                                    <i class="fas fa-draw-polygon"></i> Medir Área
                                </a></li>
                            <li><a class="dropdown-item" href="#" onclick="iniciarMedicaoDistancia(); event.preventDefault();">
                                    <i class="fas fa-ruler"></i> Medir Distância
                                </a></li>
                            <li><a class="dropdown-item" href="#" onclick="iniciarMedicaoCirculo(); event.preventDefault();">
                                    <i class="fas fa-circle"></i> Medir Círculo
                                </a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item text-warning d-none" href="#" id="btnLimparMedicoes" onclick="limparTodasMedicoes(); event.preventDefault();">
                                    <i class="fas fa-trash"></i> Limpar Todas as Medições
                                </a></li>
                            <li><a class="dropdown-item text-danger d-none" href="#" id="btnCancelarMedicao" onclick="cancelarMedicao(); event.preventDefault();">
                                    <i class="fas fa-times"></i> Sair do Modo Régua
                                </a></li>
                        </ul>
                    </div>

                    <!-- Botão Mapa Externo -->
                    <div class="btn-group ms-2">
                        <button class="btn btn-light dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-globe-americas"></i> Externo
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" onclick="abrirLocalExterno('earth_web'); event.preventDefault();">
                                    <i class="fas fa-globe"></i> Google Earth Web
                                </a></li>
                            <li><a class="dropdown-item" href="#" onclick="abrirLocalExterno('maps'); event.preventDefault();">
                                    <i class="fas fa-map-marked-alt"></i> Google Maps
                                </a></li>
                        </ul>
                    </div>

                    <!--<button data-loteamento="" data-arquivos="" data-quadricula="" onclick="desenharNoPDF(this)" id="btnLerPDF" class="btn btn-warning d-none">Desenhar no PDF</button>-->

                    <!-- Botões Cadastro removidos - agora é uma camada no dropdown -->
                    <!-- <button id="btnCadastro" class="btn btn-info">Cadastro</button> -->

                    <!-- Botão Sair do Cadastro (aparece quando entra no modo cadastro) -->
                    <!-- <button id="btnSairCadastro" class="btn btn-secondary d-none">Sair do Cadastro</button> -->

                    <!-- Botão Marcador e inputs text -->
                    <!--<button id="btnIncluirMarcador" class="btn btn-danger d-none">Marcador</button>-->
                    <!--<input type="text" id="inputLoteAtual" class="form-control" style="width: 80px; display: none;" placeholder="Lote">
                    <input type="text" id="inputQuadraAtual" class="form-control" style="width: 80px; display: none;" placeholder="Quadra">-->

                </div>

                <!-- Botão Sair -->
                <div class="d-flex">
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
            <button class="btn btn-light" id="btnToggleResults" style="display: none;"></button>
        </div>

        <!-- Quadrinho de Resultados (Tabela) -->
        <div id="searchResultsBox">
            <div id="searchResultsHeader">
                <span id="searchResultsTitle">
                    <i class="fas fa-table"></i> Resultados da Pesquisa
                    <span id="resultsCount" class="badge bg-primary ms-2">0 registros</span>
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

    <script>
        const paginaAtual = 'index_3';
        const userControl = <?php echo json_encode($_SESSION['usuario'][2]); ?>;

        // =================== SISTEMA DE PESQUISA (SEM DESENHOS) ===================

        // Opções de pesquisa (ordenadas alfabeticamente)
        const searchOptions = [{
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

        // Variáveis globais de paginação
        let currentPage = 1;
        let currentLimit = 50;
        let totalPages = 1;
        let totalResults = 0;
        let currentSearchPayload = null;

        // Função para sincronizar largura do botão toggle com searchControls
        function syncToggleButtonWidth() {
            const btnToggleResults = document.getElementById('btnToggleResults');
            const searchControls = document.getElementById('searchControls');
            if (btnToggleResults && searchControls) {
                requestAnimationFrame(() => {
                    const controlsWidth = searchControls.offsetWidth;
                    btnToggleResults.style.width = controlsWidth + 'px';
                });
            }
        }

        // Event listener para o seletor de resultados por página
        document.addEventListener('DOMContentLoaded', function() {
            const resultsPerPageSelect = document.getElementById('resultsPerPage');
            if (resultsPerPageSelect) {
                resultsPerPageSelect.addEventListener('change', function() {
                    const newLimit = parseInt(this.value);
                    if (newLimit !== currentLimit) {
                        currentLimit = newLimit;
                        const startItem = (currentPage - 1) * currentLimit + 1;
                        const newPage = Math.max(1, Math.ceil(startItem / currentLimit));
                        currentPage = newPage;
                        if (currentSearchPayload) {
                            loadPage(currentPage);
                        }
                    }
                });
            }

            // Inicializar pesquisa
            const select = document.getElementById('searchType');
            const btnPesquisar = document.getElementById('btnPesquisar');

            // Preencher select
            if (select) {
                searchOptions.forEach((opt, index) => {
                    const option = document.createElement('option');
                    option.value = opt.value;
                    option.textContent = opt.label;
                    select.appendChild(option);
                    
                    // Adicionar separador após "Cadastros não desenhados" (primeira opção)
                    if (index === 0 && opt.value === 'cadastros_nao_desenhados') {
                        const separator = document.createElement('option');
                        separator.disabled = true;
                        separator.textContent = '─────────────────────────';
                        select.appendChild(separator);
                    }
                });
            }

            // Sincronizar largura do botão toggle
            syncToggleButtonWidth();

            // Observar mudanças no tamanho do searchControls
            const resizeObserver = new ResizeObserver(() => {
                syncToggleButtonWidth();
            });
            const searchControls = document.getElementById('searchControls');
            if (searchControls) {
                resizeObserver.observe(searchControls);
            }

            // Troca de tipo de pesquisa
            if (select) {
                select.addEventListener('change', () => {
                    const searchBox = document.getElementById('searchBox');
                    if (searchBox) {
                        searchBox.style.width = '';
                    }
                    buildSearchInputs(select.value);
                    setTimeout(syncToggleButtonWidth, 0);
                });
            }

            // Botão pesquisar
            if (btnPesquisar) {
                btnPesquisar.addEventListener('click', async () => {
                    const payload = collectSearchData();
                    if (!payload.type) {
                        alert('Selecione um tipo de pesquisa.');
                        return;
                    }

                    currentPage = 1;
                    currentSearchPayload = payload;

                    btnPesquisar.classList.add('loading');
                    btnPesquisar.disabled = true;

                    payload.page = currentPage;
                    payload.limit = currentLimit;

                    try {
                        const response = await fetch('index_3.php?pesquisar=1', {
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
                            displaySearchResults(result.dados, result.total, result.page, result.totalPages);
                        }
                    } catch (err) {
                        console.error('Erro ao realizar pesquisa:', err);
                        alert('Erro ao realizar pesquisa.');
                    } finally {
                        btnPesquisar.classList.remove('loading');
                        btnPesquisar.disabled = false;
                    }
                });
            }

            // Botão para mostrar/ocultar resultados
            const btnToggleResults = document.getElementById('btnToggleResults');
            if (btnToggleResults && !btnToggleResults.dataset.listenerAdded) {
                btnToggleResults.dataset.listenerAdded = 'true';
                const searchResultsBox = document.getElementById('searchResultsBox');

                btnToggleResults.addEventListener('click', () => {
                    const isVisible = searchResultsBox.classList.contains('visible');
                    if (isVisible) {
                        searchResultsBox.classList.remove('visible');
                        btnToggleResults.innerHTML = '<i class="fas fa-chevron-down"></i>';
                    } else {
                        searchResultsBox.classList.add('visible');
                        btnToggleResults.innerHTML = '<i class="fas fa-chevron-up"></i>';
                    }
                    syncToggleButtonWidth();
                });
            }

            // Carregar última pesquisa
            loadLastSearch();
        });

        // Função para carregar página
        async function loadPage(page) {
            if (!currentSearchPayload) return;

            const payload = {
                ...currentSearchPayload,
                page: page,
                limit: currentLimit
            };

            const btnPesquisar = document.getElementById('btnPesquisar');
            if (btnPesquisar) {
                btnPesquisar.classList.add('loading');
                btnPesquisar.disabled = true;
            }

            try {
                const response = await fetch('index_3.php?pesquisar=1', {
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
                    displaySearchResults(result.dados, result.total, result.page, result.totalPages);
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

        function buildSearchInputs(selectedValue, savedValues = {}) {
            const container = document.getElementById('searchInputs');
            if (!container) return;
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
            const select = document.getElementById('searchType');
            if (!select) return {
                type: '',
                values: {}
            };

            const type = select.value;
            const inputs = document.querySelectorAll('#searchInputs input');
            const values = {};
            inputs.forEach(inp => values[inp.name] = inp.value);
            return {
                type,
                values
            };
        }

        async function loadLastSearch() {
            try {
                const res = await fetch('index_3.php?searchApi=1');
                if (!res.ok) return;
                const data = await res.json();
                if (!data || !data.type) return;

                const select = document.getElementById('searchType');
                if (select) {
                    select.value = data.type;
                    buildSearchInputs(data.type, data.values || {});
                }
            } catch (err) {
                console.error('Erro ao carregar última pesquisa:', err);
            }
        }

        // Função para exibir resultados da pesquisa (SEM desenhos)
        // Variáveis para controle de ordenação
        let sortColumnIndex3 = null;
        let sortDirectionIndex3 = 'asc'; // 'asc' ou 'desc'
        let dadosOrdenaveisIndex3 = null; // Armazenar dados originais para ordenação
        let columnArrayGlobalIndex3 = []; // Array de colunas para uso na ordenação
        let columnNamesGlobalIndex3 = {}; // Mapeamento de nomes de colunas

        function displaySearchResults(dados, total, page = 1, totalPages = 1) {
            const resultsTableHead = document.getElementById('resultsTableHead');
            const resultsTableBody = document.getElementById('resultsTableBody');
            const resultsCount = document.getElementById('resultsCount');
            const searchResultsBox = document.getElementById('searchResultsBox');
            const btnToggleResults = document.getElementById('btnToggleResults');
            
            // Armazenar dados originais para ordenação
            dadosOrdenaveisIndex3 = dados;
            const searchResultsPaginationBottom = document.getElementById('searchResultsPaginationBottom');
            const paginationInfoBottom = document.getElementById('paginationInfoBottom');
            const paginationControlsBottom = document.getElementById('paginationControlsBottom');

            // Mostrar botão toggle e definir ícone inicial
            if (btnToggleResults) {
                syncToggleButtonWidth();
                setTimeout(() => {
                    btnToggleResults.style.display = 'block';
                    // Quando a tabela aparece, ela está visível, então o ícone deve apontar para cima
                    btnToggleResults.innerHTML = '<i class="fas fa-chevron-up"></i>';
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
                    resultsCount.textContent = '0 registros';
                }
                if (searchResultsPaginationBottom) {
                    searchResultsPaginationBottom.style.display = 'none';
                }
                if (searchResultsBox) {
                    searchResultsBox.classList.add('visible');
                    // Atualizar ícone do botão toggle
                    if (btnToggleResults) {
                        btnToggleResults.innerHTML = '<i class="fas fa-chevron-up"></i>';
                    }
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
                'historico',
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
                'historico': 'Histórico',
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
            
            // Armazenar para uso na ordenação
            columnArrayGlobalIndex3 = columnArray;
            columnNamesGlobalIndex3 = columnNames;

            // Criar cabeçalho
            let headerHTML = '<tr>';
            columnArray.forEach(col => {
                const headerName = columnNames[col] || col;
                // Adicionar indicador de ordenação se a coluna estiver ordenada
                let sortIndicator = '';
                if (sortColumnIndex3 === col) {
                    sortIndicator = sortDirectionIndex3 === 'asc' ? ' <i class="fas fa-sort-up"></i>' : ' <i class="fas fa-sort-down"></i>';
                } else {
                    sortIndicator = ' <i class="fas fa-sort" style="opacity: 0.3;"></i>';
                }
                headerHTML += `<th class="sortable-header" data-column="${col}" style="cursor: pointer; user-select: none;">${headerName}${sortIndicator}</th>`;
            });
            headerHTML += '</tr>';
            if (resultsTableHead) {
                resultsTableHead.innerHTML = headerHTML;
            }
            
            // Adicionar event listeners para ordenação
            document.querySelectorAll('.sortable-header').forEach(header => {
                header.addEventListener('click', function() {
                    const column = this.getAttribute('data-column');
                    ordenarTabelaIndex3(column);
                });
            });

            // Criar corpo
            let bodyHTML = '';
            dados.forEach((row) => {
                const imobId = row.imob_id || '';
                bodyHTML += `<tr data-imob-id="${imobId}">`;
                columnArray.forEach(col => {
                    const value = row[col] !== null && row[col] !== undefined ? row[col] : '';
                    // Se for a coluna historico, criar link clicável
                    if (col === 'historico') {
                        bodyHTML += `<td title="${value}"><a href="#" class="historico-link" data-imob-id="${imobId}" style="color: #007bff; text-decoration: underline; cursor: pointer;">${value || '[vazio]'}</a></td>`;
                    } else {
                        bodyHTML += `<td title="${value}">${value}</td>`;
                    }
                });
                bodyHTML += '</tr>';
            });
            if (resultsTableBody) {
                resultsTableBody.innerHTML = bodyHTML;
            }

            // Atualizar contador
            if (resultsCount) {
                resultsCount.textContent = total.toLocaleString('pt-BR') + ' registros';
            }

            // Atualizar informações de paginação
            const start = (page - 1) * currentLimit + 1;
            const end = Math.min(start + dados.length - 1, total);
            if (paginationInfoBottom) {
                paginationInfoBottom.textContent = `Mostrando ${start.toLocaleString('pt-BR')} a ${end.toLocaleString('pt-BR')} de ${total.toLocaleString('pt-BR')} resultados`;
            }

            // Criar controles de paginação
            if (paginationControlsBottom) {
                let paginationHTML = '';

                // Botão primeira página
                paginationHTML += `<li class="page-item ${page === 1 ? 'disabled' : ''}"><a class="page-link" href="#" data-page="1">Primeira</a></li>`;

                // Botão anterior
                paginationHTML += `<li class="page-item ${page === 1 ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${page - 1}">Anterior</a></li>`;

                // Números de página
                const maxVisible = 5;
                let startPage = Math.max(1, page - Math.floor(maxVisible / 2));
                let endPage = Math.min(totalPages, startPage + maxVisible - 1);
                if (endPage - startPage < maxVisible - 1) {
                    startPage = Math.max(1, endPage - maxVisible + 1);
                }

                for (let i = startPage; i <= endPage; i++) {
                    paginationHTML += `<li class="page-item ${i === page ? 'active' : ''}"><a class="page-link" href="#" data-page="${i}">${i}</a></li>`;
                }

                // Botão próximo
                paginationHTML += `<li class="page-item ${page === totalPages ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${page + 1}">Próxima</a></li>`;

                // Botão última página
                paginationHTML += `<li class="page-item ${page === totalPages ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${totalPages}">Última</a></li>`;

                paginationControlsBottom.innerHTML = paginationHTML;

                // Adicionar event listeners aos links de paginação
                paginationControlsBottom.querySelectorAll('a.page-link').forEach(link => {
                    link.addEventListener('click', function(e) {
                        e.preventDefault();
                        const pageNum = parseInt(this.getAttribute('data-page'));
                        if (pageNum && pageNum !== page && pageNum >= 1 && pageNum <= totalPages) {
                            loadPage(pageNum);
                            const searchResultsBody = document.getElementById('searchResultsBody');
                            if (searchResultsBody) {
                                searchResultsBody.scrollTop = 0;
                            }
                        }
                    });
                });
            }

            // Mostrar paginação
            if (searchResultsPaginationBottom && totalPages > 1) {
                searchResultsPaginationBottom.style.display = 'flex';
            } else if (searchResultsPaginationBottom) {
                searchResultsPaginationBottom.style.display = 'none';
            }

            // Mostrar tabela
            if (searchResultsBox) {
                searchResultsBox.classList.add('visible');
                // Atualizar ícone do botão toggle quando a tabela é mostrada
                if (btnToggleResults) {
                    btnToggleResults.innerHTML = '<i class="fas fa-chevron-up"></i>';
                }
            }
        }
        const nomeUsuario = <?php echo json_encode($_SESSION['usuario'][0] ?? ''); ?>;

        function criaBotAdm() {

            let botAdm = document.createElement('button');
            botAdm.className = 'btn btn-primary';
            botAdm.innerHTML = 'Desenhar';
            botAdm.onclick = function() {
                window.location.href = `index_2.php?quadricula=${dadosOrto[0]['quadricula']}`;
            };

            if (userControl == 'true') {
                document.getElementById('divBots').appendChild(botAdm);
            }

        }

        function voltarParaPainel() {
            window.location.href = `painel.php`;
        }

        function irConsulta() {
            window.location.href = `consultas`;
        }

        function abrirLocalExterno(tipo) {
            if (typeof MapFramework === 'undefined' || !MapFramework.map) {
                console.warn('MapFramework.map não está disponível.');
                return;
            }

            const centro = MapFramework.map.getCenter();
            const zoomMap = MapFramework.map.getZoom();

            if (!centro || zoomMap === null || zoomMap === undefined) {
                console.warn('Centro ou zoom do mapa indisponíveis.');
                return;
            }

            const latValor = centro.lat();
            const lngValor = centro.lng();
            const lat = latValor.toFixed(8);
            const lng = lngValor.toFixed(8);
            const altitudeBase = converterZoomParaAltitude(zoomMap, latValor);
            const {
                altitude: altitudeEarthWeb,
                range: rangeEarthWeb
            } = calcularParametrosEarthWeb(zoomMap, latValor);
            const altitudePro = Math.max(50, Math.round(altitudeBase));

            let url = '';

            switch (tipo) {
                case 'earth_web':
                    url = `https://earth.google.com/web/@${lat},${lng},${altitudeEarthWeb}a,${rangeEarthWeb}d,1y,0h,0t,0r`;
                    break;
                case 'earth_pro':
                    url = `googleearth://?ll=${lat},${lng}&z=${altitudePro}`;
                    break;
                case 'maps':
                    url = `https://www.google.com/maps/@${lat},${lng},${zoomMap}z`;
                    break;
                default:
                    console.warn(`Tipo de destino desconhecido: ${tipo}`);
                    return;
            }

            window.open(url, '_blank', 'noopener');
        }

        function converterZoomParaAltitude(zoom, latitude) {
            const radLat = latitude * Math.PI / 180;
            const metrosPorPixel = 156543.03392 * Math.cos(radLat) / Math.pow(2, zoom);
            const altitude = metrosPorPixel * 256;
            return Math.max(50, Math.round(altitude));
        }

        function calcularParametrosEarthWeb(zoom, latitude) {
            // Valores testados e validados pelo usuário
            // A altitude (parâmetro 'a') é sempre ~595 para essa localização
            const altitudeFixa = 594.81286735;

            const zoomRangeMap = {
                21: 3405.18688813,
                20: 7353.01885773,
                19: 15595.86320988,
                18: 32516.31324371
            };

            // Se o zoom está na tabela, usa o valor exato
            if (zoomRangeMap[zoom] !== undefined) {
                return {
                    altitude: altitudeFixa,
                    range: zoomRangeMap[zoom]
                };
            }

            // Para zooms fora da tabela, calcula usando interpolação exponencial
            // Fator aproximado: ~2.12x por nível de zoom (média dos fatores calculados)
            // Zoom 20→21: 7353/3405 = 2.159
            // Zoom 19→20: 15596/7353 = 2.121  
            // Zoom 18→19: 32516/15596 = 2.085
            const referenceZoom = 21;
            const baseRange = 3405.18688813;
            const fatorMultiplicacao = 2.121; // Média dos fatores entre os zooms testados
            const exponent = referenceZoom - zoom;
            const rangeCalculado = baseRange * Math.pow(fatorMultiplicacao, exponent);

            // Garante valores mínimos e máximos razoáveis
            const range = Math.max(100, Math.min(rangeCalculado, 500000));

            return {
                altitude: altitudeFixa,
                range
            };
        }

        // Sistema de medição de régua
        const Medicao = {
            ativa: false,
            tipo: null, // 'area', 'distancia' ou 'circulo'
            pontos: [],
            poligono: null,
            polilinha: null,
            circulo: null,
            centroCirculo: null,
            labels: [],
            medicoesSalvas: [], // Array para armazenar todas as medições
            listenerClick: null,
            listenerRightClick: null,
            listenerMouseMove: null,
            linhaTemporaria: null,
            labelTemporaria: null,

            limpar: function() {
                // Remove polígono/polilinha/círculo temporários
                if (this.poligono) {
                    this.poligono.setMap(null);
                    this.poligono = null;
                }
                if (this.polilinha) {
                    this.polilinha.setMap(null);
                    this.polilinha = null;
                }
                if (this.circulo) {
                    this.circulo.setMap(null);
                    this.circulo = null;
                }
                if (this.linhaTemporaria) {
                    this.linhaTemporaria.setMap(null);
                    this.linhaTemporaria = null;
                }
                if (this.labelTemporaria) {
                    this.labelTemporaria.setMap(null);
                    this.labelTemporaria = null;
                }
                this.centroCirculo = null;

                // Remove labels temporárias
                this.labels.forEach(l => l.setMap(null));
                this.labels = [];

                // Remove todas as medições salvas
                this.medicoesSalvas.forEach(medicao => {
                    if (medicao.objeto) {
                        medicao.objeto.setMap(null);
                    }
                    if (medicao.labels) {
                        medicao.labels.forEach(l => l.setMap(null));
                    }
                });
                this.medicoesSalvas = [];

                // Remove listeners
                if (this.listenerClick) {
                    google.maps.event.removeListener(this.listenerClick);
                    this.listenerClick = null;
                }
                if (this.listenerRightClick) {
                    google.maps.event.removeListener(this.listenerRightClick);
                    this.listenerRightClick = null;
                }
                if (this.listenerMouseMove) {
                    google.maps.event.removeListener(this.listenerMouseMove);
                    this.listenerMouseMove = null;
                }

                this.pontos = [];
                this.ativa = false;
                this.tipo = null;
                MapFramework.map.setOptions({
                    draggableCursor: 'default'
                });

                // Reabilita interatividade de todos os objetos do mapa
                if (MapFramework && MapFramework.atualizarInteratividadeObjetos) {
                    MapFramework.atualizarInteratividadeObjetos(true);
                }
            },

            limparMedicaoAtual: function() {
                // Remove apenas a medição atual (temporária)
                if (this.poligono) {
                    this.poligono.setMap(null);
                    this.poligono = null;
                }
                if (this.polilinha) {
                    this.polilinha.setMap(null);
                    this.polilinha = null;
                }
                if (this.circulo) {
                    this.circulo.setMap(null);
                    this.circulo = null;
                }
                if (this.linhaTemporaria) {
                    this.linhaTemporaria.setMap(null);
                    this.linhaTemporaria = null;
                }
                if (this.labelTemporaria) {
                    this.labelTemporaria.setMap(null);
                    this.labelTemporaria = null;
                }

                // Remove labels temporárias
                this.labels.forEach(l => l.setMap(null));
                this.labels = [];

                this.pontos = [];
                this.centroCirculo = null;
            },

            calcularDistancia: function(ponto1, ponto2) {
                // Usa a biblioteca Turf.js para calcular distância em metros
                const from = turf.point([ponto1.lng(), ponto1.lat()]);
                const to = turf.point([ponto2.lng(), ponto2.lat()]);
                return turf.distance(from, to, {
                    units: 'kilometers'
                }) * 1000; // Converte km para metros
            },

            formatarDistancia: function(metros) {
                if (metros >= 1000) {
                    return (metros / 1000).toFixed(1) + ' km';
                }
                return metros.toFixed(1) + ' m';
            },

            formatarArea: function(metrosQuadrados) {
                if (metrosQuadrados >= 10000) {
                    return (metrosQuadrados / 10000).toFixed(1) + ' ha';
                }
                return metrosQuadrados.toFixed(1) + ' m²';
            },

            adicionarLabelAresta: function(ponto1, ponto2, distancia) {
                // Calcula o ponto médio entre os dois pontos
                const lat = (ponto1.lat() + ponto2.lat()) / 2;
                const lng = (ponto1.lng() + ponto2.lng()) / 2;

                // Aplica um pequeno offset vertical (sobe a label)
                const offsetVertical = 0.0000015; // Aproximadamente 1.5 metros para cima
                const posicao = new google.maps.LatLng(lat + offsetVertical, lng);

                // Cria elemento HTML para a label
                const el = document.createElement('div');
                el.className = 'measurement-label';
                el.textContent = this.formatarDistancia(distancia);

                // Cria marcador avançado para a label
                const label = new google.maps.marker.AdvancedMarkerElement({
                    position: posicao,
                    content: el,
                    map: MapFramework.map,
                    zIndex: 1000,
                    gmpClickable: false
                });

                this.labels.push(label);
            },

            adicionarLabelCentral: function(texto, posicao, classe) {
                const el = document.createElement('div');
                el.className = classe;
                el.textContent = texto;

                const label = new google.maps.marker.AdvancedMarkerElement({
                    position: posicao,
                    content: el,
                    map: MapFramework.map,
                    zIndex: 1001,
                    gmpClickable: false
                });

                this.labels.push(label);
            },

            calcularCentroide: function(pontos) {
                if (pontos.length === 0) return null;

                const coords = pontos.map(p => [p.lng(), p.lat()]);
                coords.push([pontos[0].lng(), pontos[0].lat()]); // Fecha o polígono

                const polygon = turf.polygon([coords]);
                const centroid = turf.centroid(polygon);

                return new google.maps.LatLng(
                    centroid.geometry.coordinates[1],
                    centroid.geometry.coordinates[0]
                );
            },

            atualizarDesenho: function() {
                if (this.tipo === 'area') {
                    this.atualizarPoligono();
                } else if (this.tipo === 'distancia') {
                    this.atualizarPolilinha();
                } else if (this.tipo === 'circulo') {
                    this.atualizarCirculo();
                }
            },

            atualizarPoligono: function() {
                // Remove labels antigas
                this.labels.forEach(l => l.setMap(null));
                this.labels = [];

                if (this.pontos.length < 2) return;

                // Atualiza ou cria polígono
                if (!this.poligono) {
                    this.poligono = new google.maps.Polygon({
                        paths: this.pontos,
                        strokeColor: '#FF0000',
                        strokeOpacity: 0.8,
                        strokeWeight: 2,
                        fillColor: '#FF0000',
                        fillOpacity: 0.2,
                        map: MapFramework.map,
                        zIndex: 999,
                        editable: false,
                        draggable: false,
                        clickable: false
                    });
                } else {
                    this.poligono.setPath(this.pontos);
                }

                // Adiciona labels nas arestas
                for (let i = 0; i < this.pontos.length; i++) {
                    const proximoIndice = (i + 1) % this.pontos.length;
                    const distancia = this.calcularDistancia(this.pontos[i], this.pontos[proximoIndice]);
                    this.adicionarLabelAresta(this.pontos[i], this.pontos[proximoIndice], distancia);
                }

                // Se tiver pelo menos 3 pontos, calcula a área
                if (this.pontos.length >= 3) {
                    const coords = this.pontos.map(p => [p.lng(), p.lat()]);
                    coords.push([this.pontos[0].lng(), this.pontos[0].lat()]); // Fecha o polígono

                    const polygon = turf.polygon([coords]);
                    const area = turf.area(polygon);

                    const centroide = this.calcularCentroide(this.pontos);
                    this.adicionarLabelCentral(area.toFixed(1) + ' m²', centroide, 'measurement-area-label');
                }
            },

            atualizarPolilinha: function() {
                // Remove labels antigas
                this.labels.forEach(l => l.setMap(null));
                this.labels = [];

                if (this.pontos.length < 1) return;

                // Atualiza ou cria polilinha
                if (!this.polilinha) {
                    this.polilinha = new google.maps.Polyline({
                        path: this.pontos,
                        strokeColor: '#0000FF',
                        strokeOpacity: 0.8,
                        strokeWeight: 3,
                        map: MapFramework.map,
                        zIndex: 999,
                        editable: false,
                        draggable: false,
                        clickable: false
                    });
                } else {
                    this.polilinha.setPath(this.pontos);
                }

                // Adiciona labels nas arestas e calcula distância total
                let distanciaTotal = 0;
                for (let i = 0; i < this.pontos.length - 1; i++) {
                    const distancia = this.calcularDistancia(this.pontos[i], this.pontos[i + 1]);
                    distanciaTotal += distancia;
                    this.adicionarLabelAresta(this.pontos[i], this.pontos[i + 1], distancia);
                }

                // Adiciona label com distância total no último ponto
                if (this.pontos.length >= 2) {
                    const ultimoPonto = this.pontos[this.pontos.length - 1];
                    this.adicionarLabelCentral(
                        'Total: ' + this.formatarDistancia(distanciaTotal),
                        ultimoPonto,
                        'measurement-distance-label'
                    );
                }
            },

            iniciar: function(tipo) {
                // Se já está ativo, limpa apenas a medição atual (não as salvas)
                if (this.ativa) {
                    this.limparMedicaoAtual();
                } else {
                    // Se está iniciando pela primeira vez, limpa tudo
                    this.limpar();
                }

                // Remove listeners antigos antes de criar novos
                if (this.listenerClick) {
                    google.maps.event.removeListener(this.listenerClick);
                    this.listenerClick = null;
                }
                if (this.listenerRightClick) {
                    google.maps.event.removeListener(this.listenerRightClick);
                    this.listenerRightClick = null;
                }
                if (this.listenerMouseMove) {
                    google.maps.event.removeListener(this.listenerMouseMove);
                    this.listenerMouseMove = null;
                }

                this.ativa = true;
                this.tipo = tipo;
                MapFramework.map.setOptions({
                    draggableCursor: 'crosshair'
                });

                // Desabilita interatividade de todos os objetos do mapa
                MapFramework.atualizarInteratividadeObjetos(false);

                // Listener para adicionar pontos
                this.listenerClick = MapFramework.map.addListener('click', (e) => {
                    if (this.tipo === 'circulo') {
                        if (this.pontos.length === 0) {
                            // Primeiro clique: define o centro
                            this.centroCirculo = e.latLng;
                            this.pontos.push(e.latLng);
                        } else if (this.pontos.length === 1) {
                            // Segundo clique: define o raio e finaliza
                            this.pontos.push(e.latLng);
                            this.atualizarDesenho();
                            this.finalizar();
                        }
                    } else {
                        this.pontos.push(e.latLng);
                        this.atualizarDesenho();
                    }
                });

                // Listener para finalizar (botão direito)
                this.listenerRightClick = MapFramework.map.addListener('rightclick', (e) => {
                    if (this.tipo === 'area' && this.pontos.length < 3) {
                        alert('É necessário pelo menos 3 pontos para criar uma área.');
                        return;
                    }
                    if (this.tipo === 'distancia' && this.pontos.length < 2) {
                        alert('É necessário pelo menos 2 pontos para medir distância.');
                        return;
                    }

                    // Finaliza a medição
                    this.finalizar();
                });

                // Listener para mostrar linha temporária (preview)
                this.listenerMouseMove = MapFramework.map.addListener('mousemove', (e) => {
                    if (this.pontos.length === 0) return;

                    // Remove temporários anteriores
                    if (this.linhaTemporaria) {
                        this.linhaTemporaria.setMap(null);
                    }
                    if (this.labelTemporaria) {
                        this.labelTemporaria.setMap(null);
                    }

                    const ultimoPonto = this.pontos[this.pontos.length - 1];

                    // Preview para círculo
                    if (this.tipo === 'circulo' && this.centroCirculo) {
                        const raioAtual = this.calcularDistancia(this.centroCirculo, e.latLng);

                        // Círculo temporário
                        if (this.circulo) {
                            this.circulo.setMap(null);
                        }
                        this.circulo = new google.maps.Circle({
                            center: this.centroCirculo,
                            radius: raioAtual,
                            strokeColor: '#FF0000',
                            strokeOpacity: 0.5,
                            strokeWeight: 2,
                            fillColor: '#FF0000',
                            fillOpacity: 0.1,
                            map: MapFramework.map,
                            zIndex: 998,
                            clickable: false
                        });

                        // Label temporária com raio e área
                        const lat = (this.centroCirculo.lat() + e.latLng.lat()) / 2;
                        const lng = (this.centroCirculo.lng() + e.latLng.lng()) / 2;
                        const offsetVertical = 0.0000015;
                        const posicao = new google.maps.LatLng(lat + offsetVertical, lng);

                        const area = Math.PI * raioAtual * raioAtual;
                        const el = document.createElement('div');
                        el.className = 'measurement-label';
                        el.style.opacity = '0.8';
                        el.innerHTML = 'Raio: ' + this.formatarDistancia(raioAtual) + '<br>Área: ' + area.toFixed(1) + ' m²';

                        this.labelTemporaria = new google.maps.marker.AdvancedMarkerElement({
                            position: posicao,
                            content: el,
                            map: MapFramework.map,
                            zIndex: 1000,
                            gmpClickable: false
                        });
                    } else {
                        // Preview para polígono/polilinha
                        this.linhaTemporaria = new google.maps.Polyline({
                            path: [ultimoPonto, e.latLng],
                            strokeColor: this.tipo === 'area' ? '#FF0000' : '#0000FF',
                            strokeOpacity: 0.5,
                            strokeWeight: 2,
                            map: MapFramework.map,
                            zIndex: 998,
                            clickable: false
                        });
                    }
                });
            },

            finalizar: function() {
                // Remove linha temporária
                if (this.linhaTemporaria) {
                    this.linhaTemporaria.setMap(null);
                    this.linhaTemporaria = null;
                }
                if (this.labelTemporaria) {
                    this.labelTemporaria.setMap(null);
                    this.labelTemporaria = null;
                }

                // Salva a medição atual antes de limpar
                const medicaoSalva = {
                    tipo: this.tipo,
                    objeto: null,
                    labels: []
                };

                // Torna o polígono/polilinha/círculo editável
                if (this.poligono) {
                    this.poligono.setOptions({
                        editable: true,
                        draggable: false,
                        clickable: false
                    });

                    // Recria as labels uma vez
                    this.atualizarMedidasPoligono();
                    medicaoSalva.labels = [...this.labels];
                    medicaoSalva.objeto = this.poligono;

                    const poligonoSalvo = this.poligono;
                    const medicaoRef = medicaoSalva;
                    const path = this.poligono.getPath();
                    google.maps.event.addListener(path, 'set_at', () => {
                        this.atualizarMedidasObjetoSalvo(medicaoRef, poligonoSalvo, 'poligono');
                    });
                    google.maps.event.addListener(path, 'insert_at', () => {
                        this.atualizarMedidasObjetoSalvo(medicaoRef, poligonoSalvo, 'poligono');
                    });
                    google.maps.event.addListener(path, 'remove_at', () => {
                        this.atualizarMedidasObjetoSalvo(medicaoRef, poligonoSalvo, 'poligono');
                    });
                }

                if (this.polilinha) {
                    this.polilinha.setOptions({
                        editable: true,
                        draggable: false,
                        clickable: false
                    });

                    // Recria as labels uma vez
                    this.atualizarMedidasPolilinha();
                    medicaoSalva.labels = [...this.labels];
                    medicaoSalva.objeto = this.polilinha;

                    const polilinhaSalva = this.polilinha;
                    const medicaoRef = medicaoSalva;
                    const path = this.polilinha.getPath();
                    google.maps.event.addListener(path, 'set_at', () => {
                        this.atualizarMedidasObjetoSalvo(medicaoRef, polilinhaSalva, 'polilinha');
                    });
                    google.maps.event.addListener(path, 'insert_at', () => {
                        this.atualizarMedidasObjetoSalvo(medicaoRef, polilinhaSalva, 'polilinha');
                    });
                    google.maps.event.addListener(path, 'remove_at', () => {
                        this.atualizarMedidasObjetoSalvo(medicaoRef, polilinhaSalva, 'polilinha');
                    });
                }

                if (this.circulo) {
                    this.circulo.setOptions({
                        editable: true,
                        draggable: true,
                        clickable: false
                    });

                    // Recria as labels uma vez
                    this.atualizarMedidasCirculo();
                    medicaoSalva.labels = [...this.labels];
                    medicaoSalva.objeto = this.circulo;

                    const circuloSalvo = this.circulo;
                    const medicaoRef = medicaoSalva;
                    google.maps.event.addListener(this.circulo, 'radius_changed', () => {
                        this.atualizarMedidasObjetoSalvo(medicaoRef, circuloSalvo, 'circulo');
                    });
                    google.maps.event.addListener(this.circulo, 'center_changed', () => {
                        this.atualizarMedidasObjetoSalvo(medicaoRef, circuloSalvo, 'circulo');
                    });
                }

                // Adiciona a medição ao array de salvas
                if (medicaoSalva.objeto) {
                    this.medicoesSalvas.push(medicaoSalva);
                }

                // Reseta variáveis para nova medição
                this.poligono = null;
                this.polilinha = null;
                this.circulo = null;
                this.centroCirculo = null;
                this.pontos = [];
                this.labels = [];

                // Mantém o modo ativo para permitir nova medição
                // Reinicia os listeners mantendo this.ativa = true
                const tipoAtual = this.tipo;
                // NÃO zera this.ativa para que iniciar() saiba que está continuando
                this.iniciar(tipoAtual);

                // Atualiza UI
                $('#btnCancelarMedicao').removeClass('d-none');
                $('#btnLimparMedicoes').removeClass('d-none');
            },

            atualizarMedidasObjetoSalvo: function(medicao, objeto, tipo) {
                // Remove labels antigas da medição
                if (medicao.labels) {
                    medicao.labels.forEach(l => l.setMap(null));
                }

                const labelsNovas = [];

                // Recria labels baseado no tipo
                if (tipo === 'poligono') {
                    const path = objeto.getPath();
                    const pontos = [];

                    for (let i = 0; i < path.getLength(); i++) {
                        pontos.push(path.getAt(i));
                    }

                    if (pontos.length >= 2) {
                        for (let i = 0; i < pontos.length; i++) {
                            const proximoIndice = (i + 1) % pontos.length;
                            const distancia = this.calcularDistancia(pontos[i], pontos[proximoIndice]);

                            const lat = (pontos[i].lat() + pontos[proximoIndice].lat()) / 2;
                            const lng = (pontos[i].lng() + pontos[proximoIndice].lng()) / 2;
                            const offsetVertical = 0.0000015;
                            const posicao = new google.maps.LatLng(lat + offsetVertical, lng);

                            const el = document.createElement('div');
                            el.className = 'measurement-label';
                            el.textContent = this.formatarDistancia(distancia);

                            const label = new google.maps.marker.AdvancedMarkerElement({
                                position: posicao,
                                content: el,
                                map: MapFramework.map,
                                zIndex: 1000,
                                gmpClickable: false
                            });

                            labelsNovas.push(label);
                        }

                        if (pontos.length >= 3) {
                            const coords = pontos.map(p => [p.lng(), p.lat()]);
                            coords.push([pontos[0].lng(), pontos[0].lat()]);
                            const polygon = turf.polygon([coords]);
                            const area = turf.area(polygon);

                            const centroide = this.calcularCentroide(pontos);
                            const elArea = document.createElement('div');
                            elArea.className = 'measurement-area-label';
                            elArea.textContent = area.toFixed(1) + ' m²';

                            const labelArea = new google.maps.marker.AdvancedMarkerElement({
                                position: centroide,
                                content: elArea,
                                map: MapFramework.map,
                                zIndex: 1001,
                                gmpClickable: false
                            });

                            labelsNovas.push(labelArea);
                        }
                    }
                } else if (tipo === 'polilinha') {
                    const path = objeto.getPath();
                    const pontos = [];

                    for (let i = 0; i < path.getLength(); i++) {
                        pontos.push(path.getAt(i));
                    }

                    for (let i = 0; i < pontos.length - 1; i++) {
                        const distancia = this.calcularDistancia(pontos[i], pontos[i + 1]);

                        const lat = (pontos[i].lat() + pontos[i + 1].lat()) / 2;
                        const lng = (pontos[i].lng() + pontos[i + 1].lng()) / 2;
                        const offsetVertical = 0.0000015;
                        const posicao = new google.maps.LatLng(lat + offsetVertical, lng);

                        const el = document.createElement('div');
                        el.className = 'measurement-label';
                        el.textContent = this.formatarDistancia(distancia);

                        const label = new google.maps.marker.AdvancedMarkerElement({
                            position: posicao,
                            content: el,
                            map: MapFramework.map,
                            zIndex: 1000,
                            gmpClickable: false
                        });

                        labelsNovas.push(label);
                    }

                    // Adiciona label com distância total no último ponto
                    if (pontos.length >= 2) {
                        let distanciaTotal = 0;
                        for (let i = 0; i < pontos.length - 1; i++) {
                            distanciaTotal += this.calcularDistancia(pontos[i], pontos[i + 1]);
                        }

                        const ultimoPonto = pontos[pontos.length - 1];
                        const elTotal = document.createElement('div');
                        elTotal.className = 'measurement-distance-label';
                        elTotal.textContent = 'Total: ' + this.formatarDistancia(distanciaTotal);

                        const labelTotal = new google.maps.marker.AdvancedMarkerElement({
                            position: ultimoPonto,
                            content: elTotal,
                            map: MapFramework.map,
                            zIndex: 1001,
                            gmpClickable: false
                        });

                        labelsNovas.push(labelTotal);
                    }
                } else if (tipo === 'circulo') {
                    const centro = objeto.getCenter();
                    const raio = objeto.getRadius();

                    const pontoRaio = google.maps.geometry.spherical.computeOffset(centro, raio, 45);
                    const lat = (centro.lat() + pontoRaio.lat()) / 2;
                    const lng = (centro.lng() + pontoRaio.lng()) / 2;
                    const offsetVertical = 0.0000015;
                    const posicaoRaio = new google.maps.LatLng(lat + offsetVertical, lng);

                    const elRaio = document.createElement('div');
                    elRaio.className = 'measurement-label';
                    elRaio.textContent = 'Raio: ' + this.formatarDistancia(raio);

                    const labelRaio = new google.maps.marker.AdvancedMarkerElement({
                        position: posicaoRaio,
                        content: elRaio,
                        map: MapFramework.map,
                        zIndex: 1000,
                        gmpClickable: false
                    });

                    labelsNovas.push(labelRaio);

                    const area = Math.PI * raio * raio;
                    const elArea = document.createElement('div');
                    elArea.className = 'measurement-area-label';
                    elArea.style.backgroundColor = '#FF0000';
                    elArea.style.borderColor = '#CC0000';
                    elArea.textContent = area.toFixed(1) + ' m²';

                    const labelArea = new google.maps.marker.AdvancedMarkerElement({
                        position: centro,
                        content: elArea,
                        map: MapFramework.map,
                        zIndex: 1001,
                        gmpClickable: false
                    });

                    labelsNovas.push(labelArea);
                }

                medicao.labels = labelsNovas;
            },

            atualizarMedidasPoligono: function() {
                // Remove labels antigas
                this.labels.forEach(l => l.setMap(null));
                this.labels = [];

                if (!this.poligono) return;

                const path = this.poligono.getPath();
                const pontos = [];

                for (let i = 0; i < path.getLength(); i++) {
                    pontos.push(path.getAt(i));
                }

                if (pontos.length < 2) return;

                // Adiciona labels nas arestas
                for (let i = 0; i < pontos.length; i++) {
                    const proximoIndice = (i + 1) % pontos.length;
                    const distancia = this.calcularDistancia(pontos[i], pontos[proximoIndice]);
                    this.adicionarLabelAresta(pontos[i], pontos[proximoIndice], distancia);
                }

                // Se tiver pelo menos 3 pontos, calcula a área
                if (pontos.length >= 3) {
                    const coords = pontos.map(p => [p.lng(), p.lat()]);
                    coords.push([pontos[0].lng(), pontos[0].lat()]); // Fecha o polígono

                    const polygon = turf.polygon([coords]);
                    const area = turf.area(polygon);

                    const centroide = this.calcularCentroide(pontos);
                    this.adicionarLabelCentral(area.toFixed(1) + ' m²', centroide, 'measurement-area-label');
                }
            },

            atualizarMedidasPolilinha: function() {
                // Remove labels antigas
                this.labels.forEach(l => l.setMap(null));
                this.labels = [];

                if (!this.polilinha) return;

                const path = this.polilinha.getPath();
                const pontos = [];

                for (let i = 0; i < path.getLength(); i++) {
                    pontos.push(path.getAt(i));
                }

                if (pontos.length < 1) return;

                // Adiciona labels nas arestas e calcula distância total
                let distanciaTotal = 0;
                for (let i = 0; i < pontos.length - 1; i++) {
                    const distancia = this.calcularDistancia(pontos[i], pontos[i + 1]);
                    distanciaTotal += distancia;
                    this.adicionarLabelAresta(pontos[i], pontos[i + 1], distancia);
                }

                // Adiciona label com distância total no último ponto
                if (pontos.length >= 2) {
                    const ultimoPonto = pontos[pontos.length - 1];
                    this.adicionarLabelCentral(
                        'Total: ' + this.formatarDistancia(distanciaTotal),
                        ultimoPonto,
                        'measurement-distance-label'
                    );
                }
            },

            atualizarCirculo: function() {
                this.labels.forEach(l => l.setMap(null));
                this.labels = [];

                if (!this.centroCirculo || this.pontos.length < 2) return;

                const raio = this.calcularDistancia(this.centroCirculo, this.pontos[1]);

                if (!this.circulo) {
                    this.circulo = new google.maps.Circle({
                        center: this.centroCirculo,
                        radius: raio,
                        strokeColor: '#FF0000',
                        strokeOpacity: 0.8,
                        strokeWeight: 2,
                        fillColor: '#FF0000',
                        fillOpacity: 0.2,
                        map: MapFramework.map,
                        zIndex: 999,
                        editable: false,
                        draggable: false,
                        clickable: false
                    });
                } else {
                    this.circulo.setRadius(raio);
                }

                // Label com o raio
                const pontoRaio = this.pontos[1];
                const lat = (this.centroCirculo.lat() + pontoRaio.lat()) / 2;
                const lng = (this.centroCirculo.lng() + pontoRaio.lng()) / 2;
                const offsetVertical = 0.0000015;
                const posicaoRaio = new google.maps.LatLng(lat + offsetVertical, lng);

                const elRaio = document.createElement('div');
                elRaio.className = 'measurement-label';
                elRaio.textContent = 'Raio: ' + this.formatarDistancia(raio);

                const labelRaio = new google.maps.marker.AdvancedMarkerElement({
                    position: posicaoRaio,
                    content: elRaio,
                    map: MapFramework.map,
                    zIndex: 1000,
                    gmpClickable: false
                });

                this.labels.push(labelRaio);

                // Label com a área no centro
                const area = Math.PI * raio * raio;
                const elArea = document.createElement('div');
                elArea.className = 'measurement-area-label';
                elArea.style.backgroundColor = '#FF0000';
                elArea.style.borderColor = '#CC0000';
                elArea.textContent = area.toFixed(1) + ' m²'; // Sempre em m² para círculos

                const labelArea = new google.maps.marker.AdvancedMarkerElement({
                    position: this.centroCirculo,
                    content: elArea,
                    map: MapFramework.map,
                    zIndex: 1001,
                    gmpClickable: false
                });

                this.labels.push(labelArea);
            },

            atualizarMedidasCirculo: function() {
                this.labels.forEach(l => l.setMap(null));
                this.labels = [];

                if (!this.circulo) return;

                const centro = this.circulo.getCenter();
                const raio = this.circulo.getRadius();

                // Calcula um ponto na borda do círculo para posicionar a label do raio
                const pontoRaio = google.maps.geometry.spherical.computeOffset(centro, raio, 45);

                const lat = (centro.lat() + pontoRaio.lat()) / 2;
                const lng = (centro.lng() + pontoRaio.lng()) / 2;
                const offsetVertical = 0.0000015;
                const posicaoRaio = new google.maps.LatLng(lat + offsetVertical, lng);

                const elRaio = document.createElement('div');
                elRaio.className = 'measurement-label';
                elRaio.textContent = 'Raio: ' + this.formatarDistancia(raio);

                const labelRaio = new google.maps.marker.AdvancedMarkerElement({
                    position: posicaoRaio,
                    content: elRaio,
                    map: MapFramework.map,
                    zIndex: 1000,
                    gmpClickable: false
                });

                this.labels.push(labelRaio);

                // Label com a área no centro
                const area = Math.PI * raio * raio;
                const elArea = document.createElement('div');
                elArea.className = 'measurement-area-label';
                elArea.style.backgroundColor = '#FF0000';
                elArea.style.borderColor = '#CC0000';
                elArea.textContent = area.toFixed(1) + ' m²'; // Sempre em m² para círculos

                const labelArea = new google.maps.marker.AdvancedMarkerElement({
                    position: centro,
                    content: elArea,
                    map: MapFramework.map,
                    zIndex: 1001,
                    gmpClickable: false
                });

                this.labels.push(labelArea);
            }
        };

        // Funções para os botões de medição
        function iniciarMedicaoArea() {
            if (Medicao.ativa && Medicao.tipo !== 'area') {
                Medicao.limparMedicaoAtual();
            }
            Medicao.iniciar('area');
            $('#btnCancelarMedicao').removeClass('d-none');
            if (Medicao.medicoesSalvas.length > 0) {
                $('#btnLimparMedicoes').removeClass('d-none');
            }
        }

        function iniciarMedicaoDistancia() {
            if (Medicao.ativa && Medicao.tipo !== 'distancia') {
                Medicao.limparMedicaoAtual();
            }
            Medicao.iniciar('distancia');
            $('#btnCancelarMedicao').removeClass('d-none');
            if (Medicao.medicoesSalvas.length > 0) {
                $('#btnLimparMedicoes').removeClass('d-none');
            }
        }

        function iniciarMedicaoCirculo() {
            if (Medicao.ativa && Medicao.tipo !== 'circulo') {
                Medicao.limparMedicaoAtual();
            }
            Medicao.iniciar('circulo');
            $('#btnCancelarMedicao').removeClass('d-none');
            if (Medicao.medicoesSalvas.length > 0) {
                $('#btnLimparMedicoes').removeClass('d-none');
            }
        }

        function limparTodasMedicoes() {
            // Remove todas as medições salvas
            Medicao.medicoesSalvas.forEach(medicao => {
                if (medicao.objeto) {
                    medicao.objeto.setMap(null);
                }
                if (medicao.labels) {
                    medicao.labels.forEach(l => l.setMap(null));
                }
            });
            Medicao.medicoesSalvas = [];
            $('#btnLimparMedicoes').addClass('d-none');
        }

        function cancelarMedicao() {
            Medicao.limpar();
            $('#btnCancelarMedicao').addClass('d-none');
            $('#btnLimparMedicoes').addClass('d-none');
        }

        const arrayCamadas = {
            prefeitura: [],
            limite: [],
            marcador: [],
            marcador_quadra: [],
            quadriculas: [],
            ortofoto: [],
            quadra: [],
            lotesPref: [],
            lote: [],
            poligono_lote: [],
            lote_ortofoto: [],
            quarteirao: [],
            imagens_aereas: [],
            streetview: [],
            streetview_fotos: [],
            semCamadas: []
        };

        $('#btnCloseTooltip').on('click', function() {
            $('#tooltipMarcador').hide();
        });

        // Função para carregar o controle de navegação de quadrículas
        function tentarCarregarControleQuadriculas() {

            if (typeof MapFramework !== 'undefined' &&
                MapFramework.carregarControleNavegacaoQuadriculas &&
                dadosOrto &&
                dadosOrto.length > 0 &&
                dadosOrto[0]['quadricula']) {
                MapFramework.carregarControleNavegacaoQuadriculas(dadosOrto[0]['quadricula']);
                return true;
            }
            return false;
        }

        // Aguarda o carregamento completo da página (incluindo scripts com defer)
        window.addEventListener('load', function() {
            console.log('Página carregada completamente');

            // Tenta carregar imediatamente
            if (!tentarCarregarControleQuadriculas()) {
                // Se não funcionou, tenta novamente com um pequeno delay
                let tentativas = 0;
                const maxTentativas = 10;
                const intervalo = setInterval(function() {
                    tentativas++;
                    console.log(`Tentativa ${tentativas} de carregar controle...`);

                    if (tentarCarregarControleQuadriculas() || tentativas >= maxTentativas) {
                        clearInterval(intervalo);
                        if (tentativas >= maxTentativas) {
                            console.error('Não foi possível carregar o controle de navegação após', maxTentativas, 'tentativas');
                        }
                    }
                }, 200);
            }
        });


        function adicionarObjetoNaCamada(nome, objeto) {
            try {
                const chave = (nome || 'semCamadas').toLowerCase();
                if (!arrayCamadas[chave]) {
                    arrayCamadas[chave] = [];
                }
                arrayCamadas[chave].push(objeto);
                //`Objeto adicionado à camada: ${chave}`, objeto);
            } catch (e) {
                console.error('Erro ao adicionar objeto na camada:', e);
                throw e;
            }
        }

        function abrirModalCamada() {
            this.bloqueiaRightClick = true; // trava cliques direitos no mapa
            $('#modalCamada').fadeIn(150);
        }

        function fecharModalCamada() {
            this.bloqueiaRightClick = false; // libera de novo
            $('#modalCamada').fadeOut(150);
        }

        // Botões de inclusão
        $('#btnIncluirPoligono').on('click', function() {
            MapFramework.iniciarDesenhoQuadra();
            controlarVisibilidadeBotoes('quadra');
        });

        // Botão Crop
        $('#btnModoCrop').on('click', function() {
            MapFramework.iniciarModoCrop();
        });

        $('#btnIncluirLinha').on('click', function() {
            MapFramework.iniciarDesenhoLote();
            controlarVisibilidadeBotoes('lote');
        });

        $('#btnIncluirMarcador').on('click', function() {
            MapFramework.iniciarDesenhoMarcador();
            controlarVisibilidadeBotoes('marcador');
        });

        // Botão para finalizar desenho
        $('#btnFinalizarDesenho').on('click', function() {
            MapFramework.finalizarDesenho();
            controlarVisibilidadeBotoes('normal');
        });

        // Botão específico para sair do modo marcador
        $('#btnSairModoMarcador').on('click', function() {
            MapFramework.sairModoMarcador();
            voltarModoCadastro(); // Volta para o modo cadastro
        });

        // Modal: SALVAR quadra
        $('#btnSalvarCamada').on('click', function() {
            const identificador = $('#inputNumeroQuadra').val().trim();
            if (!identificador) {
                alert('Informe o identificador da quadra.');
                return;
            }
            MapFramework.salvarDesenho('Quadra', identificador);
        });

        // Modal: CANCELAR / sair do modo desenho
        $('#btnCancelarCamada').on('click', function() {
            MapFramework.finalizarDesenho({
                descartarTemporario: true
            });
        });

        $('#btnExcluir').on('click', function() {
            if (!dadosOrto || dadosOrto.length === 0) {
                alert('Erro: Dados da ortofoto não estão disponíveis.');
                return;
            }
            MapFramework.excluirDesenhoSelecionado2('paulinia', dadosOrto[0]['quadricula']);
        });

        // Botão Editar - Entra no modo de edição
        $('#btnEditar').on('click', function() {
            MapFramework.entrarModoEdicao();
        });

        // Botão Sair da Edição - Salva e sai do modo de edição
        $('#btnSairEdicao').on('click', function() {
            MapFramework.sairModoEdicao();
        });

        // Checkbox da Ortofoto
        $('#chkOrtofoto').on('change', function() {
            if (!dadosOrto || dadosOrto.length === 0) {
                alert('Erro: Dados da ortofoto não estão disponíveis.');
                return;
            }

            const visivel = $(this).is(':checked');
            if (visivel) {
                MapFramework.inserirOrtofoto2(dadosOrto[0]["quadricula"]); // Se estava desativada, reinsere
            } else {
                MapFramework.limparOrtofoto(); // Remove a ortofoto do mapa
            }
        });

        // Checkbox das Quadras
        $('#chkQuadras').on('change', function() {
            const visivel = $(this).is(':checked');
            MapFramework.alternarVisibilidadeCamada('quadra', visivel);
        });

        // Checkbox das Unidades
        $('#chkUnidades').on('change', function() {
            const visivel = $(this).is(':checked');
            MapFramework.alternarVisibilidadeCamada('unidade', visivel);
        });

        // Checkbox das Piscinas
        $('#chkPiscinas').on('change', function() {
            const visivel = $(this).is(':checked');
            MapFramework.alternarVisibilidadeCamada('piscina', visivel);
        });

        // Checkbox dos Lotes
        $('#chkLotes').on('change', function() {
            const visivel = $(this).is(':checked');
            MapFramework.alternarVisibilidadeCamada('lote', visivel);
        });

        // Checkbox dos Lotes
        $('#chkPoligono_lote').on('change', function() {
            const visivel = $(this).is(':checked');
            MapFramework.alternarVisibilidadeCamada('lote_ortofoto', visivel);
        });

        $('#chkPrefeitura').on('change', function() {
            const visivel = $(this).is(':checked');
            MapFramework.alternarVisibilidadeCamada('prefeitura', visivel);
        });

        $('#chkLimite').on('change', function() {
            const visivel = $(this).is(':checked');
            MapFramework.alternarVisibilidadeCamada('limite', visivel);
        });

        $('#chkCondominiosVerticais').on('change', function() {
            const visivel = $(this).is(':checked');
            if (visivel && (!arrayCamadas.condominios_verticais || arrayCamadas.condominios_verticais.length === 0)) {
                MapFramework.carregarCondominiosVerticaisKML();
            } else {
                MapFramework.alternarVisibilidadeCamada('condominios_verticais', visivel);
            }
        });

        $('#chkCondominiosHorizontais').on('change', function() {
            const visivel = $(this).is(':checked');
            if (visivel && (!arrayCamadas.condominios_horizontais || arrayCamadas.condominios_horizontais.length === 0)) {
                MapFramework.carregarCondominiosHorizontaisKML();
            } else {
                MapFramework.alternarVisibilidadeCamada('condominios_horizontais', visivel);
            }
        });

        $('#chkAreasPublicas').on('change', function() {
            const visivel = $(this).is(':checked');
            if (visivel && (!arrayCamadas.areas_publicas || arrayCamadas.areas_publicas.length === 0)) {
                MapFramework.carregarAreasPublicasKML();
            } else {
                MapFramework.alternarVisibilidadeCamada('areas_publicas', visivel);
            }
        });

        $('#chkQuadriculas').on('change', function() {
            const visivel = $(this).is(':checked');
            MapFramework.alternarVisibilidadeCamada('quadriculas', visivel);
        });

        $('#chkMarcadores').on('change', function() {
            const visivel = $(this).is(':checked');

            // Mostra/oculta o botão de filtros
            if (visivel) {
                $('#btnFiltroCores').fadeIn(200);
                // Mostra TODOS os marcadores quando ativa o checkbox principal
                // NÃO aplica filtro aqui - os filtros de cores são controlados pelos checkboxes de cores
                if (MapFramework && MapFramework.alternarVisibilidadeTodosMarcadores) {
                    MapFramework.alternarVisibilidadeTodosMarcadores(true);
                }
            } else {
                $('#btnFiltroCores').fadeOut(200);
                $('#divFiltroCores').fadeOut(150);
                $('#btnFiltroCores').removeClass('aberto');
                // Oculta todos os marcadores quando desativa
                if (MapFramework && MapFramework.alternarVisibilidadeTodosMarcadores) {
                    MapFramework.alternarVisibilidadeTodosMarcadores(false);
                } else if (arrayCamadas.marcador_quadra) {
                    arrayCamadas.marcador_quadra.forEach(marker => {
                        marker.setMap(null);
                    });
                }
            }
        });

        $('#chkQuarteiroes').on('change', function() {
            const visivel = $(this).is(':checked');
            MapFramework.alternarVisibilidadeCamada('quarteirao', visivel);
        });

        $('#chkImagensAereas').on('change', function() {
            const visivel = $(this).is(':checked');
            MapFramework.alternarVisibilidadeCamada('imagens_aereas', visivel);
        });

        $('#chkStreetview').on('change', function() {
            const visivel = $(this).is(':checked');
            MapFramework.alternarVisibilidadeCamada('streetview', visivel);
        });

        $('#chkStreetviewFotos').on('change', function() {
            const visivel = $(this).is(':checked');
            MapFramework.alternarVisibilidadeCamada('streetview_fotos', visivel);

            // Mostra/oculta o botão de edição de trajeto
            if (visivel) {
                $('#btnEditarStreetviewFotos').removeClass('d-none');
            } else {
                $('#btnEditarStreetviewFotos').addClass('d-none');
                // Se estava no modo de edição, sai dele
                if (MapFramework.modoEdicaoStreetviewFotos) {
                    MapFramework.desativarEdicaoStreetviewFotos();
                    $('#btnEditarStreetviewFotos').removeClass('btn-danger').addClass('btn-info');
                    $('#btnEditarStreetviewFotos').html('<i class="fas fa-edit"></i> Corrigir Trajeto');
                }
            }
        });

        // Evento do botão de edição de trajeto
        $('#btnEditarStreetviewFotos').on('click', function() {
            if (MapFramework.modoEdicaoStreetviewFotos) {
                // Se já está no modo de edição, sai dele
                MapFramework.desativarEdicaoStreetviewFotos();
                $(this).removeClass('btn-danger').addClass('btn-info');
                $(this).html('<i class="fas fa-edit"></i> Corrigir Trajeto');
            } else {
                // Se não está no modo de edição, entra nele
                MapFramework.ativarEdicaoStreetviewFotos();
                $(this).removeClass('btn-info').addClass('btn-danger');
                $(this).html('<i class="fas fa-save"></i> Finalizar Edição');
            }
        });

        // Eventos para os radio buttons de tipo de marcador
        $('input[name="tipoMarcador"]').on('change', function() {
            const tipoSelecionado = $(this).val();
            atualizarRotulosMarcadores(tipoSelecionado);
        });

        // Eventos para os radio buttons de tipo de marcador
        $('input[name="tipoLotes"]').on('change', function() {
            const tipoSelecionado2 = $(this).val();
            atualizarCorPoligonos_lotes(tipoSelecionado2);
        });

        // Eventos para o botão de filtro de cores
        $('#btnFiltroCores').on('click', function() {
            const divFiltro = $('#divFiltroCores');
            const btn = $(this);

            if (divFiltro.is(':visible')) {
                divFiltro.fadeOut(150);
                btn.removeClass('aberto');
            } else {
                divFiltro.fadeIn(150);
                btn.addClass('aberto');
            }
        });

        // Eventos para os checkboxes de filtro de cores
        $('#chkVermelho, #chkAmarelo, #chkLaranja, #chkVerde, #chkAzul, #chkCinza').on('change', function() {
            aplicarFiltroCores();
        });

        // Função auxiliar para normalizar cor (converte RGB para hex e remove espaços)
        function normalizarCor(cor) {
            if (!cor) return '';
            cor = cor.trim().toLowerCase();

            // Se já é hexadecimal, retorna em minúsculas
            if (cor.startsWith('#')) {
                return cor;
            }

            // Se é RGB, converte para hex
            const rgbMatch = cor.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/);
            if (rgbMatch) {
                const r = parseInt(rgbMatch[1]).toString(16).padStart(2, '0');
                const g = parseInt(rgbMatch[2]).toString(16).padStart(2, '0');
                const b = parseInt(rgbMatch[3]).toString(16).padStart(2, '0');
                return `#${r}${g}${b}`;
            }

            // Se é nome de cor conhecido, converte
            const coresNomes = {
                'red': '#e84646',
                'yellow': '#eddf47',
                'orange': '#ed7947',
                'green': '#32cd32',
                'blue': '#47cced',
                'gray': '#7c7c7c',
                'grey': '#7c7c7c'
            };

            return coresNomes[cor] || cor;
        }

        // Função para aplicar filtro de cores nos marcadores
        function aplicarFiltroCores() {
            if (!arrayCamadas.marcador_quadra || arrayCamadas.marcador_quadra.length === 0) {
                return;
            }

            // Verifica se o checkbox de marcadores está ativo
            const marcadoresAtivos = $('#chkMarcadores').is(':checked');

            // Se o checkbox principal não está ativo, oculta todos os marcadores
            if (!marcadoresAtivos) {
                arrayCamadas.marcador_quadra.forEach(marker => {
                    marker.setMap(null);
                });
                return;
            }

            // Verifica se TODOS os checkboxes de cores estão marcados
            const todosCoresMarcados = $('#chkVermelho').is(':checked') &&
                $('#chkAmarelo').is(':checked') &&
                $('#chkLaranja').is(':checked') &&
                $('#chkVerde').is(':checked') &&
                $('#chkAzul').is(':checked') &&
                $('#chkCinza').is(':checked');

            // Se todos os checkboxes de cores estão marcados, mostra TODOS os marcadores
            if (todosCoresMarcados) {
                if (MapFramework && MapFramework.alternarVisibilidadeTodosMarcadores) {
                    MapFramework.alternarVisibilidadeTodosMarcadores(true);
                }
                return; // Não precisa aplicar filtro
            }

            // Mapeamento de cores para checkboxes (em formato normalizado)
            const coresMap = {
                '#e84646': 'chkVermelho', // Vermelho
                '#eddf47': 'chkAmarelo', // Amarelo
                '#ed7947': 'chkLaranja', // Laranja
                '#32cd32': 'chkVerde', // Verde
                '#47cced': 'chkAzul', // Azul
                '#7c7c7c': 'chkCinza' // Cinza
            };

            arrayCamadas.marcador_quadra.forEach(marker => {
                // Tenta obter a cor do marker.corMarcador primeiro (cor original do banco)
                // Se não existir, tenta obter do style.backgroundColor
                let corBruta = marker.corMarcador || '';
                if (!corBruta && marker.content && marker.content.style) {
                    corBruta = marker.content.style.backgroundColor || marker.content.style.background || '';
                }

                // Normaliza a cor para formato hexadecimal
                const corNormalizada = normalizarCor(corBruta);

                // Verifica se a cor está no mapeamento
                const checkboxId = coresMap[corNormalizada];
                let deveMostrar = false;
                let corMapeada = false;

                if (checkboxId) {
                    // Cor está mapeada, verifica o checkbox correspondente
                    deveMostrar = $('#' + checkboxId).is(':checked');
                    corMapeada = true;
                }

                // Se a cor não foi mapeada, sempre mostra (cores desconhecidas aparecem sempre)
                // Se foi mapeada, mostra baseado no checkbox
                if (!corMapeada || deveMostrar) {
                    marker.setMap(MapFramework.map);
                } else {
                    marker.setMap(null);
                }
            });
        }


        // Função para atualizar os rótulos dos marcadores
        function atualizarRotulosMarcadores(tipo) {
            if (!arrayCamadas.marcador_quadra || arrayCamadas.marcador_quadra.length === 0) {
                return;
            }

            arrayCamadas.marcador_quadra.forEach(marker => {
                let novoRotulo = '';

                if (tipo === 'lote') {
                    // Usa o rótulo atual (número do lote)
                    novoRotulo = marker.numeroMarcador || '-';

                } else if (tipo === 'predial') {

                    // Busca o número predial nos dados do morador
                    const dadosMorador = MapFramework.dadosMoradores.find(morador =>
                        morador.lote == marker.numeroMarcador &&
                        morador.quadra == marker.quadra &&
                        morador.cara_quarteirao == marker.quarteirao
                    );


                    novoRotulo = dadosMorador ? (dadosMorador.numero || '-') : '-';
                }

                // Atualiza o texto do elemento HTML do marcador
                if (marker.content && marker.content.textContent !== undefined) {
                    marker.content.textContent = novoRotulo;
                }
            });
        }

        function atualizarCorPoligonos_lotes(tipo) {
            console.log(arrayCamadas.poligono_lote);
            if (tipo === 'todos') {
                arrayCamadas.poligono_lote.forEach(poligono => {
                    poligono.setOptions({
                        fillColor: 'purple',
                        strokeColor: 'purple'
                    });
                });
            } else if (tipo === 'impacto') {
                arrayCamadas.poligono_lote.forEach(poligono => {
                    poligono.setOptions({
                        fillColor: 'red',
                        strokeColor: 'red'
                    });
                });
            }

        }

        $('#customRange1').on('input', function() {
            MapFramework.controlarOpacidade(this.value);

            // Controla também a opacidade dos loteamentos
            if (window.loteamentosLayer) {
                window.loteamentosLayer.forEach(polygon => {
                    polygon.setOptions({
                        fillOpacity: this.value
                    });
                });
            }
        })

        // Controle de expansão do navegador de quadrículas
        $('#btnExpandir').on('click', function() {
            const controle = $('#controleNavegacaoQuadriculas');
            const grade3x3 = $('#grade3x3');
            const gradeExpandida = $('#gradeExpandida');
            const btn = $(this);

            if (controle.hasClass('expandido')) {
                // Contrai
                controle.removeClass('expandido');
                gradeExpandida.removeClass('show');
                grade3x3.show();
                btn.html('<i class="fas fa-expand"></i> Expandir');
                btn.removeClass('expandido');
            } else {
                // Expande
                controle.addClass('expandido');
                grade3x3.hide();
                gradeExpandida.addClass('show');
                btn.html('<i class="fas fa-compress"></i> Contrair');
                btn.addClass('expandido');
            }
        });

        // Função para carregar loteamentos de forma invisível (apenas dados, sem desenhar no mapa)
        async function carregarLoteamentosInvisiveis(quadricula) {
            try {
                //console.log('📦 Carregando loteamentos em background para:', quadricula);

                const response = await fetch(`loteamentos_quadriculas/json/resultados_quadricula_${quadricula}.json`, {
                    cache: "no-store"
                });

                if (!response.ok) {
                    console.warn('⚠️ Arquivo de loteamentos não encontrado para quadrícula:', quadricula);
                    return;
                }

                const dados = await response.json();

                if (!dados || !dados.resultados || !dados.resultados.loteamentos || dados.resultados.loteamentos.length === 0) {
                    console.warn('⚠️ Nenhum loteamento encontrado na quadrícula:', quadricula);
                    return;
                }

                const loteamentos = dados.resultados.loteamentos;
                const loteamentosInvisiveis = [];

                loteamentos.forEach((loteamento) => {
                    if (loteamento.coordenadas && loteamento.coordenadas.length > 0) {
                        const primeiraCoordenada = loteamento.coordenadas[0];

                        if (primeiraCoordenada.type === 'Polygon' && primeiraCoordenada.coordinates) {
                            try {
                                const path = primeiraCoordenada.coordinates[0].map(coord => ({
                                    lat: coord[1],
                                    lng: coord[0]
                                }));

                                if (path.length >= 3) {
                                    // Cria polígono INVISÍVEL (não é adicionado ao mapa)
                                    const polygon = new google.maps.Polygon({
                                        paths: path,
                                        map: null // NÃO desenha no mapa
                                    });
                                    polygon.nomeLoteamento = loteamento.nome;
                                    loteamentosInvisiveis.push(polygon);
                                }
                            } catch (error) {
                                console.error(`Erro ao criar polígono invisível para ${loteamento.nome}:`, error);
                            }

                        } else if (primeiraCoordenada.type === 'MultiPolygon' && primeiraCoordenada.coordinates) {
                            try {
                                primeiraCoordenada.coordinates.forEach((polygonCoords) => {
                                    const path = polygonCoords[0].map(coord => ({
                                        lat: coord[1],
                                        lng: coord[0]
                                    }));

                                    // Cria polígono INVISÍVEL
                                    const polygon = new google.maps.Polygon({
                                        paths: path,
                                        map: null // NÃO desenha no mapa
                                    });
                                    polygon.nomeLoteamento = loteamento.nome;
                                    loteamentosInvisiveis.push(polygon);
                                });
                            } catch (error) {
                                console.error(`Erro ao criar MultiPolygon invisível para ${loteamento.nome}:`, error);
                            }
                        }
                    }
                });

                // Salva os loteamentos invisíveis no cache do MapFramework
                if (loteamentosInvisiveis.length > 0) {
                    MapFramework.loteamentosCache = loteamentosInvisiveis;
                    //console.log(`✅ ${loteamentosInvisiveis.length} loteamentos carregados em background (invisíveis)`);
                }

            } catch (error) {
                console.error('Erro ao carregar loteamentos em background:', error);
            }
        }

        // Checkbox Modo Cadastro (Loteamentos)
        let processandoModoCadastro = false;

        $('#chkModoCadastro').on('change', function() {
            if (processandoModoCadastro) return;

            const ativado = $(this).is(':checked');

            if (ativado) {
                // Ativar modo cadastro
                if (!dadosOrto || dadosOrto.length === 0) {
                    alert('Erro: Dados da ortofoto não estão disponíveis.');
                    $(this).prop('checked', false);
                    return;
                }

                // Controla visibilidade dos botões
                controlarVisibilidadeBotoes('cadastro');

                //aqui desabilita o clique no poligonos quadra e lote
                arrayCamadas.quadra.forEach(quadra => {
                    quadra.setOptions({
                        clickable: false
                    });
                });
                arrayCamadas.lote.forEach(lote => {
                    lote.setOptions({
                        clickable: false
                    });
                });

                const quadricula = dadosOrto[0]['quadricula'];
                carregarLoteamentosQuadricula(quadricula);
            } else {
                // Desativar modo cadastro
                sairModoCadastro();
            }
        });

        // Função para carregar loteamentos de uma quadrícula específica
        async function carregarLoteamentosQuadricula(quadricula) {
            try {
                // Valida a quadrícula
                if (!quadricula || quadricula.trim() === '') {
                    throw new Error('Quadrícula inválida');
                }

                // Atualiza o título da div
                $('#quadriculaAtual').text(quadricula);

                // Mostra indicador de carregamento
                $('#opcoesLoteamentos').html('<div class="text-center"><i class="fas fa-spinner fa-spin fa-2x"></i><p>Carregando loteamentos...</p></div>');

                // Carrega o JSON da quadrícula
                const response = await fetch(`loteamentos_quadriculas/json/resultados_quadricula_${quadricula}.json`, {
                    cache: "no-store"
                });
                if (!response.ok) {
                    if (response.status === 404) {
                        throw new Error(`Arquivo de loteamentos não encontrado para a quadrícula ${quadricula}`);
                    } else {
                        throw new Error(`Erro HTTP: ${response.status} - ${response.statusText}`);
                    }
                }

                const dados = await response.json();

                // Valida a estrutura dos dados
                if (!dados || typeof dados !== 'object') {
                    throw new Error('Formato de dados inválido');
                }

                // Verifica se há loteamentos
                if (!dados.resultados || !dados.resultados.loteamentos || dados.resultados.loteamentos.length === 0) {
                    $('#opcoesLoteamentos').html(`
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            Nenhum loteamento encontrado para a quadrícula <strong>${quadricula}</strong>.
                        </div>
                    `);
                } else {
                    // Cria os botões radio dinamicamente
                    criarOpcoesLoteamentos(dados.resultados.loteamentos);

                    // Adiciona os desenhos no mapa
                    adicionarDesenhosNoMapa(dados.resultados.loteamentos, quadricula);
                }

                // Abre a div flutuante
                $('#divCadastro').fadeIn(150);

            } catch (error) {
                console.error('Erro ao carregar loteamentos:', error);

                let mensagemErro = 'Erro ao carregar os dados dos loteamentos.';
                if (error.message.includes('não encontrado')) {
                    mensagemErro = error.message;
                } else if (error.message.includes('Formato de dados inválido')) {
                    mensagemErro = 'O arquivo de dados está corrompido ou em formato inválido.';
                }

                $('#opcoesLoteamentos').html(`
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        ${mensagemErro}
                    </div>
                `);

                // Abre a div flutuante mesmo com erro para mostrar a mensagem
                $('#divCadastro').fadeIn(150);
            }
        }

        // Função para verificar se um arquivo existe
        async function verificarArquivoExiste(caminho) {
            try {
                const response = await fetch(caminho, {
                    method: 'HEAD'
                });
                return response.ok;
            } catch (error) {
                console.log('Erro ao verificar arquivo:', caminho, error);
                return false;
            }
        }

        // Função para decodificar caracteres especiais
        function normalizarString(str) {
            // Converte para Normalization Form C (NFC)
            return str.normalize("NFC");
        }

        // Função para abrir PDF com tratamento de erro
        async function abrirPDF(nomeArquivo) {
            const nomeDecodificado = normalizarString(nomeArquivo);
            window.open('loteamentos_quadriculas/pdf/' + nomeDecodificado, '_blank');
        }

        // Função para abrir PDF em nova aba (nova função para os botões)
        function abrirPDFNovaAba(nomeArquivo) {
            const nomeDecodificado = normalizarString(nomeArquivo);
            window.open('loteamentos_quadriculas/pdf/' + nomeDecodificado, '_blank');
        }

        // Função para abrir PDF de quarteirão com tratamento de erro
        async function abrirPDFQuarteirao(nomeArquivo) {
            console.log('Nome original PDF quarteirão:', nomeArquivo);
            const nomeDecodificado = normalizarString(nomeArquivo);

            // Se o caminho já inclui a pasta, usa diretamente
            if (nomeArquivo.includes('/')) {
                window.open('loteamentos_quadriculas/pdfs_quarteiroes/' + nomeDecodificado, '_blank');
            } else {
                // Caso contrário, usa o caminho padrão
                window.open('loteamentos_quadriculas/pdfs_quarteiroes/' + nomeDecodificado, '_blank');
            }
        }

        // Função para criar as opções de loteamentos na div
        function criarOpcoesLoteamentos(loteamentos) {
            const container = $('#opcoesLoteamentos');
            container.empty();

            // Salva os loteamentos em variável global para uso posterior
            window.loteamentosSelecionados = loteamentos;

            loteamentos.forEach((loteamento, index) => {
                const temArquivos = loteamento.arquivos_associados && loteamento.arquivos_associados.length > 0;
                const statusClass = loteamento.status_planilha === 'ok' ? 'text-success' : 'text-warning';
                const statusText = loteamento.status_planilha === 'ok' ? "" : ""; //'✓ Com arquivos' : '⚠ Sem arquivos';

                const opcao = $(`
                    <div class="opcao-loteamento">
                        <div class="d-flex align-items-start">
                            <input style="margin-top: 2px;" type="radio" id="loteamento_${index}" name="loteamento" data-loteamento="${loteamento.nome}" data-arquivos="${loteamento.arquivos_associados}" value="${index}">
                            <label for="loteamento_${index}">
                                ${loteamento.nome}
                                <small class="d-block ${statusClass}">${statusText}</small>
                                ${loteamento.subpasta ? `<small class="d-block text-muted">${''}</small>` : ''}
                            </label>
                        </div>
                        ${temArquivos ? 
                            `<div class="submenu-pdfs" id="pdfs_loteamento_${index}" style="margin-left: 20px; margin-top: 8px;">
                                ${loteamento.arquivos_associados.map((arquivo, pdfIndex) => {
                                    const pdfId = `pdf_${index}_${pdfIndex}`;
                                    const isFirst = pdfIndex === 0;
                                    return `<div class="pdf-option d-flex align-items-center justify-content-between" style="margin-bottom: 5px;">
                                        <div class="d-flex align-items-center">
                                            <input type="radio" id="${pdfId}" name="pdf_loteamento_${index}" 
                                                   data-loteamento="${loteamento.nome}" 
                                                   data-arquivo="${arquivo}" 
                                                   data-quadricula="" 
                                                   value="${pdfIndex}"
                                                   disabled>
                                            <label for="${pdfId}" style="margin-left: 5px; font-size: 12px; margin-bottom: 0;">
                                                <i class="fas fa-file-pdf text-danger"></i> 
                                                ${arquivo}
                                            </label>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                style="font-size: 10px; padding: 2px 6px;" 
                                                onclick="abrirPDFNovaAba('${arquivo}')" 
                                                title="Abrir PDF em nova aba">
                                            <i class="fas fa-external-link-alt"></i>
                                        </button>
                                    </div>`;
                                }).join('')}
                            </div>` : 
                            '<div class="submenu-pdfs"><em class="text-muted">Sem PDFs</em></div>'
                        }
                    </div>
                `);

                container.append(opcao);
            });

            // Adiciona evento para destacar seleção
            $('input[name="loteamento"]').on('change', function() {
                const indexSelecionado = parseInt($(this).val());

                let selecoesDados_loteamento = $(this).data('loteamento');
                let selecoesDados_documentos = $(this).data('arquivos');

                // Remove destaque anterior
                removerDestaques();

                // Destaca o loteamento selecionado e desenhos relacionados
                destacarLoteamentoSelecionado(indexSelecionado, selecoesDados_loteamento, selecoesDados_documentos);

                // Adiciona classe visual para destacar a opção selecionada
                $('.opcao-loteamento').removeClass('selected');
                const opcaoSelecionada = $(this).closest('.opcao-loteamento').addClass('selected');
                scrollParaElemento('#divCadastro .div-cadastro-body', opcaoSelecionada);

                // Habilitar apenas os PDFs do loteamento selecionado (apenas nos controles originais)
                $('input[name^="pdf_loteamento_"]:not([name*="integrado"])').prop('disabled', true); // Desabilita todos exceto integrados
                $(`input[name="pdf_loteamento_${indexSelecionado}"]:not([name*="integrado"])`).prop('disabled', false); // Habilita apenas do loteamento selecionado (original)

                // CORREÇÃO: Selecionar automaticamente o primeiro PDF do loteamento selecionado
                //const primeiroPDF = $(`input[name="pdf_loteamento_${indexSelecionado}"]:not([name*="integrado"]):first`);
                //if (primeiroPDF.length > 0) {
                //    primeiroPDF.prop('checked', true);
                //}

                // Abre a divCadastro2 com os quarteirões do loteamento selecionado
                abrirDivCadastro2(indexSelecionado);

                // Fecha a divCadastro3 se estiver aberta
                $('#divCadastro3').fadeOut(150);

                // Atualiza dados do botão desenhar no PDF com o PDF selecionado
                atualizarBotaoDesenharPDF(indexSelecionado);

                // CORREÇÃO: Sincronizar loteamento com o modal integrado em tempo real
                sincronizarLoteamentoComIntegrado(indexSelecionado);
            });

            // Adiciona eventos para os radio buttons dos PDFs
            $('input[name^="pdf_loteamento_"]').on('change', function() {
                const nomeInput = $(this).attr('name');
                const indexLoteamento = nomeInput.match(/\d+/)[0];

                // CORREÇÃO: Desmarcar todos os outros radio buttons quando um for selecionado
                if ($(this).is(':checked')) {
                    // Desmarcar todos os outros PDFs
                    $('input[name^="pdf_loteamento_"]').not(this).prop('checked', false);

                    // Atualizar variável global com o PDF selecionado
                    window.pdfSelecionadoGlobal = {
                        loteamento: $(this).data('loteamento'),
                        arquivoPdf: $(this).data('arquivo'),
                        indexLoteamento: parseInt(indexLoteamento)
                    };
                }

                atualizarBotaoDesenharPDF(parseInt(indexLoteamento));

                // CORREÇÃO: Sincronizar com o modal integrado em tempo real
                sincronizarPDFComIntegrado(parseInt(indexLoteamento));
            });
        }

        // Função para sincronizar loteamento selecionado com o modal integrado
        function sincronizarLoteamentoComIntegrado(indexLoteamento) {
            // Verificar se o modal integrado está visível
            if (!$('#divCadastroIntegrado').is(':visible')) {
                return; // Modal integrado não está visível, não precisa sincronizar
            }

            // Selecionar o mesmo loteamento no modal integrado
            $(`#opcoesLoteamentosIntegrado input[name="loteamentoIntegrado"][value="${indexLoteamento}"]`).prop('checked', true);
            $(`#opcoesLoteamentosIntegrado input[name="loteamentoIntegrado"][value="${indexLoteamento}"]`).closest('.opcao-loteamento').addClass('selected');

            // Limpar seleções de outros loteamentos
            $('#opcoesLoteamentosIntegrado .opcao-loteamento').removeClass('selected');
            $(`#opcoesLoteamentosIntegrado input[name="loteamentoIntegrado"][value="${indexLoteamento}"]`).closest('.opcao-loteamento').addClass('selected');

            // Sincronizar o PDF selecionado também
            sincronizarPDFComIntegrado(indexLoteamento);
        }

        // Função para sincronizar PDF selecionado com o modal integrado
        // Variável global para armazenar o PDF selecionado
        window.pdfSelecionadoGlobal = null;

        function sincronizarPDFComIntegrado(indexLoteamento) {
            // Verificar se o modal integrado está visível
            if (!$('#divCadastroIntegrado').is(':visible')) {
                return; // Modal integrado não está visível, não precisa sincronizar
            }

            // Usar a variável global para sincronizar
            if (window.pdfSelecionadoGlobal) {
                const {
                    loteamento,
                    arquivoPdf,
                    indexLoteamento: indexLoteamentoGlobal
                } = window.pdfSelecionadoGlobal;


                // Sincronizar no modal integrado
                const pdfIntegrado = $(`#opcoesLoteamentosIntegrado input[name="pdf_loteamento_integrado_${indexLoteamentoGlobal}"][data-arquivo="${arquivoPdf}"]`);
                if (pdfIntegrado.length > 0) {
                    // CORREÇÃO: Desmarcar todos os outros PDFs no modal integrado
                    $('#opcoesLoteamentosIntegrado input[name^="pdf_loteamento_integrado_"]').prop('checked', false);
                    // Marcar o PDF correto
                    pdfIntegrado.prop('checked', true);
                }
            }
        }

        // Função para atualizar o botão "Desenhar no PDF" com o PDF selecionado
        function atualizarBotaoDesenharPDF(indexLoteamento) {
            const loteamento = window.loteamentosSelecionados[indexLoteamento];
            if (!loteamento || !loteamento.arquivos_associados || loteamento.arquivos_associados.length === 0) {
                $("#btnLerPDF").addClass("d-none");
                return;
            }

            // CORREÇÃO: Pega o PDF selecionado corretamente
            const pdfSelecionado = $(`input[name="pdf_loteamento_${indexLoteamento}"]:checked`);
            let arquivoSelecionado;

            if (pdfSelecionado.length > 0) {
                // Se há um PDF selecionado, usar ele
                arquivoSelecionado = pdfSelecionado.data('arquivo');
            } else {
                // Se não há PDF selecionado, selecionar o primeiro automaticamente
                const primeiroPDF = $(`input[name="pdf_loteamento_${indexLoteamento}"]:first`);
                if (primeiroPDF.length > 0) {
                    primeiroPDF.prop('checked', true);
                    arquivoSelecionado = primeiroPDF.data('arquivo');
                } else {
                    // Fallback para o primeiro arquivo da lista
                    arquivoSelecionado = loteamento.arquivos_associados[0];
                }
            }

            $("#btnLerPDF").attr('data-loteamento', loteamento.nome);
            $("#btnLerPDF").attr('data-arquivos', arquivoSelecionado);
            $("#btnLerPDF").attr('data-quadricula', dadosOrto[0]['quadricula']);
            $("#btnLerPDF").removeClass("d-none");

            /*
            console.log('Botão atualizado para:', {
                loteamento: loteamento.nome,
                arquivo: arquivoSelecionado,
                quadricula: dadosOrto[0]['quadricula']
            });
            */
        }

        // Função para adicionar desenhos no mapa
        function adicionarDesenhosNoMapa(loteamentos, quadricula) {

            // Limpa desenhos anteriores se existirem
            if (window.loteamentosLayer) {
                window.loteamentosLayer.forEach(polygon => {
                    polygon.setMap(null);
                });
            }
            if (window.loteamentosLabels) {
                window.loteamentosLabels.forEach(marker => {
                    if (marker) {
                        marker.setMap(null);
                    }
                });
            }

            // Cria uma nova camada para os loteamentos
            window.loteamentosLayer = [];
            window.loteamentosLabels = [];

            loteamentos.forEach((loteamento, index) => {

                if (loteamento.coordenadas && loteamento.coordenadas.length > 0) {


                    // CORREÇÃO: Processar apenas o primeiro conjunto de coordenadas (índice 0)
                    const primeiraCoordenada = loteamento.coordenadas[0];

                    if (primeiraCoordenada.type === 'Polygon' && primeiraCoordenada.coordinates) {
                        try {
                            // Converte as coordenadas para o formato do Google Maps
                            const path = primeiraCoordenada.coordinates[0].map(coord => {
                                return {
                                    lat: coord[1],
                                    lng: coord[0]
                                }; // {lat, lng} para Google Maps
                            });

                            // Verificar se temos coordenadas suficientes
                            if (path.length < 3) {
                                console.error(`❌ Polígono ${loteamento.nome} tem apenas ${path.length} pontos - insuficiente para formar polígono`);
                                return;
                            }

                            // Cria o polígono
                            const polygon = new google.maps.Polygon({
                                paths: path,
                                strokeColor: '#B9E2FF',
                                strokeOpacity: 0.8,
                                strokeWeight: 7,
                                fillColor: '#B9E2FF',
                                fillOpacity: 0.2,
                                clickable: false,
                                map: MapFramework.map
                            });

                            // Armazena o nome do loteamento no polígono
                            polygon.nomeLoteamento = loteamento.nome;

                            // Adiciona à camada
                            window.loteamentosLayer.push(polygon);

                            const centroid = calcularCentroidePoligono(path);
                            if (centroid) {
                                const labelElement = criarElementoRotuloLoteamento(loteamento.nome);
                                const labelMarker = new google.maps.marker.AdvancedMarkerElement({
                                    position: centroid,
                                    content: labelElement,
                                    gmpClickable: false,
                                    map: MapFramework.map,
                                    zIndex: 60
                                });
                                window.loteamentosLabels.push(labelMarker);
                            }


                        } catch (error) {
                            console.error(`Erro ao criar polígono para ${loteamento.nome}:`, error);
                        }

                    } else if (primeiraCoordenada.type === 'MultiPolygon' && primeiraCoordenada.coordinates) {

                        try {
                            // CORREÇÃO: Processar TODOS os polígonos do MultiPolygon como UM ÚNICO loteamento
                            const polygonosDoLoteamento = []; // Array para armazenar todos os polígonos deste loteamento

                            primeiraCoordenada.coordinates.forEach((polygonCoords, polygonIndex) => {
                                // Converte as coordenadas para o formato do Google Maps
                                const path = polygonCoords[0].map(coord => {
                                    return {
                                        lat: coord[1],
                                        lng: coord[0]
                                    }; // {lat, lng} para Google Maps
                                });

                                // Cria o polígono
                                const polygon = new google.maps.Polygon({
                                    paths: path,
                                    strokeColor: '#FF8C00',
                                    strokeOpacity: 0.8,
                                    strokeWeight: 7,
                                    fillColor: '#FF8C00',
                                    fillOpacity: 0.2,
                                    clickable: false,
                                    map: MapFramework.map
                                });

                                // Armazena o nome do loteamento no polígono
                                polygon.nomeLoteamento = loteamento.nome;

                                // Adiciona à camada
                                window.loteamentosLayer.push(polygon);

                                // Armazena o polígono para referência futura
                                polygonosDoLoteamento.push(polygon);
                            });

                            // Armazena a referência dos polígonos deste loteamento para uso posterior
                            if (!window.loteamentosPolygons) {
                                window.loteamentosPolygons = {};
                            }
                            window.loteamentosPolygons[loteamento.nome] = polygonosDoLoteamento;

                            const centroid = calcularCentroideMultiPoligono(primeiraCoordenada.coordinates);
                            if (centroid) {
                                const labelElement = criarElementoRotuloLoteamento(loteamento.nome);
                                const labelMarker = new google.maps.marker.AdvancedMarkerElement({
                                    position: centroid,
                                    content: labelElement,
                                    gmpClickable: false,
                                    map: MapFramework.map,
                                    zIndex: 60
                                });
                                window.loteamentosLabels.push(labelMarker);
                            }

                        } catch (error) {
                            console.error(`Erro ao criar MultiPolygon para ${loteamento.nome}:`, error);
                        }
                    }
                } else {}
            });

            // Salva os loteamentos no cache do MapFramework para uso posterior
            if (typeof MapFramework !== 'undefined' && typeof MapFramework.cachearLoteamentos === 'function') {
                MapFramework.cachearLoteamentos();
            }

            /*
            // Ajusta o zoom para mostrar todos os loteamentos
            if (window.loteamentosLayer.length > 0) {
                const bounds = new google.maps.LatLngBounds();
                window.loteamentosLayer.forEach(polygon => {
                    polygon.getPath().forEach(latLng => {
                        bounds.extend(latLng);
                    });
                });
                MapFramework.map.fitBounds(bounds, {
                    padding: 20
                });
            }
            */
        }

        // Função para sair do modo cadastro
        function sairModoCadastro() {
            processandoModoCadastro = true;

            $('#divCadastro').fadeOut(150);

            // Fecha também a divCadastro2 se estiver aberta
            $('#divCadastro2').fadeOut(150);

            // Fecha também a divCadastro3 se estiver aberta
            $('#divCadastro3').fadeOut(150);

            // Volta ao modo normal
            controlarVisibilidadeBotoes('normal');

            // Remove todos os destaques
            removerDestaques();

            // Limpa os desenhos dos loteamentos do mapa
            if (window.loteamentosLayer && window.loteamentosLayer.length > 0) {
                window.loteamentosLayer.forEach(polygon => {
                    polygon.setMap(null);
                });
                window.loteamentosLayer = [];
            }
            if (window.loteamentosLabels && window.loteamentosLabels.length > 0) {
                window.loteamentosLabels.forEach(marker => {
                    if (marker) {
                        marker.setMap(null);
                    }
                });
                window.loteamentosLabels = [];
            }

            //aqui desabilita o clique no poligonos quadra e lote
            arrayCamadas.quadra.forEach(quadra => {
                quadra.setOptions({
                    clickable: true
                });
            });
            arrayCamadas.lote.forEach(lote => {
                lote.setOptions({
                    clickable: true
                });
            });

            // Limpa a seleção dos radio buttons
            $('input[name="loteamento"]').prop('checked', false);
            $('.opcao-loteamento').removeClass('selected');
            $('input[name="quarteirao"]').prop('checked', false);
            $('.opcao-quarteirao').removeClass('selected');

            // Limpa a variável global dos loteamentos
            window.loteamentosSelecionados = null;

            // Limpa os inputs text
            $('#inputLoteAtual').val('');
            $('#inputQuadraAtual').val('');

            // Sai do modo de inserção de marcador se estiver ativo
            if (MapFramework.modoInsercaoMarcador) {
                MapFramework.finalizarDesenho();
            }

            // Limpa a seleção dos lotes
            $('.opcao-lote').removeClass('selected');
            $('.lote-flecha').html('&nbsp;&nbsp;');

            // Limpa variáveis globais do quarteirão
            quarteiraoAtualSelecionado = null;
            quarteiraoIdAtualSelecionado = null;

            $("#btnLerPDF").addClass('d-none');

            // Desmarca o checkbox do modo cadastro
            $('#chkModoCadastro').prop('checked', false);

            processandoModoCadastro = false;
        }

        // Evento para fechar a div de cadastro (botão X)
        $('#btnFecharCadastro').on('click', function() {
            sairModoCadastro();
        });

        $(document).ready(async function() {
            // Verifica se dadosOrto está disponível antes de usar
            if (!dadosOrto || dadosOrto.length === 0) {
                alert('Erro: Dados da ortofoto não estão disponíveis. Verifique se a quadrícula foi passada corretamente na URL.');
                return;
            }

            let coordsInitial = {
                lat: JSON.parse(dadosOrto[0]['latitude']),
                lng: JSON.parse(dadosOrto[0]['longitude'])
            }

            await MapFramework.iniciarMapa('map', coordsInitial, 16);

            // Insere a ortofoto
            await MapFramework.inserirOrtofoto2(dadosOrto[0]["quadricula"]);

            await MapFramework.carregarDesenhosSalvos('paulinia', dadosOrto[0]['quadricula']);

            await MapFramework.carregarDesenhosPrefeitura(dadosOrto[0]['quadricula']);

            MapFramework.carregarLimiteKML();

            MapFramework.carregarQuadriculasKML();

            // Carrega os quarteirões da quadrícula atual
            await MapFramework.carregaQuarteiroes(dadosOrto[0]['quadricula']);

            await MapFramework.carregarPlanilha();

            await MapFramework.carregarMarcadoresSalvos(dadosOrto[0]['quadricula']);

            // Carrega loteamentos em background (invisíveis) para consulta
            await carregarLoteamentosInvisiveis(dadosOrto[0]['quadricula']);

            await MapFramework.carregarImagensAereas(dadosOrto[0]['quadricula']);

            // Carrega os trajetos Streetview da quadrícula no mapa
            MapFramework.carregarStreets(dadosOrto[0]['quadricula']);

            // Carrega as fotos do Streetview da quadrícula no mapa
            MapFramework.carregarStreetviewFotos(dadosOrto[0]['quadricula']);

            // Carrega camadas dinâmicas adicionais de KML
            MapFramework.carregarMaisCamadas();

            // Carrega camadas dinâmicas adicionais de DXF
            MapFramework.carregarMaisCamadasDXF();

            // Inicializa o modo normal (mostra botões principais)
            controlarVisibilidadeBotoes('normal');

            // ========== FUNCIONALIDADE DE FILTRO POR PESQUISA ==========
            let idsDesenhosFiltrados = [];
            let temPesquisaSalva = false;
            let estadoOriginalVisibilidade = new Map(); // Mapa para armazenar estado original: obj => boolean

            // Buscar pesquisa salva e IDs dos desenhos filtrados
            async function carregarDesenhosFiltrados() {
                try {
                    if (!dadosOrto || dadosOrto.length === 0) return;

                    const quadricula = dadosOrto[0]['quadricula'];
                    const response = await fetch(`index_3.php?buscarDesenhosFiltrados=1&quadricula=${quadricula}`);

                    if (!response.ok) return;

                    const data = await response.json();

                    if (data.ids && Array.isArray(data.ids) && data.ids.length > 0) {
                        idsDesenhosFiltrados = data.ids.map(id => parseInt(id));
                        temPesquisaSalva = true;
                        //console.log('Desenhos filtrados encontrados:', idsDesenhosFiltrados.length);
                        // Mostrar checkbox se houver pesquisa
                        $('#liFiltrado').show();
                        $('#liFiltradoCheckbox').show();
                    } else {
                        idsDesenhosFiltrados = [];
                        temPesquisaSalva = false;
                        // Ocultar checkbox se não houver pesquisa
                        $('#liFiltrado').hide();
                        $('#liFiltradoCheckbox').hide();
                    }
                } catch (err) {
                    console.error('Erro ao carregar desenhos filtrados:', err);
                    $('#liFiltrado').hide();
                    $('#liFiltradoCheckbox').hide();
                }
            }

            // Salvar estado original de visibilidade de todos os objetos
            function salvarEstadoOriginal() {
                estadoOriginalVisibilidade.clear();
                Object.keys(arrayCamadas).forEach(camada => {
                    if (arrayCamadas[camada] && Array.isArray(arrayCamadas[camada])) {
                        arrayCamadas[camada].forEach(obj => {
                            if (obj && typeof obj.setMap === 'function') {
                                // Verificar se o objeto está visível
                                let estaVisivel = false;
                                if (typeof obj.getMap === 'function') {
                                    estaVisivel = obj.getMap() !== null;
                                } else if (obj.map !== undefined) {
                                    estaVisivel = obj.map !== null;
                                } else {
                                    // Se não tem método getMap nem propriedade map, assumir que está visível
                                    // (será tratado na restauração)
                                    estaVisivel = true;
                                }
                                estadoOriginalVisibilidade.set(obj, estaVisivel);
                            }
                        });
                    }
                });
            }

            // Aplicar visibilidade baseada nos checkboxes atuais
            function aplicarVisibilidadePorCheckboxes() {
                // Mapear checkboxes para camadas
                const mapeamentoCheckboxes = {
                    'chkQuadras': 'quadra',
                    'chkUnidades': 'unidade',
                    'chkPiscinas': 'piscina',
                    'chkPoligono_lote': 'lote_ortofoto', // Corrigido: chkPoligono_lote controla lote_ortofoto
                    'new_checkLotes': 'poligono_lote', // Adicionado: new_checkLotes controla poligono_lote
                    'chkLotes': 'lote',
                    'chkLimite': 'limite',
                    'chkQuadriculas': 'quadriculas',
                    'chkCondominiosVerticais': 'condominios_verticais',
                    'chkCondominiosHorizontais': 'condominios_horizontais',
                    'chkAreasPublicas': 'areas_publicas',
                    'chkPrefeitura': 'prefeitura',
                    'chkQuarteiroes': 'quarteirao',
                    'chkImagensAereas': 'imagens_aereas',
                    'chkStreetview': 'streetview',
                    'chkStreetviewFotos': 'streetview_fotos'
                };

                // Aplicar visibilidade para cada camada baseado no checkbox
                Object.keys(mapeamentoCheckboxes).forEach(checkboxId => {
                    const camada = mapeamentoCheckboxes[checkboxId];
                    const checkbox = $(`#${checkboxId}`);
                    if (checkbox.length > 0) {
                        const visivel = checkbox.is(':checked');
                        if (MapFramework && MapFramework.alternarVisibilidadeCamada) {
                            MapFramework.alternarVisibilidadeCamada(camada, visivel);
                        }
                    }
                });

                // Tratamento especial para marcadores
                const chkMarcadores = $('#chkMarcadores');
                if (chkMarcadores.length > 0) {
                    const visivel = chkMarcadores.is(':checked');
                    if (visivel && MapFramework && MapFramework.alternarVisibilidadeTodosMarcadores) {
                        MapFramework.alternarVisibilidadeTodosMarcadores(true);
                    } else if (!visivel && MapFramework && MapFramework.alternarVisibilidadeTodosMarcadores) {
                        MapFramework.alternarVisibilidadeTodosMarcadores(false);
                    } else if (arrayCamadas.marcador_quadra) {
                        arrayCamadas.marcador_quadra.forEach(marker => {
                            marker.setMap(visivel ? MapFramework.map : null);
                        });
                    }
                }
            }

            // Controlar visibilidade baseado no filtro
            function toggleFiltrado(mostrarFiltrado) {
                if (!mostrarFiltrado) {
                    // Aplicar visibilidade baseada nos checkboxes atuais
                    aplicarVisibilidadePorCheckboxes();
                } else {
                    // Salvar estado atual antes de aplicar filtro
                    salvarEstadoOriginal();

                    // Mostrar apenas desenhos filtrados
                    Object.keys(arrayCamadas).forEach(camada => {
                        if (arrayCamadas[camada] && Array.isArray(arrayCamadas[camada])) {
                            arrayCamadas[camada].forEach(obj => {
                                if (obj && typeof obj.setMap === 'function') {
                                    // Verificar se o identificador está na lista
                                    // Para polígonos: obj.identificador ou obj.id
                                    // Para marcadores: obj.identificadorBanco
                                    const identificador = obj.identificador || obj.id || obj.identificadorBanco;
                                    const estaNaLista = identificador && idsDesenhosFiltrados.includes(parseInt(identificador));

                                    if (estaNaLista) {
                                        // Mostrar
                                        const mapaParaUsar = obj.mapaRef || MapFramework.map;
                                        obj.setMap(mapaParaUsar);
                                    } else {
                                        // Ocultar
                                        obj.setMap(null);
                                    }
                                }
                            });
                        }
                    });
                }
            }

            // Event listener para checkbox Filtrado
            $('#chkFiltrado').on('change', function() {
                const mostrarFiltrado = $(this).is(':checked');
                toggleFiltrado(mostrarFiltrado);
            });

            // Carregar desenhos filtrados após carregar todos os desenhos
            setTimeout(() => {
                carregarDesenhosFiltrados();
            }, 2000); // Aguardar 2 segundos para garantir que todos os desenhos foram carregados

            // Agora que o mapa foi criado, pode adicionar o listener
            MapFramework.map.getDiv().addEventListener('contextmenu', function(event) {
                if (MapFramework.desenho.modo === 'poligono') {
                    if (MapFramework.cliqueEmVertice) {
                        MapFramework.cliqueEmVertice = false;
                        return;
                    }

                    if (MapFramework.desenho.temporario &&
                        MapFramework.desenho.temporario.getPath().getLength() >= 3) {
                        event.preventDefault();
                        //MapFramework.abrirModalCamada();
                    }
                }
            });

            // no ready, uma vez só
            $('#modalCamada').on('contextmenu', function(e) {
                e.preventDefault();
            });

            // Outros inits
            // Evento para o dropdown de tipo de mapa
            $('#dropdownTipoMapa .dropdown-item').on('click', function(e) {
                e.preventDefault();
                const tipoMapa = $(this).data('tipo');
                MapFramework.alternarTipoMapa(tipoMapa);
            });

            $('#dropCamadas').on('click', function(e) {
                e.stopPropagation();
            });
        });

        // Função para verificar se um ponto está dentro de um polígono
        function pontoDentroDoPoligono(ponto, coordenadasPoligono) {
            // Algoritmo ray casting para verificar se ponto está dentro do polígono
            let dentro = false;
            const x = ponto.lng;
            const y = ponto.lat;

            for (let i = 0, j = coordenadasPoligono.length - 1; i < coordenadasPoligono.length; j = i++) {
                const xi = coordenadasPoligono[i].lng;
                const yi = coordenadasPoligono[i].lat;
                const xj = coordenadasPoligono[j].lng;
                const yj = coordenadasPoligono[j].lat;

                if (((yi > y) !== (yj > y)) && (x < (xj - xi) * (y - yi) / (yj - yi) + xi)) {
                    dentro = !dentro;
                }
            }
            return dentro;
        }

        // Função para verificar se uma linha intersecta com um polígono
        function linhaIntersectaPoligono(linha, coordenadasPoligono) {
            // Verifica se algum ponto da linha está dentro do polígono
            for (let i = 0; i < linha.length; i++) {
                if (pontoDentroDoPoligono(linha[i], coordenadasPoligono)) {
                    return true;
                }
            }

            // Verifica se alguma aresta da linha cruza com alguma aresta do polígono
            for (let i = 0; i < linha.length - 1; i++) {
                const segmentoLinha = [linha[i], linha[i + 1]];

                for (let j = 0; j < coordenadasPoligono.length - 1; j++) {
                    const segmentoPoligono = [coordenadasPoligono[j], coordenadasPoligono[j + 1]];

                    if (segmentosSeCruzam(segmentoLinha, segmentoPoligono)) {
                        return true;
                    }
                }
            }

            return false;
        }

        // Função para verificar se dois segmentos de linha se cruzam
        function segmentosSeCruzam(seg1, seg2) {
            const p1 = seg1[0];
            const p2 = seg1[1];
            const p3 = seg2[0];
            const p4 = seg2[1];

            const d = (p2.lng - p1.lng) * (p4.lat - p3.lat) - (p2.lat - p1.lat) * (p4.lng - p3.lng);

            if (Math.abs(d) < 1e-10) return false; // Linhas paralelas

            const ua = ((p4.lng - p3.lng) * (p1.lat - p3.lat) - (p4.lat - p3.lat) * (p1.lng - p3.lng)) / d;
            const ub = ((p2.lng - p1.lng) * (p1.lat - p3.lat) - (p2.lat - p1.lat) * (p1.lng - p3.lng)) / d;

            return ua >= 0 && ua <= 1 && ub >= 0 && ub <= 1;
        }

        // Função para destacar loteamento selecionado e desenhos relacionados
        function destacarLoteamentoSelecionado(indexLoteamento, selecoesDados_loteamento, selecoesDados_documentos) {

            if (selecoesDados_loteamento != "") {
                //console.log(selecoesDados_loteamento);
                //console.log(selecoesDados_documentos);
                $("#btnLerPDF").attr('data-loteamento', selecoesDados_loteamento);
                $("#btnLerPDF").attr('data-arquivos', selecoesDados_documentos);
                $("#btnLerPDF").attr('data-quadricula', dadosOrto[0]['quadricula']);

                $("#btnLerPDF").removeClass("d-none");
            }

            const nomeLoteamentoSelecionado = window.loteamentosSelecionados && window.loteamentosSelecionados[indexLoteamento] ? window.loteamentosSelecionados[indexLoteamento].nome : null;

            // Remove destaque anterior
            if (window.loteamentosLayer) {
                window.loteamentosLayer.forEach((polygon, i) => {
                    // Verifica se este polígono pertence ao loteamento selecionado
                    let pertenceAoSelecionado = false;

                    if (window.loteamentosSelecionados && window.loteamentosSelecionados[indexLoteamento]) {
                        const nomeLoteamento = window.loteamentosSelecionados[indexLoteamento].nome;

                        // Verifica se este polígono está no array de polígonos deste loteamento
                        if (window.loteamentosPolygons && window.loteamentosPolygons[nomeLoteamento]) {
                            pertenceAoSelecionado = window.loteamentosPolygons[nomeLoteamento].includes(polygon);
                        } else {
                            // Para loteamentos com Polygon simples, verifica pelo índice
                            pertenceAoSelecionado = (i === indexLoteamento);
                        }
                    }

                    if (pertenceAoSelecionado) {
                        // Mantém o loteamento selecionado com cor original e grossura 5
                        polygon.setOptions({
                            strokeColor: '#B9E2FF',
                            fillColor: '#B9E2FF',
                            strokeWeight: 7,
                            fillOpacity: 0.3,
                            zIndex: 4
                        });
                    } else {
                        // Deixa os outros loteamentos cinza com grossura 5
                        polygon.setOptions({
                            strokeColor: '#666666',
                            fillColor: '#cccccc',
                            strokeWeight: 7,
                            strokeOpacity: 1,
                            fillOpacity: 0.0,
                            zIndex: 3
                        });
                    }
                });
            }

            // Controla visibilidade dos rótulos
            // Labels permanecem sempre visíveis

            // Obtém as coordenadas do loteamento selecionado
            if (window.loteamentosSelecionados && window.loteamentosSelecionados[indexLoteamento]) {
                const loteamento = window.loteamentosSelecionados[indexLoteamento];

                if (loteamento.coordenadas && loteamento.coordenadas.length > 0) {
                    // CORREÇÃO: Processar apenas o primeiro conjunto de coordenadas (índice 0)
                    let coordenadasPoligono = [];

                    // Pegar apenas o primeiro conjunto de coordenadas
                    const primeiraCoordenada = loteamento.coordenadas[0];

                    if (primeiraCoordenada.type === 'Polygon' && primeiraCoordenada.coordinates) {
                        // Polygon simples - usar apenas o primeiro conjunto
                        const coords = primeiraCoordenada.coordinates[0].map(coord => ({
                            lat: coord[1],
                            lng: coord[0]
                        }));
                        coordenadasPoligono = coords;
                    } else if (primeiraCoordenada.type === 'MultiPolygon' && primeiraCoordenada.coordinates) {
                        // MultiPolygon - processar todos os polígonos do primeiro conjunto
                        primeiraCoordenada.coordinates.forEach(polygonCoords => {
                            const coords = polygonCoords[0].map(coord => ({
                                lat: coord[1],
                                lng: coord[0]
                            }));
                            coordenadasPoligono = coordenadasPoligono.concat(coords);
                        });
                    }

                    // CORREÇÃO: Função para verificar se uma quadra está dentro do loteamento (Polygon ou MultiPolygon)
                    function quadraEstaDentroDoLoteamento(quadra, loteamentoCoordenadas) {
                        let coordenadasQuadra = null;

                        // Tenta diferentes formas de obter coordenadas da quadra
                        if (quadra.coordenadasGeoJSON && quadra.coordenadasGeoJSON.coordinates) {
                            coordenadasQuadra = quadra.coordenadasGeoJSON.coordinates[0].map(coord => ({
                                lng: coord[0],
                                lat: coord[1]
                            }));
                        } else if (quadra.getPath) {
                            const path = quadra.getPath();
                            coordenadasQuadra = [];

                            for (let i = 0; i < path.getLength(); i++) {
                                const latLng = path.getAt(i);
                                coordenadasQuadra.push({
                                    lng: latLng.lng(),
                                    lat: latLng.lat()
                                });
                            }
                        } else if (quadra.getBounds) {
                            const bounds = quadra.getBounds();
                            const ne = bounds.getNorthEast();
                            const sw = bounds.getSouthWest();
                            coordenadasQuadra = [{
                                    lng: sw.lng(),
                                    lat: sw.lat()
                                },
                                {
                                    lng: ne.lng(),
                                    lat: sw.lat()
                                },
                                {
                                    lng: ne.lng(),
                                    lat: ne.lat()
                                },
                                {
                                    lng: sw.lng(),
                                    lat: ne.lat()
                                }
                            ];
                        }

                        if (coordenadasQuadra && coordenadasQuadra.length > 0) {
                            // CORREÇÃO: Verificar apenas o primeiro conjunto de coordenadas (índice 0)
                            const primeiraCoordenada = loteamentoCoordenadas[0];

                            if (primeiraCoordenada.type === 'Polygon' && primeiraCoordenada.coordinates) {
                                const coords = primeiraCoordenada.coordinates[0].map(coord => ({
                                    lat: coord[1],
                                    lng: coord[0]
                                }));
                                return linhaIntersectaPoligono(coordenadasQuadra, coords);
                            } else if (primeiraCoordenada.type === 'MultiPolygon' && primeiraCoordenada.coordinates) {
                                // Verificar se está dentro de qualquer polígono do MultiPolygon
                                return primeiraCoordenada.coordinates.some(polygonCoords => {
                                    const coords = polygonCoords[0].map(coord => ({
                                        lat: coord[1],
                                        lng: coord[0]
                                    }));
                                    return linhaIntersectaPoligono(coordenadasQuadra, coords);
                                });
                            }
                        }

                        return false;
                    }

                    // Função para ativar as linhas que pertencem a uma quadra
                    function ativarLinhasDaQuadra(quadra) {
                        if (arrayCamadas["lote"]) {
                            arrayCamadas["lote"].forEach(lote => {
                                // Verifica se o lote pertence a esta quadra
                                // lote.id_desenho = ID da quadra pai, quadra.identificador = ID único da quadra
                                if (parseInt(lote.id_desenho) === parseInt(quadra.identificador)) {
                                    // Restaura cor azul do lote, mantendo grossura original
                                    lote.setOptions({
                                        strokeColor: '#0078D7',
                                        fillColor: '#0078D7',
                                        fillOpacity: 0.30
                                        // strokeWeight não é alterado - mantém o original
                                    });
                                    lote.desativado = false;
                                }
                            });
                        }
                    }

                    // Primeiro, deixa TODOS os desenhos cinza (mantendo grossuras originais)
                    if (arrayCamadas["quadra"]) {
                        arrayCamadas["quadra"].forEach(quadra => {
                            // Deixa cinza por padrão, mantendo grossura original
                            quadra.setOptions({
                                strokeColor: 'gray',
                                fillColor: 'gray',
                                fillOpacity: 0.3
                                // strokeWeight não é alterado - mantém o original
                            });
                            quadra.desativado = true;
                        });
                    }

                    if (arrayCamadas["lote"]) {
                        arrayCamadas["lote"].forEach(lote => {
                            // Deixa cinza por padrão, mantendo grossura original
                            lote.setOptions({
                                strokeColor: 'gray',
                                fillColor: 'gray',
                                fillOpacity: 0.3
                                // strokeWeight não é alterado - mantém o original
                            });
                            lote.desativado = true;
                        });
                    }

                    // Agora, verifica cada quadra e ativa se estiver dentro do loteamento
                    if (arrayCamadas["quadra"]) {
                        arrayCamadas["quadra"].forEach(quadra => {
                            if (quadraEstaDentroDoLoteamento(quadra, [loteamento.coordenadas[0]])) {
                                // Quadra está dentro do loteamento - ativa ela com cor azul
                                quadra.setOptions({
                                    strokeColor: '#0078D7',
                                    fillColor: '#0078D7',
                                    fillOpacity: 0.30
                                    // strokeWeight não é alterado - mantém o original
                                });
                                quadra.desativado = false;

                                // Ativa todas as linhas que pertencem a esta quadra
                                ativarLinhasDaQuadra(quadra);
                            }
                        });
                    }
                }
            }
        }

        // Função para abrir a divCadastro2 com os quarteirões do loteamento selecionado
        function abrirDivCadastro2(indexLoteamento) {
            if (window.loteamentosSelecionados && window.loteamentosSelecionados[indexLoteamento]) {
                const loteamento = window.loteamentosSelecionados[indexLoteamento];

                // Atualiza o título da div
                $('#quarteiraoSelecionado').text(loteamento.nome);

                // Popula a lista de quarteirões
                popularQuarteiroes(loteamento);

                // Abre a div flutuante
                $('#divCadastro2').fadeIn(150);

                // IMPORTANTE: NÃO mostra quarteirões automaticamente
                // Só mostra quando o usuário selecionar um radio na divCadastro2
            }
        }

        // Variável global para armazenar os dados dos quarteirões
        let dadosQuarteiroesLoteamentos = null;

        // Variável global para armazenar os dados dos PDFs dos quarteirões
        let dadosPDFsQuarteiroes = null;

        function scrollParaElemento(containerSelector, elemento) {
            const container = $(containerSelector);
            if (!container.length || !elemento || !elemento.length) {
                return;
            }

            const containerHeight = container.outerHeight();
            const itemHeight = elemento.outerHeight();

            const containerOffsetTop = container.offset().top;
            const itemOffsetTop = elemento.offset().top;

            const itemScrollTop = container.scrollTop() + (itemOffsetTop - containerOffsetTop);

            if (itemScrollTop < container.scrollTop()) {
                container.stop().animate({
                    scrollTop: Math.max(itemScrollTop - 20, 0)
                }, 200);
            } else if ((itemScrollTop + itemHeight) > (container.scrollTop() + containerHeight)) {
                const target = itemScrollTop - containerHeight + itemHeight + 20;
                container.stop().animate({
                    scrollTop: Math.max(target, 0)
                }, 200);
            }
        }

        // Função utilitária: rolar modal até elemento visível
        function scrollParaElemento(containerSelector, elemento) {
            const container = $(containerSelector);
            if (!container.length || !elemento || !elemento.length) {
                return;
            }

            const containerHeight = container.outerHeight();
            const itemHeight = elemento.outerHeight();

            const containerOffsetTop = container.offset().top;
            const itemOffsetTop = elemento.offset().top;

            const itemScrollTop = container.scrollTop() + (itemOffsetTop - containerOffsetTop);

            if (itemScrollTop < container.scrollTop()) {
                container.stop().animate({
                    scrollTop: Math.max(itemScrollTop - 20, 0)
                }, 200);
            } else if ((itemScrollTop + itemHeight) > (container.scrollTop() + containerHeight)) {
                const target = itemScrollTop - containerHeight + itemHeight + 20;
                container.stop().animate({
                    scrollTop: Math.max(target, 0)
                }, 200);
            }
        }

        function criarElementoRotuloLoteamento(nomeLoteamento) {
            const el = document.createElement('div');
            el.className = 'rotulo-loteamento';
            el.style.background = 'rgba(185, 226, 255, 0.8)';
            el.style.color = '#027cff';
            el.style.padding = '4px 10px';
            el.style.borderRadius = '8px';
            el.style.fontSize = '13px';
            el.style.fontWeight = '600';
            el.style.whiteSpace = 'nowrap';
            el.style.pointerEvents = 'none';
            el.style.boxShadow = '0 2px 6px rgba(0,0,0,0.35)';
            el.textContent = nomeLoteamento;
            return el;
        }

        function calcularCentroidePoligono(path) {
            if (!path || path.length === 0) {
                return null;
            }

            const bounds = new google.maps.LatLngBounds();
            path.forEach(coord => {
                bounds.extend(new google.maps.LatLng(coord.lat, coord.lng));
            });

            const isEmpty = (typeof bounds.isEmpty === 'function') ? bounds.isEmpty() : false;
            if (isEmpty) {
                return null;
            }

            const centro = bounds.getCenter();
            return centro ? {
                lat: centro.lat(),
                lng: centro.lng()
            } : null;
        }

        function calcularCentroideMultiPoligono(multiPolygonCoordinates) {
            if (!multiPolygonCoordinates || multiPolygonCoordinates.length === 0) {
                return null;
            }

            const bounds = new google.maps.LatLngBounds();

            multiPolygonCoordinates.forEach(polygonCoords => {
                if (!Array.isArray(polygonCoords) || polygonCoords.length === 0) return;

                const anelPrincipal = polygonCoords[0] || [];
                anelPrincipal.forEach(coord => {
                    if (Array.isArray(coord) && coord.length >= 2) {
                        bounds.extend(new google.maps.LatLng(coord[1], coord[0]));
                    }
                });
            });

            const isEmpty = (typeof bounds.isEmpty === 'function') ? bounds.isEmpty() : false;
            if (isEmpty) {
                return null;
            }

            const centro = bounds.getCenter();
            return centro ? {
                lat: centro.lat(),
                lng: centro.lng()
            } : null;
        }

        // Função para carregar os dados complementares dos quarteirões
        function carregarDadosQuarteiroes() {
            if (dadosQuarteiroesLoteamentos) {
                return Promise.resolve(dadosQuarteiroesLoteamentos);
            }

            return $.ajax({
                url: 'correspondencias_quarteiroes/resultado_quarteiroes_loteamentos.json',
                method: 'GET',
                dataType: 'json'
            }).then(function(data) {
                dadosQuarteiroesLoteamentos = data;
                return data;
            }).catch(function(error) {
                console.error('Erro ao carregar dados dos quarteirões:', error);
                return null;
            });
        }

        // Função para sincronizar seleção de loteamento/quarteirão ao clicar em marcadores
        window.sincronizarSelecaoPorMarcador = async function(dadosMarcador = {}) {
            const camadaMarcadoresAtiva = $('#chkMarcadores').is(':checked');
            const camadaLoteamentosAtiva = $('#chkModoCadastro').is(':checked');

            // Só sincroniza se ambas as camadas estiverem visíveis
            if (!camadaMarcadoresAtiva || !camadaLoteamentosAtiva) {
                return;
            }

            const nomeQuarteiraoDestino = (dadosMarcador.quarteirao || '').toString();
            if (!nomeQuarteiraoDestino) {
                return;
            }

            // Garante que os dados complementares estejam carregados
            try {
                await carregarDadosQuarteiroes();
            } catch (error) {
                console.error('Erro ao carregar dados dos quarteirões para sincronização:', error);
            }

            const selecionarQuarteirao = () => {
                const radioQuarteirao = $(`#opcoesQuarteiroes input[name="quarteirao"][data-nome="${nomeQuarteiraoDestino}"]`);
                if (radioQuarteirao.length === 0) {
                    return false;
                }

                const jaSelecionado = radioQuarteirao.prop('checked');
                if (!jaSelecionado) {
                    radioQuarteirao.prop('checked', true).trigger('change');
                } else {
                    destacarQuarteiraoSelecionado(nomeQuarteiraoDestino, radioQuarteirao.val());
                }
                return true;
            };

            const tentarSelecionarQuarteirao = (tentativa = 0) => {
                if (selecionarQuarteirao()) {
                    return;
                }

                if (tentativa >= 10) {
                    console.warn('Não foi possível sincronizar o quarteirão para o marcador clicado:', nomeQuarteiraoDestino);
                    return;
                }

                setTimeout(() => tentarSelecionarQuarteirao(tentativa + 1), 120);
            };

            // Descobre loteamento correspondente ao quarteirão
            let nomeLoteamentoDestino = null;
            if (dadosQuarteiroesLoteamentos) {
                Object.keys(dadosQuarteiroesLoteamentos).some(nomeLoteamento => {
                    const info = dadosQuarteiroesLoteamentos[nomeLoteamento];
                    if (!info || !Array.isArray(info.quarteiroes)) {
                        return false;
                    }

                    const encontrado = info.quarteiroes.some(quarteiraoObj => {
                        const nomeComparacao = (quarteiraoObj.nome || '').toString();
                        return nomeComparacao === nomeQuarteiraoDestino;
                    });

                    if (encontrado) {
                        nomeLoteamentoDestino = nomeLoteamento;
                        return true;
                    }
                    return false;
                });
            }

            // Tenta selecionar o loteamento correspondente
            if (nomeLoteamentoDestino) {
                const radiosLoteamentos = $('#opcoesLoteamentos input[name="loteamento"]');
                const radioLoteamento = radiosLoteamentos.filter(function() {
                    const valor = ($(this).data('loteamento') || '').toString();
                    return valor === nomeLoteamentoDestino;
                });

                if (radioLoteamento.length > 0) {
                    const indiceLoteamento = parseInt(radioLoteamento.val(), 10);

                    if (!radioLoteamento.prop('checked')) {
                        radioLoteamento.prop('checked', true).trigger('change');
                    } else if (!Number.isNaN(indiceLoteamento)) {
                        abrirDivCadastro2(indiceLoteamento);
                    }

                    tentarSelecionarQuarteirao();
                    return;
                }
            }

            // Se não encontrou loteamento correspondente, tenta apenas marcar o quarteirão atual
            tentarSelecionarQuarteirao();
        };

        // Função para carregar os dados dos PDFs dos quarteirões
        function carregarDadosPDFsQuarteiroes(numeroQuarteirao = null) {
            console.log('carregarDadosPDFsQuarteiroes', numeroQuarteirao);
            // Se um número de quarteirão foi especificado, tenta carregar o JSON específico
            if (numeroQuarteirao) {
                return $.ajax({
                    url: `loteamentos_quadriculas/pdfs_quarteiroes/${numeroQuarteirao}/quarteiroes.json`,
                    method: 'GET',
                    dataType: 'json'
                }).then(function(data) {
                    return data;
                }).catch(function(error) {
                    // Se falhar, carrega o arquivo geral e filtra apenas os dados deste quarteirão
                    //console.log(`Arquivo específico não encontrado para quarteirão ${numeroQuarteirao}, usando arquivo geral`);
                    return carregarDadosPDFsQuarteiroes().then(function(dadosGerais) {
                        if (dadosGerais && Array.isArray(dadosGerais)) {
                            // Filtra apenas os dados relacionados ao quarteirão específico
                            return dadosGerais.filter(item =>
                                item.quarteiroes && item.quarteiroes.includes(numeroQuarteirao)
                            );
                        }
                        return [];
                    });
                });
            }

            // Comportamento original para carregamento geral
            if (dadosPDFsQuarteiroes) {
                return Promise.resolve(dadosPDFsQuarteiroes);
            }

            return $.ajax({
                url: 'loteamentos_quadriculas/quarteiroes.json',
                method: 'GET',
                dataType: 'json'
            }).then(function(data) {
                dadosPDFsQuarteiroes = data;
                return data;
            }).catch(function(error) {
                console.error('Erro ao carregar dados dos PDFs dos quarteirões:', error);
                return null;
            });
        }

        // Função para obter informações complementares de um quarteirão
        function obterInfoComplementarQuarteirao(nomeLoteamento, nomeQuarteirao) {
            if (!dadosQuarteiroesLoteamentos || !dadosQuarteiroesLoteamentos[nomeLoteamento]) {
                return null;
            }

            const loteamento = dadosQuarteiroesLoteamentos[nomeLoteamento];
            const quarteirao = loteamento.quarteiroes.find(q => q.nome === nomeQuarteirao);

            if (quarteirao && quarteirao.quadras_unicas) {
                return quarteirao.quadras_unicas.join(', ');
            }

            return null;
        }
        // Função para obter PDFs de um quarteirão específico
        function obterPDFsQuarteirao(nomeQuarteirao, dadosPDFs = null) {
            // Usa os dados passados como parâmetro ou a variável global como fallback
            const dadosParaUsar = dadosPDFs || dadosPDFsQuarteiroes;

            if (!dadosParaUsar || !Array.isArray(dadosParaUsar)) {
                return [];
            }

            // Procura por arquivos que contenham este quarteirão
            const arquivosComQuarteirao = dadosParaUsar.filter(item => {
                return item.quarteiroes && item.quarteiroes.includes(nomeQuarteirao);
            });

            // Retorna os nomes dos arquivos encontrados
            return arquivosComQuarteirao.map(item => {
                // Se os dados vieram de um arquivo específico (pasta do quarteirão), adiciona o caminho
                if (dadosPDFs && dadosPDFs.length > 0 && dadosPDFs[0] && dadosPDFs[0].nome_arquivo) {
                    // Se o nome do arquivo não começa com o caminho da pasta, adiciona o caminho
                    if (!item.nome_arquivo.startsWith(`${nomeQuarteirao}/`)) {
                        return `${nomeQuarteirao}/${item.nome_arquivo}`;
                    }
                }
                // Para a estrutura atual (arquivo geral), usa o caminho padrão
                return `pdfs_quarteiroes/${item.nome_arquivo}`;
            });
        }

        // Função para popular a lista de quarteirões
        function popularQuarteiroes(loteamento) {
            const container = $('#opcoesQuarteiroes');
            container.empty();

            if (!arrayCamadas.quarteirao || arrayCamadas.quarteirao.length === 0) {
                container.html('<div class="alert alert-info">Nenhum quarteirão encontrado para este loteamento.</div>');
                return;
            }

            // Filtra quarteirões que estão dentro do loteamento selecionado
            const quarteiroesDoLoteamento = arrayCamadas.quarteirao.filter(quarteirao => {
                // Só considera quarteirões que têm polígono (não linhas separadas)
                if (!quarteirao.polygon) return false;

                // CORREÇÃO: Processar apenas o primeiro conjunto de coordenadas (índice 0)
                let coordenadasLoteamento = [];

                // Pegar apenas o primeiro conjunto de coordenadas
                const primeiraCoordenada = loteamento.coordenadas[0];

                if (primeiraCoordenada.type === 'Polygon' && primeiraCoordenada.coordinates) {
                    // Polygon simples - usar apenas o primeiro conjunto
                    const coords = primeiraCoordenada.coordinates[0].map(coord => ({
                        lat: coord[1],
                        lng: coord[0]
                    }));
                    coordenadasLoteamento = coords;
                } else if (primeiraCoordenada.type === 'MultiPolygon' && primeiraCoordenada.coordinates) {
                    // MultiPolygon - processar todos os polígonos do primeiro conjunto
                    primeiraCoordenada.coordinates.forEach(polygonCoords => {
                        const coords = polygonCoords[0].map(coord => ({
                            lat: coord[1],
                            lng: coord[0]
                        }));
                        coordenadasLoteamento = coordenadasLoteamento.concat(coords);
                    });
                }

                // Verifica se o quarteirão está dentro do loteamento
                return quarteiraoEstaDentroDoLoteamento(quarteirao, coordenadasLoteamento);
            });

            //mostra os marcadores dos quarteirões de dentro do loteamento
            quarteiroesDoLoteamento.forEach(quarteirao => {
                if (quarteirao.marker) {
                    quarteirao.marker.setMap(MapFramework.map);
                }
            });

            //mostra todos os quarteirões de dentro do loteamento
            console.log('🗺️ Mostrando', quarteiroesDoLoteamento.length, 'quarteirões no mapa');
            quarteiroesDoLoteamento.forEach(quarteirao => {

                quarteirao.polygon.setOptions({
                    clickable: true
                });

                quarteirao.polygon.addListener('click', function() {
                    // Destaca o quarteirão clicado
                    quarteirao.polygon.setOptions({
                        strokeColor: 'yellow',
                    });

                    // Calcula o centro do polígono para centralizá-lo
                    const path = quarteirao.polygon.getPath();
                    const bounds = new google.maps.LatLngBounds();

                    // Adiciona todos os pontos aos bounds
                    for (let i = 0; i < path.getLength(); i++) {
                        bounds.extend(path.getAt(i));
                    }

                    // Centraliza no quarteirão e aplica zoom 18
                    //MapFramework.map.setCenter(bounds.getCenter());
                    //MapFramework.map.setZoom(18);

                    // Seleciona o radio correspondente no divCadastro2
                    const nomeQuarteirao = quarteirao.properties.impreciso_name || quarteirao.id;
                    const radioSelector = `input[name="quarteirao"][data-nome="${nomeQuarteirao}"]`;
                    const radioElement = $(radioSelector);

                    if (radioElement.length > 0) {
                        radioElement.prop('checked', true).trigger('change');

                        // Faz scroll automático para o radio selecionado
                        const radioContainer = $('#divCadastro2 .div-cadastro-body');
                        const radioOption = radioElement.closest('.opcao-quarteirao');

                        if (radioContainer.length > 0 && radioOption.length > 0) {
                            const containerScrollTop = radioContainer.scrollTop();
                            const containerHeight = radioContainer.height();
                            const optionTop = radioOption.position().top;
                            const optionHeight = radioOption.outerHeight();

                            // Calcula se o elemento está visível
                            const isVisible = (optionTop >= 0) && (optionTop + optionHeight <= containerHeight);

                            if (!isVisible) {
                                // Scroll para centralizar o elemento selecionado
                                const targetScrollTop = containerScrollTop + optionTop - (containerHeight / 2) + (optionHeight / 2);
                                radioContainer.animate({
                                    scrollTop: targetScrollTop
                                }, 300);
                            }
                        }
                    }
                });

                //mostra o poligono do quarteirão
                quarteirao.polygon.setMap(MapFramework.map);
                console.log('✅ Quarteirão mostrado no mapa - ID:', quarteirao.id, 'Nome:', quarteirao.properties.impreciso_name);
            });

            if (quarteiroesDoLoteamento.length === 0) {
                container.html('<div class="alert alert-info">Nenhum quarteirão encontrado dentro deste loteamento.</div>');
                return;
            }

            // Carrega os dados complementares e depois cria os botões
            carregarDadosQuarteiroes().then(function() {
                // Ordena os quarteirões numericamente
                quarteiroesDoLoteamento.sort((a, b) => {
                    const nomeA = a.properties.impreciso_name || a.id;
                    const nomeB = b.properties.impreciso_name || b.id;

                    // Extrai números dos nomes dos quarteirões
                    const numeroA = parseInt(nomeA.replace(/\D/g, '')) || 0;
                    const numeroB = parseInt(nomeB.replace(/\D/g, '')) || 0;

                    //console.log(`Comparando: ${nomeA} (${numeroA}) vs ${nomeB} (${numeroB})`);

                    // Ordenação numérica
                    return numeroA - numeroB;
                });

                //console.log('Quarteirões ordenados:', quarteiroesDoLoteamento.map(q => q.properties.impreciso_name || q.id));

                // Array para armazenar todos os elementos criados
                const elementosQuarteiroes = [];
                // Cria os botões radio para cada quarteirão
                quarteiroesDoLoteamento.forEach((quarteirao, index) => {
                    // Obtém o nome do quarteirão (impreciso_name ou id)
                    const nomeQuarteirao = quarteirao.properties.impreciso_name || quarteirao.id;

                    // Busca informações complementares
                    const infoComplementar = obterInfoComplementarQuarteirao(loteamento.nome, nomeQuarteirao);

                    // Carrega os dados dos PDFs específicos para este quarteirão
                    carregarDadosPDFsQuarteiroes(nomeQuarteirao).then(function(dadosPDFs) {
                        // Busca PDFs do quarteirão passando os dados carregados
                        const pdfsQuarteirao = obterPDFsQuarteirao(nomeQuarteirao, dadosPDFs);
                        const temPDFs = pdfsQuarteirao && pdfsQuarteirao.length > 0;

                        // Cria o texto do small baseado nas informações disponíveis
                        let textoSmall = ''; //`ID: ${quarteirao.id}`;
                        if (infoComplementar) {
                            textoSmall += `Quadras: ${infoComplementar}`;
                        }

                        const opcao = $(`
                            <div class="opcao-quarteirao">
                                <div class="d-flex align-items-start">
                                    <input style="margin-top: 2px;" type="radio" id="quarteirao_${quarteirao.id}" data-nome="${nomeQuarteirao}" name="quarteirao" value="${quarteirao.id}">
                                    <label for="quarteirao_${quarteirao.id}" class="flex-grow-1">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div>
                                                Quarteirão ${nomeQuarteirao}
                                                <small class="d-block text-muted">${textoSmall}</small>
                                            </div>
                                            <button type="button" class="btn btn-outline-secondary btn-sm ms-2 btn-docs-quarteirao" data-quarteirao="${nomeQuarteirao}" style="margin-top: -10px; font-size: 10px; padding: 2px 6px; border-radius: 3px;">
                                                Docs
                                            </button>
                                        </div>
                                    </label>
                                </div>
                                ${temPDFs ? 
                                    `<div class="submenu-pdfs">
                                        ${pdfsQuarteirao.map(arquivo => {
                                            // Extrai apenas o nome do arquivo para exibição
                                            const nomeArquivo = arquivo.split('/').pop();
                                            return `<a href="javascript:void(0)" onclick="abrirPDFQuarteirao('${arquivo}')" title="${arquivo}">
                                                <i class="fas fa-file-pdf"></i>${nomeArquivo.length > 20 ? nomeArquivo.substring(0, 20) + '...' : nomeArquivo}
                                            </a>`;
                                        }).join('')}
                                    </div>` : 
                                    '<div class="submenu-pdfs"><em class="text-muted">Sem PDFs</em></div>'
                                }
                            </div>
                        `);

                        // Armazena o elemento no array com o índice correto
                        elementosQuarteiroes[index] = opcao;

                        // Verifica se todos os elementos foram criados
                        if (elementosQuarteiroes.filter(el => el !== undefined).length === quarteiroesDoLoteamento.length) {
                            // Adiciona todos os elementos ao container na ordem correta
                            elementosQuarteiroes.forEach(elemento => {
                                if (elemento) {
                                    container.append(elemento);
                                }
                            });

                            // Adiciona evento para o botão Docs de cada quarteirão usando delegação
                            // IMPORTANTE: Adicionar APÓS os elementos estarem no DOM
                            $(document).off('click', '.btn-docs-quarteirao').on('click', '.btn-docs-quarteirao', function(e) {
                                e.preventDefault();
                                e.stopPropagation();

                                const nomeQuarteirao = $(this).data('quarteirao');
                                console.log('Botão Docs clicado para quarteirão:', nomeQuarteirao);
                                abrirModalGerenciarDocs(nomeQuarteirao);
                            });

                            // Adiciona evento para destacar seleção de quarteirão
                            // IMPORTANTE: Adicionar APÓS os radio buttons estarem no DOM
                            $('input[name="quarteirao"]').off('change').on('change', function() {
                                const quarteiraoId = $(this).val();
                                const nomeQuarteirao = $(this).data('nome');

                                console.log('📻 Radio quarteirão clicado:', nomeQuarteirao, 'ID:', quarteiraoId);

                                // Define as variáveis globais do quarteirão
                                quarteiraoAtualSelecionado = nomeQuarteirao;
                                quarteiraoIdAtualSelecionado = quarteiraoId;

                                if (quarteiraoId) {
                                    // Destaca o quarteirão selecionado passando apenas o ID
                                    destacarQuarteiraoSelecionado(nomeQuarteirao, quarteiraoId);
                                }
                            });
                        }
                    });

                });

            });

        }

        // Função para verificar se um quarteirão está dentro de um loteamento
        function quarteiraoEstaDentroDoLoteamento(quarteirao, coordenadasLoteamento) {
            // Só trabalha com quarteirões que têm polígono
            if (!quarteirao.polygon || !quarteirao.polygon.getPath) return false;

            let coordenadasQuarteirao = [];

            // Obtém as coordenadas do polígono
            const path = quarteirao.polygon.getPath();
            for (let i = 0; i < path.getLength(); i++) {
                const latLng = path.getAt(i);
                coordenadasQuarteirao.push({
                    lng: latLng.lng(),
                    lat: latLng.lat()
                });
            }

            if (coordenadasQuarteirao.length === 0) return false;

            // Verifica se pelo menos um ponto do quarteirão está dentro do loteamento
            return coordenadasQuarteirao.some(ponto =>
                pontoDentroDoPoligono(ponto, coordenadasLoteamento)
            );
        }

        // Função para destacar o quarteirão selecionado
        function destacarQuarteiraoSelecionado(nomeQuarteirao, idQuarteirao) {
            console.log('🎯 destacarQuarteiraoSelecionado chamado:', nomeQuarteirao, idQuarteirao);
            console.log('📊 Total de quarteirões em arrayCamadas:', arrayCamadas.quarteirao ? arrayCamadas.quarteirao.length : 0);

            // Primeiro, redefine TODOS os quarteirões visíveis para cor branca
            if (arrayCamadas.quarteirao) {
                let quarteiresVisiveis = 0;
                arrayCamadas.quarteirao.forEach(obj => {
                    // Só mexe nos quarteirões que estão visíveis no mapa
                    if (obj.polygon && obj.polygon.getMap()) {
                        quarteiresVisiveis++;
                        obj.polygon.setOptions({
                            strokeColor: '#1275C3',
                            strokeWeight: 2,
                            zIndex: 10,
                        });
                    }
                });
                console.log('👁️ Quarteirões visíveis no mapa:', quarteiresVisiveis);
            }

            // Obtém o quarteirão pelo ID usando a função do framework
            const quarteirao = MapFramework.obterQuarteiraoPorId(idQuarteirao);
            console.log('🔍 Quarteirão encontrado:', quarteirao ? 'SIM' : 'NÃO');

            if (!quarteirao) {
                console.error('❌ Quarteirão não encontrado com ID:', idQuarteirao);
                return;
            }

            // Destaca APENAS o quarteirão selecionado em amarelo
            if (quarteirao.polygon) {
                console.log('🎨 Destacando quarteirão com cor branca e peso 5');
                quarteirao.polygon.setOptions({
                    strokeColor: 'white',
                    strokeWeight: 5,
                    zIndex: 15
                });
                quarteirao.polygon.setMap(MapFramework.map);
                console.log('✅ Quarteirão destacado com sucesso!');
            } else {
                console.error('❌ Quarteirão não tem polygon!');
            }

            if (quarteirao.marker) {
                quarteirao.marker.setMap(MapFramework.map);
            }

            // Adiciona classe visual para destacar a opção selecionada
            $('.opcao-quarteirao').removeClass('selected');
            const opcaoQuarteirao = $(`#quarteirao_${idQuarteirao}`).closest('.opcao-quarteirao').addClass('selected');
            scrollParaElemento('#divCadastro2 .div-cadastro-body', opcaoQuarteirao);

            // Automaticamente mostra os marcadores do quarteirão selecionado
            // MAS NÃO marca o checkbox - deixa o usuário decidir se quer ver todos
            if (!$('#chkMarcadores').is(':checked')) {
                MapFramework.mostrarMarcadoresDoQuarteirao(nomeQuarteirao);
            } else if (typeof aplicarFiltroCores === 'function') {
                aplicarFiltroCores();
            }

            // Faz a requisição AJAX para buscar os lotes do quarteirão
            $.ajax({
                url: 'index_procurar_lotes.php',
                type: 'POST',
                async: false,
                cache: false,
                data: {
                    quarteirao: nomeQuarteirao
                },
                success: function(response) {

                    // Parse da resposta JSON
                    let dadosLotes = [];
                    try {
                        if (typeof response === 'string') {
                            dadosLotes = JSON.parse(response);
                        } else {
                            dadosLotes = response;
                        }
                    } catch (e) {
                        console.error('Erro ao fazer parse da resposta:', e);
                        return;
                    }

                    // Popula a divCadastro3 com os lotes
                    popularLotesQuarteirao(dadosLotes);

                    // Mostra o botão Marcador e inputs text
                    $('#btnIncluirMarcador').removeClass('d-none');
                    $('#inputLoteAtual').show();
                    $('#inputQuadraAtual').show();

                    // Abre a divCadastro3
                    //$('#divCadastro3').fadeIn(150);
                },
                error: function(xhr, status, error) {
                    console.error('Erro ao buscar lotes:', error);
                }
            });
        }

        // Função para ordenar lotes numericamente respeitando sufixos alfabéticos
        function ordenarLotesNumericamente(lotes) {
            return lotes.sort((a, b) => {
                const loteA = a.lote.toString();
                const loteB = b.lote.toString();

                // Extrai número e sufixo de cada lote
                const matchA = loteA.match(/^(\d+)([A-Za-z]*)$/);
                const matchB = loteB.match(/^(\d+)([A-Za-z]*)$/);

                if (!matchA || !matchB) {
                    // Se não conseguir extrair, usa ordenação alfabética como fallback
                    return loteA.localeCompare(loteB);
                }

                const numeroA = parseInt(matchA[1]);
                const numeroB = parseInt(matchB[1]);
                const sufixoA = matchA[2] || '';
                const sufixoB = matchB[2] || '';

                // Primeiro compara os números
                if (numeroA !== numeroB) {
                    return numeroA - numeroB;
                }

                // Se os números são iguais, compara os sufixos alfabeticamente
                return sufixoA.localeCompare(sufixoB);
            });
        }

        // Função para popular a divCadastro3 com os lotes do quarteirão
        function popularLotesQuarteirao(dadosLotes) {

            const container = $('#opcoesLotes');
            container.empty();

            $('#quarteiraoSelecionado2').text(quarteiraoAtualSelecionado);
            $('#qtdLotes').text(dadosLotes.length);

            if (!dadosLotes || dadosLotes.length === 0) {
                container.html('<div class="alert alert-info">Nenhum lote encontrado para este quarteirão.</div>');
                return;
            }

            // Agrupa os lotes por quadra
            const lotesPorQuadra = {};

            dadosLotes.forEach(lote => {
                const quadra = lote.quadra;

                if (!lotesPorQuadra[quadra]) {
                    lotesPorQuadra[quadra] = [];
                }
                lotesPorQuadra[quadra].push(lote);
            });

            // Função para verificar se um lote já foi inserido no mapa
            function verificarLoteJaInserido(quadra, numeroLote) {
                if (!arrayCamadas.marcador_quadra || arrayCamadas.marcador_quadra.length === 0) {
                    return false;
                }

                // Procura por um marcador que tenha a mesma quadra, número de lote E quarteirão
                return arrayCamadas.marcador_quadra.some(marker => {
                    return marker.quadra == quadra &&
                        marker.numeroMarcador == numeroLote &&
                        marker.quarteirao == quarteiraoAtualSelecionado;
                });
            }

            // Cria as opções para cada quadra
            Object.keys(lotesPorQuadra).forEach(quadra => {
                const lotes = ordenarLotesNumericamente(lotesPorQuadra[quadra]);

                lotes.forEach((lote, index) => {
                    // Verifica se este lote já foi inserido no mapa
                    const jaInserido = verificarLoteJaInserido(quadra, lote.lote);

                    const opcao = $(`
                        <div class="opcao-lote" data-quadra="${quadra}" data-lote="${lote.lote}">
                            <div class="d-flex align-items-center">
                                <span class="lote-flecha me-2" style="color: #007bff; font-weight: bold;">${index === 0 && !jaInserido ? '>' : '&nbsp;&nbsp;'}</span>
                                <span class="lote-texto">
                                    Quadra: ${quadra} | Lote: ${lote.lote}
                                </span>
                            </div>
                        </div>
                    `);

                    // Se o lote já foi inserido, marca como verde
                    if (jaInserido) {
                        opcao.css({
                            'background-color': '#d4edda',
                            'border-color': '#c3e6cb',
                            'color': '#155724'
                        }).addClass('lote-inserido');
                    }

                    // Adiciona evento de clique
                    opcao.on('click', function() {
                        // Verifica se este lote já foi inserido
                        if ($(this).hasClass('lote-inserido')) {
                            alert('Este lote já foi inserido!');
                            return;
                        }

                        // Remove a flecha de todos os lotes
                        $('.lote-flecha').html('&nbsp;&nbsp;');

                        // Adiciona a flecha ao lote clicado
                        $(this).find('.lote-flecha').html('>');

                        const lote = $(this).data('lote');
                        const quadra = $(this).data('quadra');

                        //console.log('Lote clicado:', lote);
                        //console.log('Quadra clicada:', quadra);

                        // Atualiza os inputs text com o lote e quadra selecionados
                        $('#inputLoteAtual').val(lote);
                        $('#inputQuadraAtual').val(quadra);

                        // Adiciona classe visual para destacar a opção selecionada
                        $('.opcao-lote').removeClass('selected');
                        $(this).addClass('selected');
                    });

                    container.append(opcao);
                });
            });

            // Seleciona o primeiro lote NÃO INSERIDO por padrão
            const lotesDisponiveis = container.find('.opcao-lote:not(.lote-inserido)');
            if (lotesDisponiveis.length > 0) {
                const primeiroLoteDisponivel = lotesDisponiveis.first();
                primeiroLoteDisponivel.trigger('click');
            }
        }

        // Variáveis globais para controle do tooltip
        let marcadorAtualTooltip = null;
        let marcadorIdAtual = null;

        // Variáveis globais para controle do quarteirão e quadra selecionados
        let quarteiraoAtualSelecionado = null;
        let quarteiraoIdAtualSelecionado = null;

        // Função para atualizar a lista de lotes na divCadastro3
        function atualizarListaLotes() {
            // Verifica se a divCadastro3 está visível
            if (!$('#divCadastro3').is(':visible')) {
                return; // Não faz nada se a div não estiver aberta
            }

            // Re-aplica a verificação de lotes já inseridos para todos os itens da lista
            $('.opcao-lote').each(function() {
                const $elemento = $(this);
                const quadra = $elemento.data('quadra');
                const lote = $elemento.data('lote');

                // Verifica se este lote ainda está no mapa (inclui verificação do quarteirão)
                const jaInserido = arrayCamadas.marcador_quadra.some(marker => {
                    return marker.quadra == quadra &&
                        marker.numeroMarcador == lote &&
                        marker.quarteirao == quarteiraoAtualSelecionado;
                });

                if (jaInserido) {
                    // Marca como inserido (verde)
                    $elemento.css({
                        'background-color': '#d4edda',
                        'border-color': '#c3e6cb',
                        'color': '#155724'
                    }).addClass('lote-inserido');
                } else {
                    // Remove marcação de inserido (volta ao normal)
                    $elemento.css({
                        'background-color': '#fafafa',
                        'border-color': '#eee',
                        'color': '#333'
                    }).removeClass('lote-inserido');
                }
            });
        }

        // Função específica para atualizar a lista após deletar marcadores novos
        function atualizarListaAposDeletarMarcadorNovo(quadraDeletada, loteDeletado) {

            // Procura o elemento específico na lista e libera ele
            $(`.opcao-lote`).each(function() {
                const $elemento = $(this);
                const quadraLista = $elemento.data('quadra');
                const loteLista = $elemento.data('lote');

                // Compara convertendo ambos para string para garantir match
                if (String(quadraLista) === String(quadraDeletada) && String(loteLista) === String(loteDeletado)) {

                    // Remove marcação de inserido (volta ao normal)
                    $elemento.css({
                        'background-color': '#fafafa',
                        'border-color': '#eee',
                        'color': '#333'
                    }).removeClass('lote-inserido');

                    // Se este era o lote selecionado, mantém a seleção
                    if ($elemento.hasClass('selected')) {
                        $elemento.addClass('selected');
                    }

                    return false; // Para o loop quando encontra
                }
            });
        }

        // Função para mostrar InfoWindow do marcador
        function mostrarTooltipMarcador(marker, event) {
            marcadorAtualTooltip = marker;
            marcadorIdAtual = marker.identificadorBanco; // ID no banco

            // Pega o loteamento selecionado na tela
            const loteamentoSelecionado = $('input[name="loteamento"]:checked').data('loteamento') || 'N/A';

            // Mostra loading no InfoWindow
            const infoWindow = new google.maps.InfoWindow({
                content: '<div style="padding: 10px; text-align: center;"><i class="fas fa-spinner fa-spin"></i> Carregando dados...</div>'
            });

            infoWindow.open(MapFramework.map, marker);

            // Busca dados do marcador
            $.ajax({
                url: 'buscar_dados_marcador.php',
                method: 'GET',
                data: {
                    id_marcador: marker.identificadorBanco
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'sucesso') {
                        const dados = response.dados;
                        let content = '<div style="padding: 15px; min-width: 250px;">';

                        // Dados da tabela desenhos
                        content += '<h6 style="margin: 0 0 10px 0; color: #333; border-bottom: 1px solid #ddd; padding-bottom: 5px;">Desenhos</h6>';
                        content += '<div style="margin-bottom: 15px;">';
                        content += '<div style="margin-bottom: 3px;"><strong>Quadricula:</strong> ' + (dados.desenhos.quadricula || 'N/A') + '</div>';
                        content += '<div style="margin-bottom: 3px;"><strong>Loteamento:</strong> ' + loteamentoSelecionado + '</div>';
                        content += '<div style="margin-bottom: 3px;"><strong>Quarteirão:</strong> ' + (dados.desenhos.quarteirao || 'N/A') + '</div>';
                        content += '<div style="margin-bottom: 3px;"><strong>Quadra:</strong> ' + (dados.desenhos.quadra || 'N/A') + '</div>';
                        content += '<div style="margin-bottom: 3px;"><strong>Lote:</strong> ' + (dados.desenhos.lote || 'N/A') + '</div>';
                        content += '<div style="margin-bottom: 3px;"><strong>Desenho:</strong> ' + (dados.desenhos.id || 'N/A') + '</div>';
                        content += '</div>';

                        // Dados da tabela cadastro (se existir)
                        if (dados.cadastro) {
                            content += '<h6 style="margin: 0 0 10px 0; color: #333; border-bottom: 1px solid #ddd; padding-bottom: 5px;">Cadastro</h6>';
                            content += '<div style="margin-bottom: 15px;">';
                            content += '<div style="margin-bottom: 3px;"><strong>ID Imobiliário:</strong> ' + (dados.cadastro.imob_id || 'N/A') + '</div>';
                            content += '<div style="margin-bottom: 3px;"><strong>Rua:</strong> ' + (dados.cadastro.logradouro || 'N/A') + '</div>';
                            content += '<div style="margin-bottom: 3px;"><strong>Número:</strong> ' + (dados.cadastro.numero || 'N/A') + '</div>';
                            content += '<div style="margin-bottom: 3px;"><strong>Bairro:</strong> ' + (dados.cadastro.bairro || 'N/A') + '</div>';
                            content += '</div>';
                        } else {
                            content += '<div style="margin-bottom: 15px; color: #666; font-style: italic;">Nenhum dado encontrado na tabela cadastro</div>';
                        }

                        // Botões de ação
                        content += '<div style="text-align: center; margin-top: 10px;">';
                        content += '<button id="btnEditMarcadorInfoWindow" class="btn btn-warning btn-sm" style="background-color: #ffc107; color: black; border: none; padding: 5px 15px; border-radius: 3px; cursor: pointer; margin-right: 10px;">';
                        content += '<i class="fas fa-edit"></i> Editar';
                        content += '</button>';
                        content += '<button id="btnDeleteMarcadorInfoWindow" class="btn btn-danger btn-sm" style="background-color: #dc3545; color: white; border: none; padding: 5px 15px; border-radius: 3px; cursor: pointer;">';
                        content += '<i class="fas fa-trash"></i> Deletar';
                        content += '</button>';
                        content += '</div>';

                        // Botão salvar (inicialmente oculto)
                        content += '<div id="divSalvarMarcador" style="text-align: center; margin-top: 10px; display: none;">';
                        content += '<button id="btnSalvarMarcadorInfoWindow" class="btn btn-success btn-sm" style="background-color: #28a745; color: white; border: none; padding: 5px 15px; border-radius: 3px; cursor: pointer;">';
                        content += '<i class="fas fa-save"></i> Salvar';
                        content += '</button>';
                        content += '</div>';

                        content += '</div>';

                        // Atualiza o InfoWindow com os dados
                        infoWindow.setContent(content);

                        // Adiciona eventos aos botões
                        setTimeout(() => {
                            // Evento do botão deletar
                            $('#btnDeleteMarcadorInfoWindow').on('click', function() {
                                if (confirm('Tem certeza que deseja deletar este marcador?')) {
                                    deletarMarcador(marcadorIdAtual, marker);
                                    infoWindow.close();
                                }
                            });

                            // Evento do botão editar
                            $('#btnEditMarcadorInfoWindow').on('click', function() {
                                entrarModoEdicaoMarcador(dados.desenhos, infoWindow, dados.desenhos.id);
                            });

                            // Evento do botão salvar
                            $('#btnSalvarMarcadorInfoWindow').on('click', function() {
                                salvarEdicaoMarcador(marcadorIdAtual, infoWindow, marker);
                            });
                        }, 100);

                    } else {
                        infoWindow.setContent('<div style="padding: 10px; color: red;">Erro ao carregar dados: ' + response.mensagem + '</div>');
                    }
                },
                error: function() {
                    infoWindow.setContent('<div style="padding: 10px; color: red;">Erro ao carregar dados do marcador</div>');
                }
            });
        }

        // Função para esconder tooltip
        function esconderTooltipMarcador() {
            $('#tooltipMarcador').hide();
            marcadorAtualTooltip = null;
            marcadorIdAtual = null;
        }

        // Função para entrar no modo de edição do marcador
        function entrarModoEdicaoMarcador(dadosDesenhos, infoWindow, idDesenho) {
            // Pega o loteamento selecionado na tela
            const loteamentoSelecionado = $('input[name="loteamento"]:checked').data('loteamento') || 'N/A';

            // Monta o conteúdo em modo de edição
            let content = '<div style="padding: 15px; min-width: 250px;">';

            // Dados da tabela desenhos (em modo edição)
            content += '<h6 style="margin: 0 0 10px 0; color: #333; border-bottom: 1px solid #ddd; padding-bottom: 5px;">Desenhos</h6>';
            content += '<div style="margin-bottom: 15px;">';
            content += '<div style="margin-bottom: 3px;"><strong>Quadrícula:</strong> ' + (dadosDesenhos.quadricula || 'N/A') + '</div>';
            content += '<div style="margin-bottom: 3px;"><strong>Loteamento:</strong> ' + loteamentoSelecionado + '</div>';
            content += '<div style="margin-bottom: 3px;"><strong>Quarteirão:</strong> <input type="text" id="editQuarteirao" value="' + (dadosDesenhos.quarteirao || '') + '" style="width: 100px; padding: 2px; border: 1px solid #ccc; border-radius: 3px;"></div>';
            content += '<div style="margin-bottom: 3px;"><strong>Quadra:</strong> <input type="text" id="editQuadra" value="' + (dadosDesenhos.quadra || '') + '" style="width: 100px; padding: 2px; border: 1px solid #ccc; border-radius: 3px;"></div>';
            content += '<div style="margin-bottom: 3px;"><strong>Lote:</strong> <input type="text" id="editLote" value="' + (dadosDesenhos.lote || '') + '" style="width: 100px; padding: 2px; border: 1px solid #ccc; border-radius: 3px;"></div>';
            content += '<div style="margin-bottom: 3px;"><strong>Desenho:</strong> ' + (dadosDesenhos.id || 'N/A') + '</div>';
            content += '</div>';

            // Dados da tabela cadastro (se existir) - apenas visualização
            content += '<h6 style="margin: 0 0 10px 0; color: #333; border-bottom: 1px solid #ddd; padding-bottom: 5px;">Cadastro</h6>';
            content += '<div style="margin-bottom: 15px; color: #666; font-style: italic;">Dados do cadastro não podem ser editados aqui</div>';

            // Botão salvar
            content += '<div id="divSalvarMarcador" style="text-align: center; margin-top: 10px;">';
            content += '<button id="btnSalvarMarcadorInfoWindow" class="btn btn-success btn-sm" style="background-color: #28a745; color: white; border: none; padding: 5px 15px; border-radius: 3px; cursor: pointer;">';
            content += '<i class="fas fa-save"></i> Salvar';
            content += '</button>';
            content += '</div>';

            content += '</div>';

            // Atualiza o InfoWindow
            infoWindow.setContent(content);

            // Adiciona evento ao botão salvar
            setTimeout(() => {
                $('#btnSalvarMarcadorInfoWindow').on('click', function() {
                    salvarEdicaoMarcador(idDesenho, infoWindow, marcadorAtualTooltip);
                });
            }, 100);
        }

        // Função para salvar a edição do marcador
        function salvarEdicaoMarcador(idMarcador, infoWindow, marker) {
            console.log('ID do marcador para edição:', idMarcador);

            const quarteirao = $('#editQuarteirao').val().trim();
            const quadra = $('#editQuadra').val().trim();
            const lote = $('#editLote').val().trim();

            if (!quarteirao || !quadra || !lote) {
                alert('Todos os campos são obrigatórios!');
                return;
            }

            // Salva a posição do marcador antes de enviar
            let posicaoMarcador = null;
            if (arrayCamadas.marcador_quadra) {
                for (let i = 0; i < arrayCamadas.marcador_quadra.length; i++) {
                    if (arrayCamadas.marcador_quadra[i].identificadorBanco == idMarcador) {
                        posicaoMarcador = {
                            lat: arrayCamadas.marcador_quadra[i].position.lat,
                            lng: arrayCamadas.marcador_quadra[i].position.lng
                        };
                        break;
                    }
                }
            }

            // Verifica se o lote existe no divCadastro3 para definir a cor
            let correspondeAoLoteSelecionado = false;
            $('#divCadastro3 .opcao-lote').each(function() {
                const loteItem = $(this).data('lote');
                if (loteItem == lote) {
                    correspondeAoLoteSelecionado = true;
                    return false; // break
                }
            });

            // Define a cor baseada na verificação
            const corFinal = correspondeAoLoteSelecionado ? '#32CD32' : '#FF0000'; // Verde ou Vermelho

            // Envia dados para o servidor incluindo a cor
            $.ajax({
                url: 'editar_marcador.php',
                method: 'POST',
                data: {
                    id_marcador: idMarcador,
                    quarteirao: quarteirao,
                    quadra: quadra,
                    lote: lote,
                    cor: corFinal
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'sucesso') {
                        // Remove o marcador antigo do array e do mapa
                        if (arrayCamadas.marcador_quadra) {
                            for (let i = arrayCamadas.marcador_quadra.length - 1; i >= 0; i--) {
                                if (arrayCamadas.marcador_quadra[i].identificadorBanco == idMarcador) {
                                    arrayCamadas.marcador_quadra[i].setMap(null); // Remove do mapa
                                    arrayCamadas.marcador_quadra.splice(i, 1); // Remove do array
                                    break;
                                }
                            }
                        }

                        // Recria o marcador com os novos dados
                        if (posicaoMarcador) {
                            MapFramework.recriarMarcadorEditado({
                                id: idMarcador,
                                quarteirao: quarteirao,
                                quadra: quadra,
                                lote: lote,
                                cor: corFinal,
                                lat: posicaoMarcador.lat,
                                lng: posicaoMarcador.lng
                            });
                        }

                        infoWindow.close();
                    } else {
                        alert('Erro ao editar marcador: ' + (response.mensagem || 'Erro desconhecido'));
                    }
                },
                error: function() {
                    alert('Erro ao editar marcador no servidor.');
                }
            });
        }

        // Sistema de controle de visibilidade dos botões por modo
        function controlarVisibilidadeBotoes(modoAtivo) {
            // Lista de todos os botões de modos
            const botoesModos = [
                'btnIncluirPoligono', // Modo Quadra
                'btnIncluirLinha', // Modo Lote
                'btnIncluirMarcador', // Modo Marcador
                'btnLerPDF', // Modo PDF
                'btnFinalizarDesenho', // Botão de sair do desenho
                'btnSairModoMarcador', // Botão de sair do marcador
                'btnEditar', // Botão de editar
                'btnExcluir', // Botão de excluir
                'btnSairEdicao' // Botão de sair da edição
            ];

            // Oculta todos os botões primeiro
            botoesModos.forEach(botaoId => {
                $(`#${botaoId}`).addClass('d-none');
            });

            // Mostra apenas os botões do modo ativo
            switch (modoAtivo) {
                case 'quadra':
                    $('#btnFinalizarDesenho').removeClass('d-none');
                    break;

                case 'lote':
                    $('#btnFinalizarDesenho').removeClass('d-none');
                    break;

                case 'marcador':
                    // Modo marcador é um submodo do cadastro
                    $('#btnSairModoMarcador').removeClass('d-none');
                    break;

                case 'cadastro':
                    // Modo cadastro agora é controlado pelo checkbox
                    // Não há botão específico a mostrar
                    break;

                case 'pdf':
                    // No modo PDF, não oculta outros botões pois é um modo especial
                    break;

                case 'normal':
                default:
                    // Modo normal - mostra botões principais
                    $('#btnIncluirPoligono').removeClass('d-none');
                    $('#btnIncluirLinha').removeClass('d-none');
                    // btnCadastro foi removido - agora é checkbox
                    // Botões de editar/excluir só aparecem se há quadra selecionada
                    // (serão controlados pelo framework.js)
                    break;
            }
        }

        // Função para voltar ao modo cadastro (usado quando sai do modo marcador)
        function voltarModoCadastro() {
            controlarVisibilidadeBotoes('cadastro');
            // Oculta os inputs text do marcador
            $('#inputLoteAtual').hide();
            $('#inputQuadraAtual').hide();
        }

        // Evento do botão deletar no tooltip
        $('#btnDeleteMarcador').on('click', function() {
            if (!marcadorIdAtual || !marcadorAtualTooltip) return;

            deletarMarcador(marcadorIdAtual, marcadorAtualTooltip);
        });

        // Função para deletar marcador via AJAX
        function deletarMarcador(idMarcador, marcadorElement) {
            $.ajax({
                url: 'deletarMarcador.php',
                method: 'POST',
                data: {
                    id: idMarcador
                },
                success: function(response) {
                    try {
                        let resultado = response;
                        if (typeof response === 'string') {
                            resultado = JSON.parse(response);
                        }

                        if (resultado.status === 'sucesso') {
                            // Remove o marcador do mapa
                            marcadorElement.setMap(null);

                            // Remove da camada
                            // Guarda os dados do marcador antes de remover
                            const quadraMarcador = marcadorElement.quadra;
                            const loteMarcador = marcadorElement.numeroMarcador;

                            const index = arrayCamadas['marcador_quadra'].indexOf(marcadorElement);
                            if (index > -1) {
                                arrayCamadas['marcador_quadra'].splice(index, 1);
                            }

                            // Usa a função específica para marcadores novos
                            atualizarListaAposDeletarMarcadorNovo(quadraMarcador, loteMarcador);

                            // Esconde o tooltip
                            esconderTooltipMarcador();

                        } else {
                            alert('Erro ao deletar marcador: ' + (resultado.mensagem || 'Erro desconhecido'));
                        }
                    } catch (e) {
                        alert('Erro ao processar resposta do servidor');
                        console.error('Erro:', e);
                    }
                },
                error: function(xhr, status, error) {
                    alert('Erro na comunicação com o servidor');
                    console.error('Erro AJAX:', error);
                }
            });
        }

        // Esconde tooltip quando clicar fora
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#tooltipMarcador').length && !$(e.target).hasClass('marcador-personalizado')) {
                esconderTooltipMarcador();
            }
        });

        // Função para popular os controles integrados com dados do divCadastro principal
        function popularControlesIntegrados(quadricula) {

            // Atualizar título da quadrícula
            $('#quadriculaAtualIntegrado').text(quadricula);

            // Copiar loteamentos do divCadastro principal
            const loteamentosHtml = $('#opcoesLoteamentos').html();
            $('#opcoesLoteamentosIntegrado').html(loteamentosHtml);

            // Ajustar IDs para evitar conflitos
            ajustarIDsControlesIntegrados();

            // Mostrar o divCadastro integrado
            $('#divCadastroIntegrado').show();

            // Sincronizar com as seleções originais
            sincronizarSelecaoInicial();

            // Adicionar eventos para os controles integrados
            adicionarEventosControlesIntegrados();

        }

        // Função para sincronizar seleção inicial com os controles originais
        function sincronizarSelecaoInicial() {

            // Encontrar loteamento selecionado no original
            const loteamentoOriginal = $('input[name="loteamento"]:checked');
            if (loteamentoOriginal.length > 0) {
                const indexLoteamento = loteamentoOriginal.val();
                const nomeLoteamento = loteamentoOriginal.data('loteamento');


                // Selecionar o mesmo loteamento no integrado
                $(`#opcoesLoteamentosIntegrado input[name="loteamentoIntegrado"][value="${indexLoteamento}"]`).prop('checked', true);
                $(`#opcoesLoteamentosIntegrado input[name="loteamentoIntegrado"][value="${indexLoteamento}"]`).closest('.opcao-loteamento').addClass('selected');

                // Encontrar PDF selecionado no original
                const pdfOriginal = $(`input[name="pdf_loteamento_${indexLoteamento}"]:checked`);
                if (pdfOriginal.length > 0) {
                    const arquivoPdf = pdfOriginal.data('arquivo');
                    const pdfIndex = pdfOriginal.val(); // Índice do PDF na lista

                    // CORREÇÃO: Selecionar o PDF correto no modal integrado sem desmarcar outros
                    const pdfIntegrado = $(`#opcoesLoteamentosIntegrado input[name="pdf_loteamento_integrado_${indexLoteamento}"][data-arquivo="${arquivoPdf}"]`);
                    if (pdfIntegrado.length > 0) {
                        // Desmarcar apenas os PDFs do mesmo loteamento
                        $(`#opcoesLoteamentosIntegrado input[name="pdf_loteamento_integrado_${indexLoteamento}"]`).prop('checked', false);
                        // Marcar o PDF correto
                        pdfIntegrado.prop('checked', true);
                    } else {
                        // Fallback: tentar por índice
                        const pdfIntegradoFallback = $(`#opcoesLoteamentosIntegrado input[name="pdf_loteamento_integrado_${indexLoteamento}"][value="${pdfIndex}"]`);
                        if (pdfIntegradoFallback.length > 0) {
                            $(`#opcoesLoteamentosIntegrado input[name="pdf_loteamento_integrado_${indexLoteamento}"]`).prop('checked', false);
                            pdfIntegradoFallback.prop('checked', true);
                        }
                    }

                    // CORREÇÃO: Não carregar PDF aqui para evitar carregamento duplo
                    // O PDF será carregado pela função abrirLeitorPDF

                    // SEMPRE abrir divCadastro2Integrado se há um PDF selecionado
                    abrirDivCadastro2Integrado(parseInt(indexLoteamento));
                } else {
                    // CORREÇÃO: Não selecionar automaticamente o primeiro PDF para evitar sobrescrever a sincronização
                }
            } else {
                // CORREÇÃO: Não carregar primeiro PDF automaticamente se há um PDF sendo carregado via abrirLeitorPDF
                if (window.carregandoPDFViaAbrirLeitorPDF || (window.dadosLeitorPDF && window.dadosLeitorPDF.arquivo)) {} else {
                    // CORREÇÃO: Não carregar primeiro PDF automaticamente para evitar conflitos
                    // carregarPrimeiroPDFAutomatico();
                }
            }
        }

        // Função para ajustar IDs dos controles integrados para evitar conflitos
        function ajustarIDsControlesIntegrados() {

            // Mudar IDs e names dos loteamentos integrados
            $('#opcoesLoteamentosIntegrado input[name="loteamento"]').each(function(index) {
                const novoId = `loteamento_integrado_${index}`;
                $(this).attr('id', novoId);
                $(this).attr('name', 'loteamentoIntegrado');
                $(this).next('label').attr('for', novoId);
            });

            // Mudar IDs e names dos PDFs integrados
            $('#opcoesLoteamentosIntegrado input[name^="pdf_loteamento_"]').each(function() {
                const name = $(this).attr('name');
                const index = name.match(/\d+/)[0];
                const pdfIndex = $(this).val();
                const novoId = `pdf_integrado_${index}_${pdfIndex}`;
                const novoName = `pdf_loteamento_integrado_${index}`;

                $(this).attr('id', novoId);
                $(this).attr('name', novoName);
                $(this).next('label').attr('for', novoId);
            });

            // Ajustar IDs dos containers de PDFs
            $('#opcoesLoteamentosIntegrado .submenu-pdfs').each(function(index) {
                $(this).attr('id', `pdfs_loteamento_integrado_${index}`);
            });

            // Todos os PDFs começam habilitados nos controles integrados
            $('#opcoesLoteamentosIntegrado input[name^="pdf_loteamento_integrado_"]').prop('disabled', false);

            // CORREÇÃO: Não limpar seleções dos PDFs para manter a seleção do usuário
            // $('#opcoesLoteamentosIntegrado input[name^="pdf_loteamento_integrado_"]').prop('checked', false);

            // CORREÇÃO: Não selecionar automaticamente o primeiro PDF
            // const primeiroPDF = $(`#opcoesLoteamentosIntegrado input[name="pdf_loteamento_integrado_${indexLoteamento}"]:first`);
            // if (primeiroPDF.length > 0) {
            //     primeiroPDF.prop('checked', true);
            // }

            // CORREÇÃO: Não limpar seleções dos loteamentos para manter a seleção do usuário
            // $('#opcoesLoteamentosIntegrado input[name="loteamentoIntegrado"]').prop('checked', false);

        }

        // Função para carregar automaticamente o primeiro PDF
        function carregarPrimeiroPDFAutomatico() {

            // Aguardar um pouco para garantir que os IDs foram ajustados
            setTimeout(() => {
                // Selecionar primeiro loteamento integrado
                const primeiroLoteamento = $('#opcoesLoteamentosIntegrado input[name="loteamentoIntegrado"]:first');
                if (primeiroLoteamento.length > 0) {

                    // Garantir que apenas este loteamento está selecionado
                    $('#opcoesLoteamentosIntegrado input[name="loteamentoIntegrado"]').prop('checked', false);
                    $('#opcoesLoteamentosIntegrado .opcao-loteamento').removeClass('selected');

                    primeiroLoteamento.prop('checked', true);
                    primeiroLoteamento.closest('.opcao-loteamento').addClass('selected');

                    const indexLoteamento = primeiroLoteamento.val();

                    // Garantir que apenas o primeiro PDF está selecionado
                    $('#opcoesLoteamentosIntegrado input[name^="pdf_loteamento_integrado_"]').prop('checked', false);

                    // Selecionar primeiro PDF do primeiro loteamento
                    const primeiroPDF = $(`#opcoesLoteamentosIntegrado input[name="pdf_loteamento_integrado_${indexLoteamento}"]:first`);
                    if (primeiroPDF.length > 0) {
                        primeiroPDF.prop('checked', true);


                        // Carregar o PDF automaticamente
                        const loteamento = primeiroPDF.data('loteamento');
                        const arquivo = primeiroPDF.data('arquivo');
                        const quadricula = primeiroPDF.data('quadricula') || window.dadosLeitorPDF.quadricula;


                        // Aguardar um pouco para o viewer estar pronto
                        setTimeout(async () => {
                            if (window.pdfViewerIntegrado && window.pdfViewerIntegrado.loadSpecificPDF) {
                                await window.pdfViewerIntegrado.loadSpecificPDF(loteamento, arquivo, quadricula);
                            }
                        }, 500);
                    }
                }
            }, 200);
        }

        // Função removida: sincronização não é mais necessária - controles são independentes

        // Função para interceptar e modificar o HTML dos quarteirões
        function modificarHtmlQuarteires(html) {

            // Criar um elemento temporário para manipular o HTML
            const tempDiv = $('<div>').html(html);

            // Para cada quarteirão, modificar a estrutura das quadras (mantendo duplicidades para sincronização)
            tempDiv.find('.opcao-quarteirao').each(function() {
                const quarteiraoElement = $(this);
                const inputElement = quarteiraoElement.find('input');
                const smallText = quarteiraoElement.find('small').text();

                // Preservar o ID único do quarteirão
                const quarteiraoId = inputElement.attr('id');
                const quarteiraoValue = inputElement.val();
                const quarteiraoNome = inputElement.data('nome');


                // Extrair quadras do texto "Quadras: A, B, C"
                const quadrasMatch = smallText.match(/Quadras:\s*(.+)/);
                if (quadrasMatch && quadrasMatch[1]) {
                    const quadrasText = quadrasMatch[1].trim();
                    const quadras = quadrasText.split(',').map(q => q.trim()).filter(q => q.length > 0);

                    if (quadras.length > 0) {
                        // Remover o texto small original
                        quarteiraoElement.find('small').remove();

                        // Adicionar as quadras como radio buttons
                        quadras.forEach((quadra) => {
                            const quadraHtml = `
                                <div class="opcao-quadra" style="margin-bottom: 3px; padding-left: 20px;">
                                    <input type="radio" name="quadraIntegrado_${quarteiraoValue}" value="${quadra}" data-quarteirao="${quarteiraoNome}" data-quarteirao-id="${quarteiraoValue}" style="margin-right: 6px;">
                                    <label style="font-size: 11px; color: #555; cursor: pointer; margin: 0;">
                                        ${quadra}
                                    </label>
                                </div>
                            `;
                            quarteiraoElement.append(quadraHtml);
                        });
                    }
                }
            });

            return tempDiv.html();
        }

        // Função para copiar o estado do divCadastro2 para o integrado
        function copiarDivCadastro2ParaIntegrado() {

            // Copiar HTML do divCadastro2
            const titulo = $('#quarteiraoSelecionado').text();
            let quarteiresHtml = $('#opcoesQuarteiroes').html();

            // Interceptar e modificar o HTML antes de inserir
            quarteiresHtml = modificarHtmlQuarteires(quarteiresHtml);

            $('#quarteiraoSelecionadoIntegrado').text(titulo);
            $('#opcoesQuarteiresIntegrado').html(quarteiresHtml);

            // Ajustar IDs dos inputs copiados - PRESERVAR IDs ÚNICOS DOS QUARTEIRÕES
            $('#opcoesQuarteiresIntegrado input[name="quarteirao"]').attr('name', 'quarteiraoIntegrado');
            $('#opcoesQuarteiresIntegrado input[id]').each(function() {
                const oldId = $(this).attr('id');
                const newId = oldId + 'Integrado';
                $(this).attr('id', newId);

                // Atualizar labels correspondentes
                $(`#opcoesQuarteiresIntegrado label[for="${oldId}"]`).attr('for', newId);
            });


            // Verificar se há quarteirão selecionado no original e sincronizar
            const quarteiraoOriginal = $('input[name="quarteirao"]:checked');
            if (quarteiraoOriginal.length > 0) {
                const quarteiraoNome = quarteiraoOriginal.data('nome');
                const quarteiraoId = quarteiraoOriginal.val(); // ID único do quarteirão

                // Selecionar o mesmo no integrado usando o ID único
                $(`#opcoesQuarteiresIntegrado input[name="quarteiraoIntegrado"][value="${quarteiraoId}"]`).prop('checked', true);
                $(`#opcoesQuarteiresIntegrado input[name="quarteiraoIntegrado"][value="${quarteiraoId}"]`).closest('.opcao-quarteirao').addClass('selected');

                // Atualizar variáveis globais com informações completas
                window.quarteiraoAtualDesenho = quarteiraoId; // ID único
                window.quarteiraoIdAtualDesenho = quarteiraoId; // ID único (mesmo valor)
                window.quarteiraoNumeroAtualDesenho = quarteiraoNome; // Número do quarteirão

                // Resetar modos de desenho ao trocar de quarteirão
                if (window.pdfViewerIntegrado) {
                    window.pdfViewerIntegrado.onQuarteiraoQuadraChanged();
                }
            } else {
                // Limpar seleções no integrado
                $('#opcoesQuarteiresIntegrado input[name="quarteiraoIntegrado"]').prop('checked', false);
                $('#opcoesQuarteiresIntegrado .opcao-quarteirao').removeClass('selected');
                window.quarteiraoAtualDesenho = null;
                window.quarteiraoIdAtualDesenho = null;
                window.quarteiraoNumeroAtualDesenho = null;

                // Resetar modos de desenho ao limpar quarteirão
                if (window.pdfViewerIntegrado) {
                    window.pdfViewerIntegrado.onQuarteiraoQuadraChanged();
                }
            }

            // Adicionar eventos para os quarteirões integrados
            adicionarEventosQuarteiresIntegrados();

            // Quadras já foram adicionadas durante a interceptação do HTML
            // Adicionar eventos para as quadras
            adicionarEventosQuadrasIntegradas();

            // Adicionar eventos para sincronização bidirecional
            adicionarSincronizacaoQuarteiroes();

            // Atualizar botões baseado no estado atual
            if (window.pdfViewerIntegrado && window.pdfViewerIntegrado.updateButtonsVisibility) {
                window.pdfViewerIntegrado.updateButtonsVisibility();
            }

            // Mostrar divCadastro2 integrado
            $('#divCadastro2Integrado').show();
        }

        // Função para adicionar eventos das quadras (agora integrada no HTML interceptado)
        function adicionarEventosQuadrasIntegradas() {

            // Eventos para quadras integradas (radio buttons)
            $('#opcoesQuarteiresIntegrado input[name^="quadraIntegrado_"]').off('change').on('change', function(e) {
                const quadra = $(this).val();
                const quarteirao = $(this).data('quarteirao');
                const quarteiraoId = $(this).data('quarteirao-id');
                const isChecked = $(this).is(':checked');

                // Verificar se o quarteirão está selecionado
                const quarteiraoSelecionado = $(`#opcoesQuarteiresIntegrado input[name="quarteiraoIntegrado"][value="${quarteiraoId}"]`).is(':checked');

                if (!quarteiraoSelecionado) {
                    // Se o quarteirão não está selecionado, desmarcar o radio silenciosamente
                    $(this).prop('checked', false);
                    return; // Sair da função sem executar o resto
                }


                // Desativar modo de desenho ao trocar quadra
                if (window.pdfViewerIntegrado && window.pdfViewerIntegrado.deactivateDrawingMode) {
                    window.pdfViewerIntegrado.deactivateDrawingMode();
                }

                if (isChecked) {
                    // Atualizar variável global de quadra
                    window.quadraAtualDesenho = quadra;

                    // Remover seleção visual de outras quadras do mesmo quarteirão
                    $(`input[name="quadraIntegrado_${quarteiraoId}"]`).closest('.opcao-quadra').removeClass('selected');
                    // Adicionar seleção visual à quadra atual
                    $(this).closest('.opcao-quadra').addClass('selected');

                } else {
                    // Se desmarcou, limpar a quadra atual
                    window.quadraAtualDesenho = null;
                    $(this).closest('.opcao-quadra').removeClass('selected');
                }

                // Resetar modos de desenho ao trocar de quadra
                if (window.pdfViewerIntegrado) {
                    window.pdfViewerIntegrado.onQuarteiraoQuadraChanged();
                }

                // Atualizar visibilidade dos botões de desenho
                if (window.pdfViewerIntegrado && window.pdfViewerIntegrado.updateButtonsVisibility) {
                    window.pdfViewerIntegrado.updateButtonsVisibility();
                }
            });

            // Permitir click em toda a opcao-quadra para selecionar
            $('#opcoesQuarteiresIntegrado .opcao-quadra').off('click').on('click', function(e) {
                const input = $(this).find('input[type="radio"]');
                if (input.length > 0) {
                    const quarteirao = input.data('quarteirao');
                    const quarteiraoId = input.data('quarteirao-id');

                    // Verificar se o quarteirão está selecionado
                    const quarteiraoSelecionado = $(`#opcoesQuarteiresIntegrado input[name="quarteiraoIntegrado"][value="${quarteiraoId}"]`).is(':checked');

                    if (!quarteiraoSelecionado) {
                        // Se o quarteirão não está selecionado, não fazer nada
                        return;
                    }

                    // Só prevenir comportamento padrão se NÃO for clique direto no radio
                    if (!$(e.target).is('input[type="radio"]')) {
                        e.preventDefault();
                    }
                    e.stopPropagation();

                    input.prop('checked', true);
                    input.trigger('change');
                }
            });
        }

        // Função para adicionar eventos aos quarteirões integrados
        function adicionarEventosQuarteiresIntegrados() {
            $('#opcoesQuarteiresIntegrado input[name="quarteiraoIntegrado"]').off('change').on('change', function(e) {
                // NÃO usar preventDefault nem stopPropagation para permitir comportamento nativo do radio

                const nomeQuarteirao = $(this).data('nome');
                const quarteiraoId = $(this).val(); // ID único do quarteirão

                // Desativar modo de desenho ao trocar quarteirão
                if (window.pdfViewerIntegrado && window.pdfViewerIntegrado.deactivateDrawingMode) {
                    window.pdfViewerIntegrado.deactivateDrawingMode();
                }

                // Atualizar variáveis globais de quarteirão
                window.quarteiraoAtualDesenho = quarteiraoId;
                window.quarteiraoIdAtualDesenho = quarteiraoId;
                window.quarteiraoNumeroAtualDesenho = nomeQuarteirao;

                // Resetar modos de desenho ao trocar de quarteirão
                if (window.pdfViewerIntegrado) {
                    window.pdfViewerIntegrado.onQuarteiraoQuadraChanged();
                }

                // Limpar todos os radio buttons das quadras ao trocar de quarteirão
                $('#opcoesQuarteiresIntegrado input[name^="quadraIntegrado_"]').prop('checked', false);
                $('#opcoesQuarteiresIntegrado .opcao-quadra').removeClass('selected');
                window.quadraAtualDesenho = null;

                // Resetar modos de desenho ao limpar quadra
                if (window.pdfViewerIntegrado) {
                    window.pdfViewerIntegrado.onQuarteiraoQuadraChanged();
                }

                // Remover seleção visual de outros quarteirões
                $('#opcoesQuarteiresIntegrado .opcao-quarteirao').removeClass('selected');
                // Adicionar seleção visual ao quarteirão atual
                $(this).closest('.opcao-quarteirao').addClass('selected');

                // Destacar o quarteirão selecionado no mapa usando o ID único
                if (typeof destacarQuarteiraoSelecionado === 'function') {
                    destacarQuarteiraoSelecionado(nomeQuarteirao, quarteiraoId);
                }

                // Atualizar visibilidade dos botões de desenho
                if (window.pdfViewerIntegrado && window.pdfViewerIntegrado.updateButtonsVisibility) {
                    window.pdfViewerIntegrado.updateButtonsVisibility();
                }

            });

            // Evitar scroll ao clicar em labels e elementos da lista
            $('#opcoesQuarteiresIntegrado label').off('click').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                const input = $(this).prev('input[type="radio"]');
                if (input.length > 0) {
                    input.prop('checked', true);
                    input.trigger('change');
                }
            });

            // Permitir click em toda a opcao-quarteirao para selecionar
            $('#opcoesQuarteiresIntegrado .opcao-quarteirao').off('click').on('click', function(e) {
                // Só prevenir comportamento padrão se NÃO for clique direto no radio
                if (!$(e.target).is('input[type="radio"]')) {
                    e.preventDefault();
                }
                e.stopPropagation();

                const input = $(this).find('input[type="radio"]');
                if (input.length > 0) {
                    input.prop('checked', true);
                    input.trigger('change');
                }
            });
        }

        // Função para adicionar sincronização bidirecional entre quarteirões
        function adicionarSincronizacaoQuarteiroes() {

            // Sincronizar do original para o integrado
            $('#opcoesQuarteiroes input[name="quarteirao"]').off('change.sync').on('change.sync', function() {
                const quarteiraoNome = $(this).data('nome');
                const quarteiraoId = $(this).val(); // ID único do quarteirão

                // Limpar seleções no integrado
                $('#opcoesQuarteiresIntegrado input[name="quarteiraoIntegrado"]').prop('checked', false);
                $('#opcoesQuarteiresIntegrado .opcao-quarteirao').removeClass('selected');

                // Limpar todos os radio buttons das quadras
                $('#opcoesQuarteiresIntegrado input[name^="quadraIntegrado_"]').prop('checked', false);
                $('#opcoesQuarteiresIntegrado .opcao-quadra').removeClass('selected');
                window.quadraAtualDesenho = null;

                // Resetar modos de desenho ao limpar quadra
                if (window.pdfViewerIntegrado) {
                    window.pdfViewerIntegrado.onQuarteiraoQuadraChanged();
                }

                // Selecionar o mesmo no integrado usando o ID único
                if (quarteiraoId) {
                    $(`#opcoesQuarteiresIntegrado input[name="quarteiraoIntegrado"][value="${quarteiraoId}"]`).prop('checked', true);
                    $(`#opcoesQuarteiresIntegrado input[name="quarteiraoIntegrado"][value="${quarteiraoId}"]`).closest('.opcao-quarteirao').addClass('selected');
                    window.quarteiraoAtualDesenho = quarteiraoId;
                    window.quarteiraoIdAtualDesenho = quarteiraoId;
                    window.quarteiraoNumeroAtualDesenho = quarteiraoNome;
                } else {
                    window.quarteiraoAtualDesenho = null;
                    window.quarteiraoIdAtualDesenho = null;
                    window.quarteiraoNumeroAtualDesenho = null;
                }

                // Atualizar botões
                if (window.pdfViewerIntegrado && window.pdfViewerIntegrado.updateButtonsVisibility) {
                    window.pdfViewerIntegrado.updateButtonsVisibility();
                }
            });

            // Sincronizar do integrado para o original
            $('#opcoesQuarteiresIntegrado input[name="quarteiraoIntegrado"]').off('change.sync').on('change.sync', function() {
                const quarteiraoNome = $(this).data('nome');
                const quarteiraoId = $(this).val(); // ID único do quarteirão

                // IMPORTANTE: Limpar seleções no integrado primeiro (corrige problema visual)
                $('#opcoesQuarteiresIntegrado input[name="quarteiraoIntegrado"]').prop('checked', false);
                $('#opcoesQuarteiresIntegrado .opcao-quarteirao').removeClass('selected');

                // Limpar todos os radio buttons das quadras
                $('#opcoesQuarteiresIntegrado input[name^="quadraIntegrado_"]').prop('checked', false);
                $('#opcoesQuarteiresIntegrado .opcao-quadra').removeClass('selected');
                window.quadraAtualDesenho = null;

                // Resetar modos de desenho ao limpar quadra
                if (window.pdfViewerIntegrado) {
                    window.pdfViewerIntegrado.onQuarteiraoQuadraChanged();
                }

                // Marcar apenas o quarteirão atual no integrado
                $(this).prop('checked', true);
                $(this).closest('.opcao-quarteirao').addClass('selected');

                // Limpar seleções no original
                $('#opcoesQuarteiroes input[name="quarteirao"]').prop('checked', false);
                $('#opcoesQuarteiroes .opcao-quarteirao').removeClass('selected');

                // Selecionar o mesmo no original usando o ID único
                if (quarteiraoId) {
                    $(`#opcoesQuarteiroes input[name="quarteirao"][value="${quarteiraoId}"]`).prop('checked', true);
                    $(`#opcoesQuarteiroes input[name="quarteirao"][value="${quarteiraoId}"]`).closest('.opcao-quarteirao').addClass('selected');
                    window.quarteiraoAtualDesenho = quarteiraoId;
                    window.quarteiraoIdAtualDesenho = quarteiraoId;
                    window.quarteiraoNumeroAtualDesenho = quarteiraoNome;
                } else {
                    window.quarteiraoAtualDesenho = null;
                    window.quarteiraoIdAtualDesenho = null;
                    window.quarteiraoNumeroAtualDesenho = null;
                }

                // Atualizar botões
                if (window.pdfViewerIntegrado && window.pdfViewerIntegrado.updateButtonsVisibility) {
                    window.pdfViewerIntegrado.updateButtonsVisibility();
                }
            });
        }

        // Função para adicionar eventos aos controles integrados
        function adicionarEventosControlesIntegrados() {
            // Eventos para loteamentos integrados (novos IDs)
            $('#opcoesLoteamentosIntegrado input[name="loteamentoIntegrado"]').off('change').on('change', function() {
                const indexSelecionado = parseInt($(this).val());

                // Desativar modo de desenho ao trocar loteamento
                if (window.pdfViewerIntegrado && window.pdfViewerIntegrado.deactivateDrawingMode) {
                    window.pdfViewerIntegrado.deactivateDrawingMode();
                }

                // Destacar visualmente
                $('#opcoesLoteamentosIntegrado .opcao-loteamento').removeClass('selected');
                $(this).closest('.opcao-loteamento').addClass('selected');

                // CORREÇÃO: Não limpar seleções de PDFs para manter a seleção do usuário
                // $('#opcoesLoteamentosIntegrado input[name^="pdf_loteamento_integrado_"]').prop('checked', false);

                // Limpar quarteirão atual
                window.quarteiraoAtualDesenho = null;
                window.quarteiraoIdAtualDesenho = null;
                window.quarteiraoNumeroAtualDesenho = null;

                // CORREÇÃO: Usar variável global para selecionar o PDF correto
                if (window.pdfSelecionadoGlobal && window.pdfSelecionadoGlobal.indexLoteamento === indexSelecionado) {
                    const pdfCorreto = $(`#opcoesLoteamentosIntegrado input[name="pdf_loteamento_integrado_${indexSelecionado}"][data-arquivo="${window.pdfSelecionadoGlobal.arquivoPdf}"]`);
                    if (pdfCorreto.length > 0) {
                        pdfCorreto.prop('checked', true);
                        pdfCorreto.trigger('change'); // Dispara o evento para carregar o PDF
                    }
                } else {
                    // Fallback: selecionar o primeiro PDF apenas se não há variável global
                    const primeiroPDF = $(`#opcoesLoteamentosIntegrado input[name="pdf_loteamento_integrado_${indexSelecionado}"]:first`);
                    if (primeiroPDF.length > 0) {
                        primeiroPDF.prop('checked', true);
                        primeiroPDF.trigger('change'); // Dispara o evento para carregar o PDF
                    }
                }

                // CORREÇÃO: Abrir divCadastro2Integrado automaticamente

                // Sincronizar com divCadastro original para popular divCadastro2
                const loteamentoOriginal = $(`input[name="loteamento"][value="${indexSelecionado}"]`);
                if (loteamentoOriginal.length > 0) {
                    // Selecionar o mesmo loteamento no original
                    loteamentoOriginal.prop('checked', true);
                    loteamentoOriginal.trigger('change'); // Dispara o evento para popular divCadastro2

                    // Aguardar um pouco e depois copiar para integrado
                    setTimeout(() => {
                        abrirDivCadastro2Integrado(indexSelecionado);
                    }, 300);
                } else {
                    // CORREÇÃO: Mesmo se não encontrar no original, tentar abrir divCadastro2Integrado
                    setTimeout(() => {
                        abrirDivCadastro2Integrado(indexSelecionado);
                    }, 300);
                }
            });

            // Eventos para PDFs integrados (novos IDs)
            $('#opcoesLoteamentosIntegrado input[name^="pdf_loteamento_integrado_"]').off('change').on('change', async function() {
                const loteamento = $(this).data('loteamento');
                const arquivo = $(this).data('arquivo');
                const quadricula = $(this).data('quadricula') || window.dadosLeitorPDF.quadricula;


                // Desativar modo de desenho ao trocar PDF
                if (window.pdfViewerIntegrado && window.pdfViewerIntegrado.deactivateDrawingMode) {
                    window.pdfViewerIntegrado.deactivateDrawingMode();
                }

                // IMPORTANTE: Desmarcar todos os outros PDFs primeiro
                $('#opcoesLoteamentosIntegrado input[name^="pdf_loteamento_integrado_"]').prop('checked', false);
                // Marcar apenas o PDF selecionado
                $(this).prop('checked', true);

                // Encontrar e selecionar o loteamento correspondente
                const nomeInput = $(this).attr('name');
                const indexLoteamento = nomeInput.match(/pdf_loteamento_integrado_(\d+)/)[1];

                // Selecionar o loteamento do PDF
                $('#opcoesLoteamentosIntegrado .opcao-loteamento').removeClass('selected');
                $(`#opcoesLoteamentosIntegrado input[name="loteamentoIntegrado"][value="${indexLoteamento}"]`).prop('checked', true);
                $(`#opcoesLoteamentosIntegrado input[name="loteamentoIntegrado"][value="${indexLoteamento}"]`).closest('.opcao-loteamento').addClass('selected');

                // Limpar quarteirão atual
                window.quarteiraoAtualDesenho = null;
                window.quarteiraoIdAtualDesenho = null;
                window.quarteiraoNumeroAtualDesenho = null;

                // Carregar o PDF no viewer
                if (window.pdfViewerIntegrado && window.pdfViewerIntegrado.loadSpecificPDF) {
                    await window.pdfViewerIntegrado.loadSpecificPDF(loteamento, arquivo, quadricula);
                }

                // Abrir quarteirões se necessário
                abrirDivCadastro2Integrado(parseInt(indexLoteamento));
            });
        }

        // Função para abrir divCadastro2 integrado  
        function abrirDivCadastro2Integrado(indexLoteamento) {

            // Verificar se divCadastro2 está visível
            if ($('#divCadastro2').is(':visible')) {
                copiarDivCadastro2ParaIntegrado();
            } else {}
        }

        // Variáveis globais para o leitor de PDF integrado
        let pdfViewerIntegrado = null;
        let leitorPDFAtivo = false;

        // Variáveis globais para controle do quarteirão e quadra atual para desenho
        window.quarteiraoAtualDesenho = null;
        window.quarteiraoIdAtualDesenho = null;
        window.quarteiraoNumeroAtualDesenho = null;
        window.quadraAtualDesenho = null;

        // Inicializar input de lote com valor 1
        $(document).ready(function() {
            $('#inputLoteAtualIntegrado').val('1');

            // Permitir texto no input (não apenas números)
            $('#inputLoteAtualIntegrado').on('input', function() {
                const value = $(this).val();
                // Permitir qualquer texto, mas remover caracteres especiais perigosos
                const cleanValue = value.replace(/[<>]/g, '');
                if (value !== cleanValue) {
                    $(this).val(cleanValue);
                }
            });
        });

        // Função para abrir o leitor de PDF integrado
        function abrirLeitorPDF(loteamento, arquivo, quadricula) {

            // Preparar dados globais para o PDF viewer
            window.dadosLeitorPDF = {
                loteamento: loteamento,
                arquivo: arquivo, // Agora é um único arquivo
                quadricula: quadricula
            };

            // Exibir a div do leitor PDF
            $('#divLeitorPDF').show();

            // Auto-scroll para baixo para mostrar o leitor
            $('html, body').animate({
                scrollTop: $('#divLeitorPDF').offset().top
            }, 500);

            // Marcar como ativo
            leitorPDFAtivo = true;

            // CORREÇÃO: Definir flag para evitar carregamento automático
            window.carregandoPDFViaAbrirLeitorPDF = true;

            // Inicializar o PDF viewer PRIMEIRO (sempre criar nova instância para evitar problemas)
            console.log('Inicializando PDF viewer integrado...');
            pdfViewerIntegrado = new PDFViewerIntegrado();

            // Aguardar inicialização e depois mostrar controles integrados
            setTimeout(() => {
                popularControlesIntegrados(quadricula);

                // CORREÇÃO: Aguardar mais um pouco para garantir que a sincronização aconteça
                setTimeout(() => {
                    // Forçar sincronização com o PDF selecionado no modal original
                    const loteamentoOriginal = $('input[name="loteamento"]:checked');
                    if (loteamentoOriginal.length > 0) {
                        const indexLoteamento = loteamentoOriginal.val();
                        console.log('🔄 Forçando sincronização final:', {
                            indexLoteamento,
                            arquivo
                        });
                        sincronizarPDFComIntegrado(parseInt(indexLoteamento));
                    }

                    // CORREÇÃO: Carregar o PDF correto diretamente
                    setTimeout(() => {
                        if (window.pdfViewerIntegrado && window.pdfViewerIntegrado.loadSpecificPDF) {
                            console.log('📄 Carregando PDF final:', {
                                loteamento,
                                arquivo,
                                quadricula
                            });
                            window.pdfViewerIntegrado.loadSpecificPDF(loteamento, arquivo, quadricula);
                        }

                        // Resetar flag
                        window.carregandoPDFViaAbrirLeitorPDF = false;
                    }, 200);
                }, 500);
            }, 300);

            // Expor globalmente para acesso externo
            window.pdfViewerIntegrado = pdfViewerIntegrado;

            // Carregamento gerenciado pelo pdfViewerIntegrado.js
        }

        // Função removida: controles sempre visíveis agora

        // Função para fechar o leitor de PDF
        function fecharLeitorPDF() {
            //console.log'Fechando leitor PDF integrado');

            // Desativar modo de desenho antes de fechar
            if (pdfViewerIntegrado && pdfViewerIntegrado.deactivateDrawingMode) {
                pdfViewerIntegrado.deactivateDrawingMode();
            }

            // Limpar recursos do PDF viewer se necessário
            if (pdfViewerIntegrado && pdfViewerIntegrado.cleanup) {
                pdfViewerIntegrado.cleanup();
            }

            // Ocultar a div
            $('#divLeitorPDF').hide();

            // Esconder controles integrados
            $('#divCadastroIntegrado').hide();
            $('#divCadastro2Integrado').hide();

            // Controles permanecem sempre visíveis

            // Auto-scroll de volta para o mapa
            $('html, body').animate({
                scrollTop: $('#map').offset().top
            }, 500);

            // Marcar como inativo
            leitorPDFAtivo = false;

            // Limpar dados globais
            window.dadosLeitorPDF = null;
            window.quarteiraoAtualDesenho = null;
            window.quarteiraoIdAtualDesenho = null;
            window.quarteiraoNumeroAtualDesenho = null;

            // Resetar variável global para permitir nova inicialização
            pdfViewerIntegrado = null;
            window.pdfViewerIntegrado = null;
        }

        // Event listeners para o leitor PDF integrado
        $(document).ready(function() {
            // Botão fechar leitor PDF
            $('#btnFecharLeitorPDF').on('click', function() {
                fecharLeitorPDF();
            });
        });

        // Função para remover todos os destaques
        function removerDestaques() {
            // Remove destaque dos loteamentos
            if (window.loteamentosLayer) {
                window.loteamentosLayer.forEach(polygon => {
                    polygon.setOptions({
                        strokeColor: 'gray',
                        fillColor: 'gray',
                        strokeWeight: 2,
                        fillOpacity: 0.4
                    });
                });
            }

            // Restaura cores originais de todas as quadras (mantendo grossuras originais)
            if (arrayCamadas["quadra"]) {
                arrayCamadas["quadra"].forEach(quadra => {
                    quadra.setOptions({
                        strokeColor: quadra.corOriginal || 'red',
                        fillColor: quadra.corOriginal || 'red',
                        fillOpacity: 0.30
                        // strokeWeight não é alterado - mantém o original
                    });
                    quadra.desativado = false;
                });
            }

            // Restaura cores originais de todos os lotes (mantendo grossuras originais)
            if (arrayCamadas["lote"]) {
                arrayCamadas["lote"].forEach(lote => {
                    lote.setOptions({
                        strokeColor: lote.corOriginal || 'red',
                        fillColor: lote.corOriginal || 'red',
                        fillOpacity: 0.30
                        // strokeWeight não é alterado - mantém o original
                    });
                    lote.desativado = false;
                });
            }

            // OCULTA COMPLETAMENTE todos os quarteirões do mapa
            if (arrayCamadas.quarteirao) {
                arrayCamadas.quarteirao.forEach(obj => {
                    if (obj.polygon) obj.polygon.setMap(null);
                    if (obj.marker) obj.marker.setMap(null);
                    if (obj.polyline) obj.polyline.setMap(null);
                });
            }

            // Esconde o botão Marcador e inputs text
            $('#btnIncluirMarcador').addClass('d-none');
            $('#inputLoteAtual').hide();
            $('#inputQuadraAtual').hide();

            // Oculta todos os marcadores quando não há quarteirão selecionado
            // MAS respeita o estado do checkbox - se estiver marcado, mantém todos visíveis
            if (!$('#chkMarcadores').is(':checked')) {
                MapFramework.mostrarMarcadoresDoQuarteirao(null);
            }
        }

        // ============================================================================
        // FUNÇÕES DOS LOTES DA PREFEITURA MOVIDAS PARA O FRAMEWORK.JS
        // ============================================================================
        // As funções carregarLotesPrefeitura() e toggleLotesPrefeitura() foram 
        // movidas para o framework.js como MapFramework.carregarLotesGeojson() e 
        // MapFramework.toggleLotesGeojson() respectivamente.
        // 
        // Isso garante que os polígonos sejam criados corretamente na instância 
        // do Google Maps e sigam o padrão arquitetural do sistema.
        // ============================================================================

        // ============================================================================
        // FUNÇÕES DO CONTROLE DE DESENHOS DA PREFEITURA
        // Variáveis para armazenar as coordenadas originais dos desenhos
        let coordenadasOriginaisDesenhos = [];
        let desenhosCarregados = false;

        // Função para obter a distância selecionada
        function obterDistancia() {
            const distanciaSelecionada = $('input[name="distancia"]:checked').val();
            return parseFloat(distanciaSelecionada);
        }

        // Função para converter metros para graus WGS84
        function metrosParaGraus(metros, latitude) {
            // Aproximação para conversão de metros para graus
            const grausLat = metros / 111320; // 1 grau de latitude ≈ 111.32 km
            const grausLng = metros / (111320 * Math.cos(latitude * Math.PI / 180));
            return {
                lat: grausLat,
                lng: grausLng
            };
        }

        // Função para mover desenhos em uma direção específica
        function moverDesenhosPrefeitura(direcao) {
            const distancia = obterDistancia();
            const camadaLotes = "lotesPref";
            const destinoLotes = arrayCamadas[camadaLotes] ? camadaLotes : 'semCamadas';

            if (!arrayCamadas[destinoLotes] || arrayCamadas[destinoLotes].length === 0) {
                alert('Nenhum desenho carregado para mover.');
                return;
            }

            // Salva coordenadas originais na primeira movimentação
            if (!desenhosCarregados) {
                salvarCoordenadasOriginais();
                desenhosCarregados = true;
            }

            // Define os offsets baseados na direção
            let offsetLat = 0,
                offsetLng = 0;

            switch (direcao) {
                case 'norte':
                    offsetLat = distancia;
                    break;
                case 'sul':
                    offsetLat = -distancia;
                    break;
                case 'leste':
                    offsetLng = distancia;
                    break;
                case 'oeste':
                    offsetLng = -distancia;
                    break;
                case 'nordeste':
                    offsetLat = distancia;
                    offsetLng = distancia;
                    break;
                case 'noroeste':
                    offsetLat = distancia;
                    offsetLng = -distancia;
                    break;
                case 'sudeste':
                    offsetLat = -distancia;
                    offsetLng = distancia;
                    break;
                case 'sudoeste':
                    offsetLat = -distancia;
                    offsetLng = -distancia;
                    break;
            }

            // Move cada polígono
            arrayCamadas[destinoLotes].forEach(function(polygon) {
                if (polygon.getPath && polygon.setPath) {
                    const path = polygon.getPath();
                    const newPath = [];

                    for (let i = 0; i < path.getLength(); i++) {
                        const point = path.getAt(i);
                        const grausOffset = metrosParaGraus(1, point.lat());

                        const newPoint = new google.maps.LatLng(
                            point.lat() + (offsetLat * grausOffset.lat),
                            point.lng() + (offsetLng * grausOffset.lng)
                        );
                        newPath.push(newPoint);
                    }

                    polygon.setPath(newPath);
                }
            });
        }

        // Função para salvar coordenadas originais
        function salvarCoordenadasOriginais() {
            const camadaLotes = "lotesPref";
            const destinoLotes = arrayCamadas[camadaLotes] ? camadaLotes : 'semCamadas';

            if (arrayCamadas[destinoLotes] && arrayCamadas[destinoLotes].length > 0) {
                coordenadasOriginaisDesenhos = [];

                arrayCamadas[destinoLotes].forEach(function(polygon, index) {
                    if (polygon.getPath) {
                        const path = polygon.getPath();
                        const coordinates = [];

                        for (let i = 0; i < path.getLength(); i++) {
                            const point = path.getAt(i);
                            coordinates.push({
                                lat: point.lat(),
                                lng: point.lng()
                            });
                        }

                        coordenadasOriginaisDesenhos[index] = coordinates;
                    }
                });
            }
        }

        // Função para resetar desenhos para coordenadas originais
        function resetarDesenhosPrefeitura() {
            const camadaLotes = "lotesPref";
            const destinoLotes = arrayCamadas[camadaLotes] ? camadaLotes : 'semCamadas';

            if (!coordenadasOriginaisDesenhos || coordenadasOriginaisDesenhos.length === 0) {
                alert('Nenhuma coordenada original salva para resetar.');
                return;
            }

            if (arrayCamadas[destinoLotes] && arrayCamadas[destinoLotes].length > 0) {
                arrayCamadas[destinoLotes].forEach(function(polygon, index) {
                    if (polygon.setPath && coordenadasOriginaisDesenhos[index]) {
                        const originalCoords = coordenadasOriginaisDesenhos[index];
                        const newPath = originalCoords.map(coord =>
                            new google.maps.LatLng(coord.lat, coord.lng)
                        );
                        polygon.setPath(newPath);
                    }
                });
            }
        }

        // Função para salvar desenhos modificados
        function salvarDesenhosPrefeitura() {
            const camadaLotes = "lotesPref";
            const destinoLotes = arrayCamadas[camadaLotes] ? camadaLotes : 'semCamadas';

            if (!arrayCamadas[destinoLotes] || arrayCamadas[destinoLotes].length === 0) {
                alert('Nenhum desenho para salvar.');
                return;
            }

            // Coletar coordenadas atuais de todos os desenhos
            const coordenadasAtuais = [];

            arrayCamadas[destinoLotes].forEach(function(polygon) {
                if (polygon.getPath) {
                    const path = polygon.getPath();
                    const coordinates = [];

                    for (let i = 0; i < path.getLength(); i++) {
                        const point = path.getAt(i);
                        coordinates.push({
                            lat: point.lat(),
                            lng: point.lng()
                        });
                    }

                    coordenadasAtuais.push(coordinates);
                }
            });

            // Obter quadrícula atual
            const quadricula = dadosOrto && dadosOrto[0] && dadosOrto[0]['quadricula'] ? dadosOrto[0]['quadricula'] : null;

            if (!quadricula) {
                alert('Erro: Quadrícula não identificada.');
                return;
            }

            // Preparar dados para envio
            const dadosParaSalvar = {
                quadricula: quadricula,
                coordenadas: coordenadasAtuais
            };

            // Enviar dados via AJAX
            $.ajax({
                url: 'salvar_coordenadas_desenhos_prefeitura.php',
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(dadosParaSalvar),
                success: function(response) {
                    if (response.success) {
                        alert(`Coordenadas salvas com sucesso! ${response.features_atualizadas} desenhos atualizados.`);
                        // Atualiza as coordenadas originais com as novas posições
                        salvarCoordenadasOriginais();
                    } else {
                        alert('Erro ao salvar: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Erro na requisição:', error);
                    alert('Erro ao salvar coordenadas. Tente novamente.');
                }
            });
        }

        // Função para rotacionar desenhos (individual ou coletiva)
        function rotacionarDesenhosPrefeitura(tipoRotacao) {
            const distancia = obterDistancia();
            const camadaLotes = "lotesPref";
            const destinoLotes = arrayCamadas[camadaLotes] ? camadaLotes : 'semCamadas';

            if (!arrayCamadas[destinoLotes] || arrayCamadas[destinoLotes].length === 0) {
                alert('Nenhum desenho carregado para rotacionar.');
                return;
            }

            // Salva coordenadas originais na primeira movimentação
            if (!desenhosCarregados) {
                salvarCoordenadasOriginais();
                desenhosCarregados = true;
            }

            // Converter distância em metros para ângulo em graus
            // Usando uma conversão mais intuitiva: 1 metro ≈ 0.01 graus de rotação
            const anguloGraus = distancia * 0.01;
            const anguloRad = anguloGraus * Math.PI / 180;

            // Definir direção da rotação
            const fatorRotacao = (tipoRotacao.includes('esquerda')) ? 1 : -1;
            const anguloFinal = fatorRotacao * anguloRad;

            if (tipoRotacao.includes('individual')) {
                // ROTAÇÃO INDIVIDUAL: Cada desenho rotaciona em torno do seu próprio centro
                rotacionarDesenhosIndividual(arrayCamadas[destinoLotes], anguloFinal);
            } else if (tipoRotacao.includes('coletiva')) {
                // ROTAÇÃO COLETIVA: Todos os desenhos rotacionam em torno de um centro comum
                rotacionarDesenhosColetiva(arrayCamadas[destinoLotes], anguloFinal);
            }
        }

        // Função para rotação individual (cada desenho em torno do seu centro)
        function rotacionarDesenhosIndividual(polygons, anguloRad) {
            polygons.forEach(function(polygon) {
                if (polygon.getPath && polygon.setPath) {
                    const path = polygon.getPath();

                    // Calcular o centro do polígono individual
                    let centroLat = 0,
                        centroLng = 0;
                    for (let i = 0; i < path.getLength(); i++) {
                        const point = path.getAt(i);
                        centroLat += point.lat();
                        centroLng += point.lng();
                    }
                    centroLat /= path.getLength();
                    centroLng /= path.getLength();

                    // Aplicar rotação em torno do centro individual
                    const newPath = [];
                    for (let i = 0; i < path.getLength(); i++) {
                        const point = path.getAt(i);
                        const lat = point.lat() - centroLat;
                        const lng = point.lng() - centroLng;

                        // Aplicar matriz de rotação
                        const newLat = lat * Math.cos(anguloRad) - lng * Math.sin(anguloRad);
                        const newLng = lat * Math.sin(anguloRad) + lng * Math.cos(anguloRad);

                        const rotatedPoint = new google.maps.LatLng(
                            newLat + centroLat,
                            newLng + centroLng
                        );
                        newPath.push(rotatedPoint);
                    }

                    polygon.setPath(newPath);
                }
            });
        }

        // Função para rotação coletiva (todos os desenhos em torno de um centro comum)
        function rotacionarDesenhosColetiva(polygons, anguloRad) {
            // Calcular o centro comum de todos os desenhos
            let centroLatTotal = 0,
                centroLngTotal = 0;
            let totalPontos = 0;

            polygons.forEach(function(polygon) {
                if (polygon.getPath) {
                    const path = polygon.getPath();
                    for (let i = 0; i < path.getLength(); i++) {
                        const point = path.getAt(i);
                        centroLatTotal += point.lat();
                        centroLngTotal += point.lng();
                        totalPontos++;
                    }
                }
            });

            const centroLat = centroLatTotal / totalPontos;
            const centroLng = centroLngTotal / totalPontos;

            // Rotacionar cada polígono em torno do centro comum
            polygons.forEach(function(polygon) {
                if (polygon.getPath && polygon.setPath) {
                    const path = polygon.getPath();
                    const newPath = [];

                    for (let i = 0; i < path.getLength(); i++) {
                        const point = path.getAt(i);
                        const lat = point.lat() - centroLat;
                        const lng = point.lng() - centroLng;

                        // Aplicar matriz de rotação em torno do centro comum
                        const newLat = lat * Math.cos(anguloRad) - lng * Math.sin(anguloRad);
                        const newLng = lat * Math.sin(anguloRad) + lng * Math.cos(anguloRad);

                        const rotatedPoint = new google.maps.LatLng(
                            newLat + centroLat,
                            newLng + centroLng
                        );
                        newPath.push(rotatedPoint);
                    }

                    polygon.setPath(newPath);
                }
            });
        }

        // Função para cancelar controle e ocultar
        function cancelarControleDesenhos() {
            // Resetar para coordenadas originais
            resetarDesenhosPrefeitura();

            // Ocultar controle
            $('#controleDesenhosPrefeitura').removeClass('show');

            // Desmarcar checkbox
            $('#new_checkLotes').prop('checked', false);

            // Ocultar desenhos
            if (MapFramework && MapFramework.toggleLotesGeojson) {
                MapFramework.toggleLotesGeojson(false);
            }
        }

        // ============================================================================
        // EVENT LISTENER PARA CHECKBOX DOS LOTES DA PREFEITURA
        // ============================================================================
        // Conecta o checkbox com as funções do MapFramework para carregar/mostrar lotes
        // ============================================================================
        $(document).ready(function() {
            $('#new_checkLotes').change(function() {
                const isChecked = $(this).is(':checked');

                // Controla a camada poligono_lote
                if (MapFramework && MapFramework.alternarVisibilidadeCamada) {
                    MapFramework.alternarVisibilidadeCamada('poligono_lote', isChecked);
                }

                // Mostra/oculta o controle de desenhos da prefeitura
                const controle = $('#controleDesenhosPrefeitura');
                if (isChecked) {
                    controle.addClass('show');
                } else {
                    controle.removeClass('show');
                }
            });

            criaBotAdm()
        });
        // ============================================================================

        let quarteiraoAtualModal = null;
        let imovelAtualModal = null;
        let dadosImovelAtualModal = null;

        // Função para abrir o modal de gerenciamento de documentos
        function abrirModalGerenciarDocs(nomeQuarteirao) {
            console.log('Função abrirModalGerenciarDocs chamada para:', nomeQuarteirao);
            quarteiraoAtualModal = nomeQuarteirao;

            // Atualiza as informações do modal
            $('#nomeQuarteiraoModal').text(`Quarteirão ${nomeQuarteirao}`);
            $('#caminhoPastaModal').text(`Pasta: loteamentos_quadriculas/pdfs_quarteiroes/`);

            // Limpa a lista de arquivos
            $('#listaArquivos').empty();
            $('#inputArquivos').val('');
            $('#btnUploadArquivos').prop('disabled', true);

            // Carrega a lista de arquivos
            carregarListaArquivosQuarteirao(nomeQuarteirao);

            // Verifica se o modal existe
            const modal = $('#modalGerenciarDocs');
            console.log('Modal encontrado:', modal.length > 0);

            // Mostra o modal
            if (modal.length > 0) {
                modal.modal('show');
                console.log('Modal.show() executado');
            } else {
                console.error('Modal não encontrado!');
            }
        }

        // Função para carregar a lista de arquivos do quarteirão
        function carregarListaArquivosQuarteirao(nomeQuarteirao) {
            console.log('Carregando lista de arquivos para quarteirão:', nomeQuarteirao);

            // Tenta carregar arquivos da pasta física do quarteirão
            $.ajax({
                url: 'consultas/listar_arquivos_quarteirao.php',
                method: 'POST',
                data: {
                    quarteirao: nomeQuarteirao
                },
                dataType: 'json',
                success: function(response) {
                    console.log('Resposta da listagem:', response);
                    if (response.success && response.arquivos.length > 0) {
                        exibirListaArquivos(response.arquivos);
                    } else {
                        // Se não encontrar arquivos na pasta, tenta carregar do JSON
                        carregarDadosPDFsQuarteiroes(nomeQuarteirao).then(function(dadosPDFs) {
                            if (dadosPDFs && Array.isArray(dadosPDFs)) {
                                const arquivos = dadosPDFs.map(item => {
                                    return item.nome_arquivo.split('/').pop();
                                });
                                exibirListaArquivos(arquivos);
                            } else {
                                $('#listaArquivos').html('<div class="alert alert-warning">Nenhum arquivo encontrado.</div>');
                            }
                        }).catch(function(error) {
                            console.error('Erro ao carregar dados dos PDFs:', error);
                            $('#listaArquivos').html('<div class="alert alert-warning">Nenhum arquivo encontrado.</div>');
                        });
                    }
                },
                error: function() {
                    $('#listaArquivos').html('<div class="alert alert-danger">Erro ao carregar arquivos.</div>');
                }
            });
        }

        // Função para exibir a lista de arquivos
        function exibirListaArquivos(arquivos) {
            const container = $('#listaArquivos');
            container.empty();

            if (arquivos.length === 0) {
                container.html('<div class="alert alert-info">Nenhum arquivo encontrado nesta pasta.</div>');
                return;
            }

            arquivos.forEach(function(arquivo) {
                const extensao = arquivo.split('.').pop().toLowerCase();
                const icone = getIconeArquivo(extensao);

                const item = $(`
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <i class="${icone} me-2"></i>
                            <span>${arquivo}</span>
                        </div>
                        <div>
                            <button class="btn btn-sm btn-outline-primary me-2" onclick="visualizarArquivo('${arquivo}')">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="excluirArquivoQuarteirao('${arquivo}')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                `);

                container.append(item);
            });
        }

        // Função para obter o ícone baseado na extensão do arquivo
        function getIconeArquivo(extensao) {
            const icones = {
                'pdf': 'fas fa-file-pdf text-danger',
                'doc': 'fas fa-file-word text-primary',
                'docx': 'fas fa-file-word text-primary',
                'jpg': 'fas fa-file-image text-success',
                'jpeg': 'fas fa-file-image text-success',
                'png': 'fas fa-file-image text-success',
                'gif': 'fas fa-file-image text-success'
            };
            return icones[extensao] || 'fas fa-file text-secondary';
        }

        // Função para visualizar um arquivo
        function visualizarArquivo(nomeArquivo) {
            const caminho = `loteamentos_quadriculas/pdfs_quarteiroes/${quarteiraoAtualModal}/${nomeArquivo}`;
            window.open(caminho, '_blank');
        }

        // Função para excluir um arquivo
        function excluirArquivoQuarteirao(nomeArquivo) {
            if (!confirm(`Tem certeza que deseja excluir o arquivo "${nomeArquivo}"?`)) {
                return;
            }

            $.ajax({
                url: 'consultas/excluir_arquivo_quarteirao.php',
                method: 'POST',
                data: {
                    quarteirao: quarteiraoAtualModal,
                    arquivo: nomeArquivo
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert('Arquivo excluído com sucesso!');
                        // Atualiza a lista do modal
                        carregarListaArquivosQuarteirao(quarteiraoAtualModal);
                        // Atualiza a lista de PDFs na interface principal
                        atualizarListaPDFsQuarteirao(quarteiraoAtualModal);
                    } else {
                        alert('Erro ao excluir arquivo: ' + response.message);
                    }
                },
                error: function() {
                    alert('Erro ao excluir arquivo.');
                }
            });
        }

        // Função para atualizar a lista de PDFs na divCadastro2
        function atualizarListaPDFsQuarteirao(nomeQuarteirao) {
            console.log('Atualizando lista de PDFs para quarteirão:', nomeQuarteirao);

            // Carrega arquivos da pasta física do quarteirão
            $.ajax({
                url: 'consultas/listar_arquivos_quarteirao.php',
                method: 'POST',
                data: {
                    quarteirao: nomeQuarteirao
                },
                dataType: 'json',
                success: function(response) {
                    console.log('Resposta da atualização:', response);

                    // Encontra o elemento do quarteirão na divCadastro2
                    const radioSelector = `input[name="quarteirao"][data-nome="${nomeQuarteirao}"]`;
                    const radioElement = $(radioSelector);

                    if (radioElement.length > 0) {
                        const opcaoQuarteirao = radioElement.closest('.opcao-quarteirao');
                        const submenuPdfs = opcaoQuarteirao.find('.submenu-pdfs');

                        // Atualiza o conteúdo do submenu de PDFs
                        if (response.success && response.arquivos.length > 0) {
                            const novosPDFs = response.arquivos.map(arquivo => {
                                const caminhoCompleto = `${nomeQuarteirao}/${arquivo}`;
                                return `<a href="javascript:void(0)" onclick="abrirPDFQuarteirao('${caminhoCompleto}')" title="${caminhoCompleto}">
                                    <i class="fas fa-file-pdf"></i>${arquivo.length > 20 ? arquivo.substring(0, 20) + '...' : arquivo}
                                </a>`;
                            }).join('');

                            submenuPdfs.html(novosPDFs);
                        } else {
                            submenuPdfs.html('<em class="text-muted">Sem PDFs</em>');
                        }
                    }
                },
                error: function() {
                    console.error('Erro ao atualizar lista de PDFs');
                }
            });
        }

        // Event listeners para o modal
        $(document).ready(function() {
            // Habilita o botão de upload quando arquivos são selecionados
            $('#inputArquivos').on('change', function() {
                const files = this.files;
                $('#btnUploadArquivos').prop('disabled', files.length === 0);
            });

            // Upload de arquivos
            $('#btnUploadArquivos').on('click', function() {
                const files = $('#inputArquivos')[0].files;
                if (files.length === 0) return;

                const formData = new FormData();
                formData.append('quarteirao', quarteiraoAtualModal);

                for (let i = 0; i < files.length; i++) {
                    formData.append('arquivos[]', files[i]);
                }

                // Mostra loading
                $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Enviando...');

                console.log('Enviando arquivos para quarteirão:', quarteiraoAtualModal);
                console.log('FormData:', formData);

                $.ajax({
                    url: 'consultas/upload_arquivos_quarteirao.php',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function(response) {
                        console.log('Resposta do servidor:', response);
                        if (response.success) {
                            alert('Arquivos enviados com sucesso!');
                            $('#inputArquivos').val('');

                            // Atualiza a lista do modal
                            carregarListaArquivosQuarteirao(quarteiraoAtualModal);

                            // Atualiza a lista de PDFs na divCadastro2
                            atualizarListaPDFsQuarteirao(quarteiraoAtualModal);
                        } else {
                            alert('Erro ao enviar arquivos: ' + response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Erro na requisição:', xhr, status, error);
                        console.error('Resposta do servidor:', xhr.responseText);
                        alert('Erro ao enviar arquivos.');
                    },
                    complete: function() {
                        $('#btnUploadArquivos').prop('disabled', true).html('Adicionar Arquivos');
                    }
                });
            });
        });

        // ============================================================================
        // FUNÇÕES PARA GERENCIAR DOCUMENTOS DE IMÓVEIS
        // ============================================================================

        // Função para abrir o modal de gerenciamento de documentos de imóveis
        function abrirModalGerenciarDocsImovel(imobId, dadosImovel) {
            console.log('Função abrirModalGerenciarDocsImovel chamada para:', imobId);
            imovelAtualModal = imobId;
            dadosImovelAtualModal = dadosImovel;

            // Atualiza as informações do modal
            $('#nomeImovelModal').text(`Imóvel ID: ${imobId}`);
            $('#caminhoPastaImovelModal').text(`Pasta: loteamentos_quadriculas/imoveis/${imobId}/`);

            // Adiciona informações do imóvel
            let infoTexto = '';
            if (dadosImovel.cadastro) {
                infoTexto += `Logradouro: ${dadosImovel.cadastro.logradouro || 'N/A'}, `;
                infoTexto += `Nº: ${dadosImovel.cadastro.numero || 'N/A'} `;
                infoTexto += `- ${dadosImovel.cadastro.bairro || 'N/A'}`;
            }
            if (dadosImovel.desenhos) {
                infoTexto += ` | Quadra: ${dadosImovel.desenhos.quadra || 'N/A'}, Lote: ${dadosImovel.desenhos.lote || 'N/A'}`;
            }
            $('#infoImovelModal').text(infoTexto);

            // Limpa a lista de arquivos
            $('#listaArquivosImovel').empty();
            $('#inputArquivosImovel').val('');
            $('#btnUploadArquivosImovel').prop('disabled', true);

            // Carrega a lista de arquivos
            carregarListaArquivosImovel(imobId);

            // Mostra o modal
            const modal = $('#modalGerenciarDocsImovel');
            if (modal.length > 0) {
                modal.modal('show');
                console.log('Modal de imóvel exibido');
            } else {
                console.error('Modal de imóvel não encontrado!');
            }
        }

        // Função para carregar a lista de arquivos do imóvel
        function carregarListaArquivosImovel(imobId) {
            console.log('Carregando lista de arquivos para imóvel:', imobId);

            $.ajax({
                url: 'consultas/listar_arquivos_imovel.php',
                method: 'POST',
                data: {
                    imob_id: imobId
                },
                dataType: 'json',
                success: function(response) {
                    console.log('Resposta da listagem de imóvel:', response);
                    if (response.success && response.arquivos && response.arquivos.length > 0) {
                        exibirListaArquivosImovel(response.arquivos);
                    } else {
                        $('#listaArquivosImovel').html('<div class="alert alert-info">Nenhum arquivo encontrado para este imóvel.</div>');
                    }
                },
                error: function() {
                    $('#listaArquivosImovel').html('<div class="alert alert-danger">Erro ao carregar arquivos.</div>');
                }
            });
        }

        // Função para exibir a lista de arquivos do imóvel
        function exibirListaArquivosImovel(arquivos) {
            const container = $('#listaArquivosImovel');
            container.empty();

            if (arquivos.length === 0) {
                container.html('<div class="alert alert-info">Nenhum arquivo encontrado nesta pasta.</div>');
                return;
            }

            arquivos.forEach(function(arquivo) {
                const extensao = arquivo.split('.').pop().toLowerCase();
                const icone = getIconeArquivo(extensao);

                const item = $(`
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <i class="${icone} me-2"></i>
                            <span>${arquivo}</span>
                        </div>
                        <div>
                            <button class="btn btn-sm btn-outline-primary me-2" onclick="visualizarArquivoImovel('${arquivo}')">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="excluirArquivoImovel('${arquivo}')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                `);

                container.append(item);
            });
        }

        // Função para visualizar um arquivo do imóvel
        function visualizarArquivoImovel(nomeArquivo) {
            const caminho = `loteamentos_quadriculas/imoveis/${imovelAtualModal}/${nomeArquivo}`;
            window.open(caminho, '_blank');
        }

        // Função para excluir um arquivo do imóvel
        function excluirArquivoImovel(nomeArquivo) {
            if (!confirm(`Tem certeza que deseja excluir o arquivo "${nomeArquivo}"?`)) {
                return;
            }

            $.ajax({
                url: 'consultas/excluir_arquivo_imovel.php',
                method: 'POST',
                data: {
                    imob_id: imovelAtualModal,
                    arquivo: nomeArquivo
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert('Arquivo excluído com sucesso!');
                        carregarListaArquivosImovel(imovelAtualModal);
                    } else {
                        alert('Erro ao excluir arquivo: ' + response.message);
                    }
                },
                error: function() {
                    alert('Erro ao excluir arquivo.');
                }
            });
        }

        // Event listeners para o modal de imóveis
        $(document).ready(function() {
            // Habilita o botão de upload quando arquivos são selecionados
            $('#inputArquivosImovel').on('change', function() {
                const files = this.files;
                $('#btnUploadArquivosImovel').prop('disabled', files.length === 0);
            });

            // Upload de arquivos de imóveis
            $('#btnUploadArquivosImovel').on('click', function() {
                const files = $('#inputArquivosImovel')[0].files;
                if (files.length === 0) return;

                const formData = new FormData();
                formData.append('imob_id', imovelAtualModal);

                for (let i = 0; i < files.length; i++) {
                    formData.append('arquivos[]', files[i]);
                }

                // Mostra loading
                $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Enviando...');

                console.log('Enviando arquivos para imóvel:', imovelAtualModal);

                $.ajax({
                    url: 'consultas/upload_arquivos_imovel.php',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function(response) {
                        console.log('Resposta do servidor:', response);
                        if (response.success) {
                            alert('Arquivos enviados com sucesso!');
                            $('#inputArquivosImovel').val('');
                            carregarListaArquivosImovel(imovelAtualModal);
                        } else {
                            alert('Erro ao enviar arquivos: ' + response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Erro na requisição:', xhr, status, error);
                        console.error('Resposta do servidor:', xhr.responseText);
                        alert('Erro ao enviar arquivos.');
                    },
                    complete: function() {
                        $('#btnUploadArquivosImovel').prop('disabled', true).html('Adicionar Arquivos');
                    }
                });
            });
        });

        // ============================================================================
        // FUNÇÃO PARA ALTERAR/EDITAR DESENHO - ABRE EM NOVA ABA
        // ============================================================================
        function alterarDesenhoNovo(idDesenho, quarteirao, loteamento, idDesenho2, quadricula) {
            // Decodifica o loteamento (caso tenha caracteres especiais)
            const loteamentoDecodificado = decodeURIComponent(loteamento);

            // Constrói a URL com os parâmetros
            const params = new URLSearchParams({
                id: idDesenho,
                quarteirao: quarteirao || '',
                loteamento: loteamentoDecodificado,
                id_desenho: idDesenho2 || '',
                quadricula: quadricula || ''
            });

            // Define a página de destino (ALTERE AQUI o nome da página desejada)
            const paginaDestino = 'index_3_novoEditar.php'; // ← ALTERE para o nome da sua página

            // Abre em nova aba
            const url = `${paginaDestino}?${params.toString()}`;
            window.open(url, '_blank');

        }
    </script>

    <!-- Modal para gerenciar arquivos dos quarteirões -->
    <div class="modal fade" id="modalGerenciarDocs" tabindex="-1" aria-labelledby="modalGerenciarDocsLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalGerenciarDocsLabel">Gerenciar Documentos</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="infoQuarteirao" class="mb-3">
                        <h6 id="nomeQuarteiraoModal"></h6>
                        <small class="text-muted" id="caminhoPastaModal"></small>
                    </div>

                    <!-- Área de upload -->
                    <div class="mb-4">
                        <label for="inputArquivos" class="form-label">Adicionar Arquivos</label>
                        <input type="file" class="form-control" id="inputArquivos" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif">
                        <div class="form-text">Selecione um ou mais arquivos para adicionar à pasta do quarteirão.</div>
                    </div>

                    <!-- Lista de arquivos -->
                    <div>
                        <h6>Arquivos Existentes</h6>
                        <div id="listaArquivos" class="list-group">
                            <!-- Arquivos serão carregados aqui dinamicamente -->
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button type="button" class="btn btn-primary" id="btnUploadArquivos" disabled>Adicionar Arquivos</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para gerenciar arquivos dos imóveis -->
    <div class="modal fade" id="modalGerenciarDocsImovel" tabindex="-1" aria-labelledby="modalGerenciarDocsImovelLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalGerenciarDocsImovelLabel">Gerenciar Documentos do Imóvel</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="infoImovel" class="mb-3">
                        <h6 id="nomeImovelModal"></h6>
                        <small class="text-muted d-block" id="infoImovelModal"></small>
                        <small class="text-muted d-block" id="caminhoPastaImovelModal"></small>
                    </div>

                    <!-- Área de upload -->
                    <div class="mb-4">
                        <label for="inputArquivosImovel" class="form-label">Adicionar Arquivos</label>
                        <input type="file" class="form-control" id="inputArquivosImovel" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif">
                        <div class="form-text">Selecione um ou mais arquivos para adicionar à pasta do imóvel.</div>
                    </div>

                    <!-- Lista de arquivos -->
                    <div>
                        <h6>Arquivos Existentes</h6>
                        <div id="listaArquivosImovel" class="list-group">
                            <!-- Arquivos serão carregados aqui dinamicamente -->
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button type="button" class="btn btn-primary" id="btnUploadArquivosImovel" disabled>Adicionar Arquivos</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Pesquisa (TEMPORÁRIO) -->
    <div class="modal fade" id="modalPesquisa" tabindex="-1" aria-labelledby="modalPesquisaLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalPesquisaLabel">Pesquisa de Imóveis</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="selectTipoPesquisa" class="form-label">Tipo de Pesquisa</label>
                        <select class="form-select" id="selectTipoPesquisa">
                            <option value="idLote">ID Lote</option>
                            <option value="idMarcador">ID Marcador</option>
                            <option value="loteamento">Loteamento</option>
                            <option value="quarteirao">Quarteirão</option>
                            <option value="quadra">Quadra</option>
                            <option value="lote">Lote</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="inputPesquisa" class="form-label">Valor</label>
                        <input type="text" class="form-control" id="inputPesquisa" placeholder="Digite o valor para pesquisar">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button type="button" class="btn btn-primary" id="btnExecutarPesquisa">Pesquisar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- FUNCIONALIDADE DE PESQUISA (TEMPORÁRIA) -->
    <!-- ============================================ -->
    <script>
        // Variável para controlar se está em modo pesquisa
        let modoPesquisaAtivo = false;
        let estadoOriginalMarcadores = null;
        let estadoOriginalPoligonos = null;

        // Abrir modal de pesquisa
        $('#btnPesquisa').on('click', function() {
            $('#modalPesquisa').modal('show');
        });

        // Executar pesquisa
        $('#btnExecutarPesquisa').on('click', function() {
            const tipoPesquisa = $('#selectTipoPesquisa').val();
            const valorPesquisa = $('#inputPesquisa').val().trim();

            if (!valorPesquisa) {
                alert('Por favor, digite um valor para pesquisar.');
                return;
            }

            // Ativa o modo pesquisa
            modoPesquisaAtivo = true;

            // Salva o estado original (se ainda não foi salvo)
            if (estadoOriginalMarcadores === null) {
                salvarEstadoOriginal();
            }

            // Executa a pesquisa
            executarPesquisa(tipoPesquisa, valorPesquisa);

            // Fecha o modal
            $('#modalPesquisa').modal('hide');

            // Mostra o botão de sair do modo pesquisa
            $('#btnSairModoPesquisa').removeClass('d-none');
            $('#btnPesquisa').addClass('d-none');
        });

        // Sair do modo pesquisa
        $('#btnSairModoPesquisa').on('click', function() {
            sairModoPesquisa();
        });

        // Função para salvar o estado original
        function salvarEstadoOriginal() {
            estadoOriginalMarcadores = [];
            estadoOriginalPoligonos = [];

            // Salva estado dos marcadores
            if (arrayCamadas.marcador_quadra) {
                arrayCamadas.marcador_quadra.forEach(marker => {
                    estadoOriginalMarcadores.push({
                        marker: marker,
                        visivel: marker.map !== null
                    });
                });
            }

            // Salva estado dos polígonos
            if (arrayCamadas.poligono_lote) {
                arrayCamadas.poligono_lote.forEach(poligono => {
                    estadoOriginalPoligonos.push({
                        poligono: poligono,
                        visivel: poligono.map !== null
                    });
                });
            }
        }

        // Função para executar a pesquisa
        function executarPesquisa(tipoPesquisa, valorPesquisa) {
            // Primeiro, oculta todos os marcadores e polígonos
            if (arrayCamadas.marcador_quadra) {
                arrayCamadas.marcador_quadra.forEach(marker => {
                    marker.setMap(null);
                });
            }

            if (arrayCamadas.poligono_lote) {
                arrayCamadas.poligono_lote.forEach(poligono => {
                    if (poligono.setMap) {
                        poligono.setMap(null);
                    }
                });
            }

            // Array para armazenar os marcadores encontrados
            const marcadoresEncontrados = [];
            const poligonosEncontrados = [];

            // Pesquisa nos marcadores
            if (arrayCamadas.marcador_quadra) {
                arrayCamadas.marcador_quadra.forEach(marker => {
                    let corresponde = false;

                    switch (tipoPesquisa) {
                        case 'idLote':
                            // ID Lote = idQuadra (ID do desenho)
                            if (marker.idQuadra && marker.idQuadra.toString() === valorPesquisa) {
                                corresponde = true;
                            }
                            break;

                        case 'idMarcador':
                            // ID Marcador = identificadorBanco (ID do banco)
                            if (marker.identificadorBanco && marker.identificadorBanco.toString() === valorPesquisa) {
                                corresponde = true;
                            }
                            break;

                        case 'loteamento':
                            // Loteamento - precisa buscar do polígono relacionado
                            // Por enquanto, vamos procurar nos polígonos primeiro
                            break;

                        case 'quarteirao':
                            if (marker.quarteirao && marker.quarteirao.toString().toLowerCase() === valorPesquisa.toLowerCase()) {
                                corresponde = true;
                            }
                            break;

                        case 'quadra':
                            if (marker.quadra && marker.quadra.toString().toLowerCase() === valorPesquisa.toLowerCase()) {
                                corresponde = true;
                            }
                            break;

                        case 'lote':
                            if (marker.numeroMarcador && marker.numeroMarcador.toString().toLowerCase() === valorPesquisa.toLowerCase()) {
                                corresponde = true;
                            }
                            break;
                    }

                    if (corresponde) {
                        marcadoresEncontrados.push(marker);
                    }
                });
            }

            // Pesquisa nos polígonos (para loteamento e para encontrar polígonos relacionados aos marcadores)
            if (arrayCamadas.poligono_lote) {
                arrayCamadas.poligono_lote.forEach(poligono => {
                    let corresponde = false;
                    let mostrarPoligono = false;

                    // Busca direta nos polígonos por quarteirão, quadra ou lote
                    if (tipoPesquisa === 'quarteirao') {
                        // Tenta diferentes formas de acessar quarteirao
                        const quarteiraoPoligono = poligono.quarteirao || poligono.dados_completos_desenho?.quarteirao;
                        if (quarteiraoPoligono && quarteiraoPoligono.toString().toLowerCase() === valorPesquisa.toLowerCase()) {
                            corresponde = true;
                            mostrarPoligono = true;
                        }
                    } else if (tipoPesquisa === 'quadra') {
                        const quadraPoligono = poligono.quadra || poligono.dados_completos_desenho?.quadra;
                        if (quadraPoligono && quadraPoligono.toString().toLowerCase() === valorPesquisa.toLowerCase()) {
                            corresponde = true;
                            mostrarPoligono = true;
                        }
                    } else if (tipoPesquisa === 'lote') {
                        const lotePoligono = poligono.lote || poligono.dados_completos_desenho?.lote;
                        if (lotePoligono && lotePoligono.toString().toLowerCase() === valorPesquisa.toLowerCase()) {
                            corresponde = true;
                            mostrarPoligono = true;
                        }
                    } else if (tipoPesquisa === 'loteamento') {
                        // Para loteamento, tenta obter usando a função acharLoteamento do MapFramework
                        // ou busca na propriedade do polígono
                        const loteamentoPoligono = poligono.loteamento || poligono.dados_completos_desenho?.loteamento;
                        if (loteamentoPoligono && loteamentoPoligono.toString().toLowerCase() === valorPesquisa.toLowerCase()) {
                            corresponde = true;
                            mostrarPoligono = true;
                        } else if (poligono.getPath && poligono.getPath().getLength() > 0) {
                            // Tenta obter o loteamento pela posição do polígono
                            const path = poligono.getPath();
                            const primeiroPonto = path.getAt(0);
                            if (primeiroPonto && MapFramework && MapFramework.acharLoteamento) {
                                const loteamentoEncontrado = MapFramework.acharLoteamento(primeiroPonto);
                                if (loteamentoEncontrado && loteamentoEncontrado.toString().toLowerCase() === valorPesquisa.toLowerCase()) {
                                    corresponde = true;
                                    mostrarPoligono = true;
                                }
                            }
                        }
                    } else if (tipoPesquisa === 'idLote') {
                        // ID Lote = id_desenho do polígono
                        if (poligono.id_desenho && poligono.id_desenho.toString() === valorPesquisa) {
                            corresponde = true;
                            mostrarPoligono = true;
                        }
                    }

                    // Se encontrou marcadores, busca os polígonos relacionados (para ID Marcador)
                    if (marcadoresEncontrados.length > 0 && tipoPesquisa === 'idMarcador') {
                        marcadoresEncontrados.forEach(marker => {
                            // Para ID Marcador, mostra o polígono relacionado ao marcador
                            if (poligono.id_desenho && marker.idQuadra &&
                                poligono.id_desenho.toString() === marker.idQuadra.toString()) {
                                mostrarPoligono = true;
                            }
                        });
                    }

                    if (corresponde || mostrarPoligono) {
                        poligonosEncontrados.push(poligono);
                    }
                });
            }

            // Se encontrou polígonos por quarteirão/quadra/lote, também busca marcadores relacionados
            if (poligonosEncontrados.length > 0 && (tipoPesquisa === 'quarteirao' || tipoPesquisa === 'quadra' || tipoPesquisa === 'lote')) {
                poligonosEncontrados.forEach(poligono => {
                    const quarteiraoPoligono = poligono.quarteirao || poligono.dados_completos_desenho?.quarteirao;
                    const quadraPoligono = poligono.quadra || poligono.dados_completos_desenho?.quadra;
                    const lotePoligono = poligono.lote || poligono.dados_completos_desenho?.lote;

                    // Busca marcadores relacionados a este polígono
                    if (arrayCamadas.marcador_quadra) {
                        arrayCamadas.marcador_quadra.forEach(marker => {
                            let corresponde = false;

                            if (tipoPesquisa === 'quarteirao' && quarteiraoPoligono && marker.quarteirao) {
                                corresponde = quarteiraoPoligono.toString().toLowerCase() === marker.quarteirao.toString().toLowerCase();
                            } else if (tipoPesquisa === 'quadra' && quadraPoligono && marker.quadra) {
                                corresponde = quadraPoligono.toString().toLowerCase() === marker.quadra.toString().toLowerCase();
                            } else if (tipoPesquisa === 'lote' && lotePoligono && marker.numeroMarcador) {
                                corresponde = lotePoligono.toString().toLowerCase() === marker.numeroMarcador.toString().toLowerCase();
                            }

                            if (corresponde && !marcadoresEncontrados.includes(marker)) {
                                marcadoresEncontrados.push(marker);
                            }
                        });
                    }
                });
            }

            // Mostra os marcadores encontrados
            marcadoresEncontrados.forEach(marker => {
                marker.setMap(MapFramework.map);
            });

            // Mostra os polígonos encontrados
            poligonosEncontrados.forEach(poligono => {
                if (poligono.setMap) {
                    poligono.setMap(MapFramework.map);
                }
            });

            // Se não encontrou nada, mostra mensagem
            if (marcadoresEncontrados.length === 0 && poligonosEncontrados.length === 0) {
                alert('Nenhum resultado encontrado para: ' + valorPesquisa);
            } else {
                // Ajusta o zoom para mostrar os resultados
                if (marcadoresEncontrados.length > 0) {
                    const bounds = new google.maps.LatLngBounds();
                    marcadoresEncontrados.forEach(marker => {
                        if (marker.position) {
                            bounds.extend(marker.position);
                        }
                    });
                    if (!bounds.isEmpty()) {
                        MapFramework.map.fitBounds(bounds);
                    }
                }
            }
        }

        // Função para sair do modo pesquisa
        function sairModoPesquisa() {
            modoPesquisaAtivo = false;

            // Restaura o estado original dos marcadores
            if (estadoOriginalMarcadores) {
                estadoOriginalMarcadores.forEach(item => {
                    if (item.visivel) {
                        item.marker.setMap(MapFramework.map);
                    } else {
                        item.marker.setMap(null);
                    }
                });
            }

            // Restaura o estado original dos polígonos
            if (estadoOriginalPoligonos) {
                estadoOriginalPoligonos.forEach(item => {
                    if (item.visivel && item.poligono.setMap) {
                        item.poligono.setMap(MapFramework.map);
                    } else if (item.poligono.setMap) {
                        item.poligono.setMap(null);
                    }
                });
            }

            // Esconde o botão de sair do modo pesquisa
            $('#btnSairModoPesquisa').addClass('d-none');
            $('#btnPesquisa').removeClass('d-none');

            // Limpa os campos do modal
            $('#inputPesquisa').val('');
        }

        // Permite pesquisar pressionando Enter no input
        $('#inputPesquisa').on('keypress', function(e) {
            if (e.which === 13) {
                $('#btnExecutarPesquisa').click();
            }
        });

        // Gerenciar modal de histórico
        let imobIdAtual = null;
        const modalHistorico = new bootstrap.Modal(document.getElementById('modalHistorico'));
        const tbodyHistorico = document.getElementById('tbodyHistorico');
        const novaLinhaHistorico = document.getElementById('novaLinhaHistorico');
        const btnAdicionarHistorico = document.getElementById('btnAdicionarHistorico');

        // Event listener para links de histórico (usando delegação de eventos)
        $(document).on('click', '.historico-link', async function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            imobIdAtual = $(this).attr('data-imob-id');
            if (!imobIdAtual) {
                alert('Erro: imob_id não encontrado');
                return;
            }
            
            // Abrir modal
            modalHistorico.show();
            
            // Carregar histórico
            await carregarHistorico(imobIdAtual);
        });

        // Função para carregar histórico completo
        async function carregarHistorico(imobId) {
            try {
                tbodyHistorico.innerHTML = '<tr><td colspan="2" class="text-center">Carregando...</td></tr>';
                
                const response = await fetch(`?buscar_historico=1&imob_id=${encodeURIComponent(imobId)}`);
                const data = await response.json();
                
                if (data.error) {
                    tbodyHistorico.innerHTML = `<tr><td colspan="2" class="text-danger">Erro: ${data.error}</td></tr>`;
                    return;
                }
                
                // Limpar tabela
                tbodyHistorico.innerHTML = '';
                
                if (data.linhas && data.linhas.length > 0) {
                    data.linhas.forEach((linha, index) => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td>${index + 1}</td>
                            <td>${escapeHtml(linha)}</td>
                        `;
                        tbodyHistorico.appendChild(tr);
                    });
                } else {
                    tbodyHistorico.innerHTML = '<tr><td colspan="2" class="text-muted text-center">Nenhum histórico registrado</td></tr>';
                }
            } catch (error) {
                console.error('Erro ao carregar histórico:', error);
                tbodyHistorico.innerHTML = `<tr><td colspan="2" class="text-danger">Erro ao carregar histórico</td></tr>`;
            }
        }

        // Função para adicionar nova linha ao histórico
        btnAdicionarHistorico.addEventListener('click', async function() {
            const texto = novaLinhaHistorico.value.trim();
            
            if (!texto) {
                alert('Por favor, digite um texto para adicionar ao histórico.');
                return;
            }
            
            if (!imobIdAtual) {
                alert('Erro: imob_id não encontrado');
                return;
            }
            
            try {
                btnAdicionarHistorico.disabled = true;
                btnAdicionarHistorico.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Adicionando...';
                
                const response = await fetch('?adicionar_historico=1', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        imob_id: imobIdAtual,
                        nova_linha: texto
                    })
                });
                
                const data = await response.json();
                
                if (data.error) {
                    alert('Erro ao adicionar linha: ' + data.error);
                    btnAdicionarHistorico.disabled = false;
                    btnAdicionarHistorico.innerHTML = '<i class="fas fa-plus"></i> Adicionar Linha';
                    return;
                }
                
                // Limpar campo de texto
                novaLinhaHistorico.value = '';
                
                // Recarregar histórico atualizado
                await carregarHistorico(imobIdAtual);
                
                // Atualizar a célula do histórico na tabela principal
                atualizarHistoricoNaTabela(imobIdAtual, data.linhas[data.linhas.length - 1]);
                
                // Mostrar mensagem de sucesso
                const toast = document.createElement('div');
                toast.className = 'alert alert-success alert-dismissible fade show position-fixed top-0 end-0 m-3';
                toast.style.zIndex = '9999';
                toast.innerHTML = `
                    <strong>Sucesso!</strong> Nova linha adicionada ao histórico.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                document.body.appendChild(toast);
                setTimeout(() => toast.remove(), 3000);
                
                btnAdicionarHistorico.disabled = false;
                btnAdicionarHistorico.innerHTML = '<i class="fas fa-plus"></i> Adicionar Linha';
            } catch (error) {
                console.error('Erro ao adicionar histórico:', error);
                alert('Erro ao adicionar linha ao histórico');
                btnAdicionarHistorico.disabled = false;
                btnAdicionarHistorico.innerHTML = '<i class="fas fa-plus"></i> Adicionar Linha';
            }
        });

        // Função auxiliar para escapar HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Função para atualizar histórico na tabela principal
        function atualizarHistoricoNaTabela(imobId, novaUltimaLinha) {
            // Encontrar todas as linhas da tabela
            const linhasTabela = document.querySelectorAll('#resultsTableBody tr');
            
            linhasTabela.forEach(linha => {
                // Verificar se a linha tem o imob_id correto
                const imobIdLinha = linha.getAttribute('data-imob-id');
                if (imobIdLinha === imobId) {
                    // Encontrar a célula do histórico (link)
                    const linkHistorico = linha.querySelector('.historico-link');
                    if (linkHistorico) {
                        // Atualizar o texto do link
                        linkHistorico.textContent = novaUltimaLinha || '[vazio]';
                        // Atualizar o title também
                        linkHistorico.setAttribute('title', novaUltimaLinha || '');
                    }
                }
            });
        }

        // Limpar campo ao fechar modal
        $('#modalHistorico').on('hidden.bs.modal', function() {
            novaLinhaHistorico.value = '';
            imobIdAtual = null;
        });

        // Função para ordenar tabela (index_3.php)
        function ordenarTabelaIndex3(column) {
            if (!dadosOrdenaveisIndex3 || dadosOrdenaveisIndex3.length === 0) return;
            
            // Alternar direção se clicar na mesma coluna
            if (sortColumnIndex3 === column) {
                sortDirectionIndex3 = sortDirectionIndex3 === 'asc' ? 'desc' : 'asc';
            } else {
                sortColumnIndex3 = column;
                sortDirectionIndex3 = 'asc';
            }
            
            aplicarOrdenacaoIndex3();
        }

        // Função para aplicar ordenação e re-renderizar tabela (index_3.php)
        function aplicarOrdenacaoIndex3() {
            if (!dadosOrdenaveisIndex3 || !sortColumnIndex3 || columnArrayGlobalIndex3.length === 0) return;
            
            const dadosOrdenados = [...dadosOrdenaveisIndex3].sort((a, b) => {
                let valA = a[sortColumnIndex3];
                let valB = b[sortColumnIndex3];
                
                // Tratar valores nulos/undefined
                if (valA === null || valA === undefined || valA === '') valA = '';
                if (valB === null || valB === undefined || valB === '') valB = '';
                
                // Converter para string para comparação
                valA = String(valA).toLowerCase().trim();
                valB = String(valB).toLowerCase().trim();
                
                // Tentar converter para número se ambos forem numéricos
                const numA = parseFloat(valA);
                const numB = parseFloat(valB);
                if (!isNaN(numA) && !isNaN(numB) && valA !== '' && valB !== '') {
                    valA = numA;
                    valB = numB;
                }
                
                let comparacao = 0;
                if (valA < valB) comparacao = -1;
                else if (valA > valB) comparacao = 1;
                
                return sortDirectionIndex3 === 'asc' ? comparacao : -comparacao;
            });
            
            // Atualizar dados ordenáveis
            dadosOrdenaveisIndex3 = dadosOrdenados;
            
            // Re-renderizar apenas o corpo da tabela
            const resultsTableBody = document.getElementById('resultsTableBody');
            
            let bodyHTML = '';
            dadosOrdenados.forEach((row) => {
                const imobId = row.imob_id || '';
                bodyHTML += `<tr data-imob-id="${imobId}">`;
                columnArrayGlobalIndex3.forEach(col => {
                    const value = row[col] !== null && row[col] !== undefined ? row[col] : '';
                    if (col === 'historico') {
                        bodyHTML += `<td title="${value}"><a href="#" class="historico-link" data-imob-id="${imobId}" style="color: #007bff; text-decoration: underline; cursor: pointer;">${value || '[vazio]'}</a></td>`;
                    } else {
                        bodyHTML += `<td title="${value}">${value}</td>`;
                    }
                });
                bodyHTML += '</tr>';
            });
            
            if (resultsTableBody) {
                resultsTableBody.innerHTML = bodyHTML;
            }
            
            // Re-aplicar event listeners dos links de histórico
            $(document).off('click', '.historico-link').on('click', '.historico-link', async function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                imobIdAtual = $(this).attr('data-imob-id');
                if (!imobIdAtual) {
                    alert('Erro: imob_id não encontrado');
                    return;
                }
                
                modalHistorico.show();
                await carregarHistorico(imobIdAtual);
            });
            
            // Atualizar indicadores de ordenação no cabeçalho
            document.querySelectorAll('.sortable-header').forEach(header => {
                const col = header.getAttribute('data-column');
                const headerName = columnNamesGlobalIndex3[col] || col;
                let sortIndicator = '';
                if (sortColumnIndex3 === col) {
                    sortIndicator = sortDirectionIndex3 === 'asc' ? ' <i class="fas fa-sort-up"></i>' : ' <i class="fas fa-sort-down"></i>';
                } else {
                    sortIndicator = ' <i class="fas fa-sort" style="opacity: 0.3;"></i>';
                }
                header.innerHTML = `${headerName}${sortIndicator}`;
            });
        }
    </script>

</body>

</html>