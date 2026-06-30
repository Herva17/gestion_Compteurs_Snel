<?php
// Pages/Admin/Utilisateurs/details.php
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

$id = $_GET['id'] ?? 0;
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

$stmt = $db->prepare("SELECT * FROM Utilisateurs WHERE idUtilisateur = ?");
$stmt->execute([$id]);
$utilisateurDetail = $stmt->fetch();

if (!$utilisateurDetail) {
    header('Location: index.php');
    exit;
}

// Notifications
$notification = new Notification();
$totalNotifs = $notification->countNonLuesUtilisateur($idUtilisateur);

$page_title = 'Détails de l\'utilisateur - SNEL Admin';
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
        .btn-outline { border: 2px solid var(--secondary); color: var(--secondary); background: transparent; transition: all 0.3s ease; }
        .btn-outline:hover { background: var(--secondary); color: white; }
        .btn-danger { background: #dc2626; color: white; transition: all 0.3s ease; }
        .btn-danger:hover { background: #b91c1c; transform: translateY(-2px); }
        .card-snel { background: white; border-radius: 1rem; padding: 1.25rem; border: 1px solid #e2e8f0; transition: all 0.3s ease; }
        .card-snel:hover { box-shadow: 0 8px 30px rgba(0,0,0,0.06); }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.65rem; font-weight: 600; text-transform: uppercase; }
        .status-actif { background: #dcfce7; color: #166534; }
        .status-inactif { background: #fef3c7; color: #92400e; }
        .status-admin { background: #dbeafe; color: #1e40af; }
        .status-agent { background: #e0e7ff; color: #3730a3; }
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
            <a href="index.php" class="nav-item active">
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
            <a href="../Profil/index.php" class="nav-item">
                <i class="fas fa-user-cog w-5 text-center"></i> Mon profil
            </a>
        </nav>
        
        <div class="absolute bottom-4 left-4 right-4">
            <a href="../../logout.php" class="nav-item text-red-400 hover:text-red-300 hover:bg-red-500/10">
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
                <a href="index.php" class="text-primary hover:text-secondary transition">
                    <i class="fas fa-arrow-left"></i> Retour
                </a>
                <h1 class="text-lg font-bold text-primary hidden sm:block">Détails de l'utilisateur</h1>
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
                            <a href="../Profil/index.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition">
                                <i class="fas fa-user w-5 text-gray-400"></i> Mon profil
                            </a>
                            <hr class="my-1">
                            <a href="../../logout.php" class="flex items-center px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition">
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
            
            <!-- En-tête -->
            <div class="bg-white rounded-xl shadow-sm p-6 mb-6 border-l-4 border-secondary">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-xs text-gray-500">ID Utilisateur</p>
                        <h2 class="text-2xl font-bold text-primary">#<?= $utilisateurDetail['idUtilisateur'] ?></h2>
                        <p class="text-sm text-gray-500">Créé le <?= date('d/m/Y H:i', strtotime($utilisateurDetail['dateCreation'])) ?></p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <span class="status-badge <?= $utilisateurDetail['role'] === 'administrateur' ? 'status-admin' : 'status-agent' ?>">
                            <i class="fas <?= $utilisateurDetail['role'] === 'administrateur' ? 'fa-shield-alt' : 'fa-user-tie' ?> mr-1"></i>
                            <?= ucfirst($utilisateurDetail['role']) ?>
                        </span>
                        <span class="status-badge <?= $utilisateurDetail['statut'] === 'actif' ? 'status-actif' : 'status-inactif' ?>">
                            <?= ucfirst($utilisateurDetail['statut']) ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Informations -->
            <div class="grid md:grid-cols-2 gap-6">
                <div class="card-snel">
                    <h3 class="font-bold text-primary mb-4">
                        <i class="fas fa-user text-secondary mr-2"></i>
                        Informations personnelles
                    </h3>
                    <div class="space-y-1">
                        <div class="info-item">
                            <span class="info-label">Nom complet</span>
                            <span class="info-value"><?= htmlspecialchars($utilisateurDetail['prenom'] . ' ' . $utilisateurDetail['nom']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Email</span>
                            <span class="info-value"><?= htmlspecialchars($utilisateurDetail['email']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Téléphone</span>
                            <span class="info-value"><?= htmlspecialchars($utilisateurDetail['telephone'] ?: 'Non renseigné') ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Sexe</span>
                            <span class="info-value"><?= $utilisateurDetail['sexe'] ?: 'Non renseigné' ?></span>
                        </div>
                    </div>
                </div>

                <div class="card-snel">
                    <h3 class="font-bold text-primary mb-4">
                        <i class="fas fa-briefcase text-secondary mr-2"></i>
                        Informations professionnelles
                    </h3>
                    <div class="space-y-1">
                        <div class="info-item">
                            <span class="info-label">Rôle</span>
                            <span class="info-value">
                                <span class="status-badge <?= $utilisateurDetail['role'] === 'administrateur' ? 'status-admin' : 'status-agent' ?>">
                                    <?= ucfirst($utilisateurDetail['role']) ?>
                                </span>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Statut</span>
                            <span class="info-value">
                                <span class="status-badge <?= $utilisateurDetail['statut'] === 'actif' ? 'status-actif' : 'status-inactif' ?>">
                                    <?= ucfirst($utilisateurDetail['statut']) ?>
                                </span>
                            </span>
                        </div>
                        <?php if ($utilisateurDetail['dateEmbauche']): ?>
                        <div class="info-item">
                            <span class="info-label">Date d'embauche</span>
                            <span class="info-value"><?= date('d/m/Y', strtotime($utilisateurDetail['dateEmbauche'])) ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="info-item">
                            <span class="info-label">Dernière connexion</span>
                            <span class="info-value"><?= $utilisateurDetail['derniereConnexion'] ? date('d/m/Y H:i', strtotime($utilisateurDetail['derniereConnexion'])) : 'Jamais' ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex flex-wrap justify-center gap-4 mt-6">
                <a href="modifier.php?id=<?= $id ?>" class="btn-secondary px-6 py-2 rounded-lg">
                    <i class="fas fa-edit mr-2"></i> Modifier
                </a>
                <a href="index.php" class="btn-outline px-6 py-2 rounded-lg">
                    <i class="fas fa-arrow-left mr-2"></i> Retour
                </a>
                <?php if ($id != $idUtilisateur): ?>
                    <a href="#" class="btn-danger px-6 py-2 rounded-lg" 
                       onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ?')">
                        <i class="fas fa-trash mr-2"></i> Supprimer
                    </a>
                <?php endif; ?>
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