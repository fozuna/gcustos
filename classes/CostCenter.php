<?php
class CostCenter {
    public static function all(): array {
        $pdo = Database::connection();
        return $pdo->query('SELECT id, name, nickname FROM cost_centers ORDER BY name')->fetchAll();
    }

    public static function countAll(): int {
        $pdo = Database::connection();
        return (int)$pdo->query('SELECT COUNT(*) FROM cost_centers')->fetchColumn();
    }

    public static function findById(int $id): ?array {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id, name, nickname, notes FROM cost_centers WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(string $name, ?string $nickname = null, ?string $notes = null): bool {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO cost_centers (name, nickname, notes) VALUES (?, ?, ?)');
        try { return $stmt->execute([trim($name), $nickname ?: null, $notes ?: null]); }
        catch (\PDOException $e) { return false; }
    }

    public static function update(int $id, string $name, ?string $nickname = null, ?string $notes = null): bool {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE cost_centers SET name = ?, nickname = ?, notes = ? WHERE id = ?');
        try { return $stmt->execute([trim($name), $nickname ?: null, $notes ?: null, $id]); }
        catch (\PDOException $e) { return false; }
    }

    public static function delete(int $id): bool {
        $pdo = Database::connection();
        $stmtC = $pdo->prepare('SELECT COUNT(*) FROM costs WHERE cost_center_id = ?');
        $stmtC->execute([$id]);
        if ((int)$stmtC->fetchColumn() > 0) { return false; }

        $stmtR = $pdo->prepare('SELECT COUNT(*) FROM receipts WHERE cost_center_id = ?');
        $stmtR->execute([$id]);
        if ((int)$stmtR->fetchColumn() > 0) { return false; }

        $stmt = $pdo->prepare('DELETE FROM cost_centers WHERE id = ?');
        try { return $stmt->execute([$id]); }
        catch (\PDOException $e) { return false; }
    }
}
?>