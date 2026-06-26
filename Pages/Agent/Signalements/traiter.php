<?php
// Pages/Agent/Signalements/traiter.php
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
           cp.numeroSerie,
           cp.idCompteur
    FROM Signalements s
    JOIN Client cl ON s.idClient = cl.idClient
    LEFT JOIN Compteur cp ON s.idCompteur = cp.idCompteur
    WHERE s.idSignalement = ?
");
$stmt->execute([$idSignalement]);
$signalement = $stmt->fetch();

if (!$signalement) {
    header('Location: index.php');
    exit;
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $commentaire = $_POST['commentaire'] ?? '';
    $idAgentTraite = $user->getIdUtilisateur();
    
    if (empty($action)) {
        $message = '❌ Veuillez sélectionner une action.';
        $messageType = 'error';
    } else {
        try {
            $db->beginTransaction();
            
            // Mettre à jour le signalement
            $statut = '';
            if ($action === 'prendre_en_charge') {
                $statut = 'en_cours';
            } elseif ($action === 'resoudre') {
                $statut = 'resolu';
            } elseif ($action === 'rejeter') {
                $statut = 'rejete';
            } elseif ($action === 'terminer') {
                $statut = 'resolu';
            }
            
            $stmt = $db->prepare("
                UPDATE Signalements 
                SET statut = ?, 
                    idAgentTraite = ?, 
                    commentaireAgent = ?,
                    dateTraite = NOW()
                WHERE idSignalement = ?
            ");
            $stmt->execute([$statut, $idAgentTraite, $commentaire, $idSignalement]);
            
            // Notifier le client
            $notification = new Notification();
            $titre = '';
            $messageNotif = '';
            
            if ($statut === 'en_cours') {
                $titre = 'Signalement pris en charge';
                $messageNotif = 'Votre signalement N° ' . $signalement['reference'] . ' est en cours de traitement.';
            } elseif ($statut === 'resolu') {
                $titre = 'Signalement résolu';
                $messageNotif = 'Votre signalement N° ' . $signalement['reference'] . ' a été résolu.';
            } elseif ($statut === 'rejete') {
                $titre = 'Signalement rejeté';
                $messageNotif = 'Votre signalement N° ' . $signalement['reference'] . ' a été rejeté. Motif: ' . $commentaire;
            }
            
            if ($statut !== 'en_cours' || $statut !== 'rejete' || $statut !== 'resolu') {
                $notification->notifierClient(
                    $signalement['idClient'],
                    $titre,
                    $messageNotif,
                    $statut === 'resolu' ? 'info' : 'alerte'
                );
            }
            
            $db->commit();
            
            $message = '✅ Signalement traité avec succès !';
            $messageType = 'success';
            header('refresh:2;url=index.php');
            
        } catch (Exception $e) {
            $db->rollBack();
            $message = '❌ Erreur: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

$idUtilisateur = $user->getIdUtilisateur();

// Notifications
$notification = new Notification();
$totalNotifs = $notification->countNonLuesUtilisateur($idUtilisateur);

$page_title = 'Traiter un signalement - SNEL Agent';
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
        .input-field { width: 100%; padding: 0.75rem 1rem; border: 2px solid #e2e8f0; border-radius: 0.75rem; transition: all 0.3s ease; background: white; }
        .input-field:focus { border-color: var(--secondary); outline: none; box-shadow: 0 0 0 4px rgba(192,86,33,0.1); }
        .action-card { cursor: pointer; transition: all 0.3s ease; border: 2px solid #e2e8f0; border-radius: 0.75rem; padding: 1rem; text-align: center; }
        .action-card:hover { border-color: var(--secondary); background: #f8fafc; }
        .action-card.selected { border-color: var(--secondary); background: #fff5ed; }
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
                <h1 class="text-lg font-bold text-primary hidden sm:block">Traiter un signalement</h1>
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
        <div class="max-w-3xl mx-auto">
            
            <?php if ($message): ?>
                <div class="<?= $messageType === 'success' ? 'bg-green-50 border-green-200 text-green-700' : 'bg-red-50 border-red-200 text-red-700' ?> border rounded-xl px-4 py-3 mb-4">
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <!-- Informations du signalement -->
            <div class="card-snel mb-6 border-l-4 <?= $signalement['statut'] === 'en_attente' ? 'border-yellow-500' : ($signalement['statut'] === 'en_cours' ? 'border-blue-500' : ($signalement['statut'] === 'resolu' ? 'border-green-500' : 'border-red-500')) ?>">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-xs text-gray-500">Référence</p>
                        <h3 class="font-bold text-primary"><?= htmlspecialchars($signalement['reference']) ?></h3>
                        <p class="text-sm text-gray-500">Signalé le <?= date('d/m/Y H:i', strtotime($signalement['dateCreation'])) ?></p>
                    </div>
                    <span class="status-badge <?= 'status-' . $signalement['statut'] ?>">
                        <?= ucfirst(str_replace('_', ' ', $signalement['statut'])) ?>
                    </span>
                </div>
            </div>

            <!-- Détails du signalement -->
            <div class="grid md:grid-cols-2 gap-4 mb-6">
                <div class="card-snel">
                    <h4 class="font-semibold text-primary mb-2">
                        <i class="fas fa-user text-secondary mr-2"></i>Client
                    </h4>
                    <p class="font-medium"><?= htmlspecialchars($signalement['client_nom']) ?></p>
                    <p class="text-sm text-gray-500"><?= htmlspecialchars($signalement['client_telephone']) ?></p>
                    <p class="text-sm text-gray-500"><?= htmlspecialchars($signalement['client_email']) ?></p>
                </div>
                <div class="card-snel">
                    <h4 class="font-semibold text-primary mb-2">
                        <i class="fas fa-info-circle text-secondary mr-2"></i>Informations
                    </h4>
                    <p><span class="text-gray-500">Type:</span> <?= ucfirst(str_replace('_', ' ', $signalement['type'])) ?></p>
                    <?php if ($signalement['numeroSerie']): ?>
                        <p><span class="text-gray-500">Compteur:</span> <?= htmlspecialchars($signalement['numeroSerie']) ?></p>
                    <?php endif; ?>
                    <p><span class="text-gray-500">Priorité:</span> 
                        <span class="<?= $signalement['priorite'] === 'haute' ? 'text-red-500 font-bold' : '' ?>">
                            <?= ucfirst($signalement['priorite']) ?>
                        </span>
                    </p>
                </div>
            </div>

            <!-- Description -->
            <div class="card-snel mb-6">
                <h4 class="font-semibold text-primary mb-2">
                    <i class="fas fa-comment text-secondary mr-2"></i>Description
                </h4>
                <p class="text-gray-700 whitespace-pre-wrap"><?= nl2br(htmlspecialchars($signalement['description'])) ?></p>
            </div>

            <!-- Formulaire de traitement -->
            <?php if ($signalement['statut'] !== 'resolu' && $signalement['statut'] !== 'rejete'): ?>
            <div class="card-snel">
                <h4 class="font-semibold text-primary mb-4">
                    <i class="fas fa-tools text-secondary mr-2"></i>Traiter le signalement
                </h4>
                
                <form method="POST" action="">
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Action à effectuer <span class="text-red-500">*</span></label>
                        <div class="grid md:grid-cols-3 gap-3">
                            <?php if ($signalement['statut'] === 'en_attente'): ?>
                                <div class="action-card selected" onclick="selectAction('prendre_en_charge')" id="action-prendre_en_charge">
                                    <i class="fas fa-handshake text-2xl text-blue-500 mb-2 block"></i>
                                    <p class="font-semibold text-sm">Prendre en charge</p>
                                    <p class="text-xs text-gray-500">Commencer le traitement</p>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($signalement['statut'] === 'en_cours' || $signalement['statut'] === 'en_attente'): ?>
                                <div class="action-card <?= $signalement['statut'] !== 'en_attente' ? 'selected' : '' ?>" onclick="selectAction('resoudre')" id="action-resoudre">
                                    <i class="fas fa-check-circle text-2xl text-green-500 mb-2 block"></i>
                                    <p class="font-semibold text-sm">Résoudre</p>
                                    <p class="text-xs text-gray-500">Marquer comme résolu</p>
                                </div>
                            <?php endif; ?>
                            
                            <div class="action-card" onclick="selectAction('rejeter')" id="action-rejeter">
                                <i class="fas fa-times-circle text-2xl text-red-500 mb-2 block"></i>
                                <p class="font-semibold text-sm">Rejeter</p>
                                <p class="text-xs text-gray-500">Rejeter le signalement</p>
                            </div>
                        </div>
                        <input type="hidden" name="action" id="action-input" value="<?= $signalement['statut'] === 'en_attente' ? 'prendre_en_charge' : 'resoudre' ?>">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-comment text-secondary mr-2"></i>Commentaire
                        </label>
                        <textarea name="commentaire" class="input-field" rows="4" placeholder="Ajoutez un commentaire sur le traitement..."></textarea>
                        <p class="text-xs text-gray-400 mt-1">Ce commentaire sera visible par le client.</p>
                    </div>

                    <button type="submit" class="btn-secondary w-full py-3 rounded-xl font-bold text-lg">
                        <i class="fas fa-save mr-2"></i> Valider le traitement
                    </button>
                </form>
            </div>
            <?php else: ?>
            <div class="card-snel bg-gray-50">
                <div class="text-center py-4">
                    <i class="fas fa-check-circle text-4xl text-green-500 mb-3 block"></i>
                    <h4 class="font-bold text-primary">Signalement <?= $signalement['statut'] === 'resolu' ? 'résolu' : 'rejeté' ?></h4>
                    <p class="text-gray-500 text-sm">Ce signalement a déjà été traité.</p>
                    <?php if ($signalement['commentaireAgent']): ?>
                        <div class="mt-3 p-3 bg-white rounded-lg text-left">
                            <p class="text-xs text-gray-500">Commentaire:</p>
                            <p class="text-sm"><?= nl2br(htmlspecialchars($signalement['commentaireAgent'])) ?></p>
                        </div>
                    <?php endif; ?>
                    <a href="index.php" class="btn-primary inline-block mt-4 px-6 py-2 rounded-lg">
                        <i class="fas fa-arrow-left mr-2"></i> Retour aux signalements
                    </a>
                </div>
            </div>
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
    
    function selectAction(action) {
        document.querySelectorAll('.action-card').forEach(el => el.classList.remove('selected'));
        document.getElementById('action-' + action).classList.add('selected');
        document.getElementById('action-input').value = action;
    }
</script>

</body>
</html>