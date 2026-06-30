<?php
// Pages/Admin/Rapports/index.php
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

// Récupérer les paramètres de filtre
$periode = $_GET['periode'] ?? 'mois';
$annee = $_GET['annee'] ?? date('Y');
$mois = $_GET['mois'] ?? date('m');

// ============================================ //
// STATISTIQUES GLOBALES
// ============================================ //

// Nombre total de clients
$stmt = $db->query("SELECT COUNT(*) as total FROM Client");
$totalClients = $stmt->fetch()['total'] ?? 0;

// Nombre total de compteurs
$stmt = $db->query("SELECT COUNT(*) as total FROM Compteur");
$totalCompteurs = $stmt->fetch()['total'] ?? 0;

// Total des factures
$stmt = $db->query("SELECT COUNT(*) as total FROM Facture");
$totalFactures = $stmt->fetch()['total'] ?? 0;

// Total collecté
$stmt = $db->query("SELECT SUM(montantTotal) as total FROM Facture WHERE statut = 'payee'");
$totalCollecte = $stmt->fetch()['total'] ?? 0;

// Factures impayées
$stmt = $db->query("SELECT COUNT(*) as total, SUM(montantTotal) as total_montant FROM Facture WHERE statut IN ('en_attente', 'en_retard')");
$impayes = $stmt->fetch();
$totalImpayes = $impayes['total'] ?? 0;
$montantImpaye = $impayes['total_montant'] ?? 0;

// Taux de recouvrement
$tauxRecouvrement = $totalCollecte > 0 ? round(($totalCollecte / ($totalCollecte + $montantImpaye)) * 100, 1) : 0;

// ============================================ //
// STATISTIQUES PAR PÉRIODE
// ============================================ //

