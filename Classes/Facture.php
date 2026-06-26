<?php
require_once __DIR__ . '/Database.php';

class Facture {
    private $db;
    private $idFacture;
    private $numeroFacture;
    private $dateEmission;
    private $dateEcheance;
    private $dateLimitePaiement;
    private $datePaiementReel;
    private $montantTotal;
    private $montantHT;
    private $montantTVA;
    private $tauxTVA;
    private $montantPenalite;
    private $montantReduction;
    private $remise;
    private $statut;
    private $typeFacture;
    private $periodeConsoDebut;
    private $periodeConsoFin;
    private $description;
    private $idClient;
    private $idConsommation;
    private $idAgentCreation;
    private $dateCreation;
    private $dateModification;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    private function hydrate($data) {
        $this->idFacture = $data['idFacture'] ?? null;
        $this->numeroFacture = $data['numeroFacture'] ?? null;
        $this->dateEmission = $data['dateEmission'] ?? null;
        $this->dateEcheance = $data['dateEcheance'] ?? null;
        $this->dateLimitePaiement = $data['dateLimitePaiement'] ?? null;
        $this->datePaiementReel = $data['datePaiementReel'] ?? null;
        $this->montantTotal = $data['montantTotal'] ?? null;
        $this->montantHT = $data['montantHT'] ?? null;
        $this->montantTVA = $data['montantTVA'] ?? null;
        $this->tauxTVA = $data['tauxTVA'] ?? 18.0;
        $this->montantPenalite = $data['montantPenalite'] ?? 0;
        $this->montantReduction = $data['montantReduction'] ?? 0;
        $this->remise = $data['remise'] ?? 0;
        $this->statut = $data['statut'] ?? 'en_attente';
        $this->typeFacture = $data['typeFacture'] ?? 'normale';
        $this->periodeConsoDebut = $data['periodeConsoDebut'] ?? null;
        $this->periodeConsoFin = $data['periodeConsoFin'] ?? null;
        $this->description = $data['description'] ?? null;
        $this->idClient = $data['idClient'] ?? null;
        $this->idConsommation = $data['idConsommation'] ?? null;
        $this->idAgentCreation = $data['idAgentCreation'] ?? null;
        $this->dateCreation = $data['dateCreation'] ?? null;
        $this->dateModification = $data['dateModification'] ?? null;
    }
    
    /**
     * Enregistrer une nouvelle facture
     */
    public function enregistrer($data) {
        $errors = $this->validateData($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }
        
        try {
            // Générer un numéro de facture unique
            $numeroFacture = $this->genererNumeroFacture();
            
            // Calculer les montants
            $montantHT = $data['montantTotal'] / (1 + ($data['tauxTVA'] ?? 18) / 100);
            $montantTVA = $data['montantTotal'] - $montantHT;
            
            $sql = "INSERT INTO Facture (
                numeroFacture, dateEmission, dateEcheance, dateLimitePaiement,
                montantTotal, montantHT, montantTVA, tauxTVA,
                montantPenalite, montantReduction, remise, statut,
                typeFacture, periodeConsoDebut, periodeConsoFin,
                description, idClient, idConsommation, idAgentCreation
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $numeroFacture,
                $data['dateEmission'],
                $data['dateEcheance'],
                $data['dateLimitePaiement'],
                $data['montantTotal'],
                $montantHT,
                $montantTVA,
                $data['tauxTVA'] ?? 18.0,
                $data['montantPenalite'] ?? 0,
                $data['montantReduction'] ?? 0,
                $data['remise'] ?? 0,
                $data['statut'] ?? 'en_attente',
                $data['typeFacture'] ?? 'normale',
                $data['periodeConsoDebut'] ?? null,
                $data['periodeConsoFin'] ?? null,
                $data['description'] ?? null,
                $data['idClient'],
                $data['idConsommation'] ?? null,
                $data['idAgentCreation'] ?? null
            ]);
            
            $factureId = $this->db->lastInsertId();
            
