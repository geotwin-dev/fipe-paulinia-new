<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'connection.php';
session_start();

try {
	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
		echo json_encode(['status' => 'erro', 'mensagem' => 'Método não permitido.'], JSON_UNESCAPED_UNICODE);
		exit;
	}

	$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
	$endereco = isset($_POST['endereco']) ? trim($_POST['endereco']) : '';
	$numero = isset($_POST['numero']) ? trim($_POST['numero']) : '';
	$complemento = isset($_POST['complemento']) ? trim($_POST['complemento']) : '';
	$observacao = isset($_POST['observacao']) ? trim($_POST['observacao']) : '';

	if ($id <= 0) {
		echo json_encode(['status' => 'erro', 'mensagem' => 'ID inválido.'], JSON_UNESCAPED_UNICODE);
		exit;
	}
	if ($endereco === '') {
		echo json_encode(['status' => 'erro', 'mensagem' => 'O campo endereço é obrigatório.'], JSON_UNESCAPED_UNICODE);
		exit;
	}

	if (!$pdo) {
		echo json_encode(['status' => 'erro', 'mensagem' => 'Conexão com banco de dados não disponível.'], JSON_UNESCAPED_UNICODE);
		exit;
	}

	// Obtém o nome do usuário logado
	$usuarioLogado = null;
	if (isset($_SESSION['usuario']) && is_array($_SESSION['usuario']) && isset($_SESSION['usuario'][0])) {
		$usuarioLogado = trim($_SESSION['usuario'][0]);
	} elseif (isset($_SESSION['usuario']) && is_string($_SESSION['usuario'])) {
		$usuarioLogado = trim($_SESSION['usuario']);
	}

	$sql = "UPDATE enderecos_alternativos 
	        SET endereco = :endereco, numero = :numero, complemento = :complemento, observacao = :observacao,
	            alterado = NOW(), usuario2 = :usuario2
	        WHERE id = :id AND status = 1";

	$stmt = $pdo->prepare($sql);
	$stmt->bindValue(':endereco', $endereco, PDO::PARAM_STR);
	$stmt->bindValue(':numero', $numero, PDO::PARAM_STR);
	$stmt->bindValue(':complemento', $complemento, PDO::PARAM_STR);
	$stmt->bindValue(':observacao', $observacao, PDO::PARAM_STR);
	$stmt->bindValue(':usuario2', $usuarioLogado, PDO::PARAM_STR);
	$stmt->bindValue(':id', $id, PDO::PARAM_INT);
	$stmt->execute();

	if ($stmt->rowCount() < 1) {
		echo json_encode(['status' => 'erro', 'mensagem' => 'Registro não encontrado ou não foi possível atualizar.'], JSON_UNESCAPED_UNICODE);
		exit;
	}

	echo json_encode([
		'status' => 'sucesso',
		'id' => $id
	], JSON_UNESCAPED_UNICODE);
	exit;
} catch (PDOException $e) {
	error_log('Erro PDO atualizarEnderecoAlternativo: ' . $e->getMessage());
	echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao atualizar registro.'], JSON_UNESCAPED_UNICODE);
	exit;
} catch (Exception $e) {
	error_log('Exceção atualizarEnderecoAlternativo: ' . $e->getMessage());
	echo json_encode(['status' => 'erro', 'mensagem' => 'Exceção: '.$e->getMessage()], JSON_UNESCAPED_UNICODE);
	exit;
}


