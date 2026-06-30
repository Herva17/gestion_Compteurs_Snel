<?php
// Pages/Admin/Clients/details.php
session_start();
require_once __DIR__ . '/../../../Classes/Database.php';
require_once __DIR__ . '/../../../Classes/Utilisateur.php';
require_once __DIR__ . '/../../../Classes/Client.php';
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

$idClient = $_GET['id'] ?? 0;
if ($idClient <= 0) {
    header('Location: index.php');
    exit;
}

// Récupérer les informations du client
$stmt = $db->prepare("SELECT * FROM Client WHERE idClient = ?");
$stmt->execute([$idClient]);
$client = $stmt->fetch();

if (!$client) {
    header('Location: index.php');
    exit;
}

// Récupérer les compteurs du client
$stmt = $db->prepare("SELECT * FROM Compteur WHERE idClient = ? ORDER BY dateInstallation DESC");
$stmt->execute([$idClient]);
$compteurs = $stmt->fetchAll();

// Récupérer les factures
$stmt = $db->prepare("SELECT * FROM Facture WHERE idClient = ? ORDER BY dateEmission DESC LIMIT 15");
$stmt->execute([$idClient]);
$factures = $stmt->fetchAll();

// Récupérer les paiements
$stmt = $db->prepare("SELECT * FROM Paiement WHERE idClient = ? ORDER BY datePaiement DESC LIMIT 15");
$stmt->execute([$idClient]);
$paiements = $stmt->fetchAll();

// Statistiques
$totalCompteurs = count($compteurs);
$totalFactures = count($factures);
$facturesImpayees = array_filter($factures, function($f) {
    return in_array($f['statut'], ['en_attente', 'en_retard']);
});
$totalImpaye = array_sum(array_column($facturesImpayees, 'montantTotal'));
$totalPaye = array_sum(array_column($paiements, 'montant'));

// Notifications
$notification = new Notification();
$totalNotifs = $notification->countNonLuesUtilisateur($idUtilisateur);

