<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Usuário não autenticado']);
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$databasePath = '../../database/TesteOlga.db';

try {
    if ($user_role === 'freelancer') {
        $stmt = $db->prepare("SELECT profile_id FROM FreelancerProfiles WHERE user_id = :user_id");
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $profile_id = $row['profile_id'];
            // CONSULTA CORRIGIDA: agora as conversas são separadas corretamente por restaurant_id
            $stmt = $db->prepare("
            SELECT 
                c.conversation_id,
                r.restaurant_name as name,
                u.profile_image_url,
                s.title as job_title,
                (SELECT COUNT(*) FROM Messages WHERE conversation_id = c.conversation_id AND sender_id != :user_id AND is_read = 0) as unread_count,
                (SELECT message_text FROM Messages WHERE conversation_id = c.conversation_id ORDER BY created_at DESC LIMIT 1) as last_message,
                (SELECT created_at FROM Messages WHERE conversation_id = c.conversation_id ORDER BY created_at DESC LIMIT 1) as last_message_time
            FROM Conversations c
            JOIN RestaurantProfiles r ON c.restaurant_id = r.restaurant_id
            JOIN Users u ON r.user_id = u.user_id
            LEFT JOIN Services s ON c.job_id = s.service_id
            WHERE c.freelancer_id = :profile_id
            ORDER BY last_message_time DESC
        ");
            $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
            $stmt->bindValue(':profile_id', $profile_id, SQLITE3_INTEGER);
        }
    } else if ($user_role === 'restaurant') {
        $stmt = $db->prepare("SELECT restaurant_id FROM RestaurantProfiles WHERE user_id = :user_id");
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $profile_id = $row['restaurant_id'];
            $stmt = $db->prepare("
            SELECT 
                c.conversation_id,
                u.first_name || ' ' || u.last_name as name,
                u.profile_image_url,
                s.title as job_title,
                (SELECT COUNT(*) FROM Messages WHERE conversation_id = c.conversation_id AND sender_id != :user_id AND is_read = 0) as unread_count,
                (SELECT message_text FROM Messages WHERE conversation_id = c.conversation_id ORDER BY created_at DESC LIMIT 1) as last_message,
                (SELECT created_at FROM Messages WHERE conversation_id = c.conversation_id ORDER BY created_at DESC LIMIT 1) as last_message_time
            FROM Conversations c
            JOIN FreelancerProfiles f ON c.freelancer_id = f.profile_id
            JOIN Users u ON f.user_id = u.user_id
            LEFT JOIN Services s ON c.job_id = s.service_id
            WHERE c.restaurant_id = :profile_id
            ORDER BY last_message_time DESC
        ");
            $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
            $stmt->bindValue(':profile_id', $profile_id, SQLITE3_INTEGER);
        }
    }

    if (isset($stmt)) {
        $result = $stmt->execute();
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            // Fix para SQLite que não tem CONCAT
            if ($user_role === 'restaurant' && !isset($row['name'])) {
                // Caso o CONCAT não tenha funcionado no SQLite
                $stmt2 = $db->prepare("SELECT first_name, last_name FROM Users 
                                    JOIN FreelancerProfiles ON Users.user_id = FreelancerProfiles.user_id
                                    WHERE FreelancerProfiles.profile_id = (
                                        SELECT freelancer_id FROM Conversations WHERE conversation_id = :conversation_id
                                    )");
                $stmt2->bindValue(':conversation_id', $row['conversation_id'], SQLITE3_INTEGER);
                $result2 = $stmt2->execute();
                if ($userRow = $result2->fetchArray(SQLITE3_ASSOC)) {
                    $row['name'] = $userRow['first_name'] . ' ' . $userRow['last_name'];
                }
            }
            $conversations[] = $row;
        }
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'conversations' => $conversations]);
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Erro ao buscar conversas: ' . $e->getMessage()]);
}

// Fechar a conexão com o banco de dados
$db = null;
?>