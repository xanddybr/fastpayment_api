<?php
require 'vendor/autoload.php';
use App\Config\Database;

Dotenv\Dotenv::createImmutable(__DIR__)->load();

$db = Database::getConnection();

$queries = [
    "ALTER TABLE events ADD COLUMN IF NOT EXISTS slug VARCHAR(255) UNIQUE",
    "ALTER TABLE units ADD COLUMN IF NOT EXISTS slug VARCHAR(255) UNIQUE",
    "ALTER TABLE event_types ADD COLUMN IF NOT EXISTS slug VARCHAR(255) UNIQUE",
    "ALTER TABLE schedules ADD COLUMN IF NOT EXISTS event_type_id INT",
    "ALTER TABLE person_details ADD COLUMN birth_date DATE NULL",
    "ALTER TABLE anamnesis ADD COLUMN already_student TINYINT(1) DEFAULT 0"
];

foreach ($queries as $sql) {
    try {
        $db->exec($sql);
        echo "✅ Sucesso: $sql\n";
    } catch (Exception $e) {
        echo "❌ Erro ou já existe: " . $e->getMessage() . "\n";
    }
}