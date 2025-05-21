<?php
// Iniciar sessão
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Usuário não autenticado']);
    exit();
}

// Verificar se os parâmetros necessários foram fornecidos
if (!isset($_POST['conversation_id']) || !isset($_POST['is_typing'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Parâmetros insuficientes']);
    exit();
}

$conversation_id = (int)$_POST['conversation_id'];
$is_typing = (int)$_POST['is_typing'];
$user_id = $_SESSION['user_id'];

$databasePath = '../../database/TesteOlga.db';
if (!file_exists($databasePath)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Banco de dados não encontrado']);
    exit();
}

// Conexão com o banco de dados
try {
    $db = new SQLite3($databasePath);
    $db->busyTimeout(5000);
    $db->exec('PRAGMA foreign_keys = ON;');
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Erro na conexão com o banco de dados: ' . $e->getMessage()]);
    exit();
}

// Verificar se a tabela TypingStatus existe, se não, criar
try {
    $db->exec("CREATE TABLE IF NOT EXISTS TypingStatus (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        conversation_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        is_typing INTEGER NOT NULL DEFAULT 0,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (conversation_id) REFERENCES Conversations(conversation_id),
        FOREIGN KEY (user_id) REFERENCES Users(user_id),
        UNIQUE(conversation_id, user_id)
    )");
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Erro ao criar tabela: ' . $e->getMessage()]);
    exit();
}

// Atualizar ou inserir status de digitação
try {
    // Verificar se já existe um registro para este usuário nesta conversa
    $stmt = $db->prepare("
        REPLACE INTO TypingStatus 
        (conversation_id, user_id, is_typing, updated_at) 
        VALUES 
        (:conversation_id, :user_id, :is_typing, CURRENT_TIMESTAMP)
    ");
    $stmt->bindValue(':conversation_id', $conversation_id, SQLITE3_INTEGER);
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        // Atualizar o registro existente
        $stmt = $db->prepare("UPDATE TypingStatus 
                            SET is_typing = :is_typing, updated_at = CURRENT_TIMESTAMP
                            WHERE conversation_id = :conversation_id AND user_id = :user_id");
    } else {
        // Inserir um novo registro
        $stmt = $db->prepare("INSERT INTO TypingStatus (conversation_id, user_id, is_typing) 
                            VALUES (:conversation_id, :user_id, :is_typing)");
    }
    
    $stmt->bindValue(':conversation_id', $conversation_id, SQLITE3_INTEGER);
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $stmt->bindValue(':is_typing', $is_typing, SQLITE3_INTEGER);
    $stmt->execute();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Erro ao atualizar status de digitação: ' . $e->getMessage()]);
}

// Fechar a conexão com o banco de dados
$db = null;
?>