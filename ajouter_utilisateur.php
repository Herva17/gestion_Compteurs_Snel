<?php
require_once __DIR__ . '/Classes/Utilisateur.php';

// Créer une instance de la classe Utilisateur
$utilisateur = new Utilisateur();

// Vérifier si l'utilisateur est connecté (optionnel)
// if (!$utilisateur->isLoggedIn()) {
//     header('Location: login.php');
//     exit();
// }

$message = '';
$error = '';
$success = false;

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajouter') {
    $data = [
        'nom' => $_POST['nom'] ?? '',
        'prenom' => $_POST['prenom'] ?? '',
        'email' => $_POST['email'] ?? '',
        'motDePasse' => $_POST['motDePasse'] ?? '',
        'role' => $_POST['role'] ?? 'agent',
        'telephone' => $_POST['telephone'] ?? '',
        'dateEmbauche' => $_POST['dateEmbauche'] ?? null,
        'dateNaissance' => $_POST['dateNaissance'] ?? null,
        'sexe' => $_POST['sexe'] ?? null,
        'statut' => $_POST['statut'] ?? 'actif'
    ];

    // Appeler la méthode creer() de la classe Utilisateur
    $result = $utilisateur->creer($data);

    if ($result['success']) {
        $success = true;
        $message = $result['message'];
        // Rediriger après 2 secondes
        header("refresh:2;url=liste_utilisateurs.php");
    } else {
        $error = isset($result['errors']) ? implode('<br>', $result['errors']) : $result['error'];
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un utilisateur</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 40px;
            max-width: 700px;
            width: 100%;
        }

        h1 {
            text-align: center;
            color: #2d3748;
            margin-bottom: 10px;
            font-size: 28px;
        }

        .subtitle {
            text-align: center;
            color: #718096;
            margin-bottom: 30px;
            font-size: 14px;
        }

        .message {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .message.success {
            background: #c6f6d5;
            color: #22543d;
            border: 1px solid #9ae6b4;
        }

        .message.error {
            background: #fed7d7;
            color: #742a2a;
            border: 1px solid #feb2b2;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        label {
            display: block;
            margin-bottom: 6px;
            color: #4a5568;
            font-weight: 600;
            font-size: 14px;
        }

        label .required {
            color: #e53e3e;
            margin-left: 4px;
        }

        input, select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: #f7fafc;
        }

        input:focus, select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
            background: white;
        }

        input.error, select.error {
            border-color: #fc8181;
        }

        .error-message {
            color: #e53e3e;
            font-size: 13px;
            margin-top: 5px;
            display: none;
        }

        .error-message.show {
            display: block;
        }

        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            width: 100%;
            padding: 14px;
            font-size: 16px;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }

        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .btn-secondary {
            background: #e2e8f0;
            color: #4a5568;
            padding: 10px 20px;
            margin-top: 10px;
            width: 100%;
        }

        .btn-secondary:hover {
            background: #cbd5e0;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }

        .form-actions .btn {
            flex: 1;
        }

        .btn-cancel {
            background: #e53e3e;
            color: white;
        }

        .btn-cancel:hover {
            background: #c53030;
        }

        /* Responsive */
        @media (max-width: 600px) {
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
            
            .container {
                padding: 20px;
            }
        }

        .spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s ease-in-out infinite;
            margin: 0 auto;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .btn-loading .spinner {
            display: inline-block;
        }

        .btn-loading .btn-text {
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>➕ Ajouter un utilisateur</h1>
        <p class="subtitle">Remplissez le formulaire pour créer un nouvel utilisateur</p>

        <?php if ($message): ?>
            <div class="message <?php echo $success ? 'success' : 'error'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div style="text-align: center; padding: 20px;">
                <p style="color: #22543d; font-size: 18px;">✅ Utilisateur créé avec succès !</p>
                <p style="color: #718096; margin-top: 10px;">Redirection en cours...</p>
            </div>
        <?php else: ?>
            <form method="POST" action="" id="userForm" novalidate>
                <input type="hidden" name="action" value="ajouter">

                <!-- Identité -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="nom">Nom <span class="required">*</span></label>
                        <input type="text" id="nom" name="nom" 
                               value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>" 
                               required 
                               placeholder="Ex: Dupont">
                        <div class="error-message" id="nom-error">Le nom est requis</div>
                    </div>

                    <div class="form-group">
                        <label for="prenom">Prénom <span class="required">*</span></label>
                        <input type="text" id="prenom" name="prenom" 
                               value="<?php echo htmlspecialchars($_POST['prenom'] ?? ''); ?>" 
                               required 
                               placeholder="Ex: Jean">
                        <div class="error-message" id="prenom-error">Le prénom est requis</div>
                    </div>
                </div>

                <!-- Contact -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Email <span class="required">*</span></label>
                        <input type="email" id="email" name="email" 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                               required 
                               placeholder="exemple@domaine.com">
                        <div class="error-message" id="email-error">Email invalide</div>
                    </div>

                    <div class="form-group">
                        <label for="telephone">Téléphone</label>
                        <input type="tel" id="telephone" name="telephone" 
                               value="<?php echo htmlspecialchars($_POST['telephone'] ?? ''); ?>" 
                               placeholder="+33 6 12 34 56 78">
                    </div>
                </div>

                <!-- Sécurité -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="motDePasse">Mot de passe <span class="required">*</span></label>
                        <input type="password" id="motDePasse" name="motDePasse" 
                               required 
                               minlength="6"
                               placeholder="Minimum 6 caractères">
                        <div class="error-message" id="password-error">Le mot de passe doit contenir au moins 6 caractères</div>
                    </div>

                    <div class="form-group">
                        <label for="role">Rôle <span class="required">*</span></label>
                        <select id="role" name="role" required>
                            <option value="">Sélectionnez un rôle</option>
                            <option value="administrateur" <?php echo (isset($_POST['role']) && $_POST['role'] === 'administrateur') ? 'selected' : ''; ?>>Administrateur</option>
                            <option value="gestionnaire" <?php echo (isset($_POST['role']) && $_POST['role'] === 'gestionnaire') ? 'selected' : ''; ?>>Gestionnaire</option>
                            <option value="agent" <?php echo (isset($_POST['role']) && $_POST['role'] === 'agent') ? 'selected' : ''; ?>>Agent</option>
                            <option value="superviseur" <?php echo (isset($_POST['role']) && $_POST['role'] === 'superviseur') ? 'selected' : ''; ?>>Superviseur</option>
                        </select>
                        <div class="error-message" id="role-error">Veuillez sélectionner un rôle</div>
                    </div>
                </div>

                <!-- Informations supplémentaires -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="dateNaissance">Date de naissance</label>
                        <input type="date" id="dateNaissance" name="dateNaissance" 
                               value="<?php echo htmlspecialchars($_POST['dateNaissance'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="sexe">Sexe</label>
                        <select id="sexe" name="sexe">
                            <option value="">Sélectionnez</option>
                            <option value="M" <?php echo (isset($_POST['sexe']) && $_POST['sexe'] === 'M') ? 'selected' : ''; ?>>Masculin</option>
                            <option value="F" <?php echo (isset($_POST['sexe']) && $_POST['sexe'] === 'F') ? 'selected' : ''; ?>>Féminin</option>
                            <option value="Autre" <?php echo (isset($_POST['sexe']) && $_POST['sexe'] === 'Autre') ? 'selected' : ''; ?>>Autre</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="dateEmbauche">Date d'embauche</label>
                        <input type="date" id="dateEmbauche" name="dateEmbauche" 
                               value="<?php echo htmlspecialchars($_POST['dateEmbauche'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="statut">Statut</label>
                        <select id="statut" name="statut">
                            <option value="actif" <?php echo (isset($_POST['statut']) && $_POST['statut'] === 'actif') ? 'selected' : ''; ?>>Actif</option>
                            <option value="inactif" <?php echo (isset($_POST['statut']) && $_POST['statut'] === 'inactif') ? 'selected' : ''; ?>>Inactif</option>
                            <option value="en_conge" <?php echo (isset($_POST['statut']) && $_POST['statut'] === 'en_conge') ? 'selected' : ''; ?>>En congé</option>
                            <option value="suspendu" <?php echo (isset($_POST['statut']) && $_POST['statut'] === 'suspendu') ? 'selected' : ''; ?>>Suspendu</option>
                        </select>
                    </div>
                </div>

                <!-- Boutons -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <span class="btn-text">Créer l'utilisateur</span>
                        <div class="spinner"></div>
                    </button>
                    <button type="reset" class="btn btn-secondary">Réinitialiser</button>
                </div>

                <div style="margin-top: 15px;">
                    <a href="liste_utilisateurs.php" style="color: #667eea; text-decoration: none; font-size: 14px;">
                        ← Retour à la liste des utilisateurs
                    </a>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('userForm');
            if (!form) return; // Sortir si le formulaire n'existe pas (cas de succès)

            const submitBtn = document.getElementById('submitBtn');

            // Validation en temps réel
            const fields = {
                nom: {
                    element: document.getElementById('nom'),
                    error: document.getElementById('nom-error'),
                    validate: value => value.trim().length > 0
                },
                prenom: {
                    element: document.getElementById('prenom'),
                    error: document.getElementById('prenom-error'),
                    validate: value => value.trim().length > 0
                },
                email: {
                    element: document.getElementById('email'),
                    error: document.getElementById('email-error'),
                    validate: value => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)
                },
                motDePasse: {
                    element: document.getElementById('motDePasse'),
                    error: document.getElementById('password-error'),
                    validate: value => value.length >= 6
                },
                role: {
                    element: document.getElementById('role'),
                    error: document.getElementById('role-error'),
                    validate: value => value !== ''
                }
            };

            // Ajouter les événements de validation
            Object.keys(fields).forEach(key => {
                const field = fields[key];
                if (!field.element) return;
                
                field.element.addEventListener('blur', function() {
                    validateField(key);
                });

                field.element.addEventListener('input', function() {
                    if (this.classList.contains('error')) {
                        validateField(key);
                    }
                });
            });

            function validateField(key) {
                const field = fields[key];
                if (!field) return true;
                
                const isValid = field.validate(field.element.value);
                
                if (!isValid) {
                    field.element.classList.add('error');
                    field.error.classList.add('show');
                } else {
                    field.element.classList.remove('error');
                    field.error.classList.remove('show');
                }
                
                return isValid;
            }

            // Validation du formulaire
            form.addEventListener('submit', function(e) {
                let isValid = true;
                
                // Valider tous les champs
                Object.keys(fields).forEach(key => {
                    if (!validateField(key)) {
                        isValid = false;
                    }
                });

                if (!isValid) {
                    e.preventDefault();
                    // Faire défiler jusqu'au premier champ invalide
                    const firstError = document.querySelector('.error');
                    if (firstError) {
                        firstError.focus();
                        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    return;
                }

                // Activer le spinner
                submitBtn.classList.add('btn-loading');
                submitBtn.disabled = true;
                submitBtn.querySelector('.btn-text').textContent = 'Création en cours...';
            });

            // Gestion du mot de passe (afficher/masquer)
            const passwordInput = document.getElementById('motDePasse');
            if (passwordInput) {
                // Créer un bouton toggle
                const toggleBtn = document.createElement('button');
                toggleBtn.type = 'button';
                toggleBtn.textContent = '👁️';
                toggleBtn.style.cssText = `
                    position: absolute;
                    right: 10px;
                    top: 50%;
                    transform: translateY(-50%);
                    background: none;
                    border: none;
                    cursor: pointer;
                    font-size: 18px;
                `;
                
                const wrapper = passwordInput.parentElement;
                wrapper.style.position = 'relative';
                wrapper.appendChild(toggleBtn);
                
                toggleBtn.addEventListener('click', function() {
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    this.textContent = type === 'password' ? '👁️' : '🔒';
                });
            }
        });
    </script>
</body>
</html>