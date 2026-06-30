<?php
// Pages/Admin/dashboard.php
session_start();
require_once __DIR__ . '/../../Classes/Database.php';
require_once __DIR__ . '/../../Classes/Utilisateur.php';
require_once __DIR__ . '/../../Classes/Client.php';
require_once __DIR__ . '/../../Classes/Compteur.php';
require_once __DIR__ . '/../../Classes/Consommation.php';
require_once __DIR__ . '/../../Classes/Facture.php';
require_once __DIR__ . '/../../Classes/Paiement.php';
require_once __DIR__ . '/../../Classes/Notification.php';

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

// Vérifier que l'utilisateur est administrateur
if ($user->getRole() !== 'administrateur') {
    header('Location: /Pages/Agent/dashboard.php');
    exit;
}

$idUtilisateur = $user->getIdUtilisateur();
$db = Database::getInstance()->getConnection();

// ============================================ //
// STATISTIQUES GLOBALES
// ============================================ //

// Clients
$stmt = $db->query("SELECT COUNT(*) as total FROM Client");
$totalClients = $stmt->fetch()['total'] ?? 0;

$stmt = $db->query("SELECT COUNT(*) as total FROM Client WHERE statut = 'actif'");
$clientsActifs = $stmt->fetch()['total'] ?? 0;

// Compteurs
$stmt = $db->query("SELECT COUNT(*) as total FROM Compteur");
$totalCompteurs = $stmt->fetch()['total'] ?? 0;

$stmt = $db->query("SELECT COUNT(*) as total FROM Compteur WHERE etat = 'actif'");
$compteursActifs = $stmt->fetch()['total'] ?? 0;

$stmt = $db->query("SELECT COUNT(*) as total FROM Compteur WHERE etat = 'en_panne'");
$compteursPanne = $stmt->fetch()['total'] ?? 0;

// Utilisateurs (agents + admins)
$stmt = $db->query("SELECT COUNT(*) as total FROM Utilisateurs");
$totalUtilisateurs = $stmt->fetch()['total'] ?? 0;

$stmt = $db->query("SELECT COUNT(*) as total FROM Utilisateurs WHERE role = 'agent'");
$totalAgents = $stmt->fetch()['total'] ?? 0;

$stmt = $db->query("SELECT COUNT(*) as total FROM Utilisateurs WHERE role = 'administrateur'");
$totalAdmins = $stmt->fetch()['total'] ?? 0;

