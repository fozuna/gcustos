<?php
session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/User.php';
require_once __DIR__ . '/classes/Auth.php';
require_once __DIR__ . '/classes/Company.php';
require_once __DIR__ . '/classes/CostGroup.php';
require_once __DIR__ . '/classes/Cost.php';
require_once __DIR__ . '/classes/Supplier.php';
require_once __DIR__ . '/classes/Client.php';
require_once __DIR__ . '/classes/Receipt.php';
require_once __DIR__ . '/classes/CostCenter.php';

// Inicializa banco e tabelas
Database::initialize();

// Seed dos grupos padrão apenas na primeira execução (quando não há grupos)
$defaultGroups = [
    'Encargos',
    'Salários',
    'Mão de Obra',
    'Custos Operacionais',
    'Materiais',
    'Locação',
    'Máquinas e Veículos',
    'Empreiteiros',
    'Pneus'
];
if (CostGroup::countAll() === 0) {
    CostGroup::seedDefaults($defaultGroups, null);
}

// Cria usuário padrão na primeira execução (apenas se nenhum existir)
if (User::countAll() === 0) {
    User::create('Administrador', 'admin@gcustos.local', 'admin123');
}

function require_auth(): void {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . 'index.php');
        exit;
    }
}

function h(?string $v): string { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

function format_currency($value): string {
    return 'R$ ' . number_format((float)$value, 2, ',', '.');
}
?>
