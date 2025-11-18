<?php
class User {
    public static function create(string $name, string $email, string $password): bool {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO users (name, email, password) VALUES (?, ?, ?)');
        $hash = password_hash($password, PASSWORD_DEFAULT);
        try {
            return $stmt->execute([$name, strtolower(trim($email)), $hash]);
        } catch (\PDOException $e) {
            return false;
        }
    }

    public static function findById(int $id): ?array {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public static function findByEmail(string $email): ?array {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([strtolower(trim($email))]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public static function countAll(): int {
        $pdo = Database::connection();
        return (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }

    public static function all(): array {
        $pdo = Database::connection();
        return $pdo->query('SELECT id, name, email, created_at FROM users ORDER BY created_at DESC')->fetchAll();
    }

    public static function update(int $id, string $name, string $email, ?string $password = null): bool {
        $pdo = Database::connection();
        if ($password && $password !== '') {
            $stmt = $pdo->prepare('UPDATE users SET name = ?, email = ?, password = ? WHERE id = ?');
            $hash = password_hash($password, PASSWORD_DEFAULT);
            try { return $stmt->execute([$name, strtolower(trim($email)), $hash, $id]); }
            catch (\PDOException $e) { return false; }
        } else {
            $stmt = $pdo->prepare('UPDATE users SET name = ?, email = ? WHERE id = ?');
            try { return $stmt->execute([$name, strtolower(trim($email)), $id]); }
            catch (\PDOException $e) { return false; }
        }
    }

    public static function delete(int $id): bool {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
        try { return $stmt->execute([$id]); }
        catch (\PDOException $e) { return false; }
    }
}
?>