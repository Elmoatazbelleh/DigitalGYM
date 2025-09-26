<?php
require_once 'config.php';

// Check if user is client
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    header("Location: index.php?error=Accès non autorisé");
    exit();
}

$error = '';

// Function to calculate total price with proper error handling
function calculateTotalPrice($produits_json) {
    global $pdo;
    
    $items = json_decode($produits_json, true);
    $total_price = 0;
    
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($items)) {
        return 0;
    }
    
    foreach ($items as $item) {
        if (!is_array($item) || !isset($item['nom'])) continue;
        
        $quantity = 0;
        $price = 0;
        
        // Get quantity
        if (isset($item['quantite']) && is_numeric($item['quantite'])) {
            $quantity = floatval($item['quantite']);
        }
        
        // Get price - check multiple possible keys
        if (isset($item['prix']) && is_numeric($item['prix'])) {
            $price = floatval($item['prix']);
        } elseif (isset($item['price']) && is_numeric($item['price'])) {
            $price = floatval($item['price']);
        } else {
            // If no price in item, get from database
            try {
                $stmt = $pdo->prepare("SELECT prix FROM produits WHERE nom = ?");
                $stmt->execute([$item['nom']]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($result) {
                    $price = floatval($result['prix']);
                }
            } catch (PDOException $e) {
                // Continue with price = 0 if database error
            }
        }
        
        if ($quantity > 0 && $price > 0) {
            $total_price += $quantity * $price;
        }
    }
    
    return $total_price;
}

// Fetch orders for the current client
try {
    $user_id = $_SESSION['user_id'];
    $sort_by = $_GET['sort_by'] ?? 'date_desc';
    $order_by = $sort_by === 'date_asc' ? 'c.date_commande ASC' : 'c.date_commande DESC';

    $stmt = $pdo->prepare("
        SELECT c.date_commande, c.statut, c.produits
        FROM commandes c
        WHERE c.id_client = ?
        ORDER BY $order_by
    ");
    $stmt->execute([$user_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate total_price for each order in PHP
    foreach ($orders as &$order) {
        $order['total_price'] = calculateTotalPrice($order['produits']);
    }
} catch (PDOException $e) {
    $error = "Erreur lors du chargement de l'historique : " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique des Commandes - FitZone</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .orders-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        .orders-table th, .orders-table td {
            border: 1px solid #ddd;
            padding: 0.75rem;
            text-align: left;
        }
        .orders-table th {
            background-color: #f4f4f4;
            font-weight: bold;
        }
        .orders-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .orders-table .items-list {
            margin: 0;
            padding-left: 1.5rem;
        }
        .sort-options {
            margin-bottom: 1rem;
        }
    </style>
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
                    <li><a href="boutique.php">Boutique</a></li>
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
                    <h2>Historique des Commandes</h2>
                    <p>Consultez l'historique de vos commandes</p>
                    <div class="sort-options">
                        <label for="sort_by">Trier par date :</label>
                        <select id="sort_by" name="sort_by" onchange="window.location.href='?sort_by=' + this.value">
                            <option value="date_desc" <?php echo $sort_by === 'date_desc' ? 'selected' : ''; ?>>Décroissant</option>
                            <option value="date_asc" <?php echo $sort_by === 'date_asc' ? 'selected' : ''; ?>>Croissant</option>
                        </select>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <?php if (empty($orders)): ?>
                    <p>Aucune commande trouvée.</p>
                <?php else: ?>
                    <table class="orders-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Produits</th>
                                <th>Total</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y H:i', strtotime($order['date_commande'])); ?></td>
                                    <td>
                                        <?php
                                        $items = json_decode($order['produits'], true);
                                        if (empty($items) || !is_array($items)): ?>
                                            <p>Aucun article.</p>
                                        <?php else: ?>
                                            <ul class="items-list">
                                                <?php foreach ($items as $item): ?>
                                                    <?php
                                                    $quantity = isset($item['quantite']) && is_numeric($item['quantite']) ? floatval($item['quantite']) : 0;
                                                    $price = 0;
                                                    if (isset($item['prix']) && is_numeric($item['prix'])) {
                                                        $price = floatval($item['prix']);
                                                    } elseif (isset($item['price']) && is_numeric($item['price'])) {
                                                        $price = floatval($item['price']);
                                                    } else {
                                                        try {
                                                            $stmt = $pdo->prepare("SELECT prix FROM produits WHERE nom = ?");
                                                            $stmt->execute([$item['nom']]);
                                                            $result = $stmt->fetch(PDO::FETCH_ASSOC);
                                                            if ($result) {
                                                                $price = floatval($result['prix']);
                                                            }
                                                        } catch (PDOException $e) {
                                                            // Keep price as 0 if error
                                                        }
                                                    }
                                                    ?>
                                                    <li><?php echo htmlspecialchars($item['nom']); ?> (<?php echo $quantity; ?> x <?php echo number_format($price, 2); ?> €)</li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo number_format($order['total_price'], 2); ?> €</td>
                                    <td>
                                        <span class="role-badge role-<?php echo $order['statut']; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $order['statut'])); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
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