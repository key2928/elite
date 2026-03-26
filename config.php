<?php
session_start();

// Dados que você enviou
$host = 'localhost';
$db   = 'iubsit15_academia';
$user = 'iubsit15_academiuser';
$pass = '@Vanvan123'; // Sua senha

try {
     $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
     $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
     die("Erro ao conectar ao banco: " . $e->getMessage());
}

function e($string) { return htmlspecialchars($string, ENT_QUOTES, 'UTF-8'); }
?>