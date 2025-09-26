<?php
require_once 'config.php';

// Check if user is client
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    header("Location: index.php?error=Accès non autorisé");
    exit();
}

$error = '';
$success = '';

// Fetch products with available stock (considering items already in cart)
try {
    $stmt = $pdo->prepare("
        SELECT p.*, 
               COALESCE(p.stock - COALESCE(SUM(pan.quantite), 0), p.stock) as stock_disponible
        FROM produits p 
        LEFT JOIN panier pan ON p.id = pan.id_produit 
        LEFT JOIN commandes c ON pan.id_commande = c.id 
                              AND c.id_client = ? 
                              AND c.statut = 'en_attente'
        GROUP BY p.id 
        ORDER BY p.date_ajout DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Erreur lors du chargement des produits : " . $e->getMessage();
}

// Handle add to cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $product_id = $_POST['product_id'];
    $quantity = intval($_POST['quantity']);
    
    try {
        $pdo->beginTransaction();
        
        // Get product info and calculate available stock
        $stmt = $pdo->prepare("
            SELECT p.*, 
                   COALESCE(p.stock - COALESCE(SUM(pan.quantite), 0), p.stock) as stock_disponible
            FROM produits p 
            LEFT JOIN panier pan ON p.id = pan.id_produit 
            LEFT JOIN commandes c ON pan.id_commande = c.id 
                                  AND c.id_client = ? 
                                  AND c.statut = 'en_attente'
            WHERE p.id = ?
            GROUP BY p.id
        ");
        $stmt->execute([$_SESSION['user_id'], $product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            throw new Exception("Produit introuvable");
        }

        if ($quantity <= 0) {
            throw new Exception("Quantité invalide");
        }

        if ($quantity > $product['stock_disponible']) {
            throw new Exception("Stock insuffisant. Stock disponible : " . $product['stock_disponible']);
        }

        // Create new order if none exists
        $stmt = $pdo->prepare("SELECT id FROM commandes WHERE id_client = ? AND statut = 'en_attente' ORDER BY date_commande DESC LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $order_id = $stmt->fetchColumn();
        
        if (!$order_id) {
            $stmt = $pdo->prepare("INSERT INTO commandes (id_client, statut) VALUES (?, 'en_attente')");
            $stmt->execute([$_SESSION['user_id']]);
            $order_id = $pdo->lastInsertId();
        }
        
        // Check if product already in cart
        $stmt = $pdo->prepare("SELECT quantite FROM panier WHERE id_commande = ? AND id_produit = ?");
        $stmt->execute([$order_id, $product_id]);
        $existing_item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing_item) {
            // Update existing item
            $new_quantity = $existing_item['quantite'] + $quantity;
            
            // Double-check stock availability for the new total quantity
            if ($new_quantity > $product['stock']) {
                throw new Exception("Quantité totale demandée (" . $new_quantity . ") dépasse le stock disponible (" . $product['stock'] . ")");
            }
            
            $stmt = $pdo->prepare("UPDATE panier SET quantite = ? WHERE id_commande = ? AND id_produit = ?");
            $stmt->execute([$new_quantity, $order_id, $product_id]);
            
            $success = "Quantité mise à jour dans le panier (Total: " . $new_quantity . ")";
        } else {
            // Add new item to cart
            $stmt = $pdo->prepare("INSERT INTO panier (id_commande, id_produit, quantite) VALUES (?, ?, ?)");
            $stmt->execute([$order_id, $product_id, $quantity]);
            
            $success = "Produit ajouté au panier avec succès";
        }
        
        $pdo->commit();
        
        // Redirect with success message
        header("Location: boutique.php?success=" . urlencode($success));
        exit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
        header("Location: boutique.php?error=" . urlencode($error));
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Erreur lors de l'ajout au panier : " . $e->getMessage();
        header("Location: boutique.php?error=" . urlencode($error));
        exit();
    }
}

// Handle URL parameters for messages
if (isset($_GET['error'])) {
    $error = $_GET['error'];
}
if (isset($_GET['success'])) {
    $success = $_GET['success'];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Boutique - FitZone</title>
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
                    <li><a href="cart.php">Panier</a></li>
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
                    <h2>Boutique</h2>
                    <p>Découvrez nos produits et ajoutez-les à votre panier</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <div class="products-grid">
                    <?php foreach ($products as $product): ?>
                        <div class="product-card">
                            <?php if ($product['image']): ?>
                                <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['nom']); ?>" class="product-image">
                            <?php else: ?>
                                <div class="product-placeholder">Pas d'image</div>
                            <?php endif; ?>
                            <h3><?php echo htmlspecialchars($product['nom']); ?></h3>
                            <p><?php echo htmlspecialchars($product['description']); ?></p>
                            <p class="price"><?php echo number_format($product['prix'], 2); ?> €</p>
                            <p class="stock">
                                Stock total: <?php echo $product['stock']; ?> | 
                                Stock disponible: <span class="<?php echo $product['stock_disponible'] <= 0 ? 'text-danger' : 'text-success'; ?>">
                                    <?php echo $product['stock_disponible']; ?>
                                </span>
                            </p>
                            
                            <?php if ($product['stock_disponible'] > 0): ?>
                                <form method="POST" action="">
                                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                    <div class="form-group">
                                        <label for="quantity_<?php echo $product['id']; ?>">Quantité:</label>
                                        <input type="number" 
                                               id="quantity_<?php echo $product['id']; ?>" 
                                               name="quantity" 
                                               min="1" 
                                               max="<?php echo $product['stock_disponible']; ?>" 
                                               value="1" 
                                               required>
                                        <small class="text-muted">Maximum: <?php echo $product['stock_disponible']; ?></small>
                                    </div>
                                    <button type="submit" name="add_to_cart" class="btn btn-primary">Ajouter au panier</button>
                                </form>
                            <?php else: ?>
                                <p class="out-of-stock">Stock épuisé ou déjà dans votre panier</p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if (empty($products)): ?>
                    <div class="alert alert-info">
                        <p>Aucun produit disponible pour le moment.</p>
                    </div>
                <?php endif; ?>
                
                <div class="cta-buttons" style="margin-top: 2rem;">
                    <a href="cart.php" class="btn btn-primary btn-full">Aller au Panier</a>
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
                <p>© 2024 FitZone. Tous droits réservés.</p>
            </div>
        </div>
    </footer>

    <style>
    .text-danger {
        color: #dc3545;
        font-weight: bold;
    }
    .text-success {
        color: #28a745;
        font-weight: bold;
    }
    .text-muted {
        color: #6c757d;
        font-size: 0.9em;
    }
    .out-of-stock {
        color: #dc3545;
        font-weight: bold;
        text-align: center;
        padding: 10px;
        background-color: #f8d7da;
        border-radius: 5px;
    }
    </style>
</body>
</html>