<?php
// Pages/Client/Compteurs/index.php
session_start();
require_once __DIR__ . '/../../../Classes/Database.php';
require_once __DIR__ . '/../../../Classes/Client.php';
require_once __DIR__ . '/../../../Classes/Compteur.php';

$client = new Client();
if (!$client->isLoggedIn()) {
    header('Location: /login.php');
    exit;
}

$user = $client->getLoggedInUser();
if (!$user) {
    session_destroy();
    header('Location: /login.php?error=session_expired');
    exit;
}

$compteurs = $user->getCompteurs();
$totalCompteurs = count($compteurs);

$page_title = 'Mes compteurs - SNEL';
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
        .btn-primary:hover { background: #0f2440; transform: translateY(-2px); box-shadow: 0 10px 30px rgba(26,54,93,0.25); }
        .btn-secondary { background: var(--secondary); color: white; transition: all 0.3s ease; }
        .btn-secondary:hover { background: #a0441a; transform: translateY(-2px); box-shadow: 0 10px 30px rgba(192,86,33,0.25); }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.65rem; font-weight: 600; text-transform: uppercase; }
        .status-actif { background: #dcfce7; color: #166534; }
        .status-inactif { background: #fef3c7; color: #92400e; }
        .status-panne { background: #fecaca; color: #991b1b; }
        .card-snel { background: white; border-radius: 1rem; padding: 1.25rem; border: 1px solid #e2e8f0; transition: all 0.3s ease; }
        .card-snel:hover { transform: translateY(-4px); box-shadow: 0 8px 30px rgba(0,0,0,0.08); border-color: var(--secondary); }
        .topbar { background: white; border-bottom: 1px solid #e2e8f0; padding: 0.75rem 2rem; position: sticky; top: 0; z-index: 50; }
        @media (max-width: 768px) { .topbar { padding: 0.75rem 1rem; } }
    </style>
</head>
<body class="bg-gray-50">

<!-- Topbar -->
<header class="topbar">
    <div class="flex items-center justify-between">
        <div class="flex items-center space-x-4">
            <a href="../dashboard.php" class="text-primary hover:text-secondary transition">
                <i class="fas fa-arrow-left"></i>
            </a>
            <h1 class="text-xl font-bold text-primary">Mes compteurs</h1>
        </div>
        <span class="text-sm text-gray-500"><?= $totalCompteurs ?> compteur(s)</span>
    </div>
</header>

<div class="max-w-7xl mx-auto px-4 py-6">
    <?php if ($totalCompteurs > 0): ?>
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($compteurs as $compteur): ?>
                <div class="card-snel">
                    <div class="flex justify-between items-start mb-3">
                        <div>
                            <h3 class="font-bold text-primary"><?= htmlspecialchars($compteur['NumeroSerie']) ?></h3>
                            <p class="text-xs text-gray-500">
                                <i class="fas fa-calendar mr-1"></i>
                                <?= date('d/m/Y', strtotime($compteur['DateInstallation'])) ?>
                            </p>
                        </div>
                        <span class="status-badge status-<?= $compteur['etat'] === 'actif' ? 'actif' : ($compteur['etat'] === 'inactif' ? 'inactif' : 'panne') ?>">
                            <?= ucfirst($compteur['etat']) ?>
                        </span>
                    </div>
                    <div class="grid grid-cols-2 gap-3 mb-3">
                        <div class="bg-gray-50 rounded-lg p-3 text-center">
                            <p class="text-xs text-gray-500">Index actuel</p>
                            <p class="font-bold text-primary"><?= number_format($compteur['indexActuel'], 0, ',', ' ') ?> kWh</p>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-3 text-center">
                            <p class="text-xs text-gray-500">Type</p>
                            <p class="font-medium text-sm"><?= ucfirst($compteur['typeCompteur'] ?? 'Monophase') ?></p>
                        </div>
                    </div>
                    <a href="details.php?id=<?= $compteur['idCompteur'] ?>" 
                       class="btn-primary w-full py-2 rounded-lg text-sm font-semibold flex items-center justify-center">
                        <i class="fas fa-eye mr-2"></i> Voir les détails
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="bg-white rounded-xl shadow-sm p-12 text-center">
            <div class="text-6xl text-gray-300 mb-4"><i class="fas fa-gauge-high"></i></div>
            <h3 class="text-xl font-semibold text-gray-700 mb-2">Aucun compteur</h3>
            <p class="text-gray-500">Vous n'avez pas encore de compteur enregistré.</p>
        </div>
    <?php endif; ?>
</div>

</body>
</html>