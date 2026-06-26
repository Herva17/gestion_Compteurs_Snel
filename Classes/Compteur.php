<?php
require_once __DIR__ . '/Database.php';

class Compteur {
    private $db;
    private $idCompteur;
    private $numeroSerie;
    private $dateInstallation;
    private $dateDerniereVerification;
    private $prochaineVerification;
    private $etat;
    private $indexActuel;
    private $typeCompteur;
    private $marque;
    private $modele;
    private $capacite;
    private $tension;
    private $emplacement;
    private $coordonneesGPS;
    private $idClient;
    private $dateCreation;
    private $dateModification;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    private function hydrate($data) {
        $this->idCompteur = $data['idCompteur'] ?? null;
        $this->numeroSerie = $data['numeroSerie'] ?? null;
        $this->dateInstallation = $data['dateInstallation'] ?? null;
        $this->dateDerniereVerification = $data['dateDerniereVerification'] ?? null;
        $this->prochaineVerification = $data['prochaineVerification'] ?? null;
        $this->etat = $data['etat'] ?? 'actif';
        $this->indexActuel = $data['indexActuel'] ?? null;
        $this->typeCompteur = $data['typeCompteur'] ?? 'monophase';
        $this->marque = $data['marque'] ?? null;
        $this->modele = $data['modele'] ?? null;
        $this->capacite = $data['capacite'] ?? null;
        $this->tension = $data['tension'] ?? null;
        $this->emplacement = $data['emplacement'] ?? null;
        $this->coordonneesGPS = $data['coordonneesGPS'] ?? null;
        $this->idClient = $data['idClient'] ?? null;
        $this->dateCreation = $data['dateCreation'] ?? null;
        $this->dateModification = $data['dateModification'] ?? null;
    }
    
    /**
     * Enregistrer un nouveau compteur
     */
    public function enregistrer($data) {
        $errors = $this->validateData($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }
        
        try {
            if ($this->numeroSerieExists($data['numeroSerie'])) {
                return ['success' => false, 'errors' => ['numeroSerie' => 'Ce numéro de série existe déjà']];
            }
            
            $sql = "INSERT INTO Compteur (
                numeroSerie, dateInstallation, dateDerniereVerification,
                prochaineVerification, etat, indexActuel, typeCompteur,
                marque, modele, capacite, tension, emplacement,
                coordonneesGPS, idClient
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['numeroSerie'],
                $data['dateInstallation'],
                $data['dateDerniereVerification'] ?? null,
                $data['prochaineVerification'] ?? null,
                $data['etat'] ?? 'actif',
                $data['indexActuel'] ?? '0',
                $data['typeCompteur'] ?? 'monophase',
                $data['marque'] ?? null,
                $data['modele'] ?? null,
                $data['capacite'] ?? null,
                $data['tension'] ?? null,
                $data['emplacement'] ?? null,
                $data['coordonneesGPS'] ?? null,
                $data['idClient'] ?? null
            ]);
            
