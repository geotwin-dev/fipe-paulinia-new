<?php

session_start();

//obrigatorio chamar a pagina de conexão
require('connection.php');
//inclue a pagina para criptografar as senhas
include 'criptografar_descriptografar.php';

//se o campo login ou senha estiverem vazios entao ele encerra a cessao e nao entra.
if (empty($_POST['username']) || empty($_POST['senha'])) {
  header('Location: logout.php');
  exit();
}

//variavel que armazena o username e a senha que foi colocado nos campos iniciais
$username = $_POST['username'];
$senha = $_POST['senha'];

//código para pegar a senha e criptografar
$encrypt = crypto($senha, 'e');

$time = date('Y-m-d H-i-s');

//========================================================================================

$ip = $_SERVER['REMOTE_ADDR'];

// Verifica se o endereço IP é '::1' (loopback IPv6)
if (strpos($ip, '192.168') !== false) {
  // Ação a ser executada quando o IP é '::1'
  $ip = '187.88.87.178';
}

$api_url = "http://ip-api.com/json/{$ip}";
// Faz uma solicitação GET à API
$response = file_get_contents($api_url);
// Decodifica a resposta JSON em um array associativo
$data = json_decode($response, true);

var_dump($data);

if ($data['country']) {
  // Obtém os detalhes da localização
  $ip_pais = $data['country'];
  $ip_estado = $data['regionName'];
  $ip_cidade = $data['city'];
  $ip_latitude = $data['lat'];
  $ip_longitude = $data['lon'];
  $ip_operadora = $data['org'];
  //$ip_port = $_SERVER['REMOTE_PORT'];
} else {
  $ip_pais = 'site fora do ar';
  $ip_estado = 'site fora do ar';
  $ip_cidade = 'site fora do ar';
  $ip_latitude = 'site fora do ar';
  $ip_longitude = 'site fora do ar';
  $ip_operadora = 'site fora do ar';
}

// Prepara a consulta SQL para registrar o login
$sql_log = $pdo->prepare("INSERT INTO tentativa_login (usuario, ip, resposta, erro_php, pais, estado, cidade, latitude, longitude, operadora) 
VALUES (:usuario, :ip, :resposta, :erro, :pais, :estado, :cidade, :latitude, :longitude, :operadora)");

// Atribui os valores aos parâmetros da consulta
$sql_log->bindValue(':usuario', $username);
$sql_log->bindValue(':pais', $ip_pais);
$sql_log->bindValue(':estado', $ip_estado);
$sql_log->bindValue(':cidade', $ip_cidade);
$sql_log->bindValue(':latitude', $ip_latitude);
$sql_log->bindValue(':longitude', $ip_longitude);
$sql_log->bindValue(':operadora', $ip_operadora);
$sql_log->bindValue(':ip', $ip);
//========================================================================================

$consulta = $pdo->prepare("SELECT * FROM usuarios WHERE login= '{$username}' AND senha= '{$encrypt}'");
$consulta->execute();

$array_dados = [];

foreach ($consulta as $result) {
  array_push($array_dados, $result['id'], $result['nome'], $result['email'], $result['login'], $result['senha'], $result['pass'], $result['data_cadastro'], $result['habilitado'], $result['automa'], $result['admin']);
}

if(empty($array_dados)){
  $resposta = "Usuário ou senha inválidos";
  $sql_log->bindValue(':resposta', $resposta);
  $sql_log->bindValue(':erro', "Sem erro");
  $sql_log->execute();
  
  $_SESSION['nao_autenticado'] = true;
  header('Location: index.php');
  exit();
}

if($array_dados[7] == "false"){
  $resposta = "Usuário não habilitado";
  $sql_log->bindValue(':resposta', $resposta);
  $sql_log->bindValue(':erro', "Sem erro");
  $sql_log->execute();
  
  $_SESSION['nao_habilitado'] = true;
  header('Location: index.php');
  exit();
}

if ($array_dados[7] == "true") {

  //aqui informa a tabela sobre a tentativa de login
  try {

    $resposta = "Verificado e logado com sucesso";

    $sql_log->bindValue(':resposta', $resposta);
    $sql_log->bindValue(':erro', "Sem erro");
    // Executa a consulta
    $sql_log->execute();
  } catch (PDOException $e) {
    echo "Erro: " . $e->getMessage();

    $erro = "sem erro";
    $sql_log->bindValue(':resposta', $resposta);
    $sql_log->bindValue(':erro', $e);
    // Executa a consulta
    $sql_log->execute();
  }

  echo "entrou";

  if (isset($_SESSION['nao_habilitado'])) {
    unset($_SESSION['nao_habilitado']);
  }
  if (isset($_SESSION['nao_autorizado'])) {
    unset($_SESSION['nao_autorizado']);
  }

  $_SESSION['usuario'] = [$array_dados[1], $array_dados[2], $array_dados[8], $array_dados[9]];
  header('Location: painel.php');
  exit();
  
  
} else {

      $resposta = "Não está habilitado";

      //aqui informa a tabela sobre a tentativa de login
      try {

        $sql_log->bindValue(':resposta', $resposta);
        $sql_log->bindValue(':erro', "Sem erro");
        // Executa a consulta
        $sql_log->execute();
      } catch (PDOException $e) {
        echo "Erro: " . $e->getMessage();

        $erro = "sem erro";
        $sql_log->bindValue(':resposta', $resposta);
        $sql_log->bindValue(':erro', $e);
        // Executa a consulta
        $sql_log->execute();
      }
      $_SESSION['nao_autenticado'] = true;
      header('Location: index.php');
      exit();
}
