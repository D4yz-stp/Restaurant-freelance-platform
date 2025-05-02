-- Tabela de Usuários
CREATE TABLE Users (
    user_id INTEGER PRIMARY KEY AUTOINCREMENT,
    first_name TEXT NOT NULL,
    last_name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    contact TEXT NOT NULL,
    country TEXT,
    city TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP
);

-- Tabela de Funções/Papéis
CREATE TABLE Roles (
    role_id INTEGER PRIMARY KEY AUTOINCREMENT,
    role_name TEXT NOT NULL CHECK (role_name IN ('freelancer', 'restaurant', 'admin'))
);

-- Tabela de Relação Usuários-Funções
CREATE TABLE UserRoles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    role_id INTEGER NOT NULL,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES Roles(role_id) ON DELETE CASCADE,
    UNIQUE(user_id, role_id)
);

-- Perfis de Freelancers
CREATE TABLE FreelancerProfiles (
    profile_id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER UNIQUE NOT NULL,
    hourly_rate REAL,
    availability TEXT DEFAULT 'flexible',
    experience_years INTEGER,
    avg_rating REAL DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE
);

-- Perfis de Restaurantes
CREATE TABLE RestaurantProfiles (
    restaurant_id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER UNIQUE NOT NULL,
    restaurant_name TEXT NOT NULL,
    restaurant_type TEXT,
    description TEXT,
    avg_rating REAL DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE
);

-- Categorias de serviços
CREATE TABLE ServiceCategories (
    category_id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT UNIQUE NOT NULL,
    description TEXT
);

-- Habilidades disponíveis
CREATE TABLE Skills (
    skill_id INTEGER PRIMARY KEY AUTOINCREMENT,
    skill_name TEXT NOT NULL UNIQUE,
    description TEXT
);

-- Habilidades dos freelancers
CREATE TABLE FreelancerSkills (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    freelancer_id INTEGER NOT NULL,
    skill_id INTEGER NOT NULL,
    proficiency_level TEXT,
    FOREIGN KEY (freelancer_id) REFERENCES FreelancerProfiles(profile_id) ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES Skills(skill_id) ON DELETE CASCADE,
    UNIQUE(freelancer_id, skill_id)
);

