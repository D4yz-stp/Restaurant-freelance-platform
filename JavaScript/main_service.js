
const experienceSlider = document.getElementById('experience-slider');
const experienceValue = document.getElementById('experience-value');

experienceSlider.addEventListener('input', function() {
    experienceValue.textContent = this.value + ' anos';
});

const sortButton = document.querySelector('.sort-button');
const sortDropdown = document.querySelector('.sort-dropdown');

sortButton.addEventListener('click', function() {
    sortDropdown.classList.toggle('open');
});
document.addEventListener('click', function(event) {
    if (!sortDropdown.contains(event.target)) {
        sortDropdown.classList.remove('open');
    }
});

const sortOptions = document.querySelectorAll('.sort-option');
const sortText = document.querySelector('.sort-button strong');

sortOptions.forEach(option => {
    option.addEventListener('click', function() {
        const sortValue = this.getAttribute('data-sort');
        const sortName = this.textContent;
        
        sortText.textContent = sortName;
        sortDropdown.classList.remove('open');

        console.log('Ordenando por: ' + sortValue);
        
        const servicesGrid = document.querySelector('.services-grid');
        const serviceCards = Array.from(servicesGrid.querySelectorAll('.service-card'));
        
        if (sortValue === 'price-asc') {
            serviceCards.sort((a, b) => {
                const priceA = parseFloat(a.querySelector('.service-price').textContent.replace('R$ ', ''));
                const priceB = parseFloat(b.querySelector('.service-price').textContent.replace('R$ ', ''));
                return priceA - priceB;
            });
        } else if (sortValue === 'price-desc') {
            serviceCards.sort((a, b) => {
                const priceA = parseFloat(a.querySelector('.service-price').textContent.replace('R$ ', ''));
                const priceB = parseFloat(b.querySelector('.service-price').textContent.replace('R$ ', ''));
                return priceB - priceA;
            });
        } else if (sortValue === 'rating') {
            serviceCards.sort((a, b) => {
                const ratingA = parseFloat(a.querySelector('.service-rating').textContent.trim().split(' ')[0]);
                const ratingB = parseFloat(b.querySelector('.service-rating').textContent.trim().split(' ')[0]);
                return ratingB - ratingA;
            });
        } else if (sortValue === 'experience') {
            serviceCards.sort((a, b) => {
                const expA = parseInt(a.querySelector('.meta-item').textContent.replace(' anos exp.', ''));
                const expB = parseInt(b.querySelector('.meta-item').textContent.replace(' anos exp.', ''));
                return expB - expA;
            });
        }
        
        servicesGrid.innerHTML = '';
        
        serviceCards.forEach(card => {
            servicesGrid.appendChild(card);
        });
    });
});

const filterBtn = document.querySelector('.filter-btn');

filterBtn.addEventListener('click', function() {
    const experienceValue = document.getElementById('experience-slider').value;
    const priceMin = document.getElementById('price-min').value;
    const priceMax = document.getElementById('price-max').value;
    
    const selectedTimes = Array.from(document.querySelectorAll('input[name="time"]:checked'))
        .map(checkbox => checkbox.value);
    
    const filterData = {
        experience: experienceValue,
        priceMin: priceMin,
        priceMax: priceMax,
        times: selectedTimes
    };
    
    console.log('Aplicando filtros:', filterData);
    
    const servicesGrid = document.querySelector('.services-grid');
    const serviceCards = Array.from(servicesGrid.querySelectorAll('.service-card'));
    
    serviceCards.forEach(card => {
        const cardExp = parseInt(card.querySelector('.meta-item').textContent.replace(' anos exp.', ''));
        const cardPrice = parseFloat(card.querySelector('.service-price').textContent.replace('R$ ', ''));
        const cardTimes = card.querySelector('.meta-item:nth-child(2)').textContent;
        
        let showCard = true;
        
        if (experienceValue > 0 && cardExp < experienceValue) {
            showCard = false;
        }
        
        if (priceMin && cardPrice < priceMin) {
            showCard = false;
        }
        
        if (priceMax && cardPrice > priceMax) {
            showCard = false;
        }
        
        if (selectedTimes.length > 0) {
            const hasMatchingTime = selectedTimes.some(time => {
                const timeMap = {
                    'morning': 'Manhãs',
                    'afternoon': 'Tardes',
                    'evening': 'Noites',
                    'weekend': 'Fins de semana',
                    'flexible': 'Flexível'
                };
                
                return cardTimes.includes(timeMap[time]);
            });
            
            if (!hasMatchingTime) {
                showCard = false;
            }
        }
        
        card.style.display = showCard ? 'flex' : 'none';
    });
    
    const visibleCards = serviceCards.filter(card => card.style.display !== 'none');
    document.querySelector('.found-count strong').textContent = visibleCards.length;
    
    const noResultsEl = document.querySelector('.no-results');
    if (visibleCards.length === 0) {
        if (!noResultsEl) {
            const noResults = document.createElement('div');
            noResults.className = 'no-results';
            noResults.innerHTML = `
                <h2>Nenhum serviço encontrado</h2>
                <p>Tente ajustar seus filtros ou realizar uma nova busca com termos diferentes.</p>
            `;
            servicesGrid.after(noResults);
        }
    } else if (noResultsEl) {
        noResultsEl.remove();
    }
});

