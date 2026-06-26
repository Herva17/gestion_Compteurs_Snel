<?php
require_once __DIR__ . '/Database.php';

class Paiement {
    private $db;
    private $idPaiement;
    private $numeroReference;
    private $datePaiement;
    private $dateEnregistrement;
    private $montant;
    private $montantPaye;
    private $monnaie;
    private $modePaiement;
    private $statut;
    private $referenceTransaction;
    private $codeTransaction;
    private $banque;
    private $nomTitulaire;
    private $penalitePayee;
    private $remarques;
    private $idFacture;
    private $idClient;
    private $idAgentEnregistrement;
    private $dateCreation;
    private $dateModification;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    private function hydrate($data) {
        $this->idPaiement = $data['idPaiement'] ?? null;
        $this->numeroReference = $data['numeroReference'] ?? null;
        $this->datePaiement = $data['datePaiement'] ?? null;
        $this->dateEnregistrement = $data['dateEnregistrement'] ?? null;
        $this->montant = $data['montant'] ?? null;
        $this->montantPaye = $data['montantPaye'] ?? null;
        $this->monnaie = $data['monnaie'] ?? 'XAF';
        $this->modePaiement = $data['modePaiement'] ?? null;
        $this->statut = $data['statut'] ?? 'effectue';
        $this->referenceTransaction = $data['referenceTransaction'] ?? null;
        $this->codeTransaction = $data['codeTransaction'] ?? null;
        $this->banque = $data['banque'] ?? null;
        $this->nomTitulaire = $data['nomTitulaire'] ?? null;
        $this->penalitePayee = $data['penalitePayee'] ?? 0;
        $this->remarques = $data['remarques'] ?? null;
        $this->idFacture = $data['idFacture'] ?? null;
        $this->idClient = $data['idClient'] ?? null;
        $this->idAgentEnregistrement = $data['idAgentEnregistrement'] ?? null;
        $this->dateCreation = $data['dateCreation'] ?? null;
        $this->dateModification = $data['dateModification'] ?? null;
    }
    
    /**
     * Enregistrer un paiement
     */
    public function enregistrer($data) {
        $errors = $this->validateData($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }
        
        try {
            $this->db->beginTransaction();
            
            // Vérifier que la facture existe et n'est pas déjà payée
            $stmt = $this->db->prepare("SELECT idFacture, montantTotal, statut FROM Facture WHERE idFacture = ?");
            $stmt->execute([$data['idFacture']]);
            $facture = $stmt->fetch();
            
            if (!$facture) {
                return ['success' => false, 'error' => 'Facture non trouvée'];
            }
            
            if ($facture['statut'] === 'payee') {
                return ['success' => false, 'error' => 'Cette facture est déjà payée'];
            }
            
            // Générer un numéro de référence unique
            $numeroReference = $this->genererNumeroReference();
            
            // Enregistrer le paiement
            $sql = "INSERT INTO Paiement (
                numeroReference, datePaiement, montant, montantPaye,
                monnaie, modePaiement, statut, referenceTransaction,
                codeTransaction, banque, nomTitulaire, penalitePayee,
                remarques, idFacture, idClient, idAgentEnregistrement
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $numeroReference,
                $data['datePaiement'],
                $data['montant'],
                $data['montant'] ?? null,
                $data['monnaie'] ?? 'XAF',
                $data['modePaiement'],
                $data['statut'] ?? 'effectue',
                $data['referenceTransaction'] ?? null,
                $data['codeTransaction'] ?? null,
                $data['banque'] ?? null,
                $data['nomTitulaire'] ?? null,
                $data['penalitePayee'] ?? 0,
                $data['remarques'] ?? null,
                $data['idFacture'],
                $data['idClient'] ?? null,
                $data['idAgentEnregistrement'] ?? null
            ]);
            
            $paiementId = $this->db->lastInsertId();
            
