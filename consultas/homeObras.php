<?php
session_start();

include('verifica_login.php');

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HOME</title>

    <script src="bibliotecas/jquery.min.js"></script>

    <link rel="stylesheet" href="bibliotecas/bootstrap.min.css">
    <script src="bibliotecas/bootstrap.bundle.min.js"></script>

    <link href='bibliotecas/dataTables.dataTables.min.css' rel='stylesheet'>
    <script src="bibliotecas/dataTables.min.js"></script>

    <link rel="icon" type="image/x-icon" href="bibliotecas/icon.ico">

    <style>
        html,
        body {
            width: 100%;
            padding: 0;
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            box-sizing: border-box;
        }

        .divContainer {
            width: 100%;
            box-sizing: border-box;
            padding: 20px 30px;
            display: flex;
            flex-direction: column;
            height: 100vh;
        }

        .divFechar {
            position: absolute;
            top: 25px;
            right: 30px;
        }

        .tagSair,
        .tagSobre {
            float: right;
            text-decoration: none;
            margin-left: 10px;
        }

        .svgIcon {
            fill: silver;
        }

        .svgIcon:hover {
            fill: #0a58ca;
        }

        .btnAutoma {
            margin-right: 5px;
            background-color: #025E73;
            border-color: #025E73;
        }

        .divTitulo {
            width: 100%;
            display: flex;
            flex-direction: column;
            margin-bottom: 30px;
        }

        .spanT {
            color: #025E73;
            font-weight: bold;
        }

        .ss {
            font-size: 20px;
        }

        .st {
            font-size: 35px;
        }

        /*accordion*/
        .accordion {
            margin-bottom: 10px;
        }

        .accordion-button {
            color: rgb(2, 94, 115);
            font-weight: bold;
        }

        .accordion-button:not(.collapsed) {
            color: rgb(2, 94, 115) !important;
            background-color: white !important;
        }

        .accordion-button:focus {
            box-shadow: inset 0 calc(-1* 1px) 0 #dee2e6 !important;
        }

        .colunaCheck {
            display: flex;
            flex-direction: column;
            min-width: 250px;
        }

        .arrumaDiv {
            display: flex;
        }

        /*fim accordion*/

        /*TABLE*/
        #divTabela {
            flex-grow: 1;
        }

        /*fim TABLE*/

        #divFiltros {
            width: 100%;
            display: flex;
            justify-content: start;
        }

        .divColFilter {
            display: block;
            margin-right: 10px;
        }

        .divRowFilter {
            display: flex;
            justify-content: start;
            margin-bottom: 3px;
            margin-left: 5px;
        }

        .inputsClass {
            width: 250px;
            height: 40px;
            margin-right: 5px;
            background-color: #f0f0f0;
        }

        .form-select {
            font-weight: bold;
            color: #025E73;
        }

        .highlight {
            background-color: rgba(2, 94, 115, 0.3) !important;
            /* Cor de destaque */
        }

        #example tbody tr:hover {
            background-color: rgba(2, 94, 115, 0.3) !important;
        }

        #example tbody tr {
            cursor: pointer;
        }
        
        #example tbody td:nth-child(1),#example tbody td:nth-child(2) {
            text-align: center;  /* alinha horizontalmente à esquerda */
        }

        #collapseTwo {
            overflow: auto;
        }
    </style>
</head>