-- Serviços oferecidos pelos freelancers
CREATE TABLE Services (
    service_id INTEGER PRIMARY KEY AUTOINCREMENT,
    freelancer_id INTEGER NOT NULL,
    category_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    description TEXT NOT NULL,
    price_type TEXT NOT NULL,
    base_price REAL NOT NULL,
    is_active INTEGER DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (freelancer_id) REFERENCES FreelancerProfiles(profile_id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES ServiceCategories(category_id) ON DELETE CASCADE
);

-- Contratos entre restaurantes e freelancers
CREATE TABLE Contracts (
    contract_id INTEGER PRIMARY KEY AUTOINCREMENT,
    restaurant_id INTEGER NOT NULL,
    freelancer_id INTEGER NOT NULL,
    service_id INTEGER,
    title TEXT NOT NULL,
    description TEXT NOT NULL,
    agreed_price REAL NOT NULL,
    payment_type TEXT NOT NULL,
    start_date DATETIME NOT NULL,
    end_date DATETIME,
    status TEXT DEFAULT 'ativo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (restaurant_id) REFERENCES RestaurantProfiles(restaurant_id) ON DELETE CASCADE,
    FOREIGN KEY (freelancer_id) REFERENCES FreelancerProfiles(profile_id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES Services(service_id) ON DELETE SET NULL
);

-- Pagamentos relacionados aos contratos
CREATE TABLE Payments (
    payment_id INTEGER PRIMARY KEY AUTOINCREMENT,
    contract_id INTEGER NOT NULL,
    amount REAL NOT NULL,
    payment_method TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'pendente',
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contract_id) REFERENCES Contracts(contract_id) ON DELETE CASCADE
);

-- Sistema de avaliações
CREATE TABLE Reviews (
    review_id INTEGER PRIMARY KEY AUTOINCREMENT,
    contract_id INTEGER NOT NULL,
    reviewer_id INTEGER NOT NULL,
    reviewee_id INTEGER NOT NULL,
    overall_rating INTEGER NOT NULL CHECK (overall_rating BETWEEN 1 AND 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contract_id) REFERENCES Contracts(contract_id) ON DELETE CASCADE,
    FOREIGN KEY (reviewer_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (reviewee_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    UNIQUE(contract_id, reviewer_id, reviewee_id)
);

-- Sistema de mensagens
CREATE TABLE Conversations (
    conversation_id INTEGER PRIMARY KEY AUTOINCREMENT,
    restaurant_id INTEGER NOT NULL,
    freelancer_id INTEGER NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (restaurant_id) REFERENCES RestaurantProfiles(restaurant_id) ON DELETE CASCADE,
    FOREIGN KEY (freelancer_id) REFERENCES FreelancerProfiles(profile_id) ON DELETE CASCADE
);

CREATE TABLE Messages (
    message_id INTEGER PRIMARY KEY AUTOINCREMENT,
    conversation_id INTEGER NOT NULL,
    sender_id INTEGER NOT NULL,
    message_text TEXT NOT NULL,
    is_read INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES Conversations(conversation_id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES Users(user_id) ON DELETE CASCADE
);

-- Especializações dos chefs
CREATE TABLE ChefSpecializations (
    chef_id INTEGER PRIMARY KEY AUTOINCREMENT,
    freelancer_id INTEGER NOT NULL,
    cuisine_type TEXT NOT NULL,
    certifications TEXT,
    dietary_specialties TEXT,
    menu_planning INTEGER DEFAULT 0,
    catering_experience INTEGER DEFAULT 0,
    FOREIGN KEY (freelancer_id) REFERENCES FreelancerProfiles(profile_id) ON DELETE CASCADE
);

-- Especialização em Limpeza
CREATE TABLE CleaningSpecializations (
    cleaning_id INTEGER PRIMARY KEY AUTOINCREMENT,
    freelancer_id INTEGER NOT NULL,
    kitchen_cleaning INTEGER DEFAULT 0,
    dining_area_cleaning INTEGER DEFAULT 0,
    equipment_experience TEXT,
    eco_friendly INTEGER DEFAULT 0,
    FOREIGN KEY (freelancer_id) REFERENCES FreelancerProfiles(profile_id) ON DELETE CASCADE
);

-- Especialização em Bartending
CREATE TABLE BartenderSpecializations (
    bartender_id INTEGER PRIMARY KEY AUTOINCREMENT,
    freelancer_id INTEGER NOT NULL,
    cocktail_specialist INTEGER DEFAULT 0,
    wine_knowledge INTEGER DEFAULT 0,
    beer_knowledge INTEGER DEFAULT 0,
    flair_bartending INTEGER DEFAULT 0,
    certifications TEXT,
    FOREIGN KEY (freelancer_id) REFERENCES FreelancerProfiles(profile_id) ON DELETE CASCADE
);

-- Especialização em Atendimento
CREATE TABLE ServiceStaffSpecializations (
    service_staff_id INTEGER PRIMARY KEY AUTOINCREMENT,
    freelancer_id INTEGER NOT NULL,
    fine_dining_experience INTEGER DEFAULT 0,
    event_service INTEGER DEFAULT 0,
    sommelier_knowledge INTEGER DEFAULT 0,
    customer_service_rating INTEGER CHECK (customer_service_rating BETWEEN 1 AND 5),
    FOREIGN KEY (freelancer_id) REFERENCES FreelancerProfiles(profile_id) ON DELETE CASCADE
);

-- Idiomas falados pelos funcionários
CREATE TABLE Languages (
    language_id INTEGER PRIMARY KEY AUTOINCREMENT,
    language_name TEXT NOT NULL UNIQUE
);

CREATE TABLE FreelancerLanguages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    freelancer_id INTEGER NOT NULL,
    language_id INTEGER NOT NULL,
    proficiency TEXT,
    FOREIGN KEY (freelancer_id) REFERENCES FreelancerProfiles(profile_id) ON DELETE CASCADE,
    FOREIGN KEY (language_id) REFERENCES Languages(language_id) ON DELETE CASCADE,
    UNIQUE(freelancer_id, language_id)
);