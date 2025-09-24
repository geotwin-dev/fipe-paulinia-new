<?php

if(!isset($_SESSION['usuario'])){
    header('Location: logout.php');
    exit();
}