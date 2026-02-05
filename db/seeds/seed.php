
<?php
require 'vendor/autoload.php';
use App\Config\Database;

$db = Database::getConnection();

echo "🌱 Iniciando o Seeding do banco de dados...\n";

// Dados para popular as Unidades
$units = [
    ['name' => 'Unidade Centro', 'slug' => 'unidade-centro'],
    ['name' => 'Unidade Jardins', 'slug' => 'unidade-jardins']
];

// Dados para os Tipos de Evento
$types = [
    ['name' => 'Workshop', 'slug' => 'workshop'],
    ['name' => 'Palestra', 'slug' => 'palestra'],
    ['name' => 'Curso Presencial', 'slug' => 'curso-presencial']
];

try {
    // 1. Populando Unidades
    $stmtUnit = $db->prepare("INSERT IGNORE INTO units (name, slug) VALUES (:name, :slug)");
    foreach ($units as $unit) {
        $stmtUnit->execute($unit);
        echo "✅ Unidade adicionada: {$unit['name']}\n";
    }

    // 2. Populando Tipos de Evento
    $stmtType = $db->prepare("INSERT IGNORE INTO event_types (name, slug) VALUES (:name, :slug)");
    foreach ($types as $type) {
        $stmtType->execute($type);
        echo "✅ Tipo adicionado: {$type['name']}\n";
    }

    echo "\n🚀 Banco de dados semeado com sucesso!\n";

} catch (Exception $e) {
    echo "❌ Erro ao semear banco: " . $e->getMessage() . "\n";
}