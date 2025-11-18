<?php
class Database {
    private static ?\PDO $pdo = null;
    private static function ensureInnoDB(\PDO $pdo, string $table): void {
        $stmt = $pdo->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?');
        $stmt->execute([DB_NAME, $table]);
        $engine = $stmt->fetchColumn();
        if ($engine && strtoupper($engine) !== 'INNODB') {
            $pdo->exec("ALTER TABLE `{$table}` ENGINE=InnoDB");
        }
    }
    private static function idColumnType(\PDO $pdo, string $table): string {
        $stmt = $pdo->prepare('SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = "id"');
        $stmt->execute([DB_NAME, $table]);
        $type = $stmt->fetchColumn();
        return $type ? $type : 'INT';
    }

    public static function connection(): \PDO {
        if (self::$pdo === null) {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $options = [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ];
            try {
                self::$pdo = new \PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (\PDOException $e) {
                die('Erro na conexão com o banco: ' . $e->getMessage());
            }
        }
        return self::$pdo;
    }

    public static function initialize(): void {
        $pdo = self::connection();

        // Criar tabelas se não existirem
        $pdo->exec('CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(150) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        $pdo->exec('CREATE TABLE IF NOT EXISTS cost_groups (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        // Fornecedores (quem recebe pagamentos)
        $pdo->exec('CREATE TABLE IF NOT EXISTS suppliers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            contact_name VARCHAR(150) DEFAULT NULL,
            city VARCHAR(120) DEFAULT NULL,
            phone VARCHAR(60) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_supplier_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        // Centros de custos
        $pdo->exec('CREATE TABLE IF NOT EXISTS cost_centers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            nickname VARCHAR(100) DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_center_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        $pdo->exec('CREATE TABLE IF NOT EXISTS costs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            group_id INT NOT NULL,
            supplier_id INT DEFAULT NULL,
            cost_center_id INT DEFAULT NULL,
            cost_date DATE NOT NULL,
            description VARCHAR(255) NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_costs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_costs_group FOREIGN KEY (group_id) REFERENCES cost_groups(id) ON DELETE RESTRICT,
            CONSTRAINT fk_costs_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
            CONSTRAINT fk_costs_center FOREIGN KEY (cost_center_id) REFERENCES cost_centers(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        // Clientes (recebedores)
        $pdo->exec('CREATE TABLE IF NOT EXISTS clients (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            contact_name VARCHAR(150) DEFAULT NULL,
            city VARCHAR(120) DEFAULT NULL,
            phone VARCHAR(60) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_client_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        // Ajustes de integridade e FKs
        // Garantir que tabelas referenciadas estão em InnoDB antes de criar FKs
        self::ensureInnoDB($pdo, 'users');
        self::ensureInnoDB($pdo, 'clients');
        self::ensureInnoDB($pdo, 'suppliers');
        self::ensureInnoDB($pdo, 'cost_centers');

        $usersIdType = self::idColumnType($pdo, 'users');
        $clientsIdType = self::idColumnType($pdo, 'clients');

        $createReceiptsSql = 'CREATE TABLE IF NOT EXISTS receipts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id ' . $usersIdType . ' NOT NULL,
            client_id ' . $clientsIdType . ' NOT NULL,
            cost_center_id INT DEFAULT NULL,
            receipt_date DATE NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            notes TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_receipts_user (user_id),
            INDEX idx_receipts_client (client_id),
            INDEX idx_receipts_center (cost_center_id),
            CONSTRAINT fk_receipts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_receipts_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE RESTRICT,
            CONSTRAINT fk_receipts_center FOREIGN KEY (cost_center_id) REFERENCES cost_centers(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';

        try {
            $pdo->exec($createReceiptsSql);
        } catch (\PDOException $e) {
            // Se falhar por FK mal formada (errno 150), tentar ajustar e recriar
            if (strpos($e->getMessage(), 'errno: 150') !== false) {
                self::ensureInnoDB($pdo, 'users');
                self::ensureInnoDB($pdo, 'clients');
                $pdo->exec($createReceiptsSql);
            } else {
                throw $e;
            }
        }

        // Garantir coluna supplier_id em costs (para bases antigas)
        $stmtCol = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = "costs" AND COLUMN_NAME = "supplier_id"');
        $stmtCol->execute([DB_NAME]);
        $hasSupplierCol = (int)$stmtCol->fetchColumn() > 0;
        if (!$hasSupplierCol) {
            try {
                self::ensureInnoDB($pdo, 'suppliers');
                $suppliersIdType = self::idColumnType($pdo, 'suppliers');
                $pdo->exec('ALTER TABLE costs ADD COLUMN supplier_id ' . $suppliersIdType . ' DEFAULT NULL');
                $pdo->exec('ALTER TABLE costs ADD CONSTRAINT fk_costs_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL');
            } catch (\PDOException $e) { /* ignora se já existir ou não suportar */ }
        }

        // Garantir colunas cost_center_id em costs e receipts (para bases antigas)
        $stmtCostCenterCosts = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = "costs" AND COLUMN_NAME = "cost_center_id"');
        $stmtCostCenterCosts->execute([DB_NAME]);
        $hasCenterInCosts = (int)$stmtCostCenterCosts->fetchColumn() > 0;
        if (!$hasCenterInCosts) {
            try {
                self::ensureInnoDB($pdo, 'cost_centers');
                $pdo->exec('ALTER TABLE costs ADD COLUMN cost_center_id INT DEFAULT NULL');
                $pdo->exec('ALTER TABLE costs ADD CONSTRAINT fk_costs_center FOREIGN KEY (cost_center_id) REFERENCES cost_centers(id) ON DELETE SET NULL');
            } catch (\PDOException $e) { /* ignora */ }
        }
        $stmtCostCenterReceipts = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = "receipts" AND COLUMN_NAME = "cost_center_id"');
        $stmtCostCenterReceipts->execute([DB_NAME]);
        $hasCenterInReceipts = (int)$stmtCostCenterReceipts->fetchColumn() > 0;
        if (!$hasCenterInReceipts) {
            try {
                self::ensureInnoDB($pdo, 'cost_centers');
                $pdo->exec('ALTER TABLE receipts ADD COLUMN cost_center_id INT DEFAULT NULL');
                $pdo->exec('ALTER TABLE receipts ADD CONSTRAINT fk_receipts_center FOREIGN KEY (cost_center_id) REFERENCES cost_centers(id) ON DELETE SET NULL');
            } catch (\PDOException $e) { /* ignora */ }
        }
    }
}
?>