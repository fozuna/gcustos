<?php
class Receipt {
    public static function create(int $userId, int $clientId, ?int $centerId, string $date, float $amount, ?string $notes): bool {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO receipts (user_id, client_id, cost_center_id, receipt_date, amount, notes) VALUES (?, ?, ?, ?, ?, ?)');
        try { return $stmt->execute([$userId, $clientId, $centerId, $date, $amount, $notes]); } catch (\PDOException $e) { return false; }
    }

    public static function findById(int $id): ?array {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT r.*, c.name AS client_name FROM receipts r JOIN clients c ON c.id = r.client_id WHERE r.id = ? LIMIT 1');
        $stmt->execute([$id]);
        $rec = $stmt->fetch();
        return $rec ?: null;
    }

    public static function update(int $id, int $clientId, ?int $centerId, string $date, float $amount, ?string $notes): bool {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE receipts SET client_id = ?, cost_center_id = ?, receipt_date = ?, amount = ?, notes = ? WHERE id = ?');
        try { return $stmt->execute([$clientId, $centerId, $date, $amount, $notes, $id]); } catch (\PDOException $e) { return false; }
    }

    public static function delete(int $id): bool {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('DELETE FROM receipts WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public static function recentForUser(int $userId, int $limit = 10): array {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT r.id, r.receipt_date, r.amount, r.notes, c.name AS client_name FROM receipts r JOIN clients c ON c.id = r.client_id WHERE r.user_id = ? ORDER BY r.receipt_date DESC, r.id DESC LIMIT ?');
        $stmt->bindValue(1, $userId, \PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // Resumo diário por intervalo de datas para um usuário
    public static function dailySummaryForRangeUser(int $userId, string $startDate, string $endDate): array {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT DATE(r.receipt_date) as day, SUM(r.amount) as total
            FROM receipts r
            WHERE r.user_id = ? AND r.receipt_date BETWEEN ? AND ?
            GROUP BY DATE(r.receipt_date)
            ORDER BY day');
        $stmt->execute([$userId, $startDate, $endDate]);
        return $stmt->fetchAll();
    }

    // Total de receitas acumuladas até antes de uma data (saldo inicial)
    public static function totalUntilUser(int $userId, string $date): float {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM receipts WHERE user_id = ? AND receipt_date < ?');
        $stmt->execute([$userId, $date]);
        return (float)$stmt->fetchColumn();
    }
}
?>