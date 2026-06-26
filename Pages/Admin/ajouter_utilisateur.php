<?php
// Page d'ajout d'utilisateur - Admin
require_once __DIR__ . '/../../Classes/Administrateur.php';
require_once __DIR__ . '/../../Classes/AgentMarche.php';

$page_title = 'Ajouter un utilisateur - Marché Virunga';

// Initialiser les variables
$error = null;
$success = null;
$selected_role = 'administrateur';

// Vérifier si le formulaire est soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'] ?? '';
    
    // Préparer les données communes
    $data = [
        'nom_complet' => $_POST['nom_complet'] ?? '',
        'sexe' => $_POST['sexe'] ?? 'Masculin',
        'nationalite' => $_POST['nationalite'] ?? 'Congolaise',
        'date_naissance' => $_POST['date_naissance'] ?? null,
        'adresse' => $_POST['adresse'] ?? '',
        'matricule' => $_POST['matricule'] ?? '',
        'nom_user' => $_POST['nom_user'] ?? '',
        'mot_de_passe' => $_POST['mot_de_passe'] ?? '',
        'telephone' => $_POST['telephone'] ?? '',
        'email' => $_POST['email'] ?? ''
    ];
    
    // Validation du mot de passe
    if ($data['mot_de_passe'] !== ($_POST['confirm_password'] ?? '')) {
        $error = 'password_match';
    } elseif (strlen($data['mot_de_passe']) < 6) {
        $error = 'password_short';
    } else {
        try {
            if ($role === 'administrateur') {
                $admin = new Administrateur();
                $result = $admin->register($data);
                
                if ($result['success']) {
                    $success = 'admin_added';
                } else {
                    $errors = $result['errors'] ?? [];
                    $error = implode(', ', $errors);
                }
            } elseif ($role === 'agent') {
                $agent = new AgentMarche();
                $result = $agent->register($data);
                
                if ($result['success']) {
                    $success = 'agent_added';
                } else {
                    $errors = $result['errors'] ?? [];
                    $error = implode(', ', $errors);
                }
            } else {
                $error = 'invalid_role';
            }
        } catch (Exception $e) {
            $error = 'db_error: ' . $e->getMessage();
        }
    }
    
    // Rediriger avec le message approprié
    if ($success) {
        header('Location: /pages/Admin/ajouter_utilisateur.php?success=' . $success);
        exit;
    } else {
        header('Location: /pages/Admin/ajouter_utilisateur.php?error=' . urlencode($error));
        exit;
    }
}

// Liste des rôles disponibles
$roles = [
    'administrateur' => 'Administrateur',
    'agent' => 'Agent du marché'
];

// Données simulées pour les secteurs (pour l'agent)
$secteurs = [
    ['id_secteur' => 1, 'designation' => 'Fruits et Légumes'],
    ['id_secteur' => 2, 'designation' => 'Boucherie'],
    ['id_secteur' => 3, 'designation' => 'Poissonnerie'],
    ['id_secteur' => 4, 'designation' => 'Épicerie'],
    ['id_secteur' => 5, 'designation' => 'Restauration']
];

