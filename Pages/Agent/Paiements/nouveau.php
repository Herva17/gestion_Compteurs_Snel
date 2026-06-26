<?php
// Pages/Agent/Paiements/nouveau.php
session_start();
require_once __DIR__ . '/../../../Classes/Database.php';
require_once __DIR__ . '/../../../Classes/Utilisateur.php';
require_once __DIR__ . '/../../../Classes/Paiement.php';
require_once __DIR__ . '/../../../Classes/Facture.php';
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

// Récupérer l'ID de la facture si fourni
$factureId = $_GET['facture'] ?? 0;

// Récupérer la liste des factures impayées
$stmt = $db->prepare("
    SELECT f.*, CONCAT(cl.nom, ' ', cl.prenom) as client_nom
    FROM Facture f
    JOIN Client cl ON f.idClient = cl.idClient
    WHERE f.statut IN ('en_attente', 'en_retard')
    ORDER BY f.dateLimitePaiement ASC
");
$stmt->execute();
$facturesImpayees = $stmt->fetchAll();

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idFacture = $_POST['id_facture'] ?? 0;
    $montant = $_POST['montant'] ?? 0;
    $modePaiement = $_POST['mode_paiement'] ?? '';
    $datePaiement = $_POST['date_paiement'] ?? date('Y-m-d');
    $referenceTransaction = $_POST['reference_transaction'] ?? null;
    $remarques = $_POST['remarques'] ?? null;
    
    // Validation
    $errors = [];
    if (empty($idFacture)) $errors[] = 'Veuillez sélectionner une facture.';
    if (empty($montant) || $montant <= 0) $errors[] = 'Le montant est invalide.';
    if (empty($modePaiement)) $errors[] = 'Veuillez sélectionner un mode de paiement.';
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Vérifier que la facture existe et est impayée
            $stmt = $db->prepare("SELECT * FROM Facture WHERE idFacture = ? AND statut IN ('en_attente', 'en_retard')");
            $stmt->execute([$idFacture]);
            $facture = $stmt->fetch();
            
            if (!$facture) {
                throw new Exception('Facture non trouvée ou déjà payée.');
            }
            
            // Vérifier que le montant ne dépasse pas le montant dû
            if ($montant > $facture['montantTotal']) {
                throw new Exception('Le montant saisi (' . number_format($montant, 0, ',', ' ') . ' F) dépasse le montant de la facture (' . number_format($facture['montantTotal'], 0, ',', ' ') . ' F).');
            }
            
            // Enregistrer le paiement
            $paiement = new Paiement();
            $data = [
                'datePaiement' => $datePaiement,
                'montant' => $montant,
                'modePaiement' => $modePaiement,
                'statut' => 'effectue',
                'referenceTransaction' => $referenceTransaction,
                'remarques' => $remarques,
                'idFacture' => $idFacture,
                'idClient' => $facture['idClient'],
                'idAgentEnregistrement' => $idUtilisateur
            ];
            
            $result = $paiement->enregistrer($data);
            
            if (!$result['success']) {
                throw new Exception($result['error'] ?? 'Erreur lors de l\'enregistrement du paiement.');
            }
            
            // Mettre à jour le statut de la facture
            if ($montant >= $facture['montantTotal']) {
                $stmt = $db->prepare("UPDATE Facture SET statut = 'payee', datePaiementReel = ? WHERE idFacture = ?");
                $stmt->execute([$datePaiement, $idFacture]);
            }
            
            // Notifier le client
            $notification = new Notification();
            $notification->notifierClient(
                $facture['idClient'],
                'Paiement enregistré',
                'Un paiement de ' . number_format($montant, 0, ',', ' ') . ' FCFA a été enregistré pour votre facture N° ' . $facture['numeroFacture'] . '.',
                'info'
            );
            
            $db->commit();
            
            $message = '✅ Paiement enregistré avec succès !';
            $messageType = 'success';
            header('refresh:2;url=index.php');
            
        } catch (Exception $e) {
            $db->rollBack();
            $message = '❌ ' . $e->getMessage();
            $messageType = 'error';
        }
    } else {
        $message = '❌ ' . implode('<br>', $errors);
        $messageType = 'error';
    }
}

