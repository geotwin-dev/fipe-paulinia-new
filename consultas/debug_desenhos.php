<?php
session_start();

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['error' => 'Usuário não autenticado']);
    exit();
}

include("../connection.php");

try {
    echo "<h3>Debug da Tabela Desenhos</h3>";
    
    // Contar total de registros
    $total_stmt = $pdo->query("SELECT COUNT(*) as total FROM desenhos");
    $total = $total_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "<p><strong>Total de registros na tabela desenhos:</strong> $total</p>";
    
    // Verificar tipos disponíveis
    echo "<h4>Tipos de desenhos disponíveis:</h4>";
    $tipos_stmt = $pdo->query("SELECT DISTINCT tipo, COUNT(*) as total FROM desenhos GROUP BY tipo ORDER BY total DESC");
    echo "<ul>";
    while ($tipo_row = $tipos_stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<li><strong>{$tipo_row['tipo']}</strong>: {$tipo_row['total']} registros</li>";
    }
    echo "</ul>";
    
    // Verificar quarteirões únicos
    echo "<h4>Quarteirões disponíveis (primeiros 20):</h4>";
    $quarteiroes_stmt = $pdo->query("SELECT DISTINCT quarteirao, COUNT(*) as total FROM desenhos GROUP BY quarteirao ORDER BY total DESC LIMIT 20");
    echo "<ul>";
    while ($q = $quarteiroes_stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<li>Quarteirão <strong>{$q['quarteirao']}</strong>: {$q['total']} registros</li>";
    }
    echo "</ul>";
    
    // Amostras da tabela
    echo "<h4>Amostras de registros (10 primeiros):</h4>";
    $sample_stmt = $pdo->query("SELECT id, quarteirao, quadra, lote, tipo, LEFT(coordenadas, 50) as coord_sample FROM desenhos LIMIT 10");
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Quarteirão</th><th>Quadra</th><th>Lote</th><th>Tipo</th><th>Coordenadas (sample)</th></tr>";
    while ($sample = $sample_stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>";
        echo "<td>{$sample['id']}</td>";
        echo "<td>{$sample['quarteirao']}</td>";
        echo "<td>{$sample['quadra']}</td>";
        echo "<td>{$sample['lote']}</td>";
        echo "<td>{$sample['tipo']}</td>";
        echo "<td>{$sample['coord_sample']}...</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Verificar se existe quarteirão 31 (do exemplo)
    echo "<h4>Verificando quarteirão 31 especificamente:</h4>";
    $q31_stmt = $pdo->query("SELECT quarteirao, quadra, lote, tipo FROM desenhos WHERE quarteirao = '31' OR quarteirao = 31");
    $count31 = 0;
    while ($q31 = $q31_stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<p>Quarteirão 31: {$q31['quarteirao']}/{$q31['quadra']}/{$q31['lote']} - {$q31['tipo']}</p>";
        $count31++;
    }
    if ($count31 == 0) {
        echo "<p><em>Nenhum registro encontrado para quarteirão 31</em></p>";
    }
    
    // Verificar quarteirão 1643 (do exemplo dos logs)
    echo "<h4>Verificando quarteirão 1643 especificamente:</h4>";
    $q1643_stmt = $pdo->query("SELECT quarteirao, quadra, lote, tipo FROM desenhos WHERE quarteirao = '1643' OR quarteirao = 1643");
    $count1643 = 0;
    while ($q1643 = $q1643_stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<p>Quarteirão 1643: {$q1643['quarteirao']}/{$q1643['quadra']}/{$q1643['lote']} - {$q1643['tipo']}</p>";
        $count1643++;
    }
    if ($count1643 == 0) {
        echo "<p><em>Nenhum registro encontrado para quarteirão 1643</em></p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Erro: " . $e->getMessage() . "</p>";
}
?>
