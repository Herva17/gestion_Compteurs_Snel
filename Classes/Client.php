<?php
require_once __DIR__ . '/Database.php';

class Client {
    private $db;
    private $idClient;
    private $nom;
    private $prenom;
    private $adresse;
    private $codePostal;
    private $ville;
    private $pays;
    private $email;
    private $motDePasse;
    private $telephone;
    private $telephone2;
    private $dateInscription;
    private $dateNaissance;
    private $sexe;
    private $numeroIdentite;
    private $typeIdentite;
    private $statut;
    private $dateCreation;
    private $dateModification;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    private function hydrate($data) {
        $this->idClient = $data['idClient'] ?? null;
        $this->nom = $data['nom'] ?? null;
        $this->prenom = $data['prenom'] ?? null;
        $this->adresse = $data['adresse'] ?? null;
        $this->codePostal = $data['codePostal'] ?? null;
        $this->ville = $data['ville'] ?? null;
        $this->pays = $data['pays'] ?? null;
        $this->email = $data['email'] ?? null;
        $this->motDePasse = $data['motDePasse'] ?? null;
        $this->telephone = $data['telephone'] ?? null;
        $this->telephone2 = $data['telephone2'] ?? null;
        $this->dateInscription = $data['dateInscription'] ?? null;
        $this->dateNaissance = $data['dateNaissance'] ?? null;
        $this->sexe = $data['sexe'] ?? null;
        $this->numeroIdentite = $data['numeroIdentite'] ?? null;
        $this->typeIdentite = $data['typeIdentite'] ?? null;
        $this->statut = $data['statut'] ?? 'actif';
        $this->dateCreation = $data['dateCreation'] ?? null;
        $this->dateModification = $data['dateModification'] ?? null;
    }
    
