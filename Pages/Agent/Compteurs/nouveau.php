<?php
// Pages/Agent/Compteurs/nouveau.php
session_start();
require_once __DIR__ . '/../../../Classes/Database.php';
require_once __DIR__ . '/../../../Classes/Utilisateur.php';
require_once __DIR__ . '/../../../Classes/Compteur.php';
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

// Récupérer la liste des clients
$stmt = $db->query("SELECT idClient, nom, prenom, telephone FROM Client ORDER BY nom, prenom");
$clients = $stmt->fetchAll();

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $compteur = new Compteur();
    
    $data = [
        'numeroSerie' => $_POST['numero_serie'] ?? '',
        'dateInstallation' => $_POST['date_installation'] ?? date('Y-m-d'),
        'etat' => $_POST['etat'] ?? 'actif',
        'indexActuel' => $_POST['index_initial'] ?? '0',
        'typeCompteur' => $_POST['type_compteur'] ?? 'monophase',
        'marque' => $_POST['marque'] ?? null,
        'modele' => $_POST['modele'] ?? null,
        'capacite' => $_POST['capacite'] ?? null,
        'tension' => $_POST['tension'] ?? null,
        'emplacement' => $_POST['emplacement'] ?? null,
        'coordonneesGPS' => $_POST['coordonnees_gps'] ?? null,
        'idClient' => $_POST['id_client'] ?? null
    ];
    
    $result = $compteur->enregistrer($data);
    
    if ($result['success']) {
        // Notifier le client
        if ($data['idClient']) {
            $notification = new Notification();
            $notification->notifierClient(
                $data['idClient'],
                'Nouveau compteur installé',
                'Un compteur a été installé chez vous. Numéro de série: ' . $data['numeroSerie'],
                'info'
            );
        }
        
        $message = '✅ Compteur enregistré avec succès !';
        $messageType = 'success';
        header('refresh:2;url=index.php');
    } else {
        $errors = $result['errors'] ?? ['database' => $result['error'] ?? 'Erreur lors de l\'enregistrement'];
        $message = '❌ ' . implode('<br>', $errors);
        $messageType = 'error';
    }
}

