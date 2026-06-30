<?php
// Pages/Admin/Compteurs/details.php
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

$idCompteur = $_GET['id'] ?? 0;
if ($idCompteur <= 0) {
    header('Location: index.php');
    exit;
}

$db = Database::getInstance()->getConnection();

// Récupérer les détails du compteur
$stmt = $db->prepare("
    SELECT c.*, 
           CONCAT(cl.nom, ' ', cl.prenom) as client_nom,
           cl.telephone as client_telephone,
           cl.email as client_email,
           cl.adresse as client_adresse,
           cl.idClient
    FROM Compteur c
    LEFT JOIN Client cl ON c.idClient = cl.idClient
    WHERE c.idCompteur = ?
");
$stmt->execute([$idCompteur]);
$compteur = $stmt->fetch();

if (!$compteur) {
    header('Location: index.php');
    exit;
}

// Récupérer les consommations du compteur
try {
    $stmt = $db->prepare("
        SELECT * FROM Consommation 
        WHERE idCompteur = ? 
        ORDER BY idConsommation DESC 
        LIMIT 20
    ");
    $stmt->execute([$idCompteur]);
    $consommations = $stmt->fetchAll();
} catch (PDOException $e) {
    $consommations = [];
}

// Récupérer les factures liées à ce compteur (via les consommations)
$factures = [];
if (!empty($consommations)) {
    $ids = array_column($consommations, 'idConsommation');
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare("
            SELECT * FROM Facture 
            WHERE idConsommation IN ($placeholders)
            ORDER BY dateEmission DESC
        ");
        $stmt->execute($ids);
        $factures = $stmt->fetchAll();
    }
}

// Statistiques
$totalConso = array_sum(array_column($consommations, 'quantiteCons'));
$nbReleves = count($consommations);
$moyenne = $nbReleves > 0 ? $totalConso / $nbReleves : 0;
$maxConso = $nbReleves > 0 ? max(array_column($consommations, 'quantiteCons')) : 0;
$minConso = $nbReleves > 0 ? min(array_column($consommations, 'quantiteCons')) : 0;

// Dernière consommation
$derniereConso = !empty($consommations) ? $consommations[0] : null;

$idUtilisateur = $user->getIdUtilisateur();

// Notifications
$notification = new Notification();
$totalNotifs = $notification->countNonLuesUtilisateur($idUtilisateur);

$page_title = 'Détails du compteur - SNEL Admin';
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
        .btn-danger { background: #dc2626; color: white; transition: all 0.3s ease; }
        .btn-danger:hover { background: #b91c1c; transform: translateY(-2px); }
        .btn-outline { border: 2px solid var(--secondary); color: var(--secondary); background: transparent; transition: all 0.3s ease; }
        .btn-outline:hover { background: var(--secondary); color: white; }
        .card-snel { background: white; border-radius: 1rem; padding: 1.25rem; border: 1px solid #e2e8f0; transition: all 0.3s ease; }
        .card-snel:hover { box-shadow: 0 8px 30px rgba(0,0,0,0.06); }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.65rem; font-weight: 600; text-transform: uppercase; }
        .status-actif { background: #dcfce7; color: #166534; }
        .status-inactif { background: #fef3c7; color: #92400e; }
        .status-panne { background: #fecaca; color: #991b1b; }
        .status-payee { background: #dcfce7; color: #166534; }
        .status-attente { background: #fef3c7; color: #92400e; }
        .status-retard { background: #fecaca; color: #991b1b; }
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
            <a href="../Utilisateurs/index.php" class="nav-item">
                <i class="fas fa-users-cog w-5 text-center"></i> Utilisateurs
            </a>
            <a href="../Clients/index.php" class="nav-item">
                <i class="fas fa-user-friends w-5 text-center"></i> Clients
            </a>
            <a href="index.php" class="nav-item active">
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
                <h1 class="text-lg font-bold text-primary hidden sm:block">Détails du compteur</h1>
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
        <div class="max-w-6xl mx-auto">
            
            <!-- En-tête -->
            <div class="bg-white rounded-xl shadow-sm p-6 mb-6 border-l-4 border-secondary">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-xs text-gray-500">Numéro de série</p>
                        <h2 class="text-2xl font-bold text-primary"><?= htmlspecialchars($compteur['NumeroSerie']) ?></h2>
                        <p class="text-sm text-gray-500">Installé le <?= date('d/m/Y', strtotime($compteur['DateInstallation'])) ?></p>
                        <p class="text-xs text-gray-400">ID: #<?= $compteur['idCompteur'] ?></p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <span class="status-badge <?= $compteur['etat'] === 'actif' ? 'status-actif' : ($compteur['etat'] === 'en_panne' ? 'status-panne' : 'status-inactif') ?>">
                            <?= ucfirst($compteur['etat']) ?>
                        </span>
                        <?php if ($compteur['idClient']): ?>
                            <span class="status-badge status-actif">
                                <i class="fas fa-user-check mr-1"></i> Assigné
                            </span>
                        <?php else: ?>
                            <span class="status-badge status-inactif">
                                <i class="fas fa-user-slash mr-1"></i> Non assigné
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Infos client -->
            <div class="card-snel mb-6">
                <h3 class="font-bold text-primary mb-3">
                    <i class="fas fa-user text-secondary mr-2"></i>
                    Client associé
                </h3>
                <?php if ($compteur['idClient']): ?>
                    <div class="grid md:grid-cols-3 gap-4">
                        <div>
                            <p class="text-xs text-gray-500">Nom</p>
                            <p class="font-medium text-primary"><?= htmlspecialchars($compteur['client_nom']) ?></p>
                            <a href="../Clients/details.php?id=<?= $compteur['idClient'] ?>" class="text-secondary hover:underline text-xs">
                                <i class="fas fa-external-link-alt mr-1"></i> Voir la fiche client
                            </a>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Téléphone</p>
                            <p class="font-medium"><?= htmlspecialchars($compteur['client_telephone']) ?></p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Email</p>
                            <p class="font-medium"><?= htmlspecialchars($compteur['client_email']) ?></p>
                        </div>
                        <div class="md:col-span-3">
                            <p class="text-xs text-gray-500">Adresse</p>
                            <p class="font-medium"><?= htmlspecialchars($compteur['client_adresse'] ?: 'Non renseignée') ?></p>
                        </div>
                    </div>
                <?php else: ?>
                    <p class="text-gray-500 text-sm">Compteur non assigné à un client.</p>
                    <a href="#" class="btn-secondary text-sm px-4 py-2 rounded-lg inline-flex items-center mt-2">
                        <i class="fas fa-user-plus mr-2"></i> Assigner à un client
                    </a>
                <?php endif; ?>
            </div>

            <!-- Caractéristiques techniques -->
            <div class="grid md:grid-cols-3 gap-4 mb-6">
                <div class="card-snel">
                    <p class="text-xs text-gray-500">Type</p>
                    <p class="font-bold text-primary"><?= ucfirst($compteur['typeCompteur'] ?? 'Monophase') ?></p>
                </div>
                <div class="card-snel">
                    <p class="text-xs text-gray-500">Marque / Modèle</p>
                    <p class="font-medium"><?= htmlspecialchars($compteur['marque'] ?: '-') ?> <?= htmlspecialchars($compteur['modele'] ?: '') ?></p>
                </div>
                <div class="card-snel">
                    <p class="text-xs text-gray-500">Capacité</p>
                    <p class="font-medium"><?= htmlspecialchars($compteur['capacite'] ?: '-') ?> A</p>
                </div>
                <div class="card-snel">
                    <p class="text-xs text-gray-500">Tension</p>
                    <p class="font-medium"><?= htmlspecialchars($compteur['tension'] ?: '-') ?></p>
                </div>
                <div class="card-snel">
                    <p class="text-xs text-gray-500">Emplacement</p>
                    <p class="font-medium"><?= htmlspecialchars($compteur['emplacement'] ?: 'Non renseigné') ?></p>
                </div>
                <div class="card-snel">
                    <p class="text-xs text-gray-500">Index actuel</p>
                    <p class="font-bold text-secondary text-xl"><?= number_format($compteur['indexActuel'], 0, ',', ' ') ?> kWh</p>
                </div>
            </div>

            <!-- Statistiques -->
            <div class="grid md:grid-cols-4 gap-4 mb-6">
                <div class="card-snel text-center">
                    <p class="text-xs text-gray-500">Nombre de relevés</p>
                    <p class="text-2xl font-bold text-primary"><?= $nbReleves ?></p>
                </div>
                <div class="card-snel text-center">
                    <p class="text-xs text-gray-500">Consommation totale</p>
                    <p class="text-2xl font-bold text-secondary"><?= number_format($totalConso, 2, ',', ' ') ?> kWh</p>
                </div>
                <div class="card-snel text-center">
                    <p class="text-xs text-gray-500">Moyenne par relevé</p>
                    <p class="text-2xl font-bold text-green-600"><?= number_format($moyenne, 2, ',', ' ') ?> kWh</p>
                </div>
                <div class="card-snel text-center">
                    <p class="text-xs text-gray-500">Dernier relevé</p>
                    <p class="text-2xl font-bold text-primary"><?= $derniereConso ? number_format($derniereConso['quantiteCons'], 2, ',', ' ') . ' kWh' : '-' ?></p>
                </div>
            </div>

            <!-- Historique des consommations -->
            <div class="card-snel mb-6">
                <h3 class="font-bold text-primary mb-4">
                    <i class="fas fa-history text-secondary mr-2"></i>
                    Historique des relevés
                </h3>
                <?php if (!empty($consommations)): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Période</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Ancien index</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nouvel index</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Consommation</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Agent</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php 
                                $num = 1;
                                foreach ($consommations as $conso): 
                                ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-2 text-sm text-gray-400 font-medium"><?= $num++ ?></td>
                                        <td class="px-4 py-2 text-sm text-gray-600">
                                            <?php 
                                                $dateDebut = $conso['dateDebut'] ?? 'N/A';
                                                $dateFin = $conso['dateFin'] ?? $conso['dateReleve'] ?? 'N/A';
                                                if ($dateDebut != 'N/A' && $dateFin != 'N/A' && $dateDebut != '0000-00-00' && $dateFin != '0000-00-00') {
                                                    echo date('d/m/Y', strtotime($dateDebut)) . ' - ' . date('d/m/Y', strtotime($dateFin));
                                                } else {
                                                    echo 'Relevé #' . ($conso['idConsommation'] ?? '');
                                                }
                                            ?>
                                        </td>
                                        <td class="px-4 py-2 text-sm font-mono">
                                            <?= number_format($conso['indexAncien'] ?? 0, 0, ',', ' ') ?>
                                        </td>
                                        <td class="px-4 py-2 text-sm font-mono">
                                            <?= number_format($conso['indexNouveau'] ?? 0, 0, ',', ' ') ?>
                                        </td>
                                        <td class="px-4 py-2 text-sm font-bold text-secondary">
                                            <?= number_format($conso['quantiteCons'] ?? 0, 2, ',', ' ') ?> kWh
                                        </td>
                                        <td class="px-4 py-2 text-sm text-gray-600">
                                            <?php 
                                                $agentId = $conso['idAgentReleve'] ?? null;
                                                if ($agentId) {
                                                    $stmt = $db->prepare("SELECT CONCAT(nom, ' ', prenom) as agent_nom FROM Utilisateurs WHERE idUtilisateur = ?");
                                                    $stmt->execute([$agentId]);
                                                    $agent = $stmt->fetch();
                                                    echo htmlspecialchars($agent['agent_nom'] ?? 'N/A');
                                                } else {
                                                    echo '-';
                                                }
                                            ?>
                                        </td>
                                        <td class="px-4 py-2 text-sm text-gray-600">
                                            <?= date('d/m/Y H:i', strtotime($conso['dateCreation'] ?? $conso['dateReleve'] ?? 'now')) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if (count($consommations) > 20): ?>
                        <div class="text-center mt-4">
                            <a href="/Pages/Agent/Releves/index.php?compteur=<?= $idCompteur ?>" class="text-secondary hover:underline text-sm">
                                Voir tous les relevés →
                            </a>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-gray-500 text-sm text-center py-4">Aucun relevé enregistré pour ce compteur.</p>
                <?php endif; ?>
            </div>

            <!-- Factures liées -->
            <?php if (!empty($factures)): ?>
            <div class="card-snel">
                <h3 class="font-bold text-primary mb-4">
                    <i class="fas fa-file-invoice text-secondary mr-2"></i>
                    Factures associées
                    <span class="text-sm text-gray-400 font-normal ml-2">(<?= count($factures) ?>)</span>
                </h3>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">N° Facture</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Montant</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Statut</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach (array_slice($factures, 0, 10) as $facture): ?>
                                <?php 
                                    $statutClass = $facture['statut'] === 'payee' ? 'status-payee' : ($facture['statut'] === 'en_retard' ? 'status-retard' : 'status-attente');
                                    $statutLabel = $facture['statut'] === 'payee' ? 'Payée' : ($facture['statut'] === 'en_retard' ? 'En retard' : 'En attente');
                                ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2 text-sm font-medium text-primary">
                                        <?= htmlspecialchars($facture['numeroFacture']) ?>
                                    </td>
                                    <td class="px-4 py-2 text-sm font-bold text-secondary">
                                        <?= number_format($facture['montantTotal'], 0, ',', ' ') ?> F
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-600">
                                        <?= date('d/m/Y', strtotime($facture['dateEmission'])) ?>
                                    </td>
                                    <td class="px-4 py-2">
                                        <span class="status-badge <?= $statutClass ?>"><?= $statutLabel ?></span>
                                    </td>
                                    <td class="px-4 py-2">
                                        <a href="/Pages/Agent/Factures/details.php?id=<?= $facture['idFacture'] ?>" 
                                           class="text-secondary hover:underline text-sm">
                                            <i class="fas fa-eye mr-1"></i> Voir
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (count($factures) > 10): ?>
                    <div class="text-center mt-4">
                        <a href="/Pages/Agent/Factures/index.php?compteur=<?= $idCompteur ?>" class="text-secondary hover:underline text-sm">
                            Voir toutes les factures →
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Actions -->
            <div class="flex flex-wrap justify-center gap-4 mt-6">
                <a href="index.php" class="btn-outline px-6 py-2 rounded-lg">
                    <i class="fas fa-arrow-left mr-2"></i> Retour
                </a>
                <a href="/Pages/Agent/Releves/nouveau.php?compteur=<?= $idCompteur ?>" class="btn-secondary px-6 py-2 rounded-lg">
                    <i class="fas fa-edit mr-2"></i> Relever
                </a>
                <a href="/Pages/Agent/Compteurs/modifier.php?id=<?= $idCompteur ?>" class="btn-primary px-6 py-2 rounded-lg">
                    <i class="fas fa-pen mr-2"></i> Modifier
                </a>
                <?php if ($user->getRole() === 'administrateur'): ?>
                    <a href="#" class="btn-danger px-6 py-2 rounded-lg" 
                       onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce compteur ?')">
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