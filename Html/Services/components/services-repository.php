<?php
/**
 * Repositório de Serviços - Responsável por todas as consultas relacionadas a serviços
 */
class ServicesRepository {
    private $db;
    
    /**
     * Construtor
     */
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Constrói a consulta SQL base
     * 
     * @return string Consulta SQL base
     */
    private function buildBaseQuery() {
        return "SELECT 
                s.service_id,
                s.title AS service_title,
                s.description AS service_description,
                s.base_price,
                s.price_type,
                s.service_image_url,
                s.freelancer_id,
                u.first_name,
                u.last_name,
                u.profile_image_url,
                fp.experience_years,
                fp.availability,
                fp.availability_details,
                (SELECT AVG(r.overall_rating) FROM Reviews r 
                 JOIN Contracts c ON r.contract_id = c.contract_id 
                 WHERE c.service_id = s.service_id) AS avg_rating,
                (SELECT COUNT(r.review_id) FROM Reviews r 
                 JOIN Contracts c ON r.contract_id = c.contract_id 
                 WHERE c.service_id = s.service_id) AS review_count
            FROM 
                Services s
            JOIN 
                FreelancerProfiles fp ON s.freelancer_id = fp.profile_id
            JOIN 
                Users u ON fp.user_id = u.user_id
            LEFT JOIN 
                FreelancerSkills fs ON fp.profile_id = fs.freelancer_id
            LEFT JOIN 
                Skills sk ON fs.skill_id = sk.skill_id
            WHERE 
                s.is_active = 1";
    }
    
    /**
     * Adiciona filtros à consulta SQL
     * 
     * @param string $query Consulta SQL inicial
     * @param array $params Parâmetros de filtro
     * @param array &$queryParams Array para armazenar parâmetros da consulta
     * @return string Consulta SQL com filtros
     */
    private function addFiltersToQuery($query, $params, &$queryParams) {
        $experienceMin = $params['experienceMin'];
        $priceMin = $params['priceMin'];
        $priceMax = $params['priceMax'];
        $availability = $params['availability'];
        $search = $params['search'];
        
        if ($experienceMin > 0) {
            $query .= " AND fp.experience_years >= :exp_min";
            $queryParams[':exp_min'] = $experienceMin;
        }
        
        if ($priceMin !== null) {
            $query .= " AND s.base_price >= :price_min";
            $queryParams[':price_min'] = $priceMin;
        }
        
        if ($priceMax !== null) {
            $query .= " AND s.base_price <= :price_max";
            $queryParams[':price_max'] = $priceMax;
        }
        
        // Tratamento para disponibilidade
        $availabilityParams = [];
        if (!empty($availability)) {
            $availabilityConditions = [];
            foreach ($availability as $index => $time) {
                $paramName = ":availability_" . $index;
                $availabilityConditions[] = "fp.availability LIKE " . $paramName;
                $availabilityParams[$paramName] = "%" . trim($time) . "%";
                $queryParams[$paramName] = "%" . trim($time) . "%";
            }
            if (!empty($availabilityConditions)) {
                $query .= " AND (" . implode(" OR ", $availabilityConditions) . ")";
            }
        }
        
        if (!empty($search)) {
            $query .= " AND (s.title LIKE :search OR s.description LIKE :search OR sk.skill_name LIKE :search)";
            $queryParams[':search'] = "%$search%";
        }
        
        return $query;
    }
    
    /**
     * Adiciona ordenação à consulta SQL
     * 
     * @param string $query Consulta SQL
     * @param string $sort Critério de ordenação
     * @param string $search Termo de busca
     * @param array &$queryParams Parâmetros da consulta
     * @return string Consulta SQL com ordenação
     */
    private function addSortingToQuery($query, $sort, $search, &$queryParams) {
        switch ($sort) {
            case 'rating':
                $query .= " ORDER BY avg_rating DESC, review_count DESC";
                break;
            case 'price-asc':
                $query .= " ORDER BY s.base_price ASC";
                break;
            case 'price-desc':
                $query .= " ORDER BY s.base_price DESC";
                break;
            case 'experience':
                $query .= " ORDER BY fp.experience_years DESC";
                break;
            case 'relevance':
            default:
                if (!empty($search)) {
                    $query .= " ORDER BY 
                        CASE WHEN s.title LIKE :search_order THEN 1
                            WHEN s.description LIKE :search_order THEN 2
                            ELSE 3 END,
                        avg_rating DESC";
                    $queryParams[':search_order'] = "%$search%";
                } else {
                    $query .= " ORDER BY avg_rating DESC, review_count DESC";
                }
                break;
        }
        
        return $query;
    }
    
