<?php
// Definição de constantes e configurações globais

// Configuração do banco de dados
define('DB_PATH', '../../../database/TesteOlga.db');

// Configuração de paginação
define('ITEMS_PER_PAGE', 9);

// Configuração de caminhos
define('BASE_URL', '/main_service'); // Ajuste conforme necessário
define('ROOT_PATH', $_SERVER['DOCUMENT_ROOT'] . BASE_URL);

// Mapeamento de disponibilidade
define('AVAILABILITY_MAP', [
    'morning' => 'Manhãs',
    'afternoon' => 'Tardes',
    'evening' => 'Noites',
    'weekend' => 'Fins de semana',
    'flexible' => 'Flexível'
]);

// Rótulos de ordenação
define('SORT_LABELS', [
    'relevance' => 'Relevância',
    'rating' => 'Avaliações',
    'price-asc' => 'Preço (menor-maior)',
    'price-desc' => 'Preço (maior-menor)',
    'experience' => 'Experiência'
]);
