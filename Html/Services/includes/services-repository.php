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
                u.first_name,
                u.last_name,
                u.profile_image_url,
                fp.experience_years,
                fp.availability,
                fp.availability_details,
                fp.avg_rating,
                fp.review_count
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
                $query .= " ORDER BY fp.avg_rating DESC, fp.review_count DESC";
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
                        fp.avg_rating DESC";
                    $queryParams[':search_order'] = "%$search%";
                } else {
                    $query .= " ORDER BY fp.avg_rating DESC, fp.review_count DESC";
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
}