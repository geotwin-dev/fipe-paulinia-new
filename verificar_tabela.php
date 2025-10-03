<?php
include("connection.php");

echo "<h2>Verificação da Tabela pdfs_config</h2>";

try {
    // Verificar estrutura da tabela
    $stmt = $pdo->query("DESCRIBE pdfs_config");
    $estrutura = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>📋 Estrutura da tabela:</h3>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($estrutura as $campo) {
        echo "<tr>";
        echo "<td>" . $campo['Field'] . "</td>";
        echo "<td>" . $campo['Type'] . "</td>";
        echo "<td>" . $campo['Null'] . "</td>";
        echo "<td>" . $campo['Key'] . "</td>";
        echo "<td>" . $campo['Default'] . "</td>";
        echo "<td>" . $campo['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Contar registros
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM pdfs_config");
    $total = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p><strong>Total de registros:</strong> " . $total['total'] . "</p>";
    
    // Listar alguns registros
    $stmt = $pdo->query("SELECT arquivo, loteamento, travado, rotacao FROM pdfs_config LIMIT 5");
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>📋 Alguns registros:</h3>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Arquivo</th><th>Loteamento</th><th>Travado</th><th>Rotação</th></tr>";
    foreach ($registros as $reg) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($reg['arquivo']) . "</td>";
        echo "<td>" . htmlspecialchars($reg['loteamento']) . "</td>";
        echo "<td>" . $reg['travado'] . "</td>";
        echo "<td>" . $reg['rotacao'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Buscar especificamente o arquivo que você mencionou
    $arquivo = "55 Jd Monte Alegre 3 Etapa.pdf";
    $stmt = $pdo->prepare("SELECT * FROM pdfs_config WHERE arquivo = :arquivo");
    $stmt->execute(['arquivo' => $arquivo]);
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($config) {
        echo "<h3>✅ Configuração específica encontrada:</h3>";
        echo "<pre>";
        print_r($config);
        echo "</pre>";
    } else {
        echo "<h3>❌ Configuração específica não encontrada</h3>";
        
        // Buscar arquivos similares
        $stmt = $pdo->prepare("SELECT arquivo FROM pdfs_config WHERE arquivo LIKE :arquivo");
        $stmt->execute(['arquivo' => '%55 Jd%']);
        $similares = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($similares) {
            echo "<h3>🔍 Arquivos similares encontrados:</h3>";
            foreach ($similares as $sim) {
                echo "<p>" . htmlspecialchars($sim['arquivo']) . "</p>";
            }
        }
    }
    
} catch (Exception $e) {
    echo "<h3>❌ Erro:</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>

