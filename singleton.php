<?php
require __DIR__ . '/vendor/autoload.php';

use App\Config\Database;
use App\Models\Person;

// 1. Pegamos a conexão diretamente da classe Database
$db1 = Database::getConnection();

// 2. Instanciamos o Model (que por dentro chama o Database::getConnection no BaseModel)
$personModel = new Person();

// 3. Pegamos a conexão que está dentro do Model via Reflection (já que é protected)
$reflection = new ReflectionClass($personModel);
$property = $reflection->getProperty('conn');
$property->setAccessible(true);
$db2 = $property->getValue($personModel);

echo "<h2>Teste de Singleton</h2>";

// Verificamos se os IDs dos objetos PDO são idênticos
echo "ID Conexão 1 (Database): " . spl_object_id($db1) . "<br>";
echo "ID Conexão 2 (Model Person): " . spl_object_id($db2) . "<br>";

if (spl_object_id($db1) === spl_object_id($db2)) {
    echo "<b style='color:green'>SUCESSO: O Singleton está funcionando! Ambas as classes usam a mesma instância.</b>";
} else {
    echo "<b style='color:red'>ERRO: O Singleton falhou! Estão sendo criadas conexões diferentes.</b>";
}