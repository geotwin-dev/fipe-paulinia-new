<?php

session_start();
include "connection.php";

//var_dump($_SESSION);

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paulínia</title>
    <!--icone-->
    <link rel="icon" type="image/x-icon" href="icones/automa.ico">

    <!--Conexão com jquery-->
    <script src="https://code.jquery.com/jquery-3.6.1.min.js" integrity="sha256-o88AwQnZB+VDvE9tvIXrMQaPlFFSUTR+nldQm1LuPXQ=" crossorigin="anonymous"></script>
    <!--Bootstrap CSS only -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-Zenh87qX5JnK2Jl0vWa8Ck2rdkQ2Bzep5IDxbcnCeuOxjzrPF/et3URy9Bv1WTRi" crossorigin="anonymous">

    <!--CSS do index -->
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

        #alert2, #alert3 {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
            padding: 10px;
            margin: 5px 0;
            font-weight: bold;
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

    <div id="titulo_cliente">
        <span>
            SISTEMA DE CADASTRAMENTO DE IMÓVEIS DA CIDADE DE PAULÍNIA
        </span>
    </div>

    <div id="container">
        <div id="subcontainer">
            <div id="titulo">
                <span id="login">Login</span>
            </div>
            <div id="titulo2">
                <span id="subtitulo">Acesse sua conta</span>
            </div>

            <div id="alertas">
                <?php
                if (isset($_SESSION['nao_autenticado'])) :
                ?>
                    <div id="alert2">
                        <span>Usuário ou senha inválidos.</span>
                    </div>
                <?php
                endif;
                unset($_SESSION['nao_autenticado']);
                ?>
                <?php
                if (isset($_SESSION['nao_habilitado'])) :
                ?>
                    <div id="alert3">
                        <span>Usuário não habilitado.</span>
                    </div>
                <?php
                endif;
                unset($_SESSION['nao_habilitado']);
                ?>
                <?php
                if (isset($_SESSION['sem_cliente'])) :
                ?>
                    <div id="alert4">
                        <span>Usuário ou senha inválidos.</span>
                    </div>
                <?php
                endif;
                unset($_SESSION['sem_cliente']);
                ?>
            </div>

            <div id="formulario">
                <form action="login.php" method="POST">
                    <div id="field">
                        <label class="label_inputs" id="label_username" for="username">Usuário</label>
                        <input class="inputs_cad" id="username" name="username" type="username" placeholder="Digite seu nome de usuário" required autofocus>
                    </div>
                    <div style="margin-top: 10px; position: relative;" id="field">
                        <label class="label_inputs" id="label_senha" for="senha">Senha</label>
                        <div style="display: flex;">
                            <input style="width: 100%; box-sizing: border-box; padding-right: 40px;" class="inputs_cad" id="senha" name="senha" type="password" placeholder="Digite sua senha" required>
                            <button style="width: 40px; height: 40px; margin-top: 21px" type="button" id="mostrar-senha" onclick="revel_senha()">
                                <svg id="olhinho1" style="display: block;" xmlns="http://www.w3.org/2000/svg" width="15" height="12" viewBox="0 0 45 36">
                                    <path id="Icon_awesome-eye-slash" data-name="Icon awesome-eye-slash" d="M22.5,28.125a10.087,10.087,0,0,1-10.048-9.359l-7.376-5.7a23.435,23.435,0,0,0-2.582,3.909,2.275,2.275,0,0,0,0,2.052A22.552,22.552,0,0,0,22.5,31.5a21.84,21.84,0,0,0,5.477-.735l-3.649-2.823a10.134,10.134,0,0,1-1.828.184ZM44.565,32.21,36.792,26.2a23.291,23.291,0,0,0,5.713-7.177,2.275,2.275,0,0,0,0-2.052A22.552,22.552,0,0,0,22.5,4.5,21.667,21.667,0,0,0,12.142,7.151L3.2.237a1.125,1.125,0,0,0-1.579.2L.237,2.211a1.125,1.125,0,0,0,.2,1.579L41.8,35.763a1.125,1.125,0,0,0,1.579-.2l1.381-1.777a1.125,1.125,0,0,0-.2-1.579ZM31.648,22.226,28.884,20.09a6.663,6.663,0,0,0-8.164-8.573,3.35,3.35,0,0,1,.655,1.984,3.279,3.279,0,0,1-.108.7l-5.176-4A10.006,10.006,0,0,1,22.5,7.875,10.119,10.119,0,0,1,32.625,18a9.885,9.885,0,0,1-.977,4.226Z" transform="translate(0 0)" fill="#025e73" />
                                </svg>
                                <svg id="olhinho2" style="display: none;" xmlns="http://www.w3.org/2000/svg" width="15" height="10" viewBox="0 0 40.5 27">
                                    <path id="Icon_awesome-eye" data-name="Icon awesome-eye" d="M40.255,16.973A22.552,22.552,0,0,0,20.25,4.5,22.555,22.555,0,0,0,.245,16.974a2.275,2.275,0,0,0,0,2.052A22.552,22.552,0,0,0,20.25,31.5,22.555,22.555,0,0,0,40.255,19.026,2.275,2.275,0,0,0,40.255,16.973ZM20.25,28.125A10.125,10.125,0,1,1,30.375,18,10.125,10.125,0,0,1,20.25,28.125Zm0-16.875a6.7,6.7,0,0,0-1.78.266,3.364,3.364,0,0,1-4.7,4.7,6.735,6.735,0,1,0,6.484-4.97Z" transform="translate(0 -4.5)" fill="#025e73" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div style="margin-top: 35px;" id="field">
                        <button id="bot_submit" type="submit">Entrar</button>
                    </div>
                </form>
            </div>
            
            <div id="titulo3">
                <center>
                    <div>
                        <span>Novo Usuário? <a href="cadastro.php">Crie sua conta.</a></span>
                    </div>
                </center>
            </div>
        </div>
    </div>

</body>
<script>
    function revel_senha() {
        var olhinho1 = $('#olhinho1');
        var olhinho2 = $('#olhinho2');

        if (olhinho1.css('display') === 'block') {
            olhinho1.css('display', 'none');
            olhinho2.css('display', 'block');
            $('#senha').attr('type', 'text');
        } else {
            olhinho1.css('display', 'block');
            olhinho2.css('display', 'none');
            $('#senha').attr('type', 'password');
        }
    }
</script>

</html>