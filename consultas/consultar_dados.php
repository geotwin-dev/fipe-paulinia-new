<?php
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'mensagem' => 'Usuário não autenticado'
    ]);
    exit();
}

header('Content-Type: application/json; charset=utf-8');

// Incluir arquivo de conexão
include("../connection.php");

// Verificar se a conexão foi estabelecida
if ($pdo === null) {
    throw new Exception("Falha na conexão com o banco de dados");
}

try {
    // Verificar se os parâmetros foram enviados
    if (!isset($_POST['tabela']) || !isset($_POST['consulta_id'])) {
        throw new Exception("Parâmetros obrigatórios não fornecidos");
    }

    $tabela = $_POST['tabela'];
    $consultaId = (int)$_POST['consulta_id'];
    
    // Parâmetros do DataTables para server-side processing
    $draw = intval($_POST['draw'] ?? 0);
    $start = intval($_POST['start'] ?? 0);
    $length = intval($_POST['length'] ?? 10);
    $search = $_POST['search']['value'] ?? '';
    
    // Parâmetros de filtros customizados
    $filtrosCustomizados = [];
    if (isset($_POST['filtros_customizados'])) {
        $filtrosCustomizados = json_decode($_POST['filtros_customizados'], true) ?? [];
    }
    
    // Validar tabela
    $tabelasPermitidas = ['cadastro', 'desenhos'];
    if (!in_array($tabela, $tabelasPermitidas)) {
        throw new Exception("Tabela não permitida");
    }

    // Definir consultas baseadas na tabela e ID
    $consultas = [
        'cadastro' => [
            0 => [
                'sql' => "SELECT * FROM cadastro",
                'count_sql' => "SELECT COUNT(*) as total FROM cadastro",
                'colunas' => [
                    'id',
                    'inscricao',
                    'imob_id',
                    'cod_reduzido',
                    'matricula',
                    'logradouro',
                    'numero',
                    'bairro',
                    'cara_quarteirao',
                    'quadra',
                    'lote',
                    'lote_base',
                    'qdra_ins_parametro',
                    'situacao',
                    'total_construido',
                    'cep_segmento',
                    'cod_pessoa',
                    'nome_pessoa',
                    'cpf',
                    'cnpj',
                    'frac_ideal',
                    'area_terreno',
                    'complemento',
                    'inclusao',
                    'tipo_edificacao',
                    'tipo_utilizacao',
                    'categoria_propriedade',
                    'area_excedente',
                    'cara_quarteirao_alt',
                    'cod_valor',
                    'zona',
                    'cat_via',
                    'coef_aprov',
                    'terreo',
                    'demais',
                    'fator_prof_gleba',
                    'viela_sanit',
                    'segundo_pavimento',
                    'nome_loteamento',
                    'face_quadra',
                    'demais_faces',
                    'face',
                    'testada_principal',
                    'testada_2',
                    'testada_3',
                    'testada',
                    'utilizacao_area_a',
                    'utilizacao_area_b',
                    'utilizacao_area_c',
                    'num_apro_proj_1',
                    'dt_apro_proj_1',
                    'area_apro_proj_1',
                    'num_habitese_1',
                    'dt_habitese_1',
                    'area_habitese_1',
                    'num_apro_proj_2',
                    'dt_apro_proj_2',
                    'area_apro_proj_2',
                    'num_habitese_2',
                    'dt_habitese_2',
                    'area_habitese_2',
                    'num_apro_proj_3',
                    'dt_apro_proj_3',
                    'area_apro_proj_3',
                    'num_habitese_3',
                    'dt_habitese_3',
                    'area_habitese_3',
                    'num_apro_proj_4',
                    'dt_apro_proj_4',
                    'area_apro_proj_4',
                    'num_habitese_4',
                    'dt_habitese_4',
                    'area_habitese_4',
                    'area_construida_a',
                    'area_construida_b',
                    'area_construida_c',
                    'cartorio',
                    'cartorio_2',
                    'matricula_2',
                    'cep',
                    'apto_casa',
                    'loja_sala',
                    'bloco',
                    'galpao',
                    'condominio_edificio',
                    'unidade_condominio',
                    'acao',
                    'quadricula',
                    'id_quadra',
                    'id_lote',
                    'id_marcador',
                    'latitude',
                    'longitude'
                ]
            ],
            1 => [
                'sql' => "SELECT * FROM cadastro WHERE acao = 'DESDOBRAR'",
                'count_sql' => "SELECT COUNT(*) as total FROM cadastro WHERE acao = 'DESDOBRAR'",
                'colunas' => [
                    'id',
                    'inscricao',
                    'imob_id',
                    'cod_reduzido',
                    'matricula',
                    'logradouro',
                    'numero',
                    'bairro',
                    'cara_quarteirao',
                    'quadra',
                    'lote',
                    'lote_base',
                    'qdra_ins_parametro',
                    'situacao',
                    'total_construido',
                    'cep_segmento',
                    'cod_pessoa',
                    'nome_pessoa',
                    'cpf',
                    'cnpj',
                    'frac_ideal',
                    'area_terreno',
                    'complemento',
                    'inclusao',
                    'tipo_edificacao',
                    'tipo_utilizacao',
                    'categoria_propriedade',
                    'area_excedente',
                    'cara_quarteirao_alt',
                    'cod_valor',
                    'zona',
                    'cat_via',
                    'coef_aprov',
                    'terreo',
                    'demais',
                    'fator_prof_gleba',
                    'viela_sanit',
                    'segundo_pavimento',
                    'nome_loteamento',
                    'face_quadra',
                    'demais_faces',
                    'face',
                    'testada_principal',
                    'testada_2',
                    'testada_3',
                    'testada',
                    'utilizacao_area_a',
                    'utilizacao_area_b',
                    'utilizacao_area_c',
                    'num_apro_proj_1',
                    'dt_apro_proj_1',
                    'area_apro_proj_1',
                    'num_habitese_1',
                    'dt_habitese_1',
                    'area_habitese_1',
                    'num_apro_proj_2',
                    'dt_apro_proj_2',
                    'area_apro_proj_2',
                    'num_habitese_2',
                    'dt_habitese_2',
                    'area_habitese_2',
                    'num_apro_proj_3',
                    'dt_apro_proj_3',
                    'area_apro_proj_3',
                    'num_habitese_3',
                    'dt_habitese_3',
                    'area_habitese_3',
                    'num_apro_proj_4',
                    'dt_apro_proj_4',
                    'area_apro_proj_4',
                    'num_habitese_4',
                    'dt_habitese_4',
                    'area_habitese_4',
                    'area_construida_a',
                    'area_construida_b',
                    'area_construida_c',
                    'cartorio',
                    'cartorio_2',
                    'matricula_2',
                    'cep',
                    'apto_casa',
                    'loja_sala',
                    'bloco',
                    'galpao',
                    'condominio_edificio',
                    'unidade_condominio',
                    'acao',
                    'quadricula',
                    'id_quadra',
                    'id_lote',
                    'id_marcador',
                    'latitude',
                    'longitude'
                ]
            ],
            2 => [
                'sql' => "SELECT * FROM cadastro WHERE acao = 'AGRUPAR'",
                'count_sql' => "SELECT COUNT(*) as total FROM cadastro WHERE acao = 'AGRUPAR'",
                'colunas' => ['id',
                    'inscricao',
                    'imob_id',
                    'cod_reduzido',
                    'matricula',
                    'logradouro',
                    'numero',
                    'bairro',
                    'cara_quarteirao',
                    'quadra',
                    'lote',
                    'lote_base',
                    'qdra_ins_parametro',
                    'situacao',
                    'total_construido',
                    'cep_segmento',
                    'cod_pessoa',
                    'nome_pessoa',
                    'cpf',
                    'cnpj',
                    'frac_ideal',
                    'area_terreno',
                    'complemento',
                    'inclusao',
                    'tipo_edificacao',
                    'tipo_utilizacao',
                    'categoria_propriedade',
                    'area_excedente',
                    'cara_quarteirao_alt',
                    'cod_valor',
                    'zona',
                    'cat_via',
                    'coef_aprov',
                    'terreo',
                    'demais',
                    'fator_prof_gleba',
                    'viela_sanit',
                    'segundo_pavimento',
                    'nome_loteamento',
                    'face_quadra',
                    'demais_faces',
                    'face',
                    'testada_principal',
                    'testada_2',
                    'testada_3',
                    'testada',
                    'utilizacao_area_a',
                    'utilizacao_area_b',
                    'utilizacao_area_c',
                    'num_apro_proj_1',
                    'dt_apro_proj_1',
                    'area_apro_proj_1',
                    'num_habitese_1',
                    'dt_habitese_1',
                    'area_habitese_1',
                    'num_apro_proj_2',
                    'dt_apro_proj_2',
                    'area_apro_proj_2',
                    'num_habitese_2',
                    'dt_habitese_2',
                    'area_habitese_2',
                    'num_apro_proj_3',
                    'dt_apro_proj_3',
                    'area_apro_proj_3',
                    'num_habitese_3',
                    'dt_habitese_3',
                    'area_habitese_3',
                    'num_apro_proj_4',
                    'dt_apro_proj_4',
                    'area_apro_proj_4',
                    'num_habitese_4',
                    'dt_habitese_4',
                    'area_habitese_4',
                    'area_construida_a',
                    'area_construida_b',
                    'area_construida_c',
                    'cartorio',
                    'cartorio_2',
                    'matricula_2',
                    'cep',
                    'apto_casa',
                    'loja_sala',
                    'bloco',
                    'galpao',
                    'condominio_edificio',
                    'unidade_condominio',
                    'acao',
                    'quadricula',
                    'id_quadra',
                    'id_lote',
                    'id_marcador',
                    'latitude',
                    'longitude'
                ]
            ],
            4 => [
                'sql' => "SELECT * FROM cadastro WHERE acao = '' OR acao IS NULL",
                'count_sql' => "SELECT COUNT(*) as total FROM cadastro WHERE acao = '' OR acao IS NULL",
                'colunas' => ['id',
                    'inscricao',
                    'imob_id',
                    'cod_reduzido',
                    'matricula',
                    'logradouro',
                    'numero',
                    'bairro',
                    'cara_quarteirao',
                    'quadra',
                    'lote',
                    'lote_base',
                    'qdra_ins_parametro',
                    'situacao',
                    'total_construido',
                    'cep_segmento',
                    'cod_pessoa',
                    'nome_pessoa',
                    'cpf',
                    'cnpj',
                    'frac_ideal',
                    'area_terreno',
                    'complemento',
                    'inclusao',
                    'tipo_edificacao',
                    'tipo_utilizacao',
                    'categoria_propriedade',
                    'area_excedente',
                    'cara_quarteirao_alt',
                    'cod_valor',
                    'zona',
                    'cat_via',
                    'coef_aprov',
                    'terreo',
                    'demais',
                    'fator_prof_gleba',
                    'viela_sanit',
                    'segundo_pavimento',
                    'nome_loteamento',
                    'face_quadra',
                    'demais_faces',
                    'face',
                    'testada_principal',
                    'testada_2',
                    'testada_3',
                    'testada',
                    'utilizacao_area_a',
                    'utilizacao_area_b',
                    'utilizacao_area_c',
                    'num_apro_proj_1',
                    'dt_apro_proj_1',
                    'area_apro_proj_1',
                    'num_habitese_1',
                    'dt_habitese_1',
                    'area_habitese_1',
                    'num_apro_proj_2',
                    'dt_apro_proj_2',
                    'area_apro_proj_2',
                    'num_habitese_2',
                    'dt_habitese_2',
                    'area_habitese_2',
                    'num_apro_proj_3',
                    'dt_apro_proj_3',
                    'area_apro_proj_3',
                    'num_habitese_3',
                    'dt_habitese_3',
                    'area_habitese_3',
                    'num_apro_proj_4',
                    'dt_apro_proj_4',
                    'area_apro_proj_4',
                    'num_habitese_4',
                    'dt_habitese_4',
                    'area_habitese_4',
                    'area_construida_a',
                    'area_construida_b',
                    'area_construida_c',
                    'cartorio',
                    'cartorio_2',
                    'matricula_2',
                    'cep',
                    'apto_casa',
                    'loja_sala',
                    'bloco',
                    'galpao',
                    'condominio_edificio',
                    'unidade_condominio',
                    'acao',
                    'quadricula',
                    'id_quadra',
                    'id_lote',
                    'id_marcador',
                    'latitude',
                    'longitude'
                ]
            ],
            5 => [
                'sql' => "SELECT * FROM cadastro WHERE id_marcador = '' OR id_marcador IS NULL",
                'count_sql' => "SELECT COUNT(*) as total FROM cadastro WHERE id_marcador = '' OR id_marcador IS NULL",
                'colunas' => ['id',
                    'inscricao',
                    'imob_id',
                    'cod_reduzido',
                    'matricula',
                    'logradouro',
                    'numero',
                    'bairro',
                    'cara_quarteirao',
                    'quadra',
                    'lote',
                    'lote_base',
                    'qdra_ins_parametro',
                    'situacao',
                    'total_construido',
                    'cep_segmento',
                    'cod_pessoa',
                    'nome_pessoa',
                    'cpf',
                    'cnpj',
                    'frac_ideal',
                    'area_terreno',
                    'complemento',
                    'inclusao',
                    'tipo_edificacao',
                    'tipo_utilizacao',
                    'categoria_propriedade',
                    'area_excedente',
                    'cara_quarteirao_alt',
                    'cod_valor',
                    'zona',
                    'cat_via',
                    'coef_aprov',
                    'terreo',
                    'demais',
                    'fator_prof_gleba',
                    'viela_sanit',
                    'segundo_pavimento',
                    'nome_loteamento',
                    'face_quadra',
                    'demais_faces',
                    'face',
                    'testada_principal',
                    'testada_2',
                    'testada_3',
                    'testada',
                    'utilizacao_area_a',
                    'utilizacao_area_b',
                    'utilizacao_area_c',
                    'num_apro_proj_1',
                    'dt_apro_proj_1',
                    'area_apro_proj_1',
                    'num_habitese_1',
                    'dt_habitese_1',
                    'area_habitese_1',
                    'num_apro_proj_2',
                    'dt_apro_proj_2',
                    'area_apro_proj_2',
                    'num_habitese_2',
                    'dt_habitese_2',
                    'area_habitese_2',
                    'num_apro_proj_3',
                    'dt_apro_proj_3',
                    'area_apro_proj_3',
                    'num_habitese_3',
                    'dt_habitese_3',
                    'area_habitese_3',
                    'num_apro_proj_4',
                    'dt_apro_proj_4',
                    'area_apro_proj_4',
                    'num_habitese_4',
                    'dt_habitese_4',
                    'area_habitese_4',
                    'area_construida_a',
                    'area_construida_b',
                    'area_construida_c',
                    'cartorio',
                    'cartorio_2',
                    'matricula_2',
                    'cep',
                    'apto_casa',
                    'loja_sala',
                    'bloco',
                    'galpao',
                    'condominio_edificio',
                    'unidade_condominio',
                    'acao',
                    'quadricula',
                    'id_quadra',
                    'id_lote',
                    'id_marcador',
                    'latitude',
                    'longitude'
                ]
            ]
        ],
        'desenhos' => [
            3 => [
                'sql' => "SELECT * FROM desenhos WHERE camada = 'marcador_quadra' AND (cor = 'red' OR cor = '#FF0000')",
                'count_sql' => "SELECT COUNT(*) as total FROM desenhos WHERE camada = 'marcador_quadra' AND (cor = 'red' OR cor = '#FF0000')",
                'colunas' => [
                    'id',
                    'data_hora',
                    'usuario',
                    'quadricula',
                    'id_desenho',
                    'camada',
                    'quarteirao',
                    'quadra',
                    'lote',
                    'lote_base',
                    'tipo',
                    'cor',
                    'coordenadas'
                ]
            ]
        ]
    ];

    // Verificar se a consulta existe
    if (!isset($consultas[$tabela][$consultaId])) {
        throw new Exception("Consulta não encontrada para a tabela {$tabela} com ID {$consultaId}");
    }

    $consulta = $consultas[$tabela][$consultaId];
    $sqlBase = $consulta['sql'];
    $count_sql = $consulta['count_sql'];
    $colunas = $consulta['colunas'];

    // Contar total de registros (sem filtros)
    $stmt_count = $pdo->prepare($count_sql);
    $stmt_count->execute();
    $total_records = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Aplicar filtros (busca global + filtros customizados)
    $sqlFiltrado = $sqlBase;
    $count_sql_filtrado = $count_sql;
    $parametros = [];
    $clausulasWhere = [];
    
    // Filtro de busca global
    if ($search !== '') {
        $filtrosGlobais = [];
        foreach ($colunas as $coluna) {
            $filtrosGlobais[] = "$coluna LIKE :search";
        }
        $clausulasWhere[] = "(" . implode(" OR ", $filtrosGlobais) . ")";
        $parametros[':search'] = "%$search%";
    }
    
    // Filtros customizados
    if (!empty($filtrosCustomizados)) {
        foreach ($filtrosCustomizados as $index => $filtro) {
            if (!isset($filtro['campo']) || !in_array($filtro['campo'], $colunas)) {
                continue; // Ignorar filtros inválidos
            }
            
            $campo = $filtro['campo'];
            $tipo = $filtro['tipo'] ?? 'texto';
            $valor1 = $filtro['valor1'] ?? '';
            $valor2 = $filtro['valor2'] ?? '';
            
            switch ($tipo) {
                case 'texto':
                    if ($valor1 !== '') {
                        $paramKey = ":filtro_texto_{$index}";
                        $clausulasWhere[] = "$campo LIKE $paramKey";
                        $parametros[$paramKey] = "%$valor1%";
                    }
                    break;
                    
                case 'data':
                    if ($valor1 !== '' && $valor2 !== '') {
                        // Intervalo de datas
                        $paramKey1 = ":filtro_data_inicio_{$index}";
                        $paramKey2 = ":filtro_data_fim_{$index}";
                        $clausulasWhere[] = "DATE($campo) BETWEEN $paramKey1 AND $paramKey2";
                        $parametros[$paramKey1] = $valor1;
                        $parametros[$paramKey2] = $valor2;
                    } elseif ($valor1 !== '') {
                        // A partir da data
                        $paramKey = ":filtro_data_inicio_{$index}";
                        $clausulasWhere[] = "DATE($campo) >= $paramKey";
                        $parametros[$paramKey] = $valor1;
                    } elseif ($valor2 !== '') {
                        // Até a data
                        $paramKey = ":filtro_data_fim_{$index}";
                        $clausulasWhere[] = "DATE($campo) <= $paramKey";
                        $parametros[$paramKey] = $valor2;
                    }
                    break;
                    
                case 'numero':
                    if ($valor1 !== '' && $valor2 !== '') {
                        // Intervalo numérico
                        $paramKey1 = ":filtro_num_inicio_{$index}";
                        $paramKey2 = ":filtro_num_fim_{$index}";
                        $clausulasWhere[] = "$campo BETWEEN $paramKey1 AND $paramKey2";
                        $parametros[$paramKey1] = $valor1;
                        $parametros[$paramKey2] = $valor2;
                    } elseif ($valor1 !== '') {
                        // A partir do número
                        $paramKey = ":filtro_num_inicio_{$index}";
                        $clausulasWhere[] = "$campo >= $paramKey";
                        $parametros[$paramKey] = $valor1;
                    } elseif ($valor2 !== '') {
                        // Até o número
                        $paramKey = ":filtro_num_fim_{$index}";
                        $clausulasWhere[] = "$campo <= $paramKey";
                        $parametros[$paramKey] = $valor2;
                    }
                    break;
            }
        }
    }
    
    // Aplicar cláusulas WHERE se existirem
    if (!empty($clausulasWhere)) {
        $whereClause = " AND (" . implode(" AND ", $clausulasWhere) . ")";
        
        // Verificar se já existe WHERE na consulta
        if (stripos($sqlBase, 'WHERE') !== false) {
            $sqlFiltrado = $sqlBase . $whereClause;
            $count_sql_filtrado = $count_sql . $whereClause;
        } else {
            $whereClause = " WHERE (" . implode(" AND ", $clausulasWhere) . ")";
            $sqlFiltrado = $sqlBase . $whereClause;
            $count_sql_filtrado = $count_sql . $whereClause;
        }
    }
    
    // Contar registros filtrados
    $stmt_filtered = $pdo->prepare($count_sql_filtrado);
    foreach ($parametros as $param => $valor) {
        $stmt_filtered->bindValue($param, $valor);
    }
    $stmt_filtered->execute();
    $recordsFiltered = $stmt_filtered->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Aplicar paginação
    $sqlPaginado = $sqlFiltrado . " LIMIT :start, :length";
    
    // Executar consulta paginada
    $stmt = $pdo->prepare($sqlPaginado);
    foreach ($parametros as $param => $valor) {
        $stmt->bindValue($param, $valor);
    }
    $stmt->bindValue(':start', $start, PDO::PARAM_INT);
    $stmt->bindValue(':length', $length, PDO::PARAM_INT);
    $stmt->execute();
    $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obter tipos das colunas da tabela
    $tipos_colunas = [];
    $stmt_describe = $pdo->prepare("DESCRIBE " . $tabela);
    $stmt_describe->execute();
    $estrutura_tabela = $stmt_describe->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($estrutura_tabela as $coluna_info) {
        $nome_coluna = $coluna_info['Field'];
        $tipo_coluna = $coluna_info['Type'];
        
        // Determinar tipo simplificado
        if (strpos($tipo_coluna, 'date') !== false || strpos($tipo_coluna, 'time') !== false) {
            $tipo_simplificado = 'data';
        } elseif (strpos($tipo_coluna, 'int') !== false || strpos($tipo_coluna, 'decimal') !== false || strpos($tipo_coluna, 'float') !== false || strpos($tipo_coluna, 'double') !== false) {
            $tipo_simplificado = 'numero';
        } else {
            $tipo_simplificado = 'texto';
        }
        
        $tipos_colunas[$nome_coluna] = $tipo_simplificado;
    }

    // Preparar resposta no formato DataTables server-side
    $response = [
        'draw' => $draw,
        'recordsTotal' => (int)$total_records,
        'recordsFiltered' => (int)$recordsFiltered,
        'data' => $dados,
        'success' => true,
        'colunas' => $colunas,
        'tipos_colunas' => $tipos_colunas
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    $response = [
        'success' => false,
        'mensagem' => 'Erro: ' . $e->getMessage()
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    $response = [
        'success' => false,
        'mensagem' => 'Erro no banco de dados: ' . $e->getMessage()
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}
