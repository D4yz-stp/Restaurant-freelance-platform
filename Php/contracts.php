<?php
session_start();

// Caminho absoluto para a base de dados
$db_path ='../database/TesteOlga.db';
if (!file_exists($db_path)) {
    die('Base de dados não encontrada em: ' . $db_path);
}

$db = new SQLite3($db_path);
$db->exec('PRAGMA foreign_keys = ON;');

// Verificar sessão
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../Html/Log/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['user_role'] ?? '';

// Debug: Verificar informações do usuário atual
function debugUserInfo($db, $user_id, $role) {
    echo "<!-- DEBUG INFO:\n";
    echo "User ID: " . $user_id . "\n";
    echo "Role: " . $role . "\n";
    
    if ($role === 'restaurant') {
        $stmt = $db->prepare("SELECT * FROM RestaurantProfiles WHERE user_id = :user_id");
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $restaurant = $result->fetchArray(SQLITE3_ASSOC);
        echo "Restaurant Profile: " . print_r($restaurant, true) . "\n";
    }
    echo "-->\n";
}

// Adicionar debug temporário
if (isset($_GET['debug'])) {
    debugUserInfo($db, $user_id, $role);
}

// Handle contract rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_contract'])) {
    $contract_id = isset($_POST['contract_id']) ? intval($_POST['contract_id']) : 0;

    if ($contract_id > 0) {
        $stmt = $db->prepare("UPDATE Contracts SET status = 'rejeitado' WHERE contract_id = :id");
        $stmt->bindValue(':id', $contract_id, SQLITE3_INTEGER);
        $stmt->execute();

        // Redirect to avoid resubmission
        header("Location: contracts.php");
        exit;
    }
}

/* ===============================
   1. Aceitar / Recusar Contrato
=============================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $contract_id = (int) ($_POST['contract_id'] ?? 0);
    $action = $_POST['action'];

    if ($role === 'freelancer' && $contract_id > 0) {
        $new_status = $action === 'accept' ? 'aceite' : ($action === 'decline' ? 'recusado' : null);

        if ($new_status) {
            $stmt = $db->prepare("
                UPDATE Contracts
                SET status = :status, response_date = CURRENT_TIMESTAMP
                WHERE contract_id = :contract_id
                AND freelancer_id = (SELECT profile_id FROM FreelancerProfiles WHERE user_id = :user_id)
                AND status = 'pendente'
            ");
            if ($stmt) {
                $stmt->bindValue(':status', $new_status, SQLITE3_TEXT);
                $stmt->bindValue(':contract_id', $contract_id, SQLITE3_INTEGER);
                $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
                $stmt->execute();
            }
        }
    }

    header('Location: contracts.php');
    exit;
}

/* ===============================
   2. Criar Contrato (restaurant)
=============================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_contract']) && $role === 'restaurant') {
    $freelancer_id = (int) ($_POST['freelancer_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $hourly_rate = (float) ($_POST['hourly_rate'] ?? 0);
    $hours_per_week = (int) ($_POST['hours_per_week'] ?? 0);
    $agreed_price = (float) ($_POST['agreed_price'] ?? 0);
    $payment_type = $_POST['payment_type'] ?? '';

    // Primeiro, verificar se o restaurante existe
    $restaurant_check = $db->prepare("SELECT restaurant_id FROM RestaurantProfiles WHERE user_id = :user_id");
    $restaurant_check->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $restaurant_result = $restaurant_check->execute();
    $restaurant_data = $restaurant_result->fetchArray(SQLITE3_ASSOC);

    if (!$restaurant_data) {
        die("Erro: Perfil de restaurante não encontrado para o usuário ID: " . $user_id);
    }

    $restaurant_id = $restaurant_data['restaurant_id'];

    // Verificar se o freelancer existe
    $freelancer_check = $db->prepare("SELECT profile_id FROM FreelancerProfiles WHERE profile_id = :freelancer_id");
    $freelancer_check->bindValue(':freelancer_id', $freelancer_id, SQLITE3_INTEGER);
    $freelancer_result = $freelancer_check->execute();
    $freelancer_data = $freelancer_result->fetchArray(SQLITE3_ASSOC);

    if (!$freelancer_data) {
        die("Erro: Freelancer não encontrado com ID: " . $freelancer_id);
    }

    if ($freelancer_id > 0 && $title && $start_date && $end_date && $agreed_price > 0 && $payment_type) {
        $stmt = $db->prepare("
            INSERT INTO Contracts (
                restaurant_id, freelancer_id, title, description,
                start_date, end_date, hourly_rate, hours_per_week,
                agreed_price, payment_type, status, created_at
            ) VALUES (
                :restaurant_id, :freelancer_id, :title, :description,
                :start_date, :end_date, :hourly_rate, :hours_per_week,
                :agreed_price, :payment_type, 'pendente', CURRENT_TIMESTAMP
            )
        ");

        if (!$stmt) {
            die("Erro ao preparar statement: " . $db->lastErrorMsg());
        }

        $stmt->bindValue(':restaurant_id', $restaurant_id, SQLITE3_INTEGER);
        $stmt->bindValue(':freelancer_id', $freelancer_id, SQLITE3_INTEGER);
        $stmt->bindValue(':title', $title, SQLITE3_TEXT);
        $stmt->bindValue(':description', $description, SQLITE3_TEXT);
        $stmt->bindValue(':start_date', $start_date, SQLITE3_TEXT);
        $stmt->bindValue(':end_date', $end_date, SQLITE3_TEXT);
        $stmt->bindValue(':hourly_rate', $hourly_rate, SQLITE3_FLOAT);
        $stmt->bindValue(':hours_per_week', $hours_per_week, SQLITE3_INTEGER);
        $stmt->bindValue(':agreed_price', $agreed_price, SQLITE3_FLOAT);
        $stmt->bindValue(':payment_type', $payment_type, SQLITE3_TEXT);

        $result = $stmt->execute();
        
        if ($result) {
            $insertId = $db->lastInsertRowID();
            $_SESSION['success_message'] = "Contrato criado com sucesso! ID: " . $insertId;
        } else {
            $_SESSION['error_message'] = "Erro ao criar contrato: " . $db->lastErrorMsg();
        }
    } else {
        $_SESSION['error_message'] = "Por favor, preencha todos os campos obrigatórios.";
    }

    header('Location: contracts.php');
    exit;
}

/* ===============================
   3. Consultar Contratos
=============================== */
$contracts = [];

