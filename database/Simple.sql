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
    is_delivered INTEGER DEFAULT 0,
    read_at TIMESTAMP,
    delivered_at TIMESTAMP,
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

CREATE TABLE TypingIndicators (
    indicator_id INTEGER PRIMARY KEY AUTOINCREMENT,
    conversation_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    is_typing INTEGER DEFAULT 0,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES Conversations(conversation_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE
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

ALTER TABLE Users ADD COLUMN profile_image_url TEXT;

ALTER TABLE Services ADD COLUMN service_image_url TEXT;

ALTER TABLE FreelancerProfiles ADD COLUMN review_count INTEGER DEFAULT 0;

ALTER TABLE FreelancerProfiles ADD COLUMN availability_details TEXT;

ALTER TABLE Conversations ADD COLUMN job_id INTEGER REFERENCES Services(service_id);


-- Inserir dados na tabela Users
INSERT INTO Users (first_name, last_name, email, password_hash, contact, country, city) VALUES
('João', 'Silva', 'joao.silva@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '11987654321', 'Brasil', 'São Paulo'),
('Maria', 'Souza', 'maria.souza@example.com', 'hashed_password_2', '21987654321', 'Brasil', 'Rio de Janeiro'),
('Carlos', 'Oliveira', 'carlos.oliveira@example.com', 'hashed_password_3', '31987654321', 'Brasil', 'Belo Horizonte'),
('Ana', 'Pereira', 'ana.pereira@example.com', 'hashed_password_4', '41987654321', 'Brasil', 'Curitiba'),
('Pedro', 'Almeida', 'pedro.almeida@example.com', 'hashed_password_5', '51987654321', 'Brasil', 'Porto Alegre'),
(
    'Admin', 
    'Sistema', 
    'admin.sistema@example.com', 
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',  -- ISSO É UM HASH (da senha "password")
    '11999999999', 
    'Brasil', 
    'São Paulo'
);

-- Inserir dados na tabela Roles
INSERT INTO Roles (role_name) VALUES
('freelancer'),
('restaurant'),
('admin');

-- Inserir dados na tabela UserRoles
INSERT INTO UserRoles (user_id, role_id) VALUES
(1, 1), -- João Silva é freelancer
(2, 2), -- Maria Souza é restaurante
(3, 1), -- Carlos Oliveira é freelancer
(4, 2), -- Ana Pereira é restaurante
(5, 1), -- Pedro Almeida é admin
(6, 3); -- Admin Master é admin  -- ASSOCIAÇÃO DO NOVO ADMIN AQUI

-- Inserir dados na tabela FreelancerProfiles
INSERT INTO FreelancerProfiles (user_id, hourly_rate, availability, experience_years, avg_rating) VALUES
(1, 50.00, 'flexible', 5, 4.5),
(3, 45.00, 'part-time', 3, 4.2);

-- Inserir dados na tabela RestaurantProfiles
INSERT INTO RestaurantProfiles (user_id, restaurant_name, restaurant_type, description, avg_rating) VALUES
(2, 'Sabor Brasileiro', 'Brasileira', 'Restaurante com comidas típicas brasileiras', 4.7),
(4, 'Gourmet Italiano', 'Italiana', 'Culinária italiana autêntica', 4.5);

-- Inserir dados na tabela ServiceCategories
INSERT INTO ServiceCategories (name, description) VALUES
('Culinária', 'Serviços relacionados à preparação de alimentos'),
('Limpeza', 'Serviços de limpeza e higienização'),
('Bartending', 'Serviços de preparação de bebidas'),
('Atendimento', 'Serviços de atendimento ao cliente');

-- Inserir dados na tabela Skills
INSERT INTO Skills (skill_name, description) VALUES
('Cozinhar', 'Habilidade em preparar alimentos'),
('Limpeza Profissional', 'Habilidade em limpeza profissional'),
('Mixologia', 'Habilidade em preparar coquetéis'),
('Atendimento ao Cliente', 'Habilidade em atender clientes');

-- Inserir dados na tabela FreelancerSkills
INSERT INTO FreelancerSkills (freelancer_id, skill_id, proficiency_level) VALUES
(1, 1, 'Avançado'),
(1, 4, 'Intermediário'),
(3, 1, 'Intermediário'),
(3, 2, 'Avançado');

-- Inserir dados na tabela Services
INSERT INTO Services (freelancer_id, category_id, title, description, price_type, base_price, is_active) VALUES
(1, 1, 'Chef Particular', 'Serviço de chef particular para eventos', 'por hora', 750.00, 1),
(1, 4, 'Garçom Profissional', 'Serviço de garçom para eventos', 'por hora', 30.00, 1),
(2, 2, 'Limpeza Profissional', 'Serviço de limpeza profissional', 'por hora', 25.00, 1);

-- Inserir dados na tabela Contracts
INSERT INTO Contracts (restaurant_id, freelancer_id, service_id, title, description, agreed_price, payment_type, start_date, end_date, status) VALUES
(1, 1, 1, 'Evento de Aniversário', 'Serviço de chef particular para evento de aniversário', 1000.00, 'cartão', '2023-10-15 18:00:00', '2023-10-15 23:00:00', 'concluído'),
(2, 3, 3, 'Limpeza Semanal', 'Serviço de limpeza semanal', 500.00, 'dinheiro', '2023-10-10 08:00:00', '2023-10-10 12:00:00', 'ativo'),
(2, 1, 1, 'Evento Corporativo', 'Serviço de chef para evento corporativo', 1200.00, 'transferência', '2023-11-01 19:00:00', '2023-11-01 23:00:00', 'concluído');

-- Inserir dados na tabela Payments
INSERT INTO Payments (contract_id, amount, payment_method, status) VALUES
(1, 1000.00, 'cartão', 'concluído'),
(2, 500.00, 'dinheiro', 'pendente'),
(3, 1200.00, 'transferência', 'concluído');

-- Inserir dados na tabela Reviews
INSERT INTO Reviews (contract_id, reviewer_id, reviewee_id, overall_rating, comment) VALUES
(1, 2, 1, 5, 'Excelente serviço!'),
(3, 4, 1, 3, 'Profissional excepcional! Superou todas as expectivas'),
(2, 4, 3, 2, 'Bom serviço de limpeza, pontual e eficiente.');

-- Inserir dados na tabela Conversations
INSERT INTO Conversations (restaurant_id, freelancer_id) VALUES
(1, 1),
(2, 3);

-- Inserir dados na tabela Messages
INSERT INTO Messages (conversation_id, sender_id, message_text, is_read, is_delivered, read_at, delivered_at) VALUES
(1, 1, 'Olá, gostaria de contratar seus serviços.', 1, 1, '2023-10-16 10:00:00', '2023-10-16 09:55:00'),
(1, 2, 'Olá, ficarei feliz em ajudar. Quando será o evento?', 1, 1, '2023-10-16 10:05:00', '2023-10-16 10:02:00'),
(2, 3, 'Bom dia, preciso de um serviço de limpeza.', 1, 1, '2023-10-17 08:30:00', '2023-10-17 08:25:00'),
(2, 4, 'Bom dia, qual o horário desejado?', 1, 1, '2023-10-17 08:35:00', '2023-10-17 08:32:00');


INSERT INTO TypingIndicators (conversation_id, user_id, is_typing, last_activity) VALUES
(1, 1, 0, '2023-10-16 09:50:00'),
(1, 2, 0, '2023-10-16 10:00:00'),
(2, 3, 0, '2023-10-17 08:20:00'),
(2, 4, 0, '2023-10-17 08:30:00');

-- Inserir dados na tabela ChefSpecializations
INSERT INTO ChefSpecializations (freelancer_id, cuisine_type, certifications, dietary_specialties, menu_planning, catering_experience) VALUES
(1, 'Brasileira', 'Certificado em Culinária Brasileira', 'Vegetariana', 1, 1);

-- Inserir dados na tabela CleaningSpecializations
INSERT INTO CleaningSpecializations (freelancer_id, kitchen_cleaning, dining_area_cleaning, equipment_experience, eco_friendly) VALUES
(3, 1, 1, 'Máquinas de lavar louça', 1);

-- Inserir dados na tabela BartenderSpecializations
INSERT INTO BartenderSpecializations (freelancer_id, cocktail_specialist, wine_knowledge, beer_knowledge, flair_bartending, certifications) VALUES
(1, 1, 1, 0, 0, 'Certificado em Mixologia');

-- Inserir dados na tabela ServiceStaffSpecializations
INSERT INTO ServiceStaffSpecializations (freelancer_id, fine_dining_experience, event_service, sommelier_knowledge, customer_service_rating) VALUES
(1, 1, 1, 0, 5);

-- Inserir dados na tabela Languages
INSERT INTO Languages (language_name) VALUES
('Português'),
('Inglês'),
('Espanhol');

-- Inserir dados na tabela FreelancerLanguages
INSERT INTO FreelancerLanguages (freelancer_id, language_id, proficiency) VALUES
(1, 1, 'Nativo'),
(1, 2, 'Avançado'),
(3, 1, 'Nativo'),
(3, 3, 'Intermediário');

ALTER TABLE Users ADD COLUMN specialization TEXT;

-- Atualizar URLs de imagens e contagem de reviews
UPDATE Users SET profile_image_url = 'https://example.com/joao.jpg' WHERE user_id = 1;
UPDATE Users SET profile_image_url = 'https://example.com/maria.jpg' WHERE user_id = 2;
UPDATE Users SET profile_image_url = 'https://example.com/carlos.jpg' WHERE user_id = 3;
UPDATE Users SET profile_image_url = 'https://example.com/ana.jpg' WHERE user_id = 4;
UPDATE Users SET profile_image_url = 'https://example.com/pedro.jpg' WHERE user_id = 5;
UPDATE Users SET profile_image_url = 'https://example.com/admin.jpg' WHERE user_id = 6;

UPDATE Services SET service_image_url = 'https://example.com/chef.jpg' WHERE service_id = 1;
UPDATE Services SET service_image_url = 'https://example.com/garcom.jpg' WHERE service_id = 2;
UPDATE Services SET service_image_url = 'https://example.com/limpeza.jpg' WHERE service_id = 3;

ALTER TABLE Services ADD COLUMN review_count INTEGER DEFAULT 0;
ALTER TABLE Services ADD COLUMN avg_rating REAL DEFAULT 0;

UPDATE FreelancerProfiles SET availability_details = 'Disponível nos finais de semana' WHERE profile_id = 1;
UPDATE FreelancerProfiles SET availability_details = 'Disponível durante a semana' WHERE profile_id = 2;



--------------------------------------------------------------


-- Remover o valor default para a média de avaliações do freelancer
-- Primeiro, alteramos a coluna para remover o default value de avg_rating
ALTER TABLE FreelancerProfiles DROP COLUMN avg_rating;

-- Adicionar a coluna novamente, mas sem valor default
ALTER TABLE FreelancerProfiles ADD COLUMN avg_rating REAL;

-- Criar uma VIEW que calcula a média de avaliações para cada freelancer
-- Esta VIEW usa a tabela Reviews existente e calcula a média para cada perfil de freelancer
CREATE VIEW FreelancerRatingView AS
SELECT 
    fp.profile_id,
    AVG(r.overall_rating) AS avg_rating,
    COUNT(r.review_id) AS review_count
FROM 
    FreelancerProfiles fp
JOIN 
    Users u ON fp.user_id = u.user_id
LEFT JOIN 
    Reviews r ON r.reviewee_id = u.user_id
WHERE 
    r.reviewee_id IS NOT NULL
GROUP BY 
    fp.profile_id;

-- Trigger for INSERT
CREATE TRIGGER update_freelancer_avg_rating_insert
AFTER INSERT ON Reviews
BEGIN
    UPDATE FreelancerProfiles
    SET
        avg_rating = (
            SELECT AVG(r.overall_rating)
            FROM Reviews r
            JOIN Users u ON r.reviewee_id = u.user_id
            WHERE u.user_id = (
                SELECT user_id
                FROM FreelancerProfiles
                WHERE profile_id = FreelancerProfiles.profile_id
            )
        ),
        review_count = (
            SELECT COUNT(r.review_id)
            FROM Reviews r
            JOIN Users u ON r.reviewee_id = u.user_id
            WHERE u.user_id = (
                SELECT user_id
                FROM FreelancerProfiles
                WHERE profile_id = FreelancerProfiles.profile_id
            )
        )
    WHERE
        user_id IN (
            SELECT reviewee_id
            FROM Reviews
            WHERE rowid = new.rowid
        );
END;

-- Trigger for UPDATE
CREATE TRIGGER update_freelancer_avg_rating_update
AFTER UPDATE ON Reviews
BEGIN
    UPDATE FreelancerProfiles
    SET
        avg_rating = (
            SELECT AVG(r.overall_rating)
            FROM Reviews r
            JOIN Users u ON r.reviewee_id = u.user_id
            WHERE u.user_id = (
                SELECT user_id
                FROM FreelancerProfiles
                WHERE profile_id = FreelancerProfiles.profile_id
            )
        ),
        review_count = (
            SELECT COUNT(r.review_id)
            FROM Reviews r
            JOIN Users u ON r.reviewee_id = u.user_id
            WHERE u.user_id = (
                SELECT user_id
                FROM FreelancerProfiles
                WHERE profile_id = FreelancerProfiles.profile_id
            )
        )
    WHERE
        user_id IN (
            SELECT reviewee_id
            FROM Reviews
            WHERE rowid = new.rowid
        );
END;

-- Trigger for DELETE
CREATE TRIGGER update_freelancer_avg_rating_delete
AFTER DELETE ON Reviews
BEGIN
    UPDATE FreelancerProfiles
    SET
        avg_rating = (
            SELECT AVG(r.overall_rating)
            FROM Reviews r
            JOIN Users u ON r.reviewee_id = u.user_id
            WHERE u.user_id = (
                SELECT user_id
                FROM FreelancerProfiles
                WHERE profile_id = FreelancerProfiles.profile_id
            )
        ),
        review_count = (
            SELECT COUNT(r.review_id)
            FROM Reviews r
            JOIN Users u ON r.reviewee_id = u.user_id
            WHERE u.user_id = (
                SELECT user_id
                FROM FreelancerProfiles
                WHERE profile_id = FreelancerProfiles.profile_id
            )
        )
    WHERE
        user_id IN (
            SELECT reviewee_id
            FROM Reviews
            WHERE rowid = old.rowid
        );
END;

-- Procedimento para recalcular todas as médias de avaliações dos freelancers
-- Útil para executar após migrações ou importações de dados
-- Recalculate all freelancer ratings
UPDATE FreelancerProfiles
SET
    avg_rating = (
        SELECT AVG(r.overall_rating)
        FROM Reviews r
        JOIN Users u ON r.reviewee_id = u.user_id
        WHERE u.user_id = FreelancerProfiles.user_id
    ),
    review_count = (
        SELECT COUNT(r.review_id)
        FROM Reviews r
        JOIN Users u ON r.reviewee_id = u.user_id
        WHERE u.user_id = FreelancerProfiles.user_id
    );
-----------------------------------------------------------