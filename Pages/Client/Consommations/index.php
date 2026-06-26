<?php
// Pages/Client/Consommations/index.php
session_start();
require_once __DIR__ . '/../../../Classes/Database.php';
require_once __DIR__ . '/../../../Classes/Client.php';

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

$idClient = $user->getIdClient();

// Récupérer toutes les consommations du client
$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("
    SELECT c.*, cp.numeroSerie 
    FROM Consommation c
    JOIN Compteur cp ON c.idCompteur = cp.idCompteur
    WHERE cp.idClient = ?
    ORDER BY c.dateFin DESC
");
$stmt->execute([$idClient]);
$consommations = $stmt->fetchAll();

$totalConso = array_sum(array_column($consommations, 'quantiteCons'));

$page_title = 'Mes consommations - SNEL';
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { font-family: 'Inter', sans-serif; }
        :root { --primary: #1a365d; --secondary: #c05621; --accent: #ecc94b; }
        .bg-primary { background: var(--primary); }
        .bg-secondary { background: var(--secondary); }
        .text-primary { color: var(--primary); }
        .text-secondary { color: var(--secondary); }
        .btn-primary { background: var(--primary); color: white; transition: all 0.3s ease; }
        .btn-primary:hover { background: #0f2440; transform: translateY(-2px); }
        .card-snel { background: white; border-radius: 1rem; padding: 1.25rem; border: 1px solid #e2e8f0; }
        .topbar { background: white; border-bottom: 1px solid #e2e8f0; padding: 0.75rem 2rem; position: sticky; top: 0; z-index: 50; }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.65rem; font-weight: 600; text-transform: uppercase; }
    </style>
</head>
<body class="bg-gray-50">

<header class="topbar">
    <div class="flex items-center justify-between">
        <div class="flex items-center space-x-4">
            <a href="../dashboard.php" class="text-primary hover:text-secondary transition">
                <i class="fas fa-arrow-left"></i>
            </a>
            <h1 class="text-xl font-bold text-primary">Mes consommations</h1>
        </div>
        <span class="text-sm text-gray-500">Total: <?= number_format($totalConso, 2, ',', ' ') ?> kWh</span>
    </div>
</header>

<div class="max-w-7xl mx-auto px-4 py-6">
    <!-- Statistiques -->
    <div class="grid md:grid-cols-4 gap-4 mb-6">
        <div class="card-snel">
            <p class="text-xs text-gray-500">Total consommé</p>
            <p class="text-2xl font-bold text-primary"><?= number_format($totalConso, 2, ',', ' ') ?> kWh</p>
        </div>
        <div class="card-snel">
            <p class="text-xs text-gray-500">Nombre de relevés</p>
            <p class="text-2xl font-bold text-primary"><?= count($consommations) ?></p>
        </div>
        <div class="card-snel">
            <p class="text-xs text-gray-500">Moyenne par relevé</p>
            <p class="text-2xl font-bold text-secondary"><?= count($consommations) > 0 ? number_format($totalConso / count($consommations), 2, ',', ' ') : '0' ?> kWh</p>
        </div>
        <div class="card-snel">
            <p class="text-xs text-gray-500">Dernier relevé</p>
            <p class="text-2xl font-bold text-primary"><?= !empty($consommations) ? date('d/m/Y', strtotime($consommations[0]['dateFin'])) : '-' ?></p>
        </div>
    </div>

    <!-- Graphique -->
    <?php if (!empty($consommations)): ?>
    <div class="card-snel mb-6">
        <h3 class="font-bold text-primary mb-4">
            <i class="fas fa-chart-bar text-secondary mr-2"></i>Évolution de la consommation
        </h3>
        <canvas id="chartConso" height="200"></canvas>
    </div>
    <?php endif; ?>

    <!-- Tableau -->
    <?php if (!empty($consommations)): ?>
    <div class="card-snel">
        <h3 class="font-bold text-primary mb-4">
            <i class="fas fa-list text-secondary mr-2"></i>Historique complet
        </h3>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Compteur</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Période</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Index</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Consommation</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Saison</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($consommations as $conso): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2 text-sm font-medium text-primary">
                            <?= htmlspecialchars($conso['numeroSerie']) ?>
                        </td>
                        <td class="px-4 py-2 text-sm text-gray-600">
                            <?= date('d/m/Y', strtotime($conso['dateDebut'])) ?> - <?= date('d/m/Y', strtotime($conso['dateFin'])) ?>
                        </td>
                        <td class="px-4 py-2 text-sm font-mono">
                            <?= number_format($conso['indexAncien'], 0, ',', ' ') ?> → <?= number_format($conso['indexNouveau'], 0, ',', ' ') ?>
                        </td>
                        <td class="px-4 py-2 text-sm font-bold text-primary">
                            <?= number_format($conso['quantiteCons'], 2, ',', ' ') ?> kWh
                        </td>
                        <td class="px-4 py-2 text-sm capitalize"><?= $conso['saison'] ?? '-' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php else: ?>
    <div class="bg-white rounded-xl shadow-sm p-12 text-center">
        <div class="text-6xl text-gray-300 mb-4"><i class="fas fa-chart-line"></i></div>
        <h3 class="text-xl font-semibold text-gray-700 mb-2">Aucune consommation</h3>
        <p class="text-gray-500">Aucune consommation n'a été enregistrée pour vos compteurs.</p>
    </div>
    <?php endif; ?>
</div>

<script>
<?php if (!empty($consommations)): ?>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('chartConso');
    if (!ctx) return;
    
    const data = <?php 
        $labels = array_reverse(array_map(function($c) { return date('d/m/Y', strtotime($c['dateDebut'])); }, $consommations));
        $values = array_reverse(array_map(function($c) { return floatval($c['quantiteCons']); }, $consommations));
        echo json_encode(['labels' => $labels, 'values' => $values]);
    ?>;
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'Consommation (kWh)',
                data: data.values,
                backgroundColor: 'rgba(192,86,33,0.1)',
                borderColor: '#c05621',
                borderWidth: 2.5,
                pointBackgroundColor: '#c05621',
                pointBorderColor: 'white',
                pointBorderWidth: 2,
                pointRadius: 4,
                tension: 0.3,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { callback: v => v + ' kWh' } },
                x: { grid: { display: false }, ticks: { maxRotation: 45, font: { size: 9 } } }
            }
        }
    });
});
<?php endif; ?>
</script>

</body>
</html>