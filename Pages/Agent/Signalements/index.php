<?php
// Pages/Agent/Signalements/index.php
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

if (!in_array($user->getRole(), ['agent', 'administrateur'])) {
    header('Location: /Pages/Client/dashboard.php');
    exit;
}

$idUtilisateur = $user->getIdUtilisateur();
$db = Database::getInstance()->getConnection();

// Récupérer les paramètres de filtre
$filtre = $_GET['filtre'] ?? 'tous';
$search = $_GET['search'] ?? '';

// Requête pour les signalements
$sql = "
    SELECT s.*, 
           CONCAT(cl.nom, ' ', cl.prenom) as client_nom,
           cl.telephone as client_telephone,
           cl.email as client_email,
           cp.numeroSerie,
           CONCAT(u.nom, ' ', u.prenom) as agent_nom
    FROM Signalements s
    JOIN Client cl ON s.idClient = cl.idClient
    LEFT JOIN Compteur cp ON s.idCompteur = cp.idCompteur
    LEFT JOIN Utilisateurs u ON s.idAgentTraite = u.idUtilisateur
";

$where = [];
$params = [];

if ($filtre === 'en_attente') {
    $where[] = "s.statut = 'en_attente'";
} elseif ($filtre === 'en_cours') {
    $where[] = "s.statut = 'en_cours'";
} elseif ($filtre === 'resolu') {
    $where[] = "s.statut = 'resolu'";
} elseif ($filtre === 'rejete') {
    $where[] = "s.statut = 'rejete'";
}

if (!empty($search)) {
    $where[] = "(cl.nom LIKE ? OR cl.prenom LIKE ? OR s.reference LIKE ? OR s.description LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($where)) {
    $sql .= " WHERE " . implode(' AND ', $where);
}

$sql .= " ORDER BY s.dateCreation DESC LIMIT 50";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$signalements = $stmt->fetchAll();

// Statistiques
$stmt = $db->query("SELECT COUNT(*) as total FROM Signalements");
$totalSignalements = $stmt->fetch()['total'] ?? 0;

$stmt = $db->query("SELECT COUNT(*) as total FROM Signalements WHERE statut = 'en_attente'");
$enAttente = $stmt->fetch()['total'] ?? 0;

$stmt = $db->query("SELECT COUNT(*) as total FROM Signalements WHERE statut = 'en_cours'");
$enCours = $stmt->fetch()['total'] ?? 0;

$stmt = $db->query("SELECT COUNT(*) as total FROM Signalements WHERE statut = 'resolu'");
$resolus = $stmt->fetch()['total'] ?? 0;

$stmt = $db->query("SELECT COUNT(*) as total FROM Signalements WHERE statut = 'rejete'");
$rejetes = $stmt->fetch()['total'] ?? 0;

// Notifications
$notification = new Notification();
$totalNotifs = $notification->countNonLuesUtilisateur($idUtilisateur);

