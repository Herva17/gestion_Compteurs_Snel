<?php
require_once __DIR__ . '/Database.php';

class Consommation {
    private $db;
    private $idConsommation;
    private $dateDebut;
    private $dateFin;
    private $indexAncien;
    private $indexNouveau;
    private $quantiteCons;
    private $consommationJournaliere;
    private $consommationMoyenne;
    private $periode;
    private $saison;
    private $observations;
    private $idCompteur;
    private $idAgentReleve;
    private $dateReleve;
    private $dateCreation;
    private $dateModification;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    private function hydrate($data) {
        $this->idConsommation = $data['idConsommation'] ?? null;
        $this->dateDebut = $data['dateDebut'] ?? null;
        $this->dateFin = $data['dateFin'] ?? null;
        $this->indexAncien = $data['indexAncien'] ?? null;
        $this->indexNouveau = $data['indexNouveau'] ?? null;
        $this->quantiteCons = $data['quantiteCons'] ?? null;
        $this->consommationJournaliere = $data['consommationJournaliere'] ?? null;
        $this->consommationMoyenne = $data['consommationMoyenne'] ?? null;
        $this->periode = $data['periode'] ?? null;
        $this->saison = $data['saison'] ?? null;
        $this->observations = $data['observations'] ?? null;
        $this->idCompteur = $data['idCompteur'] ?? null;
        $this->idAgentReleve = $data['idAgentReleve'] ?? null;
        $this->dateReleve = $data['dateReleve'] ?? null;
        $this->dateCreation = $data['dateCreation'] ?? null;
        $this->dateModification = $data['dateModification'] ?? null;
    }
    
    /**
     * Créer une nouvelle consommation
     */
    public function creer($data) {
        $errors = $this->validateData($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }
        
        try {
            // Vérifier que le compteur existe
            $stmt = $this->db->prepare("SELECT idCompteur FROM Compteur WHERE idCompteur = ?");
            $stmt->execute([$data['idCompteur']]);
            if (!$stmt->fetch()) {
                return ['success' => false, 'error' => 'Compteur non trouvé'];
            }
            
            // Calculer les consommations supplémentaires
            $jours = (strtotime($data['dateFin']) - strtotime($data['dateDebut'])) / (60 * 60 * 24);
            if ($jours > 0) {
                $data['consommationJournaliere'] = $data['quantiteCons'] / $jours;
            }
            
            $sql = "INSERT INTO Consommation (
                dateDebut, dateFin, indexAncien, indexNouveau, quantiteCons,
                consommationJournaliere, consommationMoyenne, periode,
                saison, observations, idCompteur, idAgentReleve
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['dateDebut'],
                $data['dateFin'],
                $data['indexAncien'],
                $data['indexNouveau'],
                $data['quantiteCons'],
                $data['consommationJournaliere'] ?? null,
                $data['consommationMoyenne'] ?? null,
                $data['periode'] ?? 'mensuel',
                $data['saison'] ?? null,
                $data['observations'] ?? null,
                $data['idCompteur'],
                $data['idAgentReleve'] ?? null
            ]);
            
