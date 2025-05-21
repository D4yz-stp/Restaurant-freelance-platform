<?php
// config.php
error_reporting(E_ALL);
ini_set('display_errors', 1); // Em produção, defina como 0 e use logs

session_start(); // Essencial para autenticação e manter o estado do utilizador

$databasePath = __DIR__ . '/../../database/TesteOlga.db';
if (!file_exists($databasePath)) {
    die("O arquivo de banco de dados não existe no caminho especificado: $databasePath");
}

if (!is_readable($databasePath)) {
    die("O arquivo de banco de dados não é legível. Verifique as permissões.");
}

define('DB_PATH', $databasePath); // Assume TesteOlga.db no mesmo diretório
define('DEFAULT_AVATAR_URL', 'https://via.placeholder.com/50/7F9CF5/FFFFFF?Text=User');

/**
 * Obtém uma conexão com a base de dados SQLite.
 * @return PDO|null
 */
function getDbConnection() {
    try {
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Erro de conexão com a base de dados: " . $e->getMessage());
        // Não exiba erros detalhados em produção
        die("Erro ao conectar com a base de dados. Por favor, tente mais tarde.");
        return null;
    }
}

// Funções de utilidade que podem ser usadas em vários scripts
// (Ex: getUserDetails, getParticipantDisplayInfo, getUserProfileIds que estavam em chat.php)
// Para manter este exemplo focado, pode mantê-las em chat.php por enquanto,
// ou movê-las para cá ou para um 'chat_functions.php' e incluí-lo onde necessário.

/**
 * Obtém detalhes de um utilizador.
 * @param PDO $pdo
 * @param int $userId
 * @return array|null
 */
function getUserDetails(PDO $pdo, int $userId): ?array {
    $stmt = $pdo->prepare("SELECT user_id, first_name, last_name, email, profile_image_url FROM Users WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $userId]);
    return $stmt->fetch();
}

/**
 * Obtém o nome de exibição e a imagem de perfil de um participante.
 * @param PDO $pdo
 * @param int $userId
 * @return array ['name' => string, 'image_url' => string]
 */
function getParticipantDisplayInfo(PDO $pdo, int $userId): array {
    $userDetails = getUserDetails($pdo, $userId);
    $displayName = "Utilizador Desconhecido";
    $imageUrl = DEFAULT_AVATAR_URL;

    if ($userDetails) {
        $displayName = htmlspecialchars($userDetails['first_name'] . ' ' . $userDetails['last_name']);
        if (!empty($userDetails['profile_image_url'])) {
            $imageUrl = htmlspecialchars($userDetails['profile_image_url']);
        }

        $stmt = $pdo->prepare("SELECT rp.restaurant_name FROM RestaurantProfiles rp WHERE rp.user_id = :user_id");
        $stmt->execute([':user_id' => $userId]);
        $restaurantProfile = $stmt->fetch();
        if ($restaurantProfile && !empty($restaurantProfile['restaurant_name'])) {
            $displayName = htmlspecialchars($restaurantProfile['restaurant_name']);
        }
    }
    return ['name' => $displayName, 'image_url' => $imageUrl];
}

/**
 * Obtém o ID do perfil de freelancer ou restaurante para um user_id.
 * @param PDO $pdo
 * @param int $userId
 * @return array|null ['type' => 'freelancer'|'restaurant', 'profile_id' => int]
 */
function getUserProfileIds(PDO $pdo, int $userId): ?array {
    $stmt = $pdo->prepare("SELECT profile_id FROM FreelancerProfiles WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $userId]);
    $freelancer = $stmt->fetch();
    if ($freelancer) {
        return ['type' => 'freelancer', 'profile_id' => $freelancer['profile_id']];
    }

    $stmt = $pdo->prepare("SELECT restaurant_id FROM RestaurantProfiles WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $userId]);
    $restaurant = $stmt->fetch();
    if ($restaurant) {
        return ['type' => 'restaurant', 'profile_id' => $restaurant['restaurant_id']];
    }
    return null;
}

?>