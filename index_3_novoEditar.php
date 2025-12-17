<?php
session_start();

// colocar o horario correto saopaulo
date_default_timezone_set('America/Sao_Paulo');

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit();
}

// Recebe os parâmetros da URL
$id = $_GET['id'] ?? null;
$quarteirao = $_GET['quarteirao'] ?? null;
$loteamento = $_GET['loteamento'] ?? null;
$id_desenho = $_GET['id_desenho'] ?? null;
$quadricula = $_GET['quadricula'] ?? null;

// Validação básica
if (!$id || !$quarteirao || !$quadricula) {
    die('Parâmetros inválidos. ID, Quarteirão e Quadrícula são obrigatórios.');
}

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

// ========================================================================
// BUSCAR DOCUMENTOS DO QUARTEIRÃO
// ========================================================================
$documentosQuarteirao = [];
$pastaQuarteirao = "loteamentos_quadriculas/pdfs_quarteiroes/" . $quarteiraoNormalizado;

if (is_dir($pastaQuarteirao)) {
    $arquivos = scandir($pastaQuarteirao);
    foreach ($arquivos as $arquivo) {
        if ($arquivo !== '.' && $arquivo !== '..') {
            $extensao = strtolower(pathinfo($arquivo, PATHINFO_EXTENSION));
            if ($extensao === 'pdf' || $extensao === 'png' || $extensao === 'jpg' || $extensao === 'jpeg') {
                $documentosQuarteirao[] = [
                    'nome' => $arquivo,
                    'caminho' => $pastaQuarteirao . '/' . $arquivo,
                    'tipo' => $extensao
                ];
            }
        }
    }
    // Ordenar por nome
    usort($documentosQuarteirao, function($a, $b) {
        return strcmp($a['nome'], $b['nome']);
    });
}

// ========================================================================
// BUSCAR DOCUMENTOS DO LOTEAMENTO
// ========================================================================
$documentosLoteamento = [];

if ($loteamento) {
    // Caminho do JSON da quadrícula
    $jsonPath = "loteamentos_quadriculas/json/resultados_quadricula_" . $quadricula . ".json";
    
    if (file_exists($jsonPath)) {
        $jsonContent = file_get_contents($jsonPath);
        $dados = json_decode($jsonContent, true);
        
        if ($dados && isset($dados['resultados']['loteamentos']) && is_array($dados['resultados']['loteamentos'])) {
            // Procurar o loteamento correspondente
            foreach ($dados['resultados']['loteamentos'] as $loteamentoData) {
                // Comparar nome do loteamento (case-insensitive)
                if (isset($loteamentoData['nome']) && 
                    strtolower(trim($loteamentoData['nome'])) === strtolower(trim($loteamento))) {
                    
                    // Encontrar arquivos associados
                    if (isset($loteamentoData['arquivos_associados']) && 
                        is_array($loteamentoData['arquivos_associados']) && 
                        count($loteamentoData['arquivos_associados']) > 0) {
                        
                        foreach ($loteamentoData['arquivos_associados'] as $arquivo) {
                            $caminhoCompleto = "loteamentos_quadriculas/pdf/" . $arquivo;
                            
                            // Verificar se o arquivo existe
                            if (file_exists($caminhoCompleto)) {
                                $extensao = strtolower(pathinfo($arquivo, PATHINFO_EXTENSION));
                                $documentosLoteamento[] = [
                                    'nome' => $arquivo,
                                    'caminho' => $caminhoCompleto,
                                    'tipo' => $extensao
                                ];
                            }
                        }
                    }
                    break; // Loteamento encontrado, sair do loop
                }
            }
        }
    }
    
    // Ordenar por nome
    usort($documentosLoteamento, function($a, $b) {
        return strcmp($a['nome'], $b['nome']);
    });
}

// Buscar dados da quadrícula para inicializar os mapas
include("connection.php");

// Pegar coordenadas do desenho atual para centralizar o mapa
$sql = "SELECT coordenadas FROM desenhos WHERE id = :id LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute(['id' => $id]);
$coords = $stmt->fetch(PDO::FETCH_ASSOC);

$coords = json_decode($coords['coordenadas'], true);
$lat = $coords[0]['lat'];
$lng = $coords[0]['lng'];

// Função para verificar se quarteirão contém letras
function quarteiraoTemLetras($quarteirao) {
    $quarteirao = trim((string)$quarteirao);
    // Verifica se contém algum caractere que não seja dígito
    return preg_match('/[^0-9]/', $quarteirao) === 1;
}

// Verificar se o quarteirão tem letras
$temLetras = quarteiraoTemLetras($quarteirao);

//=================================================================
// Buscar todos os desenhos do quarteirão e quadrícula
//=================================================================
if ($temLetras) {
    // Se tem letras, tratar como string (comparação exata)
    $sqlDesenhos = "SELECT d.* 
                    FROM desenhos d
                    WHERE d.quarteirao = :quarteirao
                    AND d.quadricula = :quadricula
                    AND (d.camada = 'poligono_lote' OR d.camada = 'marcador_quadra') 
                    ORDER BY d.id";
    
    $stmtDesenhos = $pdo->prepare($sqlDesenhos);
    $stmtDesenhos->execute([
        ':quarteirao' => $quarteirao,
        ':quadricula' => $quadricula
    ]);
} else {
    // Se é só números, tratar como inteiro (normaliza zeros à esquerda)
    $sqlDesenhos = "SELECT d.* 
                    FROM desenhos d
                    WHERE CAST(d.quarteirao AS UNSIGNED) = CAST(:quarteirao AS UNSIGNED)
                    AND d.quadricula = :quadricula
                    AND (d.camada = 'poligono_lote' OR d.camada = 'marcador_quadra') 
                    ORDER BY d.id";
    
    $stmtDesenhos = $pdo->prepare($sqlDesenhos);
    $stmtDesenhos->execute([
        ':quarteirao' => $quarteirao,
        ':quadricula' => $quadricula
    ]);
}

$todosDesenhos = $stmtDesenhos->fetchAll(PDO::FETCH_ASSOC);

// Arrays para MAPA ANTERIOR (esquerda)
$poligonoLoteAnterior = [];
$marcadorQuadraAnterior = [];

// Arrays para MAPA POSTERIOR (direita)
$poligonoLotePosterior = [];
$marcadorQuadraPosterior = [];

//contar quantas linhas tem $todosDesenhos
$totalDesenhos = count($todosDesenhos);

// Percorrer todos os desenhos e aplicar regras específicas
foreach ($todosDesenhos as $desenho) {
    
    $camada = $desenho['camada'] ?? '';
    $status = $desenho['status'];

    // Filtrar apenas as camadas que queremos: poligono_lote e marcador_quadra
    if ($camada !== 'poligono_lote' && $camada !== 'marcador_quadra') {
        continue;
    }

    // ========================================================================
    // LÓGICA PARA MAPA ANTERIOR (Esquerda)
    // ========================================================================

    $sqlVerificaPosterior = "SELECT id FROM desdobros_unificacoes 
                                 WHERE id_desenho_posterior = :id_desenho 
                                 LIMIT 1";
    $stmtVerificaPosterior = $pdo->prepare($sqlVerificaPosterior);
    $stmtVerificaPosterior->execute(['id_desenho' => $desenho['id']]);
    $existeEmPosterior = $stmtVerificaPosterior->fetch(PDO::FETCH_ASSOC);



    if ($status == 1) {
        // Status 1: Incluir EXCETO se está em desdobros_unificacoes.id_desenho_posterior
        

        if (!$existeEmPosterior) {
            // NÃO está em posterior, então adiciona no anterior
            if ($camada === 'poligono_lote') {
                $poligonoLoteAnterior[] = $desenho;
            } else {
                $marcadorQuadraAnterior[] = $desenho;
            }
        }
    } else if ($status == 0) {
        // Status 0: Incluir APENAS se está em desdobros_unificacoes.id_desenho_anterior
        $sqlVerificaAnterior = "SELECT id FROM desdobros_unificacoes 
                                WHERE id_desenho_anterior = :id_desenho 
                                LIMIT 1";
        $stmtVerificaAnterior = $pdo->prepare($sqlVerificaAnterior);
        $stmtVerificaAnterior->execute(['id_desenho' => $desenho['id']]);
        $existeEmAnterior = $stmtVerificaAnterior->fetch(PDO::FETCH_ASSOC);

        if ($existeEmAnterior && $existeEmPosterior) {
            continue;
        }

        if ($existeEmAnterior && !$existeEmPosterior) {
            // Está em anterior, então adiciona
            if ($camada === 'poligono_lote') {
                $poligonoLoteAnterior[] = $desenho;
            } else {
                $marcadorQuadraAnterior[] = $desenho;
            }
        }
    }

    // ========================================================================
    // LÓGICA PARA MAPA POSTERIOR (Direita)
    // ========================================================================
    if ($status == 1) {
        // Status 1: Mostrar todos
        if ($camada === 'poligono_lote') {
            $poligonoLotePosterior[] = $desenho;
        } else {
            $marcadorQuadraPosterior[] = $desenho;
        }
    }
    // Status 0: NUNCA mostrar no posterior
}

// Converte para JSON para usar no JavaScript
$poligonoLoteAnteriorJSON = json_encode($poligonoLoteAnterior);
$marcadorQuadraAnteriorJSON = json_encode($marcadorQuadraAnterior);
$poligonoLotePosteriorJSON = json_encode($poligonoLotePosterior);
$marcadorQuadraPosteriorJSON = json_encode($marcadorQuadraPosterior);

