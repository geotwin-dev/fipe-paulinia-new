<?php
  
//Dados de conexão com servidor local
$host = "localhost";
$user = "paulinia_pln";
$password = "@uT0m4#*P@uL1Ni4";
$database = "paulinia";

try {
  $pdo = new PDO("mysql:host=$host; dbname=$database; charset=utf8", $user, $password);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  
} catch (PDOException $e) {
  // Não exibe erro diretamente, apenas define uma variável para controle
  $pdo = null;
}