if ($role === 'freelancer') {
    $stmt = $db->prepare("
        SELECT c.*, 
               u.first_name AS manager_first_name,
               u.last_name AS manager_last_name,
               r.restaurant_name
        FROM Contracts c
        JOIN RestaurantProfiles r ON c.restaurant_id = r.restaurant_id
        JOIN Users u ON r.user_id = u.user_id
        WHERE c.freelancer_id = (
            SELECT profile_id FROM FreelancerProfiles WHERE user_id = :user_id
        )
        ORDER BY c.created_at DESC
    ");
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $contracts[] = $row;
    }
} elseif ($role === 'restaurant') {
    $stmt = $db->prepare("
        SELECT c.*,
               u.first_name AS freelancer_first_name,
               u.last_name AS freelancer_last_name,
               f.hourly_rate AS freelancer_hourly_rate
        FROM Contracts c
        JOIN FreelancerProfiles f ON c.freelancer_id = f.profile_id
        JOIN Users u ON f.user_id = u.user_id
        WHERE c.restaurant_id = (
            SELECT restaurant_id FROM RestaurantProfiles WHERE user_id = :user_id
        )
        ORDER BY c.created_at DESC
    ");
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $contracts[] = $row;
    }

    // Lista de freelancers para o dropdown
    $freelancers = [];
    $freelancerQuery = $db->prepare("
        SELECT f.profile_id, u.first_name, u.last_name, f.hourly_rate, f.experience_years, f.avg_rating
        FROM FreelancerProfiles f
        JOIN Users u ON f.user_id = u.user_id
        JOIN UserRoles ur ON u.user_id = ur.user_id
        JOIN Roles r ON ur.role_id = r.role_id
        WHERE r.role_name = 'freelancer'
        ORDER BY u.first_name, u.last_name
    ");
    $result = $freelancerQuery->execute();
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $freelancers[] = $row;
    }
}

$create_mode = isset($_GET['create']) && $_GET['create'] === 'true';
?>

<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <title>Contratos - OlgaRJ</title>
    <link rel="stylesheet" href="../../Css/contracts.css">
    <!-- Adicionando Font Awesome para ícones -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Adicionando Moment.js para formatação de datas -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/locale/pt.js"></script>
    
    <style>
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }
        .alert-success {
            color: #3c763d;
            background-color: #dff0d8;
            border-color: #d6e9c6;
        }
        .alert-error {
            color: #a94442;
            background-color: #f2dede;
            border-color: #ebccd1;
        }
        .freelancer-info {
            font-size: 0.9em;
            color: #666;
        }
        .rating-stars {
            color: #ffc107;
        }
    </style>
    <link rel="stylesheet" href="../Css/contracts.css">
