-- DROP TABLE statements em ordem específica para evitar violação de constraints
DROP TABLE IF EXISTS Reviews;
DROP TABLE IF EXISTS Payments;
DROP TABLE IF EXISTS Contracts;
DROP TABLE IF EXISTS Messages;
DROP TABLE IF EXISTS Conversations;
DROP TABLE IF EXISTS FreelancerLanguages;
DROP TABLE IF EXISTS Languages;
DROP TABLE IF EXISTS FreelancerSkills;
DROP TABLE IF EXISTS Skills;
DROP TABLE IF EXISTS ServiceStaffSpecializations;
DROP TABLE IF EXISTS BartenderSpecializations;
DROP TABLE IF EXISTS CleaningSpecializations;
DROP TABLE IF EXISTS ChefSpecializations;
DROP TABLE IF EXISTS Services;
DROP TABLE IF EXISTS ServiceCategories;
DROP TABLE IF EXISTS RestaurantProfiles;
DROP TABLE IF EXISTS FreelancerProfiles;
DROP TABLE IF EXISTS UserRoles;
DROP TABLE IF EXISTS Roles;
DROP TABLE IF EXISTS Users;


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

-- Inserindo papéis básicos
INSERT INTO Roles (role_name) VALUES 
('freelancer'),
('restaurant'),
('admin');

-- Inserindo usuários
INSERT INTO Users (first_name, last_name, email, password_hash, contact, country, city) VALUES
('João', 'Silva', 'joao.silva@email.com', 'hash123', '(11) 99999-0000', 'Brasil', 'São Paulo'),
('Maria', 'Rodrigues', 'maria.rodrigues@email.com', 'hash456', '(11) 88888-1111', 'Brasil', 'Rio de Janeiro'),
('Pedro', 'Gomes', 'pedro.gomes@email.com', 'hash789', '(12) 77777-2222', 'Brasil', 'Curitiba'),
('Ana', 'Souza', 'ana.souza@email.com', 'hash012', '(13) 66666-3333', 'Brasil', 'Salvador'),
('Carlos', 'Oliveira', 'carlos.oliveira@email.com', 'hash345', '(14) 55555-4444', 'Brasil', 'Belo Horizonte');

-- Atribuindo papéis aos usuários
INSERT INTO UserRoles (user_id, role_id) VALUES
(1, 1), -- João é freelancer
(2, 2), -- Maria é restaurante
(3, 1), -- Pedro é freelancer
(4, 2), -- Ana é restaurante
(5, 1); -- Carlos é freelancer

-- Criando perfis de freelancers
INSERT INTO FreelancerProfiles (user_id, hourly_rate, availability, experience_years, avg_rating) VALUES
(1, 100.00, 'flexível', 5, 4.8),
(3, 120.00, 'manhã/tarde', 7, 4.9),
(5, 90.00, 'flexível', 3, 4.5);

-- Criando perfis de restaurantes
INSERT INTO RestaurantProfiles (user_id, restaurant_name, restaurant_type, description, avg_rating) VALUES
(2, 'Restaurante Bistrô', 'Francesa', 'Cozinha gourmet francesa contemporânea', 4.7),
(4, 'O Paparico', 'Brasileira', 'Comida típica brasileira com toque moderno', 4.6);

-- Inserindo categorias de serviços
INSERT INTO ServiceCategories (name, description) VALUES
('Cozinha', 'Serviços relacionados à preparação de alimentos'),
('Limpeza', 'Serviços de limpeza profissional'),
('Atendimento', 'Serviços de garçom e atendimento ao cliente'),
('Bares', 'Serviços de bar e mixologia');

-- Inserindo habilidades disponíveis
INSERT INTO Skills (skill_name, description) VALUES
('Cozinha Francesa', 'Especialização em culinária francesa'),
('Cozinha Brasileira', 'Conhecimento profundo da gastronomia brasileira'),
('Limpeza Industrial', 'Experiência com limpeza em ambientes comerciais'),
('Atendimento Premium', 'Experiência em atendimento de luxo'),
('Mixologia Avançada', 'Especialização em coquetéis artesanais');

-- Associando habilidades aos freelancers
INSERT INTO FreelancerSkills (freelancer_id, skill_id, proficiency_level) VALUES
(1, 1, 'avançado'), -- João: Cozinha Francesa
(1, 2, 'intermediário'), -- João: Cozinha Brasileira
(2, 3, 'avançado'), -- Pedro: Limpeza Industrial
(2, 4, 'básico'), -- Pedro: Atendimento Premium
(3, 5, 'avançado'); -- Carlos: Mixologia Avançada

-- Criando serviços oferecidos pelos freelancers
INSERT INTO Services (freelancer_id, category_id, title, description, price_type, base_price) VALUES
(1, 1, 'Chef Francês Experiente', 'Serviço completo de cozinha francesa gourmet', 'hora', 150.00),
(2, 2, 'Limpeza Profissional', 'Serviço especializado em limpeza industrial', 'projeto', 500.00),
(3, 4, 'Barman Especializado', 'Serviço premium de bartender para eventos', 'evento', 800.00);

-- Criando contratos entre restaurantes e freelancers
INSERT INTO Contracts (restaurant_id, freelancer_id, service_id, title, description, agreed_price, payment_type, start_date, end_date, status) VALUES
(1, 1, 1, 'Contrato Chef Temporário', 'Serviço de chef francês para evento especial', 1200.00, 'projeto', '2025-03-01', '2025-03-31', 'ativo'),
(1, 2, NULL, 'Contrato de Limpeza Diária', 'Serviço diário de limpeza do restaurante', 2500.00, 'mensal', '2025-03-01', NULL, 'ativo'),
(2, 3, 3, 'Contrato Barman Eventos', 'Serviço de barman para eventos especiais', 1000.00, 'evento', '2025-03-15', '2025-03-15', 'ativo');

-- Inserindo pagamentos
INSERT INTO Payments (contract_id, amount, payment_method, status, transaction_date) VALUES
(1, 600.00, 'cartão', 'pago', '2025-03-01'),
(1, 600.00, 'transferência', 'pendente', '2025-03-15'),
(2, 1250.00, 'boleto', 'pago', '2025-03-05'),
(3, 1000.00, 'pix', 'pago', '2025-03-10');

-- Inserindo avaliações
INSERT INTO Reviews (contract_id, reviewer_id, reviewee_id, overall_rating, comment, created_at) VALUES
(1, 2, 1, 5, 'Excelente serviço prestado pelo chef!', '2025-03-10'),
(2, 2, 2, 4, 'Serviço bom, mas com pequenos ajustes necessários.', '2025-03-12'),
(3, 4, 3, 5, 'Barman muito profissional e criativo!', '2025-03-16');