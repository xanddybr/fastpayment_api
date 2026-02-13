<?php
    
namespace App\Utils;

trait Slugger {
    
    public function generateUniqueSlug($name, $table, $db) {
        $slug = $this->createSlug($name);
        $baseSlug = $slug;
        $i = 1;

        while (true) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM $table WHERE slug = :slug");
            $stmt->execute([':slug' => $slug]);
            if ($stmt->fetchColumn() == 0) break;

            $slug = $baseSlug . '-' . $i;
            $i++;
        }
        return $slug;
    }

    private function createSlug($text) {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        return strtolower($text ?: 'n-a');
    }
}