$page_title = 'Détails du client - SNEL Admin';
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
        .card-snel { background: white; border-radius: 1rem; padding: 1.25rem; border: 1px solid #e2e8f0; transition: all 0.3s ease; }
        .card-snel:hover { box-shadow: 0 8px 30px rgba(0,0,0,0.06); }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.65rem; font-weight: 600; text-transform: uppercase; }
        .status-actif { background: #dcfce7; color: #166534; }
        .status-inactif { background: #fef3c7; color: #92400e; }
        .status-suspendu { background: #fecaca; color: #991b1b; }
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
            <a href="index.php" class="nav-item active">
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
            <a href="/logout.php" class="nav-item text-red-400 hover:text-red-300 hover:bg-red-500/10">
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
                <h1 class="text-lg font-bold text-primary hidden sm:block">Fiche client</h1>
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
                            <a href="/logout.php" class="flex items-center px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition">
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
                        <p class="text-xs text-gray-500">Client N°</p>
                        <h2 class="text-2xl font-bold text-primary">#<?= $client['idClient'] ?> - <?= htmlspecialchars($client['nom'] . ' ' . $client['prenom']) ?></h2>
                        <p class="text-sm text-gray-500">Inscrit le <?= date('d/m/Y', strtotime($client['dateInscription'])) ?></p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <span class="status-badge <?= $client['statut'] === 'actif' ? 'status-actif' : ($client['statut'] === 'suspendu' ? 'status-suspendu' : 'status-inactif') ?>">
                            <?= ucfirst($client['statut']) ?>
                        </span>
                        <?php if ($totalImpaye > 0): ?>
                            <span class="status-badge status-retard">
                                <i class="fas fa-exclamation-triangle mr-1"></i> <?= number_format($totalImpaye, 0, ',', ' ') ?> F impayé
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Coordonnées -->
            <div class="grid md:grid-cols-2 gap-4 mb-6">
                <div class="card-snel">
                    <h3 class="font-bold text-primary mb-3">
                        <i class="fas fa-address-card text-secondary mr-2"></i>
                        Coordonnées
                    </h3>
                    <div class="space-y-1">
                        <div class="info-item">
                            <span class="info-label">Email</span>
                            <span class="info-value"><?= htmlspecialchars($client['email']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Téléphone</span>
                            <span class="info-value"><?= htmlspecialchars($client['Telephone']) ?></span>
                        </div>
                        <?php if ($client['Telephone2']): ?>
                        <div class="info-item">
                            <span class="info-label">Téléphone 2</span>
                            <span class="info-value"><?= htmlspecialchars($client['elephone2']) ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="info-item">
                            <span class="info-label">Adresse</span>
                            <span class="info-value"><?= htmlspecialchars($client['adresse'] ?: 'Non renseignée') ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Ville</span>
                            <span class="info-value"><?= htmlspecialchars($client['ville'] ?: 'Non renseignée') ?></span>
                        </div>
                    </div>
                </div>

                <div class="card-snel">
                    <h3 class="font-bold text-primary mb-3">
                        <i class="fas fa-chart-pie text-secondary mr-2"></i>
                        Statistiques
                    </h3>
                    <div class="space-y-1">
                        <div class="info-item">
                            <span class="info-label">Compteurs</span>
                            <span class="info-value"><?= $totalCompteurs ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Factures</span>
                            <span class="info-value"><?= $totalFactures ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Factures impayées</span>
                            <span class="info-value text-red-500"><?= count($facturesImpayees) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Total impayé</span>
                            <span class="info-value text-secondary font-bold"><?= number_format($totalImpaye, 0, ',', ' ') ?> F</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Total payé</span>
                            <span class="info-value text-green-600"><?= number_format($totalPaye, 0, ',', ' ') ?> F</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Compteurs -->
            <div class="card-snel mb-6">
                <h3 class="font-bold text-primary mb-4">
                    <i class="fas fa-gauge-high text-secondary mr-2"></i>
                    Compteurs du client
                    <span class="text-sm text-gray-400 font-normal ml-2">(<?= $totalCompteurs ?> compteur(s))</span>
                </h3>
                
                <?php if ($totalCompteurs > 0): ?>
                    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php foreach ($compteurs as $compteur): ?>
                            <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="font-bold text-primary"><?= htmlspecialchars($compteur['NumeroSerie']) ?></p>
                                        <p class="text-xs text-gray-500">Installé le <?= date('d/m/Y', strtotime($compteur['DateInstallation'])) ?></p>
                                    </div>
                                    <span class="status-badge <?= $compteur['etat'] === 'actif' ? 'status-actif' : ($compteur['etat'] === 'en_panne' ? 'status-retard' : 'status-inactif') ?>">
                                        <?= ucfirst($compteur['etat']) ?>
                                    </span>
                                </div>
                                <div class="mt-2 flex justify-between text-sm">
                                    <span class="text-gray-500">Index</span>
                                    <span class="font-bold text-primary"><?= number_format($compteur['indexActuel'], 0, ',', ' ') ?> kWh</span>
                                </div>
                                <div class="mt-2 flex justify-between text-sm">
                                    <span class="text-gray-500">Type</span>
                                    <span><?= ucfirst($compteur['typeCompteur'] ?? 'Monophase') ?></span>
                                </div>
                                <a href="/Pages/Agent/Compteurs/details.php?id=<?= $compteur['idCompteur'] ?>" 
                                   class="text-secondary hover:underline text-xs mt-2 block">
                                    Voir détails →
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-gray-500 text-sm">Aucun compteur enregistré pour ce client.</p>
                <?php endif; ?>
            </div>

            <!-- Dernières factures -->
            <div class="card-snel">
                <h3 class="font-bold text-primary mb-4">
                    <i class="fas fa-file-invoice text-secondary mr-2"></i>
                    Dernières factures
                </h3>
                
                <?php if (!empty($factures)): ?>
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
                                        <td class="px-4 py-2 text-sm font-medium text-primary"><?= htmlspecialchars($facture['numeroFacture']) ?></td>
                                        <td class="px-4 py-2 text-sm font-bold text-secondary"><?= number_format($facture['montantTotal'], 0, ',', ' ') ?> F</td>
                                        <td class="px-4 py-2 text-sm text-gray-600"><?= date('d/m/Y', strtotime($facture['dateEmission'])) ?></td>
                                        <td class="px-4 py-2"><span class="status-badge <?= $statutClass ?>"><?= $statutLabel ?></span></td>
                                        <td class="px-4 py-2">
                                            <a href="/Pages/Agent/Factures/details.php?id=<?= $facture['idFacture'] ?>" 
                                               class="text-secondary hover:underline text-sm">Voir</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if (count($factures) > 10): ?>
                        <div class="text-center mt-3">
                            <a href="/Pages/Agent/Factures/index.php?client=<?= $idClient ?>" class="text-secondary hover:underline text-sm">
                                Voir toutes les factures →
                            </a>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-gray-500 text-sm">Aucune facture pour ce client.</p>
                <?php endif; ?>
            </div>

            <!-- Actions -->
            <div class="flex flex-wrap justify-center gap-4 mt-6">
                <a href="modifier.php?id=<?= $idClient ?>" class="btn-secondary px-6 py-2 rounded-lg">
                    <i class="fas fa-edit mr-2"></i> Modifier
                </a>
                <a href="index.php" class="btn-outline px-6 py-2 rounded-lg">
                    <i class="fas fa-arrow-left mr-2"></i> Retour
                </a>
                <a href="/Pages/Agent/Releves/nouveau.php?client=<?= $idClient ?>" class="btn-primary px-6 py-2 rounded-lg">
                    <i class="fas fa-edit mr-2"></i> Relever
                </a>
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