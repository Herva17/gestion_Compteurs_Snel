<?php
// Pages/Client/Factures/index.php
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
$stmt = $db->prepare("SELECT * FROM Facture WHERE idClient = ? ORDER BY dateEmission DESC");
$stmt->execute([$idClient]);
$factures = $stmt->fetchAll();

$facturesImpayees = array_filter($factures, function($f) {
    return in_array($f['statut'], ['en_attente', 'en_retard']);
});
$totalImpaye = array_sum(array_column($facturesImpayees, 'montantTotal'));

$page_title = 'Mes factures - SNEL';
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
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.65rem; font-weight: 600; text-transform: uppercase; }
        .status-payee { background: #dcfce7; color: #166534; }
        .status-attente { background: #fef3c7; color: #92400e; }
        .status-retard { background: #fecaca; color: #991b1b; }
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
            <h1 class="text-xl font-bold text-primary">Mes factures</h1>
        </div>
        <div class="text-sm">
            <?php if (count($facturesImpayees) > 0): ?>
                <span class="text-red-500 font-semibold">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    <?= count($facturesImpayees) ?> impayée(s) - <?= number_format($totalImpaye, 0, ',', ' ') ?> FCFA
                </span>
            <?php else: ?>
                <span class="text-green-600"><i class="fas fa-check-circle mr-1"></i> Tout est à jour</span>
            <?php endif; ?>
        </div>
    </div>
</header>

<div class="max-w-7xl mx-auto px-4 py-6">
    <?php if (!empty($factures)): ?>
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">N° Facture</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Montant</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date limite</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Statut</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($factures as $facture): ?>
                            <?php 
                                $isEnRetard = strtotime($facture['dateLimitePaiement']) < time() && $facture['statut'] !== 'payee';
                                $statutClass = $facture['statut'] === 'payee' ? 'status-payee' : ($isEnRetard ? 'status-retard' : 'status-attente');
                                $statutLabel = $facture['statut'] === 'payee' ? 'Payée' : ($isEnRetard ? 'En retard' : 'En attente');
                            ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm font-medium text-primary">
                                    <?= htmlspecialchars($facture['numeroFacture']) ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    <?= date('d/m/Y', strtotime($facture['dateEmission'])) ?>
                                </td>
                                <td class="px-4 py-3 text-sm font-bold <?= $isEnRetard ? 'text-red-600' : 'text-primary' ?>">
                                    <?= number_format($facture['montantTotal'], 0, ',', ' ') ?> FCFA
                                </td>
                                <td class="px-4 py-3 text-sm <?= $isEnRetard ? 'text-red-500' : 'text-gray-600' ?>">
                                    <?= date('d/m/Y', strtotime($facture['dateLimitePaiement'])) ?>
                                    <?php if ($isEnRetard): ?>
                                        <span class="ml-2 text-red-500 text-xs">(Retard)</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="status-badge <?= $statutClass ?>"><?= $statutLabel ?></span>
                                </td>
                                <td class="px-4 py-3">
                                    <?php if ($facture['statut'] !== 'payee'): ?>
                                        <a href="payer.php?id=<?= $facture['idFacture'] ?>" 
                                           class="btn-secondary text-xs px-3 py-1 rounded-lg inline-flex items-center">
                                            <i class="fas fa-credit-card mr-1"></i> Payer
                                        </a>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400">Payée</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
        <div class="bg-white rounded-xl shadow-sm p-12 text-center">
            <div class="text-6xl text-gray-300 mb-4"><i class="fas fa-file-invoice"></i></div>
            <h3 class="text-xl font-semibold text-gray-700 mb-2">Aucune facture</h3>
            <p class="text-gray-500">Vous n'avez pas encore de factures.</p>
        </div>
    <?php endif; ?>
</div>

</body>
</html>