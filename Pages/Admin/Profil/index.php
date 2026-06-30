<?php
// Pages/Admin/Profil/index.php
session_start();
require_once __DIR__ . '/../../../Classes/Database.php';
require_once __DIR__ . '/../../../Classes/Utilisateur.php';
require_once __DIR__ . '/../../../Classes/Notification.php';

$utilisateur = new Utilisateur();
if (!$utilisateur->isLoggedIn()) {
    header('Location: /login.php');
    exit;
}

$user = $utilisateur->getLoggedInUser();
if (!$user) {
    session_destroy();
    header('Location: /login.php?error=session_expired');
    exit;
}

if ($user->getRole() !== 'administrateur') {
    header('Location: /Pages/Agent/dashboard.php');
    exit;
}

$idUtilisateur = $user->getIdUtilisateur();
$db = Database::getInstance()->getConnection();

$message = '';
$messageType = '';

// Mise à jour du profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_profile') {
        $nom = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        
        $errors = [];
        if (empty($nom)) $errors[] = 'Le nom est requis.';
        if (empty($prenom)) $errors[] = 'Le prénom est requis.';
        if (empty($email)) $errors[] = 'L\'email est requis.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email invalide.';
        
        if (empty($errors)) {
            try {
                $stmt = $db->prepare("
                    UPDATE Utilisateurs 
                    SET nom = ?, prenom = ?, telephone = ?, email = ?
                    WHERE idUtilisateur = ?
                ");
                $stmt->execute([$nom, $prenom, $telephone, $email, $idUtilisateur]);
                $message = '✅ Profil mis à jour avec succès !';
                $messageType = 'success';
                
                // Mettre à jour la session
                $_SESSION['user_nom'] = $prenom . ' ' . $nom;
                
                // Recharger les données
                $user = $utilisateur->getLoggedInUser();
            } catch (Exception $e) {
                $message = '❌ Erreur: ' . $e->getMessage();
                $messageType = 'error';
            }
        } else {
            $message = '❌ ' . implode('<br>', $errors);
            $messageType = 'error';
        }
    } elseif ($_POST['action'] === 'change_password') {
        $old_password = $_POST['old_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($old_password) || empty($new_password) || empty($confirm_password)) {
            $message = '❌ Veuillez remplir tous les champs.';
            $messageType = 'error';
        } elseif (strlen($new_password) < 6) {
            $message = '❌ Le mot de passe doit contenir au moins 6 caractères.';
            $messageType = 'error';
        } elseif ($new_password !== $confirm_password) {
            $message = '❌ Les mots de passe ne correspondent pas.';
            $messageType = 'error';
        } else {
            $result = $utilisateur->changerMotDePasse($idUtilisateur, $old_password, $new_password);
            if ($result['success']) {
                $message = '✅ Mot de passe modifié avec succès !';
                $messageType = 'success';
            } else {
                $message = '❌ ' . $result['error'];
                $messageType = 'error';
            }
        }
    }
}

// Notifications
$notification = new Notification();
$totalNotifs = $notification->countNonLuesUtilisateur($idUtilisateur);

