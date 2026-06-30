<?php
// Pages/Admin/Compteurs/index.php
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

$search = $_GET['search'] ?? '';
$filtre = $_GET['filtre'] ?? 'tous';

$sql = "
    SELECT c.*, CONCAT(cl.nom, ' ', cl.prenom) as client_nom, cl.telephone as client_telephone
    FROM Compteur c
    LEFT JOIN Client cl ON c.idClient = cl.idClient
";

$where = [];
$params = [];

if ($filtre === 'actif') {
    $where[] = "c.etat = 'actif'";
} elseif ($filtre === 'panne') {
    $where[] = "c.etat = 'en_panne'";
} elseif ($filtre === 'inactif') {
    $where[] = "c.etat = 'inactif'";
} elseif ($filtre === 'non_assignes') {
    $where[] = "c.idClient IS NULL";
}

if (!empty($search)) {
    $where[] = "(c.NumeroSerie LIKE ? OR cl.nom LIKE ? OR cl.prenom LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($where)) {
    $sql .= " WHERE " . implode(' AND ', $where);
}

$sql .= " ORDER BY c.dateCreation DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$compteurs = $stmt->fetchAll();

// Statistiques
$stmt = $db->query("SELECT COUNT(*) as total FROM Compteur");
$totalCompteurs = $stmt->fetch()['total'] ?? 0;

$stmt = $db->query("SELECT COUNT(*) as total FROM Compteur WHERE etat = 'actif'");
$actifs = $stmt->fetch()['total'] ?? 0;

$stmt = $db->query("SELECT COUNT(*) as total FROM Compteur WHERE etat = 'en_panne'");
$pannes = $stmt->fetch()['total'] ?? 0;

$stmt = $db->query("SELECT COUNT(*) as total FROM Compteur WHERE idClient IS NULL");
$nonAssignes = $stmt->fetch()['total'] ?? 0;

// Notifications
$notification = new Notification();
$totalNotifs = $notification->countNonLuesUtilisateur($idUtilisateur);

$page_title = 'Compteurs - SNEL Admin';
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
        .status-panne { background: #fecaca; color: #991b1b; }
        .status-non_assignes { background: #e0e7ff; color: #3730a3; }
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
                <span class="badge"><?= $totalCompteurs ?></span>
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
                    <h1 class="text-lg font-bold text-primary hidden sm:block">Compteurs</h1>
                    <p class="text-xs text-gray-400 hidden sm:block">Gestion des compteurs</p>
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
        
        <!-- Statistiques -->
        <div class="grid grid-cols-4 gap-4 mb-6">
            <div class="card-snel text-center">
                <p class="text-xs text-gray-500">Total</p>
                <p class="text-2xl font-bold text-primary"><?= number_format($totalCompteurs) ?></p>
            </div>
            <div class="card-snel text-center border-l-4 border-green-500">
                <p class="text-xs text-gray-500">Actifs</p>
                <p class="text-2xl font-bold text-green-600"><?= number_format($actifs) ?></p>
            </div>
            <div class="card-snel text-center border-l-4 border-red-500">
                <p class="text-xs text-gray-500">En panne</p>
                <p class="text-2xl font-bold text-red-600"><?= number_format($pannes) ?></p>
            </div>
            <div class="card-snel text-center border-l-4 border-blue-500">
                <p class="text-xs text-gray-500">Non assignés</p>
                <p class="text-2xl font-bold text-blue-600"><?= number_format($nonAssignes) ?></p>
            </div>
        </div>

        <!-- Filtres et recherche -->
        <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex flex-wrap gap-2">
                    <a href="?filtre=tous" class="filter-btn <?= $filtre === 'tous' ? 'active' : '' ?>">Tous</a>
                    <a href="?filtre=actif" class="filter-btn <?= $filtre === 'actif' ? 'active' : '' ?>">Actifs</a>
                    <a href="?filtre=panne" class="filter-btn <?= $filtre === 'panne' ? 'active' : '' ?>">En panne</a>
                    <a href="?filtre=inactif" class="filter-btn <?= $filtre === 'inactif' ? 'active' : '' ?>">Inactifs</a>
                    <a href="?filtre=non_assignes" class="filter-btn <?= $filtre === 'non_assignes' ? 'active' : '' ?>">Non assignés</a>
                </div>
                
                <form method="GET" class="flex items-center gap-2">
                    <input type="hidden" name="filtre" value="<?= $filtre ?>">
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                           class="input-field px-4 py-2 border border-gray-300 rounded-lg focus:border-secondary focus:outline-none text-sm"
                           placeholder="Rechercher...">
                    <button type="submit" class="btn-secondary px-4 py-2 rounded-lg text-sm">
                        <i class="fas fa-search"></i>
                    </button>
                    <?php if ($search): ?>
                        <a href="?" class="btn-outline px-4 py-2 rounded-lg text-sm">
                            <i class="fas fa-times"></i>
                        </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Liste des compteurs -->
        <?php if (!empty($compteurs)): ?>
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Numéro de série</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Client</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Index</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Statut</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($compteurs as $compteur): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm font-medium text-primary">
                                        <?= htmlspecialchars($compteur['NumeroSerie']) ?>
                                        <p class="text-xs text-gray-400">Inst. le <?= date('d/m/Y', strtotime($compteur['DateInstallation'])) ?></p>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        <?= htmlspecialchars($compteur['client_nom'] ?? 'Non assigné') ?>
                                        <p class="text-xs text-gray-400"><?= htmlspecialchars($compteur['client_telephone'] ?? '') ?></p>
                                    </td>
                                    <td class="px-4 py-3 text-sm font-bold text-primary">
                                        <?= number_format($compteur['indexActuel'], 0, ',', ' ') ?> kWh
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <?= ucfirst($compteur['typeCompteur'] ?? 'Monophase') ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="status-badge <?= $compteur['etat'] === 'actif' ? 'status-actif' : ($compteur['etat'] === 'en_panne' ? 'status-panne' : 'status-inactif') ?>">
                                            <?= ucfirst($compteur['etat']) ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <a href="details.php?id=<?= $compteur['idCompteur'] ?>" 
                                           class="btn-primary text-xs px-3 py-1 rounded-lg inline-flex items-center">
                                            <i class="fas fa-eye mr-1"></i> Voir
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                <div class="text-6xl text-gray-300 mb-4"><i class="fas fa-gauge-high"></i></div>
                <h3 class="text-xl font-semibold text-gray-700 mb-2">Aucun compteur trouvé</h3>
                <p class="text-gray-500"><?= $search ? 'Aucun compteur ne correspond à votre recherche.' : 'Aucun compteur enregistré.' ?></p>
            </div>
        <?php endif; ?>

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