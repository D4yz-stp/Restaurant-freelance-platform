document.addEventListener('DOMContentLoaded', function() {
    // Inicializar AOS
    AOS.init({
        duration: 1000,
        once: true,
        offset: 120,
        easing: 'ease-out'
    });
    
    // Header scroll effect
    const header = document.querySelector('.main-header');
    window.addEventListener('scroll', function() {
        if (window.scrollY > 10) {
            header.classList.add('scrolled');
        } else {
            header.classList.remove('scrolled');
        }
    });
    
    // Mobile menu toggle
/*    const menuToggle = document.querySelector('.mobile-menu-toggle');
    if (menuToggle) {
        menuToggle.addEventListener('click', function() {
            const expanded = this.getAttribute('aria-expanded') === 'true';
            this.setAttribute('aria-expanded', !expanded);
            
            // Adicionar código para mostrar/esconder menu móvel
            // Implementar quando criar o menu móvel completo
        });
    }
*/
    
    // Smooth scroll para links internos
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                window.scrollTo({
                    top: target.offsetTop - 80,
                    behavior: 'smooth'
                });
            }
        });
    });
    
    // Form submission (newsletter) -- pd ser deletado sinceramente
    const newsletterForm = document.querySelector('.newsletter-form');
    if (newsletterForm) {
        newsletterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const emailInput = this.querySelector('input[type="email"]');
            if (emailInput && emailInput.value) {
                // Simular envio do formulário
                const button = this.querySelector('button');
                const originalText = button.textContent;
                
                button.disabled = true;
                button.textContent = 'Enviando...';
                
                setTimeout(() => {
                    emailInput.value = '';
                    button.textContent = 'Enviado!';
                    
                    setTimeout(() => {
                        button.disabled = false;
                        button.textContent = originalText;
                    }, 2000);
                }, 1000);
            }
        });
    }
});