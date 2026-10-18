<?php
/**
 * 蓝屏钙软件导航网 - 敏感词违禁词过滤服务
 */

class FilterService {
    private static ?array $words = null;

    public static function loadWords(): array {
        if (self::$words !== null) {
            return self::$words;
        }

        try {
            $rows = Database::fetchAll("SELECT word, replacement FROM sensitive_words");
            self::$words = $rows;
        } catch (Throwable $e) {
            self::$words = [];
        }
        return self::$words;
    }

    public static function filter(string $text): string {
        $words = self::loadWords();
        foreach ($words as $w) {
            $word = $w['word'];
            $rep = $w['replacement'] ?: '***';
            $text = str_ireplace($word, $rep, $text);
        }
        return $text;
    }

    public static function containsBanned(string $text): bool {
        $words = self::loadWords();
        foreach ($words as $w) {
            if (mb_stripos($text, $w['word']) !== false) {
                return true;
            }
        }
        return false;
    }

    public static function addWords($input, string $replacement = '***'): int {
        if (is_string($input)) {
            $lines = preg_split('/[\r\n]+/', $input);
        } else {
            $lines = (array)$input;
        }

        $count = 0;
        $existing = array_column(self::loadWords(), 'word');
        $existingLower = array_map('mb_strtolower', $existing);

        foreach ($lines as $line) {
            $w = trim((string)$line);
            if ($w === '') continue;
            if (in_array(mb_strtolower($w), $existingLower, true)) continue;

            Database::query("INSERT INTO sensitive_words (word, replacement) VALUES (:w, :r)", [
                'w' => $w,
                'r' => $replacement
            ]);
            $existingLower[] = mb_strtolower($w);
            $count++;
        }

        self::$words = null;
        return $count;
    }

    public static function deleteWord(int $id): void {
        Database::delete('sensitive_words', 'id = :id', ['id' => $id]);
        self::$words = null;
    }
}