$page_title = 'Mon profil - SNEL Admin';
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
        :root { --primary: #1a365d; --secondary: #c05621; --accent: #ecc94b; }
        .bg-primary { background: var(--primary); }
        .bg-secondary { background: var(--secondary); }
        .text-primary { color: var(--primary); }
        .text-secondary { color: var(--secondary); }
        .btn-primary { background: var(--primary); color: white; transition: all 0.3s ease; }
        .btn-primary:hover { background: #0f2440; transform: translateY(-2px); }
        .btn-secondary { background: var(--secondary); color: white; transition: all 0.3s ease; }
        .btn-secondary:hover { background: #a0441a; transform: translateY(-2px); }
        .btn-success { background: #16a34a; color: white; transition: all 0.3s ease; }
        .btn-success:hover { background: #15803d; transform: translateY(-2px); }
        .input-field { width: 100%; padding: 0.75rem 1rem; border: 2px solid #e2e8f0; border-radius: 0.75rem; transition: all 0.3s ease; background: white; }
        .input-field:focus { border-color: var(--secondary); outline: none; box-shadow: 0 0 0 4px rgba(192,86,33,0.1); }
        .card-snel { background: white; border-radius: 1rem; padding: 1.5rem; border: 1px solid #e2e8f0; transition: all 0.3s ease; }
        .card-snel:hover { box-shadow: 0 8px 30px rgba(0,0,0,0.06); }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.65rem; font-weight: 600; text-transform: uppercase; }
        .status-actif { background: #dcfce7; color: #166534; }
        .status-admin { background: #dbeafe; color: #1e40af; }
        .sidebar { background: var(--primary); min-height: 100vh; position: fixed; top: 0; left: 0; width: 260px; z-index: 100; overflow-y: auto; }
        .nav-item { display: flex; align-items: center; padding: 0.7rem 1rem; color: rgba(255,255,255,0.6); border-radius: 0.75rem; transition: all 0.3s ease; gap: 0.75rem; font-size: 0.9rem; text-decoration: none; }
        .nav-item:hover { background: rgba(255,255,255,0.08); color: white; }
        .nav-item.active { background: var(--secondary); color: white; box-shadow: 0 4px 15px rgba(192,86,33,0.3); }
        .topbar { background: white; border-bottom: 1px solid #e2e8f0; padding: 0.75rem 2rem; position: sticky; top: 0; z-index: 50; }
        .main-content { margin-left: 260px; min-height: 100vh; background: #f8fafc; }
        .user-dropdown { position: absolute; top: 100%; right: 0; width: 240px; background: white; border-radius: 1rem; box-shadow: 0 20px 60px rgba(0,0,0,0.15); border: 1px solid #e2e8f0; display: none; z-index: 60; overflow: hidden; }
        .user-dropdown.open { display: block; }
        .mobile-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 90; }
        .mobile-overlay.open { display: block; }
        .avatar { width: 100px; height: 100px; border-radius: 50%; background: var(--secondary); display: flex; align-items: center; justify-content: center; color: white; font-size: 2.5rem; font-weight: bold; }
        .info-item { display: flex; justify-content: space-between; padding: 0.75rem; border-bottom: 1px solid #f1f5f9; }
        .info-item:last-child { border-bottom: none; }
        .info-label { font-size: 0.875rem; color: #6b7280; }
        .info-value { font-weight: 500; color: #1a365d; }
        @media (max-width: 1024px) { .sidebar { transform: translateX(-100%); transition: transform 0.3s ease; } .sidebar.open { transform: translateX(0); } .main-content { margin-left: 0; } .topbar { padding: 0.75rem 1rem; } }
    </style>
</head>
<body>

<div id="mobileOverlay" class="mobile-overlay" onclick="closeMobileMenu()"></div>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="p-5">
        <div class="flex items-center space-x-3 mb-8">
            <div class="w-10 h-10 bg-secondary rounded-xl flex items-center justify-center shadow-lg">
                <i class="fas fa-bolt text-white text-lg"></i>
            </div>
            <div>
                <span class="text-white font-bold text-xl tracking-tight">SNEL</span>
                <span class="text-accent text-[10px] block -mt-0.5 font-semibold tracking-wider">Administration</span>
            </div>
        </div>
        
        <nav class="space-y-1">
            <a href="../dashboard.php" class="nav-item">
                <i class="fas fa-home w-5 text-center"></i> Tableau de bord
            </a>
            <a href="../Utilisateurs/index.php" class="nav-item">
                <i class="fas fa-users-cog w-5 text-center"></i> Utilisateurs
            </a>
            <a href="../Clients/index.php" class="nav-item">
                <i class="fas fa-user-friends w-5 text-center"></i> Clients
            </a>
            <a href="../Compteurs/index.php" class="nav-item">
                <i class="fas fa-gauge-high w-5 text-center"></i> Compteurs
            </a>
            <a href="../Tarifs/index.php" class="nav-item">
                <i class="fas fa-tags w-5 text-center"></i> Tarifs
            </a>
            <a href="../Rapports/index.php" class="nav-item">
                <i class="fas fa-chart-pie w-5 text-center"></i> Rapports
            </a>
            <a href="index.php" class="nav-item active">
                <i class="fas fa-user-cog w-5 text-center"></i> Mon profil
            </a>
        </nav>
        
        <div class="absolute bottom-4 left-4 right-4">
            <a href="../../../logout.php" class="nav-item text-red-400 hover:text-red-300 hover:bg-red-500/10">
                <i class="fas fa-sign-out-alt w-5 text-center"></i> Déconnexion
            </a>
            <div class="text-center text-xs text-white/30 mt-3">v2.0.1 | Admin</div>
        </div>
    </div>
</aside>

<!-- Contenu principal -->
<div class="main-content">

    <header class="topbar">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <button onclick="toggleMobileMenu()" class="lg:hidden text-primary text-2xl">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <h1 class="text-lg font-bold text-primary hidden sm:block">Mon profil</h1>
                    <p class="text-xs text-gray-400 hidden sm:block">Gestion de mon compte</p>
                </div>
            </div>
            
            <div class="flex items-center space-x-4">
                <div class="relative">
                    <button onclick="toggleUserMenu()" class="flex items-center space-x-2 hover:bg-gray-100 p-1.5 rounded-full transition">
                        <div class="w-9 h-9 bg-secondary rounded-full flex items-center justify-center text-white font-bold text-sm">
                            <?= strtoupper(substr($user->getPrenom(), 0, 1) . substr($user->getNom(), 0, 1)) ?>
                        </div>
                        <span class="hidden md:block text-sm font-medium text-gray-700">
                            <?= htmlspecialchars($user->getPrenom()) ?>
                        </span>
                        <i class="fas fa-chevron-down text-xs text-gray-400 hidden md:block"></i>
                    </button>
                    
                    <div id="userDropdown" class="user-dropdown">
                        <div class="p-4 bg-gray-50 border-b border-gray-100">
                            <p class="font-bold text-primary"><?= htmlspecialchars($user->getNom() . ' ' . $user->getPrenom()) ?></p>
                            <p class="text-xs text-gray-500"><?= htmlspecialchars($user->getEmail()) ?></p>
                            <span class="text-xs text-green-600"><i class="fas fa-circle text-[6px] mr-1"></i> En ligne</span>
                        </div>
                        <div class="py-1">
                            <hr class="my-1">
                            <a href="../../../logout.php" class="flex items-center px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition">
                                <i class="fas fa-sign-out-alt w-5 text-red-400"></i> Déconnexion
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="p-4 lg:p-6">
        <div class="max-w-4xl mx-auto">
            
            <?php if ($message): ?>
                <div class="<?= $messageType === 'success' ? 'bg-green-50 border-green-200 text-green-700' : 'bg-red-50 border-red-200 text-red-700' ?> border rounded-xl px-4 py-3 mb-4">
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <div class="grid md:grid-cols-2 gap-6">
                <!-- Informations personnelles -->
                <div class="card-snel">
                    <div class="flex items-center space-x-4 mb-6">
                        <div class="avatar"><?= strtoupper(substr($user->getPrenom(), 0, 1) . substr($user->getNom(), 0, 1)) ?></div>
                        <div>
                            <h2 class="text-xl font-bold text-primary"><?= htmlspecialchars($user->getNom() . ' ' . $user->getPrenom()) ?></h2>
                            <span class="status-badge status-admin"><i class="fas fa-shield-alt mr-1"></i> Administrateur</span>
                            <span class="status-badge status-actif ml-1">Actif</span>
                        </div>
                    </div>

                    <h3 class="font-bold text-primary mb-4">Modifier mes informations</h3>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_profile">
                        <div class="grid md:grid-cols-2 gap-4">
                            <div class="mb-3">
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Nom</label>
                                <input type="text" name="nom" class="input-field" value="<?= htmlspecialchars($user->getNom()) ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Prénom</label>
                                <input type="text" name="prenom" class="input-field" value="<?= htmlspecialchars($user->getPrenom()) ?>" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Email</label>
                            <input type="email" name="email" class="input-field" value="<?= htmlspecialchars($user->getEmail()) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Téléphone</label>
                            <input type="tel" name="telephone" class="input-field" value="<?= htmlspecialchars($user->getTelephone()) ?>">
                        </div>
                        <button type="submit" class="btn-secondary w-full py-2.5 rounded-lg font-semibold">
                            <i class="fas fa-save mr-2"></i> Mettre à jour
                        </button>
                    </form>
                </div>

                <!-- Modifier le mot de passe -->
                <div class="card-snel">
                    <h3 class="font-bold text-primary mb-4">
                        <i class="fas fa-lock text-secondary mr-2"></i>
                        Modifier le mot de passe
                    </h3>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="change_password">
                        <div class="mb-3">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Mot de passe actuel</label>
                            <input type="password" name="old_password" class="input-field" placeholder="Entrez votre mot de passe actuel" required>
                        </div>
                        <div class="mb-3">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Nouveau mot de passe</label>
                            <input type="password" name="new_password" class="input-field" placeholder="Minimum 6 caractères" required>
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Confirmer le mot de passe</label>
                            <input type="password" name="confirm_password" class="input-field" placeholder="Confirmez votre mot de passe" required>
                        </div>
                        <button type="submit" class="btn-primary w-full py-2.5 rounded-lg font-semibold">
                            <i class="fas fa-key mr-2"></i> Modifier le mot de passe
                        </button>
                    </form>

                    <hr class="my-6">

                    <h3 class="font-bold text-primary mb-4">
                        <i class="fas fa-info-circle text-secondary mr-2"></i>
                        Informations du compte
                    </h3>
                    <div class="space-y-1">
                        <div class="info-item">
                            <span class="info-label">Rôle</span>
                            <span class="info-value">
                                <span class="status-badge status-admin">Administrateur</span>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Statut</span>
                            <span class="info-value">
                                <span class="status-badge status-actif">Actif</span>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Date d'inscription</span>
                            <span class="info-value"><?= date('d/m/Y', strtotime($user->getDateCreation())) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Dernière connexion</span>
                            <span class="info-value"><?= $user->toArray()['derniereConnexion'] ? date('d/m/Y H:i', strtotime($user->toArray()['derniereConnexion'])) : 'Jamais' ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function toggleMobileMenu() {
        document.getElementById('sidebar').classList.toggle('open');
        document.getElementById('mobileOverlay').classList.toggle('open');
    }
    function closeMobileMenu() {
        document.getElementById('sidebar').classList.remove('open');
        document.getElementById('mobileOverlay').classList.remove('open');
    }
    function toggleUserMenu() {
        document.getElementById('userDropdown').classList.toggle('open');
    }
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.user-dropdown') && !e.target.closest('[onclick="toggleUserMenu()"]')) {
            document.getElementById('userDropdown').classList.remove('open');
        }
    });
</script>

</body>
</html>