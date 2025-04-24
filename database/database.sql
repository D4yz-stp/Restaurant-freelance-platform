-- Usuários e Autenticação
CREATE TABLE Users (
    user_id INTEGER PRIMARY KEY AUTOINCREMENT,
    first_name VARCHAR(30) NOT NULL,
    last_name VARCHAR(30) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    contact VARCHAR(20) NOT NULL,
    country VARCHAR(40),
    city VARCHAR(50),
    address TEXT,
    gender CHAR(1) CHECK(gender IN ('M', 'F', 'O')), -- M = Masculino, F = Feminino, O = Outro
    birth_date DATE,
    profile_picture_url VARCHAR(255),
    bio TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP
);

CREATE TABLE Roles (
    role_id INTEGER PRIMARY KEY AUTOINCREMENT,
    role_name VARCHAR(20) UNIQUE NOT NULL CHECK(role_name IN ('freelancer', 'restaurant', 'admin'))
);

CREATE TABLE UserRoles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    role_id INTEGER NOT NULL,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES Roles(role_id) ON DELETE CASCADE,
    UNIQUE(user_id, role_id) -- impede entradas duplicadas para o mesmo usuário
);

-- Perfis dos Funcionários (Freelancers)
CREATE TABLE FreelancerProfiles (
    profile_id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER UNIQUE NOT NULL,
    hourly_rate DECIMAL(10, 2),
    availability ENUM('full-time', 'part-time', 'weekends', 'evenings', 'flexible') DEFAULT 'flexible',
    experience_years INTEGER,
    avg_rating DECIMAL(3, 2) DEFAULT 0,
    total_reviews INTEGER DEFAULT 0,
    total_completed_jobs INTEGER DEFAULT 0,
    is_featured BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE
);

-- Perfis dos Restaurantes (Contratantes)
CREATE TABLE RestaurantProfiles (
    restaurant_id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER UNIQUE NOT NULL,
    restaurant_name VARCHAR(100) NOT NULL,
    restaurant_type VARCHAR(50), -- ex: italiano, japonês, etc.
    establishment_year INTEGER,
    website VARCHAR(255),
    description TEXT,
    logo_url VARCHAR(255),
    avg_rating DECIMAL(3, 2) DEFAULT 0,
    total_reviews INTEGER DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE
);

-- Endereços dos Restaurantes
CREATE TABLE RestaurantLocations (
    location_id INTEGER PRIMARY KEY AUTOINCREMENT,
    restaurant_id INTEGER NOT NULL,
    address TEXT NOT NULL,
    city VARCHAR(50) NOT NULL,
    state VARCHAR(50),
    postal_code VARCHAR(20),
    country VARCHAR(40) NOT NULL,
    phone VARCHAR(20),
    is_main_location BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (restaurant_id) REFERENCES RestaurantProfiles(restaurant_id) ON DELETE CASCADE
);

-- Categorias de Funções no Restaurante
CREATE TABLE ServiceCategories (
    category_id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    icon_url VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE
);

-- Habilidades e Especializações
CREATE TABLE SkillCategories (
    skill_category_id INTEGER PRIMARY KEY AUTOINCREMENT,
    category_name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT
);

CREATE TABLE Skills (
    skill_id INTEGER PRIMARY KEY AUTOINCREMENT,
    skill_category_id INTEGER NOT NULL,
    skill_name VARCHAR(50) NOT NULL,
    description TEXT,
    FOREIGN KEY (skill_category_id) REFERENCES SkillCategories(skill_category_id) ON DELETE CASCADE,
    UNIQUE(skill_category_id, skill_name)
);

CREATE TABLE FreelancerSkills (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    freelancer_id INTEGER NOT NULL,
    skill_id INTEGER NOT NULL,
    proficiency_level VARCHAR(20) CHECK(proficiency_level IN ('iniciante', 'intermediário', 'avançado', 'especialista')),
    years_experience INTEGER,
    details TEXT,
    FOREIGN KEY (freelancer_id) REFERENCES FreelancerProfiles(profile_id) ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES Skills(skill_id) ON DELETE CASCADE,
    UNIQUE(freelancer_id, skill_id)
);