// Si une facture est sélectionnée, récupérer ses détails
$factureSelectionnee = null;
if ($factureId > 0) {
    $stmt = $db->prepare("
        SELECT f.*, CONCAT(cl.nom, ' ', cl.prenom) as client_nom, cl.telephone as client_telephone
        FROM Facture f
        JOIN Client cl ON f.idClient = cl.idClient
        WHERE f.idFacture = ?
    ");
    $stmt->execute([$factureId]);
    $factureSelectionnee = $stmt->fetch();
}

$page_title = 'Nouveau paiement - SNEL Agent';
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
        .input-field { width: 100%; padding: 0.75rem 1rem; border: 2px solid #e2e8f0; border-radius: 0.75rem; transition: all 0.3s ease; background: white; }
        .input-field:focus { border-color: var(--secondary); outline: none; box-shadow: 0 0 0 4px rgba(192,86,33,0.1); }
        .card-snel { background: white; border-radius: 1rem; padding: 1.5rem; border: 1px solid #e2e8f0; transition: all 0.3s ease; }
        .card-snel:hover { box-shadow: 0 8px 30px rgba(0,0,0,0.06); }
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
        .payment-method { border: 2px solid #e2e8f0; border-radius: 0.75rem; padding: 0.75rem; cursor: pointer; transition: all 0.3s ease; text-align: center; }
        .payment-method:hover { border-color: var(--secondary); background: #f8fafc; }
        .payment-method.selected { border-color: var(--secondary); background: #fff5ed; }
        .montant-info { border-left: 4px solid var(--secondary); }
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
            <a href="../Paiements/index.php" class="nav-item active">
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
                <button onclick="toggleMobileMenu()" class="lg:hidden text-primary text-2xl">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <h1 class="text-lg font-bold text-primary hidden sm:block">Nouveau paiement</h1>
                    <p class="text-xs text-gray-400 hidden sm:block">Enregistrer un paiement client</p>
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
        <div class="max-w-3xl mx-auto">
            
            <?php if ($message): ?>
                <div class="<?= $messageType === 'success' ? 'bg-green-50 border-green-200 text-green-700' : 'bg-red-50 border-red-200 text-red-700' ?> border rounded-xl px-4 py-3 mb-4">
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <div class="card-snel">
                <h2 class="text-xl font-bold text-primary mb-4">
                    <i class="fas fa-credit-card text-secondary mr-2"></i>
                    Enregistrer un paiement
                </h2>
                
                <form method="POST" action="">
                    <!-- Sélection de la facture -->
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-file-invoice text-secondary mr-2"></i>Facture à payer <span class="text-red-500">*</span>
                        </label>
                        <select name="id_facture" id="id_facture" class="input-field" required onchange="loadFactureDetails()">
                            <option value="">-- Sélectionner une facture impayée --</option>
                            <?php foreach ($facturesImpayees as $facture): ?>
                                <option value="<?= $facture['idFacture'] ?>" <?= ($factureId == $facture['idFacture']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($facture['numeroFacture']) ?> - <?= htmlspecialchars($facture['client_nom']) ?> - <?= number_format($facture['montantTotal'], 0, ',', ' ') ?> FCFA
                                    <?= strtotime($facture['dateLimitePaiement']) < time() ? '⚠️ En retard' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($facturesImpayees)): ?>
                            <p class="text-xs text-green-600 mt-1">✅ Toutes les factures sont payées !</p>
                        <?php endif; ?>
                    </div>

                    <!-- Détails de la facture sélectionnée -->
                    <div id="facture_details" class="bg-gray-50 rounded-xl p-4 mb-4 <?= $factureSelectionnee ? '' : 'hidden' ?>">
                        <?php if ($factureSelectionnee): ?>
                            <div class="grid grid-cols-2 gap-3 text-sm">
                                <div>
                                    <p class="text-xs text-gray-500">Client</p>
                                    <p class="font-medium"><?= htmlspecialchars($factureSelectionnee['client_nom']) ?></p>
                                    <p class="text-xs text-gray-400"><?= htmlspecialchars($factureSelectionnee['client_telephone']) ?></p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Numéro de facture</p>
                                    <p class="font-medium"><?= htmlspecialchars($factureSelectionnee['numeroFacture']) ?></p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Montant total</p>
                                    <p class="font-bold text-secondary"><?= number_format($factureSelectionnee['montantTotal'], 0, ',', ' ') ?> FCFA</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Date limite</p>
                                    <p class="font-medium <?= strtotime($factureSelectionnee['dateLimitePaiement']) < time() ? 'text-red-500' : '' ?>">
                                        <?= date('d/m/Y', strtotime($factureSelectionnee['dateLimitePaiement'])) ?>
                                        <?= strtotime($factureSelectionnee['dateLimitePaiement']) < time() ? '⚠️ En retard' : '' ?>
                                    </p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Montant -->
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-coins text-secondary mr-2"></i>Montant à payer (FCFA) <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input type="number" name="montant" id="montant" class="input-field" 
                                   placeholder="Saisir le montant" step="100" min="0" required
                                   value="<?= $factureSelectionnee ? $factureSelectionnee['montantTotal'] : '' ?>">
                            <div class="absolute right-3 top-1/2 -translate-y-1/2 text-sm text-gray-400">FCFA</div>
                        </div>
                        <div class="mt-1 text-xs text-gray-400" id="montant_info">
                            <?php if ($factureSelectionnee): ?>
                                Montant total de la facture: <span class="font-bold text-secondary"><?= number_format($factureSelectionnee['montantTotal'], 0, ',', ' ') ?> FCFA</span>
                            <?php else: ?>
                                Le montant ne doit pas dépasser le total de la facture
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Mode de paiement -->
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-credit-card text-secondary mr-2"></i>Mode de paiement <span class="text-red-500">*</span>
                        </label>
                        <div class="grid grid-cols-4 gap-3">
                            <div class="payment-method <?= (isset($_POST['mode_paiement']) && $_POST['mode_paiement'] === 'mobile_money') ? 'selected' : '' ?>" 
                                 onclick="selectPayment('mobile_money')" id="method-mobile_money">
                                <i class="fas fa-mobile-alt text-2xl text-secondary"></i>
                                <p class="text-xs font-semibold">Mobile Money</p>
                            </div>
                            <div class="payment-method <?= (isset($_POST['mode_paiement']) && $_POST['mode_paiement'] === 'carte_bancaire') ? 'selected' : '' ?>" 
                                 onclick="selectPayment('carte_bancaire')" id="method-carte_bancaire">
                                <i class="fas fa-credit-card text-2xl text-secondary"></i>
                                <p class="text-xs font-semibold">Carte bancaire</p>
                            </div>
                            <div class="payment-method <?= (isset($_POST['mode_paiement']) && $_POST['mode_paiement'] === 'especes') ? 'selected' : '' ?>" 
                                 onclick="selectPayment('especes')" id="method-especes">
                                <i class="fas fa-money-bill-wave text-2xl text-secondary"></i>
                                <p class="text-xs font-semibold">Espèces</p>
                            </div>
                            <div class="payment-method <?= (isset($_POST['mode_paiement']) && $_POST['mode_paiement'] === 'virement') ? 'selected' : '' ?>" 
                                 onclick="selectPayment('virement')" id="method-virement">
                                <i class="fas fa-university text-2xl text-secondary"></i>
                                <p class="text-xs font-semibold">Virement</p>
                            </div>
                        </div>
                        <input type="hidden" name="mode_paiement" id="mode_paiement" value="<?= $_POST['mode_paiement'] ?? 'mobile_money' ?>">
                    </div>

                    <!-- Date du paiement -->
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-calendar text-secondary mr-2"></i>Date du paiement
                        </label>
                        <input type="date" name="date_paiement" class="input-field" value="<?= date('Y-m-d') ?>">
                    </div>

                    <!-- Référence transaction -->
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-hashtag text-secondary mr-2"></i>Référence de transaction
                        </label>
                        <input type="text" name="reference_transaction" class="input-field" placeholder="Numéro de transaction (optionnel)">
                    </div>

                    <!-- Remarques -->
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-comment text-secondary mr-2"></i>Remarques
                        </label>
                        <textarea name="remarques" class="input-field" rows="2" placeholder="Informations complémentaires (optionnel)"></textarea>
                    </div>

                    <!-- Récapitulatif -->
                    <div class="bg-gray-50 rounded-xl p-4 mb-4 montant-info">
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="text-xs text-gray-500">Montant à payer</p>
                                <p class="text-2xl font-bold text-secondary" id="montant_affichage">
                                    <?= $factureSelectionnee ? number_format($factureSelectionnee['montantTotal'], 0, ',', ' ') . ' FCFA' : '0 FCFA' ?>
                                </p>
                            </div>
                            <div>
                                <span class="status-badge status-attente">En attente de paiement</span>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn-success w-full py-3 rounded-xl font-bold text-lg">
                        <i class="fas fa-check-circle mr-2"></i> Enregistrer le paiement
                    </button>
                    
                    <div class="mt-4 text-center">
                        <a href="index.php" class="text-gray-400 hover:text-gray-600 transition text-sm">
                            <i class="fas fa-arrow-left mr-1"></i> Retour à la liste des paiements
                        </a>
                    </div>
                </form>
            </div>

            <!-- Conseils -->
            <div class="card-snel mt-6 border-l-4 border-secondary">
                <h3 class="font-bold text-primary mb-2">📌 Conseils</h3>
                <ul class="text-sm text-gray-600 space-y-1">
                    <li><i class="fas fa-check-circle text-secondary mr-2"></i> Vérifiez que la facture est bien impayée</li>
                    <li><i class="fas fa-check-circle text-secondary mr-2"></i> Le montant ne doit pas dépasser le montant total de la facture</li>
                    <li><i class="fas fa-check-circle text-secondary mr-2"></i> Notez le numéro de transaction pour le suivi</li>
                    <li><i class="fas fa-check-circle text-secondary mr-2"></i> Le client sera notifié automatiquement</li>
                </ul>
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
    
    function selectPayment(method) {
        document.querySelectorAll('.payment-method').forEach(el => el.classList.remove('selected'));
        document.getElementById('method-' + method).classList.add('selected');
        document.getElementById('mode_paiement').value = method;
    }
    
    function loadFactureDetails() {
        const factureId = document.getElementById('id_facture').value;
        if (factureId) {
            window.location.href = 'nouveau.php?facture=' + factureId;
        }
    }
    
    // Mettre à jour l'affichage du montant en temps réel
    document.addEventListener('DOMContentLoaded', function() {
        const montantInput = document.getElementById('montant');
        const montantAffichage = document.getElementById('montant_affichage');
        
        if (montantInput) {
            montantInput.addEventListener('input', function() {
                const value = parseFloat(this.value) || 0;
                montantAffichage.textContent = value.toLocaleString() + ' FCFA';
            });
        }
    });
</script>

</body>
</html>