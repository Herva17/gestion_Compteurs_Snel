<?php
// Pages/Agent/Profil/index.php
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

$idUtilisateur = $user->getIdUtilisateur();

$page_title = 'Mon profil - SNEL Agent';
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
        .input-field { width: 100%; padding: 0.6rem 1rem; border: 2px solid #e2e8f0; border-radius: 0.75rem; transition: all 0.3s ease; background: white; }
        .input-field:focus { border-color: var(--secondary); outline: none; box-shadow: 0 0 0 4px rgba(192,86,33,0.1); }
        .card-snel { background: white; border-radius: 1rem; padding: 1.5rem; border: 1px solid #e2e8f0; transition: all 0.3s ease; }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.65rem; font-weight: 600; text-transform: uppercase; }
        .status-actif { background: #dcfce7; color: #166534; }
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
        .avatar { width: 80px; height: 80px; border-radius: 50%; background: var(--secondary); display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem; font-weight: bold; }
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
            <a href="index.php" class="nav-item active">
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
                    <h1 class="text-lg font-bold text-primary hidden sm:block">Mon profil</h1>
                    <p class="text-xs text-gray-400 hidden sm:block">Gestion de mon compte</p>
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
            
            <?php if (isset($_GET['success'])): ?>
                <div class="bg-green-50 border border-green-200 text-green-600 px-4 py-3 rounded-xl mb-4">
                    <i class="fas fa-check-circle mr-2"></i> Profil mis à jour avec succès !
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['error'])): ?>
                <div class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-xl mb-4">
                    <i class="fas fa-exclamation-circle mr-2"></i> <?= htmlspecialchars($_GET['error']) ?>
                </div>
            <?php endif; ?>

            <div class="grid md:grid-cols-2 gap-6">
                <!-- Informations personnelles -->
                <div class="card-snel">
                    <div class="flex items-center space-x-4 mb-6">
                        <div class="avatar"><?= strtoupper(substr($user->getPrenom(), 0, 1) . substr($user->getNom(), 0, 1)) ?></div>
                        <div>
                            <h2 class="text-xl font-bold text-primary"><?= htmlspecialchars($user->getNom() . ' ' . $user->getPrenom()) ?></h2>
                            <span class="status-badge status-actif"><i class="fas fa-check-circle mr-1"></i> <?= ucfirst($user->getRole()) ?></span>
                        </div>
                    </div>

                    <div class="space-y-1">
                        <div class="info-item">
                            <span class="info-label"><i class="fas fa-envelope text-secondary mr-2"></i>Email</span>
                            <span class="info-value"><?= htmlspecialchars($user->getEmail()) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label"><i class="fas fa-phone text-secondary mr-2"></i>Téléphone</span>
                            <span class="info-value"><?= htmlspecialchars($user->getTelephone() ?: 'Non renseigné') ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label"><i class="fas fa-user-tag text-secondary mr-2"></i>Rôle</span>
                            <span class="info-value"><?= ucfirst($user->getRole()) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label"><i class="fas fa-venus-mars text-secondary mr-2"></i>Sexe</span>
                            <span class="info-value"><?= $user->toArray()['sexe'] ?: 'Non renseigné' ?></span>
                        </div>
                        <?php if ($user->toArray()['dateEmbauche']): ?>
                        <div class="info-item">
                            <span class="info-label"><i class="fas fa-calendar-alt text-secondary mr-2"></i>Date d'embauche</span>
                            <span class="info-value"><?= date('d/m/Y', strtotime($user->toArray()['dateEmbauche'])) ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="info-item">
                            <span class="info-label"><i class="fas fa-clock text-secondary mr-2"></i>Dernière connexion</span>
                            <span class="info-value"><?= $user->toArray()['derniereConnexion'] ? date('d/m/Y H:i', strtotime($user->toArray()['derniereConnexion'])) : 'Jamais' ?></span>
                        </div>
                    </div>

                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <a href="modifier.php" class="btn-primary w-full py-2 rounded-lg font-semibold text-center block">
                            <i class="fas fa-edit mr-2"></i> Modifier mes informations
                        </a>
                    </div>
                </div>

                <!-- Modifier le mot de passe -->
                <div class="card-snel">
                    <h3 class="font-bold text-primary mb-4">
                        <i class="fas fa-lock text-secondary mr-2"></i>Modifier le mot de passe
                    </h3>
                    
                    <form method="POST" action="/api/agent/changer_mot_de_passe.php">
                        <div class="mb-3">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Mot de passe actuel</label>
                            <div class="relative">
                                <input type="password" name="old_password" class="input-field" placeholder="Entrez votre mot de passe actuel" required>
                                <button type="button" onclick="togglePassword(this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Nouveau mot de passe</label>
                            <div class="relative">
                                <input type="password" name="new_password" class="input-field" placeholder="Minimum 6 caractères" required>
                                <button type="button" onclick="togglePassword(this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Confirmer le mot de passe</label>
                            <div class="relative">
                                <input type="password" name="confirm_password" class="input-field" placeholder="Confirmez votre mot de passe" required>
                                <button type="button" onclick="togglePassword(this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <button type="submit" class="btn-secondary w-full py-2.5 rounded-lg font-semibold">
                            <i class="fas fa-save mr-2"></i> Modifier le mot de passe
                        </button>
                    </form>
                </div>
            </div>

            <!-- Statistiques de l'agent -->
            <div class="card-snel mt-6">
                <h3 class="font-bold text-primary mb-4">
                    <i class="fas fa-chart-bar text-secondary mr-2"></i>Mes statistiques
                </h3>
                <?php
                $db = Database::getInstance()->getConnection();
                
                // Nombre de relevés effectués
                $stmt = $db->prepare("SELECT COUNT(*) as total FROM Consommation WHERE idAgentReleve = ?");
                $stmt->execute([$idUtilisateur]);
                $totalReleves = $stmt->fetch()['total'] ?? 0;
                
                // Relevés du mois
                $stmt = $db->prepare("SELECT COUNT(*) as total FROM Consommation WHERE idAgentReleve = ? AND MONTH(dateFin) = ? AND YEAR(dateFin) = ?");
                $stmt->execute([$idUtilisateur, date('m'), date('Y')]);
                $relevesMois = $stmt->fetch()['total'] ?? 0;
                
                // Dernier relevé
                $stmt = $db->prepare("SELECT dateFin FROM Consommation WHERE idAgentReleve = ? ORDER BY dateFin DESC LIMIT 1");
                $stmt->execute([$idUtilisateur]);
                $dernierReleve = $stmt->fetch();
                ?>
                <div class="grid grid-cols-3 gap-4">
                    <div class="bg-gray-50 rounded-lg p-4 text-center">
                        <p class="text-2xl font-bold text-primary"><?= number_format($totalReleves) ?></p>
                        <p class="text-xs text-gray-500">Total relevés</p>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-4 text-center">
                        <p class="text-2xl font-bold text-secondary"><?= number_format($relevesMois) ?></p>
                        <p class="text-xs text-gray-500">Ce mois</p>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-4 text-center">
                        <p class="text-2xl font-bold text-green-600"><?= $dernierReleve ? date('d/m/Y', strtotime($dernierReleve['dateFin'])) : '-' ?></p>
                        <p class="text-xs text-gray-500">Dernier relevé</p>
                    </div>
                </div>
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
    
    function togglePassword(button) {
        const input = button.parentElement.querySelector('input');
        const icon = button.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'fas fa-eye-slash';
        } else {
            input.type = 'password';
            icon.className = 'fas fa-eye';
        }
    }
</script>

</body>
</html>