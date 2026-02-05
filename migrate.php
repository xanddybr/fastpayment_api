<?php
require 'vendor/autoload.php';
use App\Config\Database;

$db = Database::getConnection();

$queries = [
    "ALTER TABLE events ADD COLUMN IF NOT EXISTS slug VARCHAR(255) UNIQUE",
    "ALTER TABLE units ADD COLUMN IF NOT EXISTS slug VARCHAR(255) UNIQUE",
    "ALTER TABLE event_types ADD COLUMN IF NOT EXISTS slug VARCHAR(255) UNIQUE",
    "ALTER TABLE schedules ADD COLUMN IF NOT EXISTS event_type_id INT"
];

foreach ($queries as $sql) {
    try {
        $db->exec($sql);
        echo "✅ Sucesso: $sql\n";
    } catch (Exception $e) {
        echo "❌ Erro ou já existe: " . $e->getMessage() . "\n";
    }
}