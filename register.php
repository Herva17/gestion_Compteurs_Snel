<?php
// Page d'inscription SNEL - Gestion des compteurs
require_once __DIR__ . '/Classes/Database.php';
require_once __DIR__ . '/Classes/Client.php';
require_once __DIR__ . '/Classes/Utilisateur.php';

$page_title = 'Inscription - SNEL Gestion des compteurs';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'] ?? 'client';
    
    // Validation commune
    if (empty($_POST['nom'])) $errors['nom'] = 'Le nom est requis';
    if (empty($_POST['prenom'])) $errors['prenom'] = 'Le prénom est requis';
    if (empty($_POST['email'])) $errors['email'] = 'L\'email est requis';
    if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Email invalide';
    if (empty($_POST['telephone'])) $errors['telephone'] = 'Le téléphone est requis';
    if (empty($_POST['mot_de_passe'])) $errors['mot_de_passe'] = 'Le mot de passe est requis';
    if (strlen($_POST['mot_de_passe']) < 6) $errors['mot_de_passe'] = 'Le mot de passe doit contenir au moins 6 caractères';
    if ($_POST['mot_de_passe'] !== $_POST['confirm_password']) $errors['confirm_password'] = 'Les mots de passe ne correspondent pas';
    
    if ($role === 'utilisateur' && empty($_POST['role_utilisateur'])) {
        $errors['role_utilisateur'] = 'Le rôle est requis pour les agents/administrateurs';
    }
    
    if (empty($errors)) {
        try {
            if ($role === 'client') {
                $client = new Client();
                $data = [
                    'nom' => $_POST['nom'],
                    'prenom' => $_POST['prenom'],
                    'adresse' => $_POST['adresse'] ?? '',
                    'codePostal' => $_POST['code_postal'] ?? null,
                    'ville' => $_POST['ville'] ?? null,
                    'pays' => $_POST['pays'] ?? 'Congo',
                    'email' => $_POST['email'],
                    'motDePasse' => $_POST['mot_de_passe'],
                    'telephone' => $_POST['telephone'],
                    'telephone2' => $_POST['telephone2'] ?? null,
                    'dateNaissance' => $_POST['date_naissance'] ?? null,
                    'sexe' => $_POST['sexe'] ?? null,
                    'statut' => 'actif'
                ];
                
                $result = $client->enregistrer($data);
                
                if ($result['success']) {
                    header('Location: login.php?success=register');
                    exit;
                } else {
                    $errors = $result['errors'] ?? ['database' => $result['error'] ?? 'Erreur lors de l\'inscription'];
                }
                
            } else if ($role === 'utilisateur') {
                $utilisateur = new Utilisateur();
                $data = [
                    'nom' => $_POST['nom'],
                    'prenom' => $_POST['prenom'],
                    'email' => $_POST['email'],
                    'motDePasse' => $_POST['mot_de_passe'],
                    'role' => $_POST['role_utilisateur'],
                    'telephone' => $_POST['telephone'],
                    'dateEmbauche' => $_POST['date_embauche'] ?? null,
                    'dateNaissance' => $_POST['date_naissance'] ?? null,
                    'sexe' => $_POST['sexe'] ?? null,
                    'statut' => 'actif'
                ];
                
                $result = $utilisateur->creer($data);
                
                if ($result['success']) {
                    header('Location: login.php?success=register');
                    exit;
                } else {
                    $errors = $result['errors'] ?? ['database' => $result['error'] ?? 'Erreur lors de l\'inscription'];
                }
            }
            
        } catch (Exception $e) {
            $errors = ['database' => $e->getMessage()];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        * { font-family: 'Inter', sans-serif; }
        
        :root {
            --primary: #1a365d;
            --primary-light: #2a4a7a;
            --secondary: #c05621;
            --secondary-light: #dd6b20;
            --accent: #ecc94b;
            --light: #f7fafc;
            --gray: #edf2f7;
        }
        
        .bg-primary { background: var(--primary); }
        .bg-primary-light { background: var(--primary-light); }
        .text-primary { color: var(--primary); }
        .text-primary-light { color: var(--primary-light); }
        .border-primary { border-color: var(--primary); }
        
        .bg-secondary { background: var(--secondary); }
        .text-secondary { color: var(--secondary); }
        .border-secondary { border-color: var(--secondary); }
        .bg-secondary-light { background: var(--secondary-light); }
        
        .bg-accent { background: var(--accent); }
        .text-accent { color: var(--accent); }
        
        .btn-primary {
            background: var(--primary);
            color: white;
            transition: all 0.3s ease;
            border: none;
        }
        .btn-primary:hover {
            background: #0f2440;
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(26, 54, 93, 0.25);
        }
        
        .btn-secondary {
            background: var(--secondary);
            color: white;
            transition: all 0.3s ease;
            border: none;
        }
        .btn-secondary:hover {
            background: #a0441a;
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(192, 86, 33, 0.25);
        }
        
        .input-field {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 0.75rem;
            transition: all 0.3s ease;
            background: white;
            font-size: 0.95rem;
        }
        
        .input-field:focus {
            border-color: var(--secondary);
            outline: none;
            box-shadow: 0 0 0 4px rgba(192, 86, 33, 0.1);
        }
        
        .input-field.error {
            border-color: #ef4444;
        }
        
        .auth-card {
            background: white;
            border-radius: 1.5rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }
        
        .role-selector {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }
        
        .role-btn {
            padding: 0.6rem 1rem;
            border-radius: 0.75rem;
            font-size: 0.8rem;
            font-weight: 600;
            border: 2px solid #e2e8f0;
            background: white;
            color: #64748b;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .role-btn:hover {
            border-color: var(--secondary);
            color: var(--primary);
        }
        
        .role-btn.active {
            border-color: var(--secondary);
            background: #fff5ed;
            color: var(--secondary);
        }
        
        .error-text {
            color: #ef4444;
            font-size: 0.75rem;
            margin-top: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-bottom: 2rem;
        }
        
        .step {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #e2e8f0;
            transition: all 0.3s;
        }
        
        .step.active {
            background: var(--secondary);
            transform: scale(1.2);
        }
        
        .step.done {
            background: var(--primary);
        }
        
        .hero-section {
            background: linear-gradient(135deg, #1a365d 0%, #2a4a7a 50%, #3a5a8a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: -40%;
            right: -15%;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(236, 201, 75, 0.08) 0%, transparent 70%);
            border-radius: 50%;
        }
        
        .hero-section::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -10%;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(192, 86, 33, 0.06) 0%, transparent 70%);
            border-radius: 50%;
        }
        
        .benefit-card {
            background: rgba(255,255,255,0.06);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            padding: 16px;
            transition: all 0.3s ease;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        
        .benefit-card:hover {
            background: rgba(255,255,255,0.1);
            transform: translateX(4px);
        }
        
        .float-slow {
            animation: float 6s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-16px); }
        }
    </style>
