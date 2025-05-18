document.addEventListener('DOMContentLoaded', function() {
    // Inicialização da biblioteca AOS para animações
    
    // Gerenciamento do slider de experiência
    const experienceSlider = document.getElementById('experience-slider');
    const experienceValue = document.getElementById('experience-value');
    
    if (experienceSlider && experienceValue) {
        experienceSlider.addEventListener('input', function() {
            experienceValue.textContent = this.value + ' anos';
        });
    }
    
    // Funcionalidade de dropdown de ordenação
    const sortButton = document.querySelector('.sort-button');
    const sortDropdown = document.querySelector('.sort-dropdown-content');
    
    if (sortButton && sortDropdown) {
        sortButton.addEventListener('click', function() {
            sortDropdown.classList.toggle('show');
        });
        
        // Fechar o dropdown quando clicar fora dele
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.sort-dropdown')) {
                sortDropdown.classList.remove('show');
            }
        });
    }
    
    // Validação do formulário de filtro
    const filterForm = document.getElementById('filter-form');
    if (filterForm) {
        filterForm.addEventListener('submit', function(event) {
            const priceMin = document.getElementById('price-min');
            const priceMax = document.getElementById('price-max');
            
            // Verificar se min é maior que max
            if (priceMin && priceMax && 
                priceMin.value !== '' && priceMax.value !== '' && 
                parseFloat(priceMin.value) > parseFloat(priceMax.value)) {
                
                alert('O valor mínimo não pode ser maior que o valor máximo.');
                event.preventDefault();
            }
        });
    }
});