    /**
     * Vérifier si l'utilisateur est connecté
     */
    public function isLoggedIn() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['client_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    /**
     * Récupérer l'utilisateur connecté
     */
    public function getLoggedInUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        try {
            $stmt = $this->db->prepare("SELECT * FROM Client WHERE idClient = ?");
            $stmt->execute([$_SESSION['client_id']]);
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
     * Authentifier un client
     */
    public function authentifier($email, $motDePasse) {
        if (empty($email) || empty($motDePasse)) {
            return ['success' => false, 'error' => 'Veuillez remplir tous les champs'];
        }
        
        try {
            $stmt = $this->db->prepare("SELECT * FROM Client WHERE email = ? OR telephone = ?");
            $stmt->execute([$email, $email]);
            $client = $stmt->fetch();
            
            if (!$client) {
                return ['success' => false, 'error' => 'Identifiants incorrects'];
            }
            
            if (!password_verify($motDePasse, $client['motDePasse'])) {
                return ['success' => false, 'error' => 'Identifiants incorrects'];
            }
            
            if ($client['statut'] !== 'actif') {
                return ['success' => false, 'error' => 'Votre compte est ' . $client['statut']];
            }
            
            $this->hydrate($client);
            $this->startSession();
            
            return [
                'success' => true,
                'message' => 'Connexion réussie',
                'client' => $this->toArray()
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
     * Enregistrer un nouveau client
     */
    public function enregistrer($data) {
        $errors = $this->validateData($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }
        
        try {
            $this->db->beginTransaction();
            
            // Vérifier si l'email existe déjà
            if ($this->emailExists($data['email'])) {
                return ['success' => false, 'errors' => ['email' => 'Cet email est déjà utilisé']];
            }
            
            // Vérifier si le téléphone existe déjà
            if ($this->telephoneExists($data['telephone'])) {
                return ['success' => false, 'errors' => ['telephone' => 'Ce numéro de téléphone est déjà utilisé']];
            }
            
            $hashedPassword = password_hash($data['motDePasse'], PASSWORD_DEFAULT);
            
            $sql = "INSERT INTO Client (
                nom, prenom, adresse, codePostal, ville, pays, 
                email, motDePasse, telephone, telephone2, 
                dateNaissance, sexe, statut
            ) VALUES (
                :nom, :prenom, :adresse, :codePostal, :ville, :pays,
                :email, :motDePasse, :telephone, :telephone2,
                :dateNaissance, :sexe, :statut
            )";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':nom' => $data['nom'],
                ':prenom' => $data['prenom'],
                ':adresse' => $data['adresse'] ?? '',
                ':codePostal' => $data['codePostal'] ?? null,
                ':ville' => $data['ville'] ?? null,
                ':pays' => $data['pays'] ?? 'Congo',
                ':email' => $data['email'],
                ':motDePasse' => $hashedPassword,
                ':telephone' => $data['telephone'],
                ':telephone2' => $data['telephone2'] ?? null,
                ':dateNaissance' => $data['dateNaissance'] ?? null,
                ':sexe' => $data['sexe'] ?? null,
                ':statut' => $data['statut'] ?? 'actif'
            ]);
            
            $clientId = $this->db->lastInsertId();
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Client enregistré avec succès',
                'idClient' => $clientId
            ];
            
        } catch (PDOException $e) {
            $this->db->rollBack();
            return ['success' => false, 'errors' => ['database' => $e->getMessage()]];
        }
    }
    
    /**
     * Récupérer tous les clients
     */
    public function getAll() {
        try {
            $stmt = $this->db->query("SELECT * FROM Client ORDER BY dateCreation DESC");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Récupérer un client par ID
     */
    public function getById($id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM Client WHERE idClient = ?");
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
     * Récupérer les compteurs du client
     */
    public function getCompteurs() {
        if (!$this->idClient) return [];
        try {
            $stmt = $this->db->prepare("SELECT * FROM Compteur WHERE idClient = ? ORDER BY dateCreation DESC");
            $stmt->execute([$this->idClient]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Récupérer les factures du client
     */
    public function getFactures() {
        if (!$this->idClient) return [];
        try {
            $stmt = $this->db->prepare("SELECT * FROM Facture WHERE idClient = ? ORDER BY dateEmission DESC");
            $stmt->execute([$this->idClient]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Récupérer les paiements du client
     */
    public function getPaiements() {
        if (!$this->idClient) return [];
        try {
            $stmt = $this->db->prepare("SELECT * FROM Paiement WHERE idClient = ? ORDER BY datePaiement DESC");
            $stmt->execute([$this->idClient]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Mettre à jour un client
     */
    public function modifier($id, $data) {
        try {
            $sql = "UPDATE Client SET 
                    nom = :nom, prenom = :prenom, adresse = :adresse, 
                    codePostal = :codePostal, ville = :ville, pays = :pays, 
                    telephone = :telephone, telephone2 = :telephone2,
                    dateNaissance = :dateNaissance, sexe = :sexe,
                    statut = :statut
                    WHERE idClient = :id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':nom' => $data['nom'] ?? '',
                ':prenom' => $data['prenom'] ?? '',
                ':adresse' => $data['adresse'] ?? '',
                ':codePostal' => $data['codePostal'] ?? null,
                ':ville' => $data['ville'] ?? null,
                ':pays' => $data['pays'] ?? 'Congo',
                ':telephone' => $data['telephone'] ?? '',
                ':telephone2' => $data['telephone2'] ?? null,
                ':dateNaissance' => $data['dateNaissance'] ?? null,
                ':sexe' => $data['sexe'] ?? null,
                ':statut' => $data['statut'] ?? 'actif',
                ':id' => $id
            ]);
            
            return ['success' => true, 'message' => 'Client mis à jour avec succès'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Changer le mot de passe
     */
    public function changerMotDePasse($id, $oldPassword, $newPassword) {
        try {
            $stmt = $this->db->prepare("SELECT motDePasse FROM Client WHERE idClient = ?");
            $stmt->execute([$id]);
            $user = $stmt->fetch();
            
            if (!$user || !password_verify($oldPassword, $user['motDePasse'])) {
                return ['success' => false, 'error' => 'Ancien mot de passe incorrect'];
            }
            
            if (strlen($newPassword) < 6) {
                return ['success' => false, 'error' => 'Le mot de passe doit contenir au moins 6 caractères'];
            }
            
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $this->db->prepare("UPDATE Client SET motDePasse = ? WHERE idClient = ?");
            $stmt->execute([$hashedPassword, $id]);
            
            return ['success' => true, 'message' => 'Mot de passe modifié avec succès'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Supprimer un client
     */
    public function supprimer($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM Client WHERE idClient = ?");
            $stmt->execute([$id]);
            return ['success' => true, 'message' => 'Client supprimé avec succès'];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Rechercher des clients
     */
    public function rechercher($critere) {
        try {
            $sql = "SELECT * FROM Client WHERE 
                    nom LIKE :critere OR prenom LIKE :critere OR 
                    email LIKE :critere OR telephone LIKE :critere";
            $search = "%$critere%";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':critere' => $search]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
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
        if (empty($data['telephone'])) $errors['telephone'] = 'Le téléphone est requis';
        return $errors;
    }
    
    private function emailExists($email) {
        $stmt = $this->db->prepare("SELECT idClient FROM Client WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch() !== false;
    }
    
    private function telephoneExists($telephone) {
        $stmt = $this->db->prepare("SELECT idClient FROM Client WHERE telephone = ?");
        $stmt->execute([$telephone]);
        return $stmt->fetch() !== false;
    }
    
    private function startSession() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['client_id'] = $this->idClient;
        $_SESSION['client_nom'] = $this->nom . ' ' . $this->prenom;
        $_SESSION['client_email'] = $this->email;
        $_SESSION['client_role'] = 'client';
        $_SESSION['logged_in'] = true;
    }
    
    // Getters
    public function getIdClient() { return $this->idClient; }
    public function getNom() { return $this->nom; }
    public function getPrenom() { return $this->prenom; }
    public function getAdresse() { return $this->adresse; }
    public function getCodePostal() { return $this->codePostal; }
    public function getVille() { return $this->ville; }
    public function getPays() { return $this->pays; }
    public function getEmail() { return $this->email; }
    public function getTelephone() { return $this->telephone; }
    public function getTelephone2() { return $this->telephone2; }
    public function getDateInscription() { return $this->dateInscription; }
    public function getDateNaissance() { return $this->dateNaissance; }
    public function getSexe() { return $this->sexe; }
    public function getStatut() { return $this->statut; }
    public function getDateCreation() { return $this->dateCreation; }
    public function getDateModification() { return $this->dateModification; }
    
    public function toArray() {
        return [
            'idClient' => $this->idClient,
            'nom' => $this->nom,
            'prenom' => $this->prenom,
            'adresse' => $this->adresse,
            'codePostal' => $this->codePostal,
            'ville' => $this->ville,
            'pays' => $this->pays,
            'email' => $this->email,
            'telephone' => $this->telephone,
            'telephone2' => $this->telephone2,
            'dateInscription' => $this->dateInscription,
            'dateNaissance' => $this->dateNaissance,
            'sexe' => $this->sexe,
            'statut' => $this->statut,
            'dateCreation' => $this->dateCreation
        ];
    }
}
?>