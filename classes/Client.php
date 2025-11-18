<?php
class Client {
    public static function create(string $name, ?string $contactName, ?string $city, ?string $phone): bool {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO clients (name, contact_name, city, phone) VALUES (?, ?, ?, ?)');
        try { return $stmt->execute([trim($name), $contactName, $city, $phone]); } catch (\PDOException $e) { return false; }
    }

    public static function all(): array {
        $pdo = Database::connection();
        return $pdo->query('SELECT id, name, contact_name, city, phone, created_at FROM clients ORDER BY name ASC')->fetchAll();
    }

    public static function findById(int $id): ?array {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM clients WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $c = $stmt->fetch();
        return $c ?: null;
    }

    public static function update(int $id, string $name, ?string $contactName, ?string $city, ?string $phone): bool {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE clients SET name = ?, contact_name = ?, city = ?, phone = ? WHERE id = ?');
        try { return $stmt->execute([trim($name), $contactName, $city, $phone, $id]); } catch (\PDOException $e) { return false; }
    }

    public static function delete(int $id): bool {
        $pdo = Database::connection();
        // Bloqueia exclusão quando há recebimentos vinculados
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM receipts WHERE client_id = ?');
        $stmt->execute([$id]);
        $count = (int)$stmt->fetchColumn();
        if ($count > 0) { return false; }
        $del = $pdo->prepare('DELETE FROM clients WHERE id = ?');
        return $del->execute([$id]);
    }
}
?>