<?php
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Conectar ao banco de dados
try {
    $pdo = new PDO('sqlite:TesteOlga.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Erro na conexão: ' . $e->getMessage());
}

// Função para escapar HTML
function safeHtml($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Obter o freelancer_id do usuário logado
$stmt = $pdo->prepare("SELECT profile_id FROM FreelancerProfiles WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$freelancer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$freelancer) {
    die('Perfil de freelancer não encontrado.');
}

$freelancer_id = $freelancer['profile_id'];

// Processar formulário quando enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_skill':
                $skill_id = $_POST['skill_id'];
                $proficiency = $_POST['proficiency'];
                
                $stmt = $pdo->prepare("INSERT OR REPLACE INTO FreelancerSkills (freelancer_id, skill_id, proficiency_level) VALUES (?, ?, ?)");
                $stmt->execute([$freelancer_id, $skill_id, $proficiency]);
                break;
                
            case 'remove_skill':
                $skill_id = $_POST['skill_id'];
                $stmt = $pdo->prepare("DELETE FROM FreelancerSkills WHERE freelancer_id = ? AND skill_id = ?");
                $stmt->execute([$freelancer_id, $skill_id]);
                break;
                
            case 'add_language':
                $language_id = $_POST['language_id'];
                $proficiency = $_POST['proficiency'];
                
                $stmt = $pdo->prepare("INSERT OR REPLACE INTO FreelancerLanguages (freelancer_id, language_id, proficiency) VALUES (?, ?, ?)");
                $stmt->execute([$freelancer_id, $language_id, $proficiency]);
                break;
                
            case 'remove_language':
                $language_id = $_POST['language_id'];
                $stmt = $pdo->prepare("DELETE FROM FreelancerLanguages WHERE freelancer_id = ? AND language_id = ?");
                $stmt->execute([$freelancer_id, $language_id]);
                break;
                
            case 'update_chef':
                $cuisine_type = $_POST['cuisine_type'];
                $certifications = $_POST['certifications'];
                $dietary_specialties = $_POST['dietary_specialties'];
                $menu_planning = isset($_POST['menu_planning']) ? 1 : 0;
                $catering_experience = isset($_POST['catering_experience']) ? 1 : 0;
                
                $stmt = $pdo->prepare("INSERT OR REPLACE INTO ChefSpecializations (freelancer_id, cuisine_type, certifications, dietary_specialties, menu_planning, catering_experience) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$freelancer_id, $cuisine_type, $certifications, $dietary_specialties, $menu_planning, $catering_experience]);
                break;
                
            case 'update_cleaning':
                $kitchen_cleaning = isset($_POST['kitchen_cleaning']) ? 1 : 0;
                $dining_area_cleaning = isset($_POST['dining_area_cleaning']) ? 1 : 0;
                $equipment_experience = $_POST['equipment_experience'];
                $eco_friendly = isset($_POST['eco_friendly']) ? 1 : 0;
                
                $stmt = $pdo->prepare("INSERT OR REPLACE INTO CleaningSpecializations (freelancer_id, kitchen_cleaning, dining_area_cleaning, equipment_experience, eco_friendly) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$freelancer_id, $kitchen_cleaning, $dining_area_cleaning, $equipment_experience, $eco_friendly]);
                break;
                
            case 'update_bartender':
                $cocktail_specialist = isset($_POST['cocktail_specialist']) ? 1 : 0;
                $wine_knowledge = isset($_POST['wine_knowledge']) ? 1 : 0;
                $beer_knowledge = isset($_POST['beer_knowledge']) ? 1 : 0;
                $flair_bartending = isset($_POST['flair_bartending']) ? 1 : 0;
                $certifications = $_POST['certifications'];
                
                $stmt = $pdo->prepare("INSERT OR REPLACE INTO BartenderSpecializations (freelancer_id, cocktail_specialist, wine_knowledge, beer_knowledge, flair_bartending, certifications) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$freelancer_id, $cocktail_specialist, $wine_knowledge, $beer_knowledge, $flair_bartending, $certifications]);
                break;
                
            case 'update_service_staff':
                $fine_dining_experience = isset($_POST['fine_dining_experience']) ? 1 : 0;
                $event_service = isset($_POST['event_service']) ? 1 : 0;
                $sommelier_knowledge = isset($_POST['sommelier_knowledge']) ? 1 : 0;
                $customer_service_rating = $_POST['customer_service_rating'];
                
                $stmt = $pdo->prepare("INSERT OR REPLACE INTO ServiceStaffSpecializations (freelancer_id, fine_dining_experience, event_service, sommelier_knowledge, customer_service_rating) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$freelancer_id, $fine_dining_experience, $event_service, $sommelier_knowledge, $customer_service_rating]);
                break;
        }
        
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Obter skills do freelancer
$stmt = $pdo->prepare("
    SELECT fs.skill_id, s.skill_name, fs.proficiency_level 
    FROM FreelancerSkills fs 
    JOIN Skills s ON fs.skill_id = s.skill_id 
    WHERE fs.freelancer_id = ?
");
$stmt->execute([$freelancer_id]);
$user_skills = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obter todas as skills disponíveis
$stmt = $pdo->query("SELECT skill_id, skill_name FROM Skills ORDER BY skill_name");
$all_skills = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obter idiomas do freelancer
$stmt = $pdo->prepare("
    SELECT fl.language_id, l.language_name, fl.proficiency 
    FROM FreelancerLanguages fl 
    JOIN Languages l ON fl.language_id = l.language_id 
    WHERE fl.freelancer_id = ?
");
$stmt->execute([$freelancer_id]);
$user_languages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obter todos os idiomas disponíveis
$stmt = $pdo->query("SELECT language_id, language_name FROM Languages ORDER BY language_name");
$all_languages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obter especializações
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
$service_spec = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Skills e Especializações</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .section {
            margin-bottom: 30px;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
        }
        .section h2 {
            margin-top: 0;
            color: #333;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input, select, textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        button {
            background-color: #007bff;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 10px;
        }
        button:hover {
            background-color: #0056b3;
        }
        .btn-danger {
            background-color: #dc3545;
        }
        .btn-danger:hover {
            background-color: #c82333;
        }
        .skill-item, .language-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            margin: 5px 0;
            background-color: #f8f9fa;
            border-radius: 4px;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin-right: 10px;
        }
        .specialization-form {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin-top: 15px;
        }
    </style>
</head>
<!-- Seção de Skills -->
<div class="tab-pane fade" id="skills" role="tabpanel"> 
    <h2>Minhas Skills</h2>
    
    <!-- Skills atuais -->
    <?php if (!empty($user_skills)): ?>
        <h3>Skills Cadastradas:</h3>
        <?php foreach ($user_skills as $skill): ?>
            <div class="skill-item">
                <span>
                    <strong><?php echo safeHtml($skill['skill_name']); ?></strong>
                    <?php if ($skill['proficiency_level']): ?>
                        - <?php echo safeHtml($skill['proficiency_level']); ?>
                    <?php endif; ?>
                </span>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="remove_skill">
                    <input type="hidden" name="skill_id" value="<?php echo $skill['skill_id']; ?>">
                    <button type="submit" class="btn-danger" onclick="return confirm('Tem certeza que deseja remover esta skill?')">Remover</button>
                </form>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>Nenhuma skill cadastrada ainda.</p>
    <?php endif; ?>

    <!-- Adicionar nova skill -->
    <h3>Adicionar Nova Skills:</h3>
    <form method="POST" class="specialization-form">
        <input type="hidden" name="action" value="add_skill">
        <div class="form-group">
            <label for="skill_id">Skill:</label>
            <select name="skill_id" id="skill_id" required>
                <option value="">Selecione uma skill</option>
                <?php foreach ($all_skills as $skill): ?>
                    <option value="<?php echo $skill['skill_id']; ?>"><?php echo safeHtml($skill['skill_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="proficiency">Nível de Proficiência:</label>
            <select name="proficiency" id="proficiency">
                <option value="">Selecione o nível</option>
                <option value="Iniciante">Iniciante</option>
                <option value="Intermediário">Intermediário</option>
                <option value="Avançado">Avançado</option>
                <option value="Especialista">Especialista</option>
            </select>
        </div>
        <button type="submit">Adicionar Skill</button>
    </form>
</div>

<!-- Seção de Idiomas -->
<div class="section">
    <h2>Idiomas</h2>
    
    <!-- Idiomas atuais -->
    <?php if (!empty($user_languages)): ?>
        <h3>Idiomas Cadastrados:</h3>
        <?php foreach ($user_languages as $language): ?>
            <div class="language-item">
                <span>
                    <strong><?php echo safeHtml($language['language_name']); ?></strong>
                    <?php if ($language['proficiency']): ?>
                        - <?php echo safeHtml($language['proficiency']); ?>
                    <?php endif; ?>
                </span>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="remove_language">
                    <input type="hidden" name="language_id" value="<?php echo $language['language_id']; ?>">
                    <button type="submit" class="btn-danger" onclick="return confirm('Tem certeza que deseja remover este idioma?')">Remover</button>
                </form>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>Nenhum idioma cadastrado ainda.</p>
    <?php endif; ?>

    <!-- Adicionar novo idioma -->
    <h3>Adicionar Novo Idioma:</h3>
    <form method="POST" class="specialization-form">
        <input type="hidden" name="action" value="add_language">
        <div class="form-group">
            <label for="language_id">Idioma:</label>
            <select name="language_id" id="language_id" required>
                <option value="">Selecione um idioma</option>
                <?php foreach ($all_languages as $language): ?>
                    <option value="<?php echo $language['language_id']; ?>"><?php echo safeHtml($language['language_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="lang_proficiency">Nível de Proficiência:</label>
            <select name="proficiency" id="lang_proficiency">
                <option value="">Selecione o nível</option>
                <option value="Básico">Básico</option>
                <option value="Intermediário">Intermediário</option>
                <option value="Avançado">Avançado</option>
                <option value="Nativo">Nativo</option>
            </select>
        </div>
        <button type="submit">Adicionar Idioma</button>
    </form>
</div>

<!-- Especialização como Chef -->
<div class="section">
    <h2>Especialização como Chef</h2>
    <form method="POST" class="specialization-form">
        <input type="hidden" name="action" value="update_chef">
        <div class="form-group">
            <label for="cuisine_type">Tipo de Culinária:</label>
            <input type="text" name="cuisine_type" id="cuisine_type" 
                    value="<?php echo safeHtml($chef_spec['cuisine_type'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label for="chef_certifications">Certificações:</label>
            <textarea name="certifications" id="chef_certifications" rows="3"><?php echo safeHtml($chef_spec['certifications'] ?? ''); ?></textarea>
        </div>
        <div class="form-group">
            <label for="dietary_specialties">Especialidades Dietéticas:</label>
            <textarea name="dietary_specialties" id="dietary_specialties" rows="3"><?php echo safeHtml($chef_spec['dietary_specialties'] ?? ''); ?></textarea>
        </div>
        <div class="checkbox-group">
            <input type="checkbox" name="menu_planning" id="menu_planning" 
                    <?php echo ($chef_spec['menu_planning'] ?? 0) ? 'checked' : ''; ?>>
            <label for="menu_planning">Planejamento de Menu</label>
        </div>
        <div class="checkbox-group">
            <input type="checkbox" name="catering_experience" id="catering_experience" 
                    <?php echo ($chef_spec['catering_experience'] ?? 0) ? 'checked' : ''; ?>>
            <label for="catering_experience">Experiência em Catering</label>
        </div>
        <button type="submit">Salvar Especialização Chef</button>
    </form>
</div>

<!-- Especialização em Limpeza -->
<div class="section">
    <h2>Especialização em Limpeza</h2>
    <form method="POST" class="specialization-form">
        <input type="hidden" name="action" value="update_cleaning">
        <div class="checkbox-group">
            <input type="checkbox" name="kitchen_cleaning" id="kitchen_cleaning" 
                    <?php echo ($cleaning_spec['kitchen_cleaning'] ?? 0) ? 'checked' : ''; ?>>
            <label for="kitchen_cleaning">Limpeza de Cozinha</label>
        </div>
        <div class="checkbox-group">
            <input type="checkbox" name="dining_area_cleaning" id="dining_area_cleaning" 
                    <?php echo ($cleaning_spec['dining_area_cleaning'] ?? 0) ? 'checked' : ''; ?>>
            <label for="dining_area_cleaning">Limpeza de Área de Jantar</label>
        </div>
        <div class="form-group">
            <label for="equipment_experience">Experiência com Equipamentos:</label>
            <textarea name="equipment_experience" id="equipment_experience" rows="3"><?php echo safeHtml($cleaning_spec['equipment_experience'] ?? ''); ?></textarea>
        </div>
        <div class="checkbox-group">
            <input type="checkbox" name="eco_friendly" id="eco_friendly" 
                    <?php echo ($cleaning_spec['eco_friendly'] ?? 0) ? 'checked' : ''; ?>>
            <label for="eco_friendly">Métodos Ecológicos</label>
        </div>
        <button type="submit">Salvar Especialização Limpeza</button>
    </form>
</div>

<!-- Especialização como Bartender -->
<div class="section">
    <h2>Especialização como Bartender</h2>
    <form method="POST" class="specialization-form">
        <input type="hidden" name="action" value="update_bartender">
        <div class="checkbox-group">
            <input type="checkbox" name="cocktail_specialist" id="cocktail_specialist" 
                    <?php echo ($bartender_spec['cocktail_specialist'] ?? 0) ? 'checked' : ''; ?>>
            <label for="cocktail_specialist">Especialista em Coquetéis</label>
        </div>
        <div class="checkbox-group">
            <input type="checkbox" name="wine_knowledge" id="wine_knowledge" 
                    <?php echo ($bartender_spec['wine_knowledge'] ?? 0) ? 'checked' : ''; ?>>
            <label for="wine_knowledge">Conhecimento em Vinhos</label>
        </div>
        <div class="checkbox-group">
            <input type="checkbox" name="beer_knowledge" id="beer_knowledge" 
                    <?php echo ($bartender_spec['beer_knowledge'] ?? 0) ? 'checked' : ''; ?>>
            <label for="beer_knowledge">Conhecimento em Cervejas</label>
        </div>
        <div class="checkbox-group">
            <input type="checkbox" name="flair_bartending" id="flair_bartending" 
                    <?php echo ($bartender_spec['flair_bartending'] ?? 0) ? 'checked' : ''; ?>>
            <label for="flair_bartending">Flair Bartending</label>
        </div>
        <div class="form-group">
            <label for="bartender_certifications">Certificações:</label>
            <textarea name="certifications" id="bartender_certifications" rows="3"><?php echo safeHtml($bartender_spec['certifications'] ?? ''); ?></textarea>
        </div>
        <button type="submit">Salvar Especialização Bartender</button>
    </form>
</div>

<!-- Especialização em Atendimento -->
<div class="section">
    <h2>Especialização em Atendimento</h2>
    <form method="POST" class="specialization-form">
        <input type="hidden" name="action" value="update_service_staff">
        <div class="checkbox-group">
            <input type="checkbox" name="fine_dining_experience" id="fine_dining_experience" 
                    <?php echo ($service_spec['fine_dining_experience'] ?? 0) ? 'checked' : ''; ?>>
            <label for="fine_dining_experience">Experiência em Fine Dining</label>
        </div>
        <div class="checkbox-group">
            <input type="checkbox" name="event_service" id="event_service" 
                    <?php echo ($service_spec['event_service'] ?? 0) ? 'checked' : ''; ?>>
            <label for="event_service">Serviço de Eventos</label>
        </div>
        <div class="checkbox-group">
            <input type="checkbox" name="sommelier_knowledge" id="sommelier_knowledge" 
                    <?php echo ($service_spec['sommelier_knowledge'] ?? 0) ? 'checked' : ''; ?>>
            <label for="sommelier_knowledge">Conhecimento de Sommelier</label>
        </div>
        <div class="form-group">
            <label for="customer_service_rating">Avaliação de Atendimento ao Cliente (1-5):</label>
            <select name="customer_service_rating" id="customer_service_rating">
                <option value="">Selecione</option>
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <option value="<?php echo $i; ?>" <?php echo ($service_spec['customer_service_rating'] ?? 0) == $i ? 'selected' : ''; ?>><?php echo $i; ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <button type="submit">Salvar Especialização Atendimento</button>
    </form>
</div>
</html>