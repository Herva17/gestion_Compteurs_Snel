<?php
// Page de connexion SNEL - Gestion des compteurs
require_once __DIR__ . '/Classes/Database.php';
require_once __DIR__ . '/Classes/Client.php';
require_once __DIR__ . '/Classes/Utilisateur.php';

$page_title = 'Connexion - SNEL Gestion des compteurs';

// Initialiser les variables
$error = null;
$error_detail = null;

// Démarrer la session si ce n'est pas déjà fait
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Si déjà connecté, rediriger
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    if (isset($_SESSION['role'])) {
        if ($_SESSION['role'] === 'client') {
            header('Location: Pages/Client/dashboard.php');
        } elseif ($_SESSION['role'] === 'administrateur') {
            header('Location: Pages/Admin/dashboard.php');
        } else {
            header('Location: Pages/Agent/dashboard.php');
        }
        exit;
    }
}

// Si le formulaire est soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $mot_de_passe = $_POST['mot_de_passe'] ?? '';
    $role = $_POST['role'] ?? 'client';
    $remember = isset($_POST['remember']);
    
    if (empty($email) || empty($mot_de_passe)) {
        $error = 'empty';
    } else {
        try {
            $loggedIn = false;
            
            if ($role === 'client') {
                $client = new Client();
                $result = $client->authentifier($email, $mot_de_passe);
                
                if ($result['success']) {
                    $_SESSION['role'] = 'client';
                    $_SESSION['client_id'] = $client->getIdClient();
                    $_SESSION['client_nom'] = $client->getNom() . ' ' . $client->getPrenom();
                    $_SESSION['logged_in'] = true;
                    $loggedIn = true;
                    header('Location: pages/Client/dashboard.php');
                    exit;
                } else {
                    $error = 'not_found';
                    $error_detail = $result['error'] ?? 'Identifiants incorrects';
                }
            }
            
            if ($role === 'utilisateur' && !$loggedIn) {
                $utilisateur = new Utilisateur();
                $result = $utilisateur->authentifier($email, $mot_de_passe);
                
                if ($result['success']) {
                    $_SESSION['role'] = $utilisateur->getRole();
                    $_SESSION['user_id'] = $utilisateur->getIdUtilisateur();
                    $_SESSION['user_nom'] = $utilisateur->getNom() . ' ' . $utilisateur->getPrenom();
                    $_SESSION['logged_in'] = true;
                    $loggedIn = true;
                    
                    if ($utilisateur->getRole() === 'administrateur') {
                        header('Location: pages/Admin/dashboard.php');
                    } else {
                        header('Location: pages/Agent/dashboard.php');
                    }
                    exit;
                } else {
                    $error = 'not_found';
                    $error_detail = $result['error'] ?? 'Identifiants incorrects';
                }
            }
            
            if (!$loggedIn) {
                $error = 'not_found';
                if (empty($error_detail)) {
                    $error_detail = 'Aucun compte trouvé avec ces identifiants.';
                }
            }
            
        } catch (Exception $e) {
            $error = 'unknown';
            $error_detail = $e->getMessage();
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
        
        .btn-outline-light {
            border: 2px solid rgba(255,255,255,0.3);
            color: white;
            background: transparent;
            transition: all 0.3s ease;
        }
        .btn-outline-light:hover {
            background: rgba(255,255,255,0.1);
            border-color: white;
            transform: translateY(-2px);
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
        
        .stat-box {
            background: rgba(255,255,255,0.06);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            padding: 16px;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .stat-box:hover {
            background: rgba(255,255,255,0.1);
            transform: translateY(-2px);
        }
        
        .demo-card {
            background: rgba(255,255,255,0.06);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 16px;
            padding: 20px;
            transition: all 0.3s ease;
        }
        
        .demo-card:hover {
            background: rgba(255,255,255,0.1);
            transform: translateY(-4px);
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
        <div class="grid lg:grid-cols-2 gap-16 items-center">
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
                    <h2 class="text-2xl font-bold text-gray-800 mt-6">Bienvenue</h2>
                    <p class="text-gray-500 text-sm mt-1">Connectez-vous à votre espace</p>
                </div>
                
                <!-- Messages -->
                <?php if (isset($error)): ?>
                    <div class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-xl mb-4 text-sm flex items-start">
                        <i class="fas fa-exclamation-circle mt-0.5 mr-3"></i>
                        <span>
                            <?php 
                                if ($error === 'empty') echo 'Veuillez remplir tous les champs.';
                                elseif ($error === 'not_found') echo isset($error_detail) ? htmlspecialchars($error_detail) : 'Aucun compte trouvé.';
                                else echo isset($error_detail) ? htmlspecialchars($error_detail) : 'Une erreur est survenue.';
                            ?>
                        </span>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['success']) && $_GET['success'] === 'register'): ?>
                    <div class="bg-green-50 border border-green-200 text-green-600 px-4 py-3 rounded-xl mb-4 text-sm flex items-start">
                        <i class="fas fa-check-circle mt-0.5 mr-3"></i>
                        <span>Inscription réussie ! Connectez-vous maintenant.</span>
                    </div>
                <?php endif; ?>
                
                <!-- Formulaire -->
                <div class="auth-card p-8">
                    <form method="POST" action="">
                        <!-- Sélecteur de rôle -->
                        <div class="role-selector">
                            <button type="button" class="role-btn active" onclick="selectRole('client')" id="role-client">
                                <i class="fas fa-user icon"></i>Client
                            </button>
                            <button type="button" class="role-btn" onclick="selectRole('utilisateur')" id="role-utilisateur">
                                <i class="fas fa-user-tie icon"></i>Agent/Admin
                            </button>
                        </div>
                        
                        <input type="hidden" name="role" id="role-input" value="client">
                        
                        <!-- Email -->
                        <div class="mb-4">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-envelope text-secondary mr-2"></i>Email ou Téléphone
                            </label>
                            <input type="text" name="email" required 
                                   class="input-field"
                                   placeholder="Entrez votre email ou téléphone"
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                        </div>
                        
                        <!-- Mot de passe -->
                        <div class="mb-6">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-lock text-secondary mr-2"></i>Mot de passe
                            </label>
                            <div class="relative">
                                <input type="password" name="mot_de_passe" id="password" required 
                                       class="input-field pr-12"
                                       placeholder="Entrez votre mot de passe">
                                <button type="button" onclick="togglePassword()" 
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition">
                                    <i id="eye-icon" class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Options -->
                        <div class="flex items-center justify-between mb-6">
                            <div class="flex items-center">
                                <input type="checkbox" name="remember" id="remember" 
                                       class="w-4 h-4 text-secondary border-gray-300 rounded focus:ring-secondary focus:ring-offset-0">
                                <label for="remember" class="ml-2 text-sm text-gray-600">Se souvenir de moi</label>
                            </div>
                            <a href="forgot-password.php" class="text-sm text-secondary hover:text-secondary/80 font-medium transition">
                                Mot de passe oublié ?
                            </a>
                        </div>
                        
                        <!-- Bouton -->
                        <button type="submit" class="btn-secondary w-full py-3.5 rounded-xl font-bold text-lg transition-all shadow-lg hover:shadow-xl">
                            <i class="fas fa-sign-in-alt mr-2"></i>Se connecter
                        </button>
                    </form>
                    
                    <!-- Lien inscription -->
                    <div class="mt-6 text-center">
                        <p class="text-sm text-gray-600">
                            Pas encore de compte ? 
                            <a href="register.php" class="text-primary font-semibold hover:text-secondary transition">
                                Créer un compte
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
            
            <!-- Section droite - Visuel -->
            <div class="hidden lg:block">
                <div class="relative float-slow">
                    <div class="demo-card p-6">
                        <div class="text-center mb-6">
                            <div class="inline-block bg-secondary/20 px-4 py-1.5 rounded-full text-accent text-sm font-semibold">
                                <i class="fas fa-bolt mr-2"></i> Service public
                            </div>
                            <h3 class="text-2xl font-bold text-white mt-4">
                                Gérez vos compteurs en ligne
                            </h3>
                            <p class="text-blue-200 mt-2 text-sm">
                                Accédez à votre consommation, payez vos factures et suivez votre historique.
                            </p>
                        </div>
                        
                        <!-- Stats -->
                        <div class="grid grid-cols-3 gap-4 mb-6">
                            <div class="stat-box">
                                <div class="text-2xl font-bold text-white">50K+</div>
                                <p class="text-blue-300 text-xs font-medium">Clients</p>
                            </div>
                            <div class="stat-box">
                                <div class="text-2xl font-bold text-accent">99.5%</div>
                                <p class="text-blue-300 text-xs font-medium">Disponibilité</p>
                            </div>
                            <div class="stat-box">
                                <div class="text-2xl font-bold text-white">24/7</div>
                                <p class="text-blue-300 text-xs font-medium">Support</p>
                            </div>
                        </div>
                        
                        <!-- Carte consommation -->
                        <div class="bg-white/5 rounded-xl p-4 border border-white/10">
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="text-white/70 text-sm">Consommation du mois</div>
                                    <div class="text-accent text-3xl font-bold">1,247 kWh</div>
                                </div>
                                <div class="text-right">
                                    <span class="text-green-400 text-sm font-semibold">
                                        <i class="fas fa-arrow-up mr-1"></i> +8%
                                    </span>
                                </div>
                            </div>
                            <div class="mt-3 bg-white/10 rounded-full h-2 overflow-hidden">
                                <div class="bg-accent rounded-full h-2" style="width: 68%"></div>
                            </div>
                            <div class="flex justify-between text-xs text-blue-300 mt-2">
                                <span>0 kWh</span>
                                <span>2,000 kWh</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Éléments flottants -->
                    <div class="absolute -top-4 -right-4 w-16 h-16 bg-accent/10 rounded-full backdrop-blur flex items-center justify-center border border-accent/20">
                        <i class="fas fa-bolt text-accent text-2xl"></i>
                    </div>
                    <div class="absolute -bottom-4 -left-4 w-12 h-12 bg-secondary/10 rounded-full backdrop-blur flex items-center justify-center border border-secondary/20">
                        <i class="fas fa-chart-line text-secondary text-xl"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function togglePassword() {
        const password = document.getElementById('password');
        const icon = document.getElementById('eye-icon');
        if (password.type === 'password') {
            password.type = 'text';
            icon.className = 'fas fa-eye-slash';
        } else {
            password.type = 'password';
            icon.className = 'fas fa-eye';
        }
    }
    
    function selectRole(role) {
        document.querySelectorAll('.role-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        document.getElementById('role-' + role).classList.add('active');
        document.getElementById('role-input').value = role;
        
        const input = document.querySelector('input[name="email"]');
        const placeholders = {
            'client': 'Entrez votre email ou téléphone',
            'utilisateur': 'Entrez votre email (Agent/Admin)'
        };
        input.placeholder = placeholders[role] || 'Entrez votre email ou téléphone';
    }
</script>

</body>
</html>