// Consommation par mois
$stmt = $db->prepare("
    SELECT MONTH(c.dateFin) as mois, SUM(c.quantiteCons) as total
    FROM Consommation c
    WHERE YEAR(c.dateFin) = ?
    GROUP BY MONTH(c.dateFin)
    ORDER BY mois
");
$stmt->execute([$annee]);
$consoParMois = $stmt->fetchAll();

// Paiements par mois
$stmt = $db->prepare("
    SELECT MONTH(datePaiement) as mois, SUM(montant) as total
    FROM Paiement
    WHERE YEAR(datePaiement) = ? AND statut = 'effectue'
    GROUP BY MONTH(datePaiement)
    ORDER BY mois
");
$stmt->execute([$annee]);
$paiementsParMois = $stmt->fetchAll();

// Nouveaux clients par mois
$stmt = $db->prepare("
    SELECT MONTH(dateInscription) as mois, COUNT(*) as total
    FROM Client
    WHERE YEAR(dateInscription) = ?
    GROUP BY MONTH(dateInscription)
    ORDER BY mois
");
$stmt->execute([$annee]);
$clientsParMois = $stmt->fetchAll();

// Nouveaux compteurs par mois
$stmt = $db->prepare("
    SELECT MONTH(DateInstallation) as mois, COUNT(*) as total
    FROM Compteur
    WHERE YEAR(DateInstallation) = ?
    GROUP BY MONTH(DateInstallation)
    ORDER BY mois
");
$stmt->execute([$annee]);
$compteursParMois = $stmt->fetchAll();

// Derniers clients
$stmt = $db->query("
    SELECT * FROM Client 
    ORDER BY dateInscription DESC 
    LIMIT 10
");
$derniersClients = $stmt->fetchAll();

// Dernières factures
$stmt = $db->query("
    SELECT f.*, CONCAT(cl.nom, ' ', cl.prenom) as client_nom
    FROM Facture f
    JOIN Client cl ON f.idClient = cl.idClient
    ORDER BY f.dateEmission DESC 
    LIMIT 10
");
$dernieresFactures = $stmt->fetchAll();

// Notifications
$notification = new Notification();
$totalNotifs = $notification->countNonLuesUtilisateur($idUtilisateur);

$page_title = 'Rapports - SNEL Admin';
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
        .btn-primary:hover { background: #0f2440; transform: translateY(-2px); }
        .btn-secondary { background: var(--secondary); color: white; transition: all 0.3s ease; }
        .btn-secondary:hover { background: #a0441a; transform: translateY(-2px); }
        .btn-outline { border: 2px solid var(--secondary); color: var(--secondary); background: transparent; transition: all 0.3s ease; }
        .btn-outline:hover { background: var(--secondary); color: white; }
        .card-snel { background: white; border-radius: 1rem; padding: 1.25rem; border: 1px solid #e2e8f0; transition: all 0.3s ease; }
        .card-snel:hover { box-shadow: 0 8px 30px rgba(0,0,0,0.06); }
        .stat-card { background: white; border-radius: 1rem; padding: 1.25rem; transition: all 0.3s ease; border: 1px solid #e2e8f0; }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 12px 40px rgba(0,0,0,0.06); border-color: var(--secondary); }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.65rem; font-weight: 600; text-transform: uppercase; }
        .status-actif { background: #dcfce7; color: #166534; }
        .status-payee { background: #dcfce7; color: #166534; }
        .status-attente { background: #fef3c7; color: #92400e; }
        .status-retard { background: #fecaca; color: #991b1b; }
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
        .input-field { width: 100%; padding: 0.6rem 1rem; border: 2px solid #e2e8f0; border-radius: 0.75rem; transition: all 0.3s ease; background: white; font-size: 0.9rem; }
        .input-field:focus { border-color: var(--secondary); outline: none; box-shadow: 0 0 0 4px rgba(192,86,33,0.1); }
        .filter-btn { padding: 0.4rem 1rem; border-radius: 9999px; font-size: 0.8rem; font-weight: 500; border: 2px solid #e2e8f0; background: white; color: #64748b; cursor: pointer; transition: all 0.3s ease; }
        .filter-btn:hover { border-color: var(--secondary); color: var(--primary); }
        .filter-btn.active { border-color: var(--secondary); background: #fff5ed; color: var(--secondary); }
        @media (max-width: 1024px) { .sidebar { transform: translateX(-100%); transition: transform 0.3s ease; } .sidebar.open { transform: translateX(0); } .main-content { margin-left: 0; } .topbar { padding: 0.75rem 1rem; } }
        .filter-select { padding: 0.4rem 1rem; border-radius: 0.75rem; border: 2px solid #e2e8f0; background: white; font-size: 0.8rem; color: #64748b; }
        .filter-select:focus { border-color: var(--secondary); outline: none; }
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
            <a href="index.php" class="nav-item active">
                <i class="fas fa-chart-pie w-5 text-center"></i> Rapports
            </a>
            <a href="../Profil/index.php" class="nav-item">
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
                    <h1 class="text-lg font-bold text-primary hidden sm:block">Rapports et statistiques</h1>
                    <p class="text-xs text-gray-400 hidden sm:block">Analyse des données SNEL</p>
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
                            <a href="../Profil/index.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition">
                                <i class="fas fa-user w-5 text-gray-400"></i> Mon profil
                            </a>
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
        
        <!-- Filtres -->
        <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
            <form method="GET" class="flex flex-wrap items-center gap-4">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Année</label>
                    <select name="annee" class="filter-select">
                        <?php for ($i = date('Y'); $i >= date('Y')-5; $i--): ?>
                            <option value="<?= $i ?>" <?= $annee == $i ? 'selected' : '' ?>><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="btn-secondary px-4 py-2 rounded-lg text-sm font-semibold">
                        <i class="fas fa-filter mr-2"></i> Filtrer
                    </button>
                    <a href="?" class="btn-outline px-4 py-2 rounded-lg text-sm font-semibold">
                        <i class="fas fa-times mr-2"></i> Réinitialiser
                    </a>
                </div>
            </form>
        </div>

        <!-- Statistiques globales -->
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-500 font-medium">Clients</p>
                        <p class="text-2xl font-bold text-primary"><?= number_format($totalClients) ?></p>
                    </div>
                    <div class="w-10 h-10 bg-blue-50 rounded-full flex items-center justify-center">
                        <i class="fas fa-users text-blue-600"></i>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-500 font-medium">Compteurs</p>
                        <p class="text-2xl font-bold text-primary"><?= number_format($totalCompteurs) ?></p>
                    </div>
                    <div class="w-10 h-10 bg-green-50 rounded-full flex items-center justify-center">
                        <i class="fas fa-gauge-high text-green-600"></i>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-500 font-medium">Factures</p>
                        <p class="text-2xl font-bold text-primary"><?= number_format($totalFactures) ?></p>
                    </div>
                    <div class="w-10 h-10 bg-yellow-50 rounded-full flex items-center justify-center">
                        <i class="fas fa-file-invoice text-yellow-600"></i>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-500 font-medium">Total collecté</p>
                        <p class="text-2xl font-bold text-secondary"><?= number_format($totalCollecte, 0, ',', ' ') ?> F</p>
                    </div>
                    <div class="w-10 h-10 bg-green-50 rounded-full flex items-center justify-center">
                        <i class="fas fa-coins text-green-600"></i>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-500 font-medium">Taux recouvrement</p>
                        <p class="text-2xl font-bold <?= $tauxRecouvrement >= 80 ? 'text-green-600' : ($tauxRecouvrement >= 50 ? 'text-yellow-600' : 'text-red-600') ?>">
                            <?= number_format($tauxRecouvrement, 1) ?>%
                        </p>
                        <p class="text-xs text-gray-400"><?= number_format($montantImpaye, 0, ',', ' ') ?> F impayé</p>
                    </div>
                    <div class="w-10 h-10 bg-red-50 rounded-full flex items-center justify-center">
                        <i class="fas fa-exclamation-triangle text-red-600"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Graphiques -->
        <div class="grid lg:grid-cols-2 gap-6 mb-6">
            <div class="card-snel">
                <h3 class="font-bold text-primary mb-4">
                    <i class="fas fa-chart-bar text-secondary mr-2"></i>
                    Consommation par mois (<?= $annee ?>)
                </h3>
                <div style="height: 220px;">
                    <canvas id="chartConso"></canvas>
                </div>
            </div>
            <div class="card-snel">
                <h3 class="font-bold text-primary mb-4">
                    <i class="fas fa-chart-line text-secondary mr-2"></i>
                    Paiements par mois (<?= $annee ?>)
                </h3>
                <div style="height: 220px;">
                    <canvas id="chartPaiements"></canvas>
                </div>
            </div>
        </div>

        <!-- Nouveaux clients et compteurs -->
        <div class="grid lg:grid-cols-2 gap-6 mb-6">
            <div class="card-snel">
                <h3 class="font-bold text-primary mb-4">
                    <i class="fas fa-user-plus text-secondary mr-2"></i>
                    Nouveaux clients (<?= $annee ?>)
                </h3>
                <div style="height: 200px;">
                    <canvas id="chartClients"></canvas>
                </div>
            </div>
            <div class="card-snel">
                <h3 class="font-bold text-primary mb-4">
                    <i class="fas fa-plus-circle text-secondary mr-2"></i>
                    Nouveaux compteurs (<?= $annee ?>)
                </h3>
                <div style="height: 200px;">
                    <canvas id="chartCompteurs"></canvas>
                </div>
            </div>
        </div>

        <!-- Derniers clients -->
        <div class="card-snel mb-6">
            <h3 class="font-bold text-primary mb-4">
                <i class="fas fa-user-clock text-secondary mr-2"></i>
                Derniers clients inscrits
            </h3>
            <?php if (!empty($derniersClients)): ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Client</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Contact</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($derniersClients as $client): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2 text-sm font-medium text-primary">
                                <?= htmlspecialchars($client['nom'] . ' ' . $client['prenom']) ?>
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-600">
                                <?= htmlspecialchars($client['Telephone']) ?>
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-600">
                                <?= date('d/m/Y', strtotime($client['dateInscription'])) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p class="text-gray-500 text-sm text-center py-4">Aucun client enregistré</p>
            <?php endif; ?>
        </div>

        <!-- Dernières factures -->
        <div class="card-snel">
            <h3 class="font-bold text-primary mb-4">
                <i class="fas fa-file-invoice text-secondary mr-2"></i>
                Dernières factures
            </h3>
            <?php if (!empty($dernieresFactures)): ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Client</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">N° Facture</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Montant</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Statut</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($dernieresFactures as $facture): ?>
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
                            <td class="px-4 py-2">
                                <span class="status-badge <?= $facture['statut'] === 'payee' ? 'status-payee' : ($facture['statut'] === 'en_retard' ? 'status-retard' : 'status-attente') ?>">
                                    <?= $facture['statut'] === 'payee' ? 'Payée' : ($facture['statut'] === 'en_retard' ? 'En retard' : 'En attente') ?>
                                </span>
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-600">
                                <?= date('d/m/Y', strtotime($facture['dateEmission'])) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p class="text-gray-500 text-sm text-center py-4">Aucune facture</p>
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
    document.addEventListener('DOMContentLoaded', function() {
        // Consommation par mois
        const ctxConso = document.getElementById('chartConso');
        if (ctxConso) {
            const consoData = <?php 
                $labels = [];
                $values = [];
                for ($i = 1; $i <= 12; $i++) {
                    $found = false;
                    foreach ($consoParMois as $data) {
                        if ($data['mois'] == $i) {
                            $labels[] = date('M', mktime(0,0,0,$i,1));
                            $values[] = floatval($data['total']);
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        $labels[] = date('M', mktime(0,0,0,$i,1));
                        $values[] = 0;
                    }
                }
                echo json_encode(['labels' => $labels, 'values' => $values]);
            ?>;
            
            new Chart(ctxConso, {
                type: 'bar',
                data: {
                    labels: consoData.labels,
                    datasets: [{
                        label: 'Consommation (kWh)',
                        data: consoData.values,
                        backgroundColor: 'rgba(192,86,33,0.6)',
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
                        y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { callback: v => v + ' kWh' } },
                        x: { grid: { display: false } }
                    }
                }
            });
        }

        // Paiements par mois
        const ctxPaiements = document.getElementById('chartPaiements');
        if (ctxPaiements) {
            const paiementsData = <?php 
                $labels = [];
                $values = [];
                for ($i = 1; $i <= 12; $i++) {
                    $found = false;
                    foreach ($paiementsParMois as $data) {
                        if ($data['mois'] == $i) {
                            $labels[] = date('M', mktime(0,0,0,$i,1));
                            $values[] = floatval($data['total']);
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        $labels[] = date('M', mktime(0,0,0,$i,1));
                        $values[] = 0;
                    }
                }
                echo json_encode(['labels' => $labels, 'values' => $values]);
            ?>;
            
            new Chart(ctxPaiements, {
                type: 'line',
                data: {
                    labels: paiementsData.labels,
                    datasets: [{
                        label: 'Paiements (FCFA)',
                        data: paiementsData.values,
                        backgroundColor: 'rgba(26,54,93,0.1)',
                        borderColor: '#1a365d',
                        borderWidth: 2,
                        pointBackgroundColor: '#1a365d',
                        pointBorderColor: 'white',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { callback: v => v + ' F' } },
                        x: { grid: { display: false } }
                    }
                }
            });
        }

        // Nouveaux clients
        const ctxClients = document.getElementById('chartClients');
        if (ctxClients) {
            const clientsData = <?php 
                $labels = [];
                $values = [];
                for ($i = 1; $i <= 12; $i++) {
                    $found = false;
                    foreach ($clientsParMois as $data) {
                        if ($data['mois'] == $i) {
                            $labels[] = date('M', mktime(0,0,0,$i,1));
                            $values[] = intval($data['total']);
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        $labels[] = date('M', mktime(0,0,0,$i,1));
                        $values[] = 0;
                    }
                }
                echo json_encode(['labels' => $labels, 'values' => $values]);
            ?>;
            
            new Chart(ctxClients, {
                type: 'bar',
                data: {
                    labels: clientsData.labels,
                    datasets: [{
                        label: 'Nouveaux clients',
                        data: clientsData.values,
                        backgroundColor: 'rgba(26,54,93,0.6)',
                        borderColor: '#1a365d',
                        borderWidth: 1,
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { stepSize: 1 } },
                        x: { grid: { display: false } }
                    }
                }
            });
        }

        // Nouveaux compteurs
        const ctxCompteurs = document.getElementById('chartCompteurs');
        if (ctxCompteurs) {
            const compteursData = <?php 
                $labels = [];
                $values = [];
                for ($i = 1; $i <= 12; $i++) {
                    $found = false;
                    foreach ($compteursParMois as $data) {
                        if ($data['mois'] == $i) {
                            $labels[] = date('M', mktime(0,0,0,$i,1));
                            $values[] = intval($data['total']);
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        $labels[] = date('M', mktime(0,0,0,$i,1));
                        $values[] = 0;
                    }
                }
                echo json_encode(['labels' => $labels, 'values' => $values]);
            ?>;
            
            new Chart(ctxCompteurs, {
                type: 'bar',
                data: {
                    labels: compteursData.labels,
                    datasets: [{
                        label: 'Nouveaux compteurs',
                        data: compteursData.values,
                        backgroundColor: 'rgba(192,86,33,0.6)',
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
                        y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { stepSize: 1 } },
                        x: { grid: { display: false } }
                    }
                }
            });
        }
    });
</script>

</body>
</html>