-- Especialização em Cozinha
CREATE TABLE ChefSpecializations (
    chef_id INTEGER PRIMARY KEY AUTOINCREMENT,
    freelancer_id INTEGER NOT NULL,
    cuisine_type VARCHAR(50) NOT NULL,
    certifications TEXT,
    dietary_specialties TEXT, -- ex: vegano, sem glúten, etc.
    menu_planning BOOLEAN DEFAULT FALSE,
    catering_experience BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (freelancer_id) REFERENCES FreelancerProfiles(profile_id) ON DELETE CASCADE
);

-- Especialização em Limpeza
CREATE TABLE CleaningSpecializations (
    cleaning_id INTEGER PRIMARY KEY AUTOINCREMENT,
    freelancer_id INTEGER NOT NULL,
    kitchen_cleaning BOOLEAN DEFAULT FALSE,
    dining_area_cleaning BOOLEAN DEFAULT FALSE,
    equipment_experience TEXT,
    eco_friendly BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (freelancer_id) REFERENCES FreelancerProfiles(profile_id) ON DELETE CASCADE
);

-- Especialização em Bartending
CREATE TABLE BartenderSpecializations (
    bartender_id INTEGER PRIMARY KEY AUTOINCREMENT,
    freelancer_id INTEGER NOT NULL,
    cocktail_specialist BOOLEAN DEFAULT FALSE,
    wine_knowledge BOOLEAN DEFAULT FALSE,
    beer_knowledge BOOLEAN DEFAULT FALSE,
    flair_bartending BOOLEAN DEFAULT FALSE,
    certifications TEXT,
    FOREIGN KEY (freelancer_id) REFERENCES FreelancerProfiles(profile_id) ON DELETE CASCADE
);

-- Especialização em Atendimento
CREATE TABLE ServiceStaffSpecializations (
    service_staff_id INTEGER PRIMARY KEY AUTOINCREMENT,
    freelancer_id INTEGER NOT NULL,
    fine_dining_experience BOOLEAN DEFAULT FALSE,
    event_service BOOLEAN DEFAULT FALSE,
    sommelier_knowledge BOOLEAN DEFAULT FALSE,
    customer_service_rating INTEGER CHECK(customer_service_rating BETWEEN 1 AND 5),
    FOREIGN KEY (freelancer_id) REFERENCES FreelancerProfiles(profile_id) ON DELETE CASCADE
);

-- Idiomas falados pelos funcionários
CREATE TABLE Languages (
    language_id INTEGER PRIMARY KEY AUTOINCREMENT,
    language_name VARCHAR(50) NOT NULL UNIQUE
);

CREATE TABLE FreelancerLanguages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    freelancer_id INTEGER NOT NULL,
    language_id INTEGER NOT NULL,
    proficiency VARCHAR(20) CHECK(proficiency IN ('básico', 'intermediário', 'fluente', 'nativo')),
    FOREIGN KEY (freelancer_id) REFERENCES FreelancerProfiles(profile_id) ON DELETE CASCADE,
    FOREIGN KEY (language_id) REFERENCES Languages(language_id) ON DELETE CASCADE,
    UNIQUE(freelancer_id, language_id)
);

-- Disponibilidade de horários
CREATE TABLE AvailabilitySlots (
    slot_id INTEGER PRIMARY KEY AUTOINCREMENT,
    freelancer_id INTEGER NOT NULL,
    day_of_week INTEGER CHECK(day_of_week BETWEEN 0 AND 6), -- 0 = domingo, 6 = sábado
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    is_available BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (freelancer_id) REFERENCES FreelancerProfiles(profile_id) ON DELETE CASCADE
);

