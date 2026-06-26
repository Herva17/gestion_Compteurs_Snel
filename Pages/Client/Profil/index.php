<?php
// Pages/Client/Profil/index.php
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

$page_title = 'Mon profil - SNEL';
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
        .status-actif { background: #dcfce7; color: #166534; }
        .input-field { width: 100%; padding: 0.75rem 1rem; border: 2px solid #e2e8f0; border-radius: 0.75rem; transition: all 0.3s ease; }
        .input-field:focus { border-color: var(--secondary); outline: none; box-shadow: 0 0 0 4px rgba(192,86,33,0.1); }
        .card-snel { background: white; border-radius: 1rem; padding: 1.5rem; border: 1px solid #e2e8f0; }
        .topbar { background: white; border-bottom: 1px solid #e2e8f0; padding: 0.75rem 2rem; position: sticky; top: 0; z-index: 50; }
        .avatar { width: 80px; height: 80px; border-radius: 50%; background: var(--secondary); display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem; font-weight: bold; }
        .info-item { display: flex; justify-content: space-between; padding: 0.75rem; border-bottom: 1px solid #f1f5f9; }
        .info-item:last-child { border-bottom: none; }
        .info-label { font-size: 0.875rem; color: #6b7280; }
        .info-value { font-weight: 500; color: #1a365d; }
    </style>
</head>
<body class="bg-gray-50">

<header class="topbar">
    <div class="flex items-center space-x-4">
        <a href="../dashboard.php" class="text-primary hover:text-secondary transition">
            <i class="fas fa-arrow-left"></i> Retour
        </a>
        <h1 class="text-xl font-bold text-primary">Mon profil</h1>
    </div>
</header>

<div class="max-w-4xl mx-auto px-4 py-6">
    <div class="grid md:grid-cols-2 gap-6">
        <!-- Informations personnelles -->
        <div class="card-snel">
            <div class="flex items-center space-x-4 mb-6">
                <div class="avatar"><?= strtoupper(substr($user->getPrenom(), 0, 1) . substr($user->getNom(), 0, 1)) ?></div>
                <div>
                    <h2 class="text-xl font-bold text-primary"><?= htmlspecialchars($user->getNom() . ' ' . $user->getPrenom()) ?></h2>
                    <span class="status-badge status-actif"><i class="fas fa-check-circle mr-1"></i> Compte actif</span>
                </div>
            </div>

            <div class="space-y-1">
                <div class="info-item">
                    <span class="info-label"><i class="fas fa-envelope text-secondary mr-2"></i>Email</span>
                    <span class="info-value"><?= htmlspecialchars($user->getEmail()) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label"><i class="fas fa-phone text-secondary mr-2"></i>Téléphone</span>
                    <span class="info-value"><?= htmlspecialchars($user->getTelephone()) ?></span>
                </div>
                <?php if ($user->getTelephone2()): ?>
                <div class="info-item">
                    <span class="info-label"><i class="fas fa-phone text-secondary mr-2"></i>Téléphone 2</span>
                    <span class="info-value"><?= htmlspecialchars($user->getTelephone2()) ?></span>
                </div>
                <?php endif; ?>
                <div class="info-item">
                    <span class="info-label"><i class="fas fa-map-marker-alt text-secondary mr-2"></i>Adresse</span>
                    <span class="info-value"><?= htmlspecialchars($user->getAdresse() ?: 'Non renseignée') ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label"><i class="fas fa-city text-secondary mr-2"></i>Ville</span>
                    <span class="info-value"><?= htmlspecialchars($user->getVille() ?: 'Non renseignée') ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label"><i class="fas fa-calendar text-secondary mr-2"></i>Date d'inscription</span>
                    <span class="info-value"><?= date('d/m/Y', strtotime($user->getDateInscription())) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label"><i class="fas fa-venus-mars text-secondary mr-2"></i>Sexe</span>
                    <span class="info-value"><?= $user->getSexe() ?: 'Non renseigné' ?></span>
                </div>
                <?php if ($user->getDateNaissance()): ?>
                <div class="info-item">
                    <span class="info-label"><i class="fas fa-birthday-cake text-secondary mr-2"></i>Date de naissance</span>
                    <span class="info-value"><?= date('d/m/Y', strtotime($user->getDateNaissance())) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Modifier le mot de passe -->
        <div class="card-snel">
            <h3 class="font-bold text-primary mb-4">
                <i class="fas fa-lock text-secondary mr-2"></i>Modifier le mot de passe
            </h3>
            <?php if (isset($_GET['success'])): ?>
                <div class="bg-green-50 border border-green-200 text-green-600 px-4 py-2 rounded-lg mb-4 text-sm">
                    <i class="fas fa-check-circle mr-2"></i> Mot de passe modifié avec succès !
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['error'])): ?>
                <div class="bg-red-50 border border-red-200 text-red-600 px-4 py-2 rounded-lg mb-4 text-sm">
                    <i class="fas fa-exclamation-circle mr-2"></i> <?= htmlspecialchars($_GET['error']) ?>
                </div>
            <?php endif; ?>
            <form method="POST" action="/api/client/changer_mot_de_passe.php">
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

            <hr class="my-6">

            <h3 class="font-bold text-primary mb-4">
                <i class="fas fa-edit text-secondary mr-2"></i>Modifier mes informations
            </h3>
            <a href="modifier.php" class="btn-primary w-full py-2.5 rounded-lg font-semibold text-center block">
                <i class="fas fa-pen mr-2"></i> Modifier mes informations
            </a>
        </div>
    </div>
</div>

<script>
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