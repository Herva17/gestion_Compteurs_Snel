<?php
require_once __DIR__ . '/Database.php';

class Utilisateur {
    private $db;
    private $idUtilisateur;
    private $nom;
    private $prenom;
    private $email;
    private $motDePasse;
    private $role;
    private $telephone;
    private $dateEmbauche;
    private $dateNaissance;
    private $sexe;
    private $statut;
    private $derniereConnexion;
    private $tentativeConnexion;
    private $bloque;
    private $dateCreation;
    private $dateModification;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    private function hydrate($data) {
        $this->idUtilisateur = $data['idUtilisateur'] ?? null;
        $this->nom = $data['nom'] ?? null;
        $this->prenom = $data['prenom'] ?? null;
        $this->email = $data['email'] ?? null;
        $this->motDePasse = $data['motDePasse'] ?? null;
        $this->role = $data['role'] ?? null;
        $this->telephone = $data['telephone'] ?? null;
        $this->dateEmbauche = $data['dateEmbauche'] ?? null;
        $this->dateNaissance = $data['dateNaissance'] ?? null;
        $this->sexe = $data['sexe'] ?? null;
        $this->statut = $data['statut'] ?? 'actif';
        $this->derniereConnexion = $data['derniereConnexion'] ?? null;
        $this->tentativeConnexion = $data['tentativeConnexion'] ?? 0;
        $this->bloque = $data['bloque'] ?? false;
        $this->dateCreation = $data['dateCreation'] ?? null;
        $this->dateModification = $data['dateModification'] ?? null;
    }
    
    /**
     * Authentifier un utilisateur
     */
    public function authentifier($email, $motDePasse) {
        if (empty($email) || empty($motDePasse)) {
            return ['success' => false, 'error' => 'Veuillez remplir tous les champs'];
        }
        
        try {
            $stmt = $this->db->prepare("SELECT * FROM Utilisateurs WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if (!$user) {
                return ['success' => false, 'error' => 'Identifiants incorrects'];
            }
            
            if ($user['bloque']) {
                return ['success' => false, 'error' => 'Ce compte est bloqué. Contactez l\'administrateur.'];
            }
            
            if ($user['statut'] !== 'actif') {
                return ['success' => false, 'error' => 'Votre compte est ' . $user['statut']];
            }
            
            if (!password_verify($motDePasse, $user['motDePasse'])) {
                // Incrémenter les tentatives de connexion
                $this->incrementerTentatives($user['idUtilisateur']);
                return ['success' => false, 'error' => 'Identifiants incorrects'];
            }
            
            // Réinitialiser les tentatives
            $this->reinitialiserTentatives($user['idUtilisateur']);
            
            // Mettre à jour la dernière connexion
            $this->updateDerniereConnexion($user['idUtilisateur']);
            
            $this->hydrate($user);
            $this->startSession();
            
            return [
                'success' => true,
                'message' => 'Connexion réussie',
                'user' => $this->toArray()
            ];
            
        } catch (PDOException $e) {
            return ['success' => false, 'error' => 'Erreur: ' . $e->getMessage()];
        }
    }
    