// Funcionalidade de pesquisa
const searchBtn = document.querySelector('.search-btn');
const searchInput = document.querySelector('.search-input');

searchBtn.addEventListener('click', function() {
    const searchTerm = searchInput.value.trim().toLowerCase();
    
    if (!searchTerm) return;
    
    console.log('Pesquisando por:', searchTerm);
    
    // Simulação de pesquisa (seria substituído pelo PHP/backend)
    const servicesGrid = document.querySelector('.services-grid');
    const serviceCards = Array.from(servicesGrid.querySelectorAll('.service-card'));
    
    serviceCards.forEach(card => {
        const title = card.querySelector('.service-title').textContent.toLowerCase();
        const description = card.querySelector('.service-description').textContent.toLowerCase();
        
        if (title.includes(searchTerm) || description.includes(searchTerm)) {
            card.style.display = 'flex';
        } else {
            card.style.display = 'none';
        }
    });
    
    // Atualizar contagem de resultados
    const visibleCards = serviceCards.filter(card => card.style.display !== 'none');
    document.querySelector('.found-count strong').textContent = visibleCards.length;
    
    // Mostrar mensagem de nenhum resultado se necessário
    const noResultsEl = document.querySelector('.no-results');
    if (visibleCards.length === 0) {
        if (!noResultsEl) {
            const noResults = document.createElement('div');
            noResults.className = 'no-results';
            noResults.innerHTML = `
                <h2>Nenhum serviço encontrado</h2>
                <p>Tente ajustar sua pesquisa ou procurar com palavras-chave diferentes.</p>
            `;
            servicesGrid.after(noResults);
        }
    } else if (noResultsEl) {
        noResultsEl.remove();
    }
});

// Permitir pesquisa ao pressionar Enter
searchInput.addEventListener('keyup', function(event) {
    if (event.key === 'Enter') {
        searchBtn.click();
    }
});

// Funcionalidade para paginação
const pageLinks = document.querySelectorAll('.page-link');

pageLinks.forEach(link => {
    link.addEventListener('click', function(e) {
        e.preventDefault();
        
        // Remover classe ativa de todos os links
        pageLinks.forEach(pl => pl.classList.remove('active'));
        
        // Adicionar classe ativa apenas ao link clicado (exceto prev/next)
        if (!this.classList.contains('prev') && !this.classList.contains('next')) {
            this.classList.add('active');
        }
        
        // Aqui você adicionaria o código para carregar os dados da página correspondente
        // via AJAX ou redirecionar para a URL da página
        console.log('Navegando para página:', this.textContent);
        
        // Simular carregamento de página (apenas para demonstração)
        window.scrollTo({
            top: document.querySelector('.services-hero').offsetTop,
            behavior: 'smooth'
        });
    });
});



/*      vffffffffffffffffffffffffffffffffffffffffffff */



document.addEventListener('DOMContentLoaded', function() {
    // Inicializar AOS
    
    // Atualizar valor do slider de experiência
    const expSlider = document.getElementById('experience-slider');
    const expValue = document.getElementById('experience-value');
    
    if (expSlider && expValue) {
        expSlider.addEventListener('input', function() {
            expValue.textContent = this.value + ' anos';
        });
    }
    
    // Dropdown de ordenação
    const sortButton = document.querySelector('.sort-button');
    const sortDropdown = document.querySelector('.sort-dropdown-content');
    
    if (sortButton && sortDropdown) {
        sortButton.addEventListener('click', function() {
            sortDropdown.classList.toggle('show');
        });
        
        // Fechar dropdown ao clicar fora
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.sort-dropdown')) {
                sortDropdown.classList.remove('show');
            }
        });
    }
});