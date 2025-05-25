<?php
header('Content-Type: application/json');

try {
    $pdo = new PDO('sqlite:TesteOlga.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro de conexão: ' . $e->getMessage()]);
    exit;
}

$action = $_POST['action'] ?? '';
$freelancer_id = $_POST['freelancer_id'] ?? '';

if (empty($freelancer_id)) {
    echo json_encode(['success' => false, 'message' => 'ID do freelancer não fornecido']);
    exit;
}

try {
    switch ($action) {
        case 'update_skills':
            updateSkills($pdo, $freelancer_id, $_POST['skills'] ?? []);
            break;
            
        case 'update_languages':
            updateLanguages($pdo, $freelancer_id, $_POST['languages'] ?? []);
            break;
            
        case 'update_chef':
            updateChefSpecialization($pdo, $freelancer_id, $_POST);
            break;
            
        case 'update_cleaning':
            updateCleaningSpecialization($pdo, $freelancer_id, $_POST);
            break;
            
        case 'update_bartender':
            updateBartenderSpecialization($pdo, $freelancer_id, $_POST);
            break;
            
        case 'update_service_staff':
            updateServiceStaffSpecialization($pdo, $freelancer_id, $_POST);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Ação não reconhecida']);
            exit;
    }
    
    echo json_encode(['success' => true, 'message' => 'Dados atualizados com sucesso']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}

function updateSkills($pdo, $freelancer_id, $skills) {
    // Primeiro, remover todas as skills existentes
    $stmt = $pdo->prepare("DELETE FROM FreelancerSkills WHERE freelancer_id = ?");
    $stmt->execute([$freelancer_id]);
    
    // Inserir as novas skills
    foreach ($skills as $skill) {
        if (!empty($skill['name'])) {
            // Verificar se a skill existe na tabela Skills, se não, criar
            $stmt = $pdo->prepare("SELECT skill_id FROM Skills WHERE skill_name = ?");
            $stmt->execute([$skill['name']]);
            $skill_id = $stmt->fetchColumn();
            
            if (!$skill_id) {
                $stmt = $pdo->prepare("INSERT INTO Skills (skill_name) VALUES (?)");
                $stmt->execute([$skill['name']]);
                $skill_id = $pdo->lastInsertId();
            }
            
            // Inserir na tabela FreelancerSkills
            $stmt = $pdo->prepare("INSERT INTO FreelancerSkills (freelancer_id, skill_id, proficiency_level) VALUES (?, ?, ?)");
            $stmt->execute([$freelancer_id, $skill_id, $skill['proficiency'] ?? null]);
        }
    }
}

function updateLanguages($pdo, $freelancer_id, $languages) {
    // Primeiro, remover todos os idiomas existentes
    $stmt = $pdo->prepare("DELETE FROM FreelancerLanguages WHERE freelancer_id = ?");
    $stmt->execute([$freelancer_id]);
    
    // Inserir os novos idiomas
    foreach ($languages as $language) {
        if (!empty($language['name'])) {
            // Verificar se o idioma existe na tabela Languages, se não, criar
            $stmt = $pdo->prepare("SELECT language_id FROM Languages WHERE language_name = ?");
            $stmt->execute([$language['name']]);
            $language_id = $stmt->fetchColumn();
            
            if (!$language_id) {
                $stmt = $pdo->prepare("INSERT INTO Languages (language_name) VALUES (?)");
                $stmt->execute([$language['name']]);
                $language_id = $pdo->lastInsertId();
            }
            
            // Inserir na tabela FreelancerLanguages
            $stmt = $pdo->prepare("INSERT INTO FreelancerLanguages (freelancer_id, language_id, proficiency) VALUES (?, ?, ?)");
            $stmt->execute([$freelancer_id, $language_id, $language['proficiency'] ?? null]);
        }
    }
}

function updateChefSpecialization($pdo, $freelancer_id, $data) {
    // Verificar se já existe uma especialização de chef
    $stmt = $pdo->prepare("SELECT chef_id FROM ChefSpecializations WHERE freelancer_id = ?");
    $stmt->execute([$freelancer_id]);
    $exists = $stmt->fetchColumn();
    
    $cuisine_type = $data['cuisine_type'] ?? '';
    $certifications = $data['certifications'] ?? '';
    $dietary_specialties = $data['dietary_specialties'] ?? '';
    $menu_planning = isset($data['menu_planning']) ? 1 : 0;
    $catering_experience = isset($data['catering_experience']) ? 1 : 0;
    
    if ($exists) {
        // Atualizar registro existente
        $stmt = $pdo->prepare("
            UPDATE ChefSpecializations 
            SET cuisine_type = ?, certifications = ?, dietary_specialties = ?, menu_planning = ?, catering_experience = ?
            WHERE freelancer_id = ?
        ");
        $stmt->execute([$cuisine_type, $certifications, $dietary_specialties, $menu_planning, $catering_experience, $freelancer_id]);
    } else {
        // Criar novo registro
        $stmt = $pdo->prepare("
            INSERT INTO ChefSpecializations (freelancer_id, cuisine_type, certifications, dietary_specialties, menu_planning, catering_experience)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$freelancer_id, $cuisine_type, $certifications, $dietary_specialties, $menu_planning, $catering_experience]);
    }
}

function updateCleaningSpecialization($pdo, $freelancer_id, $data) {
    // Verificar se já existe uma especialização de limpeza
    $stmt = $pdo->prepare("SELECT cleaning_id FROM CleaningSpecializations WHERE freelancer_id = ?");
    $stmt->execute([$freelancer_id]);
    $exists = $stmt->fetchColumn();
    
    $kitchen_cleaning = isset($data['kitchen_cleaning']) ? 1 : 0;
    $dining_area_cleaning = isset($data['dining_area_cleaning']) ? 1 : 0;
    $equipment_experience = $data['equipment_experience'] ?? '';
    $eco_friendly = isset($data['eco_friendly']) ? 1 : 0;
    
    if ($exists) {
        // Atualizar registro existente
        $stmt = $pdo->prepare("
            UPDATE CleaningSpecializations 
            SET kitchen_cleaning = ?, dining_area_cleaning = ?, equipment_experience = ?, eco_friendly = ?
            WHERE freelancer_id = ?
        ");
        $stmt->execute([$kitchen_cleaning, $dining_area_cleaning, $equipment_experience, $eco_friendly, $freelancer_id]);
    } else {
        // Criar novo registro
        $stmt = $pdo->prepare("
            INSERT INTO CleaningSpecializations (freelancer_id, kitchen_cleaning, dining_area_cleaning, equipment_experience, eco_friendly)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$freelancer_id, $kitchen_cleaning, $dining_area_cleaning, $equipment_experience, $eco_friendly]);
    }
}

function updateBartenderSpecialization($pdo, $freelancer_id, $data) {
    // Verificar se já existe uma especialização de bartender
    $stmt = $pdo->prepare("SELECT bartender_id FROM BartenderSpecializations WHERE freelancer_id = ?");
    $stmt->execute([$freelancer_id]);
    $exists = $stmt->fetchColumn();
    
    $cocktail_specialist = isset($data['cocktail_specialist']) ? 1 : 0;
    $wine_knowledge = isset($data['wine_knowledge']) ? 1 : 0;
    $beer_knowledge = isset($data['beer_knowledge']) ? 1 : 0;
    $flair_bartending = isset($data['flair_bartending']) ? 1 : 0;
    $certifications = $data['certifications'] ?? '';
    
    if ($exists) {
        // Atualizar registro existente
        $stmt = $pdo->prepare("
            UPDATE BartenderSpecializations 
            SET cocktail_specialist = ?, wine_knowledge = ?, beer_knowledge = ?, flair_bartending = ?, certifications = ?
            WHERE freelancer_id = ?
        ");
        $stmt->execute([$cocktail_specialist, $wine_knowledge, $beer_knowledge, $flair_bartending, $certifications, $freelancer_id]);
    } else {
        // Criar novo registro
        $stmt = $pdo->prepare("
            INSERT INTO BartenderSpecializations (freelancer_id, cocktail_specialist, wine_knowledge, beer_knowledge, flair_bartending, certifications)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$freelancer_id, $cocktail_specialist, $wine_knowledge, $beer_knowledge, $flair_bartending, $certifications]);
    }
}

function updateServiceStaffSpecialization($pdo, $freelancer_id, $data) {
    // Verificar se já existe uma especialização de service staff
    $stmt = $pdo->prepare("SELECT service_staff_id FROM ServiceStaffSpecializations WHERE freelancer_id = ?");
    $stmt->execute([$freelancer_id]);
    $exists = $stmt->fetchColumn();
    
    $fine_dining_experience = isset($data['fine_dining_experience']) ? 1 : 0;
    $event_service = isset($data['event_service']) ? 1 : 0;
    $sommelier_knowledge = isset($data['sommelier_knowledge']) ? 1 : 0;
    $customer_service_rating = $data['customer_service_rating'] ?? null;
    
    if ($exists) {
        // Atualizar registro existente
        $stmt = $pdo->prepare("
            UPDATE ServiceStaffSpecializations 
            SET fine_dining_experience = ?, event_service = ?, sommelier_knowledge = ?, customer_service_rating = ?
            WHERE freelancer_id = ?
        ");
        $stmt->execute([$fine_dining_experience, $event_service, $sommelier_knowledge, $customer_service_rating, $freelancer_id]);
    } else {
        // Criar novo registro
        $stmt = $pdo->prepare("
            INSERT INTO ServiceStaffSpecializations (freelancer_id, fine_dining_experience, event_service, sommelier_knowledge, customer_service_rating)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$freelancer_id, $fine_dining_experience, $event_service, $sommelier_knowledge, $customer_service_rating]);
    }
}
?>