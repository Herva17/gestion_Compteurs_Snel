<?php
require_once __DIR__ . '/Database.php';

class Etalage {
    private $db;
    private $id_etalage;
    private $numero;
    private $localisation;
    private $statut;
    private $id_secteur;
    private $id_commercant;
    private $created_at;
    
    // Données jointes
    private $secteur_nom;
    private $commercant_nom;
    
    /**
     * Constructeur
     */
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Hydrate l'objet
     */
    private function hydrate($data) {
        $this->id_etalage = $data['id_etalage'] ?? null;
        $this->numero = $data['numero'] ?? null;
        $this->localisation = $data['localisation'] ?? null;
        $this->statut = $data['statut'] ?? 'disponible';
        $this->id_secteur = $data['id_secteur'] ?? null;
        $this->id_commercant = $data['id_commercant'] ?? null;
        $this->created_at = $data['created_at'] ?? null;
        $this->secteur_nom = $data['secteur_nom'] ?? null;
        $this->commercant_nom = $data['commercant_nom'] ?? null;
    }
    
    // ============================================
    // MÉTHODES CRUD
    // ============================================
    
    /**
     * Créer un étalage
     */
    public function create($data) {
        // Validation
        if (empty($data['numero'])) {
            return ['success' => false, 'error' => 'Le numéro d\'étalage est requis'];
        }
        if (empty($data['id_secteur'])) {
            return ['success' => false, 'error' => 'Le secteur est requis'];
        }
        
        try {
            // Vérifier si le numéro existe déjà
            if ($this->numeroExists($data['numero'])) {
                return ['success' => false, 'error' => 'Ce numéro d\'étalage existe déjà'];
            }
            
            $sql = "INSERT INTO etalage (numero, localisation, statut, id_secteur, id_commercant) 
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['numero'],
                $data['localisation'] ?? '',
                $data['statut'] ?? 'disponible',
                $data['id_secteur'],
                $data['id_commercant'] ?? null
            ]);
            
            $id = $this->db->lastInsertId();
            
