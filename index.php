<?php
require_once 'config.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FitZone - Salle de Sport</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="logo">
                <h1>💪 FitZone</h1>
            </div>
            <nav class="nav">
                <ul>
                    <li><a href="index.php">Accueil</a></li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li><a href="<?php echo $_SESSION['role'] === 'admin' ? 'admin_dashboard.php' : 'client_dashboard.php'; ?>">Dashboard</a></li>
                        <li><a href="logout.php">Déconnexion</a></li>
                        <li class="user-info">Bonjour, <?php echo htmlspecialchars($_SESSION['nom'] ?? 'Utilisateur'); ?></li>
                    <?php else: ?>
                        <li><a href="login.php">Connexion</a></li>
                        <li><a href="register.php">Inscription</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <main>
        <section class="hero">
            <div class="container">
                <div class="hero-content">
                    <?php if (isset($_GET['error'])): ?>
                        <div class="alert alert-error"><?php echo htmlspecialchars($_GET['error']); ?></div>
                    <?php endif; ?>
                    <h2>Transformez votre corps, transformez votre vie</h2>
                    <p>Découvrez notre gamme complète d'équipements de fitness et de compléments alimentaires pour atteindre vos objectifs.</p>
                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <div class="cta-buttons">
                            <a href="register.php" class="btn btn-primary">S'inscrire</a>
                            <a href="login.php" class="btn btn-secondary">Se connecter</a>
                        </div>
                    <?php else: ?>
                        <div class="cta-buttons">
                            <a href="<?php echo $_SESSION['role'] === 'admin' ? 'admin_dashboard.php' : 'client_dashboard.php'; ?>" class="btn btn-primary">Accéder au Dashboard</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="features">
            <div class="container">
                <h2>Pourquoi choisir FitZone ?</h2>
                <div class="features-grid">
                    <div class="feature-card">
                        <div class="feature-icon">🏋️</div>
                        <h3>Équipements de qualité</h3>
                        <p>Découvrez notre sélection d'équipements de fitness professionnels</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">💊</div>
                        <h3>Compléments alimentaires</h3>
                        <p>Boostez vos performances avec nos suppléments certifiés</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">🚚</div>
                        <h3>Livraison rapide</h3>
                        <p>Recevez vos commandes rapidement et en toute sécurité</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">🎯</div>
                        <h3>Conseils personnalisés</h3>
                        <p>Nos experts vous accompagnent dans votre parcours fitness</p>
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
                <div class="footer-section">
                    <h3>Suivez-nous</h3>
                    <p>Facebook | Instagram | YouTube</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2024 FitZone. Tous droits réservés.</p>
            </div>
        </div>
    </footer>
</body>
</html>