</head>
<body>

<div class="hero-section min-h-screen flex items-center">
    <div class="max-w-7xl mx-auto px-4 w-full relative z-10">
        <div class="grid lg:grid-cols-2 gap-16 items-start">
            <!-- Section gauche - Formulaire -->
            <div>
                <!-- Logo -->
                <div class="text-center mb-8">
                    <div class="inline-flex items-center space-x-3">
                        <div class="w-14 h-14 bg-secondary rounded-xl flex items-center justify-center shadow-lg">
                            <i class="fas fa-bolt text-white text-2xl"></i>
                        </div>
                        <div>
                            <span class="text-3xl font-extrabold text-primary tracking-tight">SNEL</span>
                            <span class="text-secondary text-[10px] block -mt-0.5 font-semibold tracking-wider uppercase">Gestion des compteurs</span>
                        </div>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-800 mt-6">Créer un compte</h2>
                    <p class="text-gray-500 text-sm mt-1">Rejoignez notre plateforme</p>
                </div>
                
                <!-- Messages d'erreur -->
                <?php if (!empty($errors)): ?>
                    <div class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-xl mb-4 text-sm">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <span>Veuillez corriger les erreurs ci-dessous.</span>
                        <ul class="mt-1 space-y-1 text-xs">
                            <?php foreach ($errors as $field => $message): ?>
                                <?php if (is_array($message)): ?>
                                    <?php foreach ($message as $msg): ?>
                                        <li>• <?= htmlspecialchars($msg) ?></li>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <li>• <?= htmlspecialchars($message) ?></li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <!-- Formulaire -->
                <div class="auth-card p-8">
                    <!-- Sélecteur de rôle -->
                    <div class="role-selector">
                        <button type="button" class="role-btn active" onclick="selectRole('client')" id="role-client">
                            <i class="fas fa-user icon"></i>Client
                        </button>
                        <button type="button" class="role-btn" onclick="selectRole('utilisateur')" id="role-utilisateur">
                            <i class="fas fa-user-tie icon"></i>Agent/Admin
                        </button>
                    </div>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="role" id="role-input" value="client">
                        
                        <!-- Indicateur d'étapes -->
                        <div class="step-indicator">
                            <div class="step active"></div>
                            <div class="step"></div>
                            <div class="step"></div>
                        </div>
                        
                        <!-- Étape 1 : Informations personnelles -->
                        <div class="step-content" id="step1">
                            <div class="mb-4">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-user text-secondary mr-2"></i>Nom <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="nom" required 
                                       class="input-field <?= isset($errors['nom']) ? 'error' : '' ?>"
                                       placeholder="Votre nom"
                                       value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>">
                                <?php if (isset($errors['nom'])): ?>
                                    <p class="error-text"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($errors['nom']) ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-user text-secondary mr-2"></i>Prénom <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="prenom" required 
                                       class="input-field <?= isset($errors['prenom']) ? 'error' : '' ?>"
                                       placeholder="Votre prénom"
                                       value="<?= htmlspecialchars($_POST['prenom'] ?? '') ?>">
                                <?php if (isset($errors['prenom'])): ?>
                                    <p class="error-text"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($errors['prenom']) ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-venus-mars text-secondary mr-2"></i>Sexe
                                </label>
                                <select name="sexe" class="input-field">
                                    <option value="M" <?= (isset($_POST['sexe']) && $_POST['sexe'] === 'M') ? 'selected' : '' ?>>Masculin</option>
                                    <option value="F" <?= (isset($_POST['sexe']) && $_POST['sexe'] === 'F') ? 'selected' : '' ?>>Féminin</option>
                                    <option value="Autre" <?= (isset($_POST['sexe']) && $_POST['sexe'] === 'Autre') ? 'selected' : '' ?>>Autre</option>
                                </select>
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-calendar text-secondary mr-2"></i>Date de naissance
                                </label>
                                <input type="date" name="date_naissance" 
                                       class="input-field"
                                       value="<?= htmlspecialchars($_POST['date_naissance'] ?? '') ?>">
                            </div>
                            
                            <button type="button" onclick="nextStep()" 
                                    class="btn-secondary w-full py-3.5 rounded-xl font-bold transition-all shadow-lg hover:shadow-xl">
                                Suivant <i class="fas fa-arrow-right ml-2"></i>
                            </button>
                        </div>
                        
                        <!-- Étape 2 : Coordonnées -->
                        <div class="step-content hidden" id="step2">
                            <div class="mb-4">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-phone text-secondary mr-2"></i>Téléphone <span class="text-red-500">*</span>
                                </label>
                                <input type="tel" name="telephone" required 
                                       class="input-field <?= isset($errors['telephone']) ? 'error' : '' ?>"
                                       placeholder="+243 XX XXX XXXX"
                                       value="<?= htmlspecialchars($_POST['telephone'] ?? '') ?>">
                                <?php if (isset($errors['telephone'])): ?>
                                    <p class="error-text"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($errors['telephone']) ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-phone text-secondary mr-2"></i>Téléphone 2 (optionnel)
                                </label>
                                <input type="tel" name="telephone2" 
                                       class="input-field"
                                       placeholder="Autre numéro"
                                       value="<?= htmlspecialchars($_POST['telephone2'] ?? '') ?>">
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-envelope text-secondary mr-2"></i>Email <span class="text-red-500">*</span>
                                </label>
                                <input type="email" name="email" required 
                                       class="input-field <?= isset($errors['email']) ? 'error' : '' ?>"
                                       placeholder="votre@email.com"
                                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                                <?php if (isset($errors['email'])): ?>
                                    <p class="error-text"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($errors['email']) ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-map-marker-alt text-secondary mr-2"></i>Adresse
                                </label>
                                <input type="text" name="adresse" 
                                       class="input-field"
                                       placeholder="Votre adresse complète"
                                       value="<?= htmlspecialchars($_POST['adresse'] ?? '') ?>">
                            </div>
                            
                            <div class="grid grid-cols-2 gap-3">
                                <div class="mb-4">
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Ville</label>
                                    <input type="text" name="ville" 
                                           class="input-field"
                                           placeholder="Ville"
                                           value="<?= htmlspecialchars($_POST['ville'] ?? '') ?>">
                                </div>
                                <div class="mb-4">
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Code postal</label>
                                    <input type="text" name="code_postal" 
                                           class="input-field"
                                           placeholder="Code postal"
                                           value="<?= htmlspecialchars($_POST['code_postal'] ?? '') ?>">
                                </div>
                            </div>
                            
                            <!-- Section pour utilisateur -->
                            <div id="role_utilisateur_section" style="display:none;">
                                <div class="mb-4">
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        <i class="fas fa-user-tag text-secondary mr-2"></i>Rôle <span class="text-red-500">*</span>
                                    </label>
                                    <select name="role_utilisateur" class="input-field <?= isset($errors['role_utilisateur']) ? 'error' : '' ?>">
                                        <option value="">Choisir un rôle</option>
                                        <option value="agent" <?= (isset($_POST['role_utilisateur']) && $_POST['role_utilisateur'] === 'agent') ? 'selected' : '' ?>>Agent</option>
                                        <option value="administrateur" <?= (isset($_POST['role_utilisateur']) && $_POST['role_utilisateur'] === 'administrateur') ? 'selected' : '' ?>>Administrateur</option>
                                    </select>
                                    <?php if (isset($errors['role_utilisateur'])): ?>
                                        <p class="error-text"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($errors['role_utilisateur']) ?></p>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        <i class="fas fa-calendar-alt text-secondary mr-2"></i>Date d'embauche
                                    </label>
                                    <input type="date" name="date_embauche" 
                                           class="input-field"
                                           value="<?= htmlspecialchars($_POST['date_embauche'] ?? '') ?>">
                                </div>
                            </div>
                            
                            <div class="flex gap-3">
                                <button type="button" onclick="prevStep()" 
                                        class="btn-primary flex-1 py-3.5 rounded-xl font-bold transition-all">
                                    <i class="fas fa-arrow-left mr-2"></i>Retour
                                </button>
                                <button type="button" onclick="nextStep()" 
                                        class="btn-secondary flex-1 py-3.5 rounded-xl font-bold transition-all shadow-lg hover:shadow-xl">
                                    Suivant <i class="fas fa-arrow-right ml-2"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Étape 3 : Sécurité -->
                        <div class="step-content hidden" id="step3">
                            <div class="mb-4">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-lock text-secondary mr-2"></i>Mot de passe <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <input type="password" name="mot_de_passe" id="password" required 
                                           class="input-field <?= isset($errors['mot_de_passe']) ? 'error' : '' ?>"
                                           placeholder="Minimum 6 caractères">
                                    <button type="button" onclick="togglePassword('password', 'eye-icon1')" 
                                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition">
                                        <i id="eye-icon1" class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <?php if (isset($errors['mot_de_passe'])): ?>
                                    <p class="error-text"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($errors['mot_de_passe']) ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-6">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-check-circle text-secondary mr-2"></i>Confirmer le mot de passe <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <input type="password" name="confirm_password" id="confirm_password" required 
                                           class="input-field <?= isset($errors['confirm_password']) ? 'error' : '' ?>"
                                           placeholder="Confirmez votre mot de passe">
                                    <button type="button" onclick="togglePassword('confirm_password', 'eye-icon2')" 
                                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition">
                                        <i id="eye-icon2" class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <?php if (isset($errors['confirm_password'])): ?>
                                    <p class="error-text"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($errors['confirm_password']) ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="flex gap-3">
                                <button type="button" onclick="prevStep()" 
                                        class="btn-primary flex-1 py-3.5 rounded-xl font-bold transition-all">
                                    <i class="fas fa-arrow-left mr-2"></i>Retour
                                </button>
                                <button type="submit" class="btn-secondary flex-1 py-3.5 rounded-xl font-bold transition-all shadow-lg hover:shadow-xl">
                                    <i class="fas fa-user-plus mr-2"></i>S'inscrire
                                </button>
                            </div>
                            
                            <p class="text-xs text-gray-400 text-center mt-4">
                                <i class="fas fa-info-circle mr-1"></i>
                                En vous inscrivant, vous acceptez nos conditions générales
                            </p>
                        </div>
                    </form>
                    
                    <!-- Lien connexion -->
                    <div class="mt-6 text-center">
                        <p class="text-sm text-gray-600">
                            Déjà inscrit ? 
                            <a href="login.php" class="text-primary font-semibold hover:text-secondary transition">
                                Se connecter
                            </a>
                        </p>
                    </div>
                    
                    <!-- Retour accueil -->
                    <div class="mt-4 text-center">
                        <a href="index.php" class="text-sm text-gray-400 hover:text-gray-600 transition">
                            <i class="fas fa-arrow-left mr-1"></i>Retour à l'accueil
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Section droite - Avantages -->
            <div class="hidden lg:block">
                <div class="relative float-slow">
                    <div class="text-center mb-8">
                        <div class="inline-block bg-secondary/20 px-4 py-1.5 rounded-full text-accent text-sm font-semibold">
                            <i class="fas fa-bolt mr-2"></i> Service public
                        </div>
                        <h3 class="text-2xl font-bold text-white mt-4">
                            Créez votre compte en quelques minutes
                        </h3>
                        <p class="text-blue-200 mt-2 text-sm">
                            Accédez à tous nos services pour gérer vos compteurs électriques.
                        </p>
                    </div>
                    
                    <!-- Avantages -->
                    <div class="space-y-4">
                        <div class="benefit-card">
                            <div class="w-10 h-10 bg-secondary/20 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-gauge-high text-accent"></i>
                            </div>
                            <div>
                                <h4 class="text-white font-semibold">Suivi de consommation</h4>
                                <p class="text-blue-300 text-sm">Visualisez votre consommation en temps réel</p>
                            </div>
                        </div>
                        
                        <div class="benefit-card">
                            <div class="w-10 h-10 bg-secondary/20 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-file-invoice-dollar text-accent"></i>
                            </div>
                            <div>
                                <h4 class="text-white font-semibold">Gestion des factures</h4>
                                <p class="text-blue-300 text-sm">Payez vos factures en ligne facilement</p>
                            </div>
                        </div>
                        
                        <div class="benefit-card">
                            <div class="w-10 h-10 bg-secondary/20 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-headset text-accent"></i>
                            </div>
                            <div>
                                <h4 class="text-white font-semibold">Support 24/7</h4>
                                <p class="text-blue-300 text-sm">Une équipe dédiée à votre service</p>
                            </div>
                        </div>
                        
                        <div class="benefit-card">
                            <div class="w-10 h-10 bg-secondary/20 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-shield-alt text-accent"></i>
                            </div>
                            <div>
                                <h4 class="text-white font-semibold">Sécurité garantie</h4>
                                <p class="text-blue-300 text-sm">Vos données sont protégées et sécurisées</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Petit message -->
                    <div class="mt-6 p-4 bg-white/5 rounded-xl border border-white/10 text-center">
                        <p class="text-blue-200 text-sm">
                            <i class="fas fa-check-circle text-accent mr-2"></i>
                            Déjà <span class="text-white font-bold">50 000+</span> clients nous font confiance
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    let currentStep = 1;
    const totalSteps = 3;
    
    function nextStep() {
        if (currentStep === 1) {
            const nom = document.querySelector('input[name="nom"]').value;
            const prenom = document.querySelector('input[name="prenom"]').value;
            if (!nom.trim() || !prenom.trim()) {
                alert('Veuillez remplir votre nom et prénom.');
                return;
            }
        }
        
        if (currentStep === 2) {
            const telephone = document.querySelector('input[name="telephone"]').value;
            const email = document.querySelector('input[name="email"]').value;
            if (!telephone.trim()) {
                alert('Veuillez entrer votre numéro de téléphone.');
                return;
            }
            if (!email.trim()) {
                alert('Veuillez entrer votre email.');
                return;
            }
        }
        
        if (currentStep < totalSteps) {
            document.getElementById(`step${currentStep}`).classList.add('hidden');
            currentStep++;
            document.getElementById(`step${currentStep}`).classList.remove('hidden');
            updateSteps();
        }
    }
    
    function prevStep() {
        if (currentStep > 1) {
            document.getElementById(`step${currentStep}`).classList.add('hidden');
            currentStep--;
            document.getElementById(`step${currentStep}`).classList.remove('hidden');
            updateSteps();
        }
    }
    
    function updateSteps() {
        const steps = document.querySelectorAll('.step');
        steps.forEach((step, index) => {
            step.className = 'step';
            if (index + 1 === currentStep) step.classList.add('active');
            else if (index + 1 < currentStep) step.classList.add('done');
        });
    }
    
    function togglePassword(inputId, iconId) {
        const input = document.getElementById(inputId);
        const icon = document.getElementById(iconId);
        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'fas fa-eye-slash';
        } else {
            input.type = 'password';
            icon.className = 'fas fa-eye';
        }
    }
    
    function selectRole(role) {
        document.querySelectorAll('.role-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        document.getElementById('role-' + role).classList.add('active');
        document.getElementById('role-input').value = role;
        
        const roleSection = document.getElementById('role_utilisateur_section');
        if (role === 'utilisateur') {
            roleSection.style.display = 'block';
        } else {
            roleSection.style.display = 'none';
        }
    }
</script>

</body>
</html>