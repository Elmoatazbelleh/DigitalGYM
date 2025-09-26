<?php
require_once 'config.php';

// Check if user is client
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    header("Location: index.php?error=Accès non autorisé");
    exit();
}

$error = '';
$success = '';

// Fetch cart items for the user's latest pending order
try {
    $stmt = $pdo->prepare("
        SELECT p.id AS product_id, p.nom, p.prix, p.stock, p.image, pa.quantite, c.id AS order_id
        FROM commandes c
        JOIN panier pa ON c.id = pa.id_commande
        JOIN produits p ON pa.id_produit = p.id
        WHERE c.id_client = ? AND c.statut = 'en_attente'
        ORDER BY c.date_commande DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate total
    $total = 0;
    foreach ($cart_items as $item) {
        $total += $item['prix'] * $item['quantite'];
    }
} catch (PDOException $e) {
    $error = "Erreur lors du chargement du panier : " . $e->getMessage();
}

// Handle item removal (restore stock when removing)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_item'])) {
    $product_id = $_POST['product_id'];
    
    $stmt = $pdo->prepare("SELECT id FROM commandes WHERE id_client = ? AND statut = 'en_attente' ORDER BY date_commande DESC LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $order_id = $stmt->fetchColumn();

    if ($order_id) {
        try {
            $pdo->beginTransaction();
            
            // Get quantity to restore to stock
            $stmt = $pdo->prepare("SELECT quantite FROM panier WHERE id_commande = ? AND id_produit = ?");
            $stmt->execute([$order_id, $product_id]);
            $quantity_to_restore = $stmt->fetchColumn();
            
            if ($quantity_to_restore) {
                // Remove item from cart
                $stmt = $pdo->prepare("DELETE FROM panier WHERE id_commande = ? AND id_produit = ?");
                $stmt->execute([$order_id, $product_id]);
                
                // Restore stock
                $stmt = $pdo->prepare("UPDATE produits SET stock = stock + ? WHERE id = ?");
                $stmt->execute([$quantity_to_restore, $product_id]);
                
                $pdo->commit();
                $success = "Article supprimé du panier et stock restauré";
            } else {
                $error = "Article non trouvé dans le panier";
                $pdo->rollBack();
            }
            
            header("Location: cart.php?success=" . urlencode($success ?: '') . "&error=" . urlencode($error ?: ''));
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Erreur lors de la suppression de l'article : " . $e->getMessage();
            header("Location: cart.php?error=" . urlencode($error));
            exit();
        }
    } else {
        $error = "Aucune commande en cours trouvée.";
        header("Location: cart.php?error=" . urlencode($error));
        exit();
    }
}

// Handle order confirmation (temporarily decrement stock and fill commandes.produits)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['validate_order'])) {
    $stmt = $pdo->prepare("SELECT id FROM commandes WHERE id_client = ? AND statut = 'en_attente' ORDER BY date_commande DESC LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $order_id = $stmt->fetchColumn();

    if ($order_id) {
        try {
            $pdo->beginTransaction();
            
            // Fetch all cart items to store in commandes.produits and check stock
            $stmt = $pdo->prepare("
                SELECT p.id, p.nom, p.prix, p.stock, pa.quantite
                FROM panier pa
                JOIN produits p ON pa.id_produit = p.id
                WHERE pa.id_commande = ?
            ");
            $stmt->execute([$order_id]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($items)) {
                $error = "Votre panier est vide. Ajoutez des produits avant de valider.";
                $pdo->rollBack();
                header("Location: cart.php?error=" . urlencode($error));
                exit();
            }
            
            // Check stock availability and temporarily decrement stock
            foreach ($items as $item) {
                if ($item['stock'] < $item['quantite']) {
                    $error = "Stock insuffisant pour le produit : " . htmlspecialchars($item['nom']);
                    $pdo->rollBack();
                    header("Location: cart.php?error=" . urlencode($error));
                    exit();
                }
                // Temporarily decrement stock
                $stmt = $pdo->prepare("UPDATE produits SET stock = stock - ? WHERE id = ?");
                $stmt->execute([$item['quantite'], $item['id']]);
            }
            
            // Prepare items for JSON storage (only nom and quantite)
            $produits = array_map(function($item) {
                return [
                    'nom' => $item['nom'],
                    'quantite' => $item['quantite']
                ];
            }, $items);
            $produits_json = json_encode($produits);
            
            // Update order status and store products in produits column
            $stmt = $pdo->prepare("UPDATE commandes SET statut = 'en_attente_validation', produits = ? WHERE id = ?");
            $stmt->execute([$produits_json, $order_id]);
            
            // Clear the cart (panier table)
            $stmt = $pdo->prepare("DELETE FROM panier WHERE id_commande = ?");
            $stmt->execute([$order_id]);
            
            $pdo->commit();
            $success = "Commande validée avec succès! En attente de validation par l'administrateur.";
            header("Location: cart.php?success=" . urlencode($success));
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Erreur lors de la validation de la commande : " . $e->getMessage();
            header("Location: cart.php?error=" . urlencode($error));
            exit();
        }
    } else {
        $error = "Aucune commande en attente trouvée.";
        header("Location: cart.php?error=" . urlencode($error));
        exit();
    }
}

// Handle URL parameters for messages
if (isset($_GET['error']) && !empty($_GET['error'])) {
    $error = $_GET['error'];
}
if (isset($_GET['success']) && !empty($_GET['success'])) {
    $success = $_GET['success'];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panier - FitZone</title>
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
                    <li><a href="client_dashboard.php">Tableau de Bord</a></li>
                    <li><a href="boutique.php">Boutique</a></li>
                    <li><a href="cart.php" class="active">Panier</a></li>
                    <li><a href="logout.php">Déconnexion</a></li>
                    <li class="user-info">Bonjour, <?php echo htmlspecialchars($_SESSION['nom']); ?> (Client)</li>
                </ul>
            </nav>
        </div>
    </header>

    <main>
        <section class="dashboard-section">
            <div class="container">
                <div class="dashboard-header">
                    <h2>Votre Panier</h2>
                    <p>Gérez les articles de votre panier</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <?php if (empty($cart_items)): ?>
                    <div class="empty-cart">
                        <p>Votre panier est vide.</p>
                        <a href="boutique.php" class="btn btn-primary">Parcourir les produits</a>
                    </div>
                <?php else: ?>
                    <div class="products-grid">
                        <?php foreach ($cart_items as $item): ?>
                            <div class="product-card">
                                <?php if ($item['image']): ?>
                                    <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['nom']); ?>" class="product-image">
                                <?php else: ?>
                                    <div class="product-placeholder">Pas d'image</div>
                                <?php endif; ?>
                                <h3><?php echo htmlspecialchars($item['nom']); ?></h3>
                                <p>Prix unitaire: <?php echo number_format($item['prix'], 2); ?> €</p>
                                <p>Quantité: <?php echo $item['quantite']; ?></p>
                                <p>Stock restant: <?php echo $item['stock']; ?></p>
                                <p class="subtotal">Sous-total: <?php echo number_format($item['prix'] * $item['quantite'], 2); ?> €</p>
                                <form method="POST" action="">
                                    <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                    <button type="submit" name="remove_item" class="btn btn-secondary" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet article?')">Supprimer</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="cart-summary">
                        <div class="cart-total">
                            <h3>Total: <?php echo number_format($total, 2); ?> €</h3>
                            <p>Nombre d'articles: <?php echo count($cart_items); ?></p>
                        </div>
                        <form method="POST" action="">
                            <button type="submit" name="validate_order" class="btn btn-primary btn-full" onclick="return confirm('Confirmer la validation de votre commande?')">
                                Valider la commande
                            </button>
                        </form>
                        <div class="cart-actions" style="margin-top: 1rem;">
                            <a href="boutique.php" class="btn btn-secondary">Continuer les achats</a>
                        </div>
                    </div>
                <?php endif; ?>
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
                <p>© 2024 FitZone. Tous droits réservés.</p>
            </div>
        </div>
    </footer>
</body>
</html>