</head>
<body>
    <div class="contracts-container">
        <div class="contracts-header">
            <h2>Meus Contratos</h2>
            <?php if ($role === 'restaurant' && !$create_mode): ?>
                <a href="?create=true" class="btn btn-primary">Criar Novo Contrato</a>
            <?php endif; ?>
        </div>
        
        <?php
        // Mostrar mensagens de sucesso ou erro
        if (isset($_SESSION['success_message'])) {
            echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
            unset($_SESSION['success_message']);
        }
        if (isset($_SESSION['error_message'])) {
            echo '<div class="alert alert-error">' . htmlspecialchars($_SESSION['error_message']) . '</div>';
            unset($_SESSION['error_message']);
        }
        ?>
        
        <?php if ($create_mode && $role === 'restaurant'): ?>
            <form method="POST" action="contracts.php" class="contract-form">
                <h3>Criar Novo Contrato</h3>              
                <div class="form-group">
                    <label for="freelancer_id">Freelancer: <span style="color: red;">*</span></label>
                    <select id="freelancer_id" name="freelancer_id" required>
                        <option value="">Selecione um freelancer</option>
                        <?php foreach ($freelancers as $freelancer): ?>
                            <option value="<?= htmlspecialchars($freelancer['profile_id']) ?>" 
                                    data-rate="<?= htmlspecialchars($freelancer['hourly_rate'] ?? 0) ?>">
                                <?= htmlspecialchars($freelancer['first_name'] . ' ' . $freelancer['last_name']) ?> 
                                <span class="freelancer-info">
                                    (€<?= number_format($freelancer['hourly_rate'] ?? 0, 2) ?>/hora, 
                                    <?= htmlspecialchars($freelancer['experience_years'] ?? 0) ?> anos exp.
                                    <?php if ($freelancer['avg_rating']): ?>
                                        , <span class="rating-stars">★</span><?= number_format($freelancer['avg_rating'], 1) ?>
                                    <?php endif; ?>)
                                </span>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="title">Título do Trabalho: <span style="color: red;">*</span></label>
                    <input type="text" id="title" name="title" required placeholder="Ex: Chef para evento de casamento">
                </div>
                
                <div class="form-group">
                    <label for="description">Descrição do Trabalho: <span style="color: red;">*</span></label>
                    <textarea id="description" name="description" required placeholder="Descreva detalhadamente o trabalho a ser realizado..." rows="4"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="start_date">Data de Início: <span style="color: red;">*</span></label>
                        <input type="datetime-local" id="start_date" name="start_date" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="end_date">Data de Término: <span style="color: red;">*</span></label>
                        <input type="datetime-local" id="end_date" name="end_date" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="hourly_rate">Taxa por Hora (€):</label>
                        <input type="number" step="0.01" id="hourly_rate" name="hourly_rate" min="0" placeholder="0.00">
                        <small>Deixe em branco se não aplicável</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="hours_per_week">Horas por Semana:</label>
                        <input type="number" id="hours_per_week" name="hours_per_week" min="0" placeholder="0">
                        <small>Deixe em branco se não aplicável</small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="agreed_price">Valor Acordado (€): <span style="color: red;">*</span></label>
                        <input type="number" step="0.01" id="agreed_price" name="agreed_price" required min="0.01" placeholder="0.00">
                        <small>Valor total do contrato</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="payment_type">Forma de Pagamento: <span style="color: red;">*</span></label>
                        <select id="payment_type" name="payment_type" required>
                            <option value="">Selecione</option>
                            <option value="cartão">Cartão</option>
                            <option value="dinheiro">Dinheiro</option>
                        </select>
                    </div>
                </div>
                <div id="card-info" style="display: none;">
                    <div class="form-group">
                        <label for="card_number">Número do Cartão: <span style="color: red;">*</span></label>
                        <input type="text" id="card_number" name="card_number" placeholder="XXXX XXXX XXXX XXXX" pattern="\d{13,19}">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="card_expiry">Validade: <span style="color: red;">*</span></label>
                            <input type="text" id="card_expiry" name="card_expiry" placeholder="MM/AA" pattern="\d{2}/\d{2}">
                        </div>
                        
                        <div class="form-group">
                            <label for="card_cvc">CVC: <span style="color: red;">*</span></label>
                            <input type="text" id="card_cvc" name="card_cvc" placeholder="123" pattern="\d{3,4}">
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" name="create_contract" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Enviar Contrato
                    </button>
                    <a href="contracts.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                </div>
            </form>
        <?php else: ?>
            <?php if (empty($contracts)): ?>
                <div class="empty-message">
                    <i class="fas fa-file-contract" style="font-size: 48px; color: #ccc; margin-bottom: 15px;"></i>
                    <p>Nenhum contrato encontrado.</p>
                    <?php if ($role === 'restaurant'): ?>
                        <p><a href="?create=true" class="btn btn-primary">Criar seu primeiro contrato</a></p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="contracts-list">
                    <?php foreach ($contracts as $contract): 
                        $start_date = new DateTime($contract['start_date']);
                        $end_date = new DateTime($contract['end_date']);
                        $now = new DateTime();
                        $is_active = $contract['status'] === 'ativo';
                        $time_remaining = '';
                        
                        if ($is_active && $end_date > $now) {
                            $interval = $now->diff($end_date);
                            $time_remaining = $interval->format('%d dias, %h horas e %i minutos');
                        }
                    ?>
                        <div class="contract-card status-<?= htmlspecialchars(strtolower($contract['status'])) ?>">
                            <div class="contract-header">
                                <h3><?= htmlspecialchars($contract['title']) ?></h3>
                                <span class="contract-status-badge">
                                    <?php 
                                        $icon = '';
                                        switch(strtolower($contract['status'])) {
                                            case 'ativo': $icon = '<i class="fas fa-play-circle"></i>'; break;
                                            case 'pendente': $icon = '<i class="fas fa-clock"></i>'; break;
                                            case 'concluído': $icon = '<i class="fas fa-check-circle"></i>'; break;
                                            case 'aceite': $icon = '<i class="fas fa-thumbs-up"></i>'; break;
                                            case 'recusado': $icon = '<i class="fas fa-times-circle"></i>'; break;
                                            default: $icon = '<i class="fas fa-file-contract"></i>';
                                        }
                                        echo $icon . ' ' . ucfirst(htmlspecialchars($contract['status']));
                                    ?>
                                </span>
                            </div>
                            
                            <div class="contract-details">
                                <div class="detail-row">
                                    <span class="detail-label">Descrição:</span>
                                    <span class="detail-value"><?= htmlspecialchars($contract['description']) ?></span>
                                </div>
                                
                                <?php if ($role === 'freelancer'): ?>
                                    <div class="detail-row">
                                        <span class="detail-label">Restaurante:</span>
                                        <span class="detail-value">
                                            <?= htmlspecialchars($contract['restaurant_name']) ?>
                                            <span class="contact-info">(<?= htmlspecialchars($contract['manager_first_name'] . ' ' . $contract['manager_last_name']) ?>)</span>
                                        </span>
                                    </div>
                                <?php else: ?>
                                    <div class="detail-row">
                                        <span class="detail-label">Freelancer:</span>
                                        <span class="detail-value">
                                            <?= htmlspecialchars($contract['freelancer_first_name'] . ' ' . $contract['freelancer_last_name']) ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="detail-row">
                                    <span class="detail-label">Período:</span>
                                    <span class="detail-value">
                                        <?= $start_date->format('d/m/Y H:i') ?> 
                                        até 
                                        <?= $end_date->format('d/m/Y H:i') ?>
                                    </span>
                                </div>
                                
                                <?php if (!empty($contract['hourly_rate'])): ?>
                                <div class="detail-row">
                                    <span class="detail-label">Taxa por Hora:</span>
                                    <span class="detail-value">€<?= number_format($contract['hourly_rate'], 2) ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($contract['hours_per_week'])): ?>
                                <div class="detail-row">
                                    <span class="detail-label">Horas por Semana:</span>
                                    <span class="detail-value"><?= htmlspecialchars($contract['hours_per_week']) ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($contract['agreed_price'])): ?>
                                <div class="detail-row">
                                    <span class="detail-label">Valor Acordado:</span>
                                    <span class="detail-value">€<?= number_format($contract['agreed_price'], 2) ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <div class="detail-row">
                                    <span class="detail-label">Data de Criação:</span>
                                    <span class="detail-value"><?= date('d/m/Y H:i', strtotime($contract['created_at'])) ?></span>
                                </div>
                                
                                <?php if ($is_active && !empty($time_remaining)): ?>
                                <div class="detail-row">
                                    <span class="detail-label">Tempo Restante:</span>
                                    <span class="detail-value time-remaining" data-end="<?= $end_date->format('Y-m-d H:i:s') ?>">
                                        <i class="fas fa-hourglass-half"></i> <?= $time_remaining ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($contract['response_date']) && $contract['response_date'] != '1970-01-01 00:00:00'): ?>
                                <div class="detail-row">
                                    <span class="detail-label">Data de Resposta:</span>
                                    <span class="detail-value"><?= date('d/m/Y H:i', strtotime($contract['response_date'])) ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($contract['status'] === 'pendente' && $role === 'freelancer'): ?>
                                <div class="contract-actions">
                                    <form method="POST" action="contracts.php" class="inline-form">
                                        <input type="hidden" name="contract_id" value="<?= htmlspecialchars($contract['contract_id']) ?>">
                                        <button type="submit" name="action" value="accept" class="btn btn-success" 
                                                onclick="return confirm('Tem certeza que deseja aceitar este contrato?')">
                                            <i class="fas fa-check"></i> Aceitar
                                        </button>
                                    </form>
                                    <form method="POST" action="contracts.php" class="inline-form">
                                        <input type="hidden" name="contract_id" value="<?= $contract['contract_id'] ?>">
                                        <button type="submit" name="reject_contract" class="btn btn-danger"
                                                onclick="return confirm('Tem certeza que deseja recusar este contrato?')">
                                            <i class="fas fa-times"></i> Recusar
                                        </button>
                                    </form>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script>
    // Format time with proper Portuguese pluralization
    function formatTime(days, hours, minutes) {
        const parts = [];
        if (days > 0) parts.push(days + (days === 1 ? " dia" : " dias"));
        if (hours > 0) parts.push(hours + (hours === 1 ? " hora" : " horas"));
        if (minutes > 0 || parts.length === 0) {
            parts.push(minutes + (minutes === 1 ? " minuto" : " minutos"));
        }
        return parts.join(" e ");
    }

    // Update all countdown timers
    function updateTimers() {
        const now = new Date();
        const timers = document.getElementsByClassName('time-remaining');

        for (let i = 0; i < timers.length; i++) {
            const endTime = new Date(timers[i].getAttribute('data-end'));
            const diff = endTime - now;

            if (diff > 0) {
                const days = Math.floor(diff / (1000 * 60 * 60 * 24));
                const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                timers[i].innerHTML = '⏳ ' + formatTime(days, hours, minutes);
            } else {
                timers[i].innerHTML = '⌛ Contrato expirado';
            }
        }
    }

    // Initial update and interval
    updateTimers();
    setInterval(updateTimers, 60000); // Update every minute

    document.addEventListener('DOMContentLoaded', function () {
        const hourlyRate = document.getElementById('hourly_rate');
        const hoursPerWeek = document.getElementById('hours_per_week');
        const startDate = document.getElementById('start_date');
        const endDate = document.getElementById('end_date');
        const agreedPrice = document.getElementById('agreed_price');
        const paymentType = document.getElementById('payment_type');
        const cardInfo = document.getElementById('card-info');

        // Set default dates
        const today = new Date();
        startDate.value = today.toISOString().slice(0, 16);
        endDate.value = new Date(today.setDate(today.getDate() + 7)).toISOString().slice(0, 16);

        function calculateContractTotal() {
            if (hourlyRate.value && hoursPerWeek.value && startDate.value && endDate.value) {
                const rate = parseFloat(hourlyRate.value);
                const hours = parseFloat(hoursPerWeek.value);
                const start = new Date(startDate.value);
                const end = new Date(endDate.value);
                const weeks = Math.ceil((end - start) / (1000 * 60 * 60 * 24 * 7));
                const total = rate * hours * weeks;
                agreedPrice.value = total.toFixed(2);
            }
        }

        // Event listeners for total calculation
        [hourlyRate, hoursPerWeek, startDate, endDate].forEach(el => {
            el.addEventListener('change', calculateContractTotal);
            el.addEventListener('input', calculateContractTotal);
        });

        calculateContractTotal(); // Initial calculation

        // Show/hide card fields based on payment method
        paymentType.addEventListener('change', function () {
            if (paymentType.value === 'cartão') {
                cardInfo.style.display = 'block';
                document.getElementById('card_number').required = true;
                document.getElementById('card_expiry').required = true;
                document.getElementById('card_cvc').required = true;
            } else {
                cardInfo.style.display = 'none';
                document.getElementById('card_number').required = false;
                document.getElementById('card_expiry').required = false;
                document.getElementById('card_cvc').required = false;
            }
        });

        // Trigger change in case value is already set (e.g., on form validation error)
        paymentType.dispatchEvent(new Event('change'));
    });
    </script>

</body>
</html>