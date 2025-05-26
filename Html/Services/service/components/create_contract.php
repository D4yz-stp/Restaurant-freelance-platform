<?php
session_start();

// Use absolute path for reliability
$db_path = __DIR__ . '/../../../../database/TesteOlga.db';

// Debug output (remove after testing)
if (!file_exists($db_path)) {
    die("<div class='alert alert-danger'>Database not found at: " . htmlspecialchars($db_path) . "</div>");
}

try {
    $db = new SQLite3($db_path);
    $db->exec('PRAGMA foreign_keys = ON;');
} catch (Exception $e) {
    die("<div class='alert alert-danger'>Database error: " . htmlspecialchars($e->getMessage()) . "</div>");
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /Html/Log/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['user_role'] ?? '';

// Get parameters
$create_mode = isset($_GET['create']) && $_GET['create'] === 'true' && $role === 'restaurant';
$service_id = $_GET['service_id'] ?? 0;
$freelancer_id = $_GET['freelancer_id'] ?? 0;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_contract'])) {
    // [Your existing form processing code here]
    // ... keep all your existing POST handling logic ...
}

// If in create mode, show the form instead of processing
if ($create_mode && $service_id && $freelancer_id):
    // Fetch service details to pre-fill the form
    $stmt = $db->prepare("SELECT * FROM Services WHERE service_id = :service_id");
    $stmt->bindValue(':service_id', $service_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $service = $result->fetchArray(SQLITE3_ASSOC);
    
    // Fetch freelancer details
    $stmt = $db->prepare("SELECT * FROM Users WHERE user_id = :freelancer_id");
    $stmt->bindValue(':freelancer_id', $freelancer_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $freelancer = $result->fetchArray(SQLITE3_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <title>Criar Novo Contrato</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/Css/contracts.css">
</head>
<body>
    <div class="contracts-container">
        <div class="contracts-header">
            <h2>Criar Novo Contrato</h2>
        </div>
        
        <form method="POST" action="create_contract.php" class="contract-form">
            <input type="hidden" name="service_id" value="<?= htmlspecialchars($service_id) ?>">
            <input type="hidden" name="freelancer_id" value="<?= htmlspecialchars($freelancer_id) ?>">
            
            <div class="form-row">
                <div class="form-group col-md-8">
                    <label for="contract_title">Título do Contrato</label>
                    <input type="text" class="form-control" id="contract_title" name="title" required 
                           value="<?= htmlspecialchars($service['service_title'] ?? '') ?>">
                </div>
                <div class="form-group col-md-4">
                    <label for="contract_status">Estado Inicial</label>
                    <select class="form-control" id="contract_status" name="status" required>
                        <option value="pendente" selected>Pendente</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label for="contract_description">Descrição</label>
                <textarea class="form-control" id="contract_description" name="description" rows="4" required><?= htmlspecialchars($service['description'] ?? '') ?></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="start_date">Data de Início</label>
                    <input type="datetime-local" class="form-control" id="start_date" name="start_date" required>
                </div>
                <div class="form-group col-md-6">
                    <label for="end_date">Data de Término</label>
                    <input type="datetime-local" class="form-control" id="end_date" name="end_date" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="hourly_rate">Taxa por Hora (€)</label>
                    <input type="number" step="0.01" class="form-control" id="hourly_rate" name="hourly_rate" 
                           value="<?= htmlspecialchars($service['base_price'] ?? '') ?>" <?= ($service['price_type'] == 'hourly') ? 'required' : '' ?>>
                </div>
                <div class="form-group col-md-4">
                    <label for="hours_per_week">Horas por Semana</label>
                    <input type="number" class="form-control" id="hours_per_week" name="hours_per_week" min="1">
                </div>
                <div class="form-group col-md-4">
                    <label for="agreed_price">Valor Total (€)</label>
                    <input type="number" step="0.01" class="form-control" id="agreed_price" name="agreed_price" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="payment_terms">Termos de Pagamento</label>
                <select class="form-control" id="payment_terms" name="payment_terms" required>
                    <option value="50% adiantado, 50% na conclusão">50% adiantado, 50% na conclusão</option>
                    <option value="100% na conclusão">100% na conclusão</option>
                    <option value="Pagamento semanal">Pagamento semanal</option>
                    <option value="Pagamento mensal">Pagamento mensal</option>
                </select>
            </div>
            
            <div class="form-group">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="terms_agreement" name="terms_agreement" required>
                    <label class="form-check-label" for="terms_agreement">
                        Concordo com os termos e condições do contrato
                    </label>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" name="create_contract" class="btn btn-primary">
                    <i class="fas fa-file-signature"></i> Criar Contrato
                </button>
                <a href="/service.php?id=<?= htmlspecialchars($service_id) ?>" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>

    <script>
    // Calculate contract duration and total price
    document.addEventListener('DOMContentLoaded', function() {
        const hourlyRate = document.getElementById('hourly_rate');
        const hoursPerWeek = document.getElementById('hours_per_week');
        const startDate = document.getElementById('start_date');
        const endDate = document.getElementById('end_date');
        const agreedPrice = document.getElementById('agreed_price');
        
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
                
                // Calculate weeks (rounded up)
                const weeks = Math.ceil((end - start) / (1000 * 60 * 60 * 24 * 7));
                
                // Calculate total price
                const total = rate * hours * weeks;
                agreedPrice.value = total.toFixed(2);
            }
        }
        
        // Add event listeners
        [hourlyRate, hoursPerWeek, startDate, endDate].forEach(el => {
            el.addEventListener('change', calculateContractTotal);
            el.addEventListener('input', calculateContractTotal);
        });
        
        // Initial calculation
        calculateContractTotal();
    });
    </script>
</body>
</html>
<?php
else:
    // If not in create mode, redirect or show error
    header('Location: /service.php?id=' . htmlspecialchars($service_id));
    exit;
endif;
?>