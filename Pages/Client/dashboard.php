<?php
session_start();
require_once __DIR__ . '/../../Classes/Database.php';
require_once __DIR__ . '/../../Classes/Client.php';
require_once __DIR__ . '/../../Classes/Compteur.php';
require_once __DIR__ . '/../../Classes/Consommation.php';
require_once __DIR__ . '/../../Classes/Facture.php';
require_once __DIR__ . '/../../Classes/Paiement.php';
require_once __DIR__ . '/../../Classes/Notification.php';

// Vérifier si l'utilisateur est connecté
$client = new Client();
if (!$client->isLoggedIn()) {
    header('Location: /login.php');
    exit;
}

// Récupérer les données du client connecté
$user = $client->getLoggedInUser();
if (!$user) {
    session_destroy();
    header('Location: /login.php?error=session_expired');
    exit;
}

$idClient = $user->getIdClient();

// Récupérer les notifications non lues
$notification = new Notification();
$notificationsNonLues = $notification->getNonLuesClient($idClient);
$totalNotifs = $notification->countNonLuesClient($idClient);

// Récupérer les données
$compteurs = $user->getCompteurs();
$factures = $user->getFactures();

// Récupérer les consommations et paiements
$db = Database::getInstance()->getConnection();

// ============================================ //
// CONSOMMATIONS - Version sécurisée
// ============================================ //
$consommations = [];
if ($compteurs) {
    $ids = array_column($compteurs, 'idCompteur');
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        try {
            // Vérifier quelles colonnes existent
            $stmt = $db->query("SHOW COLUMNS FROM Consommation");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Choisir la colonne de date appropriée
            $orderColumn = 'idConsommation';
            if (in_array('dateFin', $columns)) {
                $orderColumn = 'dateFin';
            } elseif (in_array('dateReleve', $columns)) {
                $orderColumn = 'dateReleve';
            } elseif (in_array('dateCreation', $columns)) {
                $orderColumn = 'dateCreation';
            }
            
            $stmt = $db->prepare("SELECT * FROM Consommation WHERE idCompteur IN ($placeholders) ORDER BY $orderColumn DESC LIMIT 12");
            $stmt->execute($ids);
            $consommations = $stmt->fetchAll();
        } catch (PDOException $e) {
            $consommations = [];
        }
    }
}

// Paiements
$stmt = $db->prepare("SELECT * FROM Paiement WHERE idClient = ? ORDER BY datePaiement DESC LIMIT 10");
$stmt->execute([$idClient]);
$paiements = $stmt->fetchAll();

// Statistiques
$totalCompteurs = count($compteurs);
$totalFactures = count($factures);
$facturesImpayees = array_filter($factures, function($f) {
    return in_array($f['statut'], ['en_attente', 'en_retard']);
});
$totalImpaye = array_sum(array_column($facturesImpayees, 'montantTotal'));

// Dernière consommation
$derniereConsommation = !empty($consommations) ? $consommations[0] : null;

// ============================================ //
// CONSOMMATION MENSUELLE - Version sécurisée
// ============================================ //
$consoMois = 0;
$consoMoisPrec = 0;
$variation = 0;