$page_title = 'Signalements - SNEL Agent';
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
        .btn-outline { border: 2px solid var(--secondary); color: var(--secondary); background: transparent; transition: all 0.3s ease; }
        .btn-outline:hover { background: var(--secondary); color: white; }
        .card-snel { background: white; border-radius: 1rem; padding: 1.25rem; border: 1px solid #e2e8f0; transition: all 0.3s ease; }
        .card-snel:hover { box-shadow: 0 8px 30px rgba(0,0,0,0.06); }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.65rem; font-weight: 600; text-transform: uppercase; }
        .status-en_attente { background: #fef3c7; color: #92400e; }
        .status-en_cours { background: #dbeafe; color: #1e40af; }
        .status-resolu { background: #dcfce7; color: #166534; }
        .status-rejete { background: #fecaca; color: #991b1b; }
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
        .signalement-card { transition: all 0.3s ease; border-left: 4px solid transparent; }
        .signalement-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.08); }
        .signalement-card.urgence { border-left-color: #dc2626; }
        .signalement-card.info { border-left-color: #2563eb; }
        .signalement-card.normal { border-left-color: #16a34a; }
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
                <span class="text-accent text-[10px] block -mt-0.5 font-semibold tracking-wider">Espace Agent</span>
            </div>
        </div>
        
        <nav class="space-y-1">
            <a href="../dashboard.php" class="nav-item">
                <i class="fas fa-home w-5 text-center"></i> Tableau de bord
            </a>
            <a href="../Releves/index.php" class="nav-item">
                <i class="fas fa-clipboard-list w-5 text-center"></i> Relevés
            </a>
            <a href="../Releves/nouveau.php" class="nav-item">
                <i class="fas fa-plus-circle w-5 text-center"></i> Nouveau relevé
            </a>
            <a href="../Clients/index.php" class="nav-item">
                <i class="fas fa-users w-5 text-center"></i> Clients
            </a>
            <a href="../Compteurs/index.php" class="nav-item">
                <i class="fas fa-gauge-high w-5 text-center"></i> Compteurs
            </a>
            <a href="../Factures/index.php" class="nav-item">
                <i class="fas fa-file-invoice w-5 text-center"></i> Factures
            </a>
            <a href="../Paiements/index.php" class="nav-item">
                <i class="fas fa-credit-card w-5 text-center"></i> Paiements
            </a>
            <a href="../Signalements/index.php" class="nav-item active">
                <i class="fas fa-exclamation-triangle w-5 text-center"></i> Signalements
                <?php if ($enAttente > 0): ?>
                    <span class="badge" style="background:#ef4444;"><?= $enAttente ?></span>
                <?php endif; ?>
            </a>
            <a href="../Profil/index.php" class="nav-item">
                <i class="fas fa-user w-5 text-center"></i> Mon profil
            </a>
        </nav>
        
        <div class="absolute bottom-4 left-4 right-4">
            <a href="../../logout.php" class="nav-item text-red-400 hover:text-red-300 hover:bg-red-500/10">
                <i class="fas fa-sign-out-alt w-5 text-center"></i> Déconnexion
            </a>
            <div class="text-center text-xs text-white/30 mt-3">v2.0.1 | Agent</div>
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
                    <h1 class="text-lg font-bold text-primary hidden sm:block">Signalements</h1>
                    <p class="text-xs text-gray-400 hidden sm:block">Gestion des anomalies signalées</p>
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
                            <a href="/Pages/Agent/Profil/index.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition">
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
        
        <!-- Statistiques -->
        <div class="grid grid-cols-5 gap-4 mb-6">
            <div class="card-snel text-center">
                <p class="text-xs text-gray-500">Total</p>
                <p class="text-2xl font-bold text-primary"><?= number_format($totalSignalements) ?></p>
            </div>
            <div class="card-snel text-center border-l-4 border-yellow-500">
                <p class="text-xs text-gray-500">En attente</p>
                <p class="text-2xl font-bold text-yellow-600"><?= number_format($enAttente) ?></p>
            </div>
            <div class="card-snel text-center border-l-4 border-blue-500">
                <p class="text-xs text-gray-500">En cours</p>
                <p class="text-2xl font-bold text-blue-600"><?= number_format($enCours) ?></p>
            </div>
            <div class="card-snel text-center border-l-4 border-green-500">
                <p class="text-xs text-gray-500">Résolus</p>
                <p class="text-2xl font-bold text-green-600"><?= number_format($resolus) ?></p>
            </div>
            <div class="card-snel text-center border-l-4 border-red-500">
                <p class="text-xs text-gray-500">Rejetés</p>
                <p class="text-2xl font-bold text-red-600"><?= number_format($rejetes) ?></p>
            </div>
        </div>

        <!-- Filtres et recherche -->
        <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex flex-wrap gap-2">
                    <a href="?filtre=tous" class="filter-btn <?= $filtre === 'tous' ? 'active' : '' ?>">Tous</a>
                    <a href="?filtre=en_attente" class="filter-btn <?= $filtre === 'en_attente' ? 'active' : '' ?>">
                        En attente
                        <?php if ($enAttente > 0): ?>
                            <span class="ml-1 text-xs bg-red-500 text-white px-1.5 py-0.5 rounded-full"><?= $enAttente ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="?filtre=en_cours" class="filter-btn <?= $filtre === 'en_cours' ? 'active' : '' ?>">En cours</a>
                    <a href="?filtre=resolu" class="filter-btn <?= $filtre === 'resolu' ? 'active' : '' ?>">Résolus</a>
                    <a href="?filtre=rejete" class="filter-btn <?= $filtre === 'rejete' ? 'active' : '' ?>">Rejetés</a>
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

        <!-- Liste des signalements -->
        <?php if (!empty($signalements)): ?>
            <div class="space-y-4">
                <?php foreach ($signalements as $signalement): ?>
                    <?php 
                        $statutClass = 'status-' . $signalement['statut'];
                        $urgenceClass = $signalement['priorite'] === 'haute' ? 'urgence' : ($signalement['priorite'] === 'moyenne' ? 'info' : 'normal');
                        $statutLabel = ucfirst(str_replace('_', ' ', $signalement['statut']));
                    ?>
                    <div class="card-snel signalement-card <?= $urgenceClass ?>">
                        <div class="flex flex-wrap items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-3 mb-2">
                                    <span class="status-badge <?= $statutClass ?>"><?= $statutLabel ?></span>
                                    <span class="text-xs text-gray-400">Réf: <?= htmlspecialchars($signalement['reference']) ?></span>
                                    <?php if ($signalement['priorite'] === 'haute'): ?>
                                        <span class="text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded-full font-semibold">
                                            <i class="fas fa-exclamation-circle mr-1"></i> Urgent
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <h3 class="font-bold text-primary">
                                    <?= htmlspecialchars($signalement['client_nom']) ?>
                                </h3>
                                <p class="text-sm text-gray-600 mt-1">
                                    <?= nl2br(htmlspecialchars(substr($signalement['description'], 0, 150))) ?>
                                    <?php if (strlen($signalement['description']) > 150): ?>...<?php endif; ?>
                                </p>
                                
                                <div class="flex flex-wrap gap-4 mt-2 text-xs text-gray-500">
                                    <span><i class="fas fa-calendar mr-1"></i> <?= date('d/m/Y H:i', strtotime($signalement['dateCreation'])) ?></span>
                                    <?php if ($signalement['numeroSerie']): ?>
                                        <span><i class="fas fa-gauge-high mr-1"></i> Compteur: <?= htmlspecialchars($signalement['numeroSerie']) ?></span>
                                    <?php endif; ?>
                                    <span><i class="fas fa-phone mr-1"></i> <?= htmlspecialchars($signalement['client_telephone']) ?></span>
                                    <?php if ($signalement['agent_nom']): ?>
                                        <span><i class="fas fa-user-check mr-1"></i> Traité par: <?= htmlspecialchars($signalement['agent_nom']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="flex flex-col gap-2 mt-2 sm:mt-0">
                                <a href="traiter.php?id=<?= $signalement['idSignalement'] ?>" 
                                   class="btn-secondary text-sm px-4 py-1.5 rounded-lg inline-flex items-center justify-center">
                                    <i class="fas fa-edit mr-1"></i> Traiter
                                </a>
                                <a href="details.php?id=<?= $signalement['idSignalement'] ?>" 
                                   class="btn-primary text-sm px-4 py-1.5 rounded-lg inline-flex items-center justify-center">
                                    <i class="fas fa-eye mr-1"></i> Voir
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                <div class="text-6xl text-gray-300 mb-4"><i class="fas fa-exclamation-triangle"></i></div>
                <h3 class="text-xl font-semibold text-gray-700 mb-2">Aucun signalement trouvé</h3>
                <p class="text-gray-500"><?= $search ? 'Aucun signalement ne correspond à votre recherche.' : 'Aucun signalement enregistré.' ?></p>
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