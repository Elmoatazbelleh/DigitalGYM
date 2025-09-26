<?php
require_once 'config.php';

$error = '';
$success = '';

// Si l'utilisateur est déjà connecté, rediriger vers le dashboard
if(isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nom = trim($_POST['nom']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'];
    $admin_static_password = isset($_POST['admin_static_password']) ? $_POST['admin_static_password'] : '';
    
    // Validation des données
    if(empty($nom) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'Veuillez remplir tous les champs';
    } elseif($password !== $confirm_password) {
        $error = 'Les mots de passe ne correspondent pas';
    } elseif(strlen($password) < 6) {
        $error = 'Le mot de passe doit contenir au moins 6 caractères';
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Adresse email invalide';
    } elseif(!in_array($role, ['admin', 'client'])) {
        $error = 'Rôle invalide';
    } elseif($role === 'admin' && $admin_static_password !== 'admintest') {
        $error = 'Mot de passe administrateur incorrect';
    } else {
        try {
            // Vérifier si l'email existe déjà
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if($stmt->fetch()) {
                $error = 'Cette adresse email est déjà utilisée';
            } else {
                // Hasher le mot de passe
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insérer le nouvel utilisateur
                $stmt = $pdo->prepare("INSERT INTO users (nom, email, mot_de_passe, role) VALUES (?, ?, ?, ?)");
                $stmt->execute([$nom, $email, $hashed_password, $role]);
                
                $success = 'Inscription réussie ! Vous pouvez maintenant vous connecter.';
            }
        } catch(PDOException $e) {
            $error = 'Erreur lors de l\'inscription';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - FitZone</title>
    <link rel="stylesheet" href="style.css">
    <script>
        function toggleAdminPasswordField() {
            const roleSelect = document.getElementById('role');
            const adminPasswordField = document.getElementById('admin-password-field');
            if (roleSelect.value === 'admin') {
                adminPasswordField.style.display = 'block';
            } else {
                adminPasswordField.style.display = 'none';
            }
        }
    </script>
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="logo">
                <h1><a href="index.php"> FitZone</a></h1>
            </div>
            <nav class="nav">
                <ul>
                    <li><a href="index.php">Accueil</a></li>
                    <li><a href="login.php">Connexion</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main>
        <section class="auth-section">
            <div class="container">
                <div class="auth-container">
                    <div class="auth-form">
                        <h2>Inscription</h2>
                        <p class="auth-subtitle">Créez votre compte FitZone</p>
                        
                        <?php if($error): ?>
                            <div class="alert alert-error">
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if($success): ?>
                            <div class="alert alert-success">
                                <?php echo htmlspecialchars($success); ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="form-group">
                                <label for="nom">Nom complet</label>
                                <input type="text" id="nom" name="nom" required 
                                       value="<?php echo isset($_POST['nom']) ? htmlspecialchars($_POST['nom']) : ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" required 
                                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="role">Type de compte</label>
                                <select id="role" name="role" required onchange="toggleAdminPasswordField()">
                                    <option value="">Sélectionnez un type</option>
                                    <option value="client" <?php echo (isset($_POST['role']) && $_POST['role'] == 'client') ? 'selected' : ''; ?>>Client</option>
                                    <option value="admin" <?php echo (isset($_POST['role']) && $_POST['role'] == 'admin') ? 'selected' : ''; ?>>Administrateur</option>
                                </select>
                            </div>
                            
                            <div class="form-group" id="admin-password-field" style="display: <?php echo (isset($_POST['role']) && $_POST['role'] == 'admin') ? 'block' : 'none'; ?>;">
                                <label for="admin_static_password">Mot de passe administrateur</label>
                                <input type="password" id="admin_static_password" name="admin_static_password" placeholder="Entrez le mot de passe administrateur">
                            </div>
                            
                            <div class="form-group">
                                <label for="password">Mot de passe</label>
                                <input type="password" id="password" name="password" required minlength="6">
                                <small class="form-text">Minimum 6 caractères</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password">Confirmer le mot de passe</label>
                                <input type="password" id="confirm_password" name="confirm_password" required>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-full">S'inscrire</button>
                        </form>
                        
                        <div class="auth-links">
                            <p>Déjà un compte ? <a href="login.php">Se connecter</a></p>
                        </div>
                    </div>
                    
                    <div class="auth-image">
                        <div class="auth-image-content">
                            <h3>Commencez votre parcours fitness</h3>
                            <p>Rejoignez des milliers d'utilisateurs qui ont transformé leur vie avec FitZone</p>
                            <div class="auth-features">
                                <div class="feature">✓ Accès à tous nos produits</div>
                                <div class="feature">✓ Suivi de commandes</div>
                                <div class="feature">✓ Conseils personnalisés</div>
                                <div class="feature">✓ Offres exclusives membres</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>FitZone</h3>
                    <p>Votre partenaire fitness depuis 2024</p>
                </div>
                <div class="footer-section">
                    <h3>Contact</h3>
                    <p>Email: info@fitzone.com</p>
                    <p>Tél: +216 12 345 678</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2024 FitZone. Tous droits réservés.</p>
            </div>
        </div>
    </footer>
</body>
</html>