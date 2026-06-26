<?php
require_once __DIR__ . '/Database.php';

class Notification {
    private $db;
    private $idNotification;
    private $idClient;
    private $idUtilisateur;
    private $titre;
    private $message;
    private $type;
    private $priorite;
    private $estLue;
    private $dateLecture;
    private $dateExpiration;
    private $lien;
    private $dateCreation;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    private function hydrate($data) {
        $this->idNotification = $data['idNotification'] ?? null;
        $this->idClient = $data['idClient'] ?? null;
        $this->idUtilisateur = $data['idUtilisateur'] ?? null;
        $this->titre = $data['titre'] ?? null;
        $this->message = $data['message'] ?? null;
        $this->type = $data['type'] ?? 'info';
        $this->priorite = $data['priorite'] ?? 1;
        $this->estLue = $data['estLue'] ?? false;
        $this->dateLecture = $data['dateLecture'] ?? null;
        $this->dateExpiration = $data['dateExpiration'] ?? null;
        $this->lien = $data['lien'] ?? null;
        $this->dateCreation = $data['dateCreation'] ?? null;
    }
    
    /**
     * Créer une notification
     */
    public function creer($data) {
        $errors = $this->validateData($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }
        
        try {
            $sql = "INSERT INTO Notifications (
                idClient, idUtilisateur, titre, message, type,
                priorite, dateExpiration, lien
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['idClient'] ?? null,
                $data['idUtilisateur'] ?? null,
                $data['titre'],
                $data['message'],
                $data['type'] ?? 'info',
                $data['priorite'] ?? 1,
                $data['dateExpiration'] ?? null,
                $data['lien'] ?? null
            ]);
            
            return [
                'success' => true,
                'message' => 'Notification créée avec succès',
                'idNotification' => $this->db->lastInsertId()
            ];
            
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Notifier un client
     */
    public function notifierClient($idClient, $titre, $message, $type = 'info', $lien = null) {
        return $this->creer([
            'idClient' => $idClient,
            'titre' => $titre,
            'message' => $message,
            'type' => $type,
            'lien' => $lien
        ]);
    }
    
    /**
     * Notifier un utilisateur (agent/admin)
     */
    public function notifierUtilisateur($idUtilisateur, $titre, $message, $type = 'info', $lien = null) {
        return $this->creer([
            'idUtilisateur' => $idUtilisateur,
            'titre' => $titre,
            'message' => $message,
            'type' => $type,
            'lien' => $lien
        ]);
    }
    
