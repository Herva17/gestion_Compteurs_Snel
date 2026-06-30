<?php
// Pages/Admin/Tarifs/index.php
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

// Récupérer tous les tarifs
$stmt = $db->query("SELECT * FROM Tarifs ORDER BY type, trancheMin");
$tarifs = $stmt->fetchAll();

$message = '';
$messageType = '';

// Ajout ou modification d'un tarif
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $idTarif = $_POST['id_tarif'] ?? 0;
    
    if ($action === 'add' || $action === 'edit') {
        $categorie = $_POST['categorie'] ?? '';
        $trancheMin = $_POST['tranche_min'] ?? 0;
        $trancheMax = $_POST['tranche_max'] ?? null;
        $prixUnitaire = $_POST['prix_unitaire'] ?? 0;
        $type = $_POST['type'] ?? 'residentiel';
        $dateDebutValidite = $_POST['date_debut_validite'] ?? date('Y-m-d');
        $dateFinValidite = $_POST['date_fin_validite'] ?? null;
        $estActif = isset($_POST['est_actif']) ? 1 : 0;
        $description = $_POST['description'] ?? '';
        
        if (empty($categorie) || empty($prixUnitaire) || empty($dateDebutValidite)) {
            $message = '❌ Veuillez remplir tous les champs obligatoires.';
            $messageType = 'error';
        } else {
            try {
                if ($action === 'add') {
                    $sql = "INSERT INTO Tarifs (categorie, trancheMin, trancheMax, prixUnitaire, type, dateDebutValidite, dateFinValidite, estActif, description)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([$categorie, $trancheMin, $trancheMax, $prixUnitaire, $type, $dateDebutValidite, $dateFinValidite, $estActif, $description]);
                    $message = '✅ Tarif ajouté avec succès !';
                } else {
                    $sql = "UPDATE Tarifs SET categorie=?, trancheMin=?, trancheMax=?, prixUnitaire=?, type=?, dateDebutValidite=?, dateFinValidite=?, estActif=?, description=?
                            WHERE idTarif=?";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([$categorie, $trancheMin, $trancheMax, $prixUnitaire, $type, $dateDebutValidite, $dateFinValidite, $estActif, $description, $idTarif]);
                    $message = '✅ Tarif modifié avec succès !';
                }
                $messageType = 'success';
                header('refresh:1;url=index.php');
            } catch (Exception $e) {
                $message = '❌ Erreur: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    } elseif ($action === 'toggle') {
        $idTarif = $_POST['id_tarif'] ?? 0;
        $estActif = $_POST['est_actif'] ?? 0;
        try {
            $stmt = $db->prepare("UPDATE Tarifs SET estActif = ? WHERE idTarif = ?");
            $stmt->execute([$estActif ? 0 : 1, $idTarif]);
            $message = '✅ Statut du tarif modifié.';
            $messageType = 'success';
            header('refresh:1;url=index.php');
        } catch (Exception $e) {
            $message = '❌ Erreur: ' . $e->getMessage();
            $messageType = 'error';
        }
    } elseif ($action === 'delete') {
        $idTarif = $_POST['id_tarif'] ?? 0;
        try {
            $stmt = $db->prepare("DELETE FROM Tarifs WHERE idTarif = ?");
            $stmt->execute([$idTarif]);
            $message = '✅ Tarif supprimé avec succès.';
            $messageType = 'success';
            header('refresh:1;url=index.php');
        } catch (Exception $e) {
            $message = '❌ Erreur: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Notifications
$notification = new Notification();
$totalNotifs = $notification->countNonLuesUtilisateur($idUtilisateur);

$page_title = 'Gestion des tarifs - SNEL Admin';
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
        .status-actif { background: #dcfce7; color: #166534; }
        .status-inactif { background: #fef3c7; color: #92400e; }
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
        .input-field { width: 100%; padding: 0.6rem 1rem; border: 2px solid #e2e8f0; border-radius: 0.75rem; transition: all 0.3s ease; background: white; font-size: 0.9rem; }
        .input-field:focus { border-color: var(--secondary); outline: none; box-shadow: 0 0 0 4px rgba(192,86,33,0.1); }
        .modal-overlay { background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); }
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
            <a href="../Compteurs/index.php" class="nav-item">
                <i class="fas fa-gauge-high w-5 text-center"></i> Compteurs
            </a>
            <a href="index.php" class="nav-item active">
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
            <a href="../../logout.php" class="nav-item text-red-400 hover:text-red-300 hover:bg-red-500/10">
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
                    <h1 class="text-lg font-bold text-primary hidden sm:block">Gestion des tarifs</h1>
                    <p class="text-xs text-gray-400 hidden sm:block">Grilles tarifaires</p>
                </div>
            </div>
            
            <div class="flex items-center space-x-4">
                <button onclick="openModal('add')" class="btn-success px-4 py-2 rounded-lg font-semibold text-sm flex items-center">
                    <i class="fas fa-plus mr-2"></i> Nouveau tarif
                </button>
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
        
        <?php if ($message): ?>
            <div class="<?= $messageType === 'success' ? 'bg-green-50 border-green-200 text-green-700' : 'bg-red-50 border-red-200 text-red-700' ?> border rounded-xl px-4 py-3 mb-4">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <!-- Liste des tarifs -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Catégorie</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tranche</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Prix (FCFA)</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Validité</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Statut</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if (!empty($tarifs)): ?>
                            <?php foreach ($tarifs as $tarif): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm font-medium text-primary">
                                        <?= htmlspecialchars($tarif['categorie']) ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        <?= number_format($tarif['trancheMin'], 0, ',', ' ') ?> - 
                                        <?= $tarif['trancheMax'] ? number_format($tarif['trancheMax'], 0, ',', ' ') : '∞' ?> kWh
                                    </td>
                                    <td class="px-4 py-3 text-sm font-bold text-secondary">
                                        <?= number_format($tarif['prixUnitaire'], 0, ',', ' ') ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <span class="status-badge <?= $tarif['type'] === 'residentiel' ? 'status-actif' : 'status-inactif' ?>">
                                            <?= ucfirst($tarif['type']) ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        <?= date('d/m/Y', strtotime($tarif['dateDebutValidite'])) ?>
                                        <?php if ($tarif['dateFinValidite']): ?>
                                            <br><span class="text-xs text-gray-400">Jusqu'au <?= date('d/m/Y', strtotime($tarif['dateFinValidite'])) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="status-badge <?= $tarif['estActif'] ? 'status-actif' : 'status-inactif' ?>">
                                            <?= $tarif['estActif'] ? 'Actif' : 'Inactif' ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <button onclick="editTarif(<?= htmlspecialchars(json_encode($tarif)) ?>)" 
                                                class="btn-secondary text-xs px-2 py-1 rounded-lg inline-flex items-center">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="id_tarif" value="<?= $tarif['idTarif'] ?>">
                                            <input type="hidden" name="est_actif" value="<?= $tarif['estActif'] ?>">
                                            <button type="submit" class="btn-primary text-xs px-2 py-1 rounded-lg inline-flex items-center">
                                                <i class="fas <?= $tarif['estActif'] ? 'fa-pause' : 'fa-play' ?>"></i>
                                            </button>
                                        </form>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer ce tarif ?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id_tarif" value="<?= $tarif['idTarif'] ?>">
                                            <button type="submit" class="btn-danger text-xs px-2 py-1 rounded-lg inline-flex items-center">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                    Aucun tarif enregistré.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal Ajout/Modification -->
        <div id="modal-tarif" class="fixed inset-0 modal-overlay hidden items-center justify-center z-50">
            <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full mx-4 p-6 max-h-[90vh] overflow-y-auto">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-bold text-primary" id="modal-title">
                        <i class="fas fa-plus-circle text-secondary mr-2"></i>Ajouter un tarif
                    </h3>
                    <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 text-2xl">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form method="POST" action="">
                    <input type="hidden" name="action" id="form-action" value="add">
                    <input type="hidden" name="id_tarif" id="form-id" value="0">
                    
                    <div class="grid md:grid-cols-2 gap-4">
                        <div class="mb-3">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Catégorie <span class="text-red-500">*</span></label>
                            <input type="text" name="categorie" id="categorie" class="input-field" placeholder="Ex: Résidentiel" required>
                        </div>
                        <div class="mb-3">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Type <span class="text-red-500">*</span></label>
                            <select name="type" id="type" class="input-field" required>
                                <option value="residentiel">Résidentiel</option>
                                <option value="commercial">Commercial</option>
                                <option value="industriel">Industriel</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid md:grid-cols-2 gap-4">
                        <div class="mb-3">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Tranche min (kWh) <span class="text-red-500">*</span></label>
                            <input type="number" name="tranche_min" id="tranche_min" class="input-field" min="0" step="1" required>
                        </div>
                        <div class="mb-3">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Tranche max (kWh)</label>
                            <input type="number" name="tranche_max" id="tranche_max" class="input-field" min="0" step="1" placeholder="Laisser vide pour infini">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Prix unitaire (FCFA) <span class="text-red-500">*</span></label>
                        <input type="number" name="prix_unitaire" id="prix_unitaire" class="input-field" min="0" step="1" required>
                    </div>

                    <div class="grid md:grid-cols-2 gap-4">
                        <div class="mb-3">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Date début validité <span class="text-red-500">*</span></label>
                            <input type="date" name="date_debut_validite" id="date_debut_validite" class="input-field" required>
                        </div>
                        <div class="mb-3">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Date fin validité</label>
                            <input type="date" name="date_fin_validite" id="date_fin_validite" class="input-field">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Description</label>
                        <textarea name="description" id="description" class="input-field" rows="2" placeholder="Description du tarif..."></textarea>
                    </div>

                    <div class="mb-3 flex items-center">
                        <input type="checkbox" name="est_actif" id="est_actif" class="w-4 h-4 text-secondary border-gray-300 rounded focus:ring-secondary" checked>
                        <label for="est_actif" class="ml-2 text-sm text-gray-700">Actif</label>
                    </div>

                    <button type="submit" class="btn-success w-full py-2.5 rounded-lg font-semibold">
                        <i class="fas fa-save mr-2"></i> <span id="btn-text">Ajouter</span>
                    </button>
                </form>
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

    function openModal(type, data) {
        const modal = document.getElementById('modal-tarif');
        const title = document.getElementById('modal-title');
        const action = document.getElementById('form-action');
        const btnText = document.getElementById('btn-text');
        
        if (type === 'add') {
            title.innerHTML = '<i class="fas fa-plus-circle text-secondary mr-2"></i>Ajouter un tarif';
            action.value = 'add';
            btnText.textContent = 'Ajouter';
            document.getElementById('categorie').value = '';
            document.getElementById('type').value = 'residentiel';
            document.getElementById('tranche_min').value = '';
            document.getElementById('tranche_max').value = '';
            document.getElementById('prix_unitaire').value = '';
            document.getElementById('date_debut_validite').value = '<?= date("Y-m-d") ?>';
            document.getElementById('date_fin_validite').value = '';
            document.getElementById('description').value = '';
            document.getElementById('est_actif').checked = true;
            document.getElementById('form-id').value = '0';
        }
        
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        const modal = document.getElementById('modal-tarif');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = 'auto';
    }

    function editTarif(data) {
        const modal = document.getElementById('modal-tarif');
        const title = document.getElementById('modal-title');
        const action = document.getElementById('form-action');
        const btnText = document.getElementById('btn-text');
        
        title.innerHTML = '<i class="fas fa-edit text-secondary mr-2"></i>Modifier le tarif';
        action.value = 'edit';
        btnText.textContent = 'Modifier';
        document.getElementById('form-id').value = data.idTarif;
        document.getElementById('categorie').value = data.categorie;
        document.getElementById('type').value = data.type;
        document.getElementById('tranche_min').value = data.trancheMin;
        document.getElementById('tranche_max').value = data.trancheMax || '';
        document.getElementById('prix_unitaire').value = data.prixUnitaire;
        document.getElementById('date_debut_validite').value = data.dateDebutValidite;
        document.getElementById('date_fin_validite').value = data.dateFinValidite || '';
        document.getElementById('description').value = data.description || '';
        document.getElementById('est_actif').checked = data.estActif == 1;
        
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
    }

    // Fermer modal en cliquant à l'extérieur
    document.getElementById('modal-tarif').addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });
</script>

</body>
</html>