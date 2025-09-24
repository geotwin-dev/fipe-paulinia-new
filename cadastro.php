<?php

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro</title>

    <!--Conexão com fonts do Google-->
    <link href='https://fonts.googleapis.com/css?family=Muli' rel='stylesheet'>
    <!--Conexão com jquery-->
    <script src="https://code.jquery.com/jquery-3.6.1.min.js" integrity="sha256-o88AwQnZB+VDvE9tvIXrMQaPlFFSUTR+nldQm1LuPXQ=" crossorigin="anonymous"></script>
    <!--Bootstrap CSS only -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-Zenh87qX5JnK2Jl0vWa8Ck2rdkQ2Bzep5IDxbcnCeuOxjzrPF/et3URy9Bv1WTRi" crossorigin="anonymous">

    <style>
        #divTags {
            position: absolute;
            top: 25px;
            right: 30px;
        }

        .tagSobre {
            float: right;
            text-decoration: none;
            margin-left: 10px;
        }

        .svgIcon {
            fill: rgba(187, 187, 187, 0.7);
        }

        .svgIcon:hover {
            fill: rgba(255, 255, 255, 0.7);
        }

        html,
        body {
            width: 100%;
            height: 100%;
            margin: 0;
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #container {
            width: 50%;
            min-width: 450px;
            max-width: 700px;
            height: 520px;
            border-radius: 4px;
            box-shadow: 2px 2px 2px 1px rgba(0, 0, 0, 0.2);
            background-color: white;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #subcontainer {
            width: 400px;
            background-color: white;
        }

        #login {
            font-size: 32px;
            color: #025E73;
            font-style: "regular";
            font-weight: bold;
        }

        #subtitulo {
            font-size: 16px;
            color: #505050;
        }

        #alert {
            color: rgba(217, 98, 98);
        }

        #formulario {
            margin-top: 20px;
        }

        #field {
            display: grid;
            margin-bottom: 5px;
        }

        #field input {
            padding-left: 20px;

        }

        #titulo3 {
            margin-top: 20px;
            margin-bottom: 20px;
            font-size: 16px;
        }

        #titulo3 a {
            color: #037F8C;
            font-weight: bold;
        }

        #bot_submit {
            border: none;
            font-size: 16px;
            background-color: #037F8C;
            color: white;
            height: 40px;
            border-radius: 3px;
            font-weight: bold;
            border-style: none;
        }

        .label_inputs {
            color: #505050;
            font-size: 14px;
            font-weight: bold;
        }

        .inputs_cad {
            height: 40px;
            border: 1px solid #DADCE0;
            border-radius: 3px;
        }

        .inputs_cad:focus {
            outline: 2px solid #037F8C;
            border-style: none;
        }

        .bg_video {
            position: absolute;
            right: 0;
            bottom: 0;
            min-width: 100%;
            min-height: 100%;
            width: auto;
            height: auto;
            z-index: -1000;
        }

        #log_adm {
            display: none;
            position: absolute;
            top: 0;
            left: 30px;
            background-color: white;
        }

        #sublog_adm {
            background-color: white;
        }

        #sublog_adm button {
            padding: 0;
            font-size: 12px;
            width: 40px;
            border: none;
            background-color: #037F8C;
            color: white;
            border-radius: 3px;
            font-weight: bold;
            border-style: none;
        }

        #adminfield {
            display: grid;
            font-size: 12px;
        }

        #adminfield input {
            width: 80px;
            height: 20px;
        }

        .btn_voltar {
            position: absolute;
            top: 19%;
            left: 27%;
            border: none;
            background-color: white;
        }

        #alertas {
            text-align: center;
        }

        #titulo_cliente {
            position: absolute;
            width: 90%;
            height: 100px;
            top: 2%;
            left: 2%;
            font-size: 25px;
            font-weight: bold;
            color: white;
            opacity: 0.7;
        }

        #mostrar-senha {
            position: absolute;
            top: 0;
            right: 0;
            width: 40px;
            height: 100%;
            border: none;
            background-color: transparent;
            cursor: pointer;
        }
    </style>
</head>

<body>
    <video autoplay loop class="bg_video" muted>
        <source src="video/video3.mp4" type="video/mp4">
        <source src="video/video3.webm" type="video/webm">
    </video>
    <div id="container" style="height: 700px; font-size: small;">
        <div id="subcontainer">
            <div id="titulo">
                <span id="login">Crie sua Conta</span>
            </div>
            <div id="titulo2">
                <span id="subtitulo">Informe seus dados para criar uma conta na plataforma</span>
            </div>
            <div id="formulario">
                <form method="POST" action="cadastro_processa.php">
                    <div id="field">
                        <label class="label_inputs" id="label_nome" for="nome">Nome</label>
                        <input class="inputs_cad" style="height: 30px;" id="nome" name="nome" type="text" placeholder="    Digite seu nome completo" required>
                    </div>
                    <div id="field">
                        <label class="label_inputs" id="label_email" for="email">Email</label>
                        <input class="inputs_cad" style="height: 30px;" id="email" name="email" type="email" placeholder="    Digite seu email" required>
                    </div>
                    <div id="field">
                        <label class="label_inputs" id="label_usuario" for="usuario">Login</label>
                        <input class="inputs_cad" style="height: 30px;" id="usuario" name="usuario" type="text" placeholder="    Crie um usuário para login" required>
                    </div>
                    <div id="field">
                        <label class="label_inputs" id="label_senha" for="senha">Senha</label>
                        <input class="inputs_cad" style="height: 30px;" id="senha" name="senha" type="password" placeholder="    Crie uma senha" required>
                    </div>
                    <div id="field">
                        <label class="label_inputs" id="label_senha" for="confirma">Confirmar senha</label>
                        <input class="inputs_cad" style="height: 30px;" id="confirma" name="confirma" type="password" placeholder="    Digite a senha novamente" required>
                    </div>
                    <div style="margin-top: 35px;" id="field">
                        <button id="bot_submit" type="submit">Cadastrar</button>
                    </div>
                </form>
                <div id="titulo3">
                    <center>
                        <div>
                            <span>Já possui uma conta? <a href="index.php">Faça Login</a></span>
                        </div>
                    </center>
                </div>
            </div>
        </div>
    </div>
</body>

<script>
    var password = document.getElementById("senha");
    var confirm_password = document.getElementById("confirma");

    function validatePassword() {
        if (password.value != confirm_password.value) {
            confirm_password.setCustomValidity("Senhas diferentes!");
        } else {
            confirm_password.setCustomValidity('');
        }
    }

    password.onchange = validatePassword;
    confirm_password.onkeyup = validatePassword;
</script>

</html>