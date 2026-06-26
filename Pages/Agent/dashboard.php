<?php
// Pages/Agent/dashboard.php
session_start();
require_once __DIR__ . '/../../Classes/Database.php';
require_once __DIR__ . '/../../Classes/Utilisateur.php';
require_once __DIR__ . '/../../Classes/Client.php';
require_once __DIR__ . '/../../Classes/Compteur.php';
require_once __DIR__ . '/../../Classes/Consommation.php';
require_once __DIR__ . '/../../Classes/Facture.php';
require_once __DIR__ . '/../../Classes/Paiement.php';
require_once __DIR__ . '/../../Classes/Notification.php';

// Vérifier si l'utilisateur est connecté et est un agent
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

// Vérifier que l'utilisateur est bien un agent ou administrateur
if (!in_array($user->getRole(), ['agent', 'administrateur'])) {
    header('Location: /Pages/Client/dashboard.php');
    exit;
}

$idUtilisateur = $user->getIdUtilisateur();
$db = Database::getInstance()->getConnection();

// ============================================ //
// STATISTIQUES
// ============================================ //

// Nombre total de clients
$stmt = $db->query("SELECT COUNT(*) as total FROM Client");
$totalClients = $stmt->fetch()['total'] ?? 0;

// Nombre total de compteurs
$stmt = $db->query("SELECT COUNT(*) as total FROM Compteur");
$totalCompteurs = $stmt->fetch()['total'] ?? 0;

// Compteurs actifs
$stmt = $db->query("SELECT COUNT(*) as total FROM Compteur WHERE etat = 'actif'");
$compteursActifs = $stmt->fetch()['total'] ?? 0;

// Compteurs en panne
$stmt = $db->query("SELECT COUNT(*) as total FROM Compteur WHERE etat = 'en_panne'");
$compteursPanne = $stmt->fetch()['total'] ?? 0;

