<?php
// Arquivo para testar o buscar_coordenadas.php e ver o debug completo
session_start();

// Simular login
$_SESSION['usuario'] = 'teste_debug';

echo "<!DOCTYPE html>
<html lang='pt-br'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Debug Buscar Coordenadas</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .section { margin-bottom: 30px; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .query { background: #f0f0f0; padding: 10px; margin: 5px 0; border-radius: 3px; font-family: monospace; }
        .error { color: red; }
        .success { color: green; }
        .warning { color: orange; }
        pre { background: #f5f5f5; padding: 10px; overflow-x: auto; }
        .collapsible { cursor: pointer; background: #007bff; color: white; padding: 10px; margin: 5px 0; border: none; border-radius: 3px; }
        .content { display: none; padding: 10px; border: 1px solid #007bff; margin-bottom: 10px; }
    </style>
</head>
<body>";

echo "<h1>🔍 Debug Completo - Buscar Coordenadas</h1>";

// Dados de teste - simular alguns registros do cadastro com diferentes formatos de números
$dados_teste = [
    [
        'id' => 1,
        'cara_quarteirao' => '1', // Teste padding: 1 -> 0001
        'quadra' => '1',         // Teste padding: 1 -> 0001
        'lote' => '1',           // Teste padding: 1 -> 0001
        'nome_pessoa' => 'TESTE SILVA',
        'area_terreno' => '200'
    ],
    [
        'id' => 2,
        'cara_quarteirao' => '12',  // Teste padding: 12 -> 0012
        'quadra' => '2',           // Teste padding: 2 -> 0002
        'lote' => '15',            // Teste padding: 15 -> 0015
        'nome_pessoa' => 'OUTRO TESTE',
        'area_terreno' => '300'
    ],
    [
        'id' => 3,
        'cara_quarteirao' => '123', // Teste padding: 123 -> 0123
        'quadra' => '3',           // Teste padding: 3 -> 0003
        'lote' => '456',           // Teste padding: 456 -> 0456
        'nome_pessoa' => 'TESTE PADDING'
    ],
    [
        'id' => 4,
        'cara_quarteirao' => '1234', // Já tem 4 dígitos: 1234 -> 1234
        'quadra' => '5678',         // Já tem 4 dígitos: 5678 -> 5678
        'lote' => '9012',           // Já tem 4 dígitos: 9012 -> 9012
        'nome_pessoa' => 'JA COM 4 DIGITOS'
    ],
    [
        'id' => 5,
        'cara_quarteirao' => '  7  ', // Com espaços: "  7  " -> trim -> 7 -> 0007
        'quadra' => ' 89 ',          // Com espaços: " 89 " -> trim -> 89 -> 0089
        'lote' => '  123  ',         // Com espaços: "  123  " -> trim -> 123 -> 0123
        'nome_pessoa' => 'COM ESPACOS'
    ],
    [
        'id' => 6,
        'cara_quarteirao' => '', // Campo vazio para testar
        'quadra' => '003',
        'lote' => '003',
        'nome_pessoa' => 'SEM QUARTEIRAO'
    ]
];

echo "<div class='section'>";
echo "<h2>📊 Dados de Entrada (Simulados)</h2>";
echo "<p>Enviando " . count($dados_teste) . " registros de teste para buscar_coordenadas.php</p>";
echo "<p><strong>🔧 Teste de Normalização:</strong> O sistema aplicará padding de zeros à esquerda para 4 dígitos</p>";

// Mostrar tabela de normalização esperada
echo "<table style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
echo "<tr style='background: #f0f0f0;'>";
echo "<th style='border: 1px solid #ddd; padding: 8px;'>Registro</th>";
echo "<th style='border: 1px solid #ddd; padding: 8px;'>Quarteirao Original</th>";
echo "<th style='border: 1px solid #ddd; padding: 8px;'>Quarteirao Normalizado</th>";
echo "<th style='border: 1px solid #ddd; padding: 8px;'>Quadra Original</th>";
echo "<th style='border: 1px solid #ddd; padding: 8px;'>Quadra Normalizada</th>";
echo "<th style='border: 1px solid #ddd; padding: 8px;'>Lote Original</th>";
echo "<th style='border: 1px solid #ddd; padding: 8px;'>Lote Normalizado</th>";
echo "</tr>";

foreach ($dados_teste as $i => $reg) {
    $quarteirao_orig = $reg['cara_quarteirao'];
    $quadra_orig = $reg['quadra'];
    $lote_orig = $reg['lote'];
    
    $quarteirao_norm = str_pad(trim($quarteirao_orig), 4, '0', STR_PAD_LEFT);
    $quadra_norm = str_pad(trim($quadra_orig), 4, '0', STR_PAD_LEFT);
    $lote_norm = str_pad(trim($lote_orig), 4, '0', STR_PAD_LEFT);
    
    echo "<tr>";
    echo "<td style='border: 1px solid #ddd; padding: 8px;'>#" . ($i + 1) . "</td>";
    echo "<td style='border: 1px solid #ddd; padding: 8px;'>'" . htmlspecialchars($quarteirao_orig) . "'</td>";
    echo "<td style='border: 1px solid #ddd; padding: 8px; background: #e6ffe6;'><strong>$quarteirao_norm</strong></td>";
    echo "<td style='border: 1px solid #ddd; padding: 8px;'>'" . htmlspecialchars($quadra_orig) . "'</td>";
    echo "<td style='border: 1px solid #ddd; padding: 8px; background: #e6ffe6;'><strong>$quadra_norm</strong></td>";
    echo "<td style='border: 1px solid #ddd; padding: 8px;'>'" . htmlspecialchars($lote_orig) . "'</td>";
    echo "<td style='border: 1px solid #ddd; padding: 8px; background: #e6ffe6;'><strong>$lote_norm</strong></td>";
    echo "</tr>";
}
echo "</table>";

echo "<pre>" . json_encode($dados_teste, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
echo "</div>";

// Fazer requisição para buscar_coordenadas.php
$dados_json = json_encode(['registros' => $dados_teste]);

echo "<div class='section'>";
echo "<h2>🚀 Fazendo Requisição...</h2>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/fipe-paulinia/consultas/buscar_coordenadas.php');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $dados_json);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
curl_close($ch);

$header = substr($response, 0, $header_size);
$body = substr($response, $header_size);

echo "<p><strong>Status HTTP:</strong> <span class='" . ($http_code == 200 ? 'success' : 'error') . "'>$http_code</span></p>";
echo "<p><strong>Headers da Resposta:</strong></p>";
echo "<pre>" . htmlspecialchars($header) . "</pre>";
echo "</div>";

echo "<div class='section'>";
echo "<h2>📝 Resposta do Servidor</h2>";
echo "<p><strong>Resposta Bruta:</strong></p>";
echo "<pre>" . htmlspecialchars($body) . "</pre>";

// Tentar fazer parse do JSON
$resultado = json_decode($body, true);
if ($resultado) {
    echo "<p class='success'><strong>✅ JSON Válido</strong></p>";
} else {
    echo "<p class='error'><strong>❌ Erro no JSON:</strong> " . json_last_error_msg() . "</p>";
    echo "</div></body></html>";
    exit;
}
echo "</div>";

// Mostrar resumo das estatísticas
if (isset($resultado['stats'])) {
    echo "<div class='section'>";
    echo "<h2>📈 Estatísticas</h2>";
    $stats = $resultado['stats'];
    
    echo "<div style='display: flex; gap: 20px; flex-wrap: wrap;'>";
    foreach ($stats as $chave => $valor) {
        $cor = is_numeric($valor) && $valor > 0 ? 'success' : 'warning';
        echo "<div style='padding: 10px; border: 1px solid #ddd; border-radius: 5px; min-width: 200px;'>";
        echo "<strong>" . ucfirst(str_replace('_', ' ', $chave)) . ":</strong><br>";
        echo "<span class='$cor' style='font-size: 1.2em;'>" . (is_array($valor) ? json_encode($valor, JSON_UNESCAPED_UNICODE) : $valor) . "</span>";
        echo "</div>";
    }
    echo "</div>";
    echo "</div>";
}

// Mostrar queries executadas
if (isset($resultado['debug']['queries_executadas'])) {
    echo "<div class='section'>";
    echo "<h2>🗄️ Queries SQL Executadas</h2>";
    
    $queries = $resultado['debug']['queries_executadas'];
    
    if (empty($queries)) {
        echo "<p class='warning'>⚠️ Nenhuma query foi executada!</p>";
    } else {
        echo "<p>Total de queries executadas: <strong>" . count($queries) . "</strong></p>";
        
        foreach ($queries as $i => $query) {
            echo "<button type='button' class='collapsible' onclick='toggleContent(\"query_$i\")'>
                    Query #" . ($i + 1) . " - Registro #{$query['registro_index']} 
                    (Resultados: {$query['resultados_encontrados']})
                  </button>";
            echo "<div id='query_$i' class='content'>";
            echo "<h4>Query Executável (Cole no seu cliente SQL):</h4>";
            echo "<div class='query'>" . htmlspecialchars($query['sql_executavel']) . "</div>";
            
            echo "<h4>Parâmetros:</h4>";
            echo "<pre>" . json_encode($query['parametros'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
            
            if (isset($query['erro'])) {
                echo "<p class='error'><strong>Erro:</strong> " . htmlspecialchars($query['erro']) . "</p>";
            }
            
            echo "<p><strong>Resultados encontrados:</strong> " . $query['resultados_encontrados'] . "</p>";
            echo "</div>";
        }
    }
    echo "</div>";
}

// Mostrar informações dos registros processados
if (isset($resultado['debug']['registros_debug'])) {
    echo "<div class='section'>";
    echo "<h2>📋 Debug dos Registros</h2>";
    
    foreach ($resultado['debug']['registros_debug'] as $i => $reg_debug) {
        echo "<button type='button' class='collapsible' onclick='toggleContent(\"registro_$i\")'>
                Registro #" . ($reg_debug['indice'] + 1) . " 
                (Coordenadas encontradas: " . ($reg_debug['coordenadas_encontradas'] ?? 0) . ")
              </button>";
        echo "<div id='registro_$i' class='content'>";
        echo "<pre>" . json_encode($reg_debug, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
        echo "</div>";
    }
    echo "</div>";
}

// Mostrar estrutura da tabela desenhos
if (isset($resultado['debug']['estrutura_tabela_desenhos'])) {
    echo "<div class='section'>";
    echo "<h2>🏗️ Estrutura da Tabela 'desenhos'</h2>";
    echo "<button type='button' class='collapsible' onclick='toggleContent(\"estrutura_tabela\")'>
            Ver Estrutura da Tabela
          </button>";
    echo "<div id='estrutura_tabela' class='content'>";
    echo "<pre>" . json_encode($resultado['debug']['estrutura_tabela_desenhos'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
    echo "</div>";
    echo "</div>";
}

// Mostrar exemplos da tabela
if (isset($resultado['debug']['exemplos_tabela_desenhos'])) {
    echo "<div class='section'>";
    echo "<h2>📋 Exemplos da Tabela 'desenhos'</h2>";
    echo "<p>Total de registros na tabela: <strong>" . $resultado['debug']['total_registros_tabela_desenhos'] . "</strong></p>";
    echo "<button type='button' class='collapsible' onclick='toggleContent(\"exemplos_tabela\")'>
            Ver Primeiros 5 Registros da Tabela
          </button>";
    echo "<div id='exemplos_tabela' class='content'>";
    echo "<pre>" . json_encode($resultado['debug']['exemplos_tabela_desenhos'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
    echo "</div>";
    echo "</div>";
}

// Mostrar coordenadas encontradas
if (isset($resultado['coordenadas']) && !empty($resultado['coordenadas'])) {
    echo "<div class='section'>";
    echo "<h2>🎯 Coordenadas Encontradas</h2>";
    echo "<p class='success'>✅ Encontradas " . count($resultado['coordenadas']) . " coordenadas!</p>";
    
    foreach ($resultado['coordenadas'] as $i => $coord) {
        echo "<button type='button' class='collapsible' onclick='toggleContent(\"coord_$i\")'>
                Coordenada #" . ($i + 1) . " - {$coord['quarteirao']}/{$coord['quadra']}/{$coord['lote']} ({$coord['tipo']})
              </button>";
        echo "<div id='coord_$i' class='content'>";
        echo "<pre>" . json_encode($coord, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
        echo "</div>";
    }
    echo "</div>";
} else {
    echo "<div class='section'>";
    echo "<h2>❌ Nenhuma Coordenada Encontrada</h2>";
    echo "<p class='error'>Nenhuma coordenada foi encontrada. Verifique as queries acima para identificar o problema.</p>";
    echo "</div>";
}

echo "<script>
function toggleContent(id) {
    var content = document.getElementById(id);
    if (content.style.display === 'none' || content.style.display === '') {
        content.style.display = 'block';
    } else {
        content.style.display = 'none';
    }
}
</script>";

echo "</body></html>";
?>
