<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'connection.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

try {
	if (!isset($_GET['id_marcador'])) {
		echo json_encode(['status' => 'erro', 'mensagem' => 'Parâmetro id_marcador ausente.'], JSON_UNESCAPED_UNICODE);
		exit;
	}

	$id_marcador = intval($_GET['id_marcador']);
	if ($id_marcador <= 0) {
		echo json_encode(['status' => 'erro', 'mensagem' => 'Parâmetro id_marcador inválido.'], JSON_UNESCAPED_UNICODE);
		exit;
	}

	if (!$pdo) {
		echo json_encode(['status' => 'erro', 'mensagem' => 'Conexão com banco de dados não disponível.'], JSON_UNESCAPED_UNICODE);
		exit;
	}

	$sql = "SELECT id, id_marcador, endereco, numero, complemento, observacao, status, criado, usuario1, alterado, usuario2
	        FROM enderecos_alternativos 
	        WHERE id_marcador = :id_marcador 
	          AND (status = 1 OR status IS NULL)
	        ORDER BY id DESC";

	$stmt = $pdo->prepare($sql);
	$stmt->bindValue(':id_marcador', $id_marcador, PDO::PARAM_INT);
	$stmt->execute();

	$dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

	echo json_encode(['status' => 'sucesso', 'dados' => $dados], JSON_UNESCAPED_UNICODE);
	exit;
} catch (PDOException $e) {
	error_log('Erro PDO carregarEnderecosAlternativos: ' . $e->getMessage());
	echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao consultar a base de dados.'], JSON_UNESCAPED_UNICODE);
	exit;
} catch (Exception $e) {
	error_log('Exceção carregarEnderecosAlternativos: ' . $e->getMessage());
	echo json_encode(['status' => 'erro', 'mensagem' => 'Exceção: '.$e->getMessage()], JSON_UNESCAPED_UNICODE);
	exit;
}