            return [
                'success' => true,
                'message' => 'Étalage créé avec succès',
                'id_etalage' => $id
            ];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => 'Erreur: ' . $e->getMessage()];
        }
    }
    
    /**
     * Récupérer tous les étalages
     */
    public function getAll() {
        try {
            $stmt = $this->db->query("
                SELECT e.*, 
                       s.designation as secteur_nom,
                       u.nom_complet as commercant_nom
                FROM etalage e
                LEFT JOIN secteur s ON e.id_secteur = s.id_secteur
                LEFT JOIN commercant c ON e.id_commercant = c.id_commercant
                LEFT JOIN utilisateurs u ON c.id_user = u.id_user
                ORDER BY e.numero
            ");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Récupérer un étalage par son ID
     */
    public function getById($id_etalage) {
        try {
            $stmt = $this->db->prepare("
                SELECT e.*, 
                       s.designation as secteur_nom,
                       u.nom_complet as commercant_nom
                FROM etalage e
                LEFT JOIN secteur s ON e.id_secteur = s.id_secteur
                LEFT JOIN commercant c ON e.id_commercant = c.id_commercant
                LEFT JOIN utilisateurs u ON c.id_user = u.id_user
                WHERE e.id_etalage = ?
            ");
            $stmt->execute([$id_etalage]);
            $data = $stmt->fetch();
            
            if ($data) {
                $this->hydrate($data);
                return $this;
            }
            return null;
        } catch (PDOException $e) {
            return null;
        }
    }
    
    /**
     * Récupérer les étalages disponibles
     */
    public function getDisponibles() {
        try {
            $stmt = $this->db->query("
                SELECT e.*, s.designation as secteur_nom
                FROM etalage e
                LEFT JOIN secteur s ON e.id_secteur = s.id_secteur
                WHERE e.statut = 'disponible' OR e.id_commercant IS NULL
                ORDER BY e.numero
            ");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Récupérer les étalages occupés
     */
    public function getOccupes() {
        try {
            $stmt = $this->db->query("
                SELECT e.*, 
                       s.designation as secteur_nom,
                       u.nom_complet as commercant_nom
                FROM etalage e
                LEFT JOIN secteur s ON e.id_secteur = s.id_secteur
                INNER JOIN commercant c ON e.id_commercant = c.id_commercant
                INNER JOIN utilisateurs u ON c.id_user = u.id_user
                WHERE e.statut = 'occupe'
                ORDER BY e.numero
            ");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Mettre à jour un étalage
     */
    public function update($id_etalage, $data) {
        try {
            $sql = "UPDATE etalage SET 
                        numero = ?,
                        localisation = ?,
                        statut = ?,
                        id_secteur = ?
                    WHERE id_etalage = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['numero'],
                $data['localisation'] ?? '',
                $data['statut'] ?? 'disponible',
                $data['id_secteur'],
                $id_etalage
            ]);
            
            return ['success' => true, 'message' => 'Étalage mis à jour avec succès'];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => 'Erreur: ' . $e->getMessage()];
        }
    }
    
    /**
     * Supprimer un étalage
     */
    public function delete($id_etalage) {
        try {
            // Vérifier si l'étalage a des locations
            $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM location WHERE id_etalage = ?");
            $stmt->execute([$id_etalage]);
            $result = $stmt->fetch();
            
            if ($result['total'] > 0) {
                return ['success' => false, 'error' => 'Cet étalage a des locations. Supprimez-les d\'abord.'];
            }
            
            $stmt = $this->db->prepare("DELETE FROM etalage WHERE id_etalage = ?");
            $stmt->execute([$id_etalage]);
            
            return ['success' => true, 'message' => 'Étalage supprimé avec succès'];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => 'Erreur: ' . $e->getMessage()];
        }
    }
    
    // ============================================
    // MÉTHODES DE GESTION
    // ============================================
    
    /**
     * Attribuer un étalage à un commerçant
     */
    public function attribuer($id_etalage, $id_commercant) {
        try {
            // Vérifier si l'étalage est disponible
            $stmt = $this->db->prepare("SELECT statut FROM etalage WHERE id_etalage = ?");
            $stmt->execute([$id_etalage]);
            $etalage = $stmt->fetch();
            
            if (!$etalage || ($etalage['statut'] !== 'disponible' && $etalage['statut'] !== null)) {
                return ['success' => false, 'error' => 'Cet étalage n\'est pas disponible'];
            }
            
            $stmt = $this->db->prepare("UPDATE etalage SET statut = 'occupe', id_commercant = ? WHERE id_etalage = ?");
            $stmt->execute([$id_commercant, $id_etalage]);
            
            return ['success' => true, 'message' => 'Étalage attribué avec succès'];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => 'Erreur: ' . $e->getMessage()];
        }
    }
    
    /**
     * Libérer un étalage
     */
    public function liberer($id_etalage) {
        try {
            $stmt = $this->db->prepare("UPDATE etalage SET statut = 'disponible', id_commercant = NULL WHERE id_etalage = ?");
            $stmt->execute([$id_etalage]);
            
            return ['success' => true, 'message' => 'Étalage libéré avec succès'];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => 'Erreur: ' . $e->getMessage()];
        }
    }
    
    /**
     * Vérifier si un numéro d'étalage existe
     */
    private function numeroExists($numero) {
        $stmt = $this->db->prepare("SELECT id_etalage FROM etalage WHERE numero = ?");
        $stmt->execute([$numero]);
        return $stmt->fetch() !== false;
    }
    
    /**
     * Obtenir les statistiques des étalages
     */
    public function getStats() {
        try {
            $stats = [];
            
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM etalage");
            $stats['total'] = $stmt->fetch()['total'];
            
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM etalage WHERE statut = 'disponible' OR statut IS NULL");
            $stats['disponibles'] = $stmt->fetch()['total'];
            
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM etalage WHERE statut = 'occupe'");
            $stats['occupes'] = $stmt->fetch()['total'];
            
            return $stats;
        } catch (PDOException $e) {
            return ['total' => 0, 'disponibles' => 0, 'occupes' => 0];
        }
    }
    
    // ============================================
    // GETTERS
    // ============================================
    
    public function getIdEtalage() { return $this->id_etalage; }
    public function getNumero() { return $this->numero; }
    public function getLocalisation() { return $this->localisation; }
    public function getStatut() { return $this->statut; }
    public function getIdSecteur() { return $this->id_secteur; }
    public function getIdCommercant() { return $this->id_commercant; }
    public function getSecteurNom() { return $this->secteur_nom; }
    public function getCommercantNom() { return $this->commercant_nom; }
    public function getCreatedAt() { return $this->created_at; }
    
    public function toArray() {
        return [
            'id_etalage' => $this->id_etalage,
            'numero' => $this->numero,
            'localisation' => $this->localisation,
            'statut' => $this->statut,
            'id_secteur' => $this->id_secteur,
            'id_commercant' => $this->id_commercant,
            'secteur_nom' => $this->secteur_nom,
            'commercant_nom' => $this->commercant_nom,
            'created_at' => $this->created_at
        ];
    }
}