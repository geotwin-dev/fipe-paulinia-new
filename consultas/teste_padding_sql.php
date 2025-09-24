<?php
// Script para testar o padding de zeros direto no banco
session_start();
$_SESSION['usuario'] = 'teste';

include("../connection.php");

echo "<!DOCTYPE html>
<html lang='pt-br'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Teste Padding SQL</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .success { background-color: #e6ffe6; }
        .error { background-color: #ffe6e6; }
        .warning { background-color: #fff3cd; }
        .query { background: #f0f0f0; padding: 10px; margin: 10px 0; font-family: monospace; border-radius: 3px; }
    </style>
</head>
<body>";

echo "<h1>🧪 Teste de Padding SQL - Tabela Desenhos</h1>";

// Verificar se existe a tabela desenhos
echo "<h2>1. Verificação da Tabela</h2>";
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'desenhos'");
    $tabela_existe = $stmt->rowCount() > 0;
    
    if ($tabela_existe) {
        echo "<p class='success'>✅ Tabela 'desenhos' encontrada</p>";
        
        // Mostrar estrutura da tabela
        $stmt = $pdo->query("DESCRIBE desenhos");
        $estrutura = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>Estrutura da Tabela:</h3>";
        echo "<table><tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        foreach ($estrutura as $campo) {
            echo "<tr>";
            echo "<td>{$campo['Field']}</td>";
            echo "<td>{$campo['Type']}</td>";
            echo "<td>{$campo['Null']}</td>";
            echo "<td>{$campo['Key']}</td>";
            echo "<td>{$campo['Default']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
    } else {
        echo "<p class='error'>❌ Tabela 'desenhos' não encontrada!</p>";
        echo "</body></html>";
        exit;
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Erro ao verificar tabela: " . $e->getMessage() . "</p>";
    echo "</body></html>";
    exit;
}

// Contar total de registros
echo "<h2>2. Contagem de Registros</h2>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM desenhos");
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "<p>Total de registros na tabela desenhos: <strong>$total</strong></p>";
    
    if ($total == 0) {
        echo "<p class='warning'>⚠️ A tabela está vazia! Nenhum registro para testar.</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Erro ao contar registros: " . $e->getMessage() . "</p>";
}

// Mostrar primeiros registros
echo "<h2>3. Primeiros Registros da Tabela</h2>";
try {
    $stmt = $pdo->query("SELECT id, quarteirao, quadra, lote, tipo FROM desenhos LIMIT 10");
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($registros)) {
        echo "<table>";
        echo "<tr><th>ID</th><th>Quarteirao</th><th>Quadra</th><th>Lote</th><th>Tipo</th></tr>";
        foreach ($registros as $reg) {
            echo "<tr>";
            echo "<td>{$reg['id']}</td>";
            echo "<td>'{$reg['quarteirao']}'</td>";
            echo "<td>'{$reg['quadra']}'</td>";
            echo "<td>'{$reg['lote']}'</td>";
            echo "<td>{$reg['tipo']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='warning'>Nenhum registro encontrado</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Erro ao buscar registros: " . $e->getMessage() . "</p>";
}

// Testar diferentes formatos de busca
echo "<h2>4. Teste de Busca com Diferentes Formatos</h2>";

$testes_busca = [
    ['quarteirao' => '1', 'quadra' => '1', 'lote' => '1'],
    ['quarteirao' => '01', 'quadra' => '01', 'lote' => '01'],
    ['quarteirao' => '001', 'quadra' => '001', 'lote' => '001'],
    ['quarteirao' => '0001', 'quadra' => '0001', 'lote' => '0001'],
];

foreach ($testes_busca as $i => $teste) {
    echo "<h3>Teste " . ($i + 1) . ": Buscando {$teste['quarteirao']}/{$teste['quadra']}/{$teste['lote']}</h3>";
    
    // Query original (sem padding)
    $sql_original = "SELECT COUNT(*) as total FROM desenhos 
                     WHERE quarteirao = '{$teste['quarteirao']}' 
                     AND quadra = '{$teste['quadra']}' 
                     AND lote = '{$teste['lote']}'";
    
    echo "<div class='query'>Busca sem padding:<br>$sql_original</div>";
    
    try {
        $stmt = $pdo->query($sql_original);
        $resultado_original = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        echo "<p>Resultados encontrados (sem padding): <strong>$resultado_original</strong></p>";
    } catch (Exception $e) {
        echo "<p class='error'>Erro na busca sem padding: " . $e->getMessage() . "</p>";
    }
    
    // Query com padding
    $quarteirao_pad = str_pad($teste['quarteirao'], 4, '0', STR_PAD_LEFT);
    $quadra_pad = str_pad($teste['quadra'], 4, '0', STR_PAD_LEFT);
    $lote_pad = str_pad($teste['lote'], 4, '0', STR_PAD_LEFT);
    
    $sql_padding = "SELECT COUNT(*) as total FROM desenhos 
                    WHERE quarteirao = '$quarteirao_pad' 
                    AND quadra = '$quadra_pad' 
                    AND lote = '$lote_pad'";
    
    echo "<div class='query'>Busca com padding (0001 format):<br>$sql_padding</div>";
    
    try {
        $stmt = $pdo->query($sql_padding);
        $resultado_padding = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        echo "<p>Resultados encontrados (com padding): <strong>$resultado_padding</strong></p>";
        
        if ($resultado_padding > 0) {
            echo "<p class='success'>✅ Encontrou registros com padding!</p>";
            
            // Mostrar os registros encontrados
            $sql_detalhes = "SELECT id, quarteirao, quadra, lote, tipo FROM desenhos 
                            WHERE quarteirao = '$quarteirao_pad' 
                            AND quadra = '$quadra_pad' 
                            AND lote = '$lote_pad' 
                            LIMIT 3";
            
            $stmt = $pdo->query($sql_detalhes);
            $detalhes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<h4>Registros encontrados:</h4>";
            echo "<table>";
            echo "<tr><th>ID</th><th>Quarteirao</th><th>Quadra</th><th>Lote</th><th>Tipo</th></tr>";
            foreach ($detalhes as $det) {
                echo "<tr class='success'>";
                echo "<td>{$det['id']}</td>";
                echo "<td>'{$det['quarteirao']}'</td>";
                echo "<td>'{$det['quadra']}'</td>";
                echo "<td>'{$det['lote']}'</td>";
                echo "<td>{$det['tipo']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
    } catch (Exception $e) {
        echo "<p class='error'>Erro na busca com padding: " . $e->getMessage() . "</p>";
    }
    
    echo "<hr>";
}

// Análise dos formatos na tabela
echo "<h2>5. Análise dos Formatos na Tabela</h2>";

try {
    // Verificar quais formatos existem na tabela
    $sql_analise = "SELECT 
        LENGTH(quarteirao) as len_quarteirao,
        LENGTH(quadra) as len_quadra, 
        LENGTH(lote) as len_lote,
        COUNT(*) as quantidade,
        MIN(quarteirao) as exemplo_quarteirao,
        MIN(quadra) as exemplo_quadra,
        MIN(lote) as exemplo_lote
        FROM desenhos 
        GROUP BY LENGTH(quarteirao), LENGTH(quadra), LENGTH(lote)
        ORDER BY quantidade DESC";
    
    echo "<div class='query'>Análise dos tamanhos dos campos:<br>$sql_analise</div>";
    
    $stmt = $pdo->query($sql_analise);
    $analise = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($analise)) {
        echo "<table>";
        echo "<tr><th>Tamanho Quarteirão</th><th>Tamanho Quadra</th><th>Tamanho Lote</th><th>Quantidade</th><th>Exemplo Q</th><th>Exemplo Qd</th><th>Exemplo L</th></tr>";
        foreach ($analise as $item) {
            echo "<tr>";
            echo "<td>{$item['len_quarteirao']}</td>";
            echo "<td>{$item['len_quadra']}</td>";
            echo "<td>{$item['len_lote']}</td>";
            echo "<td><strong>{$item['quantidade']}</strong></td>";
            echo "<td>'{$item['exemplo_quarteirao']}'</td>";
            echo "<td>'{$item['exemplo_quadra']}'</td>";
            echo "<td>'{$item['exemplo_lote']}'</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<p><strong>Conclusão:</strong> Com base nesta análise, você pode ver qual formato predomina na sua tabela e ajustar o padding conforme necessário.</p>";
    }
    
} catch (Exception $e) {
    echo "<p class='error'>Erro na análise: " . $e->getMessage() . "</p>";
}

echo "<h2>6. Recomendações</h2>";
echo "<div style='background: #f0f8ff; padding: 15px; border-radius: 5px;'>";
echo "<p><strong>📋 Como usar estas informações:</strong></p>";
echo "<ol>";
echo "<li><strong>Verifique a seção 5</strong> para ver qual formato predomina na sua tabela</li>";
echo "<li><strong>Se a maioria tem 4 dígitos (0001, 0002, etc.)</strong> - o padding está correto</li>";
echo "<li><strong>Se a maioria tem 1-3 dígitos (1, 2, 123, etc.)</strong> - talvez precise ajustar a busca</li>";
echo "<li><strong>Copie as queries da seção 4</strong> e teste no phpMyAdmin para confirmar</li>";
echo "</ol>";
echo "</div>";

echo "</body></html>";
?>
