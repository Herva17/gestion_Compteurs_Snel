<?php
// Pages/Client/Notifications/index.php
session_start();
require_once __DIR__ . '/../../../Classes/Database.php';
require_once __DIR__ . '/../../../Classes/Client.php';
require_once __DIR__ . '/../../../Classes/Notification.php';

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

$notification = new Notification();
$notifications = $notification->getByClient($idClient);

$page_title = 'Mes notifications - SNEL';
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
        .notif-item { padding: 1rem; border-bottom: 1px solid #f1f5f9; transition: all 0.2s ease; }
        .notif-item:hover { background: #f8fafc; }
        .notif-item.unread { background: #fffbeb; border-left: 4px solid var(--secondary); }
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
            <h1 class="text-xl font-bold text-primary">Mes notifications</h1>
        </div>
        <div class="flex items-center gap-3">
            <span class="text-sm text-gray-500"><?= count($notifications) ?> notification(s)</span>
            <?php if (count($notifications) > 0): ?>
                <button onclick="marquerToutLu()" class="btn-secondary text-xs px-3 py-1 rounded-lg">
                    <i class="fas fa-check-double mr-1"></i> Tout lire
                </button>
            <?php endif; ?>
        </div>
    </div>
</header>

<div class="max-w-4xl mx-auto px-4 py-6">
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <?php if (!empty($notifications)): ?>
            <?php foreach ($notifications as $notif): ?>
                <div class="notif-item <?= $notif['estLue'] ? '' : 'unread' ?>" onclick="marquerLu(<?= $notif['idNotification'] ?>)">
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 rounded-full <?= $notif['type'] === 'alerte' ? 'bg-red-100' : ($notif['type'] === 'urgent' ? 'bg-orange-100' : 'bg-blue-100') ?> flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-<?= $notif['type'] === 'alerte' ? 'exclamation-triangle' : ($notif['type'] === 'urgent' ? 'circle-exclamation' : 'info-circle') ?> 
                                <?= $notif['type'] === 'alerte' ? 'text-red-500' : ($notif['type'] === 'urgent' ? 'text-orange-500' : 'text-blue-500') ?>">
                            </i>
                        </div>
                        <div class="flex-1">
                            <div class="flex items-center justify-between">
                                <p class="font-semibold text-gray-800"><?= htmlspecialchars($notif['titre']) ?></p>
                                <span class="text-xs text-gray-400"><?= date('d/m/Y H:i', strtotime($notif['dateCreation'])) ?></span>
                            </div>
                            <p class="text-sm text-gray-600 mt-1"><?= htmlspecialchars($notif['message']) ?></p>
                            <?php if (!$notif['estLue']): ?>
                                <span class="text-xs text-secondary font-semibold">● Non lu</span>
                            <?php endif; ?>
                            <?php if ($notif['lien']): ?>
                                <a href="<?= htmlspecialchars($notif['lien']) ?>" class="text-xs text-primary hover:underline block mt-1">
                                    <i class="fas fa-arrow-right mr-1"></i> Voir
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="p-12 text-center">
                <div class="text-6xl text-gray-300 mb-4">🔔</div>
                <h3 class="text-xl font-semibold text-gray-700 mb-2">Aucune notification</h3>
                <p class="text-gray-500">Vous n'avez pas encore de notifications.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function marquerLu(id) {
    fetch('/api/notifications/marquer_lu.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
    }).then(() => location.reload());
}

function marquerToutLu() {
    fetch('/api/notifications/marquer_tout_lu.php', {
        method: 'POST'
    }).then(() => location.reload());
}
</script>

</body>
</html>