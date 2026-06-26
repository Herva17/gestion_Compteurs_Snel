<?php
// Pages/Client/Support/signaler.php
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

// Récupérer les compteurs du client
$compteurs = $user->getCompteurs();

$page_title = 'Signaler une anomalie - SNEL';
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
        .input-field { width: 100%; padding: 0.75rem 1rem; border: 2px solid #e2e8f0; border-radius: 0.75rem; transition: all 0.3s ease; }
        .input-field:focus { border-color: var(--secondary); outline: none; box-shadow: 0 0 0 4px rgba(192,86,33,0.1); }
        .card-snel { background: white; border-radius: 1rem; padding: 1.5rem; border: 1px solid #e2e8f0; }
        .topbar { background: white; border-bottom: 1px solid #e2e8f0; padding: 0.75rem 2rem; position: sticky; top: 0; z-index: 50; }
        .step { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; border-radius: 0.75rem; transition: all 0.3s ease; }
        .step:hover { background: #f8fafc; }
        .step-number { width: 32px; height: 32px; border-radius: 50%; background: var(--secondary); color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 0.875rem; flex-shrink: 0; }
    </style>
</head>
<body class="bg-gray-50">

<header class="topbar">
    <div class="flex items-center space-x-4">
        <a href="index.php" class="text-primary hover:text-secondary transition">
            <i class="fas fa-arrow-left"></i> Retour
        </a>
        <h1 class="text-xl font-bold text-primary">Signaler une anomalie</h1>
    </div>
</header>

<div class="max-w-3xl mx-auto px-4 py-6">
    <div class="card-snel">
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
            <p class="text-sm text-yellow-800">
                <i class="fas fa-info-circle mr-2"></i>
                Utilisez ce formulaire pour signaler toute anomalie concernant votre compteur ou votre consommation.
            </p>
        </div>

        <form method="POST" action="/api/client/signaler_anomalie.php">
            <!-- Type d'anomalie -->
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Type d'anomalie <span class="text-red-500">*</span>
                </label>
                <select name="type_anomalie" class="input-field" required>
                    <option value="">Sélectionnez un type</option>
                    <option value="consommation_basse">Consommation anormalement basse</option>
                    <option value="consommation_haute">Consommation anormalement élevée</option>
                    <option value="compteur_defaillant">Compteur défaillant</option>
                    <option value="erreur_releve">Erreur de relevé</option>
                    <option value="panne_courant">Panne de courant</option>
                    <option value="autre">Autre</option>
                </select>
            </div>

            <!-- Compteur concerné -->
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Compteur concerné <span class="text-red-500">*</span>
                </label>
                <select name="id_compteur" class="input-field" required>
                    <option value="">Sélectionnez un compteur</option>
                    <?php foreach ($compteurs as $compteur): ?>
                        <option value="<?= $compteur['idCompteur'] ?>">
                            <?= htmlspecialchars($compteur['numeroSerie']) ?> 
                            (Index: <?= number_format($compteur['indexActuel'], 0, ',', ' ') ?> kWh)
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($compteurs)): ?>
                    <p class="text-xs text-red-500 mt-1">Vous n'avez pas de compteur enregistré.</p>
                <?php endif; ?>
            </div>

            <!-- Description -->
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Description détaillée <span class="text-red-500">*</span>
                </label>
                <textarea name="description" rows="5" class="input-field" placeholder="Décrivez précisément l'anomalie constatée..." required></textarea>
            </div>

            <!-- Consommation actuelle -->
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Consommation actuelle (kWh)
                </label>
                <input type="number" name="consommation_actuelle" class="input-field" placeholder="Ex: 45" step="0.01">
            </div>

            <!-- Photo -->
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-camera mr-2"></i>Photo du compteur
                </label>
                <input type="file" name="photo" accept="image/*" class="input-field">
                <p class="text-xs text-gray-500 mt-1">Format: JPG, PNG - Max 5MB</p>
            </div>

            <!-- Bouton -->
            <button type="submit" class="btn-secondary w-full py-3 rounded-xl font-bold text-lg">
                <i class="fas fa-paper-plane mr-2"></i> Envoyer le signalement
            </button>
        </form>
    </div>

    <!-- Guide de vérification -->
    <div class="card-snel mt-6">
        <h3 class="font-bold text-primary mb-4">🔍 Vérifications à faire avant de signaler</h3>
        <div class="space-y-3">
            <div class="step">
                <span class="step-number">1</span>
                <div>
                    <strong>Vérifier le compteur</strong>
                    <p class="text-sm text-gray-500">Regardez si le compteur tourne normalement et si les chiffres changent.</p>
                </div>
            </div>
            <div class="step">
                <span class="step-number">2</span>
                <div>
                    <strong>Vérifier les appareils</strong>
                    <p class="text-sm text-gray-500">Testez vos appareils électriques pour confirmer qu'ils fonctionnent.</p>
                </div>
            </div>
            <div class="step">
                <span class="step-number">3</span>
                <div>
                    <strong>Vérifier le disjoncteur</strong>
                    <p class="text-sm text-gray-500">Assurez-vous que le disjoncteur principal est en position ON.</p>
                </div>
            </div>
            <div class="step">
                <span class="step-number">4</span>
                <div>
                    <strong>Prendre une photo</strong>
                    <p class="text-sm text-gray-500">Photographiez votre compteur avec l'index visible.</p>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>