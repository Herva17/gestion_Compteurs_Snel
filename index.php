<?php
// Page d'accueil SNEL - Gestion des compteurs électriques
$page_title = 'SNEL - Gestion des compteurs électriques';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        * { font-family: 'Inter', sans-serif; }
        
        :root {
            --primary: #1a365d;
            --primary-light: #2a4a7a;
            --primary-dark: #0f2440;
            --secondary: #c05621;
            --secondary-light: #dd6b20;
            --accent: #ecc94b;
            --light: #f7fafc;
            --gray: #edf2f7;
        }
        
        .bg-primary { background: var(--primary); }
        .bg-primary-light { background: var(--primary-light); }
        .text-primary { color: var(--primary); }
        .text-primary-light { color: var(--primary-light); }
        .border-primary { border-color: var(--primary); }
        
        .bg-secondary { background: var(--secondary); }
        .text-secondary { color: var(--secondary); }
        .border-secondary { border-color: var(--secondary); }
        .bg-secondary-light { background: var(--secondary-light); }
        
        .bg-accent { background: var(--accent); }
        .text-accent { color: var(--accent); }
        
        /* Boutons */
        .btn-primary {
            background: var(--primary);
            color: white;
            transition: all 0.3s ease;
            border: none;
        }
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(26, 54, 93, 0.25);
        }
        
        .btn-secondary {
            background: var(--secondary);
            color: white;
            transition: all 0.3s ease;
            border: none;
        }
        .btn-secondary:hover {
            background: #a0441a;
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(192, 86, 33, 0.25);
        }
        
        .btn-outline {
            border: 2px solid var(--primary);
            color: var(--primary);
            background: transparent;
            transition: all 0.3s ease;
        }
        .btn-outline:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
        }
        
        .btn-outline-light {
            border: 2px solid rgba(255,255,255,0.4);
            color: white;
            background: transparent;
            transition: all 0.3s ease;
        }
        .btn-outline-light:hover {
            background: rgba(255,255,255,0.1);
            border-color: white;
            transform: translateY(-2px);
        }
        
        /* Hero */
        .hero-section {
            background: linear-gradient(135deg, #1a365d 0%, #2a4a7a 50%, #3a5a8a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: -40%;
            right: -15%;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(236, 201, 75, 0.08) 0%, transparent 70%);
            border-radius: 50%;
        }
        
        .hero-section::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -10%;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(192, 86, 33, 0.06) 0%, transparent 70%);
            border-radius: 50%;
        }
        
        /* Cards */
        .card-feature {
            background: white;
            border-radius: 16px;
            padding: 32px;
            transition: all 0.4s ease;
            border: 1px solid #e2e8f0;
            position: relative;
            overflow: hidden;
        }
        
        .card-feature::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--secondary), var(--accent));
            transform: scaleX(0);
            transition: transform 0.4s ease;
        }
        
        .card-feature:hover::before {
            transform: scaleX(1);
        }
        
        .card-feature:hover {
            transform: translateY(-6px);
            box-shadow: 0 20px 40px rgba(26, 54, 93, 0.08);
            border-color: var(--secondary);
        }
        
        /* Stats */
        .stat-item {
            background: rgba(255,255,255,0.06);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .stat-item:hover {
            background: rgba(255,255,255,0.1);
            transform: translateY(-2px);
        }
        
        /* Illustration cards */
        .demo-card {
            background: rgba(255,255,255,0.06);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 16px;
            padding: 20px;
            transition: all 0.3s ease;
        }
        
        .demo-card:hover {
            background: rgba(255,255,255,0.1);
            transform: translateY(-4px);
        }
        
        /* Animation */
        .float-slow {
            animation: float 6s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-16px); }
        }
        
        /* Navigation */
        .nav-link {
            color: rgba(255,255,255,0.8);
            transition: all 0.3s ease;
            font-weight: 500;
            position: relative;
        }
        
        .nav-link::after {
            content: '';
            position: absolute;
            bottom: -4px;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--accent);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }
        
        .nav-link:hover {
            color: white;
        }
        
        .nav-link:hover::after {
            transform: scaleX(1);
        }
        
        /* Section title */
        .section-title {
            position: relative;
            display: inline-block;
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 50%;
            transform: translateX(-50%);
            width: 40px;
            height: 3px;
            background: var(--secondary);
            border-radius: 2px;
        }
        
        /* Footer */
        .footer-link {
            color: rgba(255,255,255,0.7);
            transition: all 0.3s ease;
        }
        .footer-link:hover {
            color: var(--accent);
        }
        
        /* Mobile menu */
        .mobile-menu {
            background: var(--primary);
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        
        /* Scrollbar */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: var(--gray); }
        ::-webkit-scrollbar-thumb { background: var(--secondary); border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #a0441a; }
    </style>
</head>
<body>

<!-- ============================================ -->
<!-- NAVIGATION -->
<!-- ============================================ -->
<nav class="fixed top-0 left-0 right-0 z-50 bg-primary/95 backdrop-blur-md shadow-lg">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex justify-between items-center h-16">
            <!-- Logo -->
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 bg-secondary rounded-xl flex items-center justify-center shadow-lg">
                    <i class="fas fa-bolt text-white text-xl"></i>
                </div>
                <div>
                    <span class="text-white font-bold text-xl tracking-tight">SNEL</span>
                    <span class="text-accent text-[10px] block -mt-0.5 font-semibold tracking-wider uppercase">Gestion des compteurs</span>
                </div>
            </div>

            <!-- Dans index.php, après le logo ou en haut de page -->
<?php if (isset($_GET['logout']) && $_GET['logout'] === 'success'): ?>
    <div class="bg-green-50 border border-green-200 text-green-600 px-4 py-3 rounded-xl mb-4 text-sm flex items-center">
        <i class="fas fa-check-circle mr-3 text-green-500"></i>
        <span>Vous avez été déconnecté avec succès. À bientôt !</span>
    </div>
<?php endif; ?>
            
            <!-- Menu Desktop -->
            <div class="hidden md:flex items-center space-x-8">
                <a href="#hero" class="nav-link">Accueil</a>
                <a href="#features" class="nav-link">Services</a>
                <a href="#contact" class="nav-link">Contact</a>
            </div>
            
            <!-- Actions -->
            <div class="flex items-center space-x-3">
                <a href="login.php" class="hidden md:inline-flex items-center px-4 py-2 text-white border border-white/30 rounded-lg font-medium text-sm hover:bg-white/10 transition">
                    <i class="fas fa-sign-in-alt mr-2"></i> Connexion
                </a>
                <a href="register.php" class="btn-secondary px-5 py-2 rounded-lg font-semibold text-sm inline-flex items-center shadow-lg">
                    <i class="fas fa-user-plus mr-2"></i> Inscription
                </a>
                <button id="menu-toggle" class="md:hidden text-white text-2xl hover:text-accent transition">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Menu Mobile -->
    <div id="mobile-menu" class="hidden md:hidden mobile-menu py-4">
        <div class="max-w-7xl mx-auto px-4 space-y-3">
            <a href="#hero" class="block text-white/80 hover:text-white py-2 border-b border-white/10">Accueil</a>
            <a href="#features" class="block text-white/80 hover:text-white py-2 border-b border-white/10">Services</a>
            <a href="#contact" class="block text-white/80 hover:text-white py-2 border-b border-white/10">Contact</a>
            <div class="pt-3 space-y-2">
                <a href="login.php" class="block text-center py-2 text-white border border-white/30 rounded-lg font-medium">Connexion</a>
                <a href="register.php" class="block text-center py-2 btn-secondary rounded-lg font-semibold">Inscription</a>
            </div>
        </div>
    </div>
</nav>

<!-- ============================================ -->
<!-- SECTION 1: HERO -->
<!-- ============================================ -->
<section id="hero" class="hero-section pt-20">
    <div class="max-w-7xl mx-auto px-4 relative z-10">
        <div class="grid lg:grid-cols-2 gap-16 items-center">
            <!-- Texte -->
            <div>
                <div class="inline-flex items-center bg-accent/20 px-4 py-1.5 rounded-full text-accent text-sm font-semibold mb-5">
                    <i class="fas fa-bolt mr-2"></i> Service public d'électricité
                </div>
                
                <h1 class="text-4xl md:text-5xl lg:text-6xl font-extrabold text-white leading-[1.1]">
                    Gérez vos
                    <span class="text-accent block mt-1">compteurs électriques</span>
                </h1>
                
                <p class="text-blue-200 text-lg mt-4 max-w-lg leading-relaxed">
                    Consultez votre consommation, payez vos factures et suivez votre historique en temps réel, simplement et rapidement.
                </p>
                
                <div class="flex flex-wrap gap-4 mt-8">
                    <a href="#features" class="btn-secondary px-8 py-3.5 rounded-xl font-semibold inline-flex items-center shadow-xl">
                        <i class="fas fa-play-circle mr-2"></i> Découvrir
                    </a>
                    <a href="#contact" class="btn-outline-light px-8 py-3.5 rounded-xl font-medium inline-flex items-center">
                        <i class="fas fa-phone mr-2"></i> Nous contacter
                    </a>
                </div>
                
                <!-- Stats -->
                <div class="grid grid-cols-3 gap-4 mt-12">
                    <div class="stat-item">
                        <div class="text-2xl font-bold text-white">50K+</div>
                        <p class="text-blue-300 text-xs font-medium">Clients</p>
                    </div>
                    <div class="stat-item">
                        <div class="text-2xl font-bold text-accent">99.5%</div>
                        <p class="text-blue-300 text-xs font-medium">Disponibilité</p>
                    </div>
                    <div class="stat-item">
                        <div class="text-2xl font-bold text-white">24/7</div>
                        <p class="text-blue-300 text-xs font-medium">Support</p>
                    </div>
                </div>
            </div>
            
            <!-- Illustration -->
            <div class="hidden lg:block">
                <div class="relative float-slow">
                    <div class="demo-card p-6">
                        <div class="grid grid-cols-2 gap-4">
                            <!-- Card 1 -->
                            <div class="demo-card p-4 text-center">
                                <div class="text-3xl mb-2">⚡</div>
                                <div class="text-white font-bold text-xl">2,847</div>
                                <div class="text-blue-300 text-xs">kWh consommés</div>
                                <div class="mt-3 bg-white/10 rounded-full h-1.5 overflow-hidden">
                                    <div class="bg-accent rounded-full h-1.5" style="width: 68%"></div>
                                </div>
                                <span class="text-accent text-xs font-semibold mt-1 block">+12% ce mois</span>
                            </div>
                            
                            <!-- Card 2 -->
                            <div class="demo-card p-4 text-center">
                                <div class="text-3xl mb-2">💡</div>
                                <div class="text-white font-bold text-xl">12</div>
                                <div class="text-blue-300 text-xs">Jours restants</div>
                                <div class="mt-3 bg-white/10 rounded-full h-1.5 overflow-hidden">
                                    <div class="bg-secondary rounded-full h-1.5" style="width: 40%"></div>
                                </div>
                                <span class="text-secondary text-xs font-semibold mt-1 block">Paiement à venir</span>
                            </div>
                            
                            <!-- Card 3 - Full width -->
                            <div class="col-span-2 demo-card p-4">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <div class="text-white/80 text-sm font-medium">Facture du mois</div>
                                        <div class="text-accent text-2xl font-bold">34,500 F</div>
                                    </div>
                                    <span class="bg-secondary/20 text-secondary px-3 py-1 rounded-full text-xs font-bold border border-secondary/30">
                                        En attente
                                    </span>
                                </div>
                                <div class="mt-3 flex justify-between text-xs text-blue-300">
                                    <span>Échéance: 30/06/2026</span>
                                    <a href="#" class="text-accent hover:underline font-medium">Payer maintenant →</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Floating decorative elements -->
                    <div class="absolute -top-4 -right-4 w-16 h-16 bg-accent/10 rounded-full backdrop-blur flex items-center justify-center border border-accent/20">
                        <i class="fas fa-bolt text-accent text-2xl"></i>
                    </div>
                    <div class="absolute -bottom-4 -left-4 w-12 h-12 bg-secondary/10 rounded-full backdrop-blur flex items-center justify-center border border-secondary/20">
                        <i class="fas fa-chart-line text-secondary text-xl"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================================ -->
<!-- SECTION 2: FONCTIONNALITÉS -->
<!-- ============================================ -->
<section id="features" class="py-20 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4">
        <div class="text-center mb-14">
            <span class="text-secondary font-semibold text-sm uppercase tracking-wider">Services</span>
            <h2 class="text-3xl md:text-4xl font-bold text-primary mt-2 section-title">
                Ce que nous vous offrons
            </h2>
            <p class="text-gray-600 max-w-2xl mx-auto mt-6 text-lg">
                Une solution complète pour gérer efficacement votre consommation électrique
            </p>
        </div>
        
        <div class="grid md:grid-cols-3 gap-8">
            <!-- Feature 1 -->
            <div class="card-feature text-center">
                <div class="w-16 h-16 bg-primary/10 rounded-2xl flex items-center justify-center mx-auto mb-5">
                    <i class="fas fa-gauge-high text-primary text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold text-primary">Suivi de consommation</h3>
                <p class="text-gray-600 text-sm mt-2 leading-relaxed">
                    Visualisez votre consommation en temps réel et recevez des alertes personnalisées pour mieux gérer votre énergie.
                </p>
                <a href="#" class="text-secondary font-semibold text-sm inline-flex items-center mt-4 hover:underline">
                    En savoir plus <i class="fas fa-arrow-right ml-2 text-xs"></i>
                </a>
            </div>
            
            <!-- Feature 2 -->
            <div class="card-feature text-center border-secondary/30">
                <div class="w-16 h-16 bg-secondary/10 rounded-2xl flex items-center justify-center mx-auto mb-5">
                    <i class="fas fa-file-invoice-dollar text-secondary text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold text-primary">Gestion des factures</h3>
                <p class="text-gray-600 text-sm mt-2 leading-relaxed">
                    Consultez vos factures, suivez vos paiements et recevez vos quittances directement en ligne.
                </p>
                <a href="#" class="text-secondary font-semibold text-sm inline-flex items-center mt-4 hover:underline">
                    En savoir plus <i class="fas fa-arrow-right ml-2 text-xs"></i>
                </a>
            </div>
            
            <!-- Feature 3 -->
            <div class="card-feature text-center">
                <div class="w-16 h-16 bg-primary/10 rounded-2xl flex items-center justify-center mx-auto mb-5">
                    <i class="fas fa-headset text-primary text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold text-primary">Support 24/7</h3>
                <p class="text-gray-600 text-sm mt-2 leading-relaxed">
                    Une équipe dédiée à votre écoute pour répondre à toutes vos questions et résoudre vos problèmes rapidement.
                </p>
                <a href="#" class="text-secondary font-semibold text-sm inline-flex items-center mt-4 hover:underline">
                    En savoir plus <i class="fas fa-arrow-right ml-2 text-xs"></i>
                </a>
            </div>
        </div>
        
        <!-- CTA -->
        <div class="text-center mt-12">
            <a href="register.php" class="btn-primary px-10 py-3.5 rounded-xl font-semibold inline-flex items-center shadow-lg">
                <i class="fas fa-user-plus mr-2"></i> Créer un compte gratuitement
            </a>
        </div>
    </div>
</section>

<!-- ============================================ -->
<!-- SECTION 3: CONTACT (optionnel) -->
<!-- ============================================ -->
<section id="contact" class="py-16 bg-white">
    <div class="max-w-4xl mx-auto px-4">
        <div class="text-center mb-10">
            <span class="text-secondary font-semibold text-sm uppercase tracking-wider">Contact</span>
            <h2 class="text-3xl font-bold text-primary mt-2 section-title">
                Besoin d'aide ?
            </h2>
        </div>
        
        <div class="grid md:grid-cols-2 gap-8">
            <!-- Contact info -->
            <div class="space-y-4">
                <div class="flex items-start space-x-4 p-4 bg-gray-50 rounded-xl">
                    <div class="w-10 h-10 bg-primary rounded-lg flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-phone text-white text-sm"></i>
                    </div>
                    <div>
                        <p class="font-semibold text-primary text-sm">Téléphone</p>
                        <p class="text-gray-600 text-sm">+243 81 234 5678</p>
                    </div>
                </div>
                <div class="flex items-start space-x-4 p-4 bg-gray-50 rounded-xl">
                    <div class="w-10 h-10 bg-primary rounded-lg flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-envelope text-white text-sm"></i>
                    </div>
                    <div>
                        <p class="font-semibold text-primary text-sm">Email</p>
                        <p class="text-gray-600 text-sm">contact@snel.cd</p>
                    </div>
                </div>
                <div class="flex items-start space-x-4 p-4 bg-gray-50 rounded-xl">
                    <div class="w-10 h-10 bg-primary rounded-lg flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-clock text-white text-sm"></i>
                    </div>
                    <div>
                        <p class="font-semibold text-primary text-sm">Horaires</p>
                        <p class="text-gray-600 text-sm">Lun - Ven: 08:00 - 18:00</p>
                    </div>
                </div>
            </div>
            
            <!-- Form -->
            <div class="bg-gray-50 rounded-xl p-6">
                <h3 class="font-bold text-primary text-lg mb-4">Envoyez-nous un message</h3>
                <form>
                    <input type="text" placeholder="Votre nom" class="w-full px-4 py-2.5 bg-white rounded-lg border border-gray-200 focus:border-secondary focus:ring-2 focus:ring-secondary/20 outline-none transition mb-3 text-sm">
                    <input type="email" placeholder="Votre email" class="w-full px-4 py-2.5 bg-white rounded-lg border border-gray-200 focus:border-secondary focus:ring-2 focus:ring-secondary/20 outline-none transition mb-3 text-sm">
                    <textarea rows="3" placeholder="Votre message" class="w-full px-4 py-2.5 bg-white rounded-lg border border-gray-200 focus:border-secondary focus:ring-2 focus:ring-secondary/20 outline-none transition mb-3 text-sm"></textarea>
                    <button type="submit" class="btn-primary w-full py-2.5 rounded-lg font-semibold text-sm">
                        <i class="fas fa-paper-plane mr-2"></i> Envoyer
                    </button>
                </form>
            </div>
        </div>
    </div>
</section>

<!-- ============================================ -->
<!-- FOOTER -->
<!-- ============================================ -->
<footer class="bg-primary text-white py-8">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex flex-col md:flex-row justify-between items-center">
            <div class="flex items-center space-x-3">
                <div class="w-8 h-8 bg-secondary rounded-lg flex items-center justify-center">
                    <i class="fas fa-bolt text-white text-sm"></i>
                </div>
                <div>
                    <span class="font-bold text-lg">SNEL</span>
                    <span class="text-blue-300 text-xs block -mt-0.5">Gestion des compteurs</span>
                </div>
            </div>
            
            <div class="flex space-x-6 text-sm mt-4 md:mt-0">
                <a href="#hero" class="footer-link">Accueil</a>
                <a href="#features" class="footer-link">Services</a>
                <a href="#contact" class="footer-link">Contact</a>
            </div>
            
            <div class="flex space-x-4 mt-4 md:mt-0">
                <a href="#" class="footer-link"><i class="fab fa-facebook-f"></i></a>
                <a href="#" class="footer-link"><i class="fab fa-twitter"></i></a>
                <a href="#" class="footer-link"><i class="fab fa-instagram"></i></a>
                <a href="#" class="footer-link"><i class="fab fa-youtube"></i></a>
            </div>
        </div>
        
        <div class="border-t border-white/10 mt-6 pt-4 text-center text-blue-300 text-xs">
            &copy; <?= date('Y') ?> SNEL - Société Nationale d'Électricité. Tous droits réservés.
        </div>
    </div>
</footer>

<!-- ============================================ -->
<!-- SCRIPTS -->
<!-- ============================================ -->
<script>
    // Menu mobile
    document.getElementById('menu-toggle').addEventListener('click', function() {
        document.getElementById('mobile-menu').classList.toggle('hidden');
    });
    
    // Fermer le menu mobile
    document.querySelectorAll('#mobile-menu a').forEach(link => {
        link.addEventListener('click', function() {
            document.getElementById('mobile-menu').classList.add('hidden');
        });
    });
    
    // Navigation fluide
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                const navHeight = 68;
                const targetPosition = target.getBoundingClientRect().top + window.pageYOffset - navHeight;
                window.scrollTo({ behavior: 'smooth', top: targetPosition });
            }
        });
    });
</script>

</body>
</html>