//=================================================================

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Q - <?php echo htmlspecialchars($quarteirao); ?></title>

    <!-- Bootstrap CSS -->
    <link href="bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="bibliotecas/all.min.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body,
        html {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }

        body {
            overflow-x: hidden;
            overflow-y: auto;
        }

        .navbar-titulo {
            font-size: 16px;
            font-weight: bold;
        }

        .navbar-botoes {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }

        .mapa-titulo {
            position: absolute;
            top: 10px;
            left: 10px;
            z-index: 1000;
            background: rgba(255, 255, 255, 0.95);
            padding: 8px 15px;
            border-radius: 5px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
            font-weight: bold;
            font-size: 14px;
            color: #333;
        }

        .btn-edicoes {
            border-radius: 8px;
            padding: 8px 20px;
            font-size: 14px;
            margin: 0 5px;
        }

        .btn-edicoes.active {
            background-color: #ffc107 !important;
            color: #000 !important;
            border-color: #ffc107 !important;
            font-weight: bold;
        }

        .container-mapas {
            display: flex;
            width: 100%;
            height: 100vh;
        }

        .mapa-container {
            flex: 1;
            height: 100%;
            position: relative;
            border-right: 2px solid #333;
            display: flex;
            flex-direction: column;
        }

        .mapa-container:last-child {
            border-right: none;
        }

        .mapa-navbar {
            color: white;
            padding: 10px 20px;
            display: flex;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            z-index: 1001;
            height: 50px;
            flex-shrink: 0;
        }

        .mapa-wrapper {
            width: 100%;
            flex: 1;
            position: relative;
            min-height: 0; /* Permite que o flex funcione corretamente */
        }

        .mapa {
            width: 100%;
            height: 100%;
        }

        #areaPoligonoDisplay {
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: rgba(255, 255, 255, 0.95);
            padding: 10px 15px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.3);
            z-index: 1000;
            font-weight: bold;
            font-size: 14px;
            color: #333;
            pointer-events: none;
        }

        .tabela-cadastros-container {
            min-height: 100vh;
            padding: 30px;
            background: #f8f9fa;
            border-top: 3px solid #343a40;
        }

        .tabela-cadastros-titulo {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 20px;
            color: #343a40;
        }

        .table-responsive {
            background: white;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow-x: auto;
        }

        .table-responsive table {
            min-width: 1800px;
            white-space: nowrap;
            font-size: 11px;
        }

        .table-responsive th {
            position: sticky;
            top: 0;
            background: #343a40 !important;
            z-index: 10;
            font-size: 10px;
            padding: 6px 8px;
            font-weight: 600;
        }

        .table-responsive td {
            padding: 5px 8px;
            vertical-align: middle;
        }

        /* Estilos para linhas da tabela de cadastros */
        /* Sobrescrever estilos do Bootstrap table-striped */
        .table-striped tbody tr.linha-verde,
        .table-striped tbody tr.linha-verde:nth-of-type(odd),
        .table-striped tbody tr.linha-verde:nth-of-type(even),
        .table-striped tbody tr.linha-verde td,
        .table-striped tbody tr.linha-verde:nth-of-type(odd) td,
        .table-striped tbody tr.linha-verde:nth-of-type(even) td {
            background-color:rgb(172, 255, 172) !important; /* Lime sólido */
        }

        .table-striped tbody tr.linha-vermelha,
        .table-striped tbody tr.linha-vermelha:nth-of-type(odd),
        .table-striped tbody tr.linha-vermelha:nth-of-type(even),
        .table-striped tbody tr.linha-vermelha td,
        .table-striped tbody tr.linha-vermelha:nth-of-type(odd) td,
        .table-striped tbody tr.linha-vermelha:nth-of-type(even) td {
            background-color:rgb(255, 175, 175) !important; /* Vermelho sólido */
        }
        
        .table-striped tbody tr.linha-cadastro.linha-selecionada,
        .table-striped tbody tr.linha-cadastro.linha-selecionada:nth-of-type(odd),
        .table-striped tbody tr.linha-cadastro.linha-selecionada:nth-of-type(even),
        .table-striped tbody tr.linha-cadastro.linha-selecionada td,
        .table-striped tbody tr.linha-cadastro.linha-selecionada:nth-of-type(odd) td,
        .table-striped tbody tr.linha-cadastro.linha-selecionada:nth-of-type(even) td {
            background-color:rgb(255, 255, 147) !important; /* Amarelo sólido */
            font-weight: bold;
            box-shadow: 0 0 5px rgba(255, 255, 0, 0.5);
        }
        
        /* Estilos gerais (fallback) */
        .linha-cadastro.linha-selecionada,
        .linha-cadastro.linha-selecionada td {
            background-color: #FFFF00 !important;
            font-weight: bold;
        }

        .linha-verde,
        .linha-verde td {
            background-color: #00FF00 !important;
        }

        .linha-vermelha,
        .linha-vermelha td {
            background-color: #FF0000 !important;
        }

        .linha-azul-ciano,
        .linha-azul-ciano td {
            color:rgb(0, 0, 0) !important; /* Azul ciano no texto */
            font-weight: bold;
            font-style: italic;
        }

        /* Estilos para o quadro de documentos */
        .documentos-container {
            position: absolute;
            bottom: 10px;
            left: 10px;
            z-index: 1000;
            background: rgba(255, 255, 255, 0.95);
            padding: 10px 12px;
            border-radius: 5px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
            max-width: 300px;
            max-height: 250px;
            overflow-y: auto;
        }

        .documentos-titulo {
            font-size: 16px;
            font-weight: bold;
            color: #333;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .documentos-lista {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .documento-item {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 4px 6px;
            background: #f8f9fa;
            border-radius: 3px;
            font-size: 12px;
            transition: background 0.2s;
        }

        .documento-item:hover {
            background: #e9ecef;
        }

        .documento-item a {
            color: #007bff;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
            flex: 1;
            min-width: 0;
        }

        .documento-item a:hover {
            text-decoration: underline;
        }

        .documento-item .documento-icone {
            font-size: 12px;
            flex-shrink: 0;
        }

        .documento-item .documento-nome {
            flex: 1;
            word-break: break-word;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .documentos-vazio {
            color: #6c757d;
            font-size: 10px;
            font-style: italic;
            padding: 5px;
        }

        /* Estilos para checkbox da ortofoto */
        .documentos-ortofoto-control {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 8px;
            padding: 4px 0;
            font-size: 16px;
        }

        .documentos-ortofoto-control input[type="checkbox"] {
            margin: 0;
            cursor: pointer;
        }

        .documentos-ortofoto-control label {
            margin: 0;
            cursor: pointer;
            font-weight: normal;
            color: #333;
            user-select: none;
        }
    </style>
</head>

<body>

    <div class="container-mapas">
        <!-- Mapa 1 (Esquerda) -->
        <div class="mapa-container">
            <!-- Navbar do Mapa Esquerdo -->
            <div class="mapa-navbar" style="justify-content: start; background: #343a40;">
                <div class="navbar-titulo">
                    <i class="fas fa-map-marked-alt"></i> Quadrícula <?php echo htmlspecialchars($quadricula); ?> - Quarteirão <?php echo htmlspecialchars($quarteirao); ?>
                </div>
            </div>

            <div class="mapa-wrapper">
                <div class="mapa-titulo">
                    <i class="fas fa-map-marked-alt"></i> Situação atual
                    <!--
                    <button id="btnVertices" class="btn btn-primary" style="margin-left: 10px;">
                        <i class="fas fa-edit"></i> Vértices
                    </button>
                    -->
                </div>
                <div id="map1" class="mapa"></div>

                <!-- Quadro de Documentos (absoluto) - Unificado -->
                <?php 
                // Combinar todos os documentos
                $todosDocumentos = [];
                
                // Adicionar documentos do quarteirão
                if (count($documentosQuarteirao) > 0) {
                    foreach ($documentosQuarteirao as $doc) {
                        $doc['origem'] = 'Quarteirão';
                        $todosDocumentos[] = $doc;
                    }
                }
                
                // Adicionar documentos do loteamento
                if (count($documentosLoteamento) > 0) {
                    foreach ($documentosLoteamento as $doc) {
                        $doc['origem'] = 'Loteamento';
                        $todosDocumentos[] = $doc;
                    }
                }
                
                // Ordenar todos por nome
                usort($todosDocumentos, function($a, $b) {
                    return strcmp($a['nome'], $b['nome']);
                });
                ?>
                
                <div class="documentos-container">
                    <div class="documentos-ortofoto-control">
                        <input type="checkbox" id="chkOrtofoto" checked>
                        <label for="chkOrtofoto">
                            <i class="fas fa-satellite"></i> Ortofoto
                        </label>
                    </div>
                    <?php if (count($todosDocumentos) > 0): ?>
                    <div class="documentos-titulo">
                        <i class="fas fa-file-pdf"></i> Documentos
                    </div>
                    <div class="documentos-lista">
                        <?php foreach ($todosDocumentos as $doc): ?>
                            <div class="documento-item">
                                <?php if ($doc['tipo'] === 'pdf'): ?>
                                    <a href="<?php echo htmlspecialchars($doc['caminho']); ?>" target="_blank">
                                        <i class="fas fa-file-pdf documento-icone" style="color: #dc3545;"></i>
                                        <span class="documento-nome" title="<?php echo htmlspecialchars($doc['nome'] . ' (' . $doc['origem'] . ')'); ?>">
                                            <?php echo htmlspecialchars($doc['nome']); ?>
                                        </span>
                                    </a>
                                <?php elseif (in_array($doc['tipo'], ['png', 'jpg', 'jpeg'])): ?>
                                    <a href="<?php echo htmlspecialchars($doc['caminho']); ?>" target="_blank">
                                        <i class="fas fa-file-image documento-icone" style="color: #28a745;"></i>
                                        <span class="documento-nome" title="<?php echo htmlspecialchars($doc['nome'] . ' (' . $doc['origem'] . ')'); ?>">
                                            <?php echo htmlspecialchars($doc['nome']); ?>
                                        </span>
                                    </a>
                                <?php else: ?>
                                    <a href="<?php echo htmlspecialchars($doc['caminho']); ?>" target="_blank">
                                        <i class="fas fa-file documento-icone"></i>
                                        <span class="documento-nome" title="<?php echo htmlspecialchars($doc['nome'] . ' (' . $doc['origem'] . ')'); ?>">
                                            <?php echo htmlspecialchars($doc['nome']); ?>
                                        </span>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="documentos-vazio">
                        Nenhum documento encontrado.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Mapa 2 (Direita) -->
        <div class="mapa-container">
            <!-- Navbar do Mapa Direito -->
            <div class="mapa-navbar" style="justify-content: center; background:rgb(75, 84, 92);">
                <div class="navbar-botoes">
                    <button class="btn btn-light btn-edicoes" id="btnDesdobrar">
                        <i class="fas fa-cut"></i> Desdobrar
                    </button>
                    <button class="btn btn-light btn-edicoes" id="btnUnificar">
                        <i class="fa-regular fa-object-ungroup"></i> Unificar
                    </button>
                    <button class="btn btn-success btn-edicoes" id="btnDividir" style="display: none;">
                        <i class="fas fa-check"></i> Dividir
                    </button>
                    <button class="btn btn-success btn-edicoes" id="btnUnir" style="display: none;">
                        <i class="fas fa-check"></i> Unir
                    </button>
                    <button class="btn btn-secondary btn-edicoes" id="btnCancelar" style="display: none;">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                </div>
            </div>

            <div class="mapa-wrapper">
                <div class="mapa-titulo">
                    <i class="fas fa-map-marked-alt"></i> Situação pretendida
                </div>
                <div id="map2" class="mapa"></div>
                <!-- Elemento para exibir área do polígono selecionado -->
                <div id="areaPoligonoDisplay" style="display: none;">
                    <i class="fas fa-ruler-combined"></i> <span id="areaPoligonoValor">0</span> m²
                </div>
            </div>
        </div>
    </div>

    <!-- Área da Tabela de Cadastros -->
    <div class="tabela-cadastros-container">
        <div class="tabela-cadastros-titulo">
            <i class="fas fa-list"></i> Cadastros do Quarteirão <span id="numeroQuarteiraoTabela"><?php echo htmlspecialchars($quarteirao); ?></span>
            <button class="btn btn-primary btn-sm ml-3" id="btnAssociar" style="display: none; margin-left: 15px;">
                <i class="fas fa-link"></i> Associar
            </button>
            <button class="btn btn-danger btn-sm ml-3" id="btnDesassociar" style="display: none; margin-left: 15px;">
                <i class="fas fa-unlink"></i> Desassociar
            </button>
        </div>
        <div id="loadingCadastros" class="text-center" style="display: none;">
            <i class="fas fa-spinner fa-spin fa-2x"></i>
            <p>Carregando cadastros...</p>
        </div>
        <div id="conteudoCadastros">
            <!-- Tabela será carregada aqui -->
        </div>
    </div>

    <!-- jQuery -->
    <script src="jquery.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="bootstrap.bundle.min.js"></script>

    <!-- Proj4js para conversão de coordenadas -->
    <script src="bibliotecas/proj4.js"></script>

    <!-- Turf.js para operações geográficas -->
    <script src="bibliotecas/turf.min.js"></script>

    <!-- Google Maps API -->
    <script>
        (g => {
            var h, a, k, p = "The Google Maps JavaScript API",
                c = "google",
                l = "importLibrary",
                q = "__ib__",
                m = document,
                b = window;
            b = b[c] || (b[c] = {});
            var d = b.maps || (b.maps = {}),
                r = new Set,
                e = new URLSearchParams,
                u = () => h || (h = new Promise(async (f, n) => {
                    await (a = m.createElement("script"));
                    e.set("libraries", [...r] + "");
                    for (k in g) e.set(k.replace(/[A-Z]/g, t => "_" + t[0].toLowerCase()), g[k]);
                    e.set("callback", c + ".maps." + q);
                    a.src = `https://maps.${c}apis.com/maps/api/js?` + e;
                    d[q] = f;
                    a.onerror = () => h = n(Error(p + " could not load."));
                    a.nonce = m.querySelector("script[nonce]")?.nonce || "";
                    m.head.append(a)
                }));
            d[l] ? console.warn(p + " only loads once. Ignoring:", g) : d[l] = (f, ...n) => r.add(f) && u().then(() => d[l](f, ...n))
        })
        ({
            key: "AIzaSyBLPXuO8WNaFICoY6YxGaZCi-gOHCLNkrQ",
            v: "weekly"
        });
    </script>

    <script>
        // ============================================================================
        // FUNÇÕES AUXILIARES PARA ASSOCIAÇÃO
        // ============================================================================
        
        // Normalizar quarteirão (remove zeros à esquerda, trata strings)
        function normalizarQuarteirao(quarteirao) {
            if (!quarteirao) return '';
            // Converter para string e remover espaços
            let str = String(quarteirao).trim();
            // Remover zeros à esquerda
            str = str.replace(/^0+/, '');
            // Se ficou vazio, retornar '0'
            return str || '0';
        }

        // Função para atualizar linha na tabela quando desenho muda de verde para vermelho
        function atualizarLinhaTabelaParaVermelho(quarteirao, lote) {
            if (!quarteirao || !lote) return;
            
            const quarteiraoNorm = normalizarQuarteirao(quarteirao);
            const loteNorm = String(lote || '').trim();
            
            // Procurar linha correspondente na tabela
            $('.linha-cadastro').each(function() {
                const $linha = $(this);
                const quarteiraoLinha = normalizarQuarteirao($linha.data('quarteirao'));
                const loteLinha = String($linha.data('lote') || '').trim();
                
                if (quarteiraoLinha === quarteiraoNorm && loteLinha === loteNorm) {
                    // Encontrou a linha - mudar de verde para vermelho
                    $linha.removeClass('linha-verde').addClass('linha-vermelha');
                    $linha.data('esta-no-mapa', false);
                    return false; // Sair do loop
                }
            });
        }

        // Verificar se um registro está no mapa (baseado em quarteirão e lote)
        function registroEstaNoMapa(registro) {
            const quarteiraoRegistro = normalizarQuarteirao(registro.cara_quarteirao);
            const loteRegistro = String(registro.lote || '').trim();
            
            // Verificar nos polígonos do mapa posterior
            for (let poligono of poligonosPosteriorArray) {
                const desenho = poligono.desenhoCompleto;
                const quarteiraoDesenho = normalizarQuarteirao(desenho.quarteirao);
                const loteDesenho = String(desenho.lote || '').trim();
                
                if (quarteiraoRegistro === quarteiraoDesenho && loteRegistro === loteDesenho && loteRegistro !== '') {
                    return true;
                }
            }
            
            return false;
        }

        // Encontrar polígono/marcador no mapa por quarteirão e lote
        function encontrarDesenhoNoMapa(quarteirao, lote) {
            const quarteiraoNorm = normalizarQuarteirao(quarteirao);
            const loteNorm = String(lote || '').trim();
            
            if (!loteNorm) return null;
            
            // Procurar polígono
            const poligono = poligonosPosteriorArray.find(p => {
                const desenho = p.desenhoCompleto;
                const qDesenho = normalizarQuarteirao(desenho.quarteirao);
                const lDesenho = String(desenho.lote || '').trim();
                return qDesenho === quarteiraoNorm && lDesenho === loteNorm;
            });
            
            // Procurar marcador
            const marcador = marcadoresPosteriorArray.find(m => {
                const desenho = m.desenhoCompleto;
                const qDesenho = normalizarQuarteirao(desenho.quarteirao);
                const lDesenho = String(desenho.lote || '').trim();
                return qDesenho === quarteiraoNorm && lDesenho === loteNorm;
            });
            
            return { poligono, marcador };
        }

        // Variáveis para controle de seleção de associação
        let desenhoSelecionadoAssociacao = {
            poligono: null,
            marcador: null,
            isVermelho: false
        };
        
        let linhaSelecionadaAssociacao = {
            elemento: null,
            dados: null,
            isVermelho: false
        };

        // ============================================================================
        // FUNÇÃO PARA CARREGAR CADASTROS DO QUARTEIRÃO
        // ============================================================================
        function carregarCadastrosQuarteirao(numeroQuarteirao) {
            console.log('Carregando cadastros do quarteirão:', numeroQuarteirao);

            // Atualiza o número do quarteirão na tabela
            $('#numeroQuarteiraoTabela').text(numeroQuarteirao);

            // Mostra o loading
            $('#loadingCadastros').show();
            $('#conteudoCadastros').html('');

            // Faz a requisição AJAX
            $.ajax({
                url: 'index_3_novo_buscar_quarteiroes.php',
                method: 'POST',
                data: {
                    quarteirao: numeroQuarteirao
                },
                dataType: 'json',
                success: function(response) {
                    $('#loadingCadastros').hide();

                    if (response.success && response.dados && response.dados.length > 0) {
                        // Cria a tabela com os dados
                        let tabelaHTML = `
                            <div class="table-responsive">
                                <table class="table table-striped table-bordered table-hover table-sm">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Quantidade</th>
                                            <th>Inscrição</th>
                                            <th>Imob ID</th>
                                            <th>CNPJ</th>
                                            <th>Bairro</th>
                                            <th>Loteamento</th>
                                            <th>Quarteirão</th>
                                            <th>Quadra</th>
                                            <th>Lote</th>
                                            <th>Logradouro</th>
                                            <th>Número</th>
                                            <th>Zona</th>
                                            <th>Cat. Via</th>
                                            <th>Área Terreno (m²)</th>
                                            <th>Tipo Utilização</th>
                                            <th>Área Construída A (m²)</th>
                                            <th>Utilização Área A</th>
                                            
                                        </tr>
                                    </thead>
                                    <tbody>
                        `;

                        response.dados.forEach(function(item, index) {
                            // Verificar se está no mapa
                            const estaNoMapa = registroEstaNoMapa(item);
                            const classeLinha = estaNoMapa ? 'linha-verde' : 'linha-vermelha';
                            
                            // Verificar se o CNPJ é '45.751.435/0001-06' para aplicar cor azul ciano no texto
                            const cnpjEspecial = '45.751.435/0001-06';
                            const temCnpjEspecial = item.cnpj && item.cnpj.toString().trim() === cnpjEspecial;
                            const classeCnpjEspecial = temCnpjEspecial ? 'linha-azul-ciano' : '';
                            
                            // Normalizar valores para data attributes: se for null/undefined/vazio, usar 'N/A'
                            const quadraData = (item.quadra && item.quadra.toString().trim() !== '') ? item.quadra : 'N/A';
                            const loteData = (item.lote && item.lote.toString().trim() !== '') ? item.lote : 'N/A';
                            
                            tabelaHTML += `
                                <tr class="linha-cadastro ${classeLinha} ${classeCnpjEspecial}" 
                                    data-index="${index}"
                                    data-id="${item.id || ''}"
                                    data-quarteirao="${item.cara_quarteirao || ''}"
                                    data-quadra="${quadraData}"
                                    data-lote="${loteData}"
                                    data-esta-no-mapa="${estaNoMapa}"
                                    style="cursor: pointer;">
                                    <td style="text-align: center;">${item.multiplo || 'N/A'}</td>
                                    <td>${item.inscricao || 'N/A'}</td>
                                    <td>${item.imob_id || 'N/A'}</td>
                                    <td>${item.cnpj || 'N/A'}</td>
                                    <td>${item.bairro || 'N/A'}</td>
                                    <td>${item.nome_loteamento || 'N/A'}</td>
                                    <td>${item.cara_quarteirao || 'N/A'}</td>
                                    <td>${item.quadra || 'N/A'}</td>
                                    <td>${item.lote || 'N/A'}</td>
                                    <td>${item.logradouro || 'N/A'}</td>
                                    <td>${item.numero || 'N/A'}</td>
                                    <td>${item.zona || 'N/A'}</td>
                                    <td>${item.cat_via || 'N/A'}</td>
                                    <td>${item.area_terreno ? parseFloat(item.area_terreno).toFixed(2) : 'N/A'}</td>
                                    <td>${item.tipo_utilizacao || 'N/A'}</td>
                                    <td>${item.area_construida_a ? parseFloat(item.area_construida_a).toFixed(2) : 'N/A'}</td>
                                    <td>${item.utilizacao_area_a || 'N/A'}</td>
                                </tr>
                            `;
                        });

                        tabelaHTML += `
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-2">
                                <strong>Total de cadastros encontrados: ${response.dados.length}</strong>
                            </div>
                        `;

                        $('#conteudoCadastros').html(tabelaHTML);
                        
                        // Adicionar event listeners nas linhas
                        $('.linha-cadastro').on('click', function() {
                            const $linha = $(this);
                            const estaNoMapa = $linha.data('esta-no-mapa') === true;
                            const idCadastro = $linha.data('id');
                            const quarteirao = $linha.data('quarteirao');
                            // Normalizar quadra e lote: se forem vazios/null, usar "N/A"
                            const quadraRaw = $linha.data('quadra');
                            const quadra = (quadraRaw && quadraRaw.toString().trim() !== '') ? quadraRaw.toString().trim() : 'N/A';
                            const loteRaw = $linha.data('lote');
                            const lote = (loteRaw && loteRaw.toString().trim() !== '') ? loteRaw.toString().trim() : 'N/A';
                            const isVermelho = !estaNoMapa;
                            
                            // Lógica de limpeza inteligente
                            if (isVermelho) {
                                // Linha vermelha selecionada
                                // Só limpa mapa se ele for verde
                                if (desenhoSelecionadoAssociacao.poligono && !desenhoSelecionadoAssociacao.isVermelho) {
                                    limparSelecaoAssociacaoMapa();
                                }
                                // Se mapa também é vermelho, não limpar (permite associação)
                            } else {
                                // Linha verde selecionada
                                // Sempre limpa seleção do mapa (se houver)
                                if (desenhoSelecionadoAssociacao.poligono) {
                                    limparSelecaoAssociacaoMapa();
                                }
                            }
                            
                            // Limpar seleção anterior da tabela
                            $('.linha-cadastro').removeClass('linha-selecionada');
                            
                            if (estaNoMapa) {
                                // Linha verde - destacar linha e desenho no mapa
                                $linha.addClass('linha-selecionada');
                                
                                // Destacar desenho no mapa
                                const desenho = encontrarDesenhoNoMapa(quarteirao, lote);
                                if (desenho && desenho.poligono) {
                                    destacarDesenhoAssociacao(desenho.poligono, desenho.marcador, false);
                                }
                                
                                linhaSelecionadaAssociacao = {
                                    elemento: $linha[0],
                                    dados: { id: idCadastro, quarteirao, quadra, lote },
                                    isVermelho: false
                                };
                            } else {
                                // Linha vermelha - apenas destacar linha
                                $linha.addClass('linha-selecionada');
                                
                                linhaSelecionadaAssociacao = {
                                    elemento: $linha[0],
                                    dados: { id: idCadastro, quarteirao, quadra, lote },
                                    isVermelho: true
                                };
                            }
                            
                            verificarBotaoAssociar();
                            verificarBotaoDesassociar();
                        });
                    } else {
                        $('#conteudoCadastros').html(`
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                Nenhum cadastro encontrado para o quarteirão ${numeroQuarteirao}.
                            </div>
                        `);
                    }
                },
                error: function(xhr, status, error) {
                    $('#loadingCadastros').hide();
                    console.error('Erro ao carregar cadastros:', error);
                    $('#conteudoCadastros').html(`
                        <div class="alert alert-danger">
                            <i class="fas fa-times-circle"></i>
                            Erro ao carregar cadastros. Por favor, tente novamente.
                            <br><small>Detalhes: ${error}</small>
                        </div>
                    `);
                }
            });
        }

        carregarCadastrosQuarteirao(<?php echo json_encode($quarteirao); ?>);

        // Define proj4 para conversão de coordenadas
        proj4.defs("EPSG:32723", "+proj=utm +zone=23 +south +datum=WGS84 +units=m +no_defs");

        // Dados dos parâmetros recebidos
        const parametros = {
            id: <?php echo json_encode($id); ?>,
            quarteirao: <?php echo json_encode($quarteirao); ?>,
            loteamento: <?php echo json_encode($loteamento); ?>,
            id_desenho: <?php echo json_encode($id_desenho); ?>
        };

        // Desenhos para MAPA ANTERIOR (esquerda)
        const poligonoLoteAnterior = <?php echo $poligonoLoteAnteriorJSON; ?>;
        const marcadorQuadraAnterior = <?php echo $marcadorQuadraAnteriorJSON; ?>;

        // Desenhos para MAPA POSTERIOR (direita)
        const poligonoLotePosterior = <?php echo $poligonoLotePosteriorJSON; ?>;
        const marcadorQuadraPosterior = <?php echo $marcadorQuadraPosteriorJSON; ?>;

        // Coordenadas iniciais
        const coordsInitial = {
            lat: <?php echo $lat; ?>,
            lng: <?php echo $lng; ?>
        };

        // Variáveis para armazenar as instâncias dos mapas
        let map1Instance = null;
        let map2Instance = null;

        // Função para inicializar os mapas
        async function initMaps() {
            try {
                const {
                    Map
                } = await google.maps.importLibrary("maps");
                const {
                    AdvancedMarkerElement
                } = await google.maps.importLibrary("marker");
                // Carregar biblioteca de geometria para verificação de pontos dentro de polígonos
                await google.maps.importLibrary("geometry");

                map1Instance = new Map(document.getElementById('map1'), {
                    center: coordsInitial,
                    zoom: 18,
                    mapTypeId: 'roadmap',
                    mapId: '7b2f242ba6401996',
                    mapTypeControl: false,
                    fullscreenControl: false,
                    streetViewControl: true,
                    zoomControl: true
                });

                map2Instance = new Map(document.getElementById('map2'), {
                    center: coordsInitial,
                    zoom: 18,
                    mapTypeId: 'roadmap',
                    mapId: '7b2f242ba6401996',
                    mapTypeControl: false,
                    fullscreenControl: false,
                    streetViewControl: true,
                    zoomControl: true
                });

                sincronizarMapas(map1Instance, map2Instance);

                // Adicionar ortofoto nos mapas (inicialmente visível)
                ortofotoLayer1 = inserirOrtofoto(map1Instance, <?php echo json_encode($quadricula); ?>);
                ortofotoLayer2 = inserirOrtofoto(map2Instance, <?php echo json_encode($quadricula); ?>);

                desenharPoligonosAnterior(map1Instance);
                desenharPoligonosPosterior(map2Instance);
                await desenharMarcadoresAnterior(map1Instance);
                await desenharMarcadoresPosterior(map2Instance);

            } catch (error) {
                console.error('Erro ao inicializar mapas:', error);
            }
        }

        // Função para inserir ortofoto no mapa
        function inserirOrtofoto(mapa, quadricula) {
            // Pasta das fotos
            var url_ortofoto = `quadriculas/${quadricula}/google_tiles`;

            var ortofotoLayer = new google.maps.ImageMapType({
                getTileUrl: (coord, zoom) => {
                    var proj = mapa.getProjection();
                    var tileSize = 256 / Math.pow(2, zoom);

                    var tileBounds = new google.maps.LatLngBounds(
                        proj.fromPointToLatLng(new google.maps.Point(coord.x * tileSize, (coord.y + 1) * tileSize)),
                        proj.fromPointToLatLng(new google.maps.Point((coord.x + 1) * tileSize, coord.y * tileSize))
                    );

                    var invertedY = Math.pow(2, zoom) - coord.y - 1;

                    var tileUrl = `${url_ortofoto}/${zoom}/${coord.x}/${invertedY}.png`;

                    return tileUrl;
                },
                tileSize: new google.maps.Size(256, 256),
                maxZoom: 30,
                minZoom: 0,
                name: 'Ortofoto'
            });

            mapa.overlayMapTypes.push(ortofotoLayer);
            
            // Retornar a camada para poder removê-la depois
            return ortofotoLayer;
        }

        // Função para remover ortofoto do mapa
        function removerOrtofoto(mapa, layer) {
            if (layer && mapa.overlayMapTypes) {
                const overlayArray = mapa.overlayMapTypes.getArray();
                const index = overlayArray.indexOf(layer);
                if (index > -1) {
                    mapa.overlayMapTypes.removeAt(index);
                }
            }
        }

        // Função para alternar visibilidade da ortofoto
        function alternarOrtofoto(mostrar) {
            if (mostrar) {
                // Adicionar ortofoto se ainda não foi adicionada
                if (!ortofotoLayer1 && map1Instance) {
                    ortofotoLayer1 = inserirOrtofoto(map1Instance, <?php echo json_encode($quadricula); ?>);
                }
                if (!ortofotoLayer2 && map2Instance) {
                    ortofotoLayer2 = inserirOrtofoto(map2Instance, <?php echo json_encode($quadricula); ?>);
                }
            } else {
                // Remover ortofoto
                if (ortofotoLayer1 && map1Instance) {
                    removerOrtofoto(map1Instance, ortofotoLayer1);
                    ortofotoLayer1 = null;
                }
                if (ortofotoLayer2 && map2Instance) {
                    removerOrtofoto(map2Instance, ortofotoLayer2);
                    ortofotoLayer2 = null;
                }
            }
        }

        // Função para sincronizar movimento entre os mapas
        function sincronizarMapas(mapa1, mapa2) {
            let atualizandoMapa1 = false;
            let atualizandoMapa2 = false;

            // Quando o mapa 1 se move, atualiza o mapa 2
            mapa1.addListener('center_changed', function() {
                if (!atualizandoMapa1) {
                    atualizandoMapa2 = true;
                    mapa2.setCenter(mapa1.getCenter());
                    atualizandoMapa2 = false;
                }
            });

            mapa1.addListener('zoom_changed', function() {
                if (!atualizandoMapa1) {
                    atualizandoMapa2 = true;
                    mapa2.setZoom(mapa1.getZoom());
                    atualizandoMapa2 = false;
                }
            });

            // Quando o mapa 2 se move, atualiza o mapa 1
            mapa2.addListener('center_changed', function() {
                if (!atualizandoMapa2) {
                    atualizandoMapa1 = true;
                    mapa1.setCenter(mapa2.getCenter());
                    atualizandoMapa1 = false;
                }
            });

            mapa2.addListener('zoom_changed', function() {
                if (!atualizandoMapa2) {
                    atualizandoMapa1 = true;
                    mapa1.setZoom(mapa2.getZoom());
                    atualizandoMapa1 = false;
                }
            });
        }

        // ============================================================================
        // FUNÇÕES PARA DESENHAR OS ELEMENTOS NOS MAPAS
        // ============================================================================

        // Desenhar polígonos no MAPA ANTERIOR (esquerda) - APENAS VISUALIZAÇÃO
        function desenharPoligonosAnterior(mapa) {
            poligonoLoteAnterior.forEach(desenho => {
                try {
                    const coordenadas = JSON.parse(desenho.coordenadas);

                    // Determinar cor: LIMA se tem rótulo (lote), VERMELHO se não tem
                    const temRotulo = desenho.lote && desenho.lote.toString().trim() !== '';
                    const cor = temRotulo ? '#00FF00' : '#FF0000';

                    const polygon = new google.maps.Polygon({
                        paths: coordenadas,
                        strokeColor: cor,
                        strokeOpacity: 0.8,
                        strokeWeight: 2,
                        fillColor: cor,
                        fillOpacity: 0.35,
                        clickable: true,
                        editable: false, // Inicialmente não editável
                        map: mapa
                    });

                    // Armazenar dados no polígono
                    polygon.idDesenho = desenho.id;
                    polygon.lote = desenho.lote;
                    polygon.corOriginal = cor;
                    polygon.desenhoCompleto = desenho;
                    polygon.editableOriginal = false; // Guardar estado original
                    
                    // Armazenar coordenadas originais para restaurar depois
                    polygon.coordenadasOriginais = JSON.parse(JSON.stringify(coordenadas)); // Deep copy

                    // Adicionar ao array
                    poligonosAnteriorArray.push(polygon);

                    // SEMPRE mostra ID no console (independente de modo)
                    polygon.addListener('click', function(e) {
                        e.stop();
                        console.log('ID:', desenho.id);
                    });

                } catch (error) {
                    console.error('Erro ao desenhar polígono ID', desenho.id, error);
                }
            });
        }

        // Arrays para armazenar objetos do mapa posterior (para destaque)
        let poligonosPosteriorArray = [];
        let marcadoresPosteriorArray = [];
        
        // Arrays para armazenar objetos do mapa anterior (para edição de vértices)
        let poligonosAnteriorArray = [];
        let marcadoresAnteriorArray = [];

        // Função auxiliar para adicionar novo polígono aos arrays e configurar event listeners
        function adicionarPoligonoAoArray(polygon, desenhoCompleto) {
            // Armazenar dados no polígono
            polygon.idDesenho = desenhoCompleto.id;
            polygon.lote = desenhoCompleto.lote;
            polygon.corOriginal = '#FF0000'; // Vermelho por padrão (sem lote)
            polygon.desenhoCompleto = desenhoCompleto;

            // Adicionar ao array
            poligonosPosteriorArray.push(polygon);

            // Adicionar evento de clique unificado
            polygon.addListener('click', function(e) {
                if (modoAtivo) {
                    // Se já está destacado e tem sistema de divisão ativo
                    if (objetoDestacado.poligono === polygon) {
                        // Verificar se o ponto está na aresta ou dentro do polígono
                        const estaNaAresta = pontoEstaNaAresta(e.latLng, polygon);
                        let pontoParaFixar;
                        
                        if (estaNaAresta) {
                            // Se está na aresta, usar o ponto projetado na aresta
                            pontoParaFixar = encontrarPontoProximoNaAresta(e.latLng, polygon);
                        } else {
                            // Se está dentro do polígono, usar o ponto exato
                            // Mas só permite se já tiver pelo menos um ponto na aresta
                            if (pontosDivisao.length === 0) {
                                // Primeiro ponto deve estar na aresta
                                pontoParaFixar = encontrarPontoProximoNaAresta(e.latLng, polygon);
                            } else {
                                // Pontos intermediários podem ser dentro do polígono
                                pontoParaFixar = e.latLng;
                            }
                        }
                        
                        fixarPontoDivisao(pontoParaFixar, polygon);
                    } else {
                        // Primeiro clique - destacar o polígono
                        e.stop();
                        destacarDesenho(desenhoCompleto.id, desenhoCompleto.lote);
                    }
                } else {
                    // Sem modo ativo - modo de associação
                    e.stop();
                    // Encontrar marcador correspondente (por lote ou por coordenada)
                    let marcador = null;
                    if (desenhoCompleto.lote && desenhoCompleto.lote.toString().trim() !== '') {
                        marcador = marcadoresPosteriorArray.find(m => m.lote === desenhoCompleto.lote);
                    } else {
                        // Se não tem lote, tentar encontrar por coordenada
                        marcador = encontrarMarcadorPorPoligono(polygon);
                    }
                    const isVermelho = !desenhoCompleto.lote || desenhoCompleto.lote.toString().trim() === '';
                    destacarDesenhoAssociacao(polygon, marcador, isVermelho);
                }
            });
        }

        // Função auxiliar para adicionar novo marcador aos arrays e configurar event listeners
        function adicionarMarcadorAoArray(marker, elementoHTML, desenhoCompleto) {
            // Armazenar dados no marcador
            marker.idDesenho = desenhoCompleto.id;
            marker.lote = desenhoCompleto.lote;
            marker.corOriginal = '#FF0000'; // Vermelho por padrão
            marker.corTextoOriginal = 'white';
            marker.elementoHTML = elementoHTML;
            marker.desenhoCompleto = desenhoCompleto;

            // Adicionar ao array
            marcadoresPosteriorArray.push(marker);

            // Event listener de clique
            elementoHTML.addEventListener('click', function(e) {
                e.stopPropagation();
                if (modoAtivo) {
                    destacarDesenho(desenhoCompleto.id, desenhoCompleto.lote);
                } else {
                    // Sem modo ativo - modo de associação
                    // Encontrar polígono correspondente (por lote ou por coordenada)
                    let poligono = null;
                    if (desenhoCompleto.lote && desenhoCompleto.lote.toString().trim() !== '') {
                        poligono = poligonosPosteriorArray.find(p => p.lote === desenhoCompleto.lote);
                    } else {
                        // Se não tem lote, tentar encontrar por coordenada
                        poligono = encontrarPoligonoPorCoordenada(marker);
                    }
                    const isVermelho = !desenhoCompleto.lote || desenhoCompleto.lote.toString().trim() === '';
                    destacarDesenhoAssociacao(poligono, marker, isVermelho);
                }
            });
        }

        // Desenhar polígonos no MAPA POSTERIOR (direita) - EDITÁVEL
        function desenharPoligonosPosterior(mapa) {
            poligonoLotePosterior.forEach(desenho => {

                try {
                    const coordenadas = JSON.parse(desenho.coordenadas);

                    // Determinar cor: LIMA se tem rótulo (lote), VERMELHO se não tem
                    const temRotulo = desenho.lote && desenho.lote.toString().trim() !== '';
                    const cor = temRotulo ? '#00FF00' : '#FF0000';

                    const polygon = new google.maps.Polygon({
                        paths: coordenadas,
                        strokeColor: cor,
                        strokeOpacity: 0.8,
                        strokeWeight: 2,
                        fillColor: cor,
                        fillOpacity: 0.35,
                        clickable: true,
                        map: mapa
                    });

                    // Armazenar dados no polígono
                    polygon.idDesenho = desenho.id;
                    polygon.lote = desenho.lote;
                    polygon.corOriginal = cor;
                    polygon.desenhoCompleto = desenho;

                    // Adicionar ao array
                    poligonosPosteriorArray.push(polygon);

                    // Adicionar evento de clique unificado
                    polygon.addListener('click', function(e) {
                        if (modoAtivo) {
                            // Se já está destacado e tem sistema de divisão ativo
                            if (objetoDestacado.poligono === polygon) {
                                // Verificar se o ponto está na aresta ou dentro do polígono
                                const estaNaAresta = pontoEstaNaAresta(e.latLng, polygon);
                                let pontoParaFixar;
                                
                                if (estaNaAresta) {
                                    // Se está na aresta, usar o ponto projetado na aresta
                                    pontoParaFixar = encontrarPontoProximoNaAresta(e.latLng, polygon);
                                } else {
                                    // Se está dentro do polígono, usar o ponto exato
                                    // Mas só permite se já tiver pelo menos um ponto na aresta
                                    if (pontosDivisao.length === 0) {
                                        // Primeiro ponto deve estar na aresta
                                        pontoParaFixar = encontrarPontoProximoNaAresta(e.latLng, polygon);
                                    } else {
                                        // Pontos intermediários podem ser dentro do polígono
                                        pontoParaFixar = e.latLng;
                                    }
                                }
                                
                                fixarPontoDivisao(pontoParaFixar, polygon);
                            } else {
                                // Primeiro clique - destacar o polígono
                                e.stop();
                                destacarDesenho(desenho.id, desenho.lote);
                            }
                        } else {
                            // Sem modo ativo - modo de associação
                            e.stop();
                            // Encontrar marcador correspondente (por lote ou por coordenada)
                            let marcador = null;
                            if (desenho.lote && desenho.lote.toString().trim() !== '') {
                                marcador = marcadoresPosteriorArray.find(m => m.lote === desenho.lote);
                            } else {
                                // Se não tem lote, tentar encontrar por coordenada
                                marcador = encontrarMarcadorPorPoligono(polygon);
                            }
                            const isVermelho = !desenho.lote || desenho.lote.toString().trim() === '';
                            destacarDesenhoAssociacao(polygon, marcador, isVermelho);
                        }
                    });

                } catch (error) {
                    console.error('Erro ao desenhar polígono ID', desenho.id, error);
                }
            });
        }

        // Desenhar marcadores no MAPA ANTERIOR (esquerda) - APENAS VISUALIZAÇÃO
        async function desenharMarcadoresAnterior(mapa) {
            const {
                AdvancedMarkerElement
            } = await google.maps.importLibrary("marker");

            marcadorQuadraAnterior.forEach(desenho => {
                try {
                    const coordenadas = JSON.parse(desenho.coordenadas);
                    if (!coordenadas[0]) return;

                    const lat = coordenadas[0].lat;
                    const lng = coordenadas[0].lng;
                    const numeroMarcador = desenho.lote || '';

                    // Determinar cor: LIMA se tem rótulo (lote), VERMELHO se não tem
                    const temRotulo = numeroMarcador.toString().trim() !== '';
                    const cor = temRotulo ? '#00FF00' : '#FF0000';
                    const corTexto = temRotulo ? 'black' : 'white';

                    const el = document.createElement('div');
                    el.style.padding = '0 5px';
                    el.style.height = '16px';
                    el.style.background = cor;
                    el.style.borderRadius = '10px';
                    el.style.display = 'flex';
                    el.style.alignItems = 'center';
                    el.style.justifyContent = 'center';
                    el.style.color = corTexto;
                    el.style.fontWeight = 'bold';
                    el.style.fontSize = '8px';
                    el.style.border = '2px solid ' + corTexto;
                    el.style.cursor = 'pointer';
                    el.textContent = numeroMarcador;

                    const marker = new AdvancedMarkerElement({
                        position: {
                            lat: parseFloat(lat),
                            lng: parseFloat(lng)
                        },
                        content: el,
                        gmpClickable: true,
                        gmpDraggable: false, // Inicialmente não arrastável
                        map: mapa
                    });

                    // Armazenar dados no marcador
                    marker.idDesenho = desenho.id;
                    marker.lote = desenho.lote;
                    marker.corOriginal = cor;
                    marker.corTextoOriginal = corTexto;
                    marker.elementoHTML = el;
                    marker.desenhoCompleto = desenho;
                    marker.draggableOriginal = false; // Guardar estado original
                    
                    // Armazenar posição original para restaurar depois
                    marker.posicaoOriginal = {
                        lat: parseFloat(lat),
                        lng: parseFloat(lng)
                    };

                    // Adicionar ao array
                    marcadoresAnteriorArray.push(marker);

                    // SEMPRE mostra ID no console (independente de modo)
                    el.addEventListener('click', function(e) {
                        e.stopPropagation();
                        console.log('ID:', desenho.id);
                    });

                } catch (error) {
                    console.error('Erro ao criar marcador ID', desenho.id, error);
                }
            });
        }

        // Desenhar marcadores no MAPA POSTERIOR (direita) - EDITÁVEL
        async function desenharMarcadoresPosterior(mapa) {
            const {
                AdvancedMarkerElement
            } = await google.maps.importLibrary("marker");

            marcadorQuadraPosterior.forEach(desenho => {
                try {
                    const coordenadas = JSON.parse(desenho.coordenadas);
                    if (!coordenadas[0]) return;

                    const lat = coordenadas[0].lat;
                    const lng = coordenadas[0].lng;
                    const numeroMarcador = desenho.lote || '';

                    // Determinar cor: LIMA se tem rótulo (lote), VERMELHO se não tem
                    const temRotulo = numeroMarcador.toString().trim() !== '';
                    const cor = temRotulo ? '#00FF00' : '#FF0000';
                    const corTexto = temRotulo ? 'black' : 'white';

                    const el = document.createElement('div');
                    el.style.padding = '0 5px';
                    el.style.height = '16px';
                    el.style.background = cor;
                    el.style.borderRadius = '10px';
                    el.style.display = 'flex';
                    el.style.alignItems = 'center';
                    el.style.justifyContent = 'center';
                    el.style.color = corTexto;
                    el.style.fontWeight = 'bold';
                    el.style.fontSize = '8px';
                    el.style.border = '2px solid ' + corTexto;
                    el.style.transform = 'translate(0, 10px)';
                    el.style.cursor = 'pointer';
                    el.textContent = numeroMarcador;

                    const marker = new AdvancedMarkerElement({
                        position: {
                            lat: parseFloat(lat),
                            lng: parseFloat(lng)
                        },
                        content: el,
                        gmpClickable: true,
                        map: mapa
                    });

                    // Armazenar dados no marcador
                    marker.idDesenho = desenho.id;
                    marker.lote = desenho.lote;
                    marker.corOriginal = cor;
                    marker.corTextoOriginal = corTexto;
                    marker.elementoHTML = el;
                    marker.desenhoCompleto = desenho;

                    // Adicionar ao array
                    marcadoresPosteriorArray.push(marker);

                    // Event listener de clique
                    el.addEventListener('click', function(e) {
                        e.stopPropagation();
                        if (modoAtivo) {
                            destacarDesenho(desenho.id, desenho.lote);
                        } else {
                            // Sem modo ativo - modo de associação
                            // Encontrar polígono correspondente (por lote ou por coordenada)
                            let poligono = null;
                            if (desenho.lote && desenho.lote.toString().trim() !== '') {
                                poligono = poligonosPosteriorArray.find(p => p.lote === desenho.lote);
                            } else {
                                // Se não tem lote, tentar encontrar por coordenada
                                poligono = encontrarPoligonoPorCoordenada(marker);
                            }
                            const isVermelho = !desenho.lote || desenho.lote.toString().trim() === '';
                            destacarDesenhoAssociacao(poligono, marker, isVermelho);
                        }
                    });

                } catch (error) {
                    console.error('Erro ao criar marcador ID', desenho.id, error);
                }
            });
        }

        // ============================================================================
        // VARIÁVEIS DE CONTROLE
        // ============================================================================
        let modoAtivo = null; // null, 'desdobrar' ou 'unificar'
        let objetoDestacado = {
            poligono: null,
            marcador: null
        };
        
        // Variável para controlar modo de edição de vértices do mapa anterior
        let modoVerticesAtivo = false;

        // ============================================================================
        // FUNÇÕES DE SELEÇÃO PARA ASSOCIAÇÃO
        // ============================================================================

        // Função para converter coordenadas lat/lng para UTM
        function latLngToUtm(lat, lng) {
            try {
                // Converter de WGS84 (EPSG:4326) para UTM Zone 23S (EPSG:32723)
                // Proj4js espera [lng, lat] para EPSG:4326
                const [easting, northing] = proj4("EPSG:4326", "EPSG:32723", [lng, lat]);
                return { easting, northing };
            } catch (error) {
                console.error('Erro ao converter coordenadas para UTM:', error);
                return { easting: 0, northing: 0 };
            }
        }

        // Função para calcular área do polígono em metros quadrados usando coordenadas UTM
        function calcularAreaPoligono(poligono) {
            if (!poligono) return 0;
            
            try {
                const path = poligono.getPath();
                const pontosUtm = [];
                
                // Obter todos os pontos do polígono e converter para UTM
                for (let i = 0; i < path.getLength(); i++) {
                    const ponto = path.getAt(i);
                    const utm = latLngToUtm(ponto.lat(), ponto.lng());
                    pontosUtm.push({ x: utm.easting, y: utm.northing });
                }
                
                // Verificar se tem pelo menos 3 pontos
                if (pontosUtm.length < 3) return 0;
                
                // Calcular área usando fórmula de Shoelace (mais precisa para coordenadas planas UTM)
                let area = 0;
                const n = pontosUtm.length;
                
                for (let i = 0; i < n; i++) {
                    const j = (i + 1) % n; // Próximo índice (fecha o polígono)
                    area += pontosUtm[i].x * pontosUtm[j].y;
                    area -= pontosUtm[j].x * pontosUtm[i].y;
                }
                
                // Área absoluta em metros quadrados
                area = Math.abs(area) / 2;
                
                return area;
            } catch (error) {
                console.error('Erro ao calcular área do polígono:', error);
                return 0;
            }
        }

        // Função para atualizar display da área
        function atualizarDisplayArea(poligono) {
            const areaDisplay = document.getElementById('areaPoligonoDisplay');
            const areaValor = document.getElementById('areaPoligonoValor');
            
            if (!areaDisplay || !areaValor) return;
            
            if (poligono) {
                const area = calcularAreaPoligono(poligono);
                areaValor.textContent = area.toFixed(2);
                areaDisplay.style.display = 'block';
            } else {
                areaDisplay.style.display = 'none';
            }
        }

        // Destacar desenho no modo de associação
        function destacarDesenhoAssociacao(poligono, marcador, isVermelho) {
            console.log(poligono.idDesenho, marcador.idDesenho);
            if (!poligono) return;
            
            // Lógica de limpeza inteligente
            if (isVermelho) {
                // Desenho vermelho selecionado
                // Só limpa tabela se ela for verde
                if (linhaSelecionadaAssociacao.elemento && !linhaSelecionadaAssociacao.isVermelho) {
                    limparSelecaoAssociacaoTabela();
                }
                // Se tabela também é vermelha, não limpar (permite associação)
            } else {
                // Desenho verde selecionado
                // Sempre limpa seleção da tabela (se houver)
                if (linhaSelecionadaAssociacao.elemento) {
                    limparSelecaoAssociacaoTabela();
                }
            }
            
            // Limpar seleção anterior do mapa (se houver outro desenho selecionado)
            if (desenhoSelecionadoAssociacao.poligono && desenhoSelecionadoAssociacao.poligono !== poligono) {
                // Remover listeners do polígono anterior antes de limpar
                const poligonoAnterior = desenhoSelecionadoAssociacao.poligono;
                if (poligonoAnterior.listenerArea) {
                    google.maps.event.removeListener(poligonoAnterior.listenerArea);
                    poligonoAnterior.listenerArea = null;
                }
                limparSelecaoAssociacaoMapa();
            }
            
            // Destacar polígono com amarelo
            poligono.setOptions({
                strokeColor: '#FFFF00',
                fillColor: '#FFFF00',
                strokeWeight: 3
            });
            
            // Destacar marcador se houver
            if (marcador && marcador.elementoHTML) {
                marcador.elementoHTML.style.background = '#FFFF00';
                marcador.elementoHTML.style.color = 'black';
                marcador.elementoHTML.style.border = '2px solid black';
            }
            
            // Armazenar seleção
            desenhoSelecionadoAssociacao = {
                poligono: poligono,
                marcador: marcador,
                isVermelho: isVermelho
            };
            
            // Atualizar display da área do polígono
            atualizarDisplayArea(poligono);
            
            // Adicionar listeners para atualizar área quando o polígono for editado
            // Remover listeners anteriores se existirem
            if (poligono.listenersArea) {
                poligono.listenersArea.forEach(listener => {
                    google.maps.event.removeListener(listener);
                });
            }
            
            // Armazenar listeners em um array
            poligono.listenersArea = [];
            
            // Adicionar listeners para atualizar área quando o path mudar
            const path = poligono.getPath();
            
            poligono.listenersArea.push(path.addListener('set_at', function() {
                if (desenhoSelecionadoAssociacao.poligono === poligono) {
                    atualizarDisplayArea(poligono);
                }
            }));
            
            poligono.listenersArea.push(path.addListener('insert_at', function() {
                if (desenhoSelecionadoAssociacao.poligono === poligono) {
                    atualizarDisplayArea(poligono);
                }
            }));
            
            poligono.listenersArea.push(path.addListener('remove_at', function() {
                if (desenhoSelecionadoAssociacao.poligono === poligono) {
                    atualizarDisplayArea(poligono);
                }
            }));
            
            // Se for verde, destacar linha correspondente na tabela
            if (!isVermelho && poligono.desenhoCompleto) {
                const desenho = poligono.desenhoCompleto;
                const quarteiraoNorm = normalizarQuarteirao(desenho.quarteirao);
                const loteNorm = String(desenho.lote || '').trim();
                
                // Procurar linha correspondente
                $('.linha-cadastro').each(function() {
                    const $linha = $(this);
                    const quarteiraoLinha = normalizarQuarteirao($linha.data('quarteirao'));
                    const loteLinha = String($linha.data('lote') || '').trim();
                    
                    if (quarteiraoLinha === quarteiraoNorm && loteLinha === loteNorm) {
                        $linha.addClass('linha-selecionada');
                        // Normalizar quadra: se for vazio/null, usar "N/A"
                        const quadraLinha = $linha.data('quadra');
                        const quadraNormalizada = (quadraLinha && quadraLinha.toString().trim() !== '') ? quadraLinha.toString().trim() : 'N/A';
                        linhaSelecionadaAssociacao = {
                            elemento: $linha[0],
                            dados: { quarteirao: quarteiraoLinha, quadra: quadraNormalizada, lote: loteLinha },
                            isVermelho: false
                        };
                        return false; // Sair do loop
                    }
                });
            }
            
            verificarBotaoAssociar();
            verificarBotaoDesassociar();
        }

        // Limpar seleção de associação no mapa
        function limparSelecaoAssociacaoMapa() {
            if (desenhoSelecionadoAssociacao.poligono) {
                // Restaurar cor original
                const poligono = desenhoSelecionadoAssociacao.poligono;
                poligono.setOptions({
                    strokeColor: poligono.corOriginal,
                    fillColor: poligono.corOriginal,
                    strokeWeight: 2
                });
                
                // Remover listeners de área se existirem
                if (poligono.listenersArea && Array.isArray(poligono.listenersArea)) {
                    poligono.listenersArea.forEach(listener => {
                        google.maps.event.removeListener(listener);
                    });
                    poligono.listenersArea = null;
                }
            }
            
            if (desenhoSelecionadoAssociacao.marcador && desenhoSelecionadoAssociacao.marcador.elementoHTML) {
                const marcador = desenhoSelecionadoAssociacao.marcador;
                marcador.elementoHTML.style.background = marcador.corOriginal;
                marcador.elementoHTML.style.color = marcador.corTextoOriginal;
                marcador.elementoHTML.style.border = '2px solid ' + marcador.corTextoOriginal;
            }
            
            // Ocultar display da área
            atualizarDisplayArea(null);
            
            desenhoSelecionadoAssociacao = {
                poligono: null,
                marcador: null,
                isVermelho: false
            };
        }

        // Limpar seleção de associação na tabela
        function limparSelecaoAssociacaoTabela() {
            $('.linha-cadastro').removeClass('linha-selecionada');
            linhaSelecionadaAssociacao = {
                elemento: null,
                dados: null,
                isVermelho: false
            };
        }

        // Verificar se deve mostrar botão Associar
        function verificarBotaoAssociar() {
            const temDesenhoVermelho = desenhoSelecionadoAssociacao.poligono && desenhoSelecionadoAssociacao.isVermelho;
            const temLinhaVermelha = linhaSelecionadaAssociacao.elemento && linhaSelecionadaAssociacao.isVermelho;
            
            if (temDesenhoVermelho && temLinhaVermelha) {
                $('#btnAssociar').show();
            } else {
                $('#btnAssociar').hide();
            }
        }

        // Verificar se deve mostrar botão Desassociar
        function verificarBotaoDesassociar() {
            const temDesenhoVerde = desenhoSelecionadoAssociacao.poligono && !desenhoSelecionadoAssociacao.isVermelho;
            const temLinhaVerde = linhaSelecionadaAssociacao.elemento && !linhaSelecionadaAssociacao.isVermelho;
            
            // Mostrar botão se houver desenho verde OU linha verde selecionada
            if (temDesenhoVerde || temLinhaVerde) {
                $('#btnDesassociar').show();
            } else {
                $('#btnDesassociar').hide();
            }
        }

        // Função para associar desenho ao cadastro
        async function associarDesenho() {
            if (!desenhoSelecionadoAssociacao.poligono || !linhaSelecionadaAssociacao.dados) {
                alert('Selecione um desenho vermelho no mapa e uma linha vermelha na tabela');
                return;
            }
            
            // Desabilitar botão e mostrar loading
            const $btnAssociar = $('#btnAssociar');
            const textoOriginal = $btnAssociar.html();
            $btnAssociar.prop('disabled', true);
            $btnAssociar.html('<i class="fas fa-spinner fa-spin"></i> Associando...');
            
            const poligono = desenhoSelecionadoAssociacao.poligono;
            const marcador = desenhoSelecionadoAssociacao.marcador;
            const dadosLinha = linhaSelecionadaAssociacao.dados;
            
            // Normalizar valores: se quadra ou lote forem vazios/null/undefined, usar string "N/A"
            const quadraNormalizada = (dadosLinha.quadra && dadosLinha.quadra.toString().trim() !== '' && dadosLinha.quadra !== 'N/A') 
                ? dadosLinha.quadra.toString().trim() 
                : 'N/A';
            const loteNormalizado = (dadosLinha.lote && dadosLinha.lote.toString().trim() !== '' && dadosLinha.lote !== 'N/A') 
                ? dadosLinha.lote.toString().trim() 
                : 'N/A';
            
            // Preparar dados para envio
            const dadosAssociacao = {
                id_poligono: poligono.idDesenho,
                id_marcador: marcador ? marcador.idDesenho : null,
                id_cadastro: dadosLinha.id,
                quarteirao: dadosLinha.quarteirao,
                quadra: quadraNormalizada,
                lote: loteNormalizado
            };
            
            // Enviar para o servidor
            $.ajax({
                url: 'index_3_novo_associar_desenho.php',
                method: 'POST',
                data: JSON.stringify(dadosAssociacao),
                contentType: 'application/json',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Atualizar cores no mapa
                        poligono.setOptions({
                            strokeColor: '#00FF00',
                            fillColor: '#00FF00',
                            strokeWeight: 2
                        });
                        poligono.corOriginal = '#00FF00';
                        
                        // Atualizar marcador
                        if (marcador && marcador.elementoHTML) {
                            marcador.elementoHTML.style.background = '#00FF00';
                            marcador.elementoHTML.style.color = 'black';
                            marcador.elementoHTML.style.border = '2px solid black';
                            marcador.elementoHTML.textContent = dadosLinha.lote;
                            marcador.corOriginal = '#00FF00';
                            marcador.corTextoOriginal = 'black';
                            marcador.lote = dadosLinha.lote;
                            
                            // Atualizar desenho completo
                            if (marcador.desenhoCompleto) {
                                marcador.desenhoCompleto.lote = dadosLinha.lote;
                                marcador.desenhoCompleto.quarteirao = dadosLinha.quarteirao;
                                marcador.desenhoCompleto.quadra = dadosLinha.quadra;
                            }
                        }
                        
                        // Atualizar polígono completo
                        if (poligono.desenhoCompleto) {
                            poligono.desenhoCompleto.lote = dadosLinha.lote;
                            poligono.desenhoCompleto.quarteirao = dadosLinha.quarteirao;
                            poligono.desenhoCompleto.quadra = dadosLinha.quadra;
                            poligono.lote = dadosLinha.lote;
                        }
                        
                        // Atualizar linha na tabela (mudar de vermelho para verde)
                        if (linhaSelecionadaAssociacao.elemento) {
                            const $linha = $(linhaSelecionadaAssociacao.elemento);
                            $linha.removeClass('linha-vermelha').addClass('linha-verde');
                            $linha.css('background-color', '#d4edda');
                            $linha.data('esta-no-mapa', true);
                        }
                        
                        // Limpar seleções
                        limparSelecaoAssociacaoMapa();
                        limparSelecaoAssociacaoTabela();
                        
                        alert('Desenho associado com sucesso!');
                    } else {
                        alert('Erro ao associar: ' + response.message);
                    }
                    
                    // Reabilitar botão e restaurar texto original
                    $btnAssociar.prop('disabled', false);
                    $btnAssociar.html(textoOriginal);
                },
                error: function(xhr, status, error) {
                    console.error('Erro no AJAX:', error);
                    alert('Erro ao comunicar com o servidor');
                    
                    // Reabilitar botão e restaurar texto original
                    $btnAssociar.prop('disabled', false);
                    $btnAssociar.html(textoOriginal);
                }
            });
        }

        // Função para desassociar desenho
        async function desassociarDesenho() {
            // Verificar se há um desenho verde selecionado
            if (!desenhoSelecionadoAssociacao.poligono || desenhoSelecionadoAssociacao.isVermelho) {
                alert('Selecione um desenho verde (associado) para desassociar');
                return;
            }
            
            // Confirmar ação
            if (!confirm('Tem certeza que deseja desassociar este desenho? O polígono e marcador ficarão vermelhos e perderão o lote e a quadra (o quarteirão será mantido).')) {
                return;
            }
            
            // Desabilitar botão e mostrar loading
            const $btnDesassociar = $('#btnDesassociar');
            const textoOriginal = $btnDesassociar.html();
            $btnDesassociar.prop('disabled', true);
            $btnDesassociar.html('<i class="fas fa-spinner fa-spin"></i> Desassociando...');
            
            const poligono = desenhoSelecionadoAssociacao.poligono;
            const marcador = desenhoSelecionadoAssociacao.marcador;
            
            // Preparar dados para envio
            const dadosDesassociacao = {
                id_poligono: poligono.idDesenho,
                id_marcador: marcador ? marcador.idDesenho : null
            };
            
            // Enviar para o servidor
            $.ajax({
                url: 'index_3_novo_desassociar_desenho.php',
                method: 'POST',
                data: JSON.stringify(dadosDesassociacao),
                contentType: 'application/json',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Atualizar cores no mapa para vermelho
                        poligono.setOptions({
                            strokeColor: '#FF0000',
                            fillColor: '#FF0000',
                            strokeWeight: 2
                        });
                        poligono.corOriginal = '#FF0000';
                        
                        // Atualizar marcador para vermelho e remover rótulo
                        if (marcador && marcador.elementoHTML) {
                            marcador.elementoHTML.style.background = '#FF0000';
                            marcador.elementoHTML.style.color = 'white';
                            marcador.elementoHTML.style.border = '2px solid white';
                            marcador.elementoHTML.textContent = ''; // Remover rótulo
                            marcador.corOriginal = '#FF0000';
                            marcador.corTextoOriginal = 'white';
                            marcador.lote = null;
                            
                            // Atualizar desenho completo - manter quarteirao, limpar apenas quadra e lote
                            if (marcador.desenhoCompleto) {
                                marcador.desenhoCompleto.lote = null;
                                marcador.desenhoCompleto.quadra = null;
                                // quarteirao é mantido
                            }
                        }
                        
                        // Atualizar polígono completo - manter quarteirao, limpar apenas quadra e lote
                        if (poligono.desenhoCompleto) {
                            poligono.desenhoCompleto.lote = null;
                            poligono.desenhoCompleto.quadra = null;
                            // quarteirao é mantido
                            poligono.lote = null;
                        }
                        
                        // Atualizar linha na tabela (mudar de verde para vermelho)
                        if (linhaSelecionadaAssociacao.elemento) {
                            const $linha = $(linhaSelecionadaAssociacao.elemento);
                            $linha.removeClass('linha-verde').addClass('linha-vermelha');
                            $linha.css('background-color', '#f8d7da');
                            $linha.data('esta-no-mapa', false);
                        }
                        
                        // Limpar seleções
                        limparSelecaoAssociacaoMapa();
                        limparSelecaoAssociacaoTabela();
                        
                        alert('Desenho desassociado com sucesso!');
                    } else {
                        alert('Erro ao desassociar: ' + response.message);
                    }
                    
                    // Reabilitar botão e restaurar texto original
                    $btnDesassociar.prop('disabled', false);
                    $btnDesassociar.html(textoOriginal);
                    
                    // Atualizar visibilidade dos botões
                    verificarBotaoAssociar();
                    verificarBotaoDesassociar();
                },
                error: function(xhr, status, error) {
                    console.error('Erro no AJAX:', error);
                    alert('Erro ao comunicar com o servidor');
                    
                    // Reabilitar botão e restaurar texto original
                    $btnDesassociar.prop('disabled', false);
                    $btnDesassociar.html(textoOriginal);
                }
            });
        }

        let pontosDivisao = []; // Array com os 2 pontos de divisão
        let marcadoresDivisao = []; // Marcadores das bolinhas vermelhas
        let linhasDivisao = []; // Linhas que mostram a divisão
        let rotulosArea = []; // Rótulos que mostram as áreas

        // Variáveis para unificação
        let poligonosSelecionados = []; // Array de polígonos selecionados para unir
        let marcadoresSelecionados = []; // Array de marcadores dos polígonos selecionados

        // ============================================================================
        // FUNÇÕES DE DESTAQUE
        // ============================================================================

        // Verificar se um ponto está dentro de um polígono
        function pontoEstaDentroDoPoligono(ponto, poligono) {
            try {
                return google.maps.geometry.poly.containsLocation(ponto, poligono);
            } catch (error) {
                console.error('Erro ao verificar se ponto está dentro do polígono:', error);
                return false;
            }
        }

        // Obter posição do marcador como LatLng
        function obterPosicaoMarcador(marcador) {
            try {
                if (marcador.position) {
                    // AdvancedMarkerElement.position retorna um LatLng
                    if (typeof marcador.position.lat === 'function') {
                        return marcador.position;
                    } else if (marcador.position.lat !== undefined) {
                        // Se for objeto {lat, lng}
                        return new google.maps.LatLng(
                            marcador.position.lat,
                            marcador.position.lng
                        );
                    }
                }
                
                // Fallback: tentar obter do desenho completo
                const desenho = marcador.desenhoCompleto;
                if (desenho && desenho.coordenadas) {
                    const coords = typeof desenho.coordenadas === 'string' 
                        ? JSON.parse(desenho.coordenadas) 
                        : desenho.coordenadas;
                    if (coords && coords[0]) {
                        return new google.maps.LatLng(
                            parseFloat(coords[0].lat),
                            parseFloat(coords[0].lng)
                        );
                    }
                }
            } catch (error) {
                console.error('Erro ao obter posição do marcador:', error);
            }
            return null;
        }

        // Encontrar polígono que contém o marcador (quando lote é null)
        function encontrarPoligonoPorCoordenada(marcador) {
            try {
                const posicaoMarcador = obterPosicaoMarcador(marcador);
                if (!posicaoMarcador) {
                    return null;
                }

                // Procurar em todos os polígonos
                for (let poligono of poligonosPosteriorArray) {
                    if (pontoEstaDentroDoPoligono(posicaoMarcador, poligono)) {
                        return poligono;
                    }
                }
            } catch (error) {
                console.error('Erro ao encontrar polígono por coordenada:', error);
            }
            return null;
        }

        // Encontrar marcador que está dentro do polígono (quando lote é null)
        function encontrarMarcadorPorPoligono(poligono) {
            try {
                // Procurar em todos os marcadores
                for (let marcador of marcadoresPosteriorArray) {
                    const posicaoMarcador = obterPosicaoMarcador(marcador);
                    if (posicaoMarcador && pontoEstaDentroDoPoligono(posicaoMarcador, poligono)) {
                        return marcador;
                    }
                }
            } catch (error) {
                console.error('Erro ao encontrar marcador por polígono:', error);
            }
            return null;
        }

        // Destacar desenho por ID e lote (destaca polígono E marcador)
        function destacarDesenho(idDesenho, lote) {
            // Se está em modo UNIFICAR, permite múltiplas seleções
            if (modoAtivo === 'unificar') {
                selecionarParaUnificar(idDesenho, lote);
                return;
            }

            // Se está em modo DESDOBRAR, seleciona apenas um
            removerDestaque();

            let poligono = null;
            let marcador = null;

            // Se lote é null, usar lógica geográfica
            if (!lote || lote === null || lote === '') {
                // Tentar encontrar pelo ID primeiro
                poligono = poligonosPosteriorArray.find(p => p.idDesenho === idDesenho);
                marcador = marcadoresPosteriorArray.find(m => m.idDesenho === idDesenho);

                // Se encontrou marcador mas não polígono, buscar polígono pela coordenada
                if (marcador && !poligono) {
                    poligono = encontrarPoligonoPorCoordenada(marcador);
                }

                // Se encontrou polígono mas não marcador, buscar marcador pela coordenada
                if (poligono && !marcador) {
                    marcador = encontrarMarcadorPorPoligono(poligono);
                }
            } else {
                // Lógica original: buscar pelo lote
                poligono = poligonosPosteriorArray.find(p => p.lote === lote);
                marcador = marcadoresPosteriorArray.find(m => m.lote === lote);
            }

            // Destacar polígono
            if (poligono) {
                poligono.setOptions({
                    strokeColor: '#FFFF00',
                    fillColor: '#FFFF00',
                    strokeWeight: 3
                });
                objetoDestacado.poligono = poligono;

                // Se está em modo desdobrar, iniciar sistema de divisão
                if (modoAtivo === 'desdobrar') {
                    iniciarSistemaDivisao(poligono);
                }
            }

            // Destacar marcador
            if (marcador) {
                marcador.elementoHTML.style.background = '#FFFF00';
                marcador.elementoHTML.style.color = 'black';
                marcador.elementoHTML.style.border = '2px solid black';
                objetoDestacado.marcador = marcador;
            }
        }

        // Selecionar polígono para unificar (múltiplas seleções)
        function selecionarParaUnificar(idDesenho, lote) {
            let poligono = null;
            let marcador = null;

            // Se lote é null, usar lógica geográfica
            if (!lote || lote === null || lote === '') {
                // Tentar encontrar pelo ID primeiro
                poligono = poligonosPosteriorArray.find(p => p.idDesenho === idDesenho);
                marcador = marcadoresPosteriorArray.find(m => m.idDesenho === idDesenho);

                // Se encontrou marcador mas não polígono, buscar polígono pela coordenada
                if (marcador && !poligono) {
                    poligono = encontrarPoligonoPorCoordenada(marcador);
                }

                // Se encontrou polígono mas não marcador, buscar marcador pela coordenada
                if (poligono && !marcador) {
                    marcador = encontrarMarcadorPorPoligono(poligono);
                }
            } else {
                // Lógica original: buscar pelo lote
                poligono = poligonosPosteriorArray.find(p => p.lote === lote);
                marcador = marcadoresPosteriorArray.find(m => m.lote === lote);
            }

            // Verificar se já está selecionado - verificar tanto polígono quanto marcador separadamente
            const poligonoJaSelecionado = poligono ? poligonosSelecionados.find(p => p.idDesenho === poligono.idDesenho) : null;
            const marcadorJaSelecionado = marcador ? marcadoresSelecionados.find(m => m.idDesenho === marcador.idDesenho) : null;
            const jaSelecionado = poligonoJaSelecionado || marcadorJaSelecionado;

            if (jaSelecionado) {
                // Desselecionar - remover polígono e marcador pelos seus próprios IDs
                if (poligono) {
                    poligonosSelecionados = poligonosSelecionados.filter(p => p.idDesenho !== poligono.idDesenho);
                    poligono.setOptions({
                        strokeColor: poligono.corOriginal,
                        fillColor: poligono.corOriginal,
                        strokeWeight: 2,
                        zIndex: 3
                    });
                }

                if (marcador) {
                    marcadoresSelecionados = marcadoresSelecionados.filter(m => m.idDesenho !== marcador.idDesenho);
                    marcador.elementoHTML.style.background = marcador.corOriginal;
                    marcador.elementoHTML.style.color = marcador.corTextoOriginal;
                    marcador.elementoHTML.style.border = '2px solid ' + marcador.corTextoOriginal;
                }

            } else {
                // Selecionar
                if (poligono) {
                    // Verificar se já não está no array antes de adicionar
                    if (!poligonosSelecionados.find(p => p.idDesenho === poligono.idDesenho)) {
                        poligono.setOptions({
                            strokeColor: '#FFFF00',
                            fillColor: '#FFFF00',
                            strokeWeight: 3,
                            zIndex: 3
                        });
                        poligonosSelecionados.push(poligono);
                    }
                }

                if (marcador) {
                    // Verificar se já não está no array antes de adicionar
                    if (!marcadoresSelecionados.find(m => m.idDesenho === marcador.idDesenho)) {
                        marcador.elementoHTML.style.background = '#FFFF00';
                        marcador.elementoHTML.style.color = 'black';
                        marcador.elementoHTML.style.border = '2px solid black';
                        marcadoresSelecionados.push(marcador);
                    }
                }

            }

            // Mostrar/ocultar botão "Unir" baseado na quantidade selecionada
            if (poligonosSelecionados.length >= 2) {
                document.getElementById('btnUnir').style.display = 'inline-block';
            } else {
                document.getElementById('btnUnir').style.display = 'none';
            }
        }

        // Remover todos os destaques
        function removerDestaque() {
            // Limpar sistema de divisão
            limparSistemaDivisao();

            // Restaurar polígono
            if (objetoDestacado.poligono) {
                objetoDestacado.poligono.setOptions({
                    strokeColor: objetoDestacado.poligono.corOriginal,
                    fillColor: objetoDestacado.poligono.corOriginal,
                    strokeWeight: 2
                });
                objetoDestacado.poligono = null;
            }

            // Restaurar marcador
            if (objetoDestacado.marcador) {
                objetoDestacado.marcador.elementoHTML.style.background = objetoDestacado.marcador.corOriginal;
                objetoDestacado.marcador.elementoHTML.style.color = objetoDestacado.marcador.corTextoOriginal;
                objetoDestacado.marcador.elementoHTML.style.border = '2px solid ' + objetoDestacado.marcador.corTextoOriginal;
                objetoDestacado.marcador = null;
            }
        }

        // ============================================================================
        // SISTEMA DE DIVISÃO DE POLÍGONOS
        // ============================================================================

        // Iniciar sistema de divisão (bolinhas nas arestas)
        async function iniciarSistemaDivisao(poligono) {
            limparSistemaDivisao();

            // Mostrar botões de dividir e cancelar
            document.getElementById('btnUnificar').style.display = 'none';
            document.getElementById('btnDividir').style.display = 'inline-block';
            document.getElementById('btnCancelar').style.display = 'inline-block';

            // Tornar o polígono editável para capturar eventos melhor
            poligono.setOptions({
                editable: false,
                draggable: false
            });

            // Importar biblioteca de marcadores
            const {
                AdvancedMarkerElement
            } = await google.maps.importLibrary("marker");

            // Criar marcador móvel (bolinha vermelha que segue o mouse)
            const marcadorMovel = criarBolinhaDivisao();
            const markerMovel = new AdvancedMarkerElement({
                position: poligono.getPath().getAt(0),
                content: marcadorMovel,
                map: map2Instance,
                gmpDraggable: false,
                zIndex: 9999
            });

            // Listener de movimento do mouse no polígono
            const listenerMouseMove = poligono.addListener('mousemove', function(e) {
                if (markerMovel.map) {
                    // Se não tem pontos ainda, ou se o último ponto está na aresta, mostrar na aresta
                    if (pontosDivisao.length === 0) {
                        const pontoProximo = encontrarPontoProximoNaAresta(e.latLng, poligono);
                        markerMovel.position = pontoProximo;
                    } else {
                        // Se já tem pontos, verificar se está próximo da aresta
                        const estaNaAresta = pontoEstaNaAresta(e.latLng, poligono);
                        if (estaNaAresta) {
                            const pontoProximo = encontrarPontoProximoNaAresta(e.latLng, poligono);
                            markerMovel.position = pontoProximo;
                        } else {
                            // Se não está na aresta, usar o ponto exato (dentro do polígono)
                            markerMovel.position = e.latLng;
                        }
                    }
                }
            });

            // Armazenar referências
            poligono.listeners = {
                mouseMove: listenerMouseMove
            };
            poligono.marcadorMovel = markerMovel;
        }

        // Fixar ponto de divisão (chamado pelo listener do polígono)
        async function fixarPontoDivisao(ponto, poligono) {
            const {
                AdvancedMarkerElement
            } = await google.maps.importLibrary("marker");

            // Criar marcador fixo (bolinha fixa)
            const marcadorFixo = criarBolinhaDivisao();
            marcadorFixo.style.boxShadow = '0 0 8px rgba(255,0,0,0.8)';
            marcadorFixo.style.width = '14px';
            marcadorFixo.style.height = '14px';

            const markerFixo = new AdvancedMarkerElement({
                position: ponto,
                content: marcadorFixo,
                map: map2Instance,
                gmpDraggable: true,
                zIndex: 10000
            });

            // Verificar se o ponto está na aresta
            const estaNaAresta = pontoEstaNaAresta(ponto, poligono);
            
            // Armazenar ponto com informação se está na aresta
            pontosDivisao.push({
                posicao: ponto,
                marker: markerFixo,
                naAresta: estaNaAresta
            });

            marcadoresDivisao.push(markerFixo);

            // Verificar se pode finalizar (primeiro e último pontos nas arestas)
            const primeiroNaAresta = pontosDivisao.length > 0 && pontosDivisao[0].naAresta;
            const ultimoNaAresta = estaNaAresta;
            const podeFinalizar = primeiroNaAresta && ultimoNaAresta && pontosDivisao.length >= 2;

            // Se pode finalizar, desenhar linha de divisão e áreas
            if (podeFinalizar) {
                // Remove bolinha móvel
                if (poligono.marcadorMovel) {
                    poligono.marcadorMovel.map = null;
                }

                desenharLinhaDivisao();
                calcularEMostrarAreas(poligono);

                // Adicionar listeners de drag nas bolinhas fixas
                pontosDivisao.forEach((pontoItem, idx) => {
                    pontoItem.marker.addListener('drag', function(e) {
                        // Se é o primeiro ou último ponto, deve ficar na aresta
                        const deveEstarNaAresta = (idx === 0 || idx === pontosDivisao.length - 1);
                        let novoPonto;
                        
                        if (deveEstarNaAresta) {
                            novoPonto = encontrarPontoProximoNaAresta(e.latLng, poligono);
                            pontoItem.naAresta = true;
                        } else {
                            // Pontos intermediários podem ser movidos livremente dentro do polígono
                            novoPonto = e.latLng;
                            pontoItem.naAresta = pontoEstaNaAresta(novoPonto, poligono);
                        }
                        
                        pontoItem.marker.position = novoPonto;
                        pontoItem.posicao = novoPonto;
                        atualizarLinhaDivisao();
                        calcularEMostrarAreas(poligono);
                    });
                });
            } else {
                // Se ainda não pode finalizar, desenhar linha parcial do caminho
                desenharLinhaDivisao();
            }
        }

        // Criar elemento HTML da bolinha vermelha
        function criarBolinhaDivisao() {
            const el = document.createElement('div');
            el.style.width = '12px';
            el.style.height = '12px';
            el.style.background = '#FF0000';
            el.style.borderRadius = '50%';
            el.style.border = '2px solid white';
            el.style.cursor = 'pointer';
            el.style.boxShadow = '0 0 4px rgba(0,0,0,0.5)';
            return el;
        }

        // Verificar se um ponto está próximo de uma aresta (dentro de uma tolerância)
        function pontoEstaNaAresta(latLng, poligono, toleranciaMetros = 5) {
            const path = poligono.getPath();
            let menorDistancia = Infinity;

            // Percorrer todas as arestas para encontrar a distância mínima
            for (let i = 0; i < path.getLength(); i++) {
                const p1 = path.getAt(i);
                const p2 = path.getAt((i + 1) % path.getLength());

                // Usar projeção vetorial para encontrar ponto na aresta
                const lat1 = p1.lat();
                const lng1 = p1.lng();
                const lat2 = p2.lat();
                const lng2 = p2.lng();
                const lat = latLng.lat();
                const lng = latLng.lng();

                // Vetor da aresta
                const dx = lng2 - lng1;
                const dy = lat2 - lat1;

                // Vetor do ponto p1 ao cursor
                const px = lng - lng1;
                const py = lat - lat1;

                // Calcular parâmetro t da projeção
                const lengthSquared = dx * dx + dy * dy;
                let t = 0;

                if (lengthSquared > 0) {
                    t = (px * dx + py * dy) / lengthSquared;
                    // Limitar entre 0 e 1 (extremidades da aresta)
                    t = Math.max(0, Math.min(1, t));
                }

                // Calcular coordenadas do ponto projetado
                const projLat = lat1 + t * dy;
                const projLng = lng1 + t * dx;
                const pontoProjetado = new google.maps.LatLng(projLat, projLng);

                // Calcular distância do cursor ao ponto projetado
                const dist = google.maps.geometry.spherical.computeDistanceBetween(latLng, pontoProjetado);

                if (dist < menorDistancia) {
                    menorDistancia = dist;
                }
            }

            return menorDistancia <= toleranciaMetros;
        }

        // Encontrar ponto mais próximo na aresta do polígono (projeção perpendicular suave)
        function encontrarPontoProximoNaAresta(latLng, poligono) {
            const path = poligono.getPath();
            let menorDistancia = Infinity;
            let pontoMaisProximo = null;
            let melhorT = 0;
            let melhorAresta = {
                p1: null,
                p2: null
            };

            // Percorrer todas as arestas para encontrar a mais próxima
            for (let i = 0; i < path.getLength(); i++) {
                const p1 = path.getAt(i);
                const p2 = path.getAt((i + 1) % path.getLength());

                // Usar projeção vetorial para encontrar ponto na aresta
                const lat1 = p1.lat();
                const lng1 = p1.lng();
                const lat2 = p2.lat();
                const lng2 = p2.lng();
                const lat = latLng.lat();
                const lng = latLng.lng();

                // Vetor da aresta
                const dx = lng2 - lng1;
                const dy = lat2 - lat1;

                // Vetor do ponto p1 ao cursor
                const px = lng - lng1;
                const py = lat - lat1;

                // Calcular parâmetro t da projeção
                const lengthSquared = dx * dx + dy * dy;
                let t = 0;

                if (lengthSquared > 0) {
                    t = (px * dx + py * dy) / lengthSquared;
                    // Limitar entre 0 e 1 (extremidades da aresta)
                    t = Math.max(0, Math.min(1, t));
                }

                // Calcular coordenadas do ponto projetado
                const projLat = lat1 + t * dy;
                const projLng = lng1 + t * dx;
                const pontoProjetado = new google.maps.LatLng(projLat, projLng);

                // Calcular distância do cursor ao ponto projetado
                const dist = google.maps.geometry.spherical.computeDistanceBetween(latLng, pontoProjetado);

                if (dist < menorDistancia) {
                    menorDistancia = dist;
                    pontoMaisProximo = pontoProjetado;
                    melhorT = t;
                    melhorAresta = {
                        p1,
                        p2
                    };
                }
            }

            return pontoMaisProximo || path.getAt(0);
        }

        // Desenhar linha de divisão (caminho com múltiplos pontos)
        function desenharLinhaDivisao() {
            if (pontosDivisao.length < 2) return;

            // Criar array de coordenadas do caminho
            const caminho = pontosDivisao.map(p => p.posicao);

            const linha = new google.maps.Polyline({
                path: caminho,
                strokeColor: '#FF0000',
                strokeWeight: 3,
                strokeOpacity: 0.8,
                map: map2Instance
            });

            linhasDivisao.push(linha);
        }

        // Atualizar linha de divisão (quando arrasta)
        function atualizarLinhaDivisao() {
            // Remover linha antiga
            linhasDivisao.forEach(l => l.setMap(null));
            linhasDivisao = [];

            // Desenhar nova
            desenharLinhaDivisao();
        }

        // Calcular e mostrar áreas dos dois lados
        async function calcularEMostrarAreas(poligono) {
            // Verificar se tem pelo menos 2 pontos, sendo o primeiro e último nas arestas
            if (pontosDivisao.length < 2) return;
            const primeiroNaAresta = pontosDivisao[0] && pontosDivisao[0].naAresta;
            const ultimoNaAresta = pontosDivisao[pontosDivisao.length - 1] && pontosDivisao[pontosDivisao.length - 1].naAresta;
            if (!primeiroNaAresta || !ultimoNaAresta) return;
            if (!window.turf) return;

            // Remover rótulos antigos
            rotulosArea.forEach(r => {
                if (r && r.map) r.map = null;
            });
            rotulosArea = [];

            const {
                AdvancedMarkerElement
            } = await google.maps.importLibrary("marker");

            try {
                // Obter path do polígono
                const path = poligono.getPath();
                const coords = [];
                for (let i = 0; i < path.getLength(); i++) {
                    coords.push(path.getAt(i));
                }

                // Pontos de divisão (primeiro e último do caminho)
                const p1 = pontosDivisao[0].posicao;
                const p2 = pontosDivisao[pontosDivisao.length - 1].posicao;

                // Encontrar arestas mais próximas para cada ponto de corte
                let arestaP1 = {
                    idx: 0,
                    distancia: Infinity
                };
                let arestaP2 = {
                    idx: 0,
                    distancia: Infinity
                };

                for (let i = 0; i < coords.length; i++) {
                    const v1 = coords[i];
                    const v2 = coords[(i + 1) % coords.length];

                    const distP1 = distanciaAoSegmento(p1, v1, v2);
                    if (distP1 < arestaP1.distancia) {
                        arestaP1 = {
                            idx: i,
                            distancia: distP1
                        };
                    }

                    const distP2 = distanciaAoSegmento(p2, v1, v2);
                    if (distP2 < arestaP2.distancia) {
                        arestaP2 = {
                            idx: i,
                            distancia: distP2
                        };
                    }
                }

                // Construir polígonos com caminho completo
                // O caminho de divisão será a NOVA BORDA entre os dois polígonos
                
                // Parte 1: Começa em p1, usa caminho de divisão completo até p2, depois segue perímetro original até fechar
                const parte1Coords = [];
                
                // 1. Começar com p1 (ponto inicial do caminho de divisão)
                parte1Coords.push(p1);
                
                // 2. Adicionar TODO o caminho de divisão (incluindo todos os pontos intermediários) até p2
                for (let i = 1; i < pontosDivisao.length; i++) {
                    parte1Coords.push(pontosDivisao[i].posicao);
                }
                
                // 3. Seguir perímetro do polígono original desde p2 até p1 (sentido horário)
                let idxAtual = (arestaP2.idx + 1) % coords.length;
                while (idxAtual !== (arestaP1.idx + 1) % coords.length) {
                    parte1Coords.push(coords[idxAtual]);
                    idxAtual = (idxAtual + 1) % coords.length;
                    if (parte1Coords.length > coords.length + pontosDivisao.length + 10) break;
                }

                // Parte 2: Começa em p2, usa caminho de divisão completo em ordem inversa até p1, depois segue perímetro original até fechar
                const parte2Coords = [];
                
                // 1. Começar com p2 (ponto final do caminho de divisão)
                parte2Coords.push(p2);
                
                // 2. Adicionar TODO o caminho de divisão em ordem inversa (incluindo todos os pontos intermediários) até p1
                for (let i = pontosDivisao.length - 2; i >= 0; i--) {
                    parte2Coords.push(pontosDivisao[i].posicao);
                }
                
                // 3. Seguir perímetro do polígono original desde p1 até p2 (sentido horário)
                idxAtual = (arestaP1.idx + 1) % coords.length;
                while (idxAtual !== (arestaP2.idx + 1) % coords.length) {
                    parte2Coords.push(coords[idxAtual]);
                    idxAtual = (idxAtual + 1) % coords.length;
                    if (parte2Coords.length > coords.length + pontosDivisao.length + 10) break;
                }

                // Calcular áreas e centroides EXATAMENTE como no teste.html
                // Parte 1
                let coords1 = parte1Coords.map(p => [p.lng(), p.lat()]);
                coords1.push(coords1[0]); // fechar polígono
                let turfPoly1 = turf.polygon([coords1]);
                let area1 = turf.area(turfPoly1);
                let centroid1 = turf.centroid(turfPoly1);
                let [lng1, lat1] = centroid1.geometry.coordinates;

                // Parte 2
                let coords2 = parte2Coords.map(p => [p.lng(), p.lat()]);
                coords2.push(coords2[0]); // fechar polígono
                let turfPoly2 = turf.polygon([coords2]);
                let area2 = turf.area(turfPoly2);
                let centroid2 = turf.centroid(turfPoly2);
                let [lng2, lat2] = centroid2.geometry.coordinates;

                // Criar rótulos nos centroides
                const rotulo1 = criarRotuloArea(area1);
                const marker1 = new AdvancedMarkerElement({
                    position: {
                        lat: lat1,
                        lng: lng1
                    },
                    content: rotulo1,
                    map: map2Instance,
                    zIndex: 11000
                });

                const rotulo2 = criarRotuloArea(area2);
                const marker2 = new AdvancedMarkerElement({
                    position: {
                        lat: lat2,
                        lng: lng2
                    },
                    content: rotulo2,
                    map: map2Instance,
                    zIndex: 11000
                });

                rotulosArea.push(marker1, marker2);

            } catch (error) {
                console.error('Erro ao calcular áreas:', error);
            }
        }

        // Criar elemento HTML do rótulo de área
        function criarRotuloArea(area) {
            const el = document.createElement('div');
            el.style.background = 'rgba(255, 255, 255, 0.9)';
            el.style.padding = '4px 8px';
            el.style.borderRadius = '4px';
            el.style.border = '1px solid #333';
            el.style.fontSize = '11px';
            el.style.fontWeight = 'bold';
            el.textContent = area.toFixed(2) + ' m²';
            return el;
        }

        // Limpar sistema de divisão
        function limparSistemaDivisao() {
            // Remover bolinha móvel se existir
            if (objetoDestacado.poligono && objetoDestacado.poligono.marcadorMovel) {
                objetoDestacado.poligono.marcadorMovel.map = null;
            }

            // Remover listeners
            if (objetoDestacado.poligono && objetoDestacado.poligono.listeners) {
                google.maps.event.removeListener(objetoDestacado.poligono.listeners.mouseMove);
                google.maps.event.removeListener(objetoDestacado.poligono.listeners.click);
            }

            // Remover marcadores de divisão
            marcadoresDivisao.forEach(m => {
                if (m.map) m.map = null;
            });
            marcadoresDivisao = [];

            // Remover linhas de divisão
            linhasDivisao.forEach(l => {
                if (l.setMap) l.setMap(null);
            });
            linhasDivisao = [];

            // Remover rótulos de área
            rotulosArea.forEach(r => {
                if (r.map) r.map = null;
            });
            rotulosArea = [];

            pontosDivisao = [];
        }

        // Dividir polígono
        async function dividirPoligono() {
            // Verificar se tem pelo menos 2 pontos, sendo o primeiro e último nas arestas
            if (!objetoDestacado.poligono || pontosDivisao.length < 2) {
                alert('É necessário criar um caminho com pelo menos 2 pontos, sendo o primeiro e último nas arestas do polígono.');
                return;
            }
            
            const primeiroNaAresta = pontosDivisao[0] && pontosDivisao[0].naAresta;
            const ultimoNaAresta = pontosDivisao[pontosDivisao.length - 1] && pontosDivisao[pontosDivisao.length - 1].naAresta;
            
            if (!primeiroNaAresta || !ultimoNaAresta) {
                alert('O primeiro e último ponto do caminho devem estar nas arestas do polígono.');
                return;
            }

            try {
                const poligonoOriginal = objetoDestacado.poligono;
                const path = poligonoOriginal.getPath();

                // Converter para array de coordenadas
                const coords = [];
                for (let i = 0; i < path.getLength(); i++) {
                    coords.push(path.getAt(i));
                }

                // Ponto 1 e Ponto 2 da divisão (primeiro e último do caminho)
                const p1 = pontosDivisao[0].posicao;
                const p2 = pontosDivisao[pontosDivisao.length - 1].posicao;

                // Encontrar arestas mais próximas para cada ponto de corte
                let arestaP1 = {
                    idx: 0,
                    distancia: Infinity
                };
                let arestaP2 = {
                    idx: 0,
                    distancia: Infinity
                };

                for (let i = 0; i < coords.length; i++) {
                    const v1 = coords[i];
                    const v2 = coords[(i + 1) % coords.length];

                    // Distância de p1 a esta aresta
                    const distP1 = distanciaAoSegmento(p1, v1, v2);
                    if (distP1 < arestaP1.distancia) {
                        arestaP1 = {
                            idx: i,
                            distancia: distP1
                        };
                    }

                    // Distância de p2 a esta aresta
                    const distP2 = distanciaAoSegmento(p2, v1, v2);
                    if (distP2 < arestaP2.distancia) {
                        arestaP2 = {
                            idx: i,
                            distancia: distP2
                        };
                    }
                }

                // Construir caminho completo de divisão (incluindo todos os pontos intermediários)
                const caminhoDivisao = pontosDivisao.map(p => p.posicao);

                // Construir polígonos com lógica correta
                // O caminho de divisão será a NOVA BORDA entre os dois polígonos
                
                // Parte 1: Começa em p1, usa caminho de divisão completo até p2, depois segue perímetro original até fechar
                const parte1 = [];
                
                // 1. Começar com p1 (ponto inicial do caminho de divisão)
                parte1.push(p1);
                
                // 2. Adicionar TODO o caminho de divisão (incluindo todos os pontos intermediários) até p2
                for (let i = 1; i < pontosDivisao.length; i++) {
                    parte1.push(pontosDivisao[i].posicao);
                }
                
                // 3. Seguir perímetro do polígono original desde p2 até p1 (sentido horário)
                // Começar do vértice seguinte à aresta de p2
                let idxAtual = (arestaP2.idx + 1) % coords.length;
                while (idxAtual !== (arestaP1.idx + 1) % coords.length) {
                    parte1.push(coords[idxAtual]);
                    idxAtual = (idxAtual + 1) % coords.length;
                    if (parte1.length > coords.length + pontosDivisao.length + 10) break; // Proteção
                }

                // Parte 2: Começa em p2, usa caminho de divisão completo em ordem inversa até p1, depois segue perímetro original até fechar
                const parte2 = [];
                
                // 1. Começar com p2 (ponto final do caminho de divisão)
                parte2.push(p2);
                
                // 2. Adicionar TODO o caminho de divisão em ordem inversa (incluindo todos os pontos intermediários) até p1
                for (let i = pontosDivisao.length - 2; i >= 0; i--) {
                    parte2.push(pontosDivisao[i].posicao);
                }
                
                // 3. Seguir perímetro do polígono original desde p1 até p2 (sentido horário)
                // Começar do vértice seguinte à aresta de p1
                idxAtual = (arestaP1.idx + 1) % coords.length;
                while (idxAtual !== (arestaP2.idx + 1) % coords.length) {
                    parte2.push(coords[idxAtual]);
                    idxAtual = (idxAtual + 1) % coords.length;
                    if (parte2.length > coords.length + pontosDivisao.length + 10) break; // Proteção
                }

                // Criar os 2 novos polígonos VERMELHOS
                const polygon1 = new google.maps.Polygon({
                    paths: parte1,
                    strokeColor: '#FF0000',
                    strokeOpacity: 0.8,
                    strokeWeight: 2,
                    fillColor: '#FF0000',
                    fillOpacity: 0.35,
                    clickable: true,
                    map: map2Instance
                });

                const polygon2 = new google.maps.Polygon({
                    paths: parte2,
                    strokeColor: '#FF0000',
                    strokeOpacity: 0.8,
                    strokeWeight: 2,
                    fillColor: '#FF0000',
                    fillOpacity: 0.35,
                    clickable: true,
                    map: map2Instance
                });
                
                // Variáveis para armazenar os novos marcadores
                let novoMarcador1 = null;
                let novoMarcador2 = null;

                // Calcular centroides 
                // Calcular centroide com Turf
                const coords1 = parte1.map(v => [v.lng(), v.lat()]);
                coords1.push(coords1[0]);
                const turfPoly1 = turf.polygon([coords1]);
                const centro1 = turf.centroid(turfPoly1).geometry.coordinates;
                const lng1 = centro1[0];
                const lat1 = centro1[1];

                const coords2 = parte2.map(v => [v.lng(), v.lat()]);
                coords2.push(coords2[0]);
                const turfPoly2 = turf.polygon([coords2]);
                const centro2 = turf.centroid(turfPoly2).geometry.coordinates;
                const lng2 = centro2[0];
                const lat2 = centro2[1];

                // Criar marcadores vazios nos CENTROIDES
                const {
                    AdvancedMarkerElement
                } = await google.maps.importLibrary("marker");

                const marcador1 = criarMarcadorVazio();
                novoMarcador1 = new AdvancedMarkerElement({
                    position: {
                        lat: lat1,
                        lng: lng1
                    },
                    content: marcador1,
                    map: map2Instance
                });

                const marcador2 = criarMarcadorVazio();
                novoMarcador2 = new AdvancedMarkerElement({
                    position: {
                        lat: lat2,
                        lng: lng2
                    },
                    content: marcador2,
                    map: map2Instance
                });

                // ========================================================================
                // SALVAR NO BANCO DE DADOS
                // ========================================================================

                // Preparar dados para envio
                const dadosDesdobro = {
                    poligono_original: poligonoOriginal.desenhoCompleto,
                    marcador_original: objetoDestacado.marcador ? objetoDestacado.marcador.desenhoCompleto : null,
                    novos_poligonos: [{
                            coordenadas: parte1.map(v => ({
                                lat: v.lat(),
                                lng: v.lng()
                            }))
                        },
                        {
                            coordenadas: parte2.map(v => ({
                                lat: v.lat(),
                                lng: v.lng()
                            }))
                        }
                    ],
                    novos_marcadores: [{
                            coordenadas: [{
                                lat: lat1,
                                lng: lng1
                            }]
                        },
                        {
                            coordenadas: [{
                                lat: lat2,
                                lng: lng2
                            }]
                        }
                    ]
                };

                // Enviar para o servidor
                $.ajax({
                    url: 'index_3_novo_processar_desdobro.php',
                    method: 'POST',
                    data: JSON.stringify(dadosDesdobro),
                    contentType: 'application/json',
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            console.log('Desdobro salvo no banco com sucesso!');

                            // Verificar se o desenho original tinha lote (era verde) para atualizar tabela
                            const desenhoOriginal = poligonoOriginal.desenhoCompleto || {};
                            if (desenhoOriginal.lote && desenhoOriginal.lote.toString().trim() !== '') {
                                // Era verde, agora ficou vermelho - atualizar tabela
                                atualizarLinhaTabelaParaVermelho(desenhoOriginal.quarteirao, desenhoOriginal.lote);
                            }

                            // Remover polígono original dos arrays e do mapa
                            const indexPoligono = poligonosPosteriorArray.indexOf(poligonoOriginal);
                            if (indexPoligono > -1) {
                                poligonosPosteriorArray.splice(indexPoligono, 1);
                            }
                            poligonoOriginal.setMap(null);

                            // Remover marcador original dos arrays e do mapa se houver
                            if (objetoDestacado.marcador) {
                                const indexMarcador = marcadoresPosteriorArray.indexOf(objetoDestacado.marcador);
                                if (indexMarcador > -1) {
                                    marcadoresPosteriorArray.splice(indexMarcador, 1);
                                }
                                objetoDestacado.marcador.map = null;
                            }

                            // Adicionar novos desenhos aos arrays
                            const desenhoBase = poligonoOriginal.desenhoCompleto || {};
                            
                            // O servidor retorna arrays de IDs: novos_poligonos e novos_marcadores
                            const idPoligono1 = response.novos_poligonos?.[0] || null;
                            const idPoligono2 = response.novos_poligonos?.[1] || null;
                            const idMarcador1 = response.novos_marcadores?.[0] || null;
                            const idMarcador2 = response.novos_marcadores?.[1] || null;
                            
                            // Adicionar primeiro polígono
                            if (polygon1 && idPoligono1) {
                                const desenho1 = {
                                    id: idPoligono1,
                                    lote: null,
                                    quarteirao: desenhoBase.quarteirao || null,
                                    quadricula: desenhoBase.quadricula || null,
                                    quadra: desenhoBase.quadra || null,
                                    camada: 'poligono_lote',
                                    tipo: desenhoBase.tipo || 'poligono',
                                    cor: '#FF0000',
                                    status: 1,
                                    coordenadas: JSON.stringify(polygon1.getPath().getArray().map(p => ({lat: p.lat(), lng: p.lng()})))
                                };
                                adicionarPoligonoAoArray(polygon1, desenho1);
                            }
                            
                            // Adicionar segundo polígono
                            if (polygon2 && idPoligono2) {
                                const desenho2 = {
                                    id: idPoligono2,
                                    lote: null,
                                    quarteirao: desenhoBase.quarteirao || null,
                                    quadricula: desenhoBase.quadricula || null,
                                    quadra: desenhoBase.quadra || null,
                                    camada: 'poligono_lote',
                                    tipo: desenhoBase.tipo || 'poligono',
                                    cor: '#FF0000',
                                    status: 1,
                                    coordenadas: JSON.stringify(polygon2.getPath().getArray().map(p => ({lat: p.lat(), lng: p.lng()})))
                                };
                                adicionarPoligonoAoArray(polygon2, desenho2);
                            }
                            
                            // Adicionar primeiro marcador
                            if (novoMarcador1 && marcador1 && idMarcador1) {
                                const desenhoMarcador1 = {
                                    id: idMarcador1,
                                    lote: null,
                                    quarteirao: desenhoBase.quarteirao || null,
                                    quadricula: desenhoBase.quadricula || null,
                                    quadra: desenhoBase.quadra || null,
                                    camada: 'marcador_quadra',
                                    tipo: desenhoBase.tipo || 'marcador',
                                    cor: '#FF0000',
                                    status: 1,
                                    coordenadas: JSON.stringify([{lat: lat1, lng: lng1}])
                                };
                                adicionarMarcadorAoArray(novoMarcador1, marcador1, desenhoMarcador1);
                            }
                            
                            // Adicionar segundo marcador
                            if (novoMarcador2 && marcador2 && idMarcador2) {
                                const desenhoMarcador2 = {
                                    id: idMarcador2,
                                    lote: null,
                                    quarteirao: desenhoBase.quarteirao || null,
                                    quadricula: desenhoBase.quadricula || null,
                                    quadra: desenhoBase.quadra || null,
                                    camada: 'marcador_quadra',
                                    tipo: desenhoBase.tipo || 'marcador',
                                    cor: '#FF0000',
                                    status: 1,
                                    coordenadas: JSON.stringify([{lat: lat2, lng: lng2}])
                                };
                                adicionarMarcadorAoArray(novoMarcador2, marcador2, desenhoMarcador2);
                            }

                            // Limpar sistema
                            limparSistemaDivisao();
                            objetoDestacado = {
                                poligono: null,
                                marcador: null
                            };

                            // Restaurar botões para dividir outro
                            document.getElementById('btnUnificar').style.display = 'none';
                            document.getElementById('btnDividir').style.display = 'none';
                            document.getElementById('btnCancelar').style.display = 'inline-block';

                        } else {
                            console.error('Erro ao salvar desdobro:', response.message);
                            alert('Erro ao salvar desdobro: ' + response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Erro no AJAX:', error);
                        alert('Erro ao comunicar com o servidor');
                    }
                });

            } catch (error) {
                console.error('Erro ao dividir polígono:', error);
            }
        }

        // Criar marcador vazio (bolinha vermelha sem texto)
        function criarMarcadorVazio() {
            const el = document.createElement('div');
            el.style.padding = '0 5px';
            el.style.height = '16px';
            el.style.background = '#FF0000';
            el.style.borderRadius = '10px';
            el.style.display = 'flex';
            el.style.alignItems = 'center';
            el.style.justifyContent = 'center';
            el.style.color = 'white';
            el.style.fontWeight = 'bold';
            el.style.fontSize = '8px';
            el.style.border = '2px solid white';
            el.style.cursor = 'pointer';
            el.style.transform = 'translate(0, 10px)';
            el.style.minWidth = '16px';

            return el;
        }

        // Calcular distância de um ponto a um segmento de reta
        function distanciaAoSegmento(ponto, v1, v2) {
            // Usar a biblioteca do Google Maps para calcular
            // Testar vários pontos ao longo da aresta e pegar a menor distância
            let menorDist = Infinity;

            for (let t = 0; t <= 1; t += 0.1) {
                const pontoNaAresta = google.maps.geometry.spherical.interpolate(v1, v2, t);
                const dist = google.maps.geometry.spherical.computeDistanceBetween(ponto, pontoNaAresta);
                if (dist < menorDist) {
                    menorDist = dist;
                }
            }

            return menorDist;
        }

        // Encontrar índices de inserção dos pontos de divisão
        function encontrarIndicesDeInsercao(coords, p1, p2) {
            let idx1 = 0,
                idx2 = 0;
            let menorDist1 = Infinity,
                menorDist2 = Infinity;

            // Encontrar arestas mais próximas para cada ponto
            for (let i = 0; i < coords.length; i++) {
                const dist1 = google.maps.geometry.spherical.computeDistanceBetween(coords[i], p1);
                const dist2 = google.maps.geometry.spherical.computeDistanceBetween(coords[i], p2);

                if (dist1 < menorDist1) {
                    menorDist1 = dist1;
                    idx1 = i;
                }

                if (dist2 < menorDist2) {
                    menorDist2 = dist2;
                    idx2 = i;
                }
            }

            return {
                idx1,
                idx2
            };
        }

        // Limpar sistema de divisão
        function limparSistemaDivisao() {
            // Remover bolinha móvel se existir
            if (objetoDestacado.poligono && objetoDestacado.poligono.marcadorMovel) {
                objetoDestacado.poligono.marcadorMovel.map = null;
            }

            // Remover listeners
            if (objetoDestacado.poligono && objetoDestacado.poligono.listeners) {
                google.maps.event.removeListener(objetoDestacado.poligono.listeners.mouseMove);
                google.maps.event.removeListener(objetoDestacado.poligono.listeners.click);
            }

            // Remover marcadores de divisão
            marcadoresDivisao.forEach(m => {
                if (m.map) m.map = null;
            });
            marcadoresDivisao = [];

            // Remover linhas de divisão
            linhasDivisao.forEach(l => {
                if (l.setMap) l.setMap(null);
            });
            linhasDivisao = [];

            // Remover rótulos de área
            rotulosArea.forEach(r => {
                if (r.map) r.map = null;
            });
            rotulosArea = [];

            pontosDivisao = [];
        }

        // Cancelar divisão ou unificação
        function cancelarDivisao() {
            limparSistemaDivisao();
            limparSelecaoUnificacao();
            removerDestaque();
            modoAtivo = null;

            // Restaurar botões originais
            document.getElementById('btnDesdobrar').classList.remove('active');
            document.getElementById('btnUnificar').classList.remove('active');
            document.getElementById('btnUnificar').style.display = 'inline-block';
            document.getElementById('btnDividir').style.display = 'none';
            document.getElementById('btnUnir').style.display = 'none';
            document.getElementById('btnCancelar').style.display = 'none';
        }

        // ============================================================================
        // SISTEMA DE UNIFICAÇÃO DE POLÍGONOS
        // ============================================================================

        // Limpar seleção de polígonos para unificar
        function limparSelecaoUnificacao() {
            // Restaurar cores dos polígonos selecionados
            poligonosSelecionados.forEach(p => {
                p.setOptions({
                    strokeColor: p.corOriginal,
                    fillColor: p.corOriginal,
                    strokeWeight: 2
                });
            });

            // Restaurar marcadores selecionados
            marcadoresSelecionados.forEach(m => {
                m.elementoHTML.style.background = m.corOriginal;
                m.elementoHTML.style.color = m.corTextoOriginal;
                m.elementoHTML.style.border = '2px solid ' + m.corTextoOriginal;
            });

            poligonosSelecionados = [];
            marcadoresSelecionados = [];

            // Ocultar botão unir
            document.getElementById('btnUnir').style.display = 'none';
        }

        // Unir polígonos selecionados
        async function unirPoligonos() {
            // Criar cópias dos arrays para garantir que usamos apenas os itens selecionados
            const poligonosParaUnificar = [...poligonosSelecionados];
            const marcadoresParaUnificar = [...marcadoresSelecionados];

            // Mostrar no console quais itens serão unificados
            console.log('=== INÍCIO DA UNIFICAÇÃO ===');
            console.log('Polígonos selecionados:', poligonosParaUnificar.length);
            poligonosParaUnificar.forEach((p, idx) => {
                console.log(`  Polígono ${idx + 1}: ID=${p.idDesenho}, Lote=${p.lote}`);
            });
            
            console.log('Marcadores selecionados:', marcadoresParaUnificar.length);
            marcadoresParaUnificar.forEach((m, idx) => {
                console.log(`  Marcador ${idx + 1}: ID=${m.idDesenho}, Lote=${m.lote}`);
            });
            console.log('===========================');

            if (poligonosParaUnificar.length < 2) {
                alert('Selecione pelo menos 2 polígonos para unir');
                return;
            }

            try {
                // Verificar se todos os polígonos estão adjacentes (próximos)
                const poligonosSemBuffer = [];

                poligonosParaUnificar.forEach((poly, idx) => {
                    let path = poly.getPath().getArray();
                    let coords = path.map(p => [p.lng(), p.lat()]);
                    coords.push(coords[0]);

                    const turfPoly = turf.polygon([coords]);
                    poligonosSemBuffer.push(turfPoly);
                });

                // Verificar se os polígonos estão realmente adjacentes (vértices próximos)
                let algumAdjacente = false;

                for (let i = 0; i < poligonosParaUnificar.length; i++) {
                    for (let j = i + 1; j < poligonosParaUnificar.length; j++) {
                        // Pegar vértices de ambos os polígonos
                        const path1 = poligonosParaUnificar[i].getPath().getArray();
                        const path2 = poligonosParaUnificar[j].getPath().getArray();

                        let menorDistancia = Infinity;

                        // Comparar cada vértice de poly1 com cada vértice de poly2
                        path1.forEach(v1 => {
                            path2.forEach(v2 => {
                                const dist = google.maps.geometry.spherical.computeDistanceBetween(v1, v2);
                                if (dist < menorDistancia) {
                                    menorDistancia = dist;
                                }
                            });
                        });

                        // Se algum vértice está a menos de 1 metro, são adjacentes
                        if (menorDistancia < 1) {
                            algumAdjacente = true;
                        }
                    }
                }

                if (!algumAdjacente) {
                    alert('Erro: Os polígonos selecionados não estão adjacentes (distância > 1m). Selecione apenas polígonos que se tocam.');
                    limparSelecaoUnificacao();
                    return;
                }

                // Coletar todos os polígonos com buffer
                let listaPoligonos = [];

                poligonosParaUnificar.forEach((poly, idx) => {
                    let path = poly.getPath().getArray();
                    let coords = path.map(p => [p.lng(), p.lat()]);
                    coords.push(coords[0]);

                    let turfPoly = turf.polygon([coords]);

                    // Aplicar buffer para garantir sobreposição
                    turfPoly = turf.buffer(turfPoly, 0.5, {
                        units: 'meters'
                    });

                    listaPoligonos.push(turfPoly);
                });

                // União iterativa até ficar apenas 1 Polygon
                let maxIteracoes = 10;
                let iteracao = 0;

                while (listaPoligonos.length > 1 && iteracao < maxIteracoes) {
                    iteracao++;

                    const novaLista = [];
                    let i = 0;

                    // Tentar unir aos pares
                    while (i < listaPoligonos.length) {
                        if (i + 1 < listaPoligonos.length) {
                            // Unir i com i+1
                            const resultado = turf.union(listaPoligonos[i], listaPoligonos[i + 1]);

                            if (resultado) {
                                novaLista.push(resultado);
                            } else {
                                novaLista.push(listaPoligonos[i]);
                                novaLista.push(listaPoligonos[i + 1]);
                            }
                            i += 2;
                        } else {
                            // Sobrou um polígono sem par
                            novaLista.push(listaPoligonos[i]);
                            i++;
                        }
                    }

                    listaPoligonos = novaLista;
                }

                if (listaPoligonos.length === 0) {
                    console.error('Erro: Lista vazia');
                    return;
                }

                let poligonoUnido = listaPoligonos[0];

                // Aplicar buffer negativo para remover o buffer anterior e voltar ao tamanho original
                poligonoUnido = turf.buffer(poligonoUnido, -0.5, {
                    units: 'meters'
                });

                // Converter resultado de volta para Google Maps

                // Pegar o exterior do polígono
                let exterior;

                if (poligonoUnido.geometry.type === 'Polygon') {
                    exterior = poligonoUnido.geometry.coordinates[0];

                } else if (poligonoUnido.geometry.type === 'MultiPolygon') {
                    // Se é MultiPolygon, criar todos os polígonos separados
                    poligonoUnido.geometry.coordinates.forEach((polyCoords, idx) => {
                        const ext = polyCoords[0];
                        const coords = ext.map(c => ({
                            lat: parseFloat(c[1]),
                            lng: parseFloat(c[0])
                        }));

                        const polyMulti = new google.maps.Polygon({
                            paths: coords,
                            strokeColor: '#FF0000',
                            strokeOpacity: 0.8,
                            strokeWeight: 2,
                            fillColor: '#FF0000',
                            fillOpacity: 0.35,
                            clickable: true,
                            map: map2Instance
                        });
                        
                        // Adicionar ao array (usar dados temporários, serão atualizados na resposta do servidor)
                        if (idx === 0) {
                            novoPoligono = polyMulti; // Usar o primeiro como principal
                        }
                    });

                    exterior = poligonoUnido.geometry.coordinates[0][0];
                } else {
                    console.error('Tipo de geometria desconhecido:', poligonoUnido.geometry.type);
                    return;
                }

                // Variável para armazenar o novo polígono criado
                let novoPoligono = null;
                
                // Se não é MultiPolygon, criar o polígono único
                if (poligonoUnido.geometry.type === 'Polygon') {
                    const coordsFinais = exterior.map(c => ({
                        lat: parseFloat(c[1]),
                        lng: parseFloat(c[0])
                    }));

                    novoPoligono = new google.maps.Polygon({
                        paths: coordsFinais,
                        strokeColor: '#FF0000',
                        strokeOpacity: 0.8,
                        strokeWeight: 2,
                        fillColor: '#FF0000',
                        fillOpacity: 0.35,
                        clickable: true,
                        map: map2Instance
                    });
                }

                // Calcular centroide do polígono unido
                let centroide;
                if (poligonoUnido.type === 'Feature') {
                    centroide = turf.centroid(poligonoUnido);
                } else {
                    centroide = turf.centroid(turf.feature(poligonoUnido));
                }

                const [lng, lat] = centroide.geometry.coordinates;

                // Criar marcador vazio no centroide
                const {
                    AdvancedMarkerElement
                } = await google.maps.importLibrary("marker");
                const marcadorVazio = criarMarcadorVazio();
                const novoMarcador = new AdvancedMarkerElement({
                    position: {
                        lat,
                        lng
                    },
                    content: marcadorVazio,
                    map: map2Instance
                });

                // ========================================================================
                // SALVAR NO BANCO DE DADOS
                // ========================================================================

                console.log('=== ITENS QUE SERÃO UNIFICADOS (CONFIRMAÇÃO FINAL) ===');
                console.log('Polígonos para unificar:', poligonosParaUnificar.length);
                poligonosParaUnificar.forEach((p, idx) => {
                    console.log(`  Polígono ${idx + 1}: ID=${p.idDesenho}, Lote=${p.lote}`);
                });
                
                console.log('Marcadores para unificar:', marcadoresParaUnificar.length);
                marcadoresParaUnificar.forEach((m, idx) => {
                    console.log(`  Marcador ${idx + 1}: ID=${m.idDesenho}, Lote=${m.lote}`);
                });
                console.log('======================================================');

                // Preparar dados dos polígonos originais (usar apenas os selecionados)
                const poligonosOriginais = poligonosParaUnificar.map(p => p.desenhoCompleto);

                // Preparar dados dos marcadores originais (usar apenas os selecionados)
                const marcadoresOriginais = marcadoresParaUnificar.map(m => m.desenhoCompleto);

                // Preparar coordenadas do novo polígono unificado
                let coordenadasNovoPoligono;
                if (poligonoUnido.geometry.type === 'Polygon') {
                    const ext = poligonoUnido.geometry.coordinates[0];
                    coordenadasNovoPoligono = ext.map(c => ({
                        lat: parseFloat(c[1]),
                        lng: parseFloat(c[0])
                    }));
                } else {
                    const ext = poligonoUnido.geometry.coordinates[0][0];
                    coordenadasNovoPoligono = ext.map(c => ({
                        lat: parseFloat(c[1]),
                        lng: parseFloat(c[0])
                    }));
                }

                const dadosUnificacao = {
                    poligonos_originais: poligonosOriginais,
                    marcadores_originais: marcadoresOriginais,
                    novo_poligono: {
                        coordenadas: coordenadasNovoPoligono
                    },
                    novo_marcador: {
                        coordenadas: [{
                            lat,
                            lng
                        }]
                    }
                };

                // Enviar para o servidor
                $.ajax({
                    url: 'index_3_novo_processar_unificacao.php',
                    method: 'POST',
                    data: JSON.stringify(dadosUnificacao),
                    contentType: 'application/json',
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            console.log('Unificação salva com sucesso! Removendo itens do mapa...');
                            
                            // Verificar se algum desenho original tinha lote (era verde) para atualizar tabela
                            poligonosParaUnificar.forEach(p => {
                                const desenho = p.desenhoCompleto || {};
                                if (desenho.lote && desenho.lote.toString().trim() !== '') {
                                    // Era verde, agora ficou vermelho - atualizar tabela
                                    atualizarLinhaTabelaParaVermelho(desenho.quarteirao, desenho.lote);
                                }
                            });

                            // Remover polígonos originais dos arrays e do mapa
                            poligonosParaUnificar.forEach(p => {
                                const index = poligonosPosteriorArray.indexOf(p);
                                if (index > -1) {
                                    poligonosPosteriorArray.splice(index, 1);
                                }
                                p.setMap(null);
                            });

                            // Remover marcadores originais dos arrays e do mapa
                            marcadoresParaUnificar.forEach(m => {
                                const index = marcadoresPosteriorArray.indexOf(m);
                                if (index > -1) {
                                    marcadoresPosteriorArray.splice(index, 1);
                                }
                                if (m.map) m.map = null;
                            });

                            // Adicionar novos desenhos aos arrays se foram criados
                            // O servidor retorna: novo_poligono (ID) e novo_marcador (ID)
                            const desenhoBase = poligonosParaUnificar[0]?.desenhoCompleto || {};
                            const desenhoMarcadorBase = marcadoresParaUnificar[0]?.desenhoCompleto || desenhoBase;
                            
                            if (novoPoligono && response.novo_poligono) {
                                const desenhoPoligono = {
                                    id: response.novo_poligono,
                                    lote: null,
                                    quarteirao: desenhoBase.quarteirao || null,
                                    quadricula: desenhoBase.quadricula || null,
                                    quadra: desenhoBase.quadra || null,
                                    camada: 'poligono_lote',
                                    tipo: desenhoBase.tipo || 'poligono',
                                    cor: '#FF0000',
                                    status: 1,
                                    coordenadas: JSON.stringify(novoPoligono.getPath().getArray().map(p => ({lat: p.lat(), lng: p.lng()})))
                                };
                                adicionarPoligonoAoArray(novoPoligono, desenhoPoligono);
                            }

                            if (novoMarcador && marcadorVazio && response.novo_marcador) {
                                const desenhoMarcador = {
                                    id: response.novo_marcador,
                                    lote: null,
                                    quarteirao: desenhoMarcadorBase.quarteirao || desenhoBase.quarteirao || null,
                                    quadricula: desenhoMarcadorBase.quadricula || desenhoBase.quadricula || null,
                                    quadra: desenhoMarcadorBase.quadra || desenhoBase.quadra || null,
                                    camada: 'marcador_quadra',
                                    tipo: desenhoMarcadorBase.tipo || desenhoBase.tipo || 'marcador',
                                    cor: '#FF0000',
                                    status: 1,
                                    coordenadas: JSON.stringify([{lat: lat, lng: lng}])
                                };
                                adicionarMarcadorAoArray(novoMarcador, marcadorVazio, desenhoMarcador);
                            }

                            // Limpar seleção
                            limparSelecaoUnificacao();

                            // Manter no modo unificar para unir outros
                            document.getElementById('btnUnir').style.display = 'none';

                        } else {
                            console.error('Erro ao salvar unificação:', response.message);
                            alert('Erro ao salvar unificação: ' + response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Erro no AJAX:', error);
                        alert('Erro ao comunicar com o servidor');
                    }
                });

            } catch (error) {
                console.error('Erro ao unir polígonos:', error);
                alert('Erro ao unir polígonos');
            }
        }

        // ============================================================================
        // FUNÇÕES DE CONTROLE DE MODO
        // ============================================================================

        // Ativar modo desdobrar
        function ativarModoDesdobrar() {
            modoAtivo = 'desdobrar';

            document.getElementById('btnDesdobrar').classList.add('active');
            document.getElementById('btnUnificar').classList.remove('active');

            removerDestaque();
        }

        // Ativar modo unificar
        function ativarModoUnificar() {
            modoAtivo = 'unificar';

            document.getElementById('btnDesdobrar').classList.remove('active');
            document.getElementById('btnUnificar').classList.add('active');

            // Mostrar botão cancelar
            document.getElementById('btnCancelar').style.display = 'inline-block';

            removerDestaque();
            limparSelecaoUnificacao();
        }

        // ============================================================================
        // FUNÇÃO PARA ALTERNAR MODO DE EDIÇÃO DE VÉRTICES (MAP1)
        // ============================================================================
        function alternarModoVertices() {
            modoVerticesAtivo = !modoVerticesAtivo;
            const btnVertices = document.getElementById('btnVertices');
            
            if (modoVerticesAtivo) {
                // Ativar modo de edição: tornar polígonos editáveis e marcadores draggable
                poligonosAnteriorArray.forEach(polygon => {
                    polygon.setOptions({
                        editable: true
                    });
                });
                
                marcadoresAnteriorArray.forEach(marker => {
                    if (marker.setOptions) {
                        marker.setOptions({ gmpDraggable: true });
                    } else {
                        marker.gmpDraggable = true;
                    }
                });
                
                // Mudar texto do botão
                btnVertices.innerHTML = '<i class="fas fa-undo"></i> Resetar';
                btnVertices.classList.remove('btn-outline-light');
                btnVertices.classList.add('btn-warning');
                
            } else {
                // Desativar modo de edição e restaurar coordenadas originais
                poligonosAnteriorArray.forEach(polygon => {
                    // Restaurar coordenadas originais do polígono
                    if (polygon.coordenadasOriginais) {
                        polygon.setPath(polygon.coordenadasOriginais);
                    }
                    // Desativar edição
                    polygon.setOptions({
                        editable: false
                    });
                });
                
                marcadoresAnteriorArray.forEach(marker => {
                    // Restaurar posição original do marcador
                    if (marker.posicaoOriginal) {
                        // Atualizar posição diretamente (funciona com AdvancedMarkerElement)
                        marker.position = marker.posicaoOriginal;
                        // Desativar draggable
                        if (marker.setOptions) {
                            marker.setOptions({ gmpDraggable: false });
                        } else {
                            marker.gmpDraggable = false;
                        }
                    } else {
                        // Apenas desativar draggable se não houver posição original
                        if (marker.setOptions) {
                            marker.setOptions({ gmpDraggable: false });
                        } else {
                            marker.gmpDraggable = false;
                        }
                    }
                });
                
                // Mudar texto do botão
                btnVertices.innerHTML = '<i class="fas fa-edit"></i> Vértices';
                btnVertices.classList.remove('btn-warning');
                btnVertices.classList.remove('btn-primary');
                btnVertices.classList.add('btn-primary');
            }
        }

        // ============================================================================
        // INICIALIZAÇÃO
        // ============================================================================

        window.addEventListener('load', function() {
            setTimeout(initMaps, 500);

            // Event listeners dos botões
            document.getElementById('btnDesdobrar').addEventListener('click', ativarModoDesdobrar);
            document.getElementById('btnUnificar').addEventListener('click', ativarModoUnificar);
            document.getElementById('btnDividir').addEventListener('click', dividirPoligono);
            document.getElementById('btnUnir').addEventListener('click', unirPoligonos);
            document.getElementById('btnCancelar').addEventListener('click', cancelarDivisao);
            
            // Event listener do botão Vértices
            const btnVertices = document.getElementById('btnVertices');
            if (btnVertices) {
                btnVertices.addEventListener('click', alternarModoVertices);
            }

            // Event listener do checkbox da ortofoto
            const chkOrtofoto = document.getElementById('chkOrtofoto');
            if (chkOrtofoto) {
                chkOrtofoto.addEventListener('change', function() {
                    alternarOrtofoto(this.checked);
                });
            }

            // Event listener do botão Associar
            const btnAssociar = document.getElementById('btnAssociar');
            if (btnAssociar) {
                btnAssociar.addEventListener('click', associarDesenho);
            }
            
            // Event listener do botão Desassociar
            const btnDesassociar = document.getElementById('btnDesassociar');
            if (btnDesassociar) {
                btnDesassociar.addEventListener('click', desassociarDesenho);
            }

            // Clique no mapa remove destaque
            setTimeout(function() {
                if (map2Instance) {
                    map2Instance.addListener('click', function(e) {
                        // Se não estiver em nenhum modo, limpar seleções de associação
                        if (!modoAtivo) {
                            limparSelecaoAssociacaoMapa();
                            limparSelecaoAssociacaoTabela();
                            verificarBotaoAssociar();
                            verificarBotaoDesassociar();
                        } else if (modoAtivo && !objetoDestacado.poligono) {
                            // Se estiver em modo ativo, remover destaque
                            removerDestaque();
                        }
                    });
                }
            }, 2000);
        });
    </script>
</body>

</html>