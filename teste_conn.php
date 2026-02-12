<?php
require __DIR__ . '/vendor/autoload.php';

use App\Config\Database;

try {
    $db1 = Database::getConnection();
    $db2 = Database::getConnection();

    echo "Conexão 1 ID: " . spl_object_id($db1) . "<br>";
    echo "Conexão 2 ID: " . spl_object_id($db2) . "<br>";

    if (spl_object_id($db1) === spl_object_id($db2)) {
        echo "<b style='color:green'>✅ SUCESSO: O Singleton está retornando a mesma instância!</b>";
    } else {
        echo "<b style='color:red'>❌ ERRO: O Singleton está criando instâncias diferentes!</b>";
    }
} catch (Exception $e) {
    echo "<b style='color:red'>❌ FALHA NA CONEXÃO: </b>" . $e->getMessage();
}