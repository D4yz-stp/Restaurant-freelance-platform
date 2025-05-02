<?php
// Script para inicializar o banco de dados
$db_path = '../database/OlgaRJ.db'; // Ajuste este caminho conforme necessário
$db_dir = dirname($db_path);

// Criar diretório se não existir
if (!file_exists($db_dir)) {
    mkdir($db_dir, 0755, true);
}

try {
    // Criar nova conexão com o banco de dados
    $db = new SQLite3($db_path);
    
    // Habilitar chaves estrangeiras
    $db->exec('PRAGMA foreign_keys = ON;');
    
    // Criar tabela de usuários
    $db->exec('
    CREATE TABLE IF NOT EXISTS Users (
        user_id INTEGER PRIMARY KEY AUTOINCREMENT,
        first_name TEXT NOT NULL,
        last_name TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        contact TEXT NOT NULL,
        country TEXT,
        city TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_login TIMESTAMP
    );');
    
    // Criar tabela de funções/papéis
    $db->exec('
    CREATE TABLE IF NOT EXISTS Roles (
        role_id INTEGER PRIMARY KEY AUTOINCREMENT,
        role_name TEXT NOT NULL CHECK (role_name IN ("freelancer", "restaurant", "admin"))
    );');
    
    // Criar tabela de relação usuários-funções
    $db->exec('
    CREATE TABLE IF NOT EXISTS UserRoles (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        role_id INTEGER NOT NULL,
        FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE,
        FOREIGN KEY (role_id) REFERENCES Roles(role_id) ON DELETE CASCADE,
        UNIQUE(user_id, role_id)
    );');
    
    // Criar tabela de perfis de freelancers
    $db->exec('
    CREATE TABLE IF NOT EXISTS FreelancerProfiles (
        profile_id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER UNIQUE NOT NULL,
        hourly_rate REAL,
        availability TEXT DEFAULT "flexible",
        experience_years INTEGER,
        avg_rating REAL DEFAULT 0,
        FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE
    );');
    
    // Verificar se os papéis padrão existem e inseri-los se necessário
    $roles = ['freelancer', 'restaurant', 'admin'];
    foreach ($roles as $role) {
        $stmt = $db->prepare("SELECT role_id FROM Roles WHERE role_name = :role_name");
        $stmt->bindValue(':role_name', $role, SQLITE3_TEXT);
        $result = $stmt->execute();
        
        if (!$result->fetchArray()) {
            $stmt = $db->prepare("INSERT INTO Roles (role_name) VALUES (:role_name)");
            $stmt->bindValue(':role_name', $role, SQLITE3_TEXT);
            $stmt->execute();
            echo "Papel '$role' criado com sucesso.<br>";
        }
    }
    
    echo "Banco de dados inicializado com sucesso!";
    $db->close();
} catch (Exception $e) {
    die("Erro ao inicializar o banco de dados: " . $e->getMessage());
}
    
    // Criar tabela de perfis de restaurantes
    $db->exec('
    CREATE TABLE IF NOT EXISTS RestaurantProfiles (
        restaurant_id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER UNIQUE NOT NULL,
        restaurant_name TEXT NOT NULL,
        restaurant_type TEXT,
        description TEXT,
        avg_rating REAL DEFAULT 0,
        FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE
    );');