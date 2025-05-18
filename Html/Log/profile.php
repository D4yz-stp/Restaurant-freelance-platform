<?php
session_start();
$db = new SQLite3('../../database/OlgaRJ.db');
$db->exec('PRAGMA foreign_keys = ON;');

// Verificar se está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../Html/Log/login.html');
    exit;
}

$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['user_first_name'] ?? '';
$last_name = $_SESSION['user_last_name'] ?? '';
$role = $_SESSION['user_role'] ?? '';

// Buscar dados comuns
$stmt = $db->prepare("
    SELECT U.first_name, U.last_name, U.email, U.contact, U.country, U.city, R.role_name
    FROM Users U
    LEFT JOIN UserRoles UR ON U.user_id = UR.user_id
    LEFT JOIN Roles R ON UR.role_id = R.role_id
    WHERE U.user_id = :id
");
$stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
$result = $stmt->execute();
$user = $result->fetchArray(SQLITE3_ASSOC);

// Buscar dados específicos
$extra = [];

if ($role === 'freelancer') {
    $stmt = $db->prepare("SELECT * FROM FreelancerProfiles WHERE user_id = :id");
    $stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
    $extra = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
} elseif ($role === 'restaurant') {
    $stmt = $db->prepare("SELECT * FROM RestaurantProfiles WHERE user_id = :id");
    $stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
    $extra = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <title>Perfil - OlgaRJ</title>
    <link rel="stylesheet" href="../../Css/perfil.css">
</head>
<body>
    <div class="perfil-container">
        <h2>Meu Perfil</h2>
        <div class="info-box">
            <img src="<?= htmlspecialchars($user['profile_image_url']) ?>" alt="Foto de Perfil" style="max-width:100px; border-radius:50px;">
            <p><strong>Nome:</strong> <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></p>
            <p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
            <p><strong>Contacto:</strong> <?= htmlspecialchars($user['contact']) ?></p>
            <p><strong>Localização:</strong> <?= htmlspecialchars($user['city'] . ', ' . $user['country']) ?></p>
            <p><strong>Tipo de Conta:</strong> <?= htmlspecialchars($role) ?></p>

            <?php if ($role === 'freelancer'): ?>
                <hr>
                <h3>Perfil de Freelancer</h3>
                <p><strong>Preço por hora:</strong> €<?= number_format($extra['hourly_rate'], 2) ?></p>
                <p><strong>Disponibilidade:</strong> <?= htmlspecialchars($extra['availability']) ?></p>
                <p><strong>Anos de experiência:</strong> <?= $extra['experience_years'] ?></p>
                <p><strong>Avaliação média:</strong> <?= $extra['avg_rating'] ?></p>
            <?php elseif ($role === 'restaurant'): ?>
                <hr>
                <h3>Perfil de Restaurante</h3>
                <p><strong>Nome do Restaurante:</strong> <?= htmlspecialchars($extra['restaurant_name']) ?></p>
                <p><strong>Tipo:</strong> <?= htmlspecialchars($extra['restaurant_type']) ?></p>
                <p><strong>Descrição:</strong> <?= htmlspecialchars($extra['description']) ?></p>
                <p><strong>Avaliação média:</strong> <?= $extra['avg_rating'] ?></p>
            <?php endif; ?>
        </div>

        <br>
        <a href="edit_profile.php" class="btn">Editar Perfil</a>
    </div>
</body>
</html>
