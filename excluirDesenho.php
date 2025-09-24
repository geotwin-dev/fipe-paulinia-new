<?php
// excluirDesenho.php
header('Content-Type: application/json');
include 'connection.php'; // conexão PDO

$cliente   = $_POST['cliente']   ?? '';
$ortofoto  = $_POST['ortofoto']  ?? '';
$identificador = $_POST['identificador'] ?? '';
$tipo      = $_POST['tipo']      ?? '';

if (empty($ortofoto) || empty($identificador) || empty($tipo)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Parâmetros incompletos']);
    exit;
}

try {
    if ($tipo === 'poligono') {
        // 1. Primeiro descobre o ID da quadra baseado no id_desenho (que contém o identificador do usuário)
        $sql = "SELECT id FROM desenhos WHERE id = :identificador AND quadricula = :quadricula AND tipo = 'poligono'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':identificador' => $identificador,
            ':quadricula' => $ortofoto
        ]);
        $quadra = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$quadra) {
            echo json_encode(['status' => 'erro', 'mensagem' => 'Quadra não encontrada', 'dados' => [$identificador, $ortofoto]]);
            exit;
        }
        
        $idQuadra = $quadra['id'];
        
        // 2. Apaga todas as linhas que pertencem a esta quadra (id_desenho = id da quadra)
        $sqlDeleteLinhas = "DELETE FROM desenhos WHERE id_desenho = :id_quadra AND quadricula = :quadricula AND tipo = 'polilinha'";
        $stmtDelLinhas = $pdo->prepare($sqlDeleteLinhas);
        $stmtDelLinhas->execute([
            ':id_quadra' => $idQuadra,
            ':quadricula' => $ortofoto
        ]);
        $linhasRemovidas = $stmtDelLinhas->rowCount();
        
        // 2b. Apaga todos os marcadores que pertencem a esta quadra (id_desenho = id da quadra, camada = 'marcador')
        $sqlDeleteMarcadores = "DELETE FROM desenhos WHERE id_desenho = :id_quadra AND quadricula = :quadricula AND camada = 'marcador'";
        $stmtDelMarcadores = $pdo->prepare($sqlDeleteMarcadores);
        $stmtDelMarcadores->execute([
            ':id_quadra' => $idQuadra,
            ':quadricula' => $ortofoto
        ]);
        $marcadoresRemovidos = $stmtDelMarcadores->rowCount();
        
        // 3. Apaga a quadra pelo ID encontrado
        $sqlDeleteQuadra = "DELETE FROM desenhos WHERE id = :id_quadra AND quadricula = :quadricula AND tipo = 'poligono'";
        $stmtDelQuadra = $pdo->prepare($sqlDeleteQuadra);
        $stmtDelQuadra->execute([
            ':id_quadra' => $idQuadra,
            ':quadricula' => $ortofoto
        ]);
        $quadraRemovida = $stmtDelQuadra->rowCount();
        
        if ($quadraRemovida > 0) {
            echo json_encode([
                'status' => 'sucesso',
                'mensagem' => "Quadra removida com sucesso. Linhas removidas: $linhasRemovidas, Marcadores removidos: $marcadoresRemovidos",
                'registros_afetados' => $linhasRemovidas + $marcadoresRemovidos + $quadraRemovida
            ]);
        } else {
            echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao remover quadra']);
        }
        
    } elseif ($tipo === 'polilinha') {
        // Para linhas, o identificador já é o ID da linha
        $sqlDelete = "DELETE FROM desenhos WHERE id = :id AND quadricula = :quadricula AND tipo = 'polilinha'";
        $stmtDel = $pdo->prepare($sqlDelete);
        $stmtDel->execute([
            ':id' => $identificador,
            ':quadricula' => $ortofoto
        ]);
        $linhaRemovida = $stmtDel->rowCount();
        
        if ($linhaRemovida > 0) {
            echo json_encode([
                'status' => 'sucesso',
                'mensagem' => 'Linha removida com sucesso',
                'registros_afetados' => $linhaRemovida
            ]);
        } else {
            echo json_encode(['status' => 'erro', 'mensagem' => 'Linha não encontrada']);
        }
        
    } else {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Tipo inválido', 'tipo' => $tipo]);
    }

} catch (PDOException $e) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro no banco: ' . $e->getMessage()]);
}
?>
