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
if (!isset($_GET['conversation_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Parâmetros insuficientes']);
    exit();
}

$conversation_id = (int)$_GET['conversation_id'];
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

// Verificar se a tabela TypingStatus existe
try {
    $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='TypingStatus'");
    $table_exists = $result->fetchArray(SQLITE3_ASSOC) !== false;
    
    if (!$table_exists) {
        header('Content-Type: application/json');
        echo json_encode(['is_typing' => false]);
        exit();
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Erro ao verificar tabela: ' . $e->getMessage()]);
    exit();
}

// Verificar o status de digitação do outro usuário
try {
    // Procurar o usuário com quem estamos conversando
    if ($_SESSION['user_role'] === 'freelancer') {
        $stmt = $db->prepare("SELECT r.user_id 
                            FROM Conversations c 
                            JOIN RestaurantProfiles r ON c.restaurant_id = r.restaurant_id 
                            WHERE c.conversation_id = :conversation_id");
    } else {
        $stmt = $db->prepare("SELECT f.user_id 
                            FROM Conversations c 
                            JOIN FreelancerProfiles f ON c.freelancer_id = f.profile_id 
                            WHERE c.conversation_id = :conversation_id");
    }
    
    $stmt->bindValue(':conversation_id', $conversation_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $other_user_id = null;
    
    if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $other_user_id = $row['user_id'];
    }
    
    if ($other_user_id) {
        // Verificar o status de digitação do outro usuário
        $stmt = $db->prepare("SELECT is_typing, updated_at 
                            FROM TypingStatus 
                            WHERE conversation_id = :conversation_id AND user_id = :other_user_id");
        $stmt->bindValue(':conversation_id', $conversation_id, SQLITE3_INTEGER);
        $stmt->bindValue(':other_user_id', $other_user_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        
        if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $is_typing = (bool)$row['is_typing'];
            
            // Verificar se o status de digitação é recente (dentro dos últimos 5 segundos)
            $updated_at = strtotime($row['updated_at']);
            $now = time();
            
            if ($now - $updated_at > 3) {
                $is_typing = false;
            }
            
            header('Content-Type: application/json');
            echo json_encode(['is_typing' => $is_typing]);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['is_typing' => false]);
        }
    } else {
        header('Content-Type: application/json');
        echo json_encode(['is_typing' => false]);
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Erro ao verificar status de digitação: ' . $e->getMessage()]);
}

// Fechar a conexão com o banco de dados
$db = null;
?>