// Relevés du mois
$stmt = $db->prepare("
    SELECT COUNT(*) as total 
    FROM Consommation 
    WHERE MONTH(dateFin) = ? AND YEAR(dateFin) = ?
");
$stmt->execute([date('m'), date('Y')]);
$relevesMois = $stmt->fetch()['total'] ?? 0;

// Factures impayées
$stmt = $db->query("
    SELECT COUNT(*) as total, SUM(montantTotal) as total_montant 
    FROM Facture 
    WHERE statut IN ('en_attente', 'en_retard')
");
$facturesImpayees = $stmt->fetch();
$totalImpayees = $facturesImpayees['total'] ?? 0;
$montantImpaye = $facturesImpayees['total_montant'] ?? 0;

// Dernières consommations (pour le flux)
$stmt = $db->query("
    SELECT c.*, cp.numeroSerie, CONCAT(cl.nom, ' ', cl.prenom) as client_nom
    FROM Consommation c
    JOIN Compteur cp ON c.idCompteur = cp.idCompteur
    JOIN Client cl ON cp.idClient = cl.idClient
    ORDER BY c.dateFin DESC
    LIMIT 10
");
$dernieresConsommations = $stmt->fetchAll();

// Clients avec compteurs sans relevé ce mois
$stmt = $db->prepare("
    SELECT DISTINCT cl.idClient, cl.nom, cl.prenom, cl.telephone
    FROM Client cl
    JOIN Compteur cp ON cl.idClient = cp.idClient
    LEFT JOIN Consommation c ON cp.idCompteur = c.idCompteur 
        AND MONTH(c.dateFin) = ? AND YEAR(c.dateFin) = ?
    WHERE c.idConsommation IS NULL
    LIMIT 10
");
$stmt->execute([date('m'), date('Y')]);
$clientsSansReleve = $stmt->fetchAll();

// Notifications
$notification = new Notification();
$notificationsNonLues = $notification->getNonLuesUtilisateur($idUtilisateur);
$totalNotifs = $notification->countNonLuesUtilisateur($idUtilisateur);

$page_title = 'Dashboard Agent - SNEL';
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
        .border-primary { border-color: var(--primary); }
        
        .bg-secondary { background: var(--secondary); }
        .text-secondary { color: var(--secondary); }
        .border-secondary { border-color: var(--secondary); }
        
        .bg-accent { background: var(--accent); }
        .text-accent { color: var(--accent); }
        
        .btn-primary {
            background: var(--primary);
            color: white;
            transition: all 0.3s ease;
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
        }
        .btn-secondary:hover {
            background: #a0441a;
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(192, 86, 33, 0.25);
        }
        
        .btn-success {
            background: #16a34a;
            color: white;
            transition: all 0.3s ease;
        }
        .btn-success:hover {
            background: #15803d;
            transform: translateY(-2px);
        }
        
        .stat-card {
            background: white;
            border-radius: 1rem;
            padding: 1.25rem;
            transition: all 0.3s ease;
            border: 1px solid #e2e8f0;
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.06);
            border-color: var(--secondary);
        }
        
        .card-snel {
            background: white;
            border-radius: 1rem;
            padding: 1.25rem;
            transition: all 0.3s ease;
            border: 1px solid #e2e8f0;
        }
        .card-snel:hover {
            box-shadow: 0 8px 30px rgba(0,0,0,0.06);
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-actif { background: #dcfce7; color: #166534; }
        .status-inactif { background: #fef3c7; color: #92400e; }
        .status-panne { background: #fecaca; color: #991b1b; }
        .status-payee { background: #dcfce7; color: #166534; }
        .status-attente { background: #fef3c7; color: #92400e; }
        .status-retard { background: #fecaca; color: #991b1b; }
        
        .sidebar {
            background: var(--primary);
            min-height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            width: 260px;
            z-index: 100;
            overflow-y: auto;
        }
        
        .nav-item {
            display: flex;
            align-items: center;
            padding: 0.7rem 1rem;
            color: rgba(255,255,255,0.6);
            border-radius: 0.75rem;
            transition: all 0.3s ease;
            gap: 0.75rem;
            font-size: 0.9rem;
            text-decoration: none;
        }
        .nav-item:hover {
            background: rgba(255,255,255,0.08);
            color: white;
        }
        .nav-item.active {
            background: var(--secondary);
            color: white;
            box-shadow: 0 4px 15px rgba(192, 86, 33, 0.3);
        }
        .nav-item .badge {
            margin-left: auto;
            background: var(--secondary);
            color: white;
            font-size: 0.6rem;
            padding: 0.15rem 0.5rem;
            border-radius: 9999px;
        }
        
        .topbar {
            background: white;
            border-bottom: 1px solid #e2e8f0;
            padding: 0.75rem 2rem;
            position: sticky;
            top: 0;
            z-index: 50;
        }
        
        .main-content {
            margin-left: 260px;
            min-height: 100vh;
            background: #f8fafc;
        }
        
        .notification-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            width: 380px;
            background: white;
            border-radius: 1rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
            border: 1px solid #e2e8f0;
            display: none;
            z-index: 60;
            max-height: 450px;
            overflow-y: auto;
        }
        .notification-dropdown.open { display: block; }
        
        .notif-item {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #f1f5f9;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        .notif-item:hover { background: #f8fafc; }
        .notif-item.unread { background: #fffbeb; border-left: 3px solid var(--secondary); }
        
        .user-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            width: 240px;
            background: white;
            border-radius: 1rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
            border: 1px solid #e2e8f0;
            display: none;
            z-index: 60;
            overflow: hidden;
        }
        .user-dropdown.open { display: block; }
        
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        .mobile-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.4);
            z-index: 90;
        }
        .mobile-overlay.open { display: block; }
        
        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%); transition: transform 0.3s ease; }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .topbar { padding: 0.75rem 1rem; }
            .notification-dropdown { width: 320px; right: -60px; }
        }
        
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: var(--secondary); border-radius: 4px; }
    </style>
</head>
<body>

<!-- ============================================ -->
<!-- OVERLAY MOBILE -->
<!-- ============================================ -->
<div id="mobileOverlay" class="mobile-overlay" onclick="closeMobileMenu()"></div>

<!-- ============================================ -->
<!-- SIDEBAR -->
<!-- ============================================ -->
<aside class="sidebar" id="sidebar">
    <div class="p-5">
        <div class="flex items-center space-x-3 mb-8">
            <div class="w-10 h-10 bg-secondary rounded-xl flex items-center justify-center shadow-lg">
                <i class="fas fa-bolt text-white text-lg"></i>
            </div>
            <div>
                <span class="text-white font-bold text-xl tracking-tight">SNEL</span>
                <span class="text-accent text-[10px] block -mt-0.5 font-semibold tracking-wider">Espace Agent</span>
            </div>
        </div>
        
        <nav class="space-y-1">
            <a href="dashboard.php" class="nav-item active">
                <i class="fas fa-home w-5 text-center"></i> Tableau de bord
            </a>
            <a href="Releves/index.php" class="nav-item">
                <i class="fas fa-clipboard-list w-5 text-center"></i> Relevés
                <span class="badge" style="background:#ef4444;"><?= $relevesMois ?></span>
            </a>
            <a href="Releves/nouveau.php" class="nav-item">
                <i class="fas fa-plus-circle w-5 text-center"></i> Nouveau relevé
            </a>
            <a href="Clients/index.php" class="nav-item">
                <i class="fas fa-users w-5 text-center"></i> Clients
                <span class="badge"><?= $totalClients ?></span>
            </a>
            <a href="Compteurs/index.php" class="nav-item">
                <i class="fas fa-gauge-high w-5 text-center"></i> Compteurs
                <span class="badge"><?= $totalCompteurs ?></span>
            </a>
            <a href="Factures/index.php" class="nav-item">
                <i class="fas fa-file-invoice w-5 text-center"></i> Factures
                <?php if ($totalImpayees > 0): ?>
                    <span class="badge" style="background:#ef4444;"><?= $totalImpayees ?></span>
                <?php endif; ?>
            </a>
            <a href="Paiements/index.php" class="nav-item">
                <i class="fas fa-credit-card w-5 text-center"></i> Paiements
            </a>
            <a href="Signalements/index.php" class="nav-item">
                <i class="fas fa-exclamation-triangle w-5 text-center"></i> Signalements
            </a>
            <a href="Profil/index.php" class="nav-item">
                <i class="fas fa-user w-5 text-center"></i> Mon profil
            </a>
        </nav>
        
        <div class="absolute bottom-4 left-4 right-4">
        <a href="../../logout.php" class="flex items-center px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition">
    <i class="fas fa-sign-out-alt w-5 text-red-400"></i>
    Déconnexion
</a>
            <div class="text-center text-xs text-white/30 mt-3">
                v2.0.1 | Agent
            </div>
        </div>
    </div>
</aside>

<!-- ============================================ -->
<!-- CONTENU PRINCIPAL -->
<!-- ============================================ -->
<div class="main-content">

    <!-- ============================================ -->
    <!-- TOPBAR -->
    <!-- ============================================ -->
    <header class="topbar">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <button onclick="toggleMobileMenu()" class="lg:hidden text-primary text-2xl">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <h1 class="text-lg font-bold text-primary hidden sm:block">Tableau de bord agent</h1>
                    <p class="text-xs text-gray-400 hidden sm:block">Bienvenue, <?= htmlspecialchars($user->getPrenom()) ?></p>
                </div>
            </div>
            
            <div class="flex items-center space-x-4">
                <!-- Notifications -->
                <div class="relative">
                    <a href="/Pages/Agent/Notifications/index.php" class="relative p-2 rounded-full hover:bg-gray-100 transition block">
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
                    
                    <!-- Dropdown Utilisateur -->
                    <div id="userDropdown" class="user-dropdown">
                        <div class="p-4 bg-gray-50 border-b border-gray-100">
                            <p class="font-bold text-primary"><?= htmlspecialchars($user->getNom() . ' ' . $user->getPrenom()) ?></p>
                            <p class="text-xs text-gray-500"><?= htmlspecialchars($user->getEmail()) ?></p>
                            <span class="text-xs text-green-600"><i class="fas fa-circle text-[6px] mr-1"></i> En ligne</span>
                        </div>
                        <div class="py-1">
                            <a href="/Pages/Agent/Profil/index.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition">
                                <i class="fas fa-user w-5 text-gray-400"></i> Mon profil
                            </a>
                            <a href="/Pages/Agent/Clients/index.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition">
                                <i class="fas fa-users w-5 text-gray-400"></i> Clients
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

    <!-- ============================================ -->
    <!-- CONTENU -->
    <!-- ============================================ -->
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
                        <span class="status-badge status-actif">
                            <i class="fas fa-check-circle mr-1"></i> <?= ucfirst($user->getRole()) ?>
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
            <a href="/Pages/Agent/Clients/index.php" class="stat-card block hover:no-underline">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-500 font-medium">Clients</p>
                        <p class="text-2xl font-bold text-primary"><?= $totalClients ?></p>
                    </div>
                    <div class="w-11 h-11 bg-blue-50 rounded-xl flex items-center justify-center">
                        <i class="fas fa-users text-blue-600 text-lg"></i>
                    </div>
                </div>
            </a>
            
            <a href="/Pages/Agent/Compteurs/index.php" class="stat-card block hover:no-underline">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-500 font-medium">Compteurs</p>
                        <p class="text-2xl font-bold text-primary"><?= $totalCompteurs ?></p>
                        <p class="text-xs text-gray-400"><?= $compteursActifs ?> actifs</p>
                    </div>
                    <div class="w-11 h-11 bg-green-50 rounded-xl flex items-center justify-center">
                        <i class="fas fa-gauge-high text-green-600 text-lg"></i>
                    </div>
                </div>
            </a>
            
            <a href="/Pages/Agent/Releves/index.php" class="stat-card block hover:no-underline">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-500 font-medium">Relevés du mois</p>
                        <p class="text-2xl font-bold text-primary"><?= $relevesMois ?></p>
                    </div>
                    <div class="w-11 h-11 bg-yellow-50 rounded-xl flex items-center justify-center">
                        <i class="fas fa-clipboard-list text-yellow-600 text-lg"></i>
                    </div>
                </div>
            </a>
            
            <a href="/Pages/Agent/Factures/index.php" class="stat-card block hover:no-underline">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-500 font-medium">Impayés</p>
                        <p class="text-2xl font-bold text-secondary"><?= $totalImpayees ?></p>
                        <p class="text-xs text-gray-400"><?= number_format($montantImpaye, 0, ',', ' ') ?> FCFA</p>
                    </div>
                    <div class="w-11 h-11 bg-red-50 rounded-xl flex items-center justify-center">
                        <i class="fas fa-exclamation-triangle text-red-600 text-lg"></i>
                    </div>
                </div>
            </a>
        </div>

        <!-- Actions rapides -->
        <div class="grid md:grid-cols-3 gap-4 mb-6">
            <a href="/Pages/Agent/Releves/nouveau.php" class="bg-white rounded-xl shadow-sm p-5 text-center hover:shadow-md transition border-2 border-secondary hover:border-secondary">
                <i class="fas fa-plus-circle text-3xl text-secondary mb-3 block"></i>
                <span class="font-bold text-primary">Nouveau relevé</span>
                <p class="text-xs text-gray-500 mt-1">Saisir l'index d'un compteur</p>
            </a>
            <a href="/Pages/Agent/Clients/index.php" class="bg-white rounded-xl shadow-sm p-5 text-center hover:shadow-md transition border border-gray-200 hover:border-secondary">
                <i class="fas fa-search text-3xl text-blue-500 mb-3 block"></i>
                <span class="font-bold text-primary">Rechercher un client</span>
                <p class="text-xs text-gray-500 mt-1">Trouver un client et ses compteurs</p>
            </a>
            <a href="/Pages/Agent/Compteurs/nouveau.php" class="bg-white rounded-xl shadow-sm p-5 text-center hover:shadow-md transition border border-gray-200 hover:border-secondary">
                <i class="fas fa-plus text-3xl text-green-500 mb-3 block"></i>
                <span class="font-bold text-primary">Ajouter un compteur</span>
                <p class="text-xs text-gray-500 mt-1">Enregistrer un nouveau compteur</p>
            </a>
        </div>

        <!-- Clients sans relevé -->
        <?php if (!empty($clientsSansReleve)): ?>
        <div class="card-snel mb-6 border-l-4 border-yellow-500">
            <h3 class="font-bold text-primary mb-4">
                <i class="fas fa-clock text-yellow-500 mr-2"></i>
                Clients sans relevé ce mois
                <span class="text-sm text-gray-400 font-normal ml-2">(<?= count($clientsSansReleve) ?> clients)</span>
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Client</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Téléphone</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($clientsSansReleve as $client): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2 text-sm font-medium text-primary">
                                <?= htmlspecialchars($client['nom'] . ' ' . $client['prenom']) ?>
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-600">
                                <?= htmlspecialchars($client['telephone']) ?>
                            </td>
                            <td class="px-4 py-2">
                                <a href="/Pages/Agent/Releves/nouveau.php?client=<?= $client['idClient'] ?>" 
                                   class="btn-secondary text-xs px-3 py-1 rounded-lg inline-flex items-center">
                                    <i class="fas fa-edit mr-1"></i> Relever
                                </a>
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
            <?php if (!empty($dernieresConsommations)): ?>
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
                        <?php foreach ($dernieresConsommations as $conso): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2 text-sm font-medium text-primary">
                                <?= htmlspecialchars($conso['client_nom']) ?>
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-600">
                                <?= htmlspecialchars($conso['numeroSerie']) ?>
                            </td>
                            <td class="px-4 py-2 text-sm font-bold text-secondary">
                                <?= number_format($conso['quantiteCons'], 2, ',', ' ') ?> kWh
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-600">
                                <?= date('d/m/Y', strtotime($conso['dateFin'])) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="text-center mt-4">
                <a href="/Pages/Agent/Releves/index.php" class="text-secondary hover:underline text-sm">
                    Voir tous les relevés →
                </a>
            </div>
            <?php else: ?>
            <p class="text-gray-500 text-sm text-center py-4">Aucun relevé effectué</p>
            <?php endif; ?>
        </div>

    </div>
</div>

<!-- ============================================ -->
<!-- SCRIPTS -->
<!-- ============================================ -->
<script>
    // ========== MOBILE MENU ==========
    function toggleMobileMenu() {
        document.getElementById('sidebar').classList.toggle('open');
        document.getElementById('mobileOverlay').classList.toggle('open');
    }
    function closeMobileMenu() {
        document.getElementById('sidebar').classList.remove('open');
        document.getElementById('mobileOverlay').classList.remove('open');
    }

    // ========== USER MENU ==========
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