    /**
     * Se déconnecter
     */
    public function seDeconnecter() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        return ['success' => true, 'message' => 'Déconnecté avec succès'];
    }
    
    /**
     * Créer un nouvel utilisateur
     */
    public function creer($data) {
        $errors = $this->validateData($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }
        
        try {
            if ($this->emailExists($data['email'])) {
                return ['success' => false, 'errors' => ['email' => 'Cet email est déjà utilisé']];
            }
            
            $hashedPassword = password_hash($data['motDePasse'], PASSWORD_DEFAULT);
            
            $sql = "INSERT INTO Utilisateurs (
                nom, prenom, email, motDePasse, role, telephone,
                dateEmbauche, dateNaissance, sexe, statut
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['nom'],
                $data['prenom'],
                $data['email'],
                $hashedPassword,
                $data['role'] ?? 'agent',
                $data['telephone'] ?? null,
                $data['dateEmbauche'] ?? null,
                $data['dateNaissance'] ?? null,
                $data['sexe'] ?? null,
                $data['statut'] ?? 'actif'
            ]);
            
            return [
                'success' => true,
                'message' => 'Utilisateur créé avec succès',
                'idUtilisateur' => $this->db->lastInsertId()
            ];
            
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Récupérer tous les utilisateurs
     */
    public function getAll() {
        try {
            $stmt = $this->db->query("SELECT * FROM Utilisateurs ORDER BY dateCreation DESC");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Récupérer un utilisateur par ID
     */
    public function getById($id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM Utilisateurs WHERE idUtilisateur = ?");
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
     * Mettre à jour un utilisateur
     */
    public function modifier($id, $data) {
        try {
            $sql = "UPDATE Utilisateurs SET 
                    nom = ?, prenom = ?, telephone = ?, role = ?,
                    dateEmbauche = ?, dateNaissance = ?, sexe = ?,
                    statut = ?
                    WHERE idUtilisateur = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['nom'] ?? '',
                $data['prenom'] ?? '',
                $data['telephone'] ?? null,
                $data['role'] ?? 'agent',
                $data['dateEmbauche'] ?? null,
                $data['dateNaissance'] ?? null,
                $data['sexe'] ?? null,
                $data['statut'] ?? 'actif',
                $id
            ]);
            
            return ['success' => true, 'message' => 'Utilisateur mis à jour avec succès'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Supprimer un utilisateur
     */
    public function supprimer($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM Utilisateurs WHERE idUtilisateur = ?");
            $stmt->execute([$id]);
            return ['success' => true, 'message' => 'Utilisateur supprimé avec succès'];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Changer le mot de passe
     */
    public function changerMotDePasse($id, $oldPassword, $newPassword) {
        try {
            $stmt = $this->db->prepare("SELECT motDePasse FROM Utilisateurs WHERE idUtilisateur = ?");
            $stmt->execute([$id]);
            $user = $stmt->fetch();
            
            if (!$user || !password_verify($oldPassword, $user['motDePasse'])) {
                return ['success' => false, 'error' => 'Ancien mot de passe incorrect'];
            }
            
            if (strlen($newPassword) < 6) {
                return ['success' => false, 'error' => 'Le mot de passe doit contenir au moins 6 caractères'];
            }
            
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $this->db->prepare("UPDATE Utilisateurs SET motDePasse = ? WHERE idUtilisateur = ?");
            $stmt->execute([$hashedPassword, $id]);
            
            return ['success' => true, 'message' => 'Mot de passe modifié avec succès'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Vérifier si l'utilisateur est connecté
     */
    public function isLoggedIn() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        return isset($_SESSION['user_id']) && $_SESSION['logged_in'] === true;
    }
    
    /**
     * Récupérer l'utilisateur connecté
     */
    public function getLoggedInUser() {
        if (!$this->isLoggedIn()) return null;
        return $this->getById($_SESSION['user_id']);
    }
    
    // Méthodes privées
    private function validateData($data) {
        $errors = [];
        if (empty($data['nom'])) $errors['nom'] = 'Le nom est requis';
        if (empty($data['prenom'])) $errors['prenom'] = 'Le prénom est requis';
        if (empty($data['email'])) $errors['email'] = 'L\'email est requis';
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Email invalide';
        if (empty($data['motDePasse'])) $errors['motDePasse'] = 'Le mot de passe est requis';
        if (strlen($data['motDePasse']) < 6) $errors['motDePasse'] = 'Le mot de passe doit contenir au moins 6 caractères';
        if (empty($data['role'])) $errors['role'] = 'Le rôle est requis';
        return $errors;
    }
    
    private function emailExists($email) {
        $stmt = $this->db->prepare("SELECT idUtilisateur FROM Utilisateurs WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch() !== false;
    }
    
    private function incrementerTentatives($id) {
        $stmt = $this->db->prepare("UPDATE Utilisateurs SET tentativeConnexion = tentativeConnexion + 1 WHERE idUtilisateur = ?");
        $stmt->execute([$id]);
    }
    
    private function reinitialiserTentatives($id) {
        $stmt = $this->db->prepare("UPDATE Utilisateurs SET tentativeConnexion = 0 WHERE idUtilisateur = ?");
        $stmt->execute([$id]);
    }
    
    private function updateDerniereConnexion($id) {
        $stmt = $this->db->prepare("UPDATE Utilisateurs SET derniereConnexion = NOW() WHERE idUtilisateur = ?");
        $stmt->execute([$id]);
    }
    
    private function startSession() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['user_id'] = $this->idUtilisateur;
        $_SESSION['user_nom'] = $this->nom . ' ' . $this->prenom;
        $_SESSION['user_email'] = $this->email;
        $_SESSION['user_role'] = $this->role;
        $_SESSION['logged_in'] = true;
    }
    
    // Getters
    public function getIdUtilisateur() { return $this->idUtilisateur; }
    public function getNom() { return $this->nom; }
    public function getPrenom() { return $this->prenom; }
    public function getEmail() { return $this->email; }
    public function getRole() { return $this->role; }
    public function getStatut() { return $this->statut; }
    
    public function toArray() {
        return [
            'idUtilisateur' => $this->idUtilisateur,
            'nom' => $this->nom,
            'prenom' => $this->prenom,
            'email' => $this->email,
            'role' => $this->role,
            'telephone' => $this->telephone,
            'dateEmbauche' => $this->dateEmbauche,
            'statut' => $this->statut,
            'derniereConnexion' => $this->derniereConnexion
        ];
    }
}
?>