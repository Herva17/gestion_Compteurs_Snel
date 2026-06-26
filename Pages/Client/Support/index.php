<?php
// Pages/Client/Support/index.php
session_start();
require_once __DIR__ . '/../../../Classes/Database.php';
require_once __DIR__ . '/../../../Classes/Client.php';

$client = new Client();
if (!$client->isLoggedIn()) {
    header('Location: /login.php');
    exit;
}

$page_title = 'Centre d\'aide - SNEL';
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
        .card-snel { background: white; border-radius: 1rem; padding: 1.5rem; border: 1px solid #e2e8f0; transition: all 0.3s ease; }
        .card-snel:hover { transform: translateY(-4px); box-shadow: 0 8px 30px rgba(0,0,0,0.06); border-color: var(--secondary); }
        .topbar { background: white; border-bottom: 1px solid #e2e8f0; padding: 0.75rem 2rem; position: sticky; top: 0; z-index: 50; }
    </style>
</head>
<body class="bg-gray-50">

<header class="topbar">
    <div class="flex items-center space-x-4">
        <a href="../dashboard.php" class="text-primary hover:text-secondary transition">
            <i class="fas fa-arrow-left"></i> Retour
        </a>
        <h1 class="text-xl font-bold text-primary">Centre d'aide</h1>
    </div>
</header>

<div class="max-w-6xl mx-auto px-4 py-6">
    <!-- FAQ -->
    <div class="grid md:grid-cols-2 gap-6 mb-6">
        <div class="card-snel">
            <div class="flex items-center gap-4 mb-3">
                <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center">
                    <i class="fas fa-gauge-high text-blue-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="font-bold text-primary">Consommation</h3>
                    <p class="text-sm text-gray-500">Comprendre votre consommation</p>
                </div>
            </div>
            <ul class="space-y-2 text-sm text-gray-600">
                <li><a href="#" class="hover:text-secondary transition">Comment lire mon compteur ?</a></li>
                <li><a href="#" class="hover:text-secondary transition">Pourquoi ma consommation a-t-elle augmenté ?</a></li>
                <li><a href="#" class="hover:text-secondary transition">Que faire en cas de consommation anormale ?</a></li>
            </ul>
        </div>

        <div class="card-snel">
            <div class="flex items-center gap-4 mb-3">
                <div class="w-12 h-12 bg-green-50 rounded-xl flex items-center justify-center">
                    <i class="fas fa-file-invoice text-green-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="font-bold text-primary">Facturation</h3>
                    <p class="text-sm text-gray-500">Comprendre vos factures</p>
                </div>
            </div>
            <ul class="space-y-2 text-sm text-gray-600">
                <li><a href="#" class="hover:text-secondary transition">Comment est calculée ma facture ?</a></li>
                <li><a href="#" class="hover:text-secondary transition">Que faire en cas d'erreur de facturation ?</a></li>
                <li><a href="#" class="hover:text-secondary transition">Comment contester une facture ?</a></li>
            </ul>
        </div>

        <div class="card-snel">
            <div class="flex items-center gap-4 mb-3">
                <div class="w-12 h-12 bg-yellow-50 rounded-xl flex items-center justify-center">
                    <i class="fas fa-credit-card text-yellow-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="font-bold text-primary">Paiement</h3>
                    <p class="text-sm text-gray-500">Payer vos factures</p>
                </div>
            </div>
            <ul class="space-y-2 text-sm text-gray-600">
                <li><a href="#" class="hover:text-secondary transition">Comment payer ma facture en ligne ?</a></li>
                <li><a href="#" class="hover:text-secondary transition">Paiement par Mobile Money</a></li>
                <li><a href="#" class="hover:text-secondary transition">Où payer en espèces ?</a></li>
            </ul>
        </div>

        <div class="card-snel">
            <div class="flex items-center gap-4 mb-3">
                <div class="w-12 h-12 bg-red-50 rounded-xl flex items-center justify-center">
                    <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="font-bold text-primary">Assistance</h3>
                    <p class="text-sm text-gray-500">Besoin d'aide ?</p>
                </div>
            </div>
            <ul class="space-y-2 text-sm text-gray-600">
                <li><a href="#" class="hover:text-secondary transition">Signaler une panne</a></li>
                <li><a href="#" class="hover:text-secondary transition">Contacter le service client</a></li>
                <li><a href="#" class="hover:text-secondary transition">Demander un contrôle</a></li>
            </ul>
        </div>
    </div>

    <!-- Contact rapide -->
    <div class="bg-primary rounded-xl p-6 text-white text-center">
        <h3 class="text-xl font-bold mb-2">Besoin d'aide supplémentaire ?</h3>
        <p class="text-blue-200 mb-4">Notre équipe est à votre disposition 24/7</p>
        <div class="flex flex-wrap justify-center gap-4">
            <a href="tel:+243123456789" class="bg-white/20 hover:bg-white/30 px-6 py-2 rounded-lg transition inline-flex items-center">
                <i class="fas fa-phone mr-2"></i> Appeler
            </a>
            <a href="mailto:support@snel.cd" class="bg-white/20 hover:bg-white/30 px-6 py-2 rounded-lg transition inline-flex items-center">
                <i class="fas fa-envelope mr-2"></i> Email
            </a>
            <a href="signaler.php" class="bg-secondary hover:bg-secondary/80 px-6 py-2 rounded-lg transition inline-flex items-center">
                <i class="fas fa-flag mr-2"></i> Signaler une anomalie
            </a>
        </div>
    </div>
</div>

</body>
</html>