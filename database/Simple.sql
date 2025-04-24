/*
 * Sistema de Conexão entre Freelancers e Restaurantes
 * Este esquema representa um sistema simplificado onde restaurantes podem contratar
 * profissionais freelancers para diferentes funções.
 */
/*dgdgdgd*/
-- Tabela principal de usuários - armazena os dados básicos de todos os usuários do sistema
-- Tanto freelancers quanto proprietários de restaurantes são registrados aqui primeiro
CREATE TABLE Users (
    user_id INTEGER PRIMARY KEY AUTOINCREMENT,
    first_name VARCHAR(30) NOT NULL,
    last_name VARCHAR(30) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL, -- Email é único e usado para login
    password_hash VARCHAR(255) NOT NULL, -- Senha armazenada como hash por segurança
    contact VARCHAR(20) NOT NULL, -- Número de contato/telefone
    country VARCHAR(40),
    city VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Data de criação da conta
    last_login TIMESTAMP -- Último acesso ao sistema
);

-- Define os possíveis papéis no sistema: freelancer, restaurante ou administrador
CREATE TABLE Roles (
    role_id INTEGER PRIMARY KEY AUTOINCREMENT,
    role_name VARCHAR(20) UNIQUE NOT NULL CHECK(role_name IN ('freelancer', 'restaurant', 'admin'))
);

-- Associa usuários a papéis (relacionamento muitos-para-muitos)
-- Um usuário pode ter múltiplos papéis (ex: ser freelancer e também ter um restaurante)
CREATE TABLE UserRoles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    role_id INTEGER NOT NULL,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE, -- Remove o papel quando usuário é removido
    FOREIGN KEY (role_id) REFERENCES Roles(role_id) ON DELETE CASCADE,
    UNIQUE(user_id, role_id) -- Impede duplicação de papel para o mesmo usuário
);

-- Informações específicas para os perfis de freelancers
-- Cada freelancer deve ter uma entrada correspondente na tabela Users
CREATE TABLE FreelancerProfiles (
    profile_id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER UNIQUE NOT NULL, -- Um usuário tem apenas um perfil de freelancer
    hourly_rate DECIMAL(10, 2), -- Taxa cobrada por hora
    availability VARCHAR(20) DEFAULT 'flexible', -- Disponibilidade (tempo integral, parcial, etc.)
    experience_years INTEGER, -- Anos de experiência profissional
    avg_rating DECIMAL(3, 2) DEFAULT 0, -- Média das avaliações recebidas
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE -- Remove perfil quando usuário é removido
);

-- Informações específicas para os perfis de restaurantes
-- Cada restaurante deve ter uma entrada correspondente na tabela Users
CREATE TABLE RestaurantProfiles (
    restaurant_id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER UNIQUE NOT NULL, -- Um usuário tem apenas um perfil de restaurante
    restaurant_name VARCHAR(100) NOT NULL, -- Nome do estabelecimento
    restaurant_type VARCHAR(50), -- Tipo de cozinha ou estabelecimento
    description TEXT, -- Descrição do restaurante
    avg_rating DECIMAL(3, 2) DEFAULT 0, -- Média das avaliações recebidas
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE -- Remove perfil quando usuário é removido
);

-- Categorias de serviços que os freelancers podem oferecer
-- Ex: Chef, Garçom, Barman, Limpeza, etc.
CREATE TABLE ServiceCategories (
    category_id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(50) UNIQUE NOT NULL, -- Nome da categoria
    description TEXT -- Descrição da categoria
);

-- Lista de habilidades disponíveis no sistema
CREATE TABLE Skills (
    skill_id INTEGER PRIMARY KEY AUTOINCREMENT,
    skill_name VARCHAR(50) NOT NULL UNIQUE, -- Nome da habilidade
    description TEXT -- Descrição da habilidade
);

-- Associa habilidades aos freelancers (relacionamento muitos-para-muitos)
-- Um freelancer pode ter múltiplas habilidades e uma habilidade pode pertencer a vários freelancers
CREATE TABLE FreelancerSkills (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    freelancer_id INTEGER NOT NULL, -- Referência ao perfil do freelancer
    skill_id INTEGER NOT NULL, -- Referência à habilidade
    proficiency_level VARCHAR(20), -- Nível de proficiência na habilidade
    FOREIGN KEY (freelancer_id) REFERENCES FreelancerProfiles(profile_id) ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES Skills(skill_id) ON DELETE CASCADE,
    UNIQUE(freelancer_id, skill_id) -- Impede duplicação de habilidade para o mesmo freelancer
);