            return [
                'success' => true,
                'message' => 'Consommation enregistrée avec succès',
                'idConsommation' => $this->db->lastInsertId()
            ];
            
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Vérifier la consommation
     */
    public function verifier($idConsommation) {
        try {
            $stmt = $this->db->prepare("
                SELECT c.*, cp.indexActuel 
                FROM Consommation c
                JOIN Compteur cp ON c.idCompteur = cp.idCompteur
                WHERE c.idConsommation = ?
            ");
            $stmt->execute([$idConsommation]);
            $data = $stmt->fetch();
            
            if (!$data) {
                return ['success' => false, 'error' => 'Consommation non trouvée'];
            }
            
            // Vérifier si les données sont cohérentes
            $verifications = [
                'index_ok' => floatval($data['indexNouveau']) >= floatval($data['indexAncien']),
                'quantite_ok' => $data['quantiteCons'] > 0,
                'dates_ok' => strtotime($data['dateFin']) >= strtotime($data['dateDebut'])
            ];
            
            $allOk = !in_array(false, $verifications);
            
            return [
                'success' => true,
                'verifications' => $verifications,
                'est_valide' => $allOk
            ];
            
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Récupérer toutes les consommations
     */
    public function getAll() {
        try {
            $stmt = $this->db->query("
                SELECT c.*, cp.numeroSerie, 
                       CONCAT(u.nom, ' ', u.prenom) as agent_nom
                FROM Consommation c
                JOIN Compteur cp ON c.idCompteur = cp.idCompteur
                LEFT JOIN Utilisateurs u ON c.idAgentReleve = u.idUtilisateur
                ORDER BY c.dateReleve DESC
            ");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Récupérer une consommation par ID
     */
    public function getById($id) {
        try {
            $stmt = $this->db->prepare("
                SELECT c.*, cp.numeroSerie, cp.idClient
                FROM Consommation c
                JOIN Compteur cp ON c.idCompteur = cp.idCompteur
                WHERE c.idConsommation = ?
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
     * Récupérer les consommations d'un compteur
     */
    public function getByCompteur($idCompteur) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM Consommation 
                WHERE idCompteur = ? 
                ORDER BY dateFin DESC
            ");
            $stmt->execute([$idCompteur]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Récupérer la consommation mensuelle d'un client
     */
    public function getConsommationMensuelle($idClient, $mois, $annee) {
        try {
            $stmt = $this->db->prepare("
                SELECT SUM(c.quantiteCons) as total
                FROM Consommation c
                JOIN Compteur cp ON c.idCompteur = cp.idCompteur
                WHERE cp.idClient = ? 
                AND MONTH(c.dateFin) = ? 
                AND YEAR(c.dateFin) = ?
            ");
            $stmt->execute([$idClient, $mois, $annee]);
            $result = $stmt->fetch();
            return $result['total'] ?? 0;
        } catch (PDOException $e) {
            return 0;
        }
    }
    
    /**
     * Récupérer la consommation annuelle d'un client
     */
    public function getConsommationAnnuelle($idClient, $annee) {
        try {
            $stmt = $this->db->prepare("
                SELECT MONTH(c.dateFin) as mois, SUM(c.quantiteCons) as total
                FROM Consommation c
                JOIN Compteur cp ON c.idCompteur = cp.idCompteur
                WHERE cp.idClient = ? AND YEAR(c.dateFin) = ?
                GROUP BY MONTH(c.dateFin)
                ORDER BY mois
            ");
            $stmt->execute([$idClient, $annee]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Supprimer une consommation
     */
    public function supprimer($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM Consommation WHERE idConsommation = ?");
            $stmt->execute([$id]);
            return ['success' => true, 'message' => 'Consommation supprimée avec succès'];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    // Méthodes privées
    private function validateData($data) {
        $errors = [];
        if (empty($data['dateDebut'])) $errors['dateDebut'] = 'La date de début est requise';
        if (empty($data['dateFin'])) $errors['dateFin'] = 'La date de fin est requise';
        if (empty($data['indexAncien'])) $errors['indexAncien'] = 'L\'index ancien est requis';
        if (empty($data['indexNouveau'])) $errors['indexNouveau'] = 'L\'index nouveau est requis';
        if (empty($data['quantiteCons']) || $data['quantiteCons'] < 0) $errors['quantiteCons'] = 'La quantité consommée est invalide';
        if (empty($data['idCompteur'])) $errors['idCompteur'] = 'Le compteur est requis';
        return $errors;
    }
    
    // Getters
    public function getIdConsommation() { return $this->idConsommation; }
    public function getQuantiteCons() { return $this->quantiteCons; }
    public function getPeriode() { return $this->periode; }
    
    public function toArray() {
        return [
            'idConsommation' => $this->idConsommation,
            'dateDebut' => $this->dateDebut,
            'dateFin' => $this->dateFin,
            'indexAncien' => $this->indexAncien,
            'indexNouveau' => $this->indexNouveau,
            'quantiteCons' => $this->quantiteCons,
            'consommationJournaliere' => $this->consommationJournaliere,
            'periode' => $this->periode,
            'saison' => $this->saison,
            'observations' => $this->observations,
            'idCompteur' => $this->idCompteur,
            'dateReleve' => $this->dateReleve
        ];
    }
}
?>