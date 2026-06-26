<?php
// Pages/Agent/Signalements/details.php
session_start();
require_once __DIR__ . '/../../../Classes/Database.php';
require_once __DIR__ . '/../../../Classes/Utilisateur.php';

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

$idSignalement = $_GET['id'] ?? 0;
if ($idSignalement <= 0) {
    header('Location: index.php');
    exit;
}

$db = Database::getInstance()->getConnection();

// Récupérer les détails du signalement
$stmt = $db->prepare("
    SELECT s.*, 
           CONCAT(cl.nom, ' ', cl.prenom) as client_nom,
           cl.telephone as client_telephone,
           cl.email as client_email,
           cl.adresse as client_adresse,
           cp.numeroSerie,
           cp.idCompteur,
           cp.typeCompteur,
           CONCAT(u.nom, ' ', u.prenom) as agent_nom
    FROM Signalements s
    JOIN Client cl ON s.idClient = cl.idClient
    LEFT JOIN Compteur cp ON s.idCompteur = cp.idCompteur
    LEFT JOIN Utilisateurs u ON s.idAgentTraite = u.idUtilisateur
    WHERE s.idSignalement = ?
");
$stmt->execute([$idSignalement]);
$signalement = $stmt->fetch();

if (!$signalement) {
    header('Location: index.php');
    exit;
}

// Récupérer l'historique des actions (simulé)
// Dans une vraie application, vous auriez une table d'historique

$idUtilisateur = $user->getIdUtilisateur();

// Notifications
$notification = new Notification();
$totalNotifs = $notification->countNonLuesUtilisateur($idUtilisateur);

$page_title = 'Détails du signalement - SNEL Agent';
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
        .status-en_attente { background: #fef3c7; color: #92400e; }
        .status-en_cours { background: #dbeafe; color: #1e40af; }
        .status-resolu { background: #dcfce7; color: #166534; }
        .status-rejete { background: #fecaca; color: #991b1b; }
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
        @media (max-width: 1024px) { .sidebar { transform: translateX(-100%); transition: transform 0.3s ease; } .sidebar.open { transform: translateX(0); } .main-content { margin-left: 0; } .topbar { padding: 0.75rem 1rem; } }
        .timeline { position: relative; padding-left: 2rem; }
        .timeline::before { content: ''; position: absolute; left: 0.5rem; top: 0; bottom: 0; width: 2px; background: #e2e8f0; }
        .timeline-item { position: relative; padding-bottom: 1.5rem; }
        .timeline-item::before { content: ''; position: absolute; left: -1.5rem; top: 0.25rem; width: 12px; height: 12px; border-radius: 50%; background: var(--secondary); border: 2px solid white; }
        .timeline-item.completed::before { background: #16a34a; }
        .timeline-item.pending::before { background: #f59e0b; }
        .timeline-item.cancelled::before { background: #dc2626; }
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
            <a href="/Pages/Agent/dashboard.php" class="nav-item">
                <i class="fas fa-home w-5 text-center"></i> Tableau de bord
            </a>
            <a href="/Pages/Agent/Releves/index.php" class="nav-item">
                <i class="fas fa-clipboard-list w-5 text-center"></i> Relevés
            </a>
            <a href="/Pages/Agent/Releves/nouveau.php" class="nav-item">
                <i class="fas fa-plus-circle w-5 text-center"></i> Nouveau relevé
            </a>
            <a href="/Pages/Agent/Clients/index.php" class="nav-item">
                <i class="fas fa-users w-5 text-center"></i> Clients
            </a>
            <a href="/Pages/Agent/Compteurs/index.php" class="nav-item">
                <i class="fas fa-gauge-high w-5 text-center"></i> Compteurs
            </a>
            <a href="/Pages/Agent/Factures/index.php" class="nav-item">
                <i class="fas fa-file-invoice w-5 text-center"></i> Factures
            </a>
            <a href="/Pages/Agent/Paiements/index.php" class="nav-item">
                <i class="fas fa-credit-card w-5 text-center"></i> Paiements
            </a>
            <a href="/Pages/Agent/Signalements/index.php" class="nav-item active">
                <i class="fas fa-exclamation-triangle w-5 text-center"></i> Signalements
            </a>
            <a href="/Pages/Agent/Profil/index.php" class="nav-item">
                <i class="fas fa-user w-5 text-center"></i> Mon profil
            </a>
        </nav>
        
        <div class="absolute bottom-4 left-4 right-4">
            <a href="/logout.php" class="nav-item text-red-400 hover:text-red-300 hover:bg-red-500/10">
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
                <a href="index.php" class="text-primary hover:text-secondary transition">
                    <i class="fas fa-arrow-left"></i> Retour
                </a>
                <h1 class="text-lg font-bold text-primary hidden sm:block">Détails du signalement</h1>
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
        <div class="max-w-4xl mx-auto">
            
            <!-- En-tête -->
            <div class="bg-white rounded-xl shadow-sm p-6 mb-6 border-l-4 <?= $signalement['statut'] === 'en_attente' ? 'border-yellow-500' : ($signalement['statut'] === 'en_cours' ? 'border-blue-500' : ($signalement['statut'] === 'resolu' ? 'border-green-500' : 'border-red-500')) ?>">
                <div class="flex flex-wrap justify-between items-start">
                    <div>
                        <p class="text-xs text-gray-500">Référence</p>
                        <h2 class="text-2xl font-bold text-primary"><?= htmlspecialchars($signalement['reference']) ?></h2>
                        <p class="text-sm text-gray-500">Signalé le <?= date('d/m/Y H:i', strtotime($signalement['dateCreation'])) ?></p>
                    </div>
                    <div class="flex flex-wrap gap-2 mt-2 sm:mt-0">
                        <span class="status-badge <?= 'status-' . $signalement['statut'] ?>">
                            <?= ucfirst(str_replace('_', ' ', $signalement['statut'])) ?>
                        </span>
                        <?php if ($signalement['priorite'] === 'haute'): ?>
                            <span class="text-xs bg-red-100 text-red-700 px-3 py-1 rounded-full font-semibold">
                                <i class="fas fa-exclamation-circle mr-1"></i> Urgent
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Informations client -->
            <div class="card-snel mb-6">
                <h3 class="font-bold text-primary mb-3">
                    <i class="fas fa-user text-secondary mr-2"></i>
                    Client
                </h3>
                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs text-gray-500">Nom</p>
                        <p class="font-medium"><?= htmlspecialchars($signalement['client_nom']) ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Téléphone</p>
                        <p class="font-medium"><?= htmlspecialchars($signalement['client_telephone']) ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Email</p>
                        <p class="font-medium"><?= htmlspecialchars($signalement['client_email']) ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Adresse</p>
                        <p class="font-medium"><?= htmlspecialchars($signalement['client_adresse'] ?: 'Non renseignée') ?></p>
                    </div>
                </div>
            </div>

            <!-- Informations techniques -->
            <div class="grid md:grid-cols-2 gap-4 mb-6">
                <div class="card-snel">
                    <h4 class="font-semibold text-primary mb-2">
                        <i class="fas fa-info-circle text-secondary mr-2"></i>Type de signalement
                    </h4>
                    <p class="font-medium"><?= ucfirst(str_replace('_', ' ', $signalement['type'])) ?></p>
                    <?php if ($signalement['consommationActuelle']): ?>
                        <p class="text-sm text-gray-500 mt-1">Consommation actuelle: <?= number_format($signalement['consommationActuelle'], 2, ',', ' ') ?> kWh</p>
                    <?php endif; ?>
                </div>
                <div class="card-snel">
                    <h4 class="font-semibold text-primary mb-2">
                        <i class="fas fa-gauge-high text-secondary mr-2"></i>Compteur
                    </h4>
                    <?php if ($signalement['numeroSerie']): ?>
                        <p class="font-medium"><?= htmlspecialchars($signalement['numeroSerie']) ?></p>
                        <p class="text-sm text-gray-500">Type: <?= ucfirst($signalement['typeCompteur'] ?? 'Monophase') ?></p>
                    <?php else: ?>
                        <p class="text-gray-500 text-sm">Aucun compteur associé</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Description -->
            <div class="card-snel mb-6">
                <h4 class="font-semibold text-primary mb-2">
                    <i class="fas fa-comment text-secondary mr-2"></i>Description
                </h4>
                <div class="bg-gray-50 rounded-lg p-4 whitespace-pre-wrap">
                    <?= nl2br(htmlspecialchars($signalement['description'])) ?>
                </div>
            </div>

            <!-- Commentaire de l'agent -->
            <?php if ($signalement['commentaireAgent']): ?>
            <div class="card-snel mb-6 border-l-4 border-secondary">
                <h4 class="font-semibold text-primary mb-2">
                    <i class="fas fa-user-check text-secondary mr-2"></i>Commentaire de l'agent
                </h4>
                <div class="bg-blue-50 rounded-lg p-4">
                    <p class="text-gray-700"><?= nl2br(htmlspecialchars($signalement['commentaireAgent'])) ?></p>
                    <?php if ($signalement['agent_nom']): ?>
                        <p class="text-xs text-gray-500 mt-2">Par: <?= htmlspecialchars($signalement['agent_nom']) ?> le <?= date('d/m/Y H:i', strtotime($signalement['dateTraite'])) ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Timeline -->
            <div class="card-snel">
                <h4 class="font-semibold text-primary mb-4">
                    <i class="fas fa-history text-secondary mr-2"></i>Historique
                </h4>
                <div class="timeline">
                    <div class="timeline-item completed">
                        <p class="font-medium text-sm">Signalement créé</p>
                        <p class="text-xs text-gray-500"><?= date('d/m/Y H:i', strtotime($signalement['dateCreation'])) ?></p>
                        <p class="text-xs text-gray-400">Par: <?= htmlspecialchars($signalement['client_nom']) ?></p>
                    </div>
                    
                    <?php if ($signalement['statut'] === 'en_cours' || $signalement['statut'] === 'resolu' || $signalement['statut'] === 'rejete'): ?>
                    <div class="timeline-item <?= $signalement['statut'] === 'en_cours' ? 'pending' : 'completed' ?>">
                        <p class="font-medium text-sm">Prise en charge</p>
                        <p class="text-xs text-gray-500"><?= $signalement['dateTraite'] ? date('d/m/Y H:i', strtotime($signalement['dateTraite'])) : 'En cours...' ?></p>
                        <?php if ($signalement['agent_nom']): ?>
                            <p class="text-xs text-gray-400">Par: <?= htmlspecialchars($signalement['agent_nom']) ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($signalement['statut'] === 'resolu'): ?>
                    <div class="timeline-item completed">
                        <p class="font-medium text-sm text-green-600">✅ Résolu</p>
                        <p class="text-xs text-gray-500"><?= $signalement['dateTraite'] ? date('d/m/Y H:i', strtotime($signalement['dateTraite'])) : '' ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($signalement['statut'] === 'rejete'): ?>
                    <div class="timeline-item cancelled">
                        <p class="font-medium text-sm text-red-600">❌ Rejeté</p>
                        <p class="text-xs text-gray-500"><?= $signalement['dateTraite'] ? date('d/m/Y H:i', strtotime($signalement['dateTraite'])) : '' ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex flex-wrap justify-center gap-4 mt-6">
                <a href="index.php" class="btn-outline px-6 py-2 rounded-lg">
                    <i class="fas fa-arrow-left mr-2"></i> Retour
                </a>
                <?php if ($signalement['statut'] !== 'resolu' && $signalement['statut'] !== 'rejete'): ?>
                    <a href="traiter.php?id=<?= $idSignalement ?>" class="btn-secondary px-6 py-2 rounded-lg">
                        <i class="fas fa-edit mr-2"></i> Traiter
                    </a>
                <?php endif; ?>
                <a href="#" class="btn-primary px-6 py-2 rounded-lg" onclick="window.print()">
                    <i class="fas fa-print mr-2"></i> Imprimer
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