<?php
class Supplier {
    public static function all(): array {
        $pdo = Database::connection();
        return $pdo->query('SELECT id, name, contact_name, city, phone, created_at FROM suppliers ORDER BY name ASC')->fetchAll();
    }

    public static function findById(int $id): ?array {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM suppliers WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $s = $stmt->fetch();
        return $s ?: null;
    }

    public static function create(string $name, ?string $contact_name = null, ?string $city = null, ?string $phone = null): bool {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO suppliers (name, contact_name, city, phone) VALUES (?, ?, ?, ?)');
        try { return $stmt->execute([trim($name), $contact_name, $city, $phone]); }
        catch (\PDOException $e) { return false; }
    }

    public static function update(int $id, string $name, ?string $contact_name = null, ?string $city = null, ?string $phone = null): bool {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE suppliers SET name = ?, contact_name = ?, city = ?, phone = ? WHERE id = ?');
        try { return $stmt->execute([trim($name), $contact_name, $city, $phone, $id]); }
        catch (\PDOException $e) { return false; }
    }

    public static function delete(int $id): bool {
        $pdo = Database::connection();
        // Não permite excluir se estiver vinculado a costs (FK já protege, mas tratamos mensagem)
        $stmt = $pdo->prepare('DELETE FROM suppliers WHERE id = ?');
        try { return $stmt->execute([$id]); }
        catch (\PDOException $e) { return false; }
    }
}
?>