// Utilisateur connecté (mock - à remplacer par la session réelle)
$user = (object) [
    'getNomComplet' => function() { return 'Admin Jean Mukendi'; }
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    
    <!-- Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        * { font-family: 'Inter', sans-serif; }
        .bg-primary { background: #1e3a5f; }
        .bg-accent { background: #f59e0b; }
        .text-primary { color: #1e3a5f; }
        .text-accent { color: #f59e0b; }
        .border-accent { border-color: #f59e0b; }
        
        .btn-accent { background: #f59e0b; color: white; transition: all 0.3s ease; }
        .btn-accent:hover { background: #d97706; transform: scale(1.02); }
        .btn-primary { background: #1e3a5f; color: white; transition: all 0.3s ease; }
        .btn-primary:hover { background: #2d6a9f; transform: scale(1.02); }
        .btn-outline { border: 2px solid #1e3a5f; color: #1e3a5f; transition: all 0.3s ease; }
        .btn-outline:hover { background: #1e3a5f; color: white; }
        
        .input-field:focus { border-color: #f59e0b; outline: none; box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.2); }
        .form-card { transition: all 0.3s ease; }
        .form-card:hover { box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
        
        .role-card { 
            transition: all 0.3s ease;
            cursor: pointer;
            border: 2px solid #e5e7eb;
        }
        .role-card:hover { border-color: #f59e0b; transform: translateY(-3px); }
        .role-card.selected { border-color: #f59e0b; background: #fffbeb; }
        .role-card .icon { font-size: 2.5rem; }
        
        .step-indicator { display: flex; justify-content: center; gap: 0.5rem; margin-bottom: 2rem; }
        .step { width: 10px; height: 10px; border-radius: 50%; background: #e5e7eb; transition: all 0.3s; }
        .step.active { background: #f59e0b; transform: scale(1.2); }
        .step.done { background: #1e3a5f; }
        
        .password-strength-bar { height: 4px; border-radius: 4px; transition: all 0.3s; }
    </style>
</head>
<body class="bg-gray-50">

<!-- ============================================ -->
<!-- NAVIGATION -->
<!-- ============================================ -->
<nav class="bg-primary text-white shadow-lg sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex justify-between items-center h-16">
            <div class="flex items-center space-x-3">
                <i class="fas fa-store text-accent text-2xl"></i>
                <span class="font-bold text-lg">Marché Virunga</span>
                <span class="text-xs bg-accent/20 px-2 py-1 rounded-full">Admin</span>
            </div>
            <div class="flex items-center space-x-4">
                <div class="hidden md:flex items-center space-x-2">
                    <span class="text-sm">
                        <i class="fas fa-user mr-1"></i>
                       
                    </span>
                </div>
                <a href="/pages/Admin/dashboard.php" class="text-sm bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg transition flex items-center">
                    <i class="fas fa-arrow-left mr-1"></i>
                    <span class="hidden sm:inline">Retour</span>
                </a>
            </div>
        </div>
    </div>
</nav>

<!-- ============================================ -->
<!-- CONTENU PRINCIPAL -->
<!-- ============================================ -->
<div class="max-w-4xl mx-auto px-4 py-8">

    <!-- En-tête -->
    <div class="mb-8">
        <div class="flex items-center space-x-3">
            <div class="w-12 h-12 bg-accent/20 rounded-xl flex items-center justify-center">
                <i class="fas fa-user-plus text-accent text-2xl"></i>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-primary">Ajouter un utilisateur</h1>
                <p class="text-gray-500 text-sm">Créer un compte administrateur ou agent du marché</p>
            </div>
        </div>
    </div>

    <!-- Messages -->
    <?php if (isset($_GET['success'])): ?>
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4">
            <i class="fas fa-check-circle mr-2"></i>
            <?php if ($_GET['success'] === 'admin_added'): ?>
                ✅ Administrateur ajouté avec succès !
            <?php elseif ($_GET['success'] === 'agent_added'): ?>
                ✅ Agent du marché ajouté avec succès !
            <?php else: ?>
                Utilisateur ajouté avec succès !
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-lg mb-4">
            <i class="fas fa-exclamation-circle mr-2"></i>
            <?php 
                $error = urldecode($_GET['error']);
                if (strpos($error, 'username_exists') !== false) echo 'Ce nom d\'utilisateur existe déjà.';
                else if (strpos($error, 'email_exists') !== false) echo 'Cet email est déjà utilisé.';
                else if (strpos($error, 'phone_exists') !== false) echo 'Ce numéro de téléphone est déjà utilisé.';
                else if ($error === 'password_match') echo 'Les mots de passe ne correspondent pas.';
                else if ($error === 'password_short') echo 'Le mot de passe doit contenir au moins 6 caractères.';
                else if ($error === 'empty') echo 'Veuillez remplir tous les champs obligatoires.';
                else if ($error === 'invalid_role') echo 'Rôle invalide.';
                else echo htmlspecialchars($error);
            ?>
        </div>
    <?php endif; ?>

    <!-- Formulaire -->
    <div class="bg-white rounded-2xl shadow-lg p-6 md:p-8 form-card">
        
        <!-- Indicateur d'étapes -->
        <div class="step-indicator">
            <div class="step active"></div>
            <div class="step"></div>
            <div class="step"></div>
        </div>

        <form method="POST" action="">
            
            <!-- ============================================ -->
            <!-- ÉTAPE 1: RÔLE -->
            <!-- ============================================ -->
            <div class="step-content" id="step1">
                <h2 class="text-lg font-bold text-primary mb-4">
                    <i class="fas fa-user-tag text-accent mr-2"></i>
                    Choisissez le rôle
                </h2>
                <p class="text-gray-500 text-sm mb-6">Sélectionnez le type de compte à créer</p>

                <div class="grid md:grid-cols-2 gap-4">
                    <!-- Administrateur -->
                    <div class="role-card rounded-xl p-6 text-center selected" onclick="selectRole('administrateur')" id="card-administrateur">
                        <div class="icon text-blue-600 mb-3">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <h3 class="font-bold text-gray-800">Administrateur</h3>
                        <p class="text-sm text-gray-500 mt-1">Gestion complète du système</p>
                        <ul class="text-xs text-gray-400 mt-3 space-y-1">
                            <li><i class="fas fa-check-circle text-green-500 mr-1"></i> Accès total</li>
                            <li><i class="fas fa-check-circle text-green-500 mr-1"></i> Gestion des utilisateurs</li>
                            <li><i class="fas fa-check-circle text-green-500 mr-1"></i> Gestion des étalages</li>
                        </ul>
                        <div class="mt-3">
                            <input type="radio" name="role" value="administrateur" checked class="hidden" id="role-administrateur">
                        </div>
                    </div>

                    <!-- Agent -->
                    <div class="role-card rounded-xl p-6 text-center" onclick="selectRole('agent')" id="card-agent">
                        <div class="icon text-purple-600 mb-3">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <h3 class="font-bold text-gray-800">Agent du marché</h3>
                        <p class="text-sm text-gray-500 mt-1">Gestion des opérations du marché</p>
                        <ul class="text-xs text-gray-400 mt-3 space-y-1">
                            <li><i class="fas fa-check-circle text-green-500 mr-1"></i> Gestion des étalages</li>
                            <li><i class="fas fa-check-circle text-green-500 mr-1"></i> Attribution des étalages</li>
                            <li><i class="fas fa-check-circle text-green-500 mr-1"></i> Suivi des paiements</li>
                        </ul>
                        <div class="mt-3">
                            <input type="radio" name="role" value="agent" class="hidden" id="role-agent">
                        </div>
                    </div>
                </div>

                <div class="mt-6">
                    <button type="button" onclick="nextStep()" 
                            class="w-full btn-accent py-3 rounded-lg font-bold text-lg">
                        Suivant <i class="fas fa-arrow-right ml-2"></i>
                    </button>
                </div>
            </div>

            <!-- ============================================ -->
            <!-- ÉTAPE 2: INFORMATIONS PERSONNELLES -->
            <!-- ============================================ -->
            <div class="step-content hidden" id="step2">
                <h2 class="text-lg font-bold text-primary mb-4">
                    <i class="fas fa-user text-accent mr-2"></i>
                    Informations personnelles
                </h2>
                <p class="text-gray-500 text-sm mb-6">Remplissez les informations de l'utilisateur</p>

                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Nom complet <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="nom_complet" required 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg input-field"
                               placeholder="Jean Mukendi">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Sexe</label>
                        <select name="sexe" class="w-full px-4 py-2 border border-gray-300 rounded-lg input-field">
                            <option value="Masculin">Masculin</option>
                            <option value="Féminin">Féminin</option>
                        </select>
                    </div>
                </div>

                <div class="grid md:grid-cols-2 gap-4 mt-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nationalité</label>
                        <input type="text" name="nationalite" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg input-field"
                               placeholder="Congolaise" value="Congolaise">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date de naissance</label>
                        <input type="date" name="date_naissance" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg input-field">
                    </div>
                </div>

                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Adresse</label>
                    <input type="text" name="adresse" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg input-field"
                           placeholder="Goma, RDC">
                </div>

                <div class="flex gap-3 mt-6">
                    <button type="button" onclick="prevStep()" 
                            class="flex-1 btn-outline py-3 rounded-lg font-bold">
                        <i class="fas fa-arrow-left mr-2"></i> Retour
                    </button>
                    <button type="button" onclick="nextStep()" 
                            class="flex-1 btn-accent py-3 rounded-lg font-bold">
                        Suivant <i class="fas fa-arrow-right ml-2"></i>
                    </button>
                </div>
            </div>

            <!-- ============================================ -->
            <!-- ÉTAPE 3: COORDONNÉES ET SÉCURITÉ -->
            <!-- ============================================ -->
            <div class="step-content hidden" id="step3">
                <h2 class="text-lg font-bold text-primary mb-4">
                    <i class="fas fa-lock text-accent mr-2"></i>
                    Coordonnées et sécurité
                </h2>
                <p class="text-gray-500 text-sm mb-6">Configurez les accès de l'utilisateur</p>

                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Téléphone <span class="text-red-500">*</span>
                        </label>
                        <input type="tel" name="telephone" required 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg input-field"
                               placeholder="+243 81 234 5678">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Email <span class="text-red-500">*</span>
                        </label>
                        <input type="email" name="email" required 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg input-field"
                               placeholder="nom@email.com">
                    </div>
                </div>

                <div class="grid md:grid-cols-2 gap-4 mt-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Nom d'utilisateur <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="nom_user" required 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg input-field"
                               placeholder="nom_utilisateur">
                        <p class="text-xs text-gray-400 mt-1">Utilisé pour la connexion</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Matricule <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="matricule" required 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg input-field"
                               placeholder="ADM2024001 ou AGM2024001">
                        <p class="text-xs text-gray-400 mt-1">Format: ADM2024001 (Admin) ou AGM2024001 (Agent)</p>
                    </div>
                </div>

                <div class="grid md:grid-cols-2 gap-4 mt-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Mot de passe <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input type="password" name="mot_de_passe" id="password" required 
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg input-field pr-10"
                                   placeholder="Minimum 6 caractères">
                            <button type="button" onclick="togglePassword('password', 'eye-icon1')" 
                                    class="absolute right-3 top-2.5 text-gray-400 hover:text-gray-600">
                                <i id="eye-icon1" class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="mt-2">
                            <div class="flex items-center space-x-2 text-xs">
                                <span id="password-strength" class="text-gray-400">Force : </span>
                                <div class="flex-1 h-1 bg-gray-200 rounded overflow-hidden">
                                    <div id="strength-bar" class="password-strength-bar w-0 bg-red-500"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Confirmer le mot de passe <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input type="password" name="confirm_password" id="confirm_password" required 
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg input-field pr-10"
                                   placeholder="Confirmez le mot de passe">
                            <button type="button" onclick="togglePassword('confirm_password', 'eye-icon2')" 
                                    class="absolute right-3 top-2.5 text-gray-400 hover:text-gray-600">
                                <i id="eye-icon2" class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="flex gap-3 mt-6">
                    <button type="button" onclick="prevStep()" 
                            class="flex-1 btn-outline py-3 rounded-lg font-bold">
                        <i class="fas fa-arrow-left mr-2"></i> Retour
                    </button>
                    <button type="submit" class="flex-1 btn-accent py-3 rounded-lg font-bold text-lg">
                        <i class="fas fa-save mr-2"></i> Ajouter l'utilisateur
                    </button>
                </div>

                <p class="text-xs text-gray-400 text-center mt-4">
                    <i class="fas fa-info-circle mr-1"></i>
                    L'utilisateur recevra ses identifiants par email
                </p>
            </div>

        </form>
    </div>

    <!-- Bouton retour -->
    <div class="mt-4 text-center">
        <a href="/pages/Admin/dashboard.php" class="text-sm text-gray-400 hover:text-gray-600 transition">
            <i class="fas fa-arrow-left mr-1"></i> Retour au tableau de bord
        </a>
    </div>

</div>

<!-- ============================================ -->
<!-- SCRIPTS -->
<!-- ============================================ -->
<script>
    let currentStep = 1;
    const totalSteps = 3;

    // ============================================
    // NAVIGATION ENTRE LES ÉTAPES
    // ============================================
    
    function nextStep() {
        // Valider l'étape 1
        if (currentStep === 1) {
            const roleSelected = document.querySelector('input[name="role"]:checked');
            if (!roleSelected) {
                alert('Veuillez sélectionner un rôle.');
                return;
            }
        }

        // Valider l'étape 2
        if (currentStep === 2) {
            const nomComplet = document.querySelector('input[name="nom_complet"]');
            if (!nomComplet.value.trim()) {
                alert('Veuillez entrer le nom complet.');
                nomComplet.focus();
                return;
            }
        }

        if (currentStep < totalSteps) {
            document.getElementById(`step${currentStep}`).classList.add('hidden');
            currentStep++;
            document.getElementById(`step${currentStep}`).classList.remove('hidden');
            updateSteps();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    }

    function prevStep() {
        if (currentStep > 1) {
            document.getElementById(`step${currentStep}`).classList.add('hidden');
            currentStep--;
            document.getElementById(`step${currentStep}`).classList.remove('hidden');
            updateSteps();
            window.scrollTo({ top: 0, behavior: 'smooth' });
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

    // ============================================
    // SÉLECTION DU RÔLE
    // ============================================
    
    function selectRole(role) {
        // Mettre à jour les cartes
        document.querySelectorAll('.role-card').forEach(card => {
            card.classList.remove('selected');
        });
        document.getElementById(`card-${role}`).classList.add('selected');

        // Mettre à jour les radios
        document.querySelectorAll('input[name="role"]').forEach(input => {
            input.checked = false;
        });
        document.getElementById(`role-${role}`).checked = true;

        // Suggérer un matricule
        const matriculeInput = document.querySelector('input[name="matricule"]');
        const year = new Date().getFullYear();
        const random = String(Math.floor(Math.random() * 1000)).padStart(4, '0');
        
        if (role === 'administrateur') {
            if (!matriculeInput.value || matriculeInput.value.startsWith('AGM')) {
                matriculeInput.value = `ADM${year}${random}`;
            }
        } else {
            if (!matriculeInput.value || matriculeInput.value.startsWith('ADM')) {
                matriculeInput.value = `AGM${year}${random}`;
            }
        }
    }

    // ============================================
    // AFFICHER/MASQUER LE MOT DE PASSE
    // ============================================
    
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

    // ============================================
    // FORCE DU MOT DE PASSE
    // ============================================
    
    document.getElementById('password').addEventListener('input', function() {
        const password = this.value;
        let strength = 0;
        
        if (password.length >= 6) strength++;
        if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
        if (password.match(/[0-9]/) || password.match(/[^a-zA-Z0-9]/)) strength++;
        
        const bar = document.getElementById('strength-bar');
        const label = document.getElementById('password-strength');
        const colors = ['#ef4444', '#f59e0b', '#22c55e'];
        const labels = ['Faible', 'Moyen', 'Fort'];
        const widths = ['33%', '66%', '100%'];
        
        if (strength === 0) {
            bar.style.width = '0';
            label.textContent = 'Force : ';
        } else {
            bar.style.width = widths[strength - 1];
            bar.style.background = colors[strength - 1];
            label.textContent = `Force : ${labels[strength - 1]}`;
        }
    });

    // ============================================
    // GÉNÉRATION DU MATRICULE PAR DÉFAUT
    // ============================================
    
    document.addEventListener('DOMContentLoaded', function() {
        const year = new Date().getFullYear();
        const random = String(Math.floor(Math.random() * 1000)).padStart(4, '0');
        document.querySelector('input[name="matricule"]').value = `ADM${year}${random}`;
    });
</script>

</body>
</html>