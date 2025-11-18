<?php
class Cost {
    public static function findById(int $id): ?array {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id, user_id, group_id, supplier_id, cost_center_id, cost_date, description, amount FROM costs WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(int $userId, int $groupId, ?int $supplierId, ?int $centerId, string $date, string $description, float $amount): bool {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO costs (user_id, group_id, supplier_id, cost_center_id, cost_date, description, amount) VALUES (?, ?, ?, ?, ?, ?, ?)');
        try {
            return $stmt->execute([$userId, $groupId, $supplierId, $centerId, $date, trim($description), $amount]);
        } catch (\PDOException $e) {
            return false;
        }
    }

    public static function update(int $id, int $userId, int $groupId, ?int $supplierId, ?int $centerId, string $date, string $description, float $amount): bool {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE costs SET user_id = ?, group_id = ?, supplier_id = ?, cost_center_id = ?, cost_date = ?, description = ?, amount = ? WHERE id = ?');
        try {
            return $stmt->execute([$userId, $groupId, $supplierId, $centerId, $date, trim($description), $amount, $id]);
        } catch (\PDOException $e) {
            return false;
        }
    }

    public static function delete(int $id): bool {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('DELETE FROM costs WHERE id = ?');
        try {
            return $stmt->execute([$id]);
        } catch (\PDOException $e) {
            return false;
        }
    }

    public static function summaryByGroupForMonth(int $month, int $year): array {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT g.name as group_name, COALESCE(SUM(c.amount),0) as total
            FROM cost_groups g
            LEFT JOIN costs c ON c.group_id = g.id AND MONTH(c.cost_date) = ? AND YEAR(c.cost_date) = ?
            GROUP BY g.id, g.name
            ORDER BY g.name');
        $stmt->execute([$month, $year]);
        return $stmt->fetchAll();
    }

    public static function summaryByGroupForYear(int $year): array {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT g.name as group_name, COALESCE(SUM(c.amount),0) as total
            FROM cost_groups g
            LEFT JOIN costs c ON c.group_id = g.id AND YEAR(c.cost_date) = ?
            GROUP BY g.id, g.name
            ORDER BY g.name');
        $stmt->execute([$year]);
        return $stmt->fetchAll();
    }

    public static function dailySummaryForMonth(int $month, int $year, ?int $groupId = null): array {
        $pdo = Database::connection();
        if ($groupId) {
            $stmt = $pdo->prepare('SELECT DATE(c.cost_date) as day, SUM(c.amount) as total
                FROM costs c
                WHERE MONTH(c.cost_date) = ? AND YEAR(c.cost_date) = ? AND c.group_id = ?
                GROUP BY DATE(c.cost_date)
                ORDER BY day');
            $stmt->execute([$month, $year, $groupId]);
        } else {
            $stmt = $pdo->prepare('SELECT DATE(c.cost_date) as day, SUM(c.amount) as total
                FROM costs c
                WHERE MONTH(c.cost_date) = ? AND YEAR(c.cost_date) = ?
                GROUP BY DATE(c.cost_date)
                ORDER BY day');
            $stmt->execute([$month, $year]);
        }
        return $stmt->fetchAll();
    }

    // Resumo diário por intervalo de datas para um usuário
    public static function dailySummaryForRangeUser(int $userId, string $startDate, string $endDate): array {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT DATE(c.cost_date) as day, SUM(c.amount) as total
            FROM costs c
            WHERE c.user_id = ? AND c.cost_date BETWEEN ? AND ?
            GROUP BY DATE(c.cost_date)
            ORDER BY day');
        $stmt->execute([$userId, $startDate, $endDate]);
        return $stmt->fetchAll();
    }

    // Total de custos acumulados até antes de uma data (saldo inicial)
    public static function totalUntilUser(int $userId, string $date): float {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM costs WHERE user_id = ? AND cost_date < ?');
        $stmt->execute([$userId, $date]);
        return (float)$stmt->fetchColumn();
    }

    public static function groupTotalForMonth(int $groupId, int $month, int $year): array {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT g.name as group_name, COALESCE(SUM(c.amount),0) as total
            FROM cost_groups g
            LEFT JOIN costs c ON c.group_id = g.id AND MONTH(c.cost_date) = ? AND YEAR(c.cost_date) = ?
            WHERE g.id = ?
            GROUP BY g.id, g.name');
        $stmt->execute([$month, $year, $groupId]);
        $row = $stmt->fetch();
        return $row ? $row : ['group_name' => '', 'total' => 0];
    }

    public static function groupTotalForYear(int $groupId, int $year): array {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT g.name as group_name, COALESCE(SUM(c.amount),0) as total
            FROM cost_groups g
            LEFT JOIN costs c ON c.group_id = g.id AND YEAR(c.cost_date) = ?
            WHERE g.id = ?
            GROUP BY g.id, g.name');
        $stmt->execute([$year, $groupId]);
        $row = $stmt->fetch();
        return $row ? $row : ['group_name' => '', 'total' => 0];
    }

    public static function totalForMonth(int $month, int $year, ?int $groupId = null): float {
        $pdo = Database::connection();
        if ($groupId) {
            $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM costs WHERE MONTH(cost_date) = ? AND YEAR(cost_date) = ? AND group_id = ?');
            $stmt->execute([$month, $year, $groupId]);
        } else {
            $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM costs WHERE MONTH(cost_date) = ? AND YEAR(cost_date) = ?');
            $stmt->execute([$month, $year]);
        }
        return (float)$stmt->fetchColumn();
    }

    public static function totalForYear(int $year, ?int $groupId = null): float {
        $pdo = Database::connection();
        if ($groupId) {
            $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM costs WHERE YEAR(cost_date) = ? AND group_id = ?');
            $stmt->execute([$year, $groupId]);
        } else {
            $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM costs WHERE YEAR(cost_date) = ?');
            $stmt->execute([$year]);
        }
        return (float)$stmt->fetchColumn();
    }

    public static function monthlyTotalsForYear(int $year, ?int $groupId = null): array {
        $pdo = Database::connection();
        if ($groupId) {
            $stmt = $pdo->prepare('SELECT MONTH(c.cost_date) as month, SUM(c.amount) as total
                FROM costs c
                WHERE YEAR(c.cost_date) = ? AND c.group_id = ?
                GROUP BY MONTH(c.cost_date)
                ORDER BY month');
            $stmt->execute([$year, $groupId]);
        } else {
            $stmt = $pdo->prepare('SELECT MONTH(c.cost_date) as month, SUM(c.amount) as total
                FROM costs c
                WHERE YEAR(c.cost_date) = ?
                GROUP BY MONTH(c.cost_date)
                ORDER BY month');
            $stmt->execute([$year]);
        }
        $rows = $stmt->fetchAll();
        // Garante 12 meses
        $map = [];
        foreach ($rows as $r) { $map[(int)$r['month']] = (float)$r['total']; }
        $out = [];
        for ($m=1;$m<=12;$m++) { $out[] = ['month' => $m, 'total' => $map[$m] ?? 0]; }
        return $out;
    }

    public static function recent(int $limit = 10): array {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT c.id, c.cost_date, c.description, c.amount, g.name as group_name, s.name as supplier_name
            FROM costs c
            JOIN cost_groups g ON c.group_id = g.id
            LEFT JOIN suppliers s ON c.supplier_id = s.id
            ORDER BY c.created_at DESC
            LIMIT ?');
        $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function recentForUser(int $userId, int $limit = 10): array {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT c.id, c.cost_date, c.description, c.amount, g.name as group_name, s.name as supplier_name
            FROM costs c
            JOIN cost_groups g ON c.group_id = g.id
            LEFT JOIN suppliers s ON c.supplier_id = s.id
            WHERE c.user_id = ?
            ORDER BY c.created_at DESC
            LIMIT ?');
        $stmt->bindValue(1, $userId, \PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function recentForMonthGroup(int $month, int $year, ?int $groupId = null, int $limit = 10): array {
        $pdo = Database::connection();
        if ($groupId) {
            $stmt = $pdo->prepare('SELECT c.id, c.cost_date, c.description, c.amount, g.name as group_name, s.name as supplier_name
                FROM costs c
                JOIN cost_groups g ON c.group_id = g.id
                LEFT JOIN suppliers s ON c.supplier_id = s.id
                WHERE MONTH(c.cost_date) = ? AND YEAR(c.cost_date) = ? AND c.group_id = ?
                ORDER BY c.created_at DESC
                LIMIT ?');
            $stmt->bindValue(1, $month, \PDO::PARAM_INT);
            $stmt->bindValue(2, $year, \PDO::PARAM_INT);
            $stmt->bindValue(3, $groupId, \PDO::PARAM_INT);
            $stmt->bindValue(4, $limit, \PDO::PARAM_INT);
        } else {
            $stmt = $pdo->prepare('SELECT c.id, c.cost_date, c.description, c.amount, g.name as group_name, s.name as supplier_name
                FROM costs c
                JOIN cost_groups g ON c.group_id = g.id
                LEFT JOIN suppliers s ON c.supplier_id = s.id
                WHERE MONTH(c.cost_date) = ? AND YEAR(c.cost_date) = ?
                ORDER BY c.created_at DESC
                LIMIT ?');
            $stmt->bindValue(1, $month, \PDO::PARAM_INT);
            $stmt->bindValue(2, $year, \PDO::PARAM_INT);
            $stmt->bindValue(3, $limit, \PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
?>