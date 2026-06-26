<?php
require_once __DIR__ . '/Database.php';

class Tarif {
    private $db;
    private $idTarif;
    private $categorie;
    private $trancheMin;
    private $trancheMax;
    private $prixUnitaire;
    private $unite;
    private $type;
    private $dateDebutValidite;
    private $dateFinValidite;
    private $estActif;
    private $description;
    private $dateCreation;
    private $dateModification;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    private function hydrate($data) {
        $this->idTarif = $data['idTarif'] ?? null;
        $this->categorie = $data['categorie'] ?? null;
        $this->trancheMin = $data['trancheMin'] ?? null;
        $this->trancheMax = $data['trancheMax'] ?? null;
        $this->prixUnitaire = $data['prixUnitaire'] ?? null;
        $this->unite = $data['unite'] ?? 'kWh';
        $this->type = $data['type'] ?? 'residentiel';
        $this->dateDebutValidite = $data['dateDebutValidite'] ?? null;
        $this->dateFinValidite = $data['dateFinValidite'] ?? null;
        $this->estActif = $data['estActif'] ?? true;
        $this->description = $data['description'] ?? null;
        $this->dateCreation = $data['dateCreation'] ?? null;
        $this->dateModification = $data['dateModification'] ?? null;
    }
    
    /**
     * Créer un nouveau tarif
     */
    public function creer($data) {
        $errors = $this->validateData($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }
        
        try {
            $sql = "INSERT INTO Tarifs (
                categorie, trancheMin, trancheMax, prixUnitaire,
                unite, type, dateDebutValidite, dateFinValidite,
                estActif, description
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['categorie'],
                $data['trancheMin'] ?? 0,
                $data['trancheMax'] ?? null,
                $data['prixUnitaire'],
                $data['unite'] ?? 'kWh',
                $data['type'] ?? 'residentiel',
                $data['dateDebutValidite'],
                $data['dateFinValidite'] ?? null,
                $data['estActif'] ?? true,
                $data['description'] ?? null
            ]);
            
            return [
                'success' => true,
                'message' => 'Tarif créé avec succès',
                'idTarif' => $this->db->lastInsertId()
            ];
            
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Obtenir tous les tarifs
     */
    public function getAll() {
        try {
            $stmt = $this->db->query("SELECT * FROM Tarifs ORDER BY dateCreation DESC");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Obtenir les tarifs actifs
     */
    public function getActifs() {
        try {
            $stmt = $this->db->query("
                SELECT * FROM Tarifs 
                WHERE estActif = 1 
                AND (dateFinValidite IS NULL OR dateFinValidite >= CURDATE())
                ORDER BY trancheMin
            ");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Obtenir un tarif par ID
     */
    public function getById($id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM Tarifs WHERE idTarif = ?");
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
     * Calculer le prix selon la consommation
     */
    public function calculerPrix($consommation, $type = 'residentiel') {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM Tarifs 
                WHERE type = ? 
                AND estActif = 1 
                AND (dateFinValidite IS NULL OR dateFinValidite >= CURDATE())
                AND (trancheMin <= ? AND (trancheMax IS NULL OR trancheMax >= ?))
                ORDER BY trancheMin
            ");
            $stmt->execute([$type, $consommation, $consommation]);
            $tarifs = $stmt->fetchAll();
            
            if (empty($tarifs)) {
                return ['success' => false, 'error' => 'Aucun tarif trouvé pour cette consommation'];
            }
            
            $total = 0;
            $remaining = $consommation;
            
            foreach ($tarifs as $tarif) {
                if ($remaining <= 0) break;
                
                $tranche = $tarif['trancheMax'] 
                    ? min($remaining, $tarif['trancheMax'] - $tarif['trancheMin']) 
                    : $remaining;
                
                $total += $tranche * $tarif['prixUnitaire'];
                $remaining -= $tranche;
            }
            
            return [
                'success' => true,
                'total' => round($total, 2),
                'consommation' => $consommation,
                'type' => $type
            ];
            
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Mettre à jour un tarif
     */
    public function modifier($id, $data) {
        try {
            $sql = "UPDATE Tarifs SET 
                    categorie = ?, trancheMin = ?, trancheMax = ?,
                    prixUnitaire = ?, unite = ?, type = ?,
                    dateDebutValidite = ?, dateFinValidite = ?,
                    estActif = ?, description = ?
                    WHERE idTarif = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['categorie'] ?? '',
                $data['trancheMin'] ?? 0,
                $data['trancheMax'] ?? null,
                $data['prixUnitaire'] ?? 0,
                $data['unite'] ?? 'kWh',
                $data['type'] ?? 'residentiel',
                $data['dateDebutValidite'] ?? null,
                $data['dateFinValidite'] ?? null,
                $data['estActif'] ?? true,
                $data['description'] ?? null,
                $id
            ]);
            
            return ['success' => true, 'message' => 'Tarif mis à jour avec succès'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Supprimer un tarif
     */
    public function supprimer($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM Tarifs WHERE idTarif = ?");
            $stmt->execute([$id]);
            return ['success' => true, 'message' => 'Tarif supprimé avec succès'];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Activer/Désactiver un tarif
     */
    public function toggleActivation($id) {
        try {
            $stmt = $this->db->prepare("UPDATE Tarifs SET estActif = NOT estActif WHERE idTarif = ?");
            $stmt->execute([$id]);
            return ['success' => true, 'message' => 'Statut du tarif modifié avec succès'];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    // Méthodes privées
    private function validateData($data) {
        $errors = [];
        if (empty($data['categorie'])) $errors['categorie'] = 'La catégorie est requise';
        if (empty($data['prixUnitaire']) || $data['prixUnitaire'] < 0) $errors['prixUnitaire'] = 'Le prix unitaire est invalide';
        if (empty($data['dateDebutValidite'])) $errors['dateDebutValidite'] = 'La date de début de validité est requise';
        return $errors;
    }
    
    // Getters
    public function getIdTarif() { return $this->idTarif; }
    public function getCategorie() { return $this->categorie; }
    public function getPrixUnitaire() { return $this->prixUnitaire; }
    public function getEstActif() { return $this->estActif; }
    
    public function toArray() {
        return [
            'idTarif' => $this->idTarif,
            'categorie' => $this->categorie,
            'trancheMin' => $this->trancheMin,
            'trancheMax' => $this->trancheMax,
            'prixUnitaire' => $this->prixUnitaire,
            'unite' => $this->unite,
            'type' => $this->type,
            'dateDebutValidite' => $this->dateDebutValidite,
            'dateFinValidite' => $this->dateFinValidite,
            'estActif' => $this->estActif,
            'description' => $this->description
        ];
    }
}
?>