<body>

    <div class="divContainer">

        <div id="divV1" class="divFechar">
            <button onclick="irCadastrar();" class="btn btn-primary btnAutoma">CADASTRAR OBRA</button>
            <button onclick="irMapa();" class="btn btn-primary btnAutoma">MAPA</button>
            <button onclick="irindicadores();" class="btn btn-primary btnAutoma">INDICADORES</button>
            <a title="Sobre" class="tagSobre" href="#" onclick="irSobre();">
                <svg class="svgIcon" xmlns="http://www.w3.org/2000/svg" width="38" height="40" viewBox="0 0 16 16">
                    <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0M5.496 6.033h.825c.138 0 .248-.113.266-.25.09-.656.54-1.134 1.342-1.134.686 0 1.314.343 1.314 1.168 0 .635-.374.927-.965 1.371-.673.489-1.206 1.06-1.168 1.987l.003.217a.25.25 0 0 0 .25.246h.811a.25.25 0 0 0 .25-.25v-.105c0-.718.273-.927 1.01-1.486.609-.463 1.244-.977 1.244-2.056 0-1.511-1.276-2.241-2.673-2.241-1.267 0-2.655.59-2.75 2.286a.237.237 0 0 0 .241.247m2.325 6.443c.61 0 1.029-.394 1.029-.927 0-.552-.42-.94-1.029-.94-.584 0-1.009.388-1.009.94 0 .533.425.927 1.01.927z" />
                </svg>
            </a>
            <a title="Sair" class="tagSair" href="#" onclick="sair();">
                <svg class="svgIcon" xmlns="http://www.w3.org/2000/svg" width="38" height="40" viewBox="0 0 16 16">
                    <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0M5.354 4.646a.5.5 0 1 0-.708.708L7.293 8l-2.647 2.646a.5.5 0 0 0 .708.708L8 8.707l2.646 2.647a.5.5 0 0 0 .708-.708L8.707 8l2.647-2.646a.5.5 0 0 0-.708-.708L8 7.293z" />
                </svg>
            </a>
        </div>

        <div id="divV2" class="divTitulo">
            <span class="spanT st">GERENCIADOR DE OBRAS</span>
            <span class="spanT ss">Sistema de fiscalização de obras do TCU</span>
        </div>

        <!-- div para os checkboxes que contrala a visibilidade das colunas-->
        <div class="accordion" id="accordionExample">
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                        Colunas visíveis na tabela
                    </button>
                </h2>
                <div id="collapseTwo" class="accordion-collapse collapse" data-bs-parent="#accordionExample">
                    <div class="accordion-body arrumaDiv">

                        <div class="colunaCheck">
                            <label><input type="checkbox" class="column-toggle" disabled checked data-column="0"> ID Obra</label>
                            <label><input type="checkbox" class="column-toggle" data-column="1"> Nome Obra</label>
                            <label><input type="checkbox" class="column-toggle" data-column="2"> Descrição Obra</label>
                            <label><input type="checkbox" class="column-toggle" data-column="3"> Orçamento Obra</label>
                            <label><input type="checkbox" class="column-toggle" data-column="4"> Data Inicio Prevista</label>
                            <label><input type="checkbox" class="column-toggle" data-column="5"> Data Término Prevista</label>
                            <label><input type="checkbox" class="column-toggle" data-column="6"> Localização Principal</label>
                            <label><input type="checkbox" class="column-toggle" data-column="7"> Fonte Recursos</label>
                        </div>

                        <div class="colunaCheck">
                            
                            <label><input type="checkbox" class="column-toggle" data-column="8"> Número na CEF</label>
                            <label><input type="checkbox" class="column-toggle" checked data-column="9"> Número do SIAF</label>
                            <label><input type="checkbox" class="column-toggle" checked data-column="10"> UF</label>
                            <label><input type="checkbox" class="column-toggle" data-column="11"> Proponente</label>
                            <label><input type="checkbox" class="column-toggle" data-column="12"> Código Município IBGE</label>
                            <label><input type="checkbox" class="column-toggle" data-column="13"> Objeto</label>
                            <label><input type="checkbox" class="column-toggle" checked data-column="14"> Município Beneficiado</label>
                            <label><input type="checkbox" class="column-toggle" checked data-column="15"> Situação</label>
                        </div>

                        <div class="colunaCheck">
                            <label><input type="checkbox" class="column-toggle" data-column="16"> Situação do Contrato</label>
                            <label><input type="checkbox" class="column-toggle" data-column="17"> Complemento do Contrato</label>
                            <label><input type="checkbox" class="column-toggle" data-column="18"> Situação da Obra</label>
                            <label><input type="checkbox" class="column-toggle" data-column="19"> Ano da Proposta</label>
                            <label><input type="checkbox" class="column-toggle" data-column="20"> Ano do Orçamento</label>
                            <label><input type="checkbox" class="column-toggle" data-column="21"> Ano da Contratação</label>
                            <label><input type="checkbox" class="column-toggle" data-column="22"> % Físico Informado</label>
                            <label><input type="checkbox" class="column-toggle" data-column="23"> % Físico Aferido</label>
                        </div>

                        <div class="colunaCheck">
                            <label><input type="checkbox" class="column-toggle" data-column="24"> Dt. Vigência do Contrato</label>
                            <label><input type="checkbox" class="column-toggle" data-column="25"> Data SPA</label>
                            <label><input type="checkbox" class="column-toggle" data-column="26"> Dt. Referência p/ Licitação</label>
                            <label><input type="checkbox" class="column-toggle" data-column="27"> Data AIO</label>
                            <label><input type="checkbox" class="column-toggle" data-column="28"> Valor de Repasse</label>
                            <label><input type="checkbox" class="column-toggle" data-column="29"> Valor de Contrapartida</label>
                            <label><input type="checkbox" class="column-toggle" data-column="30"> Dt. Últ. Boletim de Medição</label>
                            <label><input type="checkbox" class="column-toggle" data-column="31"> Última Vistoria</label>
                        </div>

                        <div class="colunaCheck">
                            <label><input type="checkbox" class="column-toggle" data-column="32"> Descrição Aérea</label>
                            <label><input type="checkbox" class="column-toggle" data-column="33"> Descrição da Modalidade</label>
                            <label><input type="checkbox" class="column-toggle" data-column="34"> Descrição do Objetivo</label>
                            <label><input type="checkbox" class="column-toggle" data-column="35"> Data do Término da Obra</label>
                            <label><input type="checkbox" class="column-toggle" data-column="36"> Situação Atual</label>
                            <label><input type="checkbox" class="column-toggle" data-column="37"> Atualização da Situação Atual</label>
                            <label><input type="checkbox" class="column-toggle" checked data-column="38"> Data Final Fiscalização</label>
                            <label><input type="checkbox" class="column-toggle" checked data-column="39"> Score</label>
                            <label><input type="checkbox" class="column-toggle" checked data-column="40"> Estagio Cronograma</label>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <div class="accordion" id="accordionExample2">
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                        Campos de pesquisa
                    </button>
                </h2>
                <div id="collapseThree" class="accordion-collapse collapse" data-bs-parent="#accordionExample2">
                    <div class="accordion-body arrumaDiv">
                        <div id="divFiltros" class="divFiltros">
                            <div class="divColFilter">
                                <div class="divRowFilter">

                                    <select id="selectFilter1" class="form-select inputsClass">
                                        <option value="N"></option>
                                        <option value="0">ID Obra</option>
                                        <option value="1">Nome Obra</option>
                                        <option value="2">Descrição Obra</option>
                                        <option value="3">Orçamento Total</option>
                                        <option value="4">Início Previsto</option>
                                        <option value="5">Término Previsto</option>
                                        <option value="6">Situação Esperada</option>
                                        <option value="7">Localização Principal</option>
                                        <option value="8">Fonte Recursos</option>
                                        <option value="9">Número na CEF</option>
                                        <option value="10">Número do SIAF</option>
                                        <option value="11">UF</option>
                                        <option value="12">Proponente</option>
                                        <option value="13">Código Município IBGE</option>
                                        <option value="14">Objeto</option>
                                        <option selected value="15">Município Beneficiado</option>
                                        <option value="16">Situação do Contrato</option>
                                        <option value="17">Complemento do Contrato</option>
                                        <option value="18">Situação da Obra</option>
                                        <option value="19">Ano da Proposta</option>
                                        <option value="20">Ano do Orçamento</option>
                                        <option value="21">Ano da Contratação</option>
                                        <option value="22">% Físico Informado</option>
                                        <option value="23">% Físico Aferido</option>
                                        <option value="24">Dt. Vigência do Contrato</option>
                                        <option value="25">Data SPA</option>
                                        <option value="26">Dt. Referência p/ Licitação</option>
                                        <option value="27">Data AIO</option>
                                        <option value="28">Valor de Repasse</option>
                                        <option value="29">Valor de Contrapartida</option>
                                        <option value="30">Dt. Últ. Boletim de Medição</option>
                                        <option value="31">Última Vistoria</option>
                                        <option value="32">Descrição Aérea</option>
                                        <option value="33">Descrição da Modalidade</option>
                                        <option value="34">Descrição do Objetivo</option>
                                        <option value="35">Data do Término da Obra</option>
                                        <option value="36">Situação Atual</option>
                                        <option value="37">Atualização da Situação Atual</option>
                                    </select>

                                    <input id="spanUm" type="text" class="form-control inputsClass">
                                </div>
                                <div class="divRowFilter">

                                    <select id="selectFilter2" class="form-select inputsClass">
                                        <option value="N"></option>
                                        <option value="0">ID Obra</option>
                                        <option value="1">Nome Obra</option>
                                        <option value="2">Descrição Obra</option>
                                        <option value="3">Orçamento Total</option>
                                        <option value="4">Início Previsto</option>
                                        <option value="5">Término Previsto</option>
                                        <option value="6">Situação Esperada</option>
                                        <option value="7">Localização Principal</option>
                                        <option value="8">Fonte Recursos</option>
                                        <option value="9">Número na CEF</option>
                                        <option value="10">Número do SIAF</option>
                                        <option selected value="11">UF</option>
                                        <option value="12">Proponente</option>
                                        <option value="13">Código Município IBGE</option>
                                        <option value="14">Objeto</option>
                                        <option value="15">Município Beneficiado</option>
                                        <option value="16">Situação do Contrato</option>
                                        <option value="17">Complemento do Contrato</option>
                                        <option value="18">Situação da Obra</option>
                                        <option value="19">Ano da Proposta</option>
                                        <option value="20">Ano do Orçamento</option>
                                        <option value="21">Ano da Contratação</option>
                                        <option value="22">% Físico Informado</option>
                                        <option value="23">% Físico Aferido</option>
                                        <option value="24">Dt. Vigência do Contrato</option>
                                        <option value="25">Data SPA</option>
                                        <option value="26">Dt. Referência p/ Licitação</option>
                                        <option value="27">Data AIO</option>
                                        <option value="28">Valor de Repasse</option>
                                        <option value="29">Valor de Contrapartida</option>
                                        <option value="30">Dt. Últ. Boletim de Medição</option>
                                        <option value="31">Última Vistoria</option>
                                        <option value="32">Descrição Aérea</option>
                                        <option value="33">Descrição da Modalidade</option>
                                        <option value="34">Descrição do Objetivo</option>
                                        <option value="35">Data do Término da Obra</option>
                                        <option value="36">Situação Atual</option>
                                        <option value="37">Atualização da Situação Atual</option>
                                    </select>

                                    <input id="spanDois" type="text" class="form-control inputsClass">
                                </div>
                                <div class="divRowFilter">

                                    <select id="selectFilter3" class="form-select inputsClass">
                                        <option value="N"></option>
                                        <option value="0">ID Obra</option>
                                        <option selected value="1">Nome Obra</option>
                                        <option value="2">Descrição Obra</option>
                                        <option value="3">Orçamento Total</option>
                                        <option value="4">Início Previsto</option>
                                        <option value="5">Término Previsto</option>
                                        <option value="6">Situação Esperada</option>
                                        <option value="7">Localização Principal</option>
                                        <option value="8">Fonte Recursos</option>
                                        <option value="9">Número na CEF</option>
                                        <option value="10">Número do SIAF</option>
                                        <option value="11">UF</option>
                                        <option value="12">Proponente</option>
                                        <option value="13">Código Município IBGE</option>
                                        <option value="14">Objeto</option>
                                        <option value="15">Município Beneficiado</option>
                                        <option value="16">Situação do Contrato</option>
                                        <option value="17">Complemento do Contrato</option>
                                        <option value="18">Situação da Obra</option>
                                        <option value="19">Ano da Proposta</option>
                                        <option value="20">Ano do Orçamento</option>
                                        <option value="21">Ano da Contratação</option>
                                        <option value="22">% Físico Informado</option>
                                        <option value="23">% Físico Aferido</option>
                                        <option value="24">Dt. Vigência do Contrato</option>
                                        <option value="25">Data SPA</option>
                                        <option value="26">Dt. Referência p/ Licitação</option>
                                        <option value="27">Data AIO</option>
                                        <option value="28">Valor de Repasse</option>
                                        <option value="29">Valor de Contrapartida</option>
                                        <option value="30">Dt. Últ. Boletim de Medição</option>
                                        <option value="31">Última Vistoria</option>
                                        <option value="32">Descrição Aérea</option>
                                        <option value="33">Descrição da Modalidade</option>
                                        <option value="34">Descrição do Objetivo</option>
                                        <option value="35">Data do Término da Obra</option>
                                        <option value="36">Situação Atual</option>
                                        <option value="37">Atualização da Situação Atual</option>
                                    </select>

                                    <input id="spanTres" type="text" class="form-control inputsClass">
                                </div>
                            </div>
                            <div class="divColFilter">
                                <div class="divRowFilter">

                                    <select id="selectFilter4" class="form-select inputsClass">
                                        <option value="N"></option>
                                        <option value="0">ID Obra</option>
                                        <option value="1">Nome Obra</option>
                                        <option value="2">Descrição Obra</option>
                                        <option value="3">Orçamento Total</option>
                                        <option value="4">Início Previsto</option>
                                        <option value="5">Término Previsto</option>
                                        <option value="6">Situação Esperada</option>
                                        <option value="7">Localização Principal</option>
                                        <option value="8">Fonte Recursos</option>
                                        <option selected value="9">Número na CEF</option>
                                        <option value="10">Número do SIAF</option>
                                        <option value="11">UF</option>
                                        <option value="12">Proponente</option>
                                        <option value="13">Código Município IBGE</option>
                                        <option value="14">Objeto</option>
                                        <option value="15">Município Beneficiado</option>
                                        <option value="16">Situação do Contrato</option>
                                        <option value="17">Complemento do Contrato</option>
                                        <option value="18">Situação da Obra</option>
                                        <option value="19">Ano da Proposta</option>
                                        <option value="20">Ano do Orçamento</option>
                                        <option value="21">Ano da Contratação</option>
                                        <option value="22">% Físico Informado</option>
                                        <option value="23">% Físico Aferido</option>
                                        <option value="24">Dt. Vigência do Contrato</option>
                                        <option value="25">Data SPA</option>
                                        <option value="26">Dt. Referência p/ Licitação</option>
                                        <option value="27">Data AIO</option>
                                        <option value="28">Valor de Repasse</option>
                                        <option value="29">Valor de Contrapartida</option>
                                        <option value="30">Dt. Últ. Boletim de Medição</option>
                                        <option value="31">Última Vistoria</option>
                                        <option value="32">Descrição Aérea</option>
                                        <option value="33">Descrição da Modalidade</option>
                                        <option value="34">Descrição do Objetivo</option>
                                        <option value="35">Data do Término da Obra</option>
                                        <option value="36">Situação Atual</option>
                                        <option value="37">Atualização da Situação Atual</option>
                                    </select>

                                    <input id="spanQuatro" type="text" class="form-control inputsClass">
                                </div>
                                <div class="divRowFilter">

                                    <select id="selectFilter5" class="form-select inputsClass">
                                        <option value="N"></option>
                                        <option value="0">ID Obra</option>
                                        <option value="1">Nome Obra</option>
                                        <option value="2">Descrição Obra</option>
                                        <option value="3">Orçamento Total</option>
                                        <option value="4">Início Previsto</option>
                                        <option value="5">Término Previsto</option>
                                        <option value="6">Situação Esperada</option>
                                        <option value="7">Localização Principal</option>
                                        <option value="8">Fonte Recursos</option>
                                        <option value="9">Número na CEF</option>
                                        <option selected value="10">Número do SIAF</option>
                                        <option value="11">UF</option>
                                        <option value="12">Proponente</option>
                                        <option value="13">Código Município IBGE</option>
                                        <option value="14">Objeto</option>
                                        <option value="15">Município Beneficiado</option>
                                        <option value="16">Situação do Contrato</option>
                                        <option value="17">Complemento do Contrato</option>
                                        <option value="18">Situação da Obra</option>
                                        <option value="19">Ano da Proposta</option>
                                        <option value="20">Ano do Orçamento</option>
                                        <option value="21">Ano da Contratação</option>
                                        <option value="22">% Físico Informado</option>
                                        <option value="23">% Físico Aferido</option>
                                        <option value="24">Dt. Vigência do Contrato</option>
                                        <option value="25">Data SPA</option>
                                        <option value="26">Dt. Referência p/ Licitação</option>
                                        <option value="27">Data AIO</option>
                                        <option value="28">Valor de Repasse</option>
                                        <option value="29">Valor de Contrapartida</option>
                                        <option value="30">Dt. Últ. Boletim de Medição</option>
                                        <option value="31">Última Vistoria</option>
                                        <option value="32">Descrição Aérea</option>
                                        <option value="33">Descrição da Modalidade</option>
                                        <option value="34">Descrição do Objetivo</option>
                                        <option value="35">Data do Término da Obra</option>
                                        <option value="36">Situação Atual</option>
                                        <option value="37">Atualização da Situação Atual</option>
                                    </select>

                                    <input id="spanCinco" type="text" class="form-control inputsClass">
                                </div>
                                <div class="divRowFilter">

                                    <select id="selectFilter6" class="form-select inputsClass">
                                        <option value="N"></option>
                                        <option value="0">ID Obra</option>
                                        <option value="1">Nome Obra</option>
                                        <option value="2">Descrição Obra</option>
                                        <option value="3">Orçamento Total</option>
                                        <option value="4">Início Previsto</option>
                                        <option value="5">Término Previsto</option>
                                        <option value="6">Situação Esperada</option>
                                        <option value="7">Localização Principal</option>
                                        <option value="8">Fonte Recursos</option>
                                        <option value="9">Número na CEF</option>
                                        <option value="10">Número do SIAF</option>
                                        <option value="11">UF</option>
                                        <option value="12">Proponente</option>
                                        <option value="13">Código Município IBGE</option>
                                        <option value="14">Objeto</option>
                                        <option value="15">Município Beneficiado</option>
                                        <option value="16">Situação do Contrato</option>
                                        <option value="17">Complemento do Contrato</option>
                                        <option value="18">Situação da Obra</option>
                                        <option value="19">Ano da Proposta</option>
                                        <option value="20">Ano do Orçamento</option>
                                        <option value="21">Ano da Contratação</option>
                                        <option value="22">% Físico Informado</option>
                                        <option value="23">% Físico Aferido</option>
                                        <option value="24">Dt. Vigência do Contrato</option>
                                        <option value="25">Data SPA</option>
                                        <option value="26">Dt. Referência p/ Licitação</option>
                                        <option value="27">Data AIO</option>
                                        <option value="28">Valor de Repasse</option>
                                        <option value="29">Valor de Contrapartida</option>
                                        <option value="30">Dt. Últ. Boletim de Medição</option>
                                        <option value="31">Última Vistoria</option>
                                        <option value="32">Descrição Aérea</option>
                                        <option value="33">Descrição da Modalidade</option>
                                        <option value="34">Descrição do Objetivo</option>
                                        <option value="35">Data do Término da Obra</option>
                                        <option selected value="36">Situação Atual</option>
                                        <option value="37">Atualização da Situação Atual</option>
                                    </select>

                                    <input id="spanSeis" type="text" class="form-control inputsClass">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!--table-->
        <div id="divTabela" class="divTabela">
            <table id="example" class="stripe hover" style="width:100%">
                <thead>
                    <tr>
                        <th style="width: 5%">Obra</th>
                        <th>Nome Obra</th>
                        <th>Descrição Obra</th>
                        <th style="min-width: 150px;">Orçamento Total</th>
                        <th>Início Previsto</th>
                        <th>Término Previsto</th>
                        
                        <th>Localização Principal</th>
                        <th>Fonte Recursos</th>
                        <th>Número na CEF</th>
                        <th style="width: 10%">SIAF</th>
                        <th>UF</th>
                        <th>Proponente</th>
                        <th>Código Município IBGE</th>
                        <th>Objeto</th>
                        <th>Município Beneficiado</th>
                        
                        <th>Situação</th>
                        
                        <th>Situação do Contrato</th>
                        <th>Complemento do Contrato</th>
                        <th>Situação da Obra</th>
                        <th>Ano da Proposta</th>
                        <th>Ano do Orçamento</th>
                        <th>Ano da Contratação</th>
                        <th>% Físico Informado</th>
                        <th>% Físico Aferido</th>
                        <th>Dt. Vigência do Contrato</th>
                        <th>Data SPA</th>
                        <th>Dt. Referência p/ Licitação</th>
                        <th>Data AIO</th>
                        <th style="min-width: 150px;">Valor de Repasse</th>
                        <th style="min-width: 150px;">Valor de Contrapartida</th>
                        <th>Dt. Últ. Boletim de Medição</th>
                        <th>Última Vistoria</th>
                        <th>Descrição Aérea</th>
                        <th>Descrição da Modalidade</th>
                        <th>Descrição do Objetivo</th>
                        <th>Data do Término da Obra</th>
                        <th>Situação Atual</th>
                        <th>Atualização da Situação Atual</th>
                        <th>Data Final da Fiscalização</th>
                        <th>Score</th>
                        <th>Estágio Cronograma</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    <script>
        //localStorage.removeItem("abaUser");

        let table;

        function irSobre() {

            window.open("https://painel-acompanhamento-ob-bcfhlnh.gamma.site", "_blank");

        }

        function sair() {
            window.location.href = "logout.php"
        }

        function irindicadores() {
            window.location.href = "indicadores.php"
        }

        function irCadastrar() {
            window.location.href = "cadastroObra.php"
        }

        function irMapa() {
            window.location.href = "mapa.php"
        }

        // function para formatar a data hora do php
        function formataData(dataOriginal, hora) {
            // Quebra a string na parte de data e hora
            const [dataF, horaF] = dataOriginal.split(" ");
            // Reorganiza a data para o formato desejado
            const [anoF, mesF, diaF] = dataF.split("-");

            if (hora == true) {
                var dataFormatadaF = `${diaF}-${mesF}-${anoF} ${horaF}`;
            } else {
                var dataFormatadaF = `${diaF}-${mesF}-${anoF}`;
            }

            return dataFormatadaF;

        }

        // Função auxiliar para verificar se a entrada é apenas numérica
        function isNumeric(value) {
            return /^\d+$/.test(value); // Verifica se contém apenas números
        }

        // Função para construir a regex para números com prefixo/sufixo
        function getNumberPattern(value) {
            // Se o número for de 1 dígito, considera também com 0 à esquerda
            let digitWithZero = value.length === 1 ? `0${value}` : value;
            // Gera a expressão regular que aceita o número cercado por caracteres não numéricos
            let pattern = `(^|[^0-9])(${value}|${digitWithZero})([^0-9]|$)`;
            return pattern;
        }

        function formatarMoeda(valor) {
            if (!isNaN(valor)) {
                return `R$ ${parseFloat(valor).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
            }
            return valor; // Retorna o original se não for um número
        }

        $(document).ready(function() {

            const storageKey = "columnToggleState2";
            const storageSelect = "selectsState2";

            function saveToLocalStorage() {
                let columnState = {};

                $(".column-toggle").each(function() {
                    columnState[$(this).data("column")] = $(this).prop("checked");
                });

                localStorage.setItem(storageKey, JSON.stringify(columnState));
            }

            function loadFromLocalStorage() {
                const storedState = localStorage.getItem(storageKey);

                if (storedState) {
                    const columnState = JSON.parse(storedState);
                    $(".column-toggle").each(function() {
                        const column = $(this).data("column");
                        if (columnState.hasOwnProperty(column)) {
                            $(this).prop("checked", columnState[column]);
                        }
                    });
                }
            }

            // Carregar o estado salvo ao iniciar
            //loadFromLocalStorage();

            // Salvar mudanças no localStorage quando um checkbox for alterado
            //$(".column-toggle").on("change", saveToLocalStorage);

            // Definição dos valores padrões de visibilidade
            const defaultVisibility = {
                "0": true,
                "1": false,
                "2": false,
                "3": false,
                "4": false,
                "5": false,
                "6": false,
                "7": false,
                "8": false,
                "9": true,
                "10": true,
                "11": false,
                "12": false,
                "13": false,
                "14": true,
                "15": true,
                "16": false,
                "17": false,
                "18": false,
                "19": false,
                "20": false,
                "21": false,
                "22": false,
                "23": false,
                "24": false,
                "25": false,
                "26": false,
                "27": false,
                "28": false,
                "29": false,
                "30": false,
                "31": false,
                "32": false,
                "33": false,
                "34": false,
                "35": false,
                "36": false,
                "37": false,
                "38": true,
                "39": true,
                "40": true
            };

            // Função para salvar o estado dos selects no localStorage
            function saveSelectsStorage() {
                let stateSelect = [];

                // Para cada select, salvar o valor atual no array stateSelect
                $("#selectFilter1, #selectFilter2, #selectFilter3, #selectFilter4, #selectFilter5, #selectFilter6").each(function() {
                    stateSelect.push($(this).val()); // Pega o valor do select
                });

                // Salva o array no localStorage como uma string JSON
                localStorage.setItem(storageSelect, JSON.stringify(stateSelect));
            }

            // Função para carregar o estado dos selects do localStorage
            function loadPesquisa() {
                const stateSelect = localStorage.getItem(storageSelect);

                // Verifica se há um estado salvo
                if (stateSelect) {
                    const selectsState = JSON.parse(stateSelect); // Converte de volta para array

                    // Para cada select, aplica o valor salvo no localStorage
                    $("#selectFilter1, #selectFilter2, #selectFilter3, #selectFilter4, #selectFilter5, #selectFilter6").each(function(index) {
                        $(this).val(selectsState[index]); // Define o valor do select
                    });

                    // Dispara o evento change após restaurar os valores
                    $("#selectFilter1, #selectFilter2, #selectFilter3, #selectFilter4, #selectFilter5, #selectFilter6").trigger("change");
                }
            }

            // Carregar o estado ao iniciar
            //loadPesquisa();

            // Salvar o estado sempre que um select for alterado
            //$("#selectFilter1, #selectFilter2, #selectFilter3, #selectFilter4, #selectFilter5, #selectFilter6").on("change", saveSelectsStorage);
            
            
            
            function controlaVisible(className) {
                // Verifica se a chave "storageKey" existe no localStorage
                const pegaStorage = localStorage.getItem(storageKey);
                
                if (pegaStorage) {
                    // Se o localStorage contiver a chave, tenta parsear e retorna a visibilidade salva ou o padrão
                    const parsedStorage = JSON.parse(pegaStorage);
                    return parsedStorage.hasOwnProperty(className) ? parsedStorage[className] : defaultVisibility[className];
                } else {
                    // Se a chave não existir no localStorage, retorna o valor padrão
                    return defaultVisibility[className];
                }
            }


            $.when(
                $.ajax({
                    url: "buscaDados1.php",
                    dataType: "json",
                    cache: false
                })
            ).done(function(data1) {
                var alturaDiv1 = $('#divV1').outerHeight();
                var alturaDiv2 = $('#divV2').outerHeight();
                var alturaDiv3 = $('#accordionExample').outerHeight();
                var alturaDiv4 = $('#divTabela').outerHeight();

                var obras = data1; // Dados dos obras

                for (var i = 0; i < obras.length; i++) {
                    // Função para garantir que a data não seja nula antes de formatar
                    function formatarDataSeValida(data) {
                        return data ? formataData(data, false) : null;
                    }

                    function formatarValorSeValido(data) {
                        return data ? formatarMoeda(data) : null;
                    }

                    obras[i].DataInicioPrevista = formatarDataSeValida(obras[i].DataInicioPrevista);
                    obras[i].DataTerminoPrevista = formatarDataSeValida(obras[i].DataTerminoPrevista);
                    obras[i].DATA_AIO = formatarDataSeValida(obras[i].DATA_AIO);
                    obras[i].DATA_SPA_HOMOLOGACAO = formatarDataSeValida(obras[i].DATA_SPA_HOMOLOGACAO);
                    obras[i].DATA_TERMINO_OBRA = formatarDataSeValida(obras[i].DATA_TERMINO_OBRA);
                    obras[i].DATA_ULTIMA_VISTORIA = formatarDataSeValida(obras[i].DATA_ULTIMA_VISTORIA);
                    obras[i].DATA_ULTIMO_BM = formatarDataSeValida(obras[i].DATA_ULTIMO_BM);
                    obras[i].DATA_VIGENCIA = formatarDataSeValida(obras[i].DATA_VIGENCIA);
                    obras[i].DATA_VRPL = formatarDataSeValida(obras[i].DATA_VRPL);
                    obras[i].DT_ATUALIZACAO_SITUACAO_ATUAL = formatarDataSeValida(obras[i].DT_ATUALIZACAO_SITUACAO_ATUAL);
                    obras[i].VALOR_CONTRAPARTIDA = formatarValorSeValido(obras[i].VALOR_CONTRAPARTIDA)
                    obras[i].VALOR_REPASSE = formatarValorSeValido(obras[i].VALOR_REPASSE)
                    obras[i].OrcamentoTotal = formatarValorSeValido(obras[i].OrcamentoTotal);
                    obras[i].data_final = formatarDataSeValida(obras[i].data_final);
                }

                var calcHeight = Math.floor(alturaDiv4) - 200;

                //console.log(combinatedData);
                if ($.fn.DataTable.isDataTable('#example')) {
                    table = $('#example').DataTable(); // Obtenha a instância do DataTable
                    table.destroy(); // Destroi o DataTable existente
                }

                // Inicialize o DataTable com os dados combinados
                table = $('#example').DataTable({
                    "processing": true, // Mostra o indicador de processamento
                    "data": obras, // Use os dados combinados
                    "pageLength": 25, // Número de linhas por página
                    "lengthMenu": [3, 10, 25, 50, 100], // Opções de quantidade de linhas
                    "scrollCollapse": true, //Reduzir os cálculos durante o scroll
                    "scrollX": true,
                    //"scrollY": calcHeight,
                    //"scrollY": 400,
                    "paging": true, // Ativa paginação
                    "language": {
                        "decimal": ",",
                        "thousands": ".",
                        "lengthMenu": "Mostrar _MENU_ registros por página",
                        "zeroRecords": "Nenhum registro encontrado",
                        "info": "Mostrando de _START_ até _END_ de _TOTAL_ registros",
                        "infoEmpty": "Mostrando 0 até 0 de 0 registros",
                        "infoFiltered": "(filtrado de _MAX_ registros no total)",
                        "search": "Buscar:",
                        "paginate": {
                            "first": "Primeiro",
                            "last": "Último",
                            "next": "Próximo",
                            "previous": "Anterior"
                        },
                        "emptyTable": "Nenhum dado disponível na tabela"
                    },
                    order: [
                        [0, 'desc']
                    ],
                    "columns": [{ //coluna 1
                            "data": "IDObra"
                        },
                        { //coluna 2
                            "data": "NomeObra"
                        },
                        { //coluna 3
                            "data": "Descricao"
                        },
                        { //coluna 4
                            "data": "OrcamentoTotal"
                        },
                        { //coluna 5
                            "data": "DataInicioPrevista"
                        },
                        { //coluna 6
                            "data": "DataTerminoPrevista"
                        },
                        { //coluna 8
                            "data": "LocalizacaoPrincipal"
                        },
                        { //coluna 9
                            "data": "FonteRecursos"
                        },
                        { //coluna 10
                            "data": "OPERACAO"
                        },
                        { //coluna 11
                            "data": "CONVENIO_SIAFI"
                        },
                        { //coluna 12
                            "data": "UF"
                        },
                        { //coluna 13
                            "data": "PROPONENTE"
                        },
                        { //coluna 14
                            "data": "CODIGO_MUNICIPIO_IBGE"
                        },
                        { //coluna 15
                            "data": "OBJETO"
                        },
                        { //coluna 16
                            "data": "MUNICIPIO_BENEFICIADO"
                        },
                        { //coluna 7
                            "data": "Status"
                        },
                        { //coluna 17
                            "data": "SITUACAO_CONTRATO"
                        },
                        { //coluna 18
                            "data": "SITUACAO_CONTRATO_COMPLMENTO"
                        },
                        { //coluna 19
                            "data": "SITUACAO_OBRA"
                        },
                        { //coluna 20
                            "data": "ANO_PROPOSTA"
                        },
                        { //coluna 21
                            "data": "ANO_ORCAMENTARIO"
                        },
                        { //coluna 22
                            "data": "ANO_CONTRATACAO"
                        },
                        { //coluna 23
                            "data": "PERCENTUAL_FISICO_INFORMADO"
                        },
                        { //coluna 24
                            "data": "PERCENTUAL_FISICO_AFERIDO"
                        },
                        { //coluna 25
                            "data": "DATA_VIGENCIA"
                        },
                        { //coluna 26
                            "data": "DATA_SPA_HOMOLOGACAO"
                        },
                        { //coluna 27
                            "data": "DATA_VRPL"
                        },
                        { //coluna 28
                            "data": "DATA_AIO"
                        },
                        { //coluna 29
                            "data": "VALOR_REPASSE"
                        },
                        { //coluna 30
                            "data": "VALOR_CONTRAPARTIDA"
                        },
                        { //coluna 31
                            "data": "DATA_ULTIMO_BM"
                        },
                        { //coluna 32
                            "data": "DATA_ULTIMA_VISTORIA"
                        },
                        { //coluna 33
                            "data": "DESCRICAO_AREA"
                        },
                        { //coluna 34
                            "data": "DESCRICAO_MODALIDADE"
                        },
                        { //coluna 35
                            "data": "DESCRICAO_OBJETIVO"
                        },
                        { //coluna 36
                            "data": "DATA_TERMINO_OBRA"
                        },
                        { //coluna 37
                            "data": "SITUACAO_ATUAL"
                        },
                        { //coluna 38
                            "data": "DT_ATUALIZACAO_SITUACAO_ATUAL"
                        },
                        { //coluna 38
                            "data": "data_final"
                        },
                        { //coluna 38
                            "data": "score"
                        },
                        { //coluna 38
                            "data": "estagio_cronograma"
                        }
                    ],
                    columnDefs: [{
                            className: "0",
                            target: 0,
                            visible: controlaVisible(0)
                        },
                        {
                            className: "1",
                            target: 1,
                            visible: controlaVisible(1)
                        },
                        {
                            className: "2",
                            target: 2,
                            visible: controlaVisible(2)
                        },
                        {
                            className: "3",
                            target: 3,
                            visible: controlaVisible(3)
                        },
                        {
                            className: "4",
                            target: 4,
                            visible: controlaVisible(4)
                        },
                        {
                            className: "5",
                            target: 5,
                            visible: controlaVisible("5")
                        },
                        {
                            className: "6",
                            target: 6,
                            visible: controlaVisible("6")
                        },
                        {
                            className: "7",
                            target: 7,
                            visible: controlaVisible("7")
                        },
                        {
                            className: "8",
                            target: 8,
                            visible: controlaVisible("8")
                        },
                        {
                            className: "9",
                            target: 9,
                            visible: controlaVisible("9")
                        },
                        {
                            className: "10",
                            target: 10,
                            visible: controlaVisible("10")
                        },
                        {
                            className: "11",
                            target: 11,
                            visible: controlaVisible("11")
                        },
                        {
                            className: "12",
                            target: 12,
                            visible: controlaVisible("12")
                        },
                        {
                            className: "13",
                            target: 13,
                            visible: controlaVisible("13")
                        },
                        {
                            className: "14",
                            target: 14,
                            visible: controlaVisible("14")
                        },
                        {
                            className: "15",
                            target: 15,
                            visible: controlaVisible("15")
                        },
                        {
                            className: "16",
                            target: 16,
                            visible: controlaVisible("16")
                        },
                        {
                            className: "17",
                            target: 17,
                            visible: controlaVisible("17")
                        },
                        {
                            className: "18",
                            target: 18,
                            visible: controlaVisible("18")
                        },
                        {
                            className: "19",
                            target: 19,
                            visible: controlaVisible("19")
                        },
                        {
                            className: "20",
                            target: 20,
                            visible: controlaVisible("20")
                        },
                        {
                            className: "21",
                            target: 21,
                            visible: controlaVisible("21")
                        },
                        {
                            className: "22",
                            target: 22,
                            visible: controlaVisible("22")
                        },
                        {
                            className: "23",
                            target: 23,
                            visible: controlaVisible("23")
                        },
                        {
                            className: "24",
                            target: 24,
                            visible: controlaVisible("24")
                        },
                        {
                            className: "25",
                            target: 25,
                            visible: controlaVisible("25")
                        },
                        {
                            className: "26",
                            target: 26,
                            visible: controlaVisible("26")
                        },
                        {
                            className: "27",
                            target: 27,
                            visible: controlaVisible("27")
                        },
                        {
                            className: "28",
                            target: 28,
                            visible: controlaVisible("28")
                        },
                        {
                            className: "29",
                            target: 29,
                            visible: controlaVisible("29")
                        },
                        {
                            className: "30",
                            target: 30,
                            visible: controlaVisible("30")
                        },
                        {
                            className: "31",
                            target: 31,
                            visible: controlaVisible("31")
                        },
                        {
                            className: "32",
                            target: 32,
                            visible: controlaVisible("32")
                        },
                        {
                            className: "33",
                            target: 33,
                            visible: controlaVisible("33")
                        },
                        {
                            className: "34",
                            target: 34,
                            visible: controlaVisible("34")
                        },
                        {
                            className: "35",
                            target: 35,
                            visible: controlaVisible("35")
                        },
                        {
                            className: "36",
                            target: 36,
                            visible: controlaVisible("36")
                        },
                        {
                            className: "37",
                            target: 37,
                            visible: controlaVisible("37")
                        },
                        {
                            className: "38",
                            target: 38,
                            visible: controlaVisible("38")
                        },
                        {
                            className: "39",
                            target: 39,
                            visible: controlaVisible("39")
                        },
                        {
                            className: "39",
                            target: 39,
                            visible: controlaVisible("39")
                        }
                    ],
                    "rowCallback": function(row, data, index) {
                        
                        if(data.cor){
                            let cor = data.cor.toLowerCase();

                            // Mapa simples de cores nomeadas → rgba clarinho
                            const coresClarinha = {
                                "red": "rgba(255, 0, 0, 0.2)",
                                "green": "rgba(0, 128, 0, 0.2)",
                                "yellow": "rgba(255, 255, 0, 0.3)",
                                "blue": "rgba(0, 0, 255, 0.2)",
                                "orange": "rgba(255, 165, 0, 0.2)",
                                "purple": "rgba(128, 0, 128, 0.2)",
                                "pink": "rgba(255, 192, 203, 0.3)"
                            };
                        
                            // Se existir no mapa usa ele, senão aplica a cor original (fallback)
                            let corFinal = coresClarinha[cor] || data.cor;
                        
                            $(row).css('background-color', corFinal);
                        }
                        
                        /*
                        // Verifica se a célula da coluna específica (índice 0) está vazia
                        if (data['SituacaoFiscalizacao'] === "Não-Conformidades leves") {
                            // Adiciona uma classe ou estiliza a linha inteira
                            $(row).css('background-color', '#FFFFDD');
                        } else if (data['SituacaoFiscalizacao'] === "Não-Conformidades graves") {
                            // Adiciona uma classe ou estiliza a linha inteira
                            $(row).css('background-color', '#FFDDDD');
                        } else if (data['SituacaoFiscalizacao'] === "Obra em conformidade") {
                            // Adiciona uma classe ou estiliza a linha inteira
                            $(row).css('background-color', '#DDFFDD');
                        }
                        */
                    }
                });

                // Função para controlar a visibilidade das colunas
                $('.column-toggle').on('change', function() {
                    var column2 = table.column($(this).data('column')); // Pega o índice da coluna
                    column2.visible($(this).is(':checked')); // Define a visibilidade
                });

                //================================================================================

                // Adiciona o evento de clique para selecionar a linha
                $('#example tbody').on('click', 'tr', function() {

                    var dataT2 = table.row(this).data(); // Obtém os dados da linha clicada

                    window.location.href = "cadastroObra.php?obra=" + dataT2["IDObra"]
                });
                //================================================================================

                // Função para aplicar o filtro
                function applyCustomFilter() {

                    var umCol = $('#selectFilter1').val(); // Coluna selecionada para Quadra
                    var doisCol = $('#selectFilter2').val(); // Coluna selecionada para Lote
                    var tresCol = $('#selectFilter3').val();
                    var quatroCol = $('#selectFilter4').val();
                    var cincoCol = $('#selectFilter5').val();
                    var seisCol = $('#selectFilter6').val();

                    var umVal = $('#spanUm').val(); // Valor do campo de input Quadra
                    var doisVal = $('#spanDois').val(); // Valor do campo de input Lote
                    var tresVal = $('#spanTres').val();
                    var quatroVal = $('#spanQuatro').val();
                    var cincoVal = $('#spanCinco').val();
                    var seisVal = $('#spanSeis').val();

                    // Limpar filtros antes de aplicar novos
                    table.columns().search('');

                    // Função para aplicar o filtro baseado em qualquer coluna
                    function applyFilterByColumn(col, val) {
                        if (val !== '') {
                            // Se o select for 7 ou 8, fazer filtragem exata para números
                            if (col == "7" || col == "8") {
                                if (isNumeric(val)) {
                                    let regexPattern = getNumberPattern(val);
                                    table.column(col).search(regexPattern, true, false); // Usa regex para busca exata
                                } else {
                                    table.column(col).search(val); // Busca normal, sem regex
                                }
                            }
                            // Se o select for 0, aplicar lógica especial de múltiplos valores separados por vírgula
                            else if (col == "0") {
                                var valArray = val.split(',').map(v => v.trim()); // Criar array de valores, removendo espaços

                                if (valArray.length === 1) {
                                    // Se houver apenas um valor, fazer filtragem parcial para strings
                                    table.column(col).search(valArray[0]); // Busca normal, sem regex
                                } else {
                                    // Se houver múltiplos valores, criar regex para cada valor
                                    var regexPatterns = valArray.map(v => getNumberPattern(v));
                                    if (regexPatterns.length > 0) {
                                        // Combinar os regexs com a lógica OR
                                        let combinedPattern = regexPatterns.join('|');
                                        table.column(col).search(combinedPattern, true, false); // Usa regex combinada
                                    }
                                }
                            }
                            // Caso contrário, fazer a filtragem normal
                            else {
                                table.column(col).search(val); // Busca normal para strings
                            }
                        }
                    }

                    // Aplicar filtros para todos os selects e inputs
                    applyFilterByColumn(umCol, umVal);
                    applyFilterByColumn(doisCol, doisVal);
                    applyFilterByColumn(tresCol, tresVal);
                    applyFilterByColumn(quatroCol, quatroVal);
                    applyFilterByColumn(cincoCol, cincoVal);
                    applyFilterByColumn(seisCol, seisVal);

                    // Reaplicar a busca global se algum input estiver vazio
                    if (umCol === 'n' && umVal !== '') {
                        table.search(umVal);
                    }

                    if (doisCol === 'n' && doisVal !== '') {
                        table.search(doisVal);
                    }

                    if (tresCol === 'n' && tresVal !== '') {
                        table.search(tresVal);
                    }

                    if (quatroCol === 'n' && quatroVal !== '') {
                        table.search(quatroVal);
                    }

                    if (cincoCol === 'n' && cincoVal !== '') {
                        table.search(cincoVal);
                    }

                    if (seisCol === 'n' && seisVal !== '') {
                        table.search(seisVal);
                    }

                    table.draw(); // Recarregar a tabela com o novo filtro

                }

                applyCustomFilter();

                // Quando o usuário digitar ou mudar os selects, aplicar o filtro
                $('#spanUm, #spanDois, #spanTres, #spanQuatro, #spanCinco, #spanSeis, #selectFilter1, #selectFilter2, #selectFilter3, #selectFilter4, #selectFilter5, #selectFilter6').on('keyup change', function() {
                    applyCustomFilter();
                });


            });

        });
    </script>
</body>

</html>