            return [
                'success' => true,
                'message' => 'Compteur enregistré avec succès',
                'idCompteur' => $this->db->lastInsertId()
            ];
            
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Enregistrer un index de consommation
     */
    public function enregistrerIndex($idCompteur, $nouvelIndex) {
        try {
            // Récupérer l'ancien index
            $stmt = $this->db->prepare("SELECT indexActuel FROM Compteur WHERE idCompteur = ?");
            $stmt->execute([$idCompteur]);
            $compteur = $stmt->fetch();
            
            if (!$compteur) {
                return ['success' => false, 'error' => 'Compteur non trouvé'];
            }
            
            $ancienIndex = $compteur['indexActuel'];
            $consommation = floatval($nouvelIndex) - floatval($ancienIndex);
            
            if ($consommation < 0) {
                return ['success' => false, 'error' => 'Le nouvel index ne peut pas être inférieur à l\'ancien'];
            }
            
            $this->db->beginTransaction();
            
            // Mettre à jour l'index du compteur
            $stmt = $this->db->prepare("UPDATE Compteur SET indexActuel = ? WHERE idCompteur = ?");
            $stmt->execute([$nouvelIndex, $idCompteur]);
            
            // Créer une consommation
            $consommationObj = new Consommation();
            $result = $consommationObj->creer([
                'idCompteur' => $idCompteur,
                'dateDebut' => date('Y-m-d', strtotime('-1 month')),
                'dateFin' => date('Y-m-d'),
                'indexAncien' => $ancienIndex,
                'indexNouveau' => $nouvelIndex,
                'quantiteCons' => $consommation
            ]);
            
            if (!$result['success']) {
                $this->db->rollBack();
                return $result;
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Index enregistré avec succès',
                'consommation' => $consommation
            ];
            
        } catch (PDOException $e) {
            $this->db->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Calculer la consommation pour une période
     */
    public function calculerConsommation($idCompteur, $dateDebut, $dateFin) {
        try {
            $stmt = $this->db->prepare("
                SELECT SUM(quantiteCons) as total 
                FROM Consommation 
                WHERE idCompteur = ? AND dateDebut >= ? AND dateFin <= ?
            ");
            $stmt->execute([$idCompteur, $dateDebut, $dateFin]);
            $result = $stmt->fetch();
            return $result['total'] ?? 0;
        } catch (PDOException $e) {
            return 0;
        }
    }
    
    /**
     * Récupérer tous les compteurs
     */
    public function getAll() {
        try {
            $stmt = $this->db->query("
                SELECT c.*, CONCAT(cl.nom, ' ', cl.prenom) as client_nom
                FROM Compteur c
                LEFT JOIN Client cl ON c.idClient = cl.idClient
                ORDER BY c.dateCreation DESC
            ");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Récupérer les compteurs d'un client
     */
    public function getByClient($idClient) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM Compteur WHERE idClient = ?");
            $stmt->execute([$idClient]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Récupérer un compteur par ID
     */
    public function getById($id) {
        try {
            $stmt = $this->db->prepare("
                SELECT c.*, CONCAT(cl.nom, ' ', cl.prenom) as client_nom
                FROM Compteur c
                LEFT JOIN Client cl ON c.idClient = cl.idClient
                WHERE c.idCompteur = ?
            ");
            $stmt->execute([$id]);
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
     * Mettre à jour un compteur
     */
    public function modifier($id, $data) {
        try {
            $sql = "UPDATE Compteur SET 
                    dateInstallation = ?, dateDerniereVerification = ?,
                    prochaineVerification = ?, etat = ?, typeCompteur = ?,
                    marque = ?, modele = ?, capacite = ?, tension = ?,
                    emplacement = ?, coordonneesGPS = ?, idClient = ?
                    WHERE idCompteur = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['dateInstallation'] ?? null,
                $data['dateDerniereVerification'] ?? null,
                $data['prochaineVerification'] ?? null,
                $data['etat'] ?? 'actif',
                $data['typeCompteur'] ?? 'monophase',
                $data['marque'] ?? null,
                $data['modele'] ?? null,
                $data['capacite'] ?? null,
                $data['tension'] ?? null,
                $data['emplacement'] ?? null,
                $data['coordonneesGPS'] ?? null,
                $data['idClient'] ?? null,
                $id
            ]);
            
            return ['success' => true, 'message' => 'Compteur mis à jour avec succès'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Supprimer un compteur
     */
    public function supprimer($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM Compteur WHERE idCompteur = ?");
            $stmt->execute([$id]);
            return ['success' => true, 'message' => 'Compteur supprimé avec succès'];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Obtenir les consommations d'un compteur
     */
    public function getConsommations() {
        if (!$this->idCompteur) return [];
        try {
            $stmt = $this->db->prepare("SELECT * FROM Consommation WHERE idCompteur = ? ORDER BY dateFin DESC");
            $stmt->execute([$this->idCompteur]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    // Méthodes privées
    private function validateData($data) {
        $errors = [];
        if (empty($data['numeroSerie'])) $errors['numeroSerie'] = 'Le numéro de série est requis';
        if (empty($data['dateInstallation'])) $errors['dateInstallation'] = 'La date d\'installation est requise';
        return $errors;
    }
    
    private function numeroSerieExists($numeroSerie) {
        $stmt = $this->db->prepare("SELECT idCompteur FROM Compteur WHERE numeroSerie = ?");
        $stmt->execute([$numeroSerie]);
        return $stmt->fetch() !== false;
    }
    
    // Getters
    public function getIdCompteur() { return $this->idCompteur; }
    public function getNumeroSerie() { return $this->numeroSerie; }
    public function getEtat() { return $this->etat; }
    public function getIndexActuel() { return $this->indexActuel; }
    public function getIdClient() { return $this->idClient; }
    
    public function toArray() {
        return [
            'idCompteur' => $this->idCompteur,
            'numeroSerie' => $this->numeroSerie,
            'dateInstallation' => $this->dateInstallation,
            'etat' => $this->etat,
            'indexActuel' => $this->indexActuel,
            'typeCompteur' => $this->typeCompteur,
            'marque' => $this->marque,
            'modele' => $this->modele,
            'emplacement' => $this->emplacement,
            'idClient' => $this->idClient
        ];
    }
}
?>