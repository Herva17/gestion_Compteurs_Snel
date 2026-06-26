<?php
// Pages/Client/Paiements/index.php
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

$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("
    SELECT p.*, f.numeroFacture 
    FROM Paiement p
    JOIN Facture f ON p.idFacture = f.idFacture
    WHERE p.idClient = ?
    ORDER BY p.datePaiement DESC
");
$stmt->execute([$idClient]);
$paiements = $stmt->fetchAll();

$totalPaye = array_sum(array_column($paiements, 'montant'));

$page_title = 'Mes paiements - SNEL';
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
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.65rem; font-weight: 600; text-transform: uppercase; }
        .status-payee { background: #dcfce7; color: #166534; }
        .card-snel { background: white; border-radius: 1rem; padding: 1.25rem; border: 1px solid #e2e8f0; }
        .topbar { background: white; border-bottom: 1px solid #e2e8f0; padding: 0.75rem 2rem; position: sticky; top: 0; z-index: 50; }
    </style>
</head>
<body class="bg-gray-50">

<header class="topbar">
    <div class="flex items-center justify-between">
        <div class="flex items-center space-x-4">
            <a href="../dashboard.php" class="text-primary hover:text-secondary transition">
                <i class="fas fa-arrow-left"></i>
            </a>
            <h1 class="text-xl font-bold text-primary">Mes paiements</h1>
        </div>
        <span class="text-sm text-gray-500">Total: <?= number_format($totalPaye, 0, ',', ' ') ?> FCFA</span>
    </div>
</header>

<div class="max-w-7xl mx-auto px-4 py-6">
    <!-- Stats -->
    <div class="grid md:grid-cols-3 gap-4 mb-6">
        <div class="card-snel">
            <p class="text-xs text-gray-500">Total payé</p>
            <p class="text-2xl font-bold text-primary"><?= number_format($totalPaye, 0, ',', ' ') ?> FCFA</p>
        </div>
        <div class="card-snel">
            <p class="text-xs text-gray-500">Nombre de paiements</p>
            <p class="text-2xl font-bold text-primary"><?= count($paiements) ?></p>
        </div>
        <div class="card-snel">
            <p class="text-xs text-gray-500">Dernier paiement</p>
            <p class="text-2xl font-bold text-secondary"><?= !empty($paiements) ? date('d/m/Y', strtotime($paiements[0]['datePaiement'])) : '-' ?></p>
        </div>
    </div>

    <?php if (!empty($paiements)): ?>
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Référence</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Facture</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Montant</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Mode</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Statut</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($paiements as $paiement): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm font-mono font-medium">
                                    <?= htmlspecialchars($paiement['numeroReference']) ?>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <?= htmlspecialchars($paiement['numeroFacture']) ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    <?= date('d/m/Y', strtotime($paiement['datePaiement'])) ?>
                                </td>
                                <td class="px-4 py-3 text-sm font-bold text-primary">
                                    <?= number_format($paiement['montant'], 0, ',', ' ') ?> FCFA
                                </td>
                                <td class="px-4 py-3 text-sm capitalize">
                                    <?= str_replace('_', ' ', htmlspecialchars($paiement['modePaiement'])) ?>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="status-badge status-payee"><?= ucfirst($paiement['statut']) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
        <div class="bg-white rounded-xl shadow-sm p-12 text-center">
            <div class="text-6xl text-gray-300 mb-4"><i class="fas fa-credit-card"></i></div>
            <h3 class="text-xl font-semibold text-gray-700 mb-2">Aucun paiement</h3>
            <p class="text-gray-500">Vous n'avez pas encore effectué de paiement.</p>
        </div>
    <?php endif; ?>
</div>

</body>
</html>