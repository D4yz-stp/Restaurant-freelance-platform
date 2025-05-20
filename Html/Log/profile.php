<?php
session_start();
$db = new SQLite3('../../database/OlgaRJ.db');
$db->exec('PRAGMA foreign_keys = ON;');

// Check if logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../Html/Log/login.html');
    exit;
}

$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['user_first_name'] ?? '';
$last_name = $_SESSION['user_last_name'] ?? '';
$role = $_SESSION['user_role'] ?? '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update common user data
    $stmt = $db->prepare("
        UPDATE Users 
        SET first_name = :first_name, 
            last_name = :last_name, 
            email = :email, 
            contact = :contact, 
            country = :country, 
            city = :city
        WHERE user_id = :id
    ");
    
    $stmt->bindValue(':first_name', $_POST['first_name'], SQLITE3_TEXT);
    $stmt->bindValue(':last_name', $_POST['last_name'], SQLITE3_TEXT);
    $stmt->bindValue(':email', $_POST['email'], SQLITE3_TEXT);
    $stmt->bindValue(':contact', $_POST['contact'], SQLITE3_TEXT);
    $stmt->bindValue(':country', $_POST['country'], SQLITE3_TEXT);
    $stmt->bindValue(':city', $_POST['city'], SQLITE3_TEXT);
    $stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
    $stmt->execute();
    
    // Update role-specific data
    if ($role === 'freelancer') {
        $stmt = $db->prepare("
            UPDATE FreelancerProfiles 
            SET hourly_rate = :hourly_rate, 
                availability = :availability, 
                experience_years = :experience_years
            WHERE user_id = :id
        ");
        $stmt->bindValue(':hourly_rate', $_POST['hourly_rate'], SQLITE3_FLOAT);
        $stmt->bindValue(':availability', $_POST['availability'], SQLITE3_TEXT);
        $stmt->bindValue(':experience_years', $_POST['experience_years'], SQLITE3_INTEGER);
        $stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
        $stmt->execute();
    } elseif ($role === 'restaurant') {
        $stmt = $db->prepare("
            UPDATE RestaurantProfiles 
            SET restaurant_name = :restaurant_name, 
                restaurant_type = :restaurant_type, 
                description = :description
            WHERE user_id = :id
        ");
        $stmt->bindValue(':restaurant_name', $_POST['restaurant_name'], SQLITE3_TEXT);
        $stmt->bindValue(':restaurant_type', $_POST['restaurant_type'], SQLITE3_TEXT);
        $stmt->bindValue(':description', $_POST['description'], SQLITE3_TEXT);
        $stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
        $stmt->execute();
    }
    
    // Update session variables
    $_SESSION['user_first_name'] = $_POST['first_name'];
    $_SESSION['user_last_name'] = $_POST['last_name'];
    
    // Redirect to avoid form resubmission
    header('Location: profile.php');
    exit;
}

// Fetch current user data
// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update common user data
    $stmt = $db->prepare("
        UPDATE Users 
        SET first_name = :first_name, 
            last_name = :last_name, 
            email = :email, 
            contact = :contact, 
            country = :country, 
            city = :city
        WHERE user_id = :id
    ");
    
    $stmt->bindValue(':first_name', $_POST['first_name'], SQLITE3_TEXT);
    $stmt->bindValue(':last_name', $_POST['last_name'], SQLITE3_TEXT);
    $stmt->bindValue(':email', $_POST['email'], SQLITE3_TEXT);
    $stmt->bindValue(':contact', $_POST['contact'], SQLITE3_TEXT);
    $stmt->bindValue(':country', $_POST['country'], SQLITE3_TEXT);
    $stmt->bindValue(':city', $_POST['city'], SQLITE3_TEXT);
    $stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
    $stmt->execute();
    
    // Update role-specific data
    if ($role === 'freelancer') {
        $stmt = $db->prepare("
            UPDATE FreelancerProfiles 
            SET hourly_rate = :hourly_rate, 
                availability = :availability, 
                experience_years = :experience_years,
                availability_details = :availability_details
            WHERE user_id = :id
        ");
        $stmt->bindValue(':hourly_rate', $_POST['hourly_rate'], SQLITE3_FLOAT);
        $stmt->bindValue(':availability', $_POST['availability'], SQLITE3_TEXT);
        $stmt->bindValue(':experience_years', $_POST['experience_years'], SQLITE3_INTEGER);
        $stmt->bindValue(':availability_details', $_POST['availability_details'], SQLITE3_TEXT);
        $stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
        $stmt->execute();
        
        // Update skills
        $db->exec("DELETE FROM FreelancerSkills WHERE freelancer_id = (SELECT profile_id FROM FreelancerProfiles WHERE user_id = $user_id)");
        if (!empty($_POST['skills'])) {
            foreach ($_POST['skills'] as $skill_id) {
                $stmt = $db->prepare("INSERT INTO FreelancerSkills (freelancer_id, skill_id) VALUES ((SELECT profile_id FROM FreelancerProfiles WHERE user_id = :user_id), :skill_id)");
                $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
                $stmt->bindValue(':skill_id', $skill_id, SQLITE3_INTEGER);
                $stmt->execute();
            }
        }
    } elseif ($role === 'restaurant') {
        $stmt = $db->prepare("
            UPDATE RestaurantProfiles 
            SET restaurant_name = :restaurant_name, 
                restaurant_type = :restaurant_type, 
                description = :description,
                opening_hours = :opening_hours,
                capacity = :capacity,
                staff_size = :staff_size
            WHERE user_id = :id
        ");
        $stmt->bindValue(':restaurant_name', $_POST['restaurant_name'], SQLITE3_TEXT);
        $stmt->bindValue(':restaurant_type', $_POST['restaurant_type'], SQLITE3_TEXT);
        $stmt->bindValue(':description', $_POST['description'], SQLITE3_TEXT);
        $stmt->bindValue(':opening_hours', $_POST['opening_hours'], SQLITE3_TEXT);
        $stmt->bindValue(':capacity', $_POST['capacity'], SQLITE3_INTEGER);
        $stmt->bindValue(':staff_size', $_POST['staff_size'], SQLITE3_INTEGER);
        $stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
        $stmt->execute();
    }
    
    // Update session variables
    $_SESSION['user_first_name'] = $_POST['first_name'];
    $_SESSION['user_last_name'] = $_POST['last_name'];
    
    // Redirect to avoid form resubmission
    header('Location: profile.php');
    exit;
}

// Fetch current user data
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

// Fetch role-specific data
$extra = [];
$user_skills = [];
if ($role === 'freelancer') {
    $stmt = $db->prepare("SELECT * FROM FreelancerProfiles WHERE user_id = :id");
    $stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
    $extra = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
} elseif ($role === 'restaurant') {
    $stmt = $db->prepare("SELECT * FROM RestaurantProfiles WHERE user_id = :id");
    $stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
    $extra = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
}

// Restaurant types
$restaurant_types = [
    'tradicional' => 'Tradicional',
    'fastfood' => 'Fast Food',
    'cafe' => 'Café',
    'bar' => 'Bar',
    'pizzaria' => 'Pizzaria',
    'outro' => 'Outro'
];

// Check if we're in edit mode
$edit_mode = isset($_GET['edit']) && $_GET['edit'] === 'true';
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <title>Perfil - OlgaRJ</title>
    <link rel="stylesheet" href="../../Css/profile.css">
    <link rel="stylesheet" href="../../Css/profile.css">
    <link rel="stylesheet" href="../../Css/global.css">
    <link rel="stylesheet" href="../../Css/index.css">
    <link rel="stylesheet" href="../../Css/header+button.css">
    <link rel="stylesheet" href="../../Css/footer.css">
    <style>
        .field-info {
            display: block;
            font-size: 0.8rem;
            color: #666;
            font-weight: normal;
            margin-top: 0.2rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            display: block;
        }
        
        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group input[type="email"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: inherit;
        }
        
        .form-group textarea {
            min-height: 100px;
        }
        
        select[multiple] {
            height: auto;
            min-height: 100px;
        }
        
        .restaurant-type {
            background-color: rgba(0, 35, 102, 0.1);
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-weight: bold;
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #FFD700;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="perfil-container">
        <div class="profile-header">
            <h2>Meu Perfil</h2>
        </div>
        
        <?php if ($edit_mode): ?>
            <form method="POST" action="profile.php">
                <div class="info-box edit-mode">
                    <h3>Informações Básicas</h3>
                    <div class="form-group">
                        <label for="first_name">Primeiro Nome:</label>
                        <input type="text" id="first_name" name="first_name" value="<?= htmlspecialchars($user['first_name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="last_name">Último Nome:</label>
                        <input type="text" id="last_name" name="last_name" value="<?= htmlspecialchars($user['last_name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email:</label>
                        <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="contact">Contacto:</label>
                        <input type="text" id="contact" name="contact" value="<?= htmlspecialchars($user['contact']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="country">País:</label>
                        <input type="text" id="country" name="country" value="<?= htmlspecialchars($user['country']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="city">Cidade:</label>
                        <input type="text" id="city" name="city" value="<?= htmlspecialchars($user['city']) ?>">
                    </div>

                    <?php if ($role === 'freelancer'): ?>
                        <hr>
                        <h3>Perfil de Freelancer</h3>
                        <div class="form-group">
                            <label for="hourly_rate">Preço por hora (€):</label>
                            <input type="number" step="0.01" id="hourly_rate" name="hourly_rate" value="<?= htmlspecialchars($extra['hourly_rate']) ?>">
                        </div>
                        <div class="form-group">
                            <label for="availability">Disponibilidade:</label>
                            <select id="availability" name="availability">
                                <option value="flexible" <?= $extra['availability'] === 'flexible' ? 'selected' : '' ?>>Flexível</option>
                                <option value="mornings" <?= $extra['availability'] === 'mornings' ? 'selected' : '' ?>>Manhãs</option>
                                <option value="afternoons" <?= $extra['availability'] === 'afternoons' ? 'selected' : '' ?>>Tardes</option>
                                <option value="evenings" <?= $extra['availability'] === 'evenings' ? 'selected' : '' ?>>Noites</option>
                                <option value="weekends" <?= $extra['availability'] === 'weekends' ? 'selected' : '' ?>>Fins de Semana</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="experience_years">Anos de experiência:</label>
                            <input type="number" id="experience_years" name="experience_years" value="<?= htmlspecialchars($extra['experience_years']) ?>">
                        </div>
                    <?php elseif ($role === 'restaurant'): ?>
                        <hr>
                        <h3>Perfil de Restaurante</h3>
                        <!-- In your edit form (profile.php) -->
                    <div class="form-group">
                        <label for="restaurant_name">Nome do Restaurante:</label>
                        <input type="text" id="restaurant_name" name="restaurant_name" 
                            value="<?= htmlspecialchars($extra['restaurant_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="restaurant_type">Tipo de Restaurante:</label>
                        <select id="restaurant_type" name="restaurant_type" required>
                            <option value="tradicional" <?= ($extra['restaurant_type'] ?? '') === 'tradicional' ? 'selected' : '' ?>>Tradicional</option>
                            <option value="fastfood" <?= ($extra['restaurant_type'] ?? '') === 'fastfood' ? 'selected' : '' ?>>Fast Food</option>
                            <option value="cafe" <?= ($extra['restaurant_type'] ?? '') === 'cafe' ? 'selected' : '' ?>>Café</option>
                            <option value="bar" <?= ($extra['restaurant_type'] ?? '') === 'bar' ? 'selected' : '' ?>>Bar</option>
                            <option value="pizzaria" <?= ($extra['restaurant_type'] ?? '') === 'pizzaria' ? 'selected' : '' ?>>Pizzaria</option>
                            <option value="outro" <?= ($extra['restaurant_type'] ?? '') === 'outro' ? 'selected' : '' ?>>Outro</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="description">Descrição:</label>
                        <textarea id="description" name="description"><?= htmlspecialchars($extra['description'] ?? '') ?></textarea>
                    </div>
                    <?php endif; ?>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                        <a href="profile.php" class="btn btn-secondary">Cancelar</a>
                    </div>
                </div>
            </form>
        <?php else: ?>
            <div class="info-box">
                <p><strong>Nome:</strong> <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></p>
                <p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
                <p><strong>Contacto:</strong> <?= htmlspecialchars($user['contact']) ?></p>
                <p><strong>Localização:</strong> <?= htmlspecialchars($user['city'] . ', ' . $user['country']) ?></p>
                <p><strong>Tipo de Conta:</strong> <?= htmlspecialchars($role) ?></p>
        <div class="profile-header">
            <?php if ($user['profile_image_url']): ?>
                <img src="<?= htmlspecialchars($user['profile_image_url']) ?>" alt="Foto de perfil" class="profile-avatar">
            <?php else: ?>
                <div class="profile-avatar" style="background-color: #002366; color: white; display: flex; align-items: center; justify-content: center; font-size: 2.5rem;">
                    <?= strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)) ?>
                </div>
            <?php endif; ?>
            <h2>Meu Perfil</h2>
        </div>
        
        <?php if ($edit_mode): ?>
            <form method="POST" action="profile.php">
                <div class="info-box edit-mode">
                    <h3>Informações Básicas</h3>
                    <div class="form-group">
                        <label for="first_name">Primeiro Nome:
                            <span class="field-info">(Seu nome de batismo ou nome legal)</span>
                        </label>
                        <input type="text" id="first_name" name="first_name" value="<?= htmlspecialchars($user['first_name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="last_name">Último Nome:
                            <span class="field-info">(Seu sobrenome ou apelido de família)</span>
                        </label>
                        <input type="text" id="last_name" name="last_name" value="<?= htmlspecialchars($user['last_name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email:
                            <span class="field-info">(Seu endereço de email principal)</span>
                        </label>
                        <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="contact">Contacto:
                            <span class="field-info">(Número de telefone para contato profissional)</span>
                        </label>
                        <input type="text" id="contact" name="contact" value="<?= htmlspecialchars($user['contact']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="country">País:
                            <span class="field-info">(País onde reside atualmente)</span>
                        </label>
                        <input type="text" id="country" name="country" value="<?= htmlspecialchars($user['country']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="city">Cidade:
                            <span class="field-info">(Cidade onde trabalha ou está disponível)</span>
                        </label>
                        <input type="text" id="city" name="city" value="<?= htmlspecialchars($user['city']) ?>">
                    </div>

                    <?php if ($role === 'freelancer'): ?>
                        <hr>
                        <h3>Perfil de Freelancer</h3>
                        <div class="form-group">
                            <label for="hourly_rate">Preço por hora (€):
                                <span class="field-info">(Valor que cobra por hora de trabalho)</span>
                            </label>
                            <input type="number" step="0.01" id="hourly_rate" name="hourly_rate" value="<?= htmlspecialchars($extra['hourly_rate']) ?>">
                        </div>
                        <div class="form-group">
                            <label for="availability">Disponibilidade:
                                <span class="field-info">(Quando está disponível para trabalhar)</span>
                            </label>
                            <select id="availability" name="availability">
                                <option value="flexible" <?= $extra['availability'] === 'flexible' ? 'selected' : '' ?>>Flexível</option>
                                <option value="mornings" <?= $extra['availability'] === 'mornings' ? 'selected' : '' ?>>Manhãs</option>
                                <option value="afternoons" <?= $extra['availability'] === 'afternoons' ? 'selected' : '' ?>>Tardes</option>
                                <option value="evenings" <?= $extra['availability'] === 'evenings' ? 'selected' : '' ?>>Noites</option>
                                <option value="weekends" <?= $extra['availability'] === 'weekends' ? 'selected' : '' ?>>Fins de Semana</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="availability_details">Detalhes de Disponibilidade:
                                <span class="field-info">(Especifique seus horários disponíveis com mais detalhes)</span>
                            </label>
                            <textarea id="availability_details" name="availability_details"><?= htmlspecialchars($extra['availability_details']) ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="experience_years">Anos de experiência:
                                <span class="field-info">(Tempo total de experiência na área)</span>
                            </label>
                            <input type="number" id="experience_years" name="experience_years" value="<?= htmlspecialchars($extra['experience_years']) ?>">
                        </div>
                        <div class="form-group">
                            <label for="skills">Habilidades Principais:
                                <span class="field-info">(Selecione suas habilidades mais relevantes - segure CTRL para selecionar múltiplas)</span>
                            </label>
                            <select id="skills" name="skills[]" multiple>
                                <?php 
                                $stmt = $db->prepare("SELECT * FROM Skills");
                                $skills = $stmt->execute();
                                while ($skill = $skills->fetchArray(SQLITE3_ASSOC)): 
                                    $selected = in_array($skill['skill_id'], $user_skills) ? 'selected' : '';
                                ?>
                                    <option value="<?= $skill['skill_id'] ?>" <?= $selected ?>>
                                        <?= htmlspecialchars($skill['skill_name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    <?php elseif ($role === 'restaurant'): ?>
                        <hr>
                        <h3>Perfil de Restaurante</h3>
                        <div class="form-group">
                            <label for="restaurant_name">Nome do Restaurante:
                                <span class="field-info">(Nome oficial do seu estabelecimento)</span>
                            </label>
                            <input type="text" id="restaurant_name" name="restaurant_name" 
                                value="<?= htmlspecialchars($extra['restaurant_name'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="restaurant_type">Tipo de Restaurante:
                                <span class="field-info">(Categoria principal do seu restaurante)</span>
                            </label>
                            <select id="restaurant_type" name="restaurant_type" required>
                                <option value="tradicional" <?= ($extra['restaurant_type'] ?? '') === 'tradicional' ? 'selected' : '' ?>>Tradicional</option>
                                <option value="fastfood" <?= ($extra['restaurant_type'] ?? '') === 'fastfood' ? 'selected' : '' ?>>Fast Food</option>
                                <option value="cafe" <?= ($extra['restaurant_type'] ?? '') === 'cafe' ? 'selected' : '' ?>>Café</option>
                                <option value="bar" <?= ($extra['restaurant_type'] ?? '') === 'bar' ? 'selected' : '' ?>>Bar</option>
                                <option value="pizzaria" <?= ($extra['restaurant_type'] ?? '') === 'pizzaria' ? 'selected' : '' ?>>Pizzaria</option>
                                <option value="outro" <?= ($extra['restaurant_type'] ?? '') === 'outro' ? 'selected' : '' ?>>Outro</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="description">Descrição do Restaurante:
                                <span class="field-info">(Conte sobre seu estabelecimento, conceito e especialidades)</span>
                            </label>
                            <textarea id="description" name="description"><?= htmlspecialchars($extra['description'] ?? '') ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="opening_hours">Horário de Funcionamento:
                                <span class="field-info">(Dias e horários de atendimento, ex: Seg-Sex: 12h-15h, 19h-23h)</span>
                            </label>
                            <textarea id="opening_hours" name="opening_hours"><?= htmlspecialchars($extra['opening_hours'] ?? '') ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="capacity">Capacidade:
                                <span class="field-info">(Número máximo de clientes simultâneos)</span>
                            </label>
                            <input type="number" id="capacity" name="capacity" value="<?= htmlspecialchars($extra['capacity'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="staff_size">Tamanho da Equipe:
                                <span class="field-info">(Número de funcionários fixos)</span>
                            </label>
                            <input type="number" id="staff_size" name="staff_size" value="<?= htmlspecialchars($extra['staff_size'] ?? '') ?>">
                        </div>
                    <?php endif; ?>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                        <a href="profile.php" class="btn btn-secondary">Cancelar</a>
                    </div>
                </div>
            </form>
        <?php else: ?>
            <div class="info-box">
                <h3>Informações Pessoais</h3>
                <p><strong>Nome:</strong> <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></p>
                <p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
                <p><strong>Contacto:</strong> <?= htmlspecialchars($user['contact']) ?></p>
                <p><strong>Localização:</strong> <?= htmlspecialchars($user['city'] . ', ' . $user['country']) ?></p>
                <p><strong>Tipo de Conta:</strong> <?= htmlspecialchars(ucfirst($role)) ?></p>

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
                    <p><strong>Tipo:</strong> <span class="restaurant-type"><?= htmlspecialchars($restaurant_types[$extra['restaurant_type']] ?? $extra['restaurant_type']) ?></span></p>
                    <p><strong>Descrição:</strong> <?= htmlspecialchars($extra['description']) ?></p>
                    <p><strong>Avaliação média:</strong> <?= $extra['avg_rating'] ?></p>
                <?php endif; ?>
            </div>
            <div class="profile-actions">
                <a href="?edit=true" class="btn btn-primary">Editar Perfil</a>
            </div>
        <?php endif; ?>
                <?php if ($role === 'freelancer'): ?>
                    <hr>
                    <h3>Perfil Profissional</h3>
                    <p><strong>Preço por hora:</strong> €<?= number_format($extra['hourly_rate'], 2) ?></p>
                    <p><strong>Disponibilidade:</strong> <?= htmlspecialchars($extra['availability']) ?></p>
                    <?php if (!empty($extra['availability_details'])): ?>
                        <p><strong>Detalhes de Disponibilidade:</strong> <?= htmlspecialchars($extra['availability_details']) ?></p>
                    <?php endif; ?>
                    <p><strong>Anos de experiência:</strong> <?= $extra['experience_years'] ?></p>
                    <p><strong>Avaliação média:</strong> <?= $extra['avg_rating'] ? $extra['avg_rating'] . '/5' : 'Nenhuma avaliação ainda' ?></p>
                    
                    <?php if (!empty($user_skills)): ?>
                        <p><strong>Habilidades:</strong></p>
                        <ul>
                            <?php 
                            $stmt = $db->prepare("
                                SELECT s.skill_name 
                                FROM Skills s
                                JOIN FreelancerSkills fs ON s.skill_id = fs.skill_id
                                JOIN FreelancerProfiles fp ON fs.freelancer_id = fp.profile_id
                                WHERE fp.user_id = :user_id
                            ");
                            $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
                            $skills_result = $stmt->execute();
                            while ($skill = $skills_result->fetchArray(SQLITE3_ASSOC)): ?>
                                <li><?= htmlspecialchars($skill['skill_name']) ?></li>
                            <?php endwhile; ?>
                        </ul>
                    <?php endif; ?>
                <?php elseif ($role === 'restaurant'): ?>
                    <hr>
                    <h3>Informações do Restaurante</h3>
                    <p><strong>Nome do Restaurante:</strong> <?= htmlspecialchars($extra['restaurant_name']) ?></p>
                    <p><strong>Tipo:</strong> <span class="restaurant-type"><?= htmlspecialchars($restaurant_types[$extra['restaurant_type']] ?? $extra['restaurant_type']) ?></span></p>
                    <p><strong>Descrição:</strong> <?= htmlspecialchars($extra['description']) ?></p>
                    <?php if (!empty($extra['opening_hours'])): ?>
                        <p><strong>Horário de Funcionamento:</strong> <?= htmlspecialchars($extra['opening_hours']) ?></p>
                    <?php endif; ?>
                    <p><strong>Capacidade:</strong> <?= $extra['capacity'] ?? 'N/A' ?> pessoas</p>
                    <p><strong>Equipe Fixa:</strong> <?= $extra['staff_size'] ?? 'N/A' ?> funcionários</p>
                    <p><strong>Avaliação média:</strong> <?= $extra['avg_rating'] ? $extra['avg_rating'] . '/5' : 'Nenhuma avaliação ainda' ?></p>
                <?php endif; ?>
            </div>
            <div class="profile-actions">
                <a href="?edit=true" class="btn btn-primary">Editar Perfil</a>
                <?php if ($role === 'freelancer'): ?>
                    <a href=" /../Services/index.php" class="btn btn-outline">Ver Perfil Completo</a>
                <?php elseif ($role === 'restaurant'): ?>
                    <a href=" /../Services/index.php" class="btn btn-outline">Ver Perfil Completo</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>