if (!empty($compteurs)) {
    try {
        // Récupérer les colonnes de la table
        $stmt = $db->query("SHOW COLUMNS FROM Consommation");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Vérifier quelles colonnes de date sont disponibles
        $dateColumns = [];
        if (in_array('dateFin', $columns)) $dateColumns[] = 'dateFin';
        if (in_array('dateReleve', $columns)) $dateColumns[] = 'dateReleve';
        if (in_array('dateCreation', $columns)) $dateColumns[] = 'dateCreation';
        
        if (!empty($dateColumns)) {
            $dateColumn = $dateColumns[0]; // Prendre la première disponible
            $moisActuel = date('m');
            $anneeActuelle = date('Y');
            
            $stmt = $db->prepare("
                SELECT SUM(quantiteCons) as total 
                FROM Consommation c
                JOIN Compteur cp ON c.idCompteur = cp.idCompteur
                WHERE cp.idClient = ? AND MONTH(c.$dateColumn) = ? AND YEAR(c.$dateColumn) = ?
            ");
            $stmt->execute([$idClient, $moisActuel, $anneeActuelle]);
            $consoMois = $stmt->fetch()['total'] ?? 0;

            $moisPrecedent = date('m', strtotime('-1 month'));
            $anneePrecedente = date('Y', strtotime('-1 month'));
            $stmt->execute([$idClient, $moisPrecedent, $anneePrecedente]);
            $consoMoisPrec = $stmt->fetch()['total'] ?? 0;
            $variation = $consoMoisPrec > 0 ? round((($consoMois - $consoMoisPrec) / $consoMoisPrec) * 100, 1) : 0;
        }
    } catch (Exception $e) {
        $consoMois = 0;
        $consoMoisPrec = 0;
        $variation = 0;
    }
}

$page_title = 'Dashboard - SNEL Gestion des compteurs';
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
        
        .input-field {
            width: 100%;
            padding: 0.6rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 0.75rem;
            transition: all 0.3s ease;
            background: white;
            font-size: 0.9rem;
        }
        .input-field:focus {
            border-color: var(--secondary);
            outline: none;
            box-shadow: 0 0 0 4px rgba(192, 86, 33, 0.1);
        }
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
                <span class="text-accent text-[10px] block -mt-0.5 font-semibold tracking-wider">Espace Client</span>
            </div>
        </div>
        
        <nav class="space-y-1">
            <a href="dashboard.php" class="nav-item active">
                <i class="fas fa-home w-5 text-center"></i> Tableau de bord
            </a>
            <a href="Compteurs/index.php" class="nav-item">
                <i class="fas fa-gauge-high w-5 text-center"></i> Mes compteurs
                <span class="badge"><?= $totalCompteurs ?></span>
            </a>
            <a href="Consommations/index.php" class="nav-item">
                <i class="fas fa-chart-line w-5 text-center"></i> Consommations
            </a>
            <a href="Factures/index.php" class="nav-item">
                <i class="fas fa-file-invoice w-5 text-center"></i> Factures
                <?php if (count($facturesImpayees) > 0): ?>
                    <span class="badge" style="background:#ef4444;"><?= count($facturesImpayees) ?></span>
                <?php endif; ?>
            </a>
            <a href="Paiements/index.php" class="nav-item">
                <i class="fas fa-credit-card w-5 text-center"></i> Paiements
            </a>
            <a href="Notifications/index.php" class="nav-item">
                <i class="fas fa-bell w-5 text-center"></i> Notifications
                <?php if ($totalNotifs > 0): ?>
                    <span class="badge" style="background:#ef4444;"><?= $totalNotifs ?></span>
                <?php endif; ?>
            </a>
            <a href="Support/index.php" class="nav-item">
                <i class="fas fa-life-ring w-5 text-center"></i> Centre d'aide
            </a>
            <a href="Profil/index.php" class="nav-item">
                <i class="fas fa-user w-5 text-center"></i> Mon profil
            </a>
        </nav>
        
        <div class="absolute bottom-4 left-4 right-4">
            <a href="../../logout.php" class="nav-item text-red-400 hover:text-red-300 hover:bg-red-500/10">
                <i class="fas fa-sign-out-alt w-5 text-center"></i> Déconnexion
            </a>
            <div class="text-center text-xs text-white/30 mt-3">
                v2.0.1
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
                    
                    <!-- Dropdown Utilisateur -->
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
                            <a href="Factures/index.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition">
                                <i class="fas fa-file-invoice w-5 text-gray-400"></i> Mes factures
                            </a>
                            <a href="Support/index.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition">
                                <i class="fas fa-life-ring w-5 text-gray-400"></i> Centre d'aide
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
        
        <!-- ============================================ -->
        <!-- DASHBOARD -->
        <!-- ============================================ -->
        <section id="section-dashboard" class="tab-content active">
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
                                <i class="fas fa-check-circle mr-1"></i> Compte actif
                            </span>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2 mt-2 sm:mt-0">
                        <a href="/Pages/Client/Compteurs/index.php" class="bg-blue-50 text-blue-700 px-3 py-1 rounded-full text-sm flex items-center hover:bg-blue-100 transition">
                            <i class="fas fa-gauge-high mr-1"></i> <?= $totalCompteurs ?> compteur(s)
                        </a>
                        <?php if (count($facturesImpayees) > 0): ?>
                            <a href="/Pages/Client/Factures/index.php" class="bg-red-50 text-red-700 px-3 py-1 rounded-full text-sm flex items-center animate-pulse hover:bg-red-100 transition">
                                <i class="fas fa-exclamation-triangle mr-1"></i> <?= count($facturesImpayees) ?> impayée(s)
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Statistiques -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <a href="/Pages/Client/Compteurs/index.php" class="stat-card block hover:no-underline">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-gray-500 font-medium">Compteurs</p>
                            <p class="text-2xl font-bold text-primary"><?= $totalCompteurs ?></p>
                        </div>
                        <div class="w-11 h-11 bg-blue-50 rounded-xl flex items-center justify-center">
                            <i class="fas fa-gauge-high text-blue-600 text-lg"></i>
                        </div>
                    </div>
                </a>
                <a href="/Pages/Client/Consommations/index.php" class="stat-card block hover:no-underline">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-gray-500 font-medium">Consommation du mois</p>
                            <p class="text-2xl font-bold text-primary"><?= number_format($consoMois, 0, ',', ' ') ?> kWh</p>
                        </div>
                        <div class="w-11 h-11 bg-green-50 rounded-xl flex items-center justify-center">
                            <i class="fas fa-bolt text-green-600 text-lg"></i>
                        </div>
                    </div>
                    <?php if ($variation != 0): ?>
                        <p class="text-xs <?= $variation > 0 ? 'text-red-500' : 'text-green-500' ?> mt-1">
                            <i class="fas fa-<?= $variation > 0 ? 'arrow-up' : 'arrow-down' ?> mr-1"></i>
                            <?= abs($variation) ?>% <?= $variation > 0 ? 'd\'augmentation' : 'de diminution' ?>
                        </p>
                    <?php endif; ?>
                </a>
                <a href="/Pages/Client/Factures/index.php" class="stat-card block hover:no-underline">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-gray-500 font-medium">Factures</p>
                            <p class="text-2xl font-bold text-primary"><?= $totalFactures ?></p>
                        </div>
                        <div class="w-11 h-11 bg-yellow-50 rounded-xl flex items-center justify-center">
                            <i class="fas fa-file-invoice text-yellow-600 text-lg"></i>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">
                        <?= count($facturesImpayees) ?> en attente
                    </p>
                </a>
                <a href="/Pages/Client/Factures/index.php" class="stat-card block hover:no-underline">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-gray-500 font-medium">Total impayé</p>
                            <p class="text-2xl font-bold text-secondary"><?= number_format($totalImpaye, 0, ',', ' ') ?> F</p>
                        </div>
                        <div class="w-11 h-11 bg-red-50 rounded-xl flex items-center justify-center">
                            <i class="fas fa-coins text-red-600 text-lg"></i>
                        </div>
                    </div>
                    <?php if ($totalImpaye > 0): ?>
                        <span class="text-xs text-secondary hover:underline">Voir les détails →</span>
                    <?php endif; ?>
                </a>
            </div>

            <!-- Graphique et Dernière consommation -->
            <div class="grid lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 card-snel">
                    <h3 class="font-bold text-primary mb-4">
                        <i class="fas fa-chart-area text-secondary mr-2"></i>
                        Évolution de la consommation
                    </h3>
                    <!-- Conteneur du graphique avec vérification -->
                    <?php if (!empty($consommations) && count($consommations) >= 2): ?>
                        <canvas id="chartConsommation" height="200"></canvas>
                        <div class="text-center mt-3">
                            <a href="/Pages/Client/Consommations/index.php" class="text-secondary hover:underline text-sm">
                                Voir tout l'historique →
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <i class="fas fa-chart-line text-4xl text-gray-300 mb-3 block"></i>
                            <p class="text-gray-500 text-sm">Pas assez de données pour afficher le graphique</p>
                            <p class="text-xs text-gray-400">Minimum 2 relevés nécessaires</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="card-snel">
                    <h3 class="font-bold text-primary mb-4">
                        <i class="fas fa-clock text-secondary mr-2"></i>
                        Dernière consommation
                    </h3>
                    <?php if ($derniereConsommation): ?>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                                <span class="text-sm text-gray-600">Quantité</span>
                                <span class="font-bold text-primary"><?= number_format($derniereConsommation['quantiteCons'], 2, ',', ' ') ?> kWh</span>
                            </div>
                            <?php if (isset($derniereConsommation['dateDebut']) && isset($derniereConsommation['dateFin'])): ?>
                            <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                                <span class="text-sm text-gray-600">Période</span>
                                <span class="font-medium text-sm">
                                    <?= date('d/m/Y', strtotime($derniereConsommation['dateDebut'])) ?> - 
                                    <?= date('d/m/Y', strtotime($derniereConsommation['dateFin'])) ?>
                                </span>
                            </div>
                            <?php endif; ?>
                            <?php if (isset($derniereConsommation['consommationJournaliere']) && $derniereConsommation['consommationJournaliere'] > 0): ?>
                                <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                                    <span class="text-sm text-gray-600">Moyenne/jour</span>
                                    <span class="font-medium text-sm"><?= number_format($derniereConsommation['consommationJournaliere'], 2, ',', ' ') ?> kWh</span>
                                </div>
                            <?php endif; ?>
                            <a href="/Pages/Client/Consommations/index.php" class="text-secondary hover:underline text-sm block text-center">
                                Voir tout l'historique →
                            </a>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-500 text-sm text-center py-6">Aucune consommation enregistrée</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Actions rapides -->
            <div class="grid md:grid-cols-4 gap-4 mt-6">
                <a href="/Pages/Client/Factures/index.php" class="bg-white rounded-xl shadow-sm p-4 text-center hover:shadow-md transition border border-gray-100">
                    <i class="fas fa-file-invoice text-2xl text-secondary mb-2 block"></i>
                    <span class="text-sm font-medium text-gray-700">Voir mes factures</span>
                </a>
                <a href="/Pages/Client/Support/signaler.php" class="bg-white rounded-xl shadow-sm p-4 text-center hover:shadow-md transition border border-gray-100">
                    <i class="fas fa-exclamation-triangle text-2xl text-yellow-500 mb-2 block"></i>
                    <span class="text-sm font-medium text-gray-700">Signaler une anomalie</span>
                </a>
                <a href="/Pages/Client/Compteurs/index.php" class="bg-white rounded-xl shadow-sm p-4 text-center hover:shadow-md transition border border-gray-100">
                    <i class="fas fa-gauge-high text-2xl text-blue-500 mb-2 block"></i>
                    <span class="text-sm font-medium text-gray-700">Voir mes compteurs</span>
                </a>
                <a href="/Pages/Client/Support/index.php" class="bg-white rounded-xl shadow-sm p-4 text-center hover:shadow-md transition border border-gray-100">
                    <i class="fas fa-life-ring text-2xl text-green-500 mb-2 block"></i>
                    <span class="text-sm font-medium text-gray-700">Centre d'aide</span>
                </a>
            </div>
        </section>

        <!-- ============================================ -->
        <!-- AUTRES SECTIONS (chargées via les liens) -->
        <!-- ============================================ -->
        <div class="mt-4 text-center text-sm text-gray-400 border-t border-gray-200 pt-4">
            <p>Utilisez le menu de gauche pour accéder à toutes les fonctionnalités</p>
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
    
    // Fermer le dropdown au clic en dehors
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.user-dropdown') && !e.target.closest('[onclick="toggleUserMenu()"]')) {
            document.getElementById('userDropdown').classList.remove('open');
        }
    });

    // ========== GRAPHIQUE - Version sécurisée ==========
    <?php if (!empty($consommations) && count($consommations) >= 2): ?>
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('chartConsommation');
        if (!ctx) return;
        
        try {
            const consoData = <?php 
                $labels = [];
                $values = [];
                $data = array_reverse($consommations);
                $count = 0;
                foreach ($data as $conso) {
                    if ($count >= 12) break;
                    // Utiliser la date disponible
                    $date = $conso['dateFin'] ?? $conso['dateReleve'] ?? $conso['dateDebut'] ?? null;
                    if ($date) {
                        $labels[] = date('d/m/Y', strtotime($date));
                    } else {
                        $labels[] = 'Relevé #' . ($conso['idConsommation'] ?? ++$count);
                    }
                    $values[] = floatval($conso['quantiteCons'] ?? 0);
                    $count++;
                }
                echo json_encode(['labels' => array_reverse($labels), 'values' => array_reverse($values)]);
            ?>;
            
            // Vérifier que les données sont valides
            if (consoData.labels.length > 0 && consoData.values.length > 0) {
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: consoData.labels,
                        datasets: [{
                            label: 'Consommation (kWh)',
                            data: consoData.values,
                            backgroundColor: 'rgba(192, 86, 33, 0.1)',
                            borderColor: '#c05621',
                            borderWidth: 2.5,
                            pointBackgroundColor: '#c05621',
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
                        plugins: { 
                            legend: { display: false } 
                        },
                        scales: {
                            y: { 
                                beginAtZero: true, 
                                grid: { color: 'rgba(0,0,0,0.05)' }, 
                                ticks: { callback: function(v) { return v + ' kWh'; } } 
                            },
                            x: { 
                                grid: { display: false }, 
                                ticks: { maxRotation: 45, font: { size: 9 } } 
                            }
                        }
                    }
                });
            }
        } catch(e) {
            console.log('Erreur graphique:', e);
        }
    });
    <?php endif; ?>
</script>

</body>
</html>