            // Mettre à jour le statut de la facture
            if ($data['statut'] === 'effectue') {
                $stmt = $this->db->prepare("
                    UPDATE Facture 
                    SET statut = 'payee', datePaiementReel = NOW() 
                    WHERE idFacture = ?
                ");
                $stmt->execute([$data['idFacture']]);
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Paiement enregistré avec succès',
                'idPaiement' => $paiementId,
                'numeroReference' => $numeroReference
            ];
            
        } catch (PDOException $e) {
            $this->db->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Modifier un paiement
     */
    public function modifier($id, $data) {
        try {
            $sql = "UPDATE Paiement SET 
                    datePaiement = ?, montant = ?, modePaiement = ?,
                    statut = ?, referenceTransaction = ?, codeTransaction = ?,
                    banque = ?, nomTitulaire = ?, penalitePayee = ?,
                    remarques = ?
                    WHERE idPaiement = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['datePaiement'] ?? null,
                $data['montant'] ?? null,
                $data['modePaiement'] ?? null,
                $data['statut'] ?? 'effectue',
                $data['referenceTransaction'] ?? null,
                $data['codeTransaction'] ?? null,
                $data['banque'] ?? null,
                $data['nomTitulaire'] ?? null,
                $data['penalitePayee'] ?? 0,
                $data['remarques'] ?? null,
                $id
            ]);
            
            return ['success' => true, 'message' => 'Paiement modifié avec succès'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Générer un numéro de référence unique
     */
    private function genererNumeroReference() {
        $prefix = 'PAY-' . date('Ymd') . '-';
        $stmt = $this->db->prepare("SELECT MAX(CAST(SUBSTRING(numeroReference, -4) AS UNSIGNED)) as last_num 
                                   FROM Paiement WHERE numeroReference LIKE ?");
        $stmt->execute([$prefix . '%']);
        $result = $stmt->fetch();
        
        $lastNum = $result['last_num'] ?? 0;
        $newNum = str_pad($lastNum + 1, 4, '0', STR_PAD_LEFT);
        
        return $prefix . $newNum;
    }
    
    /**
     * Récupérer tous les paiements
     */
    public function getAll() {
        try {
            $stmt = $this->db->query("
                SELECT p.*, f.numeroFacture, f.montantTotal as facture_montant,
                       CONCAT(c.nom, ' ', c.prenom) as client_nom,
                       CONCAT(u.nom, ' ', u.prenom) as agent_nom
                FROM Paiement p
                JOIN Facture f ON p.idFacture = f.idFacture
                LEFT JOIN Client c ON p.idClient = c.idClient
                LEFT JOIN Utilisateurs u ON p.idAgentEnregistrement = u.idUtilisateur
                ORDER BY p.datePaiement DESC
            ");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Récupérer les paiements d'un client
     */
    public function getByClient($idClient) {
        try {
            $stmt = $this->db->prepare("
                SELECT p.*, f.numeroFacture, f.montantTotal as facture_montant
                FROM Paiement p
                JOIN Facture f ON p.idFacture = f.idFacture
                WHERE p.idClient = ?
                ORDER BY p.datePaiement DESC
            ");
            $stmt->execute([$idClient]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Récupérer les paiements d'une facture
     */
    public function getByFacture($idFacture) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM Paiement WHERE idFacture = ?");
            $stmt->execute([$idFacture]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Récupérer un paiement par ID
     */
    public function getById($id) {
        try {
            $stmt = $this->db->prepare("
                SELECT p.*, f.numeroFacture, f.montantTotal as facture_montant
                FROM Paiement p
                JOIN Facture f ON p.idFacture = f.idFacture
                WHERE p.idPaiement = ?
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
     * Récupérer un paiement par référence
     */
    public function getByReference($reference) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM Paiement WHERE numeroReference = ?");
            $stmt->execute([$reference]);
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
     * Obtenir le total des paiements pour une période
     */
    public function getTotalParPeriode($dateDebut, $dateFin) {
        try {
            $stmt = $this->db->prepare("
                SELECT SUM(montant) as total, COUNT(*) as nombre
                FROM Paiement 
                WHERE datePaiement BETWEEN ? AND ? 
                AND statut = 'effectue'
            ");
            $stmt->execute([$dateDebut, $dateFin]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            return ['total' => 0, 'nombre' => 0];
        }
    }
    
    /**
     * Récupérer les statistiques de paiement par mode
     */
    public function getStatsParMode($dateDebut, $dateFin) {
        try {
            $stmt = $this->db->prepare("
                SELECT modePaiement, COUNT(*) as nombre, SUM(montant) as total
                FROM Paiement 
                WHERE datePaiement BETWEEN ? AND ? 
                AND statut = 'effectue'
                GROUP BY modePaiement
                ORDER BY total DESC
            ");
            $stmt->execute([$dateDebut, $dateFin]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Supprimer un paiement
     */
    public function supprimer($id) {
        try {
            $this->db->beginTransaction();
            
            // Récupérer l'ID de la facture
            $stmt = $this->db->prepare("SELECT idFacture FROM Paiement WHERE idPaiement = ?");
            $stmt->execute([$id]);
            $paiement = $stmt->fetch();
            
            if ($paiement) {
                // Mettre à jour le statut de la facture
                $stmt = $this->db->prepare("
                    UPDATE Facture 
                    SET statut = 'en_attente', datePaiementReel = NULL 
                    WHERE idFacture = ?
                ");
                $stmt->execute([$paiement['idFacture']]);
            }
            
            // Supprimer le paiement
            $stmt = $this->db->prepare("DELETE FROM Paiement WHERE idPaiement = ?");
            $stmt->execute([$id]);
            
            $this->db->commit();
            
            return ['success' => true, 'message' => 'Paiement supprimé avec succès'];
            
        } catch (PDOException $e) {
            $this->db->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Valider un paiement
     */
    public function valider($id) {
        try {
            $this->db->beginTransaction();
            
            $stmt = $this->db->prepare("UPDATE Paiement SET statut = 'effectue' WHERE idPaiement = ?");
            $stmt->execute([$id]);
            
            // Mettre à jour la facture
            $stmt = $this->db->prepare("
                UPDATE Facture f
                JOIN Paiement p ON f.idFacture = p.idFacture
                SET f.statut = 'payee', f.datePaiementReel = NOW()
                WHERE p.idPaiement = ?
            ");
            $stmt->execute([$id]);
            
            $this->db->commit();
            
            return ['success' => true, 'message' => 'Paiement validé avec succès'];
            
        } catch (PDOException $e) {
            $this->db->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Annuler un paiement
     */
    public function annuler($id) {
        try {
            $this->db->beginTransaction();
            
            $stmt = $this->db->prepare("UPDATE Paiement SET statut = 'annule' WHERE idPaiement = ?");
            $stmt->execute([$id]);
            
            // Mettre à jour la facture
            $stmt = $this->db->prepare("
                UPDATE Facture f
                JOIN Paiement p ON f.idFacture = p.idFacture
                SET f.statut = 'en_attente', f.datePaiementReel = NULL
                WHERE p.idPaiement = ?
            ");
            $stmt->execute([$id]);
            
            $this->db->commit();
            
            return ['success' => true, 'message' => 'Paiement annulé avec succès'];
            
        } catch (PDOException $e) {
            $this->db->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    // Méthodes privées
    private function validateData($data) {
        $errors = [];
        if (empty($data['datePaiement'])) $errors['datePaiement'] = 'La date de paiement est requise';
        if (empty($data['montant']) || $data['montant'] <= 0) $errors['montant'] = 'Le montant est invalide';
        if (empty($data['modePaiement'])) $errors['modePaiement'] = 'Le mode de paiement est requis';
        if (empty($data['idFacture'])) $errors['idFacture'] = 'La facture est requise';
        return $errors;
    }
    
    // Getters
    public function getIdPaiement() { return $this->idPaiement; }
    public function getNumeroReference() { return $this->numeroReference; }
    public function getMontant() { return $this->montant; }
    public function getModePaiement() { return $this->modePaiement; }
    public function getStatut() { return $this->statut; }
    
    public function toArray() {
        return [
            'idPaiement' => $this->idPaiement,
            'numeroReference' => $this->numeroReference,
            'datePaiement' => $this->datePaiement,
            'dateEnregistrement' => $this->dateEnregistrement,
            'montant' => $this->montant,
            'monnaie' => $this->monnaie,
            'modePaiement' => $this->modePaiement,
            'statut' => $this->statut,
            'referenceTransaction' => $this->referenceTransaction,
            'banque' => $this->banque,
            'nomTitulaire' => $this->nomTitulaire,
            'penalitePayee' => $this->penalitePayee,
            'remarques' => $this->remarques,
            'idFacture' => $this->idFacture,
            'idClient' => $this->idClient
        ];
    }
}
?>