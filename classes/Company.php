<?php
class Company {
    public static function all(): array {
        $pdo = Database::connection();
        return $pdo->query('SELECT id, name, document, city, phone FROM companies ORDER BY name')->fetchAll();
    }

    public static function countAll(): int {
        $pdo = Database::connection();
        return (int)$pdo->query('SELECT COUNT(*) FROM companies')->fetchColumn();
    }

    public static function findById(int $id): ?array {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id, name, document, city, phone FROM companies WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(string $name, ?string $document, ?string $city, ?string $phone): bool {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO companies (name, document, city, phone) VALUES (?, ?, ?, ?)');
        try { return $stmt->execute([trim($name), $document, $city, $phone]); } catch (\PDOException $e) { return false; }
    }

    public static function update(int $id, string $name, ?string $document, ?string $city, ?string $phone): bool {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE companies SET name = ?, document = ?, city = ?, phone = ? WHERE id = ?');
        try { return $stmt->execute([trim($name), $document, $city, $phone, $id]); } catch (\PDOException $e) { return false; }
    }

    public static function delete(int $id): bool {
        $pdo = Database::connection();
        $stmtCnt = $pdo->prepare('SELECT COUNT(*) FROM cost_groups WHERE company_id = ?');
        $stmtCnt->execute([$id]);
        if ((int)$stmtCnt->fetchColumn() > 0) { return false; }
        $stmt = $pdo->prepare('DELETE FROM companies WHERE id = ?');
        try { return $stmt->execute([$id]); } catch (\PDOException $e) { return false; }
    }
}
?>