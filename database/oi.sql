INSERT INTO Users (first_name, last_name, email, password_hash, contact, country, city) VALUES
('João', 'Silva', 'joao.silva@example.com', 'hashed_password_1', '11987654321', 'Brasil', 'São Paulo'),
('Maria', 'Souza', 'maria.souza@example.com', 'hashed_password_2', '21987654321', 'Brasil', 'Rio de Janeiro'),
('Carlos', 'Oliveira', 'carlos.oliveira@example.com', 'hashed_password_3', '31987654321', 'Brasil', 'Belo Horizonte'),
('Ana', 'Pereira', 'ana.pereira@example.com', 'hashed_password_4', '41987654321', 'Brasil', 'Curitiba'),
('Pedro', 'Almeida', 'pedro.almeida@example.com', 'hashed_password_5', '51987654321', 'Brasil', 'Porto Alegre');

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
(5, 3); -- Pedro Almeida é admin

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
(1, 1, 'Chef Particular', 'Serviço de chef particular para eventos', 'por evento', 500.00, 1),
(1, 4, 'Garçom Profissional', 'Serviço de garçom para eventos', 'por hora', 30.00, 1),
(3, 2, 'Limpeza Profissional', 'Serviço de limpeza profissional', 'por hora', 25.00, 1);

-- Inserir dados na tabela Contracts
INSERT INTO Contracts (restaurant_id, freelancer_id, service_id, title, description, agreed_price, payment_type, start_date, end_date, status) VALUES
(1, 1, 1, 'Evento de Aniversário', 'Serviço de chef particular para evento de aniversário', 1000.00, 'cartão', '2023-10-15 18:00:00', '2023-10-15 23:00:00', 'concluído'),
(2, 3, 3, 'Limpeza Semanal', 'Serviço de limpeza semanal', 500.00, 'dinheiro', '2023-10-10 08:00:00', '2023-10-10 12:00:00', 'ativo');

-- Inserir dados na tabela Payments
INSERT INTO Payments (contract_id, amount, payment_method, status) VALUES
(1, 1000.00, 'cartão', 'concluído'),
(2, 500.00, 'dinheiro', 'pendente');

-- Inserir dados na tabela Reviews
INSERT INTO Reviews (contract_id, reviewer_id, reviewee_id, overall_rating, comment) VALUES
(1, 2, 1, 5, 'Excelente serviço!'),
(1, 1, 2, 4, 'Ótimo evento!');

-- Inserir dados na tabela Conversations
INSERT INTO Conversations (restaurant_id, freelancer_id) VALUES
(1, 1),
(2, 3);

-- Inserir dados na tabela Messages
INSERT INTO Messages (conversation_id, sender_id, message_text) VALUES
(1, 1, 'Olá, gostaria de contratar seus serviços.'),
(1, 2, 'Olá, ficarei feliz em ajudar. Quando será o evento?'),
(2, 3, 'Bom dia, preciso de um serviço de limpeza.'),
(2, 4, 'Bom dia, qual o horário desejado?');

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