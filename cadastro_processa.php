<?php

include "criptografar_descriptografar.php";
require_once("connection.php");

//Receber os dados do formulário
$dados = filter_input_array(INPUT_POST, FILTER_DEFAULT);

var_dump($dados);

if ($dados == null) {

    $_SESSION['nao_habilitado'] = true;

    header('Location: logout.php');

    exit();
}

$data2 = date('Y-m-d H-i-s');

$crypto_pass = crypto($dados['senha'], 'e');

$crypto_email = crypto($dados['email'], 'e');
$crypto_user = crypto($dados['usuario'], 'e');

$pass = gerar_senha(10, true, true, true, false);

$array_email = "";
$array_username = "";

$consulta1 = $pdo->prepare("SELECT email FROM usuarios WHERE email = '" . $dados['email'] . "'");
$consulta2 = $pdo->prepare("SELECT login FROM usuarios WHERE login = '" . $dados['usuario'] . "'" .  "AND email = '" . $dados['email'] . "'");

$consulta1->execute();
$consulta2->execute();

foreach ($consulta1 as $result1) {
    $array_email = $result1['email'];
}
foreach ($consulta2 as $result2) {
    $array_username = $result2['login'];
}

if($array_username == $dados['usuario']){
    echo "<script>" .
            "alert('Nome de usuário em uso, escolha outro nome.');" .
            "window.history.back();" .
        "</script>";
    exit();
}

if($array_email == ""){

    $sql_insert = $pdo->prepare("INSERT INTO usuarios (nome, email, login, senha, pass, data_cadastro, habilitado, automa) 
                                VALUES (:a, :b, :c, :d, :e, :f, :g, :h)");

    try {

        $sql_insert->bindValue(':a', $dados['nome']);
        $sql_insert->bindValue(':b', $dados['email']);
        $sql_insert->bindValue(':c', $dados['usuario']);
        $sql_insert->bindValue(':d', $crypto_pass);
        $sql_insert->bindValue(':e', $pass);
        $sql_insert->bindValue(':f', date('Y-m-d H-i-s'));
        $sql_insert->bindValue(':g', 'false');
        $sql_insert->bindValue(':h', 'false');
        $sql_insert->execute();

        session_destroy();

        echo "<script>" .
            "alert('Aguarde sua autorização via email!');" .
            "window.location.href = 'index.php'" .
            "</script>";

    } catch (PDOException $e) {
        
        echo "ERRO-> " . $e->getMessage() . "<br>";
        
        session_destroy();
        
        $_SESSION['nao_habilitado'] = true;

        echo "<script>" .
            "alert('Houve alguma falha! Retornaremos mais tarde!');" .
            "window.location.href = 'index.php'" .
            "</script>";
    }

}else{

    session_destroy();

    $_SESSION['nao_habilitado'] = true;

    echo "<script>" .
        "alert('Usuário já Cadastrado!');" .
        "window.history.back();" .
        "</script>";

}