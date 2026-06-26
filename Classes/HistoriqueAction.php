<?php
require_once __DIR__ . '/Database.php';

class HistoriqueAction {
    private $db;
    private $idHistorique;
    private $tableConcernee;
    private $idEnregistrement;
    private $action;
    private $anciennesValeurs;
    private $nouvellesValeurs;
    private $idUtilisateur;
    private $adresseIP;
    private $userAgent;
    private $dateAction;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    private function hydrate($data) {
        $this->idHistorique = $data['idHistorique'] ?? null;
        $this->tableConcernee = $data['tableConcernee'] ?? null;
        $this->idEnregistrement = $data['idEnregistrement'] ?? null;
        $this->action = $data['action'] ?? null;
        $this->anciennesValeurs = $data['anciennesValeurs'] ?? null;
        $this->nouvellesValeurs = $data['nouvellesValeurs'] ?? null;
        $this->idUtilisateur = $data['idUtilisateur'] ?? null;
        $this->adresseIP = $data['adresseIP'] ?? null;
        $this->userAgent = $data['userAgent'] ?? null;
        $this->dateAction = $data['dateAction'] ?? null;
    }
    
    /**
     * Enregistrer une action dans l'historique
     */
    public function enregistrer($table, $idEnregistrement, $action, $anciennesValeurs = null, $nouvellesValeurs = null) {
        try {
            // Récupérer l'ID de l'utilisateur connecté
            $idUtilisateur = null;
            if (session_status() === PHP_SESSION_NONE) session_start();
            if (isset($_SESSION['user_id'])) {
                $idUtilisateur = $_SESSION['user_id'];
            }
            
            // Récupérer l'adresse IP
            $adresseIP = $_SERVER['REMOTE_ADDR'] ?? null;
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            
            $sql = "INSERT INTO HistoriqueActions (
                tableConcernee, idEnregistrement, action,
                anciennesValeurs, nouvellesValeurs, idUtilisateur,
                adresseIP, userAgent
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $table,
                $idEnregistrement,
                $action,
                $anciennesValeurs,
                $nouvellesValeurs,
                $idUtilisateur,
                $adresseIP,
                $userAgent
            ]);
            
            return ['success' => true, 'idHistorique' => $this->db->lastInsertId()];
            
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Récupérer l'historique d'une table
     */
    public function getByTable($table, $idEnregistrement = null) {
        try {
            $sql = "SELECT h.*, CONCAT(u.nom, ' ', u.prenom) as utilisateur_nom
                    FROM HistoriqueActions h
                    LEFT JOIN Utilisateurs u ON h.idUtilisateur = u.idUtilisateur
                    WHERE h.tableConcernee = ?";
            $params = [$table];
            
            if ($idEnregistrement !== null) {
                $sql .= " AND h.idEnregistrement = ?";
                $params[] = $idEnregistrement;
            }
            
            $sql .= " ORDER BY h.dateAction DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Récupérer l'historique d'un enregistrement
     */
    public function getByEnregistrement($idEnregistrement, $table = null) {
        try {
            $sql = "SELECT h.*, CONCAT(u.nom, ' ', u.prenom) as utilisateur_nom
                    FROM HistoriqueActions h
                    LEFT JOIN Utilisateurs u ON h.idUtilisateur = u.idUtilisateur
                    WHERE h.idEnregistrement = ?";
            $params = [$idEnregistrement];
            
            if ($table !== null) {
                $sql .= " AND h.tableConcernee = ?";
                $params[] = $table;
            }
            
            $sql .= " ORDER BY h.dateAction DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Récupérer l'historique d'un utilisateur
     */
    public function getByUtilisateur($idUtilisateur) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM HistoriqueActions 
                WHERE idUtilisateur = ? 
                ORDER BY dateAction DESC
            ");
            $stmt->execute([$idUtilisateur]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Récupérer l'historique des actions d'un type spécifique
     */
    public function getByAction($action) {
        try {
            $stmt = $this->db->prepare("
                SELECT h.*, CONCAT(u.nom, ' ', u.prenom) as utilisateur_nom
                FROM HistoriqueActions h
                LEFT JOIN Utilisateurs u ON h.idUtilisateur = u.idUtilisateur
                WHERE h.action = ?
                ORDER BY h.dateAction DESC
            ");
            $stmt->execute([$action]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Récupérer les dernières actions
     */
    public function getDernieres($limit = 50) {
        try {
            $stmt = $this->db->prepare("
                SELECT h.*, CONCAT(u.nom, ' ', u.prenom) as utilisateur_nom
                FROM HistoriqueActions h
                LEFT JOIN Utilisateurs u ON h.idUtilisateur = u.idUtilisateur
                ORDER BY h.dateAction DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Nettoyer l'historique (supprimer les anciennes entrées)
     */
    public function nettoyer($jours = 90) {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM HistoriqueActions 
                WHERE dateAction < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$jours]);
            return ['success' => true, 'deleted' => $stmt->rowCount()];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Récupérer les statistiques d'actions
     */
    public function getStats($dateDebut = null, $dateFin = null) {
        try {
            $sql = "SELECT action, COUNT(*) as total, DATE(dateAction) as jour
                    FROM HistoriqueActions";
            $params = [];
            
            if ($dateDebut && $dateFin) {
                $sql .= " WHERE dateAction BETWEEN ? AND ?";
                $params = [$dateDebut, $dateFin];
            }
            
            $sql .= " GROUP BY action, DATE(dateAction)
                      ORDER BY dateAction DESC, total DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Récupérer les actions par table
     */
    public function getActionsParTable() {
        try {
            $stmt = $this->db->query("
                SELECT tableConcernee, COUNT(*) as total
                FROM HistoriqueActions
                GROUP BY tableConcernee
                ORDER BY total DESC
            ");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    // Getters
    public function getIdHistorique() { return $this->idHistorique; }
    public function getTableConcernee() { return $this->tableConcernee; }
    public function getAction() { return $this->action; }
    public function getDateAction() { return $this->dateAction; }
    
    public function toArray() {
        return [
            'idHistorique' => $this->idHistorique,
            'tableConcernee' => $this->tableConcernee,
            'idEnregistrement' => $this->idEnregistrement,
            'action' => $this->action,
            'anciennesValeurs' => $this->anciennesValeurs,
            'nouvellesValeurs' => $this->nouvellesValeurs,
            'idUtilisateur' => $this->idUtilisateur,
            'adresseIP' => $this->adresseIP,
            'userAgent' => $this->userAgent,
            'dateAction' => $this->dateAction
        ];
    }
}
?>