-- Serviços específicos oferecidos pelos freelancers
-- Ex: "Chefe de cozinha italiana", "Barman especializado em coquetéis"
CREATE TABLE Services (
    service_id INTEGER PRIMARY KEY AUTOINCREMENT,
    freelancer_id INTEGER NOT NULL, -- Quem oferece o serviço
    category_id INTEGER NOT NULL, -- Categoria do serviço
    title VARCHAR(100) NOT NULL, -- Título do serviço
    description TEXT NOT NULL, -- Descrição detalhada
    price_type VARCHAR(10) NOT NULL, -- Tipo de preço (por hora, fixo, por evento)
    base_price DECIMAL(10, 2) NOT NULL, -- Valor base cobrado
    is_active BOOLEAN DEFAULT TRUE, -- Se o serviço está disponível para contratação
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (freelancer_id) REFERENCES FreelancerProfiles(profile_id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES ServiceCategories(category_id) ON DELETE CASCADE
);

-- Contratos estabelecidos entre restaurantes e freelancers
-- Representa um acordo formal de trabalho
CREATE TABLE Contracts (
    contract_id INTEGER PRIMARY KEY AUTOINCREMENT,
    restaurant_id INTEGER NOT NULL, -- Contratante
    freelancer_id INTEGER NOT NULL, -- Contratado
    service_id INTEGER, -- Serviço contratado (opcional, pode ser personalizado)
    title VARCHAR(100) NOT NULL, -- Título do contrato
    description TEXT NOT NULL, -- Detalhes do trabalho
    agreed_price DECIMAL(10, 2) NOT NULL, -- Valor acordado
    payment_type VARCHAR(10) NOT NULL, -- Tipo de pagamento
    start_date DATETIME NOT NULL, -- Data de início
    end_date DATETIME, -- Data de término (opcional para contratos contínuos)
    status VARCHAR(20) DEFAULT 'ativo', -- Status atual do contrato
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (restaurant_id) REFERENCES RestaurantProfiles(restaurant_id) ON DELETE CASCADE,
    FOREIGN KEY (freelancer_id) REFERENCES FreelancerProfiles(profile_id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES Services(service_id) ON DELETE SET NULL
);

-- Registros de pagamentos relacionados aos contratos
CREATE TABLE Payments (
    payment_id INTEGER PRIMARY KEY AUTOINCREMENT,
    contract_id INTEGER NOT NULL, -- Contrato ao qual o pagamento se refere
    amount DECIMAL(10, 2) NOT NULL, -- Valor do pagamento
    payment_method VARCHAR(30) NOT NULL, -- Método de pagamento
    status VARCHAR(20) NOT NULL DEFAULT 'pendente', -- Status do pagamento
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Data da transação
    FOREIGN KEY (contract_id) REFERENCES Contracts(contract_id) ON DELETE CASCADE
);

-- Sistema de avaliações após a prestação de serviço
-- Permite que tanto freelancers quanto restaurantes avaliem uns aos outros
CREATE TABLE Reviews (
    review_id INTEGER PRIMARY KEY AUTOINCREMENT,
    contract_id INTEGER NOT NULL, -- Contrato relacionado à avaliação
    reviewer_id INTEGER NOT NULL, -- Usuário que está avaliando
    reviewee_id INTEGER NOT NULL, -- Usuário que está sendo avaliado
    overall_rating INTEGER CHECK(overall_rating BETWEEN 1 AND 5) NOT NULL, -- Nota de 1 a 5
    comment TEXT, -- Comentário textual
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contract_id) REFERENCES Contracts(contract_id) ON DELETE CASCADE,
    FOREIGN KEY (reviewer_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (reviewee_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    UNIQUE(contract_id, reviewer_id, reviewee_id) -- Impede múltiplas avaliações para o mesmo par
);

-- Conversas entre restaurantes e freelancers
-- Representa um canal de comunicação entre as partes
CREATE TABLE Conversations (
    conversation_id INTEGER PRIMARY KEY AUTOINCREMENT,
    restaurant_id INTEGER NOT NULL, -- Parte restaurante
    freelancer_id INTEGER NOT NULL, -- Parte freelancer
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Data de início da conversa
    FOREIGN KEY (restaurant_id) REFERENCES RestaurantProfiles(restaurant_id) ON DELETE CASCADE,
    FOREIGN KEY (freelancer_id) REFERENCES FreelancerProfiles(profile_id) ON DELETE CASCADE
);

-- Mensagens individuais trocadas em uma conversa
CREATE TABLE Messages (
    message_id INTEGER PRIMARY KEY AUTOINCREMENT,
    conversation_id INTEGER NOT NULL, -- Conversa à qual a mensagem pertence
    sender_id INTEGER NOT NULL, -- Usuário que enviou a mensagem
    message_text TEXT NOT NULL, -- Conteúdo da mensagem
    is_read BOOLEAN DEFAULT FALSE, -- Se a mensagem foi lida
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Data de envio
    FOREIGN KEY (conversation_id) REFERENCES Conversations(conversation_id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES Users(user_id) ON DELETE CASCADE
);

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

/*git a
 * Relacionamentos entre tabelas:
 * 
 * 1. Users é a tabela central - todos os usuários são cadastrados aqui
 * 2. Um User pode ter um ou mais Roles (através de UserRoles)
 * 3. Se um User for Freelancer, terá um registro em FreelancerProfiles
 * 4. Se um User for Restaurant, terá um registro em RestaurantProfiles
 * 5. FreelancerProfiles se relaciona com:
 *    - Skills (através de FreelancerSkills)
 *    - Services (um freelancer oferece serviços)
 * 6. Contracts conecta RestaurantProfiles e FreelancerProfiles
 * 7. Payments são vinculados a Contracts
 * 8. Reviews se referem a um Contract e envolvem dois Users
 * 9. Conversations conectam RestaurantProfiles e FreelancerProfiles
 * 10. Messages pertencem a uma Conversation e são enviadas por Users
 */