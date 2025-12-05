<?php
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Usuário não autenticado'
    ]);
    exit();
}

header('Content-Type: application/json; charset=utf-8');

// Incluir arquivo de conexão
include("connection.php");

// Verificar se a conexão foi estabelecida
if ($pdo === null) {
    echo json_encode([
        'success' => false,
        'message' => 'Falha na conexão com o banco de dados'
    ]);
    exit();
}

try {
    // Verificar se o método é POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido');
    }

    // Verificar se o parâmetro quarteirao foi enviado
    if (!isset($_POST['quarteirao'])) {
        throw new Exception('Parâmetro quarteirao é obrigatório');
    }

    $quarteirao = $_POST['quarteirao'];

    // Preparar a query
    $sql = "SELECT inscricao, imob_id, cnpj, bairro,nome_loteamento, cara_quarteirao, quadra, lote, logradouro, numero, zona, cat_via, area_terreno, tipo_utilizacao, area_construida_a, utilizacao_area_a
            FROM cadastro 
            WHERE cara_quarteirao = :quarteirao
            ORDER BY quadra, lote";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':quarteirao', $quarteirao, PDO::PARAM_STR);
    $stmt->execute();

    // Buscar todos os resultados
    $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Retornar os dados
    echo json_encode([
        'success' => true,
        'dados' => $dados,
        'total' => count($dados)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

