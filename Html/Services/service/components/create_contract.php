<?php
session_start();

$base_dir = 'LTW-PROJECT-LTW04G08';
$db_path = $base_dir . '/database/TesteOlga.db';

// Debug output (remove after testing)
echo "<!-- DB Path: $db_path -->";

if (!file_exists($db_path)) {
    die("<div class='alert alert-danger'>Database not found at: $db_path</div>");
}

try {
    $db = new SQLite3($db_path);
    $db->exec('PRAGMA foreign_keys = ON;');
    // Rest of your code...
} catch (Exception $e) {
    die("<div class='alert alert-danger'>Database error: " . $e->getMessage() . "</div>");
}

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: /Html/Log/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['user_role'] ?? '';

// Verificar se está no modo de criação e se é um restaurante
$create_mode = isset($_GET['create']) && $_GET['create'] === 'true' && $role === 'restaurant';
$service_id = $_GET['service_id'] ?? 0;
$freelancer_id = $_GET['freelancer_id'] ?? 0;

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate required fields
    $required = ['service_id', 'freelancer_id', 'title', 'description', 'start_date', 'end_date', 'agreed_price', 'payment_terms', 'terms_agreement'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            header('Location: /service.php?id=' . $_POST['service_id'] . '&error=missing_fields');
            exit;
        }
    }

    // Prepare data
    $restaurant_id = $_SESSION['user_id'];
    $freelancer_id = (int)$_POST['freelancer_id'];
    $service_id = (int)$_POST['service_id'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $hourly_rate = !empty($_POST['hourly_rate']) ? (float)$_POST['hourly_rate'] : null;
    $hours_per_week = !empty($_POST['hours_per_week']) ? (int)$_POST['hours_per_week'] : null;
    $agreed_price = (float)$_POST['agreed_price'];
    $payment_terms = $_POST['payment_terms'];
    $status = $_POST['status'];

    // Insert contract into database
    $stmt = $db->prepare("
        INSERT INTO Contracts (
            restaurant_id, 
            freelancer_id, 
            service_id, 
            title, 
            description, 
            start_date, 
            end_date, 
            hourly_rate, 
            hours_per_week, 
            agreed_price,
            payment_terms,
            status,
            created_at
        ) VALUES (
            (SELECT restaurant_id FROM RestaurantProfiles WHERE user_id = :restaurant_id),
            :freelancer_id,
            :service_id,
            :title,
            :description,
            :start_date,
            :end_date,
            :hourly_rate,
            :hours_per_week,
            :agreed_price,
            :payment_terms,
            :status,
            CURRENT_TIMESTAMP
        )
    ");
    
    $stmt->bindValue(':restaurant_id', $restaurant_id, SQLITE3_INTEGER);
    $stmt->bindValue(':freelancer_id', $freelancer_id, SQLITE3_INTEGER);
    $stmt->bindValue(':service_id', $service_id, SQLITE3_INTEGER);
    $stmt->bindValue(':title', $title, SQLITE3_TEXT);
    $stmt->bindValue(':description', $description, SQLITE3_TEXT);
    $stmt->bindValue(':start_date', $start_date, SQLITE3_TEXT);
    $stmt->bindValue(':end_date', $end_date, SQLITE3_TEXT);
    $stmt->bindValue(':hourly_rate', $hourly_rate, SQLITE3_FLOAT);
    $stmt->bindValue(':hours_per_week', $hours_per_week, SQLITE3_INTEGER);
    $stmt->bindValue(':agreed_price', $agreed_price, SQLITE3_FLOAT);
    $stmt->bindValue(':payment_terms', $payment_terms, SQLITE3_TEXT);
    $stmt->bindValue(':status', $status, SQLITE3_TEXT);

    if ($stmt->execute()) {
        // Get the ID of the newly created contract
        $contract_id = $db->lastInsertRowID();
        
        // Redirect to contracts page with success message
        header('Location: /contracts.php?success=contract_created&id=' . $contract_id);
    } else {
        // Redirect back with error message
        header('Location: /service.php?id=' . $service_id . '&error=contract_failed');
    }
    exit;
}

// If not a POST request, redirect back
header('Location: /');
?>