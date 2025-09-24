<?php
// Define o cabeçalho para retornar JSON
header('Content-Type: application/json');

// Inclui a conexão com o banco de dados
require_once 'connection.php';

// Recebe os dados via POST
$quarteirao = isset($_POST['quarteirao']) ? $_POST['quarteirao'] : '';
$quadra = isset($_POST['quadra']) ? $_POST['quadra'] : '';
$lote = isset($_POST['lote']) ? $_POST['lote'] : '';

// Log para debug
error_log("Busca marcador - Quarteirão: $quarteirao, Quadra: $quadra, Lote: $lote");

$resultado = ['erro' => 'Parâmetros inválidos'];

// Verifica se todos os parâmetros foram fornecidos
if (!empty($quarteirao) && !empty($quadra) && !empty($lote)) {
    
    // Verifica se a conexão com o banco está ativa
    if ($pdo) {
        try {
            // Consulta SQL para buscar dados do cadastro
            $stmt = $pdo->prepare("SELECT * FROM cadastro WHERE cara_quarteirao = ? AND quadra = ? AND lote = ?");
            $stmt->execute([$quarteirao, $quadra, $lote]);
            
            $dados = $stmt->fetch(PDO::FETCH_ASSOC);
            
            error_log("Query executada - Registros encontrados: " . ($dados ? 'SIM' : 'NÃO'));
            
            if ($dados) {
                $resultado = $dados;
                error_log("Dados retornados: " . json_encode($dados));
            } else {
                $resultado = ['erro' => 'Nenhum registro encontrado'];
                error_log("Nenhum registro encontrado para: cara_quarteirao=$quarteirao, quadra=$quadra, lote=$lote");
            }
            
        } catch (PDOException $e) {
            error_log("Erro na consulta: " . $e->getMessage());
            $resultado = ['erro' => 'Erro na consulta ao banco de dados'];
        }
    } else {
        error_log("Conexão com banco não disponível");
        $resultado = ['erro' => 'Conexão com banco de dados não disponível'];
    }
}

echo json_encode($resultado);
?>
