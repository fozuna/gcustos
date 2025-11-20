<?php
class CostGroup {
    public static function countAll(): int {
        $pdo = Database::connection();
        $count = (int)$pdo->query('SELECT COUNT(*) FROM cost_groups')->fetchColumn();
        return $count;
    }
    public static function all(): array {
        $pdo = Database::connection();
        return $pdo->query('SELECT cg.id, cg.name, cg.company_id, c.name AS company_name FROM cost_groups cg LEFT JOIN companies c ON c.id = cg.company_id ORDER BY cg.name')->fetchAll();
    }

    public static function findById(int $id): ?array {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT cg.id, cg.name, cg.company_id, c.name AS company_name FROM cost_groups cg LEFT JOIN companies c ON c.id = cg.company_id WHERE cg.id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(string $name, ?int $companyId = null): bool {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO cost_groups (name, company_id) VALUES (?, ?)');
        try {
            return $stmt->execute([trim($name), $companyId]);
        } catch (\PDOException $e) {
            return false;
        }
    }

    public static function update(int $id, string $name, ?int $companyId = null): bool {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE cost_groups SET name = ?, company_id = ? WHERE id = ?');
        try {
            return $stmt->execute([trim($name), $companyId, $id]);
        } catch (\PDOException $e) {
            return false;
        }
    }

    public static function delete(int $id): bool {
        $pdo = Database::connection();
        // Bloqueia exclusão se existirem custos vinculados ao grupo
        $stmtCnt = $pdo->prepare('SELECT COUNT(*) FROM costs WHERE group_id = ?');
        $stmtCnt->execute([$id]);
        $count = (int)$stmtCnt->fetchColumn();
        if ($count > 0) { return false; }

        $stmt = $pdo->prepare('DELETE FROM cost_groups WHERE id = ?');
        try { return $stmt->execute([$id]); }
        catch (\PDOException $e) { return false; }
    }

    public static function seedDefaults(array $names, ?int $companyId = null): void {
        $pdo = Database::connection();
        $stmtCheck = $pdo->prepare('SELECT id FROM cost_groups WHERE name = ?');
        $stmtInsert = $pdo->prepare('INSERT INTO cost_groups (name, company_id) VALUES (?, ?)');
        foreach ($names as $n) {
            $stmtCheck->execute([$n]);
            if (!$stmtCheck->fetch()) {
                try { $stmtInsert->execute([$n, $companyId]); } catch (\PDOException $e) { }
            }
        }
    }

    public static function findByName(string $name): ?array {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id, name FROM cost_groups WHERE name = ? LIMIT 1');
        $stmt->execute([trim($name)]);
        $g = $stmt->fetch();
        return $g ?: null;
    }
}
?>