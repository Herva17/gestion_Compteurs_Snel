<?php
// forgot-password.php - À la racine du projet
$page_title = 'Mot de passe oublié - SNEL';
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
        .btn-secondary { background: var(--secondary); color: white; transition: all 0.3s ease; }
        .btn-secondary:hover { background: #a0441a; transform: translateY(-2px); }
        .input-field { width: 100%; padding: 0.75rem 1rem; border: 2px solid #e2e8f0; border-radius: 0.75rem; transition: all 0.3s ease; }
        .input-field:focus { border-color: var(--secondary); outline: none; box-shadow: 0 0 0 4px rgba(192,86,33,0.1); }
        .hero-section { background: linear-gradient(135deg, #1a365d 0%, #2a4a7a 50%, #3a5a8a 100%); min-height: 100vh; display: flex; align-items: center; position: relative; overflow: hidden; }
        .hero-section::before { content: ''; position: absolute; top: -40%; right: -15%; width: 600px; height: 600px; background: radial-gradient(circle, rgba(236,201,75,0.08) 0%, transparent 70%); border-radius: 50%; }
    </style>
</head>
<body>

<div class="hero-section">
    <div class="max-w-md mx-auto px-4 w-full relative z-10">
        <div class="text-center mb-8">
            <div class="inline-flex items-center space-x-3">
                <div class="w-14 h-14 bg-secondary rounded-xl flex items-center justify-center shadow-lg">
                    <i class="fas fa-bolt text-white text-2xl"></i>
                </div>
                <div>
                    <span class="text-3xl font-extrabold text-white tracking-tight">SNEL</span>
                    <span class="text-secondary text-[10px] block -mt-0.5 font-semibold tracking-wider">Mot de passe oublié</span>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-2xl p-8">
            <h2 class="text-2xl font-bold text-primary text-center mb-2">Mot de passe oublié ?</h2>
            <p class="text-gray-500 text-sm text-center mb-6">
                Entrez votre email pour recevoir un lien de réinitialisation.
            </p>

            <?php if (isset($_GET['success'])): ?>
                <div class="bg-green-50 border border-green-200 text-green-600 px-4 py-3 rounded-xl mb-4 text-sm">
                    <i class="fas fa-check-circle mr-2"></i> Un email de réinitialisation a été envoyé.
                </div>
            <?php endif; ?>

            <form method="POST" action="/api/client/forgot-password.php">
                <div class="mb-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Email</label>
                    <input type="email" name="email" class="input-field" placeholder="votre@email.com" required>
                </div>
                <button type="submit" class="btn-secondary w-full py-3 rounded-xl font-bold text-lg">
                    <i class="fas fa-paper-plane mr-2"></i> Envoyer le lien
                </button>
            </form>

            <div class="mt-6 text-center">
                <a href="login.php" class="text-sm text-primary hover:text-secondary transition">
                    <i class="fas fa-arrow-left mr-1"></i> Retour à la connexion
                </a>
            </div>
        </div>

        <div class="text-center mt-6 text-xs text-white/40">
            <p>© <?= date('Y') ?> SNEL - Société Nationale d'Électricité</p>
        </div>
    </div>
</div>

</body>
</html>