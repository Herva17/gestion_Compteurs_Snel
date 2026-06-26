<?php
// Pages/Agent/Factures/details.php
session_start();
require_once __DIR__ . '/../../../Classes/Database.php';
require_once __DIR__ . '/../../../Classes/Utilisateur.php';
require_once __DIR__ . '/../../../Classes/Facture.php';
require_once __DIR__ . '/../../../Classes/Paiement.php';

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

$idFacture = $_GET['id'] ?? 0;
if ($idFacture <= 0) {
    header('Location: index.php');
    exit;
}

$db = Database::getInstance()->getConnection();

// Récupérer les détails de la facture
$stmt = $db->prepare("
    SELECT f.*, 
           CONCAT(cl.nom, ' ', cl.prenom) as client_nom,
           cl.telephone as client_telephone, cl.email as client_email,
           cl.adresse as client_adresse,
           CONCAT(u.nom, ' ', u.prenom) as agent_nom
    FROM Facture f
    JOIN Client cl ON f.idClient = cl.idClient
    LEFT JOIN Utilisateurs u ON f.idAgentCreation = u.idUtilisateur
    WHERE f.idFacture = ?
");
$stmt->execute([$idFacture]);
$facture = $stmt->fetch();

if (!$facture) {
    header('Location: index.php');
    exit;
}

// Récupérer le paiement associé
$stmt = $db->prepare("SELECT * FROM Paiement WHERE idFacture = ?");
$stmt->execute([$idFacture]);
$paiement = $stmt->fetch();

// Récupérer la consommation associée
$stmt = $db->prepare("SELECT * FROM Consommation WHERE idConsommation = ?");
$stmt->execute([$facture['idConsommation']]);
$consommation = $stmt->fetch();

$idUtilisateur = $user->getIdUtilisateur();

// Notifications
$notification = new Notification();
$totalNotifs = $notification->countNonLuesUtilisateur($idUtilisateur);

$page_title = 'Détails de la facture - SNEL Agent';
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
        .card-snel { background: white; border-radius: 1rem; padding: 1.25rem; border: 1px solid #e2e8f0; transition: all 0.3s ease; }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.65rem; font-weight: 600; text-transform: uppercase; }
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
            <a href="../Factures/index.php" class="nav-item active">
                <i class="fas fa-file-invoice w-5 text-center"></i> Factures
            </a>
            <a href="../Paiements/index.php" class="nav-item">
                <i class="fas fa-credit-card w-5 text-center"></i> Paiements
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
                <a href="index.php" class="text-primary hover:text-secondary transition">
                    <i class="fas fa-arrow-left"></i> Retour
                </a>
                <h1 class="text-lg font-bold text-primary hidden sm:block">Détails de la facture</h1>
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
                            <a href="../../Profil/index.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition">
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
                        <p class="text-xs text-gray-500">Facture N°</p>
                        <h2 class="text-2xl font-bold text-primary"><?= htmlspecialchars($facture['numeroFacture']) ?></h2>
                        <p class="text-sm text-gray-500">Émise le <?= date('d/m/Y', strtotime($facture['dateEmission'])) ?></p>
                    </div>
                    <span class="status-badge <?= $facture['statut'] === 'payee' ? 'status-payee' : ($facture['statut'] === 'en_retard' ? 'status-retard' : 'status-attente') ?>">
                        <?= ucfirst($facture['statut']) ?>
                    </span>
                </div>
            </div>

            <!-- Infos client -->
            <div class="card-snel mb-6">
                <h3 class="font-bold text-primary mb-3">
                    <i class="fas fa-user text-secondary mr-2"></i>
                    Client
                </h3>
                <div class="grid md:grid-cols-3 gap-4">
                    <div>
                        <p class="text-xs text-gray-500">Nom</p>
                        <p class="font-medium"><?= htmlspecialchars($facture['client_nom']) ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Téléphone</p>
                        <p class="font-medium"><?= htmlspecialchars($facture['client_telephone']) ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Email</p>
                        <p class="font-medium"><?= htmlspecialchars($facture['client_email']) ?></p>
                    </div>
                    <div class="md:col-span-3">
                        <p class="text-xs text-gray-500">Adresse</p>
                        <p class="font-medium"><?= htmlspecialchars($facture['client_adresse'] ?: 'Non renseignée') ?></p>
                    </div>
                </div>
            </div>

            <!-- Détails de la facture -->
            <div class="grid md:grid-cols-3 gap-4 mb-6">
                <div class="card-snel">
                    <p class="text-xs text-gray-500">Montant Total</p>
                    <p class="text-2xl font-bold text-secondary"><?= number_format($facture['montantTotal'], 0, ',', ' ') ?> F</p>
                </div>
                <div class="card-snel">
                    <p class="text-xs text-gray-500">Détail du montant</p>
                    <div class="text-sm">
                        <div class="flex justify-between"><span class="text-gray-500">HT</span><span><?= number_format($facture['montantHT'] ?? 0, 0, ',', ' ') ?> F</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">TVA (<?= $facture['tauxTVA'] ?? 18 ?>%)</span><span><?= number_format($facture['montantTVA'] ?? 0, 0, ',', ' ') ?> F</span></div>
                        <?php if ($facture['montantPenalite'] > 0): ?>
                            <div class="flex justify-between text-red-500"><span>Pénalités</span><span>+ <?= number_format($facture['montantPenalite'], 0, ',', ' ') ?> F</span></div>
                        <?php endif; ?>
                        <?php if ($facture['montantReduction'] > 0): ?>
                            <div class="flex justify-between text-green-500"><span>Réduction</span><span>- <?= number_format($facture['montantReduction'], 0, ',', ' ') ?> F</span></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-snel">
                    <p class="text-xs text-gray-500">Dates importantes</p>
                    <div class="text-sm">
                        <div class="flex justify-between"><span class="text-gray-500">Échéance</span><span><?= date('d/m/Y', strtotime($facture['dateEcheance'])) ?></span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Date limite</span><span class="<?= strtotime($facture['dateLimitePaiement']) < time() && $facture['statut'] !== 'payee' ? 'text-red-500 font-bold' : '' ?>"><?= date('d/m/Y', strtotime($facture['dateLimitePaiement'])) ?></span></div>
                        <?php if ($facture['datePaiementReel']): ?>
                            <div class="flex justify-between text-green-600"><span>Payée le</span><span><?= date('d/m/Y', strtotime($facture['datePaiementReel'])) ?></span></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Consommation associée -->
            <?php if ($consommation): ?>
            <div class="card-snel mb-6">
                <h3 class="font-bold text-primary mb-3">
                    <i class="fas fa-bolt text-secondary mr-2"></i>
                    Consommation associée
                </h3>
                <div class="grid md:grid-cols-4 gap-4">
                    <div>
                        <p class="text-xs text-gray-500">Période</p>
                        <p class="font-medium text-sm"><?= date('d/m/Y', strtotime($consommation['dateDebut'])) ?> - <?= date('d/m/Y', strtotime($consommation['dateFin'])) ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Index</p>
                        <p class="font-medium text-sm"><?= number_format($consommation['indexAncien'], 0, ',', ' ') ?> → <?= number_format($consommation['indexNouveau'], 0, ',', ' ') ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Consommation</p>
                        <p class="font-bold text-secondary"><?= number_format($consommation['quantiteCons'], 2, ',', ' ') ?> kWh</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Moyenne/jour</p>
                        <p class="font-medium text-sm"><?= number_format($consommation['consommationJournaliere'] ?? 0, 2, ',', ' ') ?> kWh</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Paiement -->
            <div class="card-snel">
                <h3 class="font-bold text-primary mb-3">
                    <i class="fas fa-credit-card text-secondary mr-2"></i>
                    Paiement
                </h3>
                <?php if ($paiement): ?>
                    <div class="grid md:grid-cols-4 gap-4">
                        <div>
                            <p class="text-xs text-gray-500">Référence</p>
                            <p class="font-medium text-sm"><?= htmlspecialchars($paiement['numeroReference']) ?></p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Montant</p>
                            <p class="font-bold text-primary"><?= number_format($paiement['montant'], 0, ',', ' ') ?> F</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Mode</p>
                            <p class="font-medium text-sm capitalize"><?= str_replace('_', ' ', htmlspecialchars($paiement['modePaiement'])) ?></p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Date</p>
                            <p class="font-medium text-sm"><?= date('d/m/Y', strtotime($paiement['datePaiement'])) ?></p>
                        </div>
                    </div>
                <?php else: ?>
                    <p class="text-gray-500 text-sm">Aucun paiement enregistré pour cette facture.</p>
                    <?php if ($facture['statut'] !== 'payee'): ?>
                        <div class="mt-4">
                            <a href="/Pages/Agent/Paiements/nouveau.php?facture=<?= $idFacture ?>" 
                               class="btn-success px-6 py-2 rounded-lg text-sm inline-flex items-center">
                                <i class="fas fa-plus mr-2"></i> Enregistrer un paiement
                            </a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Actions -->
            <div class="flex justify-center gap-4 mt-6">
                <a href="index.php" class="btn-outline px-6 py-2 rounded-lg">
                    <i class="fas fa-arrow-left mr-2"></i> Retour
                </a>
                <?php if ($facture['statut'] !== 'payee'): ?>
                    <a href="/Pages/Agent/Paiements/nouveau.php?facture=<?= $idFacture ?>" 
                       class="btn-success px-6 py-2 rounded-lg">
                        <i class="fas fa-credit-card mr-2"></i> Enregistrer paiement
                    </a>
                <?php endif; ?>
                <?php if ($user->getRole() === 'administrateur'): ?>
                    <a href="#" class="btn-primary px-6 py-2 rounded-lg" onclick="alert('Impression en développement')">
                        <i class="fas fa-print mr-2"></i> Imprimer
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