    /**
     * Conta o total de serviços com os filtros aplicados
     * 
     * @param array $params Parâmetros de filtro
     * @return int Total de serviços
     */
    public function countServices($params) {
        $countQuery = "SELECT COUNT(DISTINCT s.service_id) as total FROM 
            Services s
        JOIN 
            FreelancerProfiles fp ON s.freelancer_id = fp.profile_id
        JOIN 
            Users u ON fp.user_id = u.user_id
        LEFT JOIN 
            FreelancerSkills fs ON fp.profile_id = fs.freelancer_id
        LEFT JOIN 
            Skills sk ON fs.skill_id = sk.skill_id
        WHERE 
            s.is_active = 1";
        
        $queryParams = [];
        $countQuery = $this->addFiltersToQuery($countQuery, $params, $queryParams);
        
        $countStmt = $this->db->prepare($countQuery);
        
        foreach ($queryParams as $key => $value) {
            $countStmt->bindValue($key, $value);
        }
        
        $countStmt->execute();
        return $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    }
    
    /**
     * Busca serviços com base nos parâmetros de filtro
     * 
     * @param array $params Parâmetros de filtro
     * @return array Lista de serviços
     */
    public function getServices($params) {
        $perPage = ITEMS_PER_PAGE;
        $page = $params['page'];
        $offset = ($page - 1) * $perPage;
        
        $query = $this->buildBaseQuery();
        
        $queryParams = [];
        $query = $this->addFiltersToQuery($query, $params, $queryParams);
        
        // Agrupar resultados por usuário e serviço
        $query .= " GROUP BY s.service_id";
        
        // Adicionar ordenação
        $query = $this->addSortingToQuery($query, $params['sort'], $params['search'], $queryParams);
        
        // Adicionar limites para paginação
        $query .= " LIMIT :limit OFFSET :offset";
        $queryParams[':limit'] = $perPage;
        $queryParams[':offset'] = $offset;
        
        $stmt = $this->db->prepare($query);
        
        foreach ($queryParams as $key => $value) {
            // Bind com o tipo correto
            if (is_int($value)) {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /*--------------------------------------------------------------------------------------------------------------------------------------------------------------
    ----------------------------------------------------------------------------------------------------------------------------------------------------------------
    ----------------------------------------------------------------------------------------------------------------------------------------------------------------
                                                                     Service-Details
    ----------------------------------------------------------------------------------------------------------------------------------------------------------------
    ----------------------------------------------------------------------------------------------------------------------------------------------------------------
    --------------------------------------------------------------------------------------------------------------------------------------------------------------*/



    /**
     * Obtém os detalhes completos de um serviço específico
     * 
     * @param int $serviceId ID do serviço
     * @return array|false Detalhes do serviço ou false se não encontrado
     */
    public function getServiceDetails($serviceId) {
        $stmt = $this->db->prepare("
            SELECT
                s.service_id, s.title AS service_title, s.description AS service_description,
                s.price_type, s.base_price, s.service_image_url, s.created_at AS service_created,
                s.category_id, 
                fp.profile_id AS freelancer_id, fp.hourly_rate, fp.availability, fp.experience_years,
                fp.avg_rating, fp.review_count, fp.availability_details,
                u.user_id, u.first_name, u.last_name, u.email, u.profile_image_url, u.country, u.city,
                sc.name AS category_name
            FROM Services s
            JOIN FreelancerProfiles fp ON s.freelancer_id = fp.profile_id
            JOIN Users u ON fp.user_id = u.user_id
            JOIN ServiceCategories sc ON s.category_id = sc.category_id
            WHERE s.service_id = ? AND s.is_active = 1
        ");
        
        $stmt->execute([$serviceId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtém habilidades relevantes para um serviço específico
     * 
     * @param int $freelancerId ID do freelancer
     * @param int $categoryId ID da categoria
     * @return array Lista de habilidades
     */
    public function getServiceRelatedSkills($freelancerId, $categoryId) {
        $stmt = $this->db->prepare("
            SELECT s.skill_name, fs.proficiency_level
            FROM FreelancerSkills fs
            JOIN Skills s ON fs.skill_id = s.skill_id
            WHERE fs.freelancer_id = ?
            AND s.skill_id IN (
                -- Subquery para encontrar habilidades geralmente relacionadas a esta categoria 
                -- baseado em padrões históricos de oferta de serviços
                SELECT DISTINCT fs2.skill_id
                FROM Services sv
                JOIN FreelancerSkills fs2 ON sv.freelancer_id = fs2.freelancer_id
                WHERE sv.category_id = ?
            )
        ");
        
        $stmt->execute([$freelancerId, $categoryId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtém idiomas do freelancer
     * 
     * @param int $freelancerId ID do freelancer
     * @return array Lista de idiomas
     */
    public function getFreelancerLanguages($freelancerId) {
        $stmt = $this->db->prepare("
            SELECT l.language_name, fl.proficiency
            FROM FreelancerLanguages fl
            JOIN Languages l ON fl.language_id = l.language_id
            WHERE fl.freelancer_id = ?
        ");
        
        $stmt->execute([$freelancerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtém especializações do freelancer baseado na categoria
     * 
     * @param int $freelancerId ID do freelancer
     * @param string $categoryName Nome da categoria
     * @return array Especializações do freelancer
     */
    public function getFreelancerSpecializations($freelancerId, $categoryName) {
        $specializations = [];
        
        // Verificar qual especialização está relacionada à categoria do serviço atual
        switch (strtolower($categoryName ?? '')) {
            case 'chef':
            case 'culinária':
            case 'cozinha':
                $stmt = $this->db->prepare("SELECT * FROM ChefSpecializations WHERE freelancer_id = ?");
                $stmt->execute([$freelancerId]);
                $chefSpecs = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($chefSpecs) {
                    $specializations['chef'] = $chefSpecs;
                }
                break;
                
            case 'limpeza':
            case 'higienização':
                $stmt = $this->db->prepare("SELECT * FROM CleaningSpecializations WHERE freelancer_id = ?");
                $stmt->execute([$freelancerId]);
                $cleaningSpecs = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($cleaningSpecs) {
                    $specializations['cleaning'] = $cleaningSpecs;
                }
                break;
                
            case 'bar':
            case 'bartender':
            case 'bebidas':
                $stmt = $this->db->prepare("SELECT * FROM BartenderSpecializations WHERE freelancer_id = ?");
                $stmt->execute([$freelancerId]);
                $bartenderSpecs = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($bartenderSpecs) {
                    $specializations['bartender'] = $bartenderSpecs;
                }
                break;
                
            case 'atendimento':
            case 'garçom':
            case 'serviço':
                $stmt = $this->db->prepare("SELECT * FROM ServiceStaffSpecializations WHERE freelancer_id = ?");
                $stmt->execute([$freelancerId]);
                $serviceStaffSpecs = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($serviceStaffSpecs) {
                    $specializations['service_staff'] = $serviceStaffSpecs;
                }
                break;
        }
        
        return $specializations;
    }
    
    /**
     * Obtém avaliações para um serviço específico
     * 
     * @param int $serviceId ID do serviço
     * @param int $freelancerId ID do freelancer
     * @param int $limit Limite de avaliações a serem retornadas
     * @return array Lista de avaliações
     */
    public function getServiceReviews($serviceId, $freelancerId, $limit = 10) {
        $stmt = $this->db->prepare("
            SELECT 
                r.review_id, r.overall_rating, r.comment, r.created_at,
                u.first_name, u.last_name, u.profile_image_url,
                c.contract_id, c.title AS contract_title
            FROM Reviews r
            JOIN Contracts c ON r.contract_id = c.contract_id
            JOIN Users u ON r.reviewer_id = u.user_id
            JOIN RestaurantProfiles rp ON u.user_id = rp.user_id
            WHERE c.service_id = ? -- Filtrando especificamente por este serviço
            AND r.reviewee_id = (SELECT user_id FROM FreelancerProfiles WHERE profile_id = ?)
            ORDER BY r.created_at DESC
            LIMIT ?
        ");
        
        $stmt->execute([$serviceId, $freelancerId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Calcula estatísticas de avaliações para um serviço
     * 
     * @param array $reviews Lista de avaliações
     * @return array Estatísticas de avaliações
     */
    public function calculateServiceRatingStats($reviews) {
        $serviceStats = [
            'avg_rating' => 0,
            'total_reviews' => count($reviews),
            'rating_distribution' => [
                '5' => 0,
                '4' => 0,
                '3' => 0,
                '2' => 0, 
                '1' => 0
            ]
        ];
        
        if (count($reviews) > 0) {
            $totalRating = 0;
            foreach ($reviews as $review) {
                $totalRating += $review['overall_rating'];
                $serviceStats['rating_distribution'][$review['overall_rating']]++;
            }
            $serviceStats['avg_rating'] = $totalRating / count($reviews);
        }
        
        return $serviceStats;
    }
    
    /**
     * Verifica se um usuário é dono de um serviço
     * 
     * @param int $serviceId ID do serviço
     * @param int $userId ID do usuário
     * @return bool True se o usuário for dono, False caso contrário
     */
    public function isServiceOwner($serviceId, $userId) {
        $stmt = $this->db->prepare("
            SELECT 1 FROM FreelancerProfiles fp
            JOIN Services s ON fp.profile_id = s.freelancer_id
            WHERE s.service_id = ? AND fp.user_id = ?
        ");
        
        $stmt->execute([$serviceId, $userId]);
        return $stmt->fetchColumn() ? true : false;
    }

    /**
     * Obtém serviços semelhantes com base na categoria
     *
     * @param int $categoryId ID da categoria
     * @param int $serviceId ID do serviço atual (para excluir da busca)
     * @param int $limit Limite de serviços a serem retornados
     * @return array Lista de serviços semelhantes
     */
    public function getSimilarServices($categoryId, $serviceId, $limit = 4) {
        $stmt = $this->db->prepare("
            SELECT
                s.service_id, s.title, s.base_price, s.price_type, s.service_image_url,
                u.first_name, u.last_name, u.profile_image_url,
                fp.avg_rating, fp.review_count, fp.profile_id
            FROM Services s
            JOIN FreelancerProfiles fp ON s.freelancer_id = fp.profile_id
            JOIN Users u ON fp.user_id = u.user_id
            WHERE s.category_id = ?
            AND s.service_id != ?
            AND s.is_active = 1
            ORDER BY fp.avg_rating DESC
            LIMIT ?
        ");

        $stmt->execute([$categoryId, $serviceId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}