// Relevés
$stmt = $db->query("SELECT COUNT(*) as total FROM Consommation");
$totalReleves = $stmt->fetch()['total'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(*) as total FROM Consommation WHERE MONTH(dateFin) = ? AND YEAR(dateFin) = ?");
$stmt->execute([date('m'), date('Y')]);
$relevesMois = $stmt->fetch()['total'] ?? 0;

// Factures
$stmt = $db->query("SELECT COUNT(*) as total FROM Facture");
$totalFactures = $stmt->fetch()['total'] ?? 0;

$stmt = $db->query("SELECT COUNT(*) as total, SUM(montantTotal) as total_montant FROM Facture WHERE statut IN ('en_attente', 'en_retard')");
$facturesImpayees = $stmt->fetch();
$totalImpayees = $facturesImpayees['total'] ?? 0;
$montantImpaye = $facturesImpayees['total_montant'] ?? 0;

$stmt = $db->query("SELECT SUM(montantTotal) as total FROM Facture WHERE statut = 'payee'");
$totalCollecte = $stmt->fetch()['total'] ?? 0;

// Paiements du mois
$stmt = $db->prepare("SELECT SUM(montant) as total FROM Paiement WHERE MONTH(datePaiement) = ? AND YEAR(datePaiement) = ? AND statut = 'effectue'");
$stmt->execute([date('m'), date('Y')]);
$paiementsMois = $stmt->fetch()['total'] ?? 0;

// Derniers relevés
$stmt = $db->query("
    SELECT c.*, cp.NumeroSerie, CONCAT(cl.nom, ' ', cl.prenom) as client_nom
    FROM Consommation c
    JOIN Compteur cp ON c.idCompteur = cp.idCompteur
    JOIN Client cl ON cp.idClient = cl.idClient
    ORDER BY c.dateCreation DESC
    LIMIT 10
");
$derniersReleves = $stmt->fetchAll();

// Dernières factures impayées
$stmt = $db->query("
    SELECT f.*, CONCAT(cl.nom, ' ', cl.prenom) as client_nom
    FROM Facture f
    JOIN Client cl ON f.idClient = cl.idClient
    WHERE f.statut IN ('en_attente', 'en_retard')
    ORDER BY f.dateLimitePaiement ASC
    LIMIT 10
");
$facturesImpayeesList = $stmt->fetchAll();

// Notifications
$notification = new Notification();
$totalNotifs = $notification->countNonLuesUtilisateur($idUtilisateur);

$page_title = 'Dashboard Administrateur - SNEL';
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { font-family: 'Inter', sans-serif; }
        :root { --primary: #1a365d; --secondary: #c05621; --accent: #ecc94b; }
        .bg-primary { background: var(--primary); }
        .bg-secondary { background: var(--secondary); }
        .text-primary { color: var(--primary); }
        .text-secondary { color: var(--secondary); }
        .btn-primary { background: var(--primary); color: white; transition: all 0.3s ease; }
        .btn-primary:hover { background: #0f2440; transform: translateY(-2px); box-shadow: 0 10px 30px rgba(26,54,93,0.25); }
        .btn-secondary { background: var(--secondary); color: white; transition: all 0.3s ease; }
        .btn-secondary:hover { background: #a0441a; transform: translateY(-2px); box-shadow: 0 10px 30px rgba(192,86,33,0.25); }
        .stat-card { background: white; border-radius: 1rem; padding: 1.25rem; transition: all 0.3s ease; border: 1px solid #e2e8f0; }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 12px 40px rgba(0,0,0,0.06); border-color: var(--secondary); }
        .card-snel { background: white; border-radius: 1rem; padding: 1.25rem; border: 1px solid #e2e8f0; transition: all 0.3s ease; }
        .card-snel:hover { box-shadow: 0 8px 30px rgba(0,0,0,0.06); }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.65rem; font-weight: 600; text-transform: uppercase; }
        .status-actif { background: #dcfce7; color: #166534; }
        .status-inactif { background: #fef3c7; color: #92400e; }
        .status-panne { background: #fecaca; color: #991b1b; }
        .status-payee { background: #dcfce7; color: #166534; }
        .status-attente { background: #fef3c7; color: #92400e; }
        .status-retard { background: #fecaca; color: #991b1b; }
        .status-admin { background: #dbeafe; color: #1e40af; }
        .sidebar { background: var(--primary); min-height: 100vh; position: fixed; top: 0; left: 0; width: 260px; z-index: 100; overflow-y: auto; }
        .nav-item { display: flex; align-items: center; padding: 0.7rem 1rem; color: rgba(255,255,255,0.6); border-radius: 0.75rem; transition: all 0.3s ease; gap: 0.75rem; font-size: 0.9rem; text-decoration: none; }
        .nav-item:hover { background: rgba(255,255,255,0.08); color: white; }
        .nav-item.active { background: var(--secondary); color: white; box-shadow: 0 4px 15px rgba(192,86,33,0.3); }
        .nav-item .badge { margin-left: auto; background: var(--secondary); color: white; font-size: 0.6rem; padding: 0.15rem 0.5rem; border-radius: 9999px; }
        .topbar { background: white; border-bottom: 1px solid #e2e8f0; padding: 0.75rem 2rem; position: sticky; top: 0; z-index: 50; }
        .main-content { margin-left: 260px; min-height: 100vh; background: #f8fafc; }
        .user-dropdown { position: absolute; top: 100%; right: 0; width: 240px; background: white; border-radius: 1rem; box-shadow: 0 20px 60px rgba(0,0,0,0.15); border: 1px solid #e2e8f0; display: none; z-index: 60; overflow: hidden; }
        .user-dropdown.open { display: block; }
        .mobile-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 90; }
        .mobile-overlay.open { display: block; }
        @media (max-width: 1024px) { .sidebar { transform: translateX(-100%); transition: transform 0.3s ease; } .sidebar.open { transform: translateX(0); } .main-content { margin-left: 0; } .topbar { padding: 0.75rem 1rem; } }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: var(--secondary); border-radius: 4px; }
        .badge-danger { background: #ef4444; color: white; font-size: 0.6rem; padding: 0.15rem 0.5rem; border-radius: 9999px; }
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
            <a href="dashboard.php" class="nav-item active">
                <i class="fas fa-home w-5 text-center"></i> Tableau de bord
            </a>
            <a href="Utilisateurs/index.php" class="nav-item">
                <i class="fas fa-users-cog w-5 text-center"></i> Utilisateurs
                <span class="badge"><?= $totalUtilisateurs ?></span>
            </a>
            <a href="Clients/index.php" class="nav-item">
                <i class="fas fa-user-friends w-5 text-center"></i> Clients
                <span class="badge"><?= $totalClients ?></span>
            </a>
            <a href="Compteurs/index.php" class="nav-item">
                <i class="fas fa-gauge-high w-5 text-center"></i> Compteurs
                <span class="badge"><?= $totalCompteurs ?></span>
            </a>
            <a href="Tarifs/index.php" class="nav-item">
                <i class="fas fa-tags w-5 text-center"></i> Tarifs
            </a>
            <a href="Rapports/index.php" class="nav-item">
                <i class="fas fa-chart-pie w-5 text-center"></i> Rapports
            </a>
            <a href="Profil/index.php" class="nav-item">
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
                <button onclick="toggleMobileMenu()" class="lg:hidden text-primary text-2xl">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <h1 class="text-lg font-bold text-primary hidden sm:block">Tableau de bord</h1>
                    <p class="text-xs text-gray-400 hidden sm:block">Bienvenue, <?= htmlspecialchars($user->getPrenom()) ?></p>
                </div>
            </div>
            
            <div class="flex items-center space-x-4">
                <!-- Notifications -->
                <div class="relative">
                    <a href="Notifications/index.php" class="relative p-2 rounded-full hover:bg-gray-100 transition block">
                        <i class="fas fa-bell text-gray-600 text-lg"></i>
                        <?php if ($totalNotifs > 0): ?>
                            <span class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center">
                                <?= $totalNotifs > 9 ? '9+' : $totalNotifs ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </div>
                
                <!-- Menu Utilisateur -->
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
                            <a href="Profil/index.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition">
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
        
        <!-- En-tête -->
        <div class="bg-white rounded-xl shadow-sm p-6 mb-6 border-l-4 border-secondary">
            <div class="flex flex-wrap justify-between items-center">
                <div>
                    <p class="text-sm text-gray-500">Bonjour,</p>
                    <h1 class="text-2xl font-bold text-primary">
                        <?= htmlspecialchars($user->getNom() . ' ' . $user->getPrenom()) ?>
                    </h1>
                    <div class="flex flex-wrap items-center gap-2 mt-1">
                        <span class="text-sm text-gray-500">
                            <i class="fas fa-envelope mr-1"></i>
                            <?= htmlspecialchars($user->getEmail()) ?>
                        </span>
                        <span class="status-badge status-admin">
                            <i class="fas fa-shield-alt mr-1"></i> Administrateur
                        </span>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2 mt-2 sm:mt-0">
                    <span class="bg-blue-50 text-blue-700 px-3 py-1 rounded-full text-sm flex items-center">
                        <i class="fas fa-calendar mr-1"></i> <?= date('d/m/Y') ?>
                    </span>
                    <span class="bg-green-50 text-green-700 px-3 py-1 rounded-full text-sm flex items-center">
                        <i class="fas fa-clock mr-1"></i> <?= date('H:i') ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Statistiques -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <a href="Clients/index.php" class="stat-card block hover:no-underline">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-500 font-medium">Clients</p>
                        <p class="text-2xl font-bold text-primary"><?= number_format($totalClients) ?></p>
                        <p class="text-xs text-green-600"><?= number_format($clientsActifs) ?> actifs</p>
                    </div>
                    <div class="w-11 h-11 bg-blue-50 rounded-xl flex items-center justify-center">
                        <i class="fas fa-users text-blue-600 text-lg"></i>
                    </div>
                </div>
            </a>
            
            <a href="Compteurs/index.php" class="stat-card block hover:no-underline">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-500 font-medium">Compteurs</p>
                        <p class="text-2xl font-bold text-primary"><?= number_format($totalCompteurs) ?></p>
                        <p class="text-xs text-gray-400"><?= number_format($compteursPanne) ?> en panne</p>
                    </div>
                    <div class="w-11 h-11 bg-green-50 rounded-xl flex items-center justify-center">
                        <i class="fas fa-gauge-high text-green-600 text-lg"></i>
                    </div>
                </div>
            </a>
            
            <a href="Factures/index.php" class="stat-card block hover:no-underline">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-500 font-medium">Factures impayées</p>
                        <p class="text-2xl font-bold text-secondary"><?= number_format($totalImpayees) ?></p>
                        <p class="text-xs text-red-500"><?= number_format($montantImpaye, 0, ',', ' ') ?> F</p>
                    </div>
                    <div class="w-11 h-11 bg-red-50 rounded-xl flex items-center justify-center">
                        <i class="fas fa-exclamation-triangle text-red-600 text-lg"></i>
                    </div>
                </div>
            </a>
            
            <a href="Rapports/index.php" class="stat-card block hover:no-underline">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-500 font-medium">Collecte du mois</p>
                        <p class="text-2xl font-bold text-green-600"><?= number_format($paiementsMois, 0, ',', ' ') ?> F</p>
                        <p class="text-xs text-gray-400"><?= number_format($relevesMois) ?> relevés</p>
                    </div>
                    <div class="w-11 h-11 bg-yellow-50 rounded-xl flex items-center justify-center">
                        <i class="fas fa-coins text-yellow-600 text-lg"></i>
                    </div>
                </div>
            </a>
        </div>

        <!-- Graphiques -->
        <div class="grid lg:grid-cols-2 gap-6 mb-6">
            <div class="card-snel">
                <h3 class="font-bold text-primary mb-4">
                    <i class="fas fa-chart-bar text-secondary mr-2"></i>
                    Évolution des clients
                </h3>
                <div style="height: 200px;">
                    <canvas id="chartClients"></canvas>
                </div>
            </div>
            <div class="card-snel">
                <h3 class="font-bold text-primary mb-4">
                    <i class="fas fa-chart-pie text-secondary mr-2"></i>
                    Répartition des compteurs
                </h3>
                <div style="height: 200px;">
                    <canvas id="chartCompteurs"></canvas>
                </div>
            </div>
        </div>

        <!-- Factures impayées -->
        <?php if (!empty($facturesImpayeesList)): ?>
        <div class="card-snel mb-6 border-l-4 border-red-500">
            <h3 class="font-bold text-primary mb-4">
                <i class="fas fa-exclamation-triangle text-red-500 mr-2"></i>
                Factures impayées
                <span class="badge-danger ml-2"><?= count($facturesImpayeesList) ?></span>
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Client</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">N° Facture</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Montant</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date limite</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Statut</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($facturesImpayeesList as $facture): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 text-sm font-medium text-primary">
                                    <?= htmlspecialchars($facture['client_nom']) ?>
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-600">
                                    <?= htmlspecialchars($facture['numeroFacture']) ?>
                                </td>
                                <td class="px-4 py-2 text-sm font-bold text-secondary">
                                    <?= number_format($facture['montantTotal'], 0, ',', ' ') ?> F
                                </td>
                                <td class="px-4 py-2 text-sm <?= strtotime($facture['dateLimitePaiement']) < time() ? 'text-red-500 font-bold' : 'text-gray-600' ?>">
                                    <?= date('d/m/Y', strtotime($facture['dateLimitePaiement'])) ?>
                                    <?php if (strtotime($facture['dateLimitePaiement']) < time()): ?>
                                        <span class="text-red-500 text-xs">(Retard)</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-2">
                                    <span class="status-badge <?= $facture['statut'] === 'en_retard' ? 'status-retard' : 'status-attente' ?>">
                                        <?= $facture['statut'] === 'en_retard' ? 'En retard' : 'En attente' ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Derniers relevés -->
        <div class="card-snel">
            <h3 class="font-bold text-primary mb-4">
                <i class="fas fa-history text-secondary mr-2"></i>
                Derniers relevés effectués
            </h3>
            <?php if (!empty($derniersReleves)): ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Client</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Compteur</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Consommation</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($derniersReleves as $releve): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2 text-sm font-medium text-primary">
                                <?= htmlspecialchars($releve['client_nom']) ?>
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-600">
                                <?= htmlspecialchars($releve['NumeroSerie']) ?>
                            </td>
                            <td class="px-4 py-2 text-sm font-bold text-secondary">
                                <?= number_format($releve['quantiteCons'], 2, ',', ' ') ?> kWh
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-600">
                                <?= date('d/m/Y H:i', strtotime($releve['dateCreation'])) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p class="text-gray-500 text-sm text-center py-4">Aucun relevé effectué</p>
            <?php endif; ?>
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

    // Graphiques
    <?php if ($totalCompteurs > 0): ?>
    document.addEventListener('DOMContentLoaded', function() {
        // Graphique clients (simulé - données des 6 derniers mois)
        const ctxClients = document.getElementById('chartClients');
        if (ctxClients) {
            new Chart(ctxClients, {
                type: 'bar',
                data: {
                    labels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin'],
                    datasets: [{
                        label: 'Nouveaux clients',
                        data: [12, 19, 15, 22, 18, 25],
                        backgroundColor: 'rgba(192, 86, 33, 0.6)',
                        borderColor: '#c05621',
                        borderWidth: 1,
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } },
                        x: { grid: { display: false } }
                    }
                }
            });
        }

        // Graphique compteurs
        const ctxCompteurs = document.getElementById('chartCompteurs');
        if (ctxCompteurs) {
            new Chart(ctxCompteurs, {
                type: 'doughnut',
                data: {
                    labels: ['Actifs', 'En panne', 'Inactifs'],
                    datasets: [{
                        data: [<?= $compteursActifs ?>, <?= $compteursPanne ?>, <?= $totalCompteurs - $compteursActifs - $compteursPanne ?>],
                        backgroundColor: ['#16a34a', '#dc2626', '#f59e0b'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { padding: 10, usePointStyle: true, pointStyle: 'circle' }
                        }
                    },
                    cutout: '65%'
                }
            });
        }
    });
    <?php endif; ?>
</script>

</body>
</html>