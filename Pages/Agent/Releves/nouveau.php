<?php
// Pages/Agent/Releves/nouveau.php
session_start();
require_once __DIR__ . '/../../../Classes/Database.php';
require_once __DIR__ . '/../../../Classes/Utilisateur.php';
require_once __DIR__ . '/../../../Classes/Client.php';
require_once __DIR__ . '/../../../Classes/Compteur.php';
require_once __DIR__ . '/../../../Classes/Consommation.php';
require_once __DIR__ . '/../../../Classes/Facture.php';
require_once __DIR__ . '/../../../Classes/Notification.php';

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

if (!in_array($user->getRole(), ['agent', 'administrateur'])) {
    header('Location: /Pages/Client/dashboard.php');
    exit;
}

$idUtilisateur = $user->getIdUtilisateur();
$db = Database::getInstance()->getConnection();

// Récupérer la liste des clients pour le sélecteur
$stmt = $db->query("
    SELECT idClient, nom, prenom, telephone 
    FROM Client 
    ORDER BY nom, prenom
");
$clients = $stmt->fetchAll();

// Récupérer les compteurs d'un client spécifique si demandé
$clientId = $_GET['client'] ?? 0;
$compteurs = [];
if ($clientId > 0) {
    $stmt = $db->prepare("SELECT * FROM Compteur WHERE idClient = ? AND etat = 'actif'");
    $stmt->execute([$clientId]);
    $compteurs = $stmt->fetchAll();
}

// Traitement du formulaire
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idCompteur = $_POST['id_compteur'] ?? 0;
    $nouvelIndex = $_POST['nouvel_index'] ?? '';
    $dateReleve = $_POST['date_releve'] ?? date('Y-m-d');
    $observations = $_POST['observations'] ?? '';
    
    // Validation
    $errors = [];
    if (empty($idCompteur)) $errors[] = 'Veuillez sélectionner un compteur.';
    if (empty($nouvelIndex)) $errors[] = 'Veuillez saisir le nouvel index.';
    if (!is_numeric($nouvelIndex)) $errors[] = 'L\'index doit être un nombre valide.';
    
    if (empty($errors)) {
        try {
            // Récupérer les informations du compteur
            $stmt = $db->prepare("
                SELECT c.*, CONCAT(cl.nom, ' ', cl.prenom) as client_nom, cl.idClient, cl.email, cl.telephone
                FROM Compteur c
                JOIN Client cl ON c.idClient = cl.idClient
                WHERE c.idCompteur = ?
            ");
            $stmt->execute([$idCompteur]);
            $compteur = $stmt->fetch();
            
            if (!$compteur) {
                throw new Exception('Compteur non trouvé.');
            }
            
            $ancienIndex = $compteur['indexActuel'];
            $consommation = floatval($nouvelIndex) - floatval($ancienIndex);
            
            if ($consommation < 0) {
                throw new Exception('Le nouvel index ne peut pas être inférieur à l\'ancien index (' . $ancienIndex . ' kWh).');
            }
            
            // ============================================ //
            // DÉMARRER LA TRANSACTION ICI
            // ============================================ //
            $db->beginTransaction();
            
            try {
                // 1. Enregistrer la consommation
                $stmt = $db->prepare("
                    INSERT INTO Consommation (
                        dateDebut, dateFin, indexAncien, indexNouveau, quantiteCons,
                        consommationJournaliere, observations, idCompteur, idAgentReleve
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $dateDebut = date('Y-m-d', strtotime('-1 month', strtotime($dateReleve)));
                $jours = (strtotime($dateReleve) - strtotime($dateDebut)) / (60 * 60 * 24);
                $consommationJournaliere = $jours > 0 ? $consommation / $jours : 0;
                
                $stmt->execute([
                    $dateDebut,
                    $dateReleve,
                    $ancienIndex,
                    $nouvelIndex,
                    $consommation,
                    $consommationJournaliere,
                    $observations,
                    $idCompteur,
                    $idUtilisateur
                ]);
                
                $idConsommation = $db->lastInsertId();
                
                // 2. Mettre à jour l'index du compteur
                $stmt = $db->prepare("UPDATE Compteur SET indexActuel = ? WHERE idCompteur = ?");
                $stmt->execute([$nouvelIndex, $idCompteur]);
                
                // 3. Générer la facture
                $tarif = 75;
                $montantTotal = $consommation * $tarif;
                
                $annee = date('Y');
                $mois = date('m');
                $stmt = $db->query("SELECT MAX(CAST(SUBSTRING(numeroFacture, -6) AS UNSIGNED)) as last_num 
                                   FROM Facture WHERE numeroFacture LIKE 'FAC-" . $annee . $mois . "-%'");
                $last = $stmt->fetch()['last_num'] ?? 0;
                $numeroFacture = 'FAC-' . $annee . $mois . '-' . str_pad($last + 1, 6, '0', STR_PAD_LEFT);
                
                $dateLimite = date('Y-m-d', strtotime('+30 days', strtotime($dateReleve)));
                
                $stmt = $db->prepare("
                    INSERT INTO Facture (
                        numeroFacture, dateEmission, dateEcheance, dateLimitePaiement,
                        montantTotal, statut, typeFacture,
                        periodeConsoDebut, periodeConsoFin,
                        idClient, idConsommation, idAgentCreation
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $numeroFacture,
                    $dateReleve,
                    date('Y-m-d', strtotime('+15 days', strtotime($dateReleve))),
                    $dateLimite,
                    $montantTotal,
                    'en_attente',
                    'normale',
                    $dateDebut,
                    $dateReleve,
                    $compteur['idClient'],
                    $idConsommation,
                    $idUtilisateur
                ]);
                
                // 4. Notifier le client
                $notification = new Notification();
                $notification->notifierClient(
                    $compteur['idClient'],
                    'Nouvelle facture disponible',
                    'Votre facture de ' . number_format($montantTotal, 0, ',', ' ') . ' FCFA est disponible. Consommation: ' . number_format($consommation, 2, ',', ' ') . ' kWh. Date limite: ' . date('d/m/Y', strtotime($dateLimite)),
                    'alerte',
                    '/Pages/Client/Factures/index.php'
                );
                
                // 5. VALIDER LA TRANSACTION
                $db->commit();
                
                $message = '✅ Relevé effectué avec succès ! Consommation: ' . number_format($consommation, 2, ',', ' ') . ' kWh - Montant: ' . number_format($montantTotal, 0, ',', ' ') . ' FCFA';
                $messageType = 'success';
                
                header('refresh:3;url=index.php');
                
            } catch (Exception $e) {
                // ANNULER LA TRANSACTION EN CAS D'ERREUR
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $e;
            }
            
        } catch (Exception $e) {
            $message = '❌ Erreur: ' . $e->getMessage();
            $messageType = 'error';
        }
    } else {
        $message = '❌ ' . implode('<br>', $errors);
        $messageType = 'error';
    }
}

// Récupérer les notifications non lues
$notification = new Notification();
$totalNotifs = $notification->countNonLuesUtilisateur($idUtilisateur);

$page_title = 'Nouveau relevé - SNEL Agent';
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
        
        :root {
            --primary: #1a365d;
            --secondary: #c05621;
            --accent: #ecc94b;
        }
        
        .bg-primary { background: var(--primary); }
        .bg-secondary { background: var(--secondary); }
        .text-primary { color: var(--primary); }
        .text-secondary { color: var(--secondary); }
        
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
        
        .input-field {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 0.75rem;
            transition: all 0.3s ease;
            background: white;
        }
        .input-field:focus {
            border-color: var(--secondary);
            outline: none;
            box-shadow: 0 0 0 4px rgba(192, 86, 33, 0.1);
        }
        .input-field.error {
            border-color: #ef4444;
        }
        .input-field.success {
            border-color: #16a34a;
        }
        
        .card-snel {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
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
            <a href="../dashboard.php" class="nav-item">
                <i class="fas fa-home w-5 text-center"></i> Tableau de bord
            </a>
            <a href="../Releves/index.php" class="nav-item">
                <i class="fas fa-clipboard-list w-5 text-center"></i> Relevés
            </a>
            <a href="nouveau.php" class="nav-item active">
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
            <a href="../Signalements/index.php" class="nav-item">
                <i class="fas fa-exclamation-triangle w-5 text-center"></i> Signalements
            </a>
            <a href="../Profil/index.php" class="nav-item">
                <i class="fas fa-user w-5 text-center"></i> Mon profil
            </a>
        </nav>
        
        <div class="absolute bottom-4 left-4 right-4">
            <a href="/logout.php" class="nav-item text-red-400 hover:text-red-300 hover:bg-red-500/10">
                <i class="fas fa-sign-out-alt w-5 text-center"></i> Déconnexion
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
                    <h1 class="text-lg font-bold text-primary hidden sm:block">Nouveau relevé</h1>
                    <p class="text-xs text-gray-400 hidden sm:block">Saisir l'index d'un compteur</p>
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
                            <a href="/logout.php" class="flex items-center px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition">
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
        
        <div class="max-w-3xl mx-auto">
            
            <!-- Message -->
            <?php if ($message): ?>
                <div class="<?= $messageType === 'success' ? 'bg-green-50 border-green-200 text-green-700' : 'bg-red-50 border-red-200 text-red-700' ?> border rounded-xl px-4 py-3 mb-4">
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <!-- Formulaire -->
            <div class="card-snel">
                <h2 class="text-xl font-bold text-primary mb-4">
                    <i class="fas fa-edit text-secondary mr-2"></i>
                    Saisie d'un relevé
                </h2>
                
                <form method="POST" action="">
                    <!-- Sélection du client -->
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-user text-secondary mr-2"></i>Client <span class="text-red-500">*</span>
                        </label>
                        <select name="id_client" id="id_client" class="input-field" required onchange="loadCompteurs()">
                            <option value="">-- Sélectionner un client --</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?= $client['idClient'] ?>" <?= ($clientId == $client['idClient']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($client['nom'] . ' ' . $client['prenom']) ?> - <?= htmlspecialchars($client['telephone']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Sélection du compteur -->
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-gauge-high text-secondary mr-2"></i>Compteur <span class="text-red-500">*</span>
                        </label>
                        <select name="id_compteur" id="id_compteur" class="input-field" required>
                            <option value="">-- Sélectionner un compteur --</option>
                            <?php if ($clientId > 0): ?>
                                <?php foreach ($compteurs as $compteur): ?>
                                    <option value="<?= $compteur['idCompteur'] ?>">
                                        <?= htmlspecialchars($compteur['NumeroSerie']) ?> 
                                        (Index: <?= number_format($compteur['indexActuel'], 0, ',', ' ') ?> kWh) - <?= ucfirst($compteur['typeCompteur'] ?? 'Monophase') ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <p class="text-xs text-gray-400 mt-1" id="compteur_info"></p>
                    </div>

                    <!-- Nouvel index -->
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-sort-numeric-up text-secondary mr-2"></i>Nouvel index (kWh) <span class="text-red-500">*</span>
                        </label>
                        <input type="number" name="nouvel_index" id="nouvel_index" class="input-field" 
                               placeholder="Saisir le nouvel index du compteur" step="0.01" required>
                        <p class="text-xs text-gray-400 mt-1" id="index_info"></p>
                    </div>

                    <!-- Date du relevé -->
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-calendar text-secondary mr-2"></i>Date du relevé <span class="text-red-500">*</span>
                        </label>
                        <input type="date" name="date_releve" class="input-field" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <!-- Observations -->
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-comment text-secondary mr-2"></i>Observations
                        </label>
                        <textarea name="observations" class="input-field" rows="3" placeholder="État du compteur, anomalies constatées, etc."></textarea>
                    </div>

                    <!-- Résumé -->
                    <div class="bg-gray-50 rounded-xl p-4 mb-4">
                        <h4 class="font-semibold text-primary mb-2">📋 Récapitulatif</h4>
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <span class="text-gray-500">Ancien index</span>
                                <p class="font-bold text-primary" id="ancien_index_aff">-</p>
                            </div>
                            <div>
                                <span class="text-gray-500">Nouvel index</span>
                                <p class="font-bold text-primary" id="nouvel_index_aff">-</p>
                            </div>
                            <div>
                                <span class="text-gray-500">Consommation estimée</span>
                                <p class="font-bold text-secondary" id="consommation_aff">-</p>
                            </div>
                            <div>
                                <span class="text-gray-500">Montant estimé</span>
                                <p class="font-bold text-secondary" id="montant_aff">-</p>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn-success w-full py-3 rounded-xl font-bold text-lg">
                        <i class="fas fa-save mr-2"></i> Enregistrer le relevé
                    </button>
                    
                    <div class="mt-4 text-center">
                        <a href="index.php" class="text-gray-400 hover:text-gray-600 transition text-sm">
                            <i class="fas fa-arrow-left mr-1"></i> Retour à la liste des relevés
                        </a>
                    </div>
                </form>
            </div>

            <!-- Guide de saisie -->
            <div class="card-snel mt-6 border-l-4 border-secondary">
                <h3 class="font-bold text-primary mb-2">📌 Conseils pour le relevé</h3>
                <ul class="text-sm text-gray-600 space-y-1">
                    <li><i class="fas fa-check-circle text-secondary mr-2"></i> Vérifiez que le numéro de série du compteur correspond</li>
                    <li><i class="fas fa-check-circle text-secondary mr-2"></i> Lisez correctement l'index (tous les chiffres)</li>
                    <li><i class="fas fa-check-circle text-secondary mr-2"></i> Le nouvel index doit être supérieur à l'ancien</li>
                    <li><i class="fas fa-check-circle text-secondary mr-2"></i> Prenez une photo du compteur si possible</li>
                    <li><i class="fas fa-check-circle text-secondary mr-2"></i> Signalez toute anomalie dans les observations</li>
                </ul>
            </div>

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

    // ========== CHARGER LES COMPTEURS ==========
    function loadCompteurs() {
        const clientId = document.getElementById('id_client').value;
        const compteurSelect = document.getElementById('id_compteur');
        
        if (!clientId) {
            compteurSelect.innerHTML = '<option value="">-- Sélectionner un compteur --</option>';
            return;
        }
        
        window.location.href = 'nouveau.php?client=' + clientId;
    }

    // ========== CALCUL AUTOMATIQUE ==========
    document.addEventListener('DOMContentLoaded', function() {
        const compteursData = <?= json_encode($compteurs) ?>;
        const ancienIndex = (compteursData.length > 0) ? compteursData[0]['indexActuel'] : 0;
        
        const compteurSelect = document.getElementById('id_compteur');
        const nouvelIndexInput = document.getElementById('nouvel_index');
        const ancienIndexAff = document.getElementById('ancien_index_aff');
        const nouvelIndexAff = document.getElementById('nouvel_index_aff');
        const consoAff = document.getElementById('consommation_aff');
        const montantAff = document.getElementById('montant_aff');
        
        compteurSelect.addEventListener('change', function() {
            const selected = this.options[this.selectedIndex];
            if (selected && selected.value) {
                const text = selected.text;
                const match = text.match(/Index: ([\d,]+)/);
                if (match) {
                    const index = parseFloat(match[1].replace(/,/g, ''));
                    ancienIndexAff.textContent = index.toLocaleString() + ' kWh';
                }
            }
            calculer();
        });
        
        nouvelIndexInput.addEventListener('input', calculer);
        
        function calculer() {
            const ancienText = ancienIndexAff.textContent;
            const ancien = parseFloat(ancienText.replace(/[^0-9.]/g, '')) || 0;
            const nouvel = parseFloat(nouvelIndexInput.value) || 0;
            
            if (ancien > 0 && nouvel > 0) {
                const consommation = nouvel - ancien;
                const tarif = 75;
                const montant = consommation * tarif;
                
                nouvelIndexAff.textContent = nouvel.toLocaleString() + ' kWh';
                consoAff.textContent = consommation.toLocaleString() + ' kWh';
                montantAff.textContent = montant.toLocaleString() + ' FCFA';
                
                if (consommation < 0) {
                    consoAff.textContent = '⚠️ Index invalide';
                    consoAff.className = 'font-bold text-red-500';
                } else {
                    consoAff.className = 'font-bold text-secondary';
                }
            } else if (nouvel > 0 && ancien === 0) {
                nouvelIndexAff.textContent = nouvel.toLocaleString() + ' kWh';
                consoAff.textContent = 'Sélectionnez un compteur';
                consoAff.className = 'font-bold text-gray-400';
                montantAff.textContent = '-';
            }
        }
        
        if (compteurSelect.value) {
            const selected = compteurSelect.options[compteurSelect.selectedIndex];
            if (selected) {
                const text = selected.text;
                const match = text.match(/Index: ([\d,]+)/);
                if (match) {
                    const index = parseFloat(match[1].replace(/,/g, ''));
                    ancienIndexAff.textContent = index.toLocaleString() + ' kWh';
                }
            }
        } else if (ancienIndex > 0) {
            ancienIndexAff.textContent = ancienIndex.toLocaleString() + ' kWh';
        }
    });
</script>

</body>
</html>