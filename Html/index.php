<?php
session_start(); // Importante: isso deve ser a primeira coisa antes de qualquer saída HTML
include 'header.php';
?>

<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="OlgaRJ - Plataforma de perfis profissionais para restauração">
    <title>OlgaRJ | Perfis Profissionais para Restauração</title>
    
    <link rel="stylesheet" href="https://unpkg.com/aos@2.3.1/dist/aos.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="../Css/index.css">
</head>
<body>
    <?php include __DIR__ . '/Services/components/header.php'; ?>
    
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <?php
                echo $_SESSION['success'];
                unset($_SESSION['success']); // Limpa a mensagem após exibição
            ?>
        </div>
    <?php endif; ?>

    <main>
        <section class="hero-section" id="hero">
            <div class="container">
                <div class="hero-content" data-aos="fade-up">
                    <h2 class="hero-title">Conectamos talentos à restauração</h2>
                    <p class="hero-subtitle">A plataforma profissional para o setor de restauração</p>
                    <div class="hero-cta">
                        <a href="#features" class="btn btn-primary">Descobrir mais</a>
                        <a href="/Html/Log/register.html" class="btn btn-outline">Criar perfil</a>
                    </div>
                </div>
            </div>
        </section>
        <section id="features" class="features-section">
            <div class="container">
                <div class="section-header" data-aos="fade-up">
                    <h2>Nossas soluções</h2>
                    <p>O que oferecemos para transformar sua carreira</p>
                </div>
                <div class="features-grid">
                    <div class="feature-card shard1" data-aos="fade-up">
                        <div class="feature-content">
                            <h3>Perfis Profissionais</h3>
                            <p>Crie seu perfil profissional personalizado e destaque suas habilidades no setor de restauração</p>
                        </div>
                    </div>

                    <div class="feature-card shard2" data-aos="fade-up" data-aos-delay="100">
                        <div class="feature-content">
                            <h3>Por que somos os melhores?</h3>
                            <p>Plataforma moderna com ferramentas exclusivas para profissionais se conectarem com oportunidades ideais</p>
                        </div>
                    </div>

                    <div class="feature-card shard3" data-aos="fade-up" data-aos-delay="200">
                        <div class="feature-content">
                            <h3>Gestão Online</h3>
                            <p>Controle total de contratos, horários e informações profissionais num só lugar</p>
                        </div>
                    </div>

                    <div class="feature-card shard1" data-aos="fade-up">
                        <div class="feature-content">
                            <h3>Site Nacional</h3>
                            <p>Disfrute de um Site Português igual a você</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php include 'Services/components/footer.php'; ?>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="../JavaScript/main.js"></script>
</body>
</html>