    /**
     * Récupérer les notifications d'un client
     */
    public function getByClient($idClient) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM Notifications 
                WHERE idClient = ? 
                ORDER BY priorite DESC, dateCreation DESC
            ");
            $stmt->execute([$idClient]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Récupérer les notifications non lues d'un client
     */
    public function getNonLuesClient($idClient) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM Notifications 
                WHERE idClient = ? AND estLue = 0 
                AND (dateExpiration IS NULL OR dateExpiration >= CURDATE())
                ORDER BY priorite DESC, dateCreation DESC
            ");
            $stmt->execute([$idClient]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Récupérer les notifications d'un utilisateur (agent/admin)
     */
    public function getByUtilisateur($idUtilisateur) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM Notifications 
                WHERE idUtilisateur = ? 
                ORDER BY priorite DESC, dateCreation DESC
            ");
            $stmt->execute([$idUtilisateur]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Récupérer les notifications non lues d'un utilisateur (agent/admin)
     */
    public function getNonLuesUtilisateur($idUtilisateur) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM Notifications 
                WHERE idUtilisateur = ? AND estLue = 0 
                AND (dateExpiration IS NULL OR dateExpiration >= CURDATE())
                ORDER BY priorite DESC, dateCreation DESC
            ");
            $stmt->execute([$idUtilisateur]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Compter les notifications non lues d'un client
     */
    public function countNonLuesClient($idClient) {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as total 
                FROM Notifications 
                WHERE idClient = ? AND estLue = 0 
                AND (dateExpiration IS NULL OR dateExpiration >= CURDATE())
            ");
            $stmt->execute([$idClient]);
            return $stmt->fetch()['total'] ?? 0;
        } catch (PDOException $e) {
            return 0;
        }
    }
    
    /**
     * Compter les notifications non lues d'un utilisateur (agent/admin)
     */
    public function countNonLuesUtilisateur($idUtilisateur) {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as total 
                FROM Notifications 
                WHERE idUtilisateur = ? AND estLue = 0 
                AND (dateExpiration IS NULL OR dateExpiration >= CURDATE())
            ");
            $stmt->execute([$idUtilisateur]);
            return $stmt->fetch()['total'] ?? 0;
        } catch (PDOException $e) {
            return 0;
        }
    }
    
    /**
     * Marquer une notification comme lue
     */
    public function marquerCommeLue($id) {
        try {
            $stmt = $this->db->prepare("
                UPDATE Notifications 
                SET estLue = 1, dateLecture = NOW() 
                WHERE idNotification = ?
            ");
            $stmt->execute([$id]);
            return ['success' => true, 'message' => 'Notification marquée comme lue'];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Marquer toutes les notifications d'un client comme lues
     */
    public function marquerToutLuClient($idClient) {
        try {
            $stmt = $this->db->prepare("
                UPDATE Notifications 
                SET estLue = 1, dateLecture = NOW() 
                WHERE idClient = ? AND estLue = 0
            ");
            $stmt->execute([$idClient]);
            return ['success' => true, 'message' => 'Toutes les notifications marquées comme lues'];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Marquer toutes les notifications d'un utilisateur comme lues
     */
    public function marquerToutLuUtilisateur($idUtilisateur) {
        try {
            $stmt = $this->db->prepare("
                UPDATE Notifications 
                SET estLue = 1, dateLecture = NOW() 
                WHERE idUtilisateur = ? AND estLue = 0
            ");
            $stmt->execute([$idUtilisateur]);
            return ['success' => true, 'message' => 'Toutes les notifications marquées comme lues'];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Supprimer les notifications expirées
     */
    public function supprimerExpirees() {
        try {
            $stmt = $this->db->prepare("DELETE FROM Notifications WHERE dateExpiration < CURDATE()");
            $stmt->execute();
            return ['success' => true, 'deleted' => $stmt->rowCount()];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Supprimer une notification
     */
    public function supprimer($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM Notifications WHERE idNotification = ?");
            $stmt->execute([$id]);
            return ['success' => true, 'message' => 'Notification supprimée avec succès'];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Récupérer une notification par ID
     */
    public function getById($id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM Notifications WHERE idNotification = ?");
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
    
    // Méthodes privées
    private function validateData($data) {
        $errors = [];
        if (empty($data['titre'])) $errors['titre'] = 'Le titre est requis';
        if (empty($data['message'])) $errors['message'] = 'Le message est requis';
        if (empty($data['idClient']) && empty($data['idUtilisateur'])) {
            $errors['destinataire'] = 'Le destinataire (client ou utilisateur) est requis';
        }
        return $errors;
    }
    
    // Getters
    public function getIdNotification() { return $this->idNotification; }
    public function getTitre() { return $this->titre; }
    public function getMessage() { return $this->message; }
    public function getType() { return $this->type; }
    public function isLue() { return $this->estLue; }
    public function getDateCreation() { return $this->dateCreation; }
    
    public function toArray() {
        return [
            'idNotification' => $this->idNotification,
            'idClient' => $this->idClient,
            'idUtilisateur' => $this->idUtilisateur,
            'titre' => $this->titre,
            'message' => $this->message,
            'type' => $this->type,
            'priorite' => $this->priorite,
            'estLue' => $this->estLue,
            'dateLecture' => $this->dateLecture,
            'dateExpiration' => $this->dateExpiration,
            'lien' => $this->lien,
            'dateCreation' => $this->dateCreation
        ];
    }
}
?>