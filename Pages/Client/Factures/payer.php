<?php
// Pages/Client/Factures/payer.php
session_start();
require_once __DIR__ . '/../../../Classes/Database.php';
require_once __DIR__ . '/../../../Classes/Client.php';
require_once __DIR__ . '/../../../Classes/Facture.php';

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

$idFacture = $_GET['id'] ?? 0;
$facture = new Facture();
$factureInfo = $facture->getById($idFacture);

if (!$factureInfo || $factureInfo->toArray()['idClient'] != $user->getIdClient()) {
    header('Location: index.php');
    exit;
}

$factureData = $factureInfo->toArray();
$estPayee = $factureData['statut'] === 'payee';
$isEnRetard = strtotime($factureData['dateLimitePaiement']) < time() && !$estPayee;

$page_title = 'Paiement - SNEL';
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
        .btn-outline-secondary { border: 2px solid var(--secondary); color: var(--secondary); background: transparent; transition: all 0.3s ease; }
        .btn-outline-secondary:hover { background: var(--secondary); color: white; transform: translateY(-2px); }
        .input-field { width: 100%; padding: 0.75rem 1rem; border: 2px solid #e2e8f0; border-radius: 0.75rem; transition: all 0.3s ease; }
        .input-field:focus { border-color: var(--secondary); outline: none; box-shadow: 0 0 0 4px rgba(192,86,33,0.1); }
        .card-snel { background: white; border-radius: 1rem; padding: 1.5rem; border: 1px solid #e2e8f0; }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.65rem; font-weight: 600; text-transform: uppercase; }
        .status-payee { background: #dcfce7; color: #166534; }
        .status-attente { background: #fef3c7; color: #92400e; }
        .status-retard { background: #fecaca; color: #991b1b; }
        .payment-method { border: 2px solid #e2e8f0; border-radius: 0.75rem; padding: 1rem; cursor: pointer; transition: all 0.3s ease; text-align: center; }
        .payment-method:hover { border-color: var(--secondary); background: #f8fafc; }
        .payment-method.selected { border-color: var(--secondary); background: #fff5ed; }
        .topbar { background: white; border-bottom: 1px solid #e2e8f0; padding: 0.75rem 2rem; position: sticky; top: 0; z-index: 50; }
    </style>
</head>
<body class="bg-gray-50">

<header class="topbar">
    <div class="flex items-center space-x-4">
        <a href="index.php" class="text-primary hover:text-secondary transition">
            <i class="fas fa-arrow-left"></i> Retour
        </a>
        <h1 class="text-xl font-bold text-primary">Paiement</h1>
    </div>
</header>

<div class="max-w-3xl mx-auto px-4 py-6">
    <?php if ($estPayee): ?>
        <div class="bg-green-50 border border-green-200 text-green-600 rounded-xl p-6 text-center">
            <i class="fas fa-check-circle text-4xl mb-3 block"></i>
            <h2 class="text-xl font-bold">Facture déjà payée</h2>
            <p class="text-sm">Cette facture a déjà été réglée.</p>
            <a href="index.php" class="btn-secondary inline-block mt-4 px-6 py-2 rounded-lg">Voir mes factures</a>
        </div>
    <?php else: ?>
        <!-- Récapitulatif -->
        <div class="card-snel mb-6">
            <h3 class="font-bold text-primary mb-4">Récapitulatif</h3>
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <p class="text-xs text-gray-500">Numéro</p>
                    <p class="font-bold"><?= htmlspecialchars($factureData['numeroFacture']) ?></p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Date d'émission</p>
                    <p><?= date('d/m/Y', strtotime($factureData['dateEmission'])) ?></p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Date limite</p>
                    <p class="<?= $isEnRetard ? 'text-red-500 font-bold' : '' ?>">
                        <?= date('d/m/Y', strtotime($factureData['dateLimitePaiement'])) ?>
                        <?php if ($isEnRetard): ?>
                            <span class="text-red-500 text-xs">(En retard)</span>
                        <?php endif; ?>
                    </p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Montant</p>
                    <p class="text-2xl font-bold text-secondary"><?= number_format($factureData['montantTotal'], 0, ',', ' ') ?> FCFA</p>
                </div>
            </div>
        </div>

        <!-- Méthodes de paiement -->
        <div class="card-snel mb-6">
            <h3 class="font-bold text-primary mb-4">Méthode de paiement</h3>
            <div class="grid md:grid-cols-3 gap-3">
                <div class="payment-method selected" onclick="selectPayment(this, 'mobile_money')">
                    <i class="fas fa-mobile-alt text-3xl text-secondary mb-2"></i>
                    <p class="font-semibold text-sm">Mobile Money</p>
                </div>
                <div class="payment-method" onclick="selectPayment(this, 'carte')">
                    <i class="fas fa-credit-card text-3xl text-secondary mb-2"></i>
                    <p class="font-semibold text-sm">Carte Bancaire</p>
                </div>
                <div class="payment-method" onclick="selectPayment(this, 'especes')">
                    <i class="fas fa-money-bill-wave text-3xl text-secondary mb-2"></i>
                    <p class="font-semibold text-sm">Espèces</p>
                </div>
            </div>
            <input type="hidden" id="payment_method" value="mobile_money">
        </div>

        <!-- Formulaire Mobile Money -->
        <div class="card-snel" id="mobile_money_form">
            <h3 class="font-bold text-primary mb-4">
                <i class="fas fa-mobile-alt text-secondary mr-2"></i>Mobile Money
            </h3>
            <form method="POST" action="/api/client/payer_facture.php">
                <input type="hidden" name="id_facture" value="<?= $idFacture ?>">
                <input type="hidden" name="mode_paiement" id="mode_paiement" value="mobile_money">
                <div class="mb-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Opérateur</label>
                    <select name="operateur" class="input-field" required>
                        <option value="orange">Orange Money</option>
                        <option value="airtel">Airtel Money</option>
                        <option value="vodacom">Vodacom M-Pesa</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Numéro de téléphone</label>
                    <input type="tel" name="telephone" class="input-field" placeholder="+243 XX XXX XXXX" required>
                </div>
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                    <p class="text-sm text-yellow-800">
                        <i class="fas fa-info-circle mr-2"></i>
                        Vous recevrez une confirmation par SMS après le paiement.
                    </p>
                </div>
                <button type="submit" class="btn-secondary w-full py-3 rounded-xl font-bold text-lg">
                    <i class="fas fa-check-circle mr-2"></i> Confirmer le paiement
                </button>
            </form>
        </div>

        <!-- Formulaire Carte -->
        <div class="card-snel hidden" id="carte_form">
            <h3 class="font-bold text-primary mb-4">
                <i class="fas fa-credit-card text-secondary mr-2"></i>Carte Bancaire
            </h3>
            <form>
                <div class="mb-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Numéro de carte</label>
                    <input type="text" class="input-field" placeholder="XXXX XXXX XXXX XXXX">
                </div>
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Expiration</label>
                        <input type="text" class="input-field" placeholder="MM/AA">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">CVV</label>
                        <input type="text" class="input-field" placeholder="XXX">
                    </div>
                </div>
                <button type="submit" class="btn-secondary w-full py-3 rounded-xl font-bold">
                    <i class="fas fa-lock mr-2"></i> Payer
                </button>
            </form>
        </div>

        <!-- Espèces -->
        <div class="card-snel hidden" id="especes_form">
            <h3 class="font-bold text-primary mb-4">
                <i class="fas fa-money-bill-wave text-secondary mr-2"></i>Paiement en espèces
            </h3>
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                <p class="text-sm text-blue-800">
                    <i class="fas fa-info-circle mr-2"></i>
                    Rendez-vous dans une agence SNEL avec votre facture pour effectuer le paiement.
                </p>
                <p class="text-sm text-blue-800 mt-2">
                    <i class="fas fa-map-marker-alt mr-2"></i>
                    Agence SNEL la plus proche : Goma - Avenue du Commerce
                </p>
            </div>
            <a href="index.php" class="btn-primary w-full py-3 rounded-xl font-bold text-center block">
                <i class="fas fa-check mr-2"></i> J'ai payé, confirmer
            </a>
        </div>
    <?php endif; ?>
</div>

<script>
function selectPayment(element, method) {
    document.querySelectorAll('.payment-method').forEach(el => el.classList.remove('selected'));
    element.classList.add('selected');
    document.getElementById('payment_method').value = method;
    document.getElementById('mode_paiement').value = method;
    
    document.querySelectorAll('[id$="_form"]').forEach(el => el.classList.add('hidden'));
    document.getElementById(method + '_form').classList.remove('hidden');
}
</script>

</body>
</html>