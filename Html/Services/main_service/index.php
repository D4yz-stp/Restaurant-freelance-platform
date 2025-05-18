<?php
/**
 * Página principal de serviços
 * 
 * Este arquivo organiza a estrutura da página e inclui os componentes necessários
 */
session_start();

// Incluir arquivos necessários
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/utils.php';
require_once 'includes/services-repository.php';
include_once 'components/header.php';
// Obter parâmetros de filtro
$filterParams = getFilterParams();

// Inicializar conexão com o banco de dados
$db = Database::getInstance()->getConnection();

// Inicializar repositório de serviços
$servicesRepository = new ServicesRepository($db);

// Obter total de serviços com os filtros aplicados
$totalCount = $servicesRepository->countServices($filterParams);

// Calcular total de páginas
$totalPages = ceil($totalCount / ITEMS_PER_PAGE);

// Obter serviços para a página atual
$services = $servicesRepository->getServices($filterParams);

// Extrair variáveis para uso mais simples nos templates
extract($filterParams);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Serviços</title>
    <link rel="stylesheet" href="https://unpkg.com/aos@2.3.1/dist/aos.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <link rel="stylesheet" href="../../../Css/header+button.css">
    <link rel="stylesheet" href="../../../Css/global.css">
    <link rel="stylesheet" href="../../../Css/footer.css">
    <link rel="stylesheet" href="../../../Css/service.css">
</head>
<body>
    <?php include '../components/header.php'; ?>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <?php 
                echo $_SESSION['success']; 
                unset($_SESSION['success']); // Limpa a mensagem após exibição
            ?>
        </div>
    <?php endif; ?>

    <main>
        <section class="services-hero">
            <div class="container">
                <h1>Descubra os melhores serviços</h1>
                <p>Encontre profissionais qualificados para realizar o trabalho que você precisa</p>
                <form action="" method="GET" class="search-form">
                    <div class="search-container">
                        <input type="text" name="search" class="search-input" 
                               placeholder="Procure por serviços, palavras-chave ou habilidades..." 
                               value="<?php echo safeHtml($search); ?>">
                        <button type="submit" class="search-btn">Buscar</button>
                    </div>
                </form>
            </div>
        </section>
        
        <div class="container">
            <div class="main-content">
                <?php include 'components/filters.php'; ?>
                
                <div class="services-container">
                    <?php include 'components/service-header.php'; ?>
                    
                    <div class="services-grid">
                        <?php 
                        if (count($services) > 0):
                            $delay = 0;
                            foreach ($services as $service):
                                $delay += 100;
                                include 'components/service-card.php';
                            endforeach;
                        else:
                            include 'components/no-results.php';
                        endif; 
                        ?>
                    </div>
                    
                    <?php include 'components/pagination.php'; ?>
                </div>
            </div>
        </div>
    </main>
    
    <?php include '../components/footer.php'; ?>
    
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="../../../JavaScript/main.js"></script>
    <script src="assets/js/services.js"></script>
</body>
</html>