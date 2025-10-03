<?php
header('Content-Type: application/json');
require_once 'connection.php';

$response = ['success' => false, 'message' => ''];

$input = file_get_contents('php://input');
error_log('🔍 Input recebido: ' . $input);

$data = json_decode($input, true);
error_log('🔍 Dados decodificados: ' . print_r($data, true));

if (!isset($data['id'])) {
    $response['message'] = 'ID do marcador não fornecido.';
    error_log('❌ ID não fornecido');
    echo json_encode($response);
    exit();
}

$id = $data['id'];
error_log('🔍 ID extraído: ' . $id . ' (tipo: ' . gettype($id) . ')');

try {
    error_log('🔍 Tentando conectar ao banco...');
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    error_log('✅ Conexão com banco estabelecida');

    // Ocultar marcador usando ID
    $sql = "UPDATE marcadores_pdf SET visibilidade = 0 WHERE id = :id";
    error_log('🔍 SQL: ' . $sql);
    error_log('🔍 ID para bind: ' . $id);
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $rowCount = $stmt->rowCount();
    error_log('🔍 Linhas afetadas: ' . $rowCount);

    if ($rowCount > 0) {
        $response['success'] = true;
        $response['message'] = 'Marcador ocultado com sucesso.';
        error_log('✅ Marcador ocultado com sucesso, ID: ' . $id);
    } else {
        $response['message'] = 'Nenhum marcador encontrado com o ID fornecido.';
        error_log('⚠️ Nenhum marcador encontrado com ID: ' . $id);
    }

} catch (PDOException $e) {
    $response['message'] = 'Erro no banco de dados: ' . $e->getMessage();
    error_log('❌ Erro PDO: ' . $e->getMessage());
    error_log('❌ Stack trace: ' . $e->getTraceAsString());
} catch (Exception $e) {
    $response['message'] = 'Erro inesperado: ' . $e->getMessage();
    error_log('❌ Erro geral: ' . $e->getMessage());
    error_log('❌ Stack trace: ' . $e->getTraceAsString());
}

error_log('🔍 Resposta final: ' . json_encode($response));
echo json_encode($response);
?>
