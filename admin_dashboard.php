<?php
require_once 'config.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php?error=Accès non autorisé");
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord Admin - FitZone</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="logo">
                <h1><a href="index.php">💪 FitZone</a></h1>
            </div>
            <nav class="nav">
                <ul>
                    <li><a href="index.php">Accueil</a></li>
                    <li><a href="logout.php">Déconnexion</a></li>
                    <li class="user-info">Bonjour, <?php echo htmlspecialchars($_SESSION['nom']); ?> (Admin)</li>
                </ul>
            </nav>
        </div>
    </header>

    <main>
        <section class="dashboard-section">
            <div class="container">
                <div class="dashboard-header">
                    <h2>Tableau de Bord Administrateur</h2>
                    <p>Gérez les produits, les commandes et les terrains</p>
                </div>

                <div class="dashboard-content">
                    <div class="actions-grid">
                        <div class="action-card">
                            <div class="action-icon">📦</div>
                            <h4>Gérer les Produits</h4>
                            <p>Ajouter, modifier ou supprimer des produits</p>
                            <a href="manage_products.php" class="btn btn-primary">Accéder</a>
                        </div>
                        <div class="action-card">
                            <div class="action-icon">📜</div>
                            <h4>Historique des Commandes</h4>
                            <p>Voir et gérer les commandes des clients</p>
                            <a href="manage_orders.php" class="btn btn-primary">Accéder</a>
                        </div>
                        <div class="action-card">
                            <div class="action-icon">🏟️</div>
                            <h4>Gérer les Terrains</h4>
                            <p>Ajouter, modifier ou supprimer des terrains</p>
                            <a href="manage_terrains.php" class="btn btn-primary">Accéder</a>
                        </div>
                        <div class="action-card">
                            <div class="action-icon">📅</div>
                            <h4>Gérer les Réservations</h4>
                            <p>Voir et confirmer les réservations des terrains</p>
                            <a href="manage_reservations.php" class="btn btn-primary">Accéder</a>
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