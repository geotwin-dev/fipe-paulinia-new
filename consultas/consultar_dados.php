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
    $sql = $consulta['sql'];
    $count_sql = $consulta['count_sql'];
    $colunas = $consulta['colunas'];

    // Contar total de registros
    $stmt_count = $pdo->prepare($count_sql);
    $stmt_count->execute();
    $total_records = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Executar consulta completa
    $stmt = $pdo->prepare($sql);
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

    // Preparar resposta
    $response = [
        'recordsTotal' => (int)$total_records,
        'recordsShown' => (int)$total_records,
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