-- Serviços oferecidos pelos funcionários
CREATE TABLE Services (
    service_id INTEGER PRIMARY KEY AUTOINCREMENT,
    freelancer_id INTEGER NOT NULL,
    category_id INTEGER NOT NULL,
    title VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    price_type ENUM('por_hora', 'fixo', 'por_evento') NOT NULL,
    base_price DECIMAL(10, 2) NOT NULL,
    min_hours INTEGER, -- Mínimo de horas se for por hora
    is_featured BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (freelancer_id) REFERENCES FreelancerProfiles(profile_id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES ServiceCategories(category_id) ON DELETE CASCADE
);

-- Mídia dos serviços (imagens, vídeos)
CREATE TABLE ServiceMedia (
    media_id INTEGER PRIMARY KEY AUTOINCREMENT,
    service_id INTEGER NOT NULL,
    media_type ENUM('image', 'video') NOT NULL,
    url VARCHAR(255) NOT NULL,
    is_primary BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (service_id) REFERENCES Services(service_id) ON DELETE CASCADE
);

-- Ofertas diretas de restaurantes para funcionários
CREATE TABLE DirectOffers (
    offer_id INTEGER PRIMARY KEY AUTOINCREMENT,
    restaurant_id INTEGER NOT NULL,
    freelancer_id INTEGER NOT NULL,
    service_id INTEGER, -- Opcional, se relacionado a um serviço específico
    title VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    proposed_price DECIMAL(10, 2) NOT NULL,
    payment_type ENUM('por_hora', 'fixo', 'por_evento') NOT NULL,
    start_date DATETIME,
    end_date DATETIME, -- Data de término opcional
    address TEXT, -- Local de trabalho
    status ENUM('pendente', 'aceito', 'rejeitado', 'cancelado') DEFAULT 'pendente',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (restaurant_id) REFERENCES RestaurantProfiles(restaurant_id) ON DELETE CASCADE,
    FOREIGN KEY (freelancer_id) REFERENCES FreelancerProfiles(profile_id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES Services(service_id) ON DELETE SET NULL
);

-- Contratos para ofertas aceitas
CREATE TABLE Contracts (
    contract_id INTEGER PRIMARY KEY AUTOINCREMENT,
    restaurant_id INTEGER NOT NULL,
    freelancer_id INTEGER NOT NULL,
    offer_id INTEGER, -- Se veio de uma oferta direta
    service_id INTEGER, -- Se veio de um serviço listado
    title VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    agreed_price DECIMAL(10, 2) NOT NULL,
    payment_type ENUM('por_hora', 'fixo', 'por_evento') NOT NULL,
    payment_terms TEXT,
    start_date DATETIME NOT NULL,
    end_date DATETIME, -- Data de término opcional
    schedule_details TEXT, -- Detalhes do horário de trabalho
    status ENUM('ativo', 'concluído', 'cancelado', 'disputado') DEFAULT 'ativo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP,
    FOREIGN KEY (restaurant_id) REFERENCES RestaurantProfiles(restaurant_id) ON DELETE CASCADE,
    FOREIGN KEY (freelancer_id) REFERENCES FreelancerProfiles(profile_id) ON DELETE CASCADE,
    FOREIGN KEY (offer_id) REFERENCES DirectOffers(offer_id) ON DELETE SET NULL,
    FOREIGN KEY (service_id) REFERENCES Services(service_id) ON DELETE SET NULL
);

-- Turnos de trabalho agendados
CREATE TABLE WorkShifts (
    shift_id INTEGER PRIMARY KEY AUTOINCREMENT,
    contract_id INTEGER NOT NULL,
    date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    notes TEXT,
    status ENUM('agendado', 'concluído', 'cancelado') DEFAULT 'agendado',
    FOREIGN KEY (contract_id) REFERENCES Contracts(contract_id) ON DELETE CASCADE
);

-- Pagamentos
CREATE TABLE Payments (
    payment_id INTEGER PRIMARY KEY AUTOINCREMENT,
    contract_id INTEGER NOT NULL,
    shift_id INTEGER, -- Se pagamento por turno específico
    amount DECIMAL(10, 2) NOT NULL,
    payment_method VARCHAR(30) CHECK(payment_method IN ('cartão_crédito', 'paypal', 'transferência_bancária', 'dinheiro')) NOT NULL,
    transaction_fee DECIMAL(10, 2) DEFAULT 0,
    status VARCHAR(20) CHECK(status IN ('pendente', 'completo', 'reembolsado', 'falhou')) NOT NULL DEFAULT 'pendente',
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contract_id) REFERENCES Contracts(contract_id) ON DELETE CASCADE,
    FOREIGN KEY (shift_id) REFERENCES WorkShifts(shift_id) ON DELETE SET NULL
);

-- Avaliações
CREATE TABLE Reviews (
    review_id INTEGER PRIMARY KEY AUTOINCREMENT,
    contract_id INTEGER NOT NULL,
    reviewer_id INTEGER NOT NULL, -- Usuário deixando a avaliação
    reviewee_id INTEGER NOT NULL, -- Usuário sendo avaliado
    service_quality INTEGER CHECK(service_quality BETWEEN 1 AND 5) NOT NULL,
    communication INTEGER CHECK(communication BETWEEN 1 AND 5) NOT NULL,
    professionalism INTEGER CHECK(professionalism BETWEEN 1 AND 5) NOT NULL,
    overall_rating INTEGER CHECK(overall_rating BETWEEN 1 AND 5) NOT NULL,
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contract_id) REFERENCES Contracts(contract_id) ON DELETE CASCADE,
    FOREIGN KEY (reviewer_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (reviewee_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    UNIQUE(contract_id, reviewer_id, reviewee_id) -- Uma avaliação por contrato por avaliador-avaliado
);

-- Sistema de Mensagens
CREATE TABLE Conversations (
    conversation_id INTEGER PRIMARY KEY AUTOINCREMENT,
    restaurant_id INTEGER NOT NULL,
    freelancer_id INTEGER NOT NULL,
    offer_id INTEGER, -- Se relacionado a uma oferta
    contract_id INTEGER, -- Se relacionado a um contrato
    service_id INTEGER, -- Se relacionado a uma consulta de serviço
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (restaurant_id) REFERENCES RestaurantProfiles(restaurant_id) ON DELETE CASCADE,
    FOREIGN KEY (freelancer_id) REFERENCES FreelancerProfiles(profile_id) ON DELETE CASCADE,
    FOREIGN KEY (offer_id) REFERENCES DirectOffers(offer_id) ON DELETE SET NULL,
    FOREIGN KEY (contract_id) REFERENCES Contracts(contract_id) ON DELETE SET NULL,
    FOREIGN KEY (service_id) REFERENCES Services(service_id) ON DELETE SET NULL
);

CREATE TABLE Messages (
    message_id INTEGER PRIMARY KEY AUTOINCREMENT,
    conversation_id INTEGER NOT NULL,
    sender_id INTEGER NOT NULL,
    message_text TEXT NOT NULL,
    attachment_url VARCHAR(255),
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES Conversations(conversation_id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES Users(user_id) ON DELETE CASCADE
);

-- Notificações
CREATE TABLE NotificationTypes (
    type_id INTEGER PRIMARY KEY AUTOINCREMENT,
    type_name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT
);

CREATE TABLE Notifications (
    notification_id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    type_id INTEGER NOT NULL,
    title VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    related_id INTEGER, -- ID da entidade relacionada
    related_type VARCHAR(50), -- Tipo da entidade relacionada
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (type_id) REFERENCES NotificationTypes(type_id) ON DELETE CASCADE
);

-- Favoritos/Marcadores
CREATE TABLE Favorites (
    favorite_id INTEGER PRIMARY KEY AUTOINCREMENT,
    restaurant_id INTEGER NOT NULL,
    service_id INTEGER, -- Se favoritar serviço
    freelancer_id INTEGER, -- Se favoritar funcionário
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (restaurant_id) REFERENCES RestaurantProfiles(restaurant_id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES Services(service_id) ON DELETE CASCADE,
    FOREIGN KEY (freelancer_id) REFERENCES FreelancerProfiles(profile_id) ON DELETE CASCADE,
    CHECK ((service_id IS NOT NULL) OR (freelancer_id IS NOT NULL))
);

-- Configurações do Sistema
CREATE TABLE Settings (
    setting_id INTEGER PRIMARY KEY AUTOINCREMENT,
    setting_key VARCHAR(50) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);