            return [
                'success' => true,
                'message' => 'Facture enregistrée avec succès',
                'idFacture' => $factureId,
                'numeroFacture' => $numeroFacture
            ];
            
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Générer un numéro de facture unique
     */
    private function genererNumeroFacture() {
        $annee = date('Y');
        $mois = date('m');
        $prefix = 'FAC-' . $annee . $mois . '-';
        
        $stmt = $this->db->prepare("SELECT MAX(CAST(SUBSTRING(numeroFacture, -6) AS UNSIGNED)) as last_num 
                                   FROM Facture WHERE numeroFacture LIKE ?");
        $stmt->execute([$prefix . '%']);
        $result = $stmt->fetch();
        
        $lastNum = $result['last_num'] ?? 0;
        $newNum = str_pad($lastNum + 1, 6, '0', STR_PAD_LEFT);
        
        return $prefix . $newNum;
    }
    
    /**
     * Récupérer toutes les factures
     */
    public function getAll() {
        try {
            $stmt = $this->db->query("
                SELECT f.*, CONCAT(c.nom, ' ', c.prenom) as client_nom
                FROM Facture f
                LEFT JOIN Client c ON f.idClient = c.idClient
                ORDER BY f.dateEmission DESC
            ");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Récupérer les factures d'un client
     */
    public function getByClient($idClient) {
        try {
            $stmt = $this->db->prepare("
                SELECT f.*, CONCAT(c.nom, ' ', c.prenom) as client_nom
                FROM Facture f
                JOIN Client c ON f.idClient = c.idClient
                WHERE f.idClient = ?
                ORDER BY f.dateEmission DESC
            ");
            $stmt->execute([$idClient]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Récupérer une facture par ID
     */
    public function getById($id) {
        try {
            $stmt = $this->db->prepare("
                SELECT f.*, CONCAT(c.nom, ' ', c.prenom) as client_nom,
                       c.email as client_email, c.telephone as client_telephone
                FROM Facture f
                LEFT JOIN Client c ON f.idClient = c.idClient
                WHERE f.idFacture = ?
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
     * Récupérer une facture par numéro
     */
    public function getByNumero($numero) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM Facture WHERE numeroFacture = ?");
            $stmt->execute([$numero]);
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
     * Mettre à jour le statut d'une facture
     */
    public function modifierStatut($id, $statut) {
        try {
            $stmt = $this->db->prepare("UPDATE Facture SET statut = ? WHERE idFacture = ?");
            $stmt->execute([$statut, $id]);
            
            // Si la facture est payée, mettre à jour la date de paiement
            if ($statut === 'payee') {
                $stmt = $this->db->prepare("UPDATE Facture SET datePaiementReel = NOW() WHERE idFacture = ?");
                $stmt->execute([$id]);
            }
            
            return ['success' => true, 'message' => 'Statut mis à jour avec succès'];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Vérifier les factures en retard
     */
    public function verifierFacturesEnRetard() {
        try {
            $stmt = $this->db->prepare("
                UPDATE Facture 
                SET statut = 'en_retard' 
                WHERE dateLimitePaiement < CURDATE() 
                AND statut = 'en_attente'
            ");
            $stmt->execute();
            return ['success' => true, 'updated' => $stmt->rowCount()];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Obtenir le total des factures impayées d'un client
     */
    public function getTotalImpaye($idClient) {
        try {
            $stmt = $this->db->prepare("
                SELECT SUM(montantTotal) as total
                FROM Facture 
                WHERE idClient = ? AND statut IN ('en_attente', 'en_retard')
            ");
            $stmt->execute([$idClient]);
            $result = $stmt->fetch();
            return $result['total'] ?? 0;
        } catch (PDOException $e) {
            return 0;
        }
    }
    
    /**
     * Calculer les pénalités pour une facture
     */
    public function calculerPenalites($idFacture) {
        try {
            $facture = $this->getById($idFacture);
            if (!$facture) {
                return ['success' => false, 'error' => 'Facture non trouvée'];
            }
            
            if ($facture->statut === 'payee') {
                return ['success' => true, 'penalites' => 0, 'message' => 'Facture déjà payée'];
            }
            
            $dateLimite = new DateTime($facture->dateLimitePaiement);
            $aujourdhui = new DateTime();
            
            if ($aujourdhui <= $dateLimite) {
                return ['success' => true, 'penalites' => 0, 'message' => 'Pas de pénalités'];
            }
            
            $joursRetard = $dateLimite->diff($aujourdhui)->days;
            $tauxPenalite = 0.01; // 1% par jour
            $penalites = $facture->montantTotal * $tauxPenalite * $joursRetard;
            
            return [
                'success' => true,
                'jours_retard' => $joursRetard,
                'penalites' => round($penalites, 2)
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Supprimer une facture
     */
    public function supprimer($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM Facture WHERE idFacture = ?");
            $stmt->execute([$id]);
            return ['success' => true, 'message' => 'Facture supprimée avec succès'];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    // Méthodes privées
    private function validateData($data) {
        $errors = [];
        if (empty($data['dateEmission'])) $errors['dateEmission'] = 'La date d\'émission est requise';
        if (empty($data['dateEcheance'])) $errors['dateEcheance'] = 'La date d\'échéance est requise';
        if (empty($data['dateLimitePaiement'])) $errors['dateLimitePaiement'] = 'La date limite de paiement est requise';
        if (empty($data['montantTotal']) || $data['montantTotal'] <= 0) $errors['montantTotal'] = 'Le montant total est invalide';
        if (empty($data['idClient'])) $errors['idClient'] = 'Le client est requis';
        return $errors;
    }
    
    // Getters
    public function getIdFacture() { return $this->idFacture; }
    public function getNumeroFacture() { return $this->numeroFacture; }
    public function getMontantTotal() { return $this->montantTotal; }
    public function getStatut() { return $this->statut; }
    public function getDateLimitePaiement() { return $this->dateLimitePaiement; }
    
    public function toArray() {
        return [
            'idFacture' => $this->idFacture,
            'numeroFacture' => $this->numeroFacture,
            'dateEmission' => $this->dateEmission,
            'dateEcheance' => $this->dateEcheance,
            'dateLimitePaiement' => $this->dateLimitePaiement,
            'datePaiementReel' => $this->datePaiementReel,
            'montantTotal' => $this->montantTotal,
            'montantHT' => $this->montantHT,
            'montantTVA' => $this->montantTVA,
            'tauxTVA' => $this->tauxTVA,
            'montantPenalite' => $this->montantPenalite,
            'montantReduction' => $this->montantReduction,
            'remise' => $this->remise,
            'statut' => $this->statut,
            'typeFacture' => $this->typeFacture,
            'description' => $this->description,
            'idClient' => $this->idClient
        ];
    }
}
?>