$page_title = 'Nouveau compteur - SNEL Agent';
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
        .input-field { width: 100%; padding: 0.75rem 1rem; border: 2px solid #e2e8f0; border-radius: 0.75rem; transition: all 0.3s ease; background: white; }
        .input-field:focus { border-color: var(--secondary); outline: none; box-shadow: 0 0 0 4px rgba(192,86,33,0.1); }
        .card-snel { background: white; border-radius: 1rem; padding: 1.5rem; border: 1px solid #e2e8f0; transition: all 0.3s ease; }
        .card-snel:hover { box-shadow: 0 8px 30px rgba(0,0,0,0.06); }
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
            <a href="/Pages/Agent/Compteurs/index.php" class="nav-item active">
                <i class="fas fa-gauge-high w-5 text-center"></i> Compteurs
            </a>
            <a href="/Pages/Agent/Factures/index.php" class="nav-item">
                <i class="fas fa-file-invoice w-5 text-center"></i> Factures
            </a>
            <a href="/Pages/Agent/Paiements/index.php" class="nav-item">
                <i class="fas fa-credit-card w-5 text-center"></i> Paiements
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
                <button onclick="toggleMobileMenu()" class="lg:hidden text-primary text-2xl">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <h1 class="text-lg font-bold text-primary hidden sm:block">Nouveau compteur</h1>
                    <p class="text-xs text-gray-400 hidden sm:block">Enregistrer un compteur</p>
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
                    <i class="fas fa-plus-circle text-secondary mr-2"></i>
                    Enregistrer un compteur
                </h2>
                
                <form method="POST" action="">
                    <!-- Informations générales -->
                    <div class="grid md:grid-cols-2 gap-4">
                        <div class="mb-4">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-barcode text-secondary mr-2"></i>Numéro de série <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="numero_serie" class="input-field" placeholder="Ex: SNEL-2024-001" required>
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-calendar text-secondary mr-2"></i>Date d'installation <span class="text-red-500">*</span>
                            </label>
                            <input type="date" name="date_installation" class="input-field" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>

                    <!-- Client -->
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-user text-secondary mr-2"></i>Client
                        </label>
                        <select name="id_client" class="input-field">
                            <option value="">-- Non assigné --</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?= $client['idClient'] ?>">
                                    <?= htmlspecialchars($client['nom'] . ' ' . $client['prenom']) ?> - <?= htmlspecialchars($client['telephone']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Caractéristiques techniques -->
                    <div class="grid md:grid-cols-3 gap-4">
                        <div class="mb-4">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-bolt text-secondary mr-2"></i>Type
                            </label>
                            <select name="type_compteur" class="input-field">
                                <option value="monophase">Monophase</option>
                                <option value="triphase">Triphase</option>
                                <option value="prepaye">Prépayé</option>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-tag text-secondary mr-2"></i>Marque
                            </label>
                            <input type="text" name="marque" class="input-field" placeholder="Marque du compteur">
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-tag text-secondary mr-2"></i>Modèle
                            </label>
                            <input type="text" name="modele" class="input-field" placeholder="Modèle du compteur">
                        </div>
                    </div>

                    <div class="grid md:grid-cols-2 gap-4">
                        <div class="mb-4">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-arrows-up-down text-secondary mr-2"></i>Capacité (A)
                            </label>
                            <input type="number" name="capacite" class="input-field" placeholder="Ex: 30, 60, 100">
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-bolt text-secondary mr-2"></i>Tension (V)
                            </label>
                            <input type="text" name="tension" class="input-field" placeholder="Ex: 220V, 380V">
                        </div>
                    </div>

                    <!-- Emplacement -->
                    <div class="grid md:grid-cols-2 gap-4">
                        <div class="mb-4">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-map-marker-alt text-secondary mr-2"></i>Emplacement
                            </label>
                            <input type="text" name="emplacement" class="input-field" placeholder="Adresse ou localisation">
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-map-pin text-secondary mr-2"></i>Coordonnées GPS
                            </label>
                            <input type="text" name="coordonnees_gps" class="input-field" placeholder="Lat, Long">
                        </div>
                    </div>

                    <!-- Index initial -->
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-sort-numeric-up text-secondary mr-2"></i>Index initial (kWh)
                        </label>
                        <input type="number" name="index_initial" class="input-field" value="0" step="0.01">
                        <p class="text-xs text-gray-400 mt-1">L'index initial du compteur (généralement 0 pour un nouveau compteur)</p>
                    </div>

                    <!-- État -->
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-check-circle text-secondary mr-2"></i>État
                        </label>
                        <select name="etat" class="input-field">
                            <option value="actif">Actif</option>
                            <option value="inactif">Inactif</option>
                            <option value="en_panne">En panne</option>
                        </select>
                    </div>

                    <button type="submit" class="btn-success w-full py-3 rounded-xl font-bold text-lg">
                        <i class="fas fa-save mr-2"></i> Enregistrer le compteur
                    </button>
                    
                    <div class="mt-4 text-center">
                        <a href="index.php" class="text-gray-400 hover:text-gray-600 transition text-sm">
                            <i class="fas fa-arrow-left mr-1"></i> Retour à la liste des compteurs
                        </a>
                    </div>
                </form>
            </div>

            <!-- Conseils -->
            <div class="card-snel mt-6 border-l-4 border-secondary">
                <h3 class="font-bold text-primary mb-2">📌 Conseils</h3>
                <ul class="text-sm text-gray-600 space-y-1">
                    <li><i class="fas fa-check-circle text-secondary mr-2"></i> Vérifiez le numéro de série avant l'enregistrement</li>
                    <li><i class="fas fa-check-circle text-secondary mr-2"></i> L'index initial est généralement à 0 pour un compteur neuf</li>
                    <li><i class="fas fa-check-circle text-secondary mr-2"></i> Associez le compteur au bon client</li>
                    <li><i class="fas fa-check-circle text-secondary mr-2"></i> Notez l'emplacement exact pour faciliter les relevés futurs</li>
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
</script>

</body>
</html>