<?php
session_start();

// Verificar se o usuário está logado e é freelancer
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'freelancer') {
    header('Location: login.php');
    exit();
}

// Conectar à base de dados
try {
    $pdo = new PDO('sqlite: ../../../../database/TesteOlga.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Erro de conexão: ' . $e->getMessage());
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';


try {
    $stmt = $pdo->prepare("SELECT profile_id FROM FreelancerProfiles WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $freelancer = $stmt->fetch();
    
    if (!$freelancer) {
        die('Perfil de freelancer não encontrado.');
    }
    
    $freelancer_id = $freelancer['profile_id'];
} catch (PDOException $e) {
    die('Erro ao buscar perfil: ' . $e->getMessage());
}


// Processar formulários
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'update_profile':
                    // Atualizar perfil básico
                    $stmt = $pdo->prepare("UPDATE FreelancerProfiles SET hourly_rate = ?, availability = ?, experience_years = ?, availability_details = ? WHERE user_id = ?");
                    $stmt->execute([
                        $_POST['hourly_rate'],
                        $_POST['availability'],
                        $_POST['experience_years'],
                        $_POST['availability_details'],
                        $user_id
                    ]);
                    $success_message = "Perfil atualizado com sucesso!";
                    break;

                case 'add_skill':
                    // Verificar se a skill já existe
                    $stmt = $pdo->prepare("SELECT skill_id FROM Skills WHERE skill_name = ?");
                    $stmt->execute([$_POST['skill_name']]);
                    $skill = $stmt->fetch();
                    
                    if (!$skill) {
                        // Criar nova skill
                        $stmt = $pdo->prepare("INSERT INTO Skills (skill_name, description) VALUES (?, ?)");
                        $stmt->execute([$_POST['skill_name'], $_POST['skill_description'] ?? '']);
                        $skill_id = $pdo->lastInsertId();
                    } else {
                        $skill_id = $skill['skill_id'];
                    }
                    
                    // Obter freelancer_id
                    $stmt = $pdo->prepare("SELECT profile_id FROM FreelancerProfiles WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    $freelancer = $stmt->fetch();
                    
                    // Adicionar skill ao freelancer
                    $stmt = $pdo->prepare("INSERT OR REPLACE INTO FreelancerSkills (freelancer_id, skill_id, proficiency_level) VALUES (?, ?, ?)");
                    $stmt->execute([$freelancer['profile_id'], $skill_id, $_POST['proficiency_level']]);
                    $success_message = "Skill adicionada com sucesso!";
                    break;

                case 'remove_skill':
                    $stmt = $pdo->prepare("SELECT profile_id FROM FreelancerProfiles WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    $freelancer = $stmt->fetch();
                    
                    $stmt = $pdo->prepare("DELETE FROM FreelancerSkills WHERE freelancer_id = ? AND skill_id = ?");
                    $stmt->execute([$freelancer['profile_id'], $_POST['skill_id']]);
                    $success_message = "Skill removida com sucesso!";
                    break;

                case 'add_language':
                    // Verificar se o idioma já existe
                    $stmt = $pdo->prepare("SELECT language_id FROM Languages WHERE language_name = ?");
                    $stmt->execute([$_POST['language_name']]);
                    $language = $stmt->fetch();
                    
                    if (!$language) {
                        // Criar novo idioma
                        $stmt = $pdo->prepare("INSERT INTO Languages (language_name) VALUES (?)");
                        $stmt->execute([$_POST['language_name']]);
                        $language_id = $pdo->lastInsertId();
                    } else {
                        $language_id = $language['language_id'];
                    }
                    
                    // Obter freelancer_id
                    $stmt = $pdo->prepare("SELECT profile_id FROM FreelancerProfiles WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    $freelancer = $stmt->fetch();
                    
                    // Adicionar idioma ao freelancer
                    $stmt = $pdo->prepare("INSERT OR REPLACE INTO FreelancerLanguages (freelancer_id, language_id, proficiency) VALUES (?, ?, ?)");
                    $stmt->execute([$freelancer['profile_id'], $language_id, $_POST['proficiency']]);
                    $success_message = "Idioma adicionado com sucesso!";
                    break;

                case 'remove_language':
                    $stmt = $pdo->prepare("SELECT profile_id FROM FreelancerProfiles WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    $freelancer = $stmt->fetch();
                    
                    $stmt = $pdo->prepare("DELETE FROM FreelancerLanguages WHERE freelancer_id = ? AND language_id = ?");
                    $stmt->execute([$freelancer['profile_id'], $_POST['language_id']]);
                    $success_message = "Idioma removido com sucesso!";
                    break;

                case 'add_service':
                    // Obter freelancer_id
                    $stmt = $pdo->prepare("SELECT profile_id FROM FreelancerProfiles WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    $freelancer = $stmt->fetch();
                    
                    $stmt = $pdo->prepare("INSERT INTO Services (freelancer_id, category_id, title, description, price_type, base_price, service_image_url) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $freelancer['profile_id'],
                        $_POST['category_id'],
                        $_POST['title'],
                        $_POST['description'],
                        $_POST['price_type'],
                        $_POST['base_price'],
                        $_POST['service_image_url'] ?? null
                    ]);
                    $success_message = "Serviço adicionado com sucesso!";
                    break;

                case 'update_service':
                    $stmt = $pdo->prepare("UPDATE Services SET category_id = ?, title = ?, description = ?, price_type = ?, base_price = ?, service_image_url = ?, is_active = ? WHERE service_id = ?");
                    $stmt->execute([
                        $_POST['category_id'],
                        $_POST['title'],
                        $_POST['description'],
                        $_POST['price_type'],
                        $_POST['base_price'],
                        $_POST['service_image_url'] ?? null,
                        $_POST['is_active'],
                        $_POST['service_id']
                    ]);
                    $success_message = "Serviço atualizado com sucesso!";
                    break;

                case 'remove_service':
                    $stmt = $pdo->prepare("DELETE FROM Services WHERE service_id = ?");
                    $stmt->execute([$_POST['service_id']]);
                    $success_message = "Serviço removido com sucesso!";
                    break;

                case 'update_chef_specialization':
                   // Eliminar especialização antiga
                    $stmt = $pdo->prepare("DELETE FROM ChefSpecializations WHERE freelancer_id = ?");
                    $stmt->execute([$freelancer_id]);
                    
                    // Inserir nova especialização
                    $stmt = $pdo->prepare("INSERT INTO ChefSpecializations (freelancer_id, cuisine_type, certifications, dietary_specialties, menu_planning, catering_experience) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $freelancer_id,
                        $_POST['cuisine_type'] ?? '',
                        $_POST['certifications'] ?? '',
                        $_POST['dietary_specialties'] ?? '',
                        isset($_POST['menu_planning']) ? 1 : 0,
                        isset($_POST['catering_experience']) ? 1 : 0
                    ]);
                    $success_message = "Especialização de chef atualizada com sucesso!";
                    
                    // Atualizar campo de especialização do usuário
                    $stmt = $pdo->prepare("UPDATE Users SET specialization = 'chef' WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    break;
                case 'update_cleaning_specialization':
                    // Eliminar especialização antiga
                    $stmt = $pdo->prepare("DELETE FROM CleaningSpecializations WHERE freelancer_id = ?");
                    $stmt->execute([$freelancer_id]);
                    
                    // Inserir nova especialização
                    $stmt = $pdo->prepare("INSERT INTO CleaningSpecializations (freelancer_id, kitchen_cleaning, dining_area_cleaning, equipment_experience, eco_friendly) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $freelancer_id,
                        isset($_POST['kitchen_cleaning']) ? 1 : 0,
                        isset($_POST['dining_area_cleaning']) ? 1 : 0,
                        $_POST['equipment_experience'] ?? '',
                        isset($_POST['eco_friendly']) ? 1 : 0
                    ]);
                    $success_message = "Especialização de limpeza atualizada com sucesso!";
                    
                    // Atualizar campo de especialização do usuário
                    $stmt = $pdo->prepare("UPDATE Users SET specialization = 'cleaning' WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    break;

                case 'update_bartender_specialization':
                    // Eliminar especialização antiga
                    $stmt = $pdo->prepare("DELETE FROM BartenderSpecializations WHERE freelancer_id = ?");
                    $stmt->execute([$freelancer_id]);
                    
                    // Inserir nova especialização
                    $stmt = $pdo->prepare("INSERT INTO BartenderSpecializations (freelancer_id, cocktail_specialist, wine_knowledge, beer_knowledge, flair_bartending, certifications) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $freelancer_id,
                        isset($_POST['cocktail_specialist']) ? 1 : 0,
                        isset($_POST['wine_knowledge']) ? 1 : 0,
                        isset($_POST['beer_knowledge']) ? 1 : 0,
                        isset($_POST['flair_bartending']) ? 1 : 0,
                        $_POST['certifications'] ?? ''
                    ]);
                    $success_message = "Especialização de bartender atualizada com sucesso!";
                    
                    // Atualizar campo de especialização do usuário
                    $stmt = $pdo->prepare("UPDATE Users SET specialization = 'bartender' WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    break;
                    
                case 'update_service_staff_specialization':
                    // Debug detalhado
                    error_log("=== DEBUG SERVICE STAFF ===");
                    error_log("POST data: " . print_r($_POST, true));
                    error_log("Freelancer ID: " . $freelancer_id);
                    
                    try {
                        // Eliminar especialização antiga
                        $stmt = $pdo->prepare("DELETE FROM ServiceStaffSpecializations WHERE freelancer_id = ?");
                        $delete_result = $stmt->execute([$freelancer_id]);
                        error_log("Delete result: " . ($delete_result ? 'SUCCESS' : 'FAILED'));
                        error_log("Rows affected by delete: " . $stmt->rowCount());
                        
                        // Preparar valores para inserção
                        $fine_dining = isset($_POST['fine_dining_experience']) ? 1 : 0;
                        $event_service = isset($_POST['event_service']) ? 1 : 0;
                        $sommelier = isset($_POST['sommelier_knowledge']) ? 1 : 0;
                        $rating = !empty($_POST['customer_service_rating']) ? (int)$_POST['customer_service_rating'] : null;
                        
                        error_log("Values to insert:");
                        error_log("- freelancer_id: " . $freelancer_id);
                        error_log("- fine_dining_experience: " . $fine_dining);
                        error_log("- event_service: " . $event_service);
                        error_log("- sommelier_knowledge: " . $sommelier);
                        error_log("- customer_service_rating: " . ($rating === null ? 'NULL' : $rating));
                        
                        // Inserir nova especialização
                        $stmt = $pdo->prepare("INSERT INTO ServiceStaffSpecializations (freelancer_id, fine_dining_experience, event_service, sommelier_knowledge, customer_service_rating) VALUES (?, ?, ?, ?, ?)");
                        $insert_result = $stmt->execute([
                            $freelancer_id,
                            $fine_dining,
                            $event_service,
                            $sommelier,
                            $rating
                        ]);
                        
                        error_log("Insert result: " . ($insert_result ? 'SUCCESS' : 'FAILED'));
                        error_log("Last insert ID: " . $pdo->lastInsertId());
                        
                        if ($insert_result) {
                            $success_message = "Especialização de atendimento atualizada com sucesso!";
                            
                            // Atualizar campo de especialização do usuário
                            $stmt = $pdo->prepare("UPDATE Users SET specialization = 'service_staff' WHERE user_id = ?");
                            $stmt->execute([$user_id]);
                        } else {
                            $error_message = "Erro ao inserir especialização de atendimento.";
                        }
                        
                    } catch (Exception $e) {
                        error_log("Exception in service staff update: " . $e->getMessage());
                        $error_message = "Erro: " . $e->getMessage();
                    }
                    break;
            }
        }
    } catch (PDOException $e) {
        $error_message = "Erro: " . $e->getMessage();
    }
}

// Buscar dados do perfil
$stmt = $pdo->prepare("SELECT * FROM FreelancerProfiles WHERE user_id = ?");
$stmt->execute([$user_id]);
$profile = $stmt->fetch();

if (!$profile) {
    // Criar profile se não existir
    $stmt = $pdo->prepare("INSERT INTO FreelancerProfiles (user_id) VALUES (?)");
    $stmt->execute([$user_id]);
    $stmt = $pdo->prepare("SELECT * FROM FreelancerProfiles WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $profile = $stmt->fetch();
}

$freelancer_id = $profile['profile_id'];

// Buscar skills do freelancer
$stmt = $pdo->prepare("SELECT fs.*, s.skill_name FROM FreelancerSkills fs JOIN Skills s ON fs.skill_id = s.skill_id WHERE fs.freelancer_id = ?");
$stmt->execute([$freelancer_id]);
$skills = $stmt->fetchAll();

// Buscar idiomas do freelancer
$stmt = $pdo->prepare("SELECT fl.*, l.language_name FROM FreelancerLanguages fl JOIN Languages l ON fl.language_id = l.language_id WHERE fl.freelancer_id = ?");
$stmt->execute([$freelancer_id]);
$languages = $stmt->fetchAll();

// Buscar serviços do freelancer
$stmt = $pdo->prepare("SELECT s.*, sc.name as category_name FROM Services s JOIN ServiceCategories sc ON s.category_id = sc.category_id WHERE s.freelancer_id = ?");
$stmt->execute([$freelancer_id]);
$services = $stmt->fetchAll();

// Buscar categorias de serviços
$stmt = $pdo->query("SELECT * FROM ServiceCategories");
$categories = $stmt->fetchAll();

// Buscar especializações
try {
    $stmt = $pdo->prepare("SELECT * FROM ChefSpecializations WHERE freelancer_id = ?");
    $stmt->execute([$freelancer_id]);
    $chef_spec = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT * FROM CleaningSpecializations WHERE freelancer_id = ?");
    $stmt->execute([$freelancer_id]);
    $cleaning_spec = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT * FROM BartenderSpecializations WHERE freelancer_id = ?");
    $stmt->execute([$freelancer_id]);
    $bartender_spec = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT * FROM ServiceStaffSpecializations WHERE freelancer_id = ?");
    $stmt->execute([$freelancer_id]);
    $service_staff_spec = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Erro ao buscar especializações: " . $e->getMessage();
    // Inicializar arrays vazios em caso de erro
    $chef_spec = [];
    $cleaning_spec = [];
    $bartender_spec = [];
    $service_staff_spec = [];
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Perfil - <?= htmlspecialchars($_SESSION['user_first_name'] . ' ' . $_SESSION['user_last_name']) ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
        .section { margin-bottom: 30px; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
        .section h3 { margin-top: 0; color: #333; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        .form-group textarea { height: 80px; resize: vertical; }
        .btn { padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; margin-right: 10px; }
        .btn:hover { background: #0056b3; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; }
        .alert-success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .alert-error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .item-list { list-style: none; padding: 0; }
        .item-list li { background: #f8f9fa; padding: 10px; margin-bottom: 10px; border-radius: 4px; display: flex; justify-content: space-between; align-items: center; }
        .form-inline { display: flex; gap: 10px; align-items: end; margin-bottom: 20px; }
        .form-inline .form-group { margin-bottom: 0; flex: 1; }
        .checkbox-group { display: flex; gap: 15px; flex-wrap: wrap; }
        .checkbox-group label { display: flex; align-items: center; font-weight: normal; }
        .checkbox-group input[type="checkbox"] { width: auto; margin-right: 5px; }
        .service-item { background: #f8f9fa; padding: 15px; margin-bottom: 15px; border-radius: 4px; border: 1px solid #dee2e6; }
        .service-status { padding: 3px 8px; border-radius: 3px; font-size: 12px; font-weight: bold; }
        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }
        .two-column { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media (max-width: 768px) { .two-column { grid-template-columns: 1fr; } .form-inline { flex-direction: column; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Meu Perfil</h1>
            <p>Bem-vindo, <?= htmlspecialchars($_SESSION['user_first_name'] . ' ' . $_SESSION['user_last_name']) ?>!</p>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <!-- Perfil Básico -->
        <div class="section">
            <h3>Informações Básicas</h3>
            <form method="POST">
                <input type="hidden" name="action" value="update_profile">
                <div class="two-column">
                    <div class="form-group">
                        <label for="hourly_rate">Taxa Horária (€)</label>
                        <input type="number" step="0.01" id="hourly_rate" name="hourly_rate" value="<?= htmlspecialchars($profile['hourly_rate'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="experience_years">Anos de Experiência</label>
                        <input type="number" id="experience_years" name="experience_years" value="<?= htmlspecialchars($profile['experience_years'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="availability">Disponibilidade</label>
                    <select id="availability" name="availability">
                        <option value="flexible" <?= ($profile['availability'] ?? '') === 'flexible' ? 'selected' : '' ?>>Flexível</option>
                        <option value="part-time" <?= ($profile['availability'] ?? '') === 'part-time' ? 'selected' : '' ?>>Meio Período</option>
                        <option value="full-time" <?= ($profile['availability'] ?? '') === 'full-time' ? 'selected' : '' ?>>Período Integral</option>
                        <option value="weekends" <?= ($profile['availability'] ?? '') === 'weekends' ? 'selected' : '' ?>>Fins de Semana</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="availability_details">Detalhes da Disponibilidade</label>
                    <textarea id="availability_details" name="availability_details" placeholder="Descreva sua disponibilidade detalhadamente..."><?= htmlspecialchars($profile['availability_details'] ?? '') ?></textarea>
                </div>
                <button type="submit" class="btn">Atualizar Perfil</button>
            </form>
        </div>

        <!-- Skills -->
        <div class="section">
            <h3>Competências (Skills)</h3>
            
            <!-- Adicionar Skill -->
            <form method="POST" class="form-inline">
                <input type="hidden" name="action" value="add_skill">
                <div class="form-group">
                    <label for="skill_name">Nome da Skill</label>
                    <input type="text" id="skill_name" name="skill_name" required>
                </div>
                <div class="form-group">
                    <label for="proficiency_level">Nível</label>
                    <select id="proficiency_level" name="proficiency_level" required>
                        <option value="iniciante">Iniciante</option>
                        <option value="intermediário">Intermediário</option>
                        <option value="avançado">Avançado</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="skill_description">Descrição (opcional)</label>
                    <input type="text" id="skill_description" name="skill_description">
                </div>
                <button type="submit" class="btn btn-success">Adicionar</button>
            </form>

            <!-- Lista de Skills -->
            <?php if ($skills): ?>
                <ul class="item-list">
                    <?php foreach ($skills as $skill): ?>
                        <li>
                            <span><?= htmlspecialchars($skill['skill_name']) ?> - <strong><?= htmlspecialchars($skill['proficiency_level']) ?></strong></span>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="remove_skill">
                                <input type="hidden" name="skill_id" value="<?= $skill['skill_id'] ?>">
                                <button type="submit" class="btn btn-danger" onclick="return confirm('Tem certeza que deseja remover esta skill?')">Remover</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>Nenhuma skill adicionada ainda.</p>
            <?php endif; ?>
        </div>

        <!-- Idiomas -->
        <div class="section">
            <h3>Idiomas</h3>
            
            <!-- Adicionar Idioma -->
            <form method="POST" class="form-inline">
                <input type="hidden" name="action" value="add_language">
                <div class="form-group">
                    <label for="language_name">Idioma</label>
                    <input type="text" id="language_name" name="language_name" required>
                </div>
                <div class="form-group">
                    <label for="proficiency">Proficiência</label>
                    <select id="proficiency" name="proficiency" required>
                        <option value="básico">Básico</option>
                        <option value="intermediário">Intermediário</option>
                        <option value="avançado">Avançado</option>
                        <option value="fluente">Fluente</option>
                        <option value="nativo">Nativo</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-success">Adicionar</button>
            </form>

            <!-- Lista de Idiomas -->
            <?php if ($languages): ?>
                <ul class="item-list">
                    <?php foreach ($languages as $language): ?>
                        <li>
                            <span><?= htmlspecialchars($language['language_name']) ?> - <strong><?= htmlspecialchars($language['proficiency']) ?></strong></span>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="remove_language">
                                <input type="hidden" name="language_id" value="<?= $language['language_id'] ?>">
                                <button type="submit" class="btn btn-danger" onclick="return confirm('Tem certeza que deseja remover este idioma?')">Remover</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>Nenhum idioma adicionado ainda.</p>
            <?php endif; ?>
        </div>

        <!-- Especializações -->
        <div class="section">
            <h3>Especializações</h3>
            
            <!-- Chef Specialization -->
            <h4>Especialização de Chef</h4>
            <form method="POST">
                <input type="hidden" name="action" value="update_chef_specialization">
                <div class="two-column">
                    <div class="form-group">
                        <label for="cuisine_type">Tipo de Culinária</label>
                        <input type="text" id="cuisine_type" name="cuisine_type" value="<?= htmlspecialchars($chef_spec['cuisine_type'] ?? '') ?>" placeholder="Ex: Italiana, Portuguesa, Asiática">
                    </div>
                    <div class="form-group">
                        <label for="certifications">Certificações</label>
                        <input type="text" id="certifications" name="certifications" value="<?= htmlspecialchars($chef_spec['certifications'] ?? '') ?>" placeholder="Suas certificações profissionais">
                    </div>
                </div>
                <div class="form-group">
                    <label for="dietary_specialties">Especialidades Dietéticas</label>
                    <input type="text" id="dietary_specialties" name="dietary_specialties" value="<?= htmlspecialchars($chef_spec['dietary_specialties'] ?? '') ?>" placeholder="Ex: Vegano, Sem Glúten, Keto">
                </div>
                <div class="checkbox-group">
                    <label><input type="checkbox" name="menu_planning" <?= ($chef_spec['menu_planning'] ?? 0) ? 'checked' : '' ?>> Planeamento de Menus</label>
                    <label><input type="checkbox" name="catering_experience" <?= ($chef_spec['catering_experience'] ?? 0) ? 'checked' : '' ?>> Experiência em Catering</label>
                </div>
                <button type="submit" class="btn">Atualizar Especialização Chef</button>
            </form>

            <!-- Cleaning Specialization -->
            <h4>Especialização de Limpeza</h4>
            <form method="POST">
                <input type="hidden" name="action" value="update_cleaning_specialization">
                <div class="form-group">
                    <label for="equipment_experience">Experiência com Equipamentos</label>
                    <textarea id="equipment_experience" name="equipment_experience" placeholder="Descreva sua experiência com equipamentos de limpeza..."><?= htmlspecialchars($cleaning_spec['equipment_experience'] ?? '') ?></textarea>
                </div>
                <div class="checkbox-group">
                    <label><input type="checkbox" name="kitchen_cleaning" <?= ($cleaning_spec['kitchen_cleaning'] ?? 0) ? 'checked' : '' ?>> Limpeza de Cozinha</label>
                    <label><input type="checkbox" name="dining_area_cleaning" <?= ($cleaning_spec['dining_area_cleaning'] ?? 0) ? 'checked' : '' ?>> Limpeza de Área de Refeições</label>
                    <label><input type="checkbox" name="eco_friendly" <?= ($cleaning_spec['eco_friendly'] ?? 0) ? 'checked' : '' ?>> Produtos Ecológicos</label>
                </div>
                <button type="submit" class="btn">Atualizar Especialização Limpeza</button>
            </form>

            <!-- Bartender Specialization -->
            <h4>Especialização de Bartender</h4>
            <form method="POST">
                <input type="hidden" name="action" value="update_bartender_specialization">
                <div class="form-group">
                    <label for="bartender_certifications">Certificações</label>
                    <input type="text" id="bartender_certifications" name="certifications" value="<?= htmlspecialchars($bartender_spec['certifications'] ?? '') ?>" placeholder="Suas certificações de bartender">
                </div>
                <div class="checkbox-group">
                    <label><input type="checkbox" name="cocktail_specialist" <?= ($bartender_spec['cocktail_specialist'] ?? 0) ? 'checked' : '' ?>> Especialista em Cocktails</label>
                    <label><input type="checkbox" name="wine_knowledge" <?= ($bartender_spec['wine_knowledge'] ?? 0) ? 'checked' : '' ?>> Conhecimento de Vinhos</label>
                    <label><input type="checkbox" name="beer_knowledge" <?= ($bartender_spec['beer_knowledge'] ?? 0) ? 'checked' : '' ?>> Conhecimento de Cervejas</label>
                    <label><input type="checkbox" name="flair_bartending" <?= ($bartender_spec['flair_bartending'] ?? 0) ? 'checked' : '' ?>> Flair Bartending</label>
                </div>
                <button type="submit" class="btn">Atualizar Especialização Bartender</button>
            </form>

            <!-- Service Staff Specialization -->
            <h4>Especialização de Atendimento</h4>
            <form method="POST">
                <input type="hidden" name="action" value="update_service_staff_specialization">
                <div class="form-group">
                    <label for="customer_service_rating">Avaliação de Atendimento ao Cliente (1-5)</label>
                    <select id="customer_service_rating" name="customer_service_rating">
                        <option value="">Selecione...</option>
                        <option value="1" <?= ($service_staff_spec['customer_service_rating'] ?? '') == '1' ? 'selected' : '' ?>>1 - Básico</option>
                        <option value="2" <?= ($service_staff_spec['customer_service_rating'] ?? '') == '2' ? 'selected' : '' ?>>2 - Regular</option>
                        <option value="3" <?= ($service_staff_spec['customer_service_rating'] ?? '') == '3' ? 'selected' : '' ?>>3 - Bom</option>
                        <option value="4" <?= ($service_staff_spec['customer_service_rating'] ?? '') == '4' ? 'selected' : '' ?>>4 - Muito Bom</option>
                        <option value="5" <?= ($service_staff_spec['customer_service_rating'] ?? '') == '5' ? 'selected' : '' ?>>5 - Excelente</option>
                    </select>
                </div>
                <div class="checkbox-group">
                    <label><input type="checkbox" name="fine_dining_experience" <?= ($service_staff_spec['fine_dining_experience'] ?? 0) ? 'checked' : '' ?>> Experiência em Fine Dining</label>
                    <label><input type="checkbox" name="event_service" <?= ($service_staff_spec['event_service'] ?? 0) ? 'checked' : '' ?>> Serviço de Eventos</label>
                    <label><input type="checkbox" name="sommelier_knowledge" <?= ($service_staff_spec['sommelier_knowledge'] ?? 0) ? 'checked' : '' ?>> Conhecimento de Sommelier</label>
                </div>
                <button type="submit" class="btn">Atualizar Especialização Atendimento</button>
            </form>
        </div>

        <!-- Serviços -->
        <div class="section">
            <h3>Meus Serviços</h3>
            
            <!-- Adicionar Serviço -->
            <h4>Adicionar Novo Serviço</h4>
            <form method="POST">
                <input type="hidden" name="action" value="add_service">
                <div class="two-column">
                    <div class="form-group">
                        <label for="title">Título do Serviço</label>
                        <input type="text" id="title" name="title" required>
                    </div>
                    <div class="form-group">
                        <label for="category_id">Categoria</label>
                        <select id="category_id" name="category_id" required>
                            <option value="">Selecione uma categoria</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= $category['category_id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="description">Descrição</label>
                    <textarea id="description" name="description" required placeholder="Descreva detalhadamente o seu serviço..."></textarea>
                </div>
                <div class="two-column">
                    <div class="form-group">
                        <label for="price_type">Tipo de Preço</label>
                        <select id="price_type" name="price_type" required>
                            <option value="hourly">Por Hora</option>
                            <option value="fixed">Preço Fixo</option>
                            <option value="daily">Por Dia</option>
                            <option value="project">Por Projeto</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="base_price">Preço Base (€)</label>
                        <input type="number" step="0.01" id="base_price" name="base_price" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="service_image_url">URL da Imagem (opcional)</label>
                    <input type="url" id="service_image_url" name="service_image_url" placeholder="https://exemplo.com/imagem.jpg">
                </div>
                <button type="submit" class="btn btn-success">Adicionar Serviço</button>
            </form>

            <!-- Lista de Serviços -->
            <h4>Meus Serviços Atuais</h4>
            <?php if ($services): ?>
                <?php foreach ($services as $service): ?>
                    <div class="service-item">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                            <div>
                                <h5 style="margin: 0; color: #333;"><?= htmlspecialchars($service['title']) ?></h5>
                                <small style="color: #666;">Categoria: <?= htmlspecialchars($service['category_name']) ?></small>
                                <span class="service-status <?= $service['is_active'] ? 'status-active' : 'status-inactive' ?>" style="margin-left: 10px;">
                                    <?= $service['is_active'] ? 'Ativo' : 'Inativo' ?>
                                </span>
                            </div>
                            <div style="font-weight: bold; color: #28a745;">
                                €<?= number_format($service['base_price'], 2) ?> 
                                <small style="color: #666;">(<?= ucfirst($service['price_type']) ?>)</small>
                            </div>
                        </div>
                        
                        <p style="margin: 10px 0; color: #555;"><?= htmlspecialchars($service['description']) ?></p>
                        
                        <?php if ($service['service_image_url']): ?>
                            <p><small>Imagem: <a href="<?= htmlspecialchars($service['service_image_url']) ?>" target="_blank">Ver imagem</a></small></p>
                        <?php endif; ?>
                        
                        <!-- Formulário de Edição -->
                        <form method="POST" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee;">
                            <input type="hidden" name="action" value="update_service">
                            <input type="hidden" name="service_id" value="<?= $service['service_id'] ?>">
                            
                            <div class="two-column">
                                <div class="form-group">
                                    <label>Título</label>
                                    <input type="text" name="title" value="<?= htmlspecialchars($service['title']) ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Categoria</label>
                                    <select name="category_id" required>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?= $category['category_id'] ?>" <?= $category['category_id'] == $service['category_id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($category['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Descrição</label>
                                <textarea name="description" required><?= htmlspecialchars($service['description']) ?></textarea>
                            </div>
                            
                            <div class="two-column">
                                <div class="form-group">
                                    <label>Tipo de Preço</label>
                                    <select name="price_type" required>
                                        <option value="hourly" <?= $service['price_type'] === 'hourly' ? 'selected' : '' ?>>Por Hora</option>
                                        <option value="fixed" <?= $service['price_type'] === 'fixed' ? 'selected' : '' ?>>Preço Fixo</option>
                                        <option value="daily" <?= $service['price_type'] === 'daily' ? 'selected' : '' ?>>Por Dia</option>
                                        <option value="project" <?= $service['price_type'] === 'project' ? 'selected' : '' ?>>Por Projeto</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Preço Base (€)</label>
                                    <input type="number" step="0.01" name="base_price" value="<?= $service['base_price'] ?>" required>
                                </div>
                            </div>
                            
                            <div class="two-column">
                                <div class="form-group">
                                    <label>URL da Imagem</label>
                                    <input type="url" name="service_image_url" value="<?= htmlspecialchars($service['service_image_url'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label>Status</label>
                                    <select name="is_active">
                                        <option value="1" <?= $service['is_active'] ? 'selected' : '' ?>>Ativo</option>
                                        <option value="0" <?= !$service['is_active'] ? 'selected' : '' ?>>Inativo</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div style="display: flex; gap: 10px;">
                                <button type="submit" class="btn">Atualizar Serviço</button>
                                <button type="button" class="btn btn-danger" onclick="removeService(<?= $service['service_id'] ?>)">Remover Serviço</button>
                            </div>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>Nenhum serviço adicionado ainda.</p>
            <?php endif; ?>
        </div>

        <!-- Links de Navegação -->
        <div class="section">
            <h3>Navegação</h3>
            <p>
                <a href="../Services/main_service/index.php" class="btn">Voltar ao Dashboard</a>
                <a href="logout.php" class="btn btn-danger">Sair</a>
            </p>
        </div>
    </div>

    <script>
        function removeService(serviceId) {
            if (confirm('Tem certeza que deseja remover este serviço? Esta ação não pode ser desfeita.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="remove_service">
                    <input type="hidden" name="service_id" value="${serviceId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    alert.style.opacity = '0';
                    setTimeout(function() {
                        alert.remove();
                    }, 300);
                }, 5000);
            });
        });

        // Form validation
        document.querySelectorAll('form').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                const requiredFields = form.querySelectorAll('[required]');
                let isValid = true;
                
                requiredFields.forEach(function(field) {
                    if (!field.value.trim()) {
                        field.style.borderColor = '#dc3545';
                        isValid = false;
                    } else {
                        field.style.borderColor = '#ddd';
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    alert('Por favor, preencha todos os campos obrigatórios.');
                }
            });
        });
    </script>
</body>
</html>