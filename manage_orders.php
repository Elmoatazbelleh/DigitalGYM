<?php
require_once 'config.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php?error=Accès non autorisé");
    exit();
}

$error = '';
$success = '';

// Pagination logic
$limit = 10; // Orders per page
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Sorting logic
$sort_by = $_GET['sort_by'] ?? 'date_desc';
$order_by = 'c.date_commande DESC';
if ($sort_by === 'date_asc') {
    $order_by = 'c.date_commande ASC';
} elseif ($sort_by === 'price_desc') {
    $order_by = 'total_price DESC';
} elseif ($sort_by === 'price_asc') {
    $order_by = 'total_price ASC';
}

// Function to calculate total price with proper error handling
function calculateTotalPrice($produits_json) {
    $items = json_decode($produits_json, true);
    $total_price = 0;
    
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($items)) {
        return 0;
    }
    
    foreach ($items as $item) {
        if (!is_array($item)) continue;
        
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
            global $pdo;
            if (isset($item['nom'])) {
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
        }
        
        if ($quantity > 0 && $price > 0) {
            $total_price += $quantity * $price;
        }
    }
    
    return $total_price;
}

// Fetch total number of orders for pagination
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM commandes");
    $total_orders = $stmt->fetchColumn();
    $total_pages = ceil($total_orders / $limit);
} catch (PDOException $e) {
    $error = "Erreur lors du comptage des commandes : " . $e->getMessage();
}

// Fetch orders with user information
try {
    $query = "
        SELECT c.id, c.id_client, c.statut, c.date_commande, c.produits, u.nom, u.email
        FROM commandes c 
        JOIN users u ON c.id_client = u.id 
        ORDER BY $order_by
        LIMIT " . intval($limit) . " OFFSET " . intval($offset);
    
    $stmt = $pdo->query($query);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate total_price for each order in PHP
    foreach ($orders as &$order) {
        $order['total_price'] = calculateTotalPrice($order['produits']);
    }

    // Sort orders by total_price if required
    if ($sort_by === 'price_desc' || $sort_by === 'price_asc') {
        usort($orders, function ($a, $b) use ($sort_by) {
            return $sort_by === 'price_desc' 
                ? $b['total_price'] <=> $a['total_price']
                : $a['total_price'] <=> $b['total_price'];
        });
    }
} catch (PDOException $e) {
    $error = "Erreur lors du chargement des commandes : " . $e->getMessage();
}

// Handle order status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order'])) {
    $order_id = $_POST['order_id'];
    $new_status = $_POST['status'];

    if (!in_array($new_status, ['en_attente', 'approuvee', 'refusee'])) {
        $error = "Statut invalide";
    } else {
        try {
            $pdo->beginTransaction();

            // Fetch current order status, products, and client info
            $stmt = $pdo->prepare("SELECT c.statut, c.produits, c.id_client, u.nom FROM commandes c JOIN users u ON c.id_client = u.id WHERE c.id = ?");
            $stmt->execute([$order_id]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            $current_status = $order['statut'];
            $produits = json_decode($order['produits'], true);
            $id_client = $order['id_client'];

            // If status is changing to 'approuvee' and was not previously 'approuvee', decrement stock
            if ($new_status === 'approuvee' && $current_status !== 'approuvee' && !empty($produits)) {
                foreach ($produits as $item) {
                    if (isset($item['nom']) && isset($item['quantite']) && is_numeric($item['quantite'])) {
                        $stmt = $pdo->prepare("UPDATE produits SET stock = stock - ? WHERE nom = ? AND stock >= ?");
                        $stmt->execute([$item['quantite'], $item['nom'], $item['quantite']]);
                        if ($stmt->rowCount() === 0) {
                            throw new PDOException("Stock insuffisant pour le produit : " . $item['nom']);
                        }
                    }
                }
            }

            // If status is changing to 'refusee' and was not previously 'refusee', restore stock
            if ($new_status === 'refusee' && $current_status !== 'refusee' && !empty($produits)) {
                foreach ($produits as $item) {
                    if (isset($item['nom']) && isset($item['quantite']) && is_numeric($item['quantite'])) {
                        $stmt = $pdo->prepare("UPDATE produits SET stock = stock + ? WHERE nom = ?");
                        $stmt->execute([$item['quantite'], $item['nom']]);
                    }
                }
            }

            // Update order status
            $stmt = $pdo->prepare("UPDATE commandes SET statut = ? WHERE id = ?");
            $stmt->execute([$new_status, $order_id]);

            // Store notification in session for the client
            $_SESSION['notification'] = "Votre commande #$order_id a été " . ($new_status === 'approuvee' ? 'approuvée' : 'refusée') . ".";

            $pdo->commit();
            $success = "Statut de la commande mis à jour avec succès";
            header("Location: manage_orders.php?page=$page&sort_by=$sort_by&success=" . urlencode($success));
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Erreur lors de la mise à jour de la commande : " . $e->getMessage();
        }
    }
}

// Handle order deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_order'])) {
    $order_id = $_POST['order_id'];
    try {
        $pdo->beginTransaction();
        
        // Fetch products, status, and client info to restore stock if order is not already refused
        $stmt = $pdo->prepare("SELECT c.statut, c.produits, c.id_client FROM commandes c WHERE c.id = ?");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        $produits = json_decode($order['produits'], true);
        $id_client = $order['id_client'];

        if ($order['statut'] !== 'refusee' && !empty($produits)) {
            foreach ($produits as $item) {
                if (isset($item['nom']) && isset($item['quantite']) && is_numeric($item['quantite'])) {
                    $stmt = $pdo->prepare("UPDATE produits SET stock = stock + ? WHERE nom = ?");
                    $stmt->execute([$item['quantite'], $item['nom']]);
                }
            }
        }

        // Delete order
        $stmt = $pdo->prepare("DELETE FROM commandes WHERE id = ?");
        $stmt->execute([$order_id]);

        // Store notification in session for the client
        $_SESSION['notification'] = "Votre commande #$order_id a été annulée par l'administrateur.";

        $pdo->commit();
        $success = "Commande supprimée avec succès";
        header("Location: manage_orders.php?page=$page&sort_by=$sort_by&success=" . urlencode($success));
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Erreur lors de la suppression de la commande : " . $e->getMessage();
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
    <title>Gérer les Commandes - FitZone</title>
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
        .pagination {
            margin-top: 1rem;
            text-align: center;
        }
        .pagination a {
            margin: 0 0.5rem;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .pagination a.active {
            background-color: #007bff;
            color: white;
            border-color: #007bff;
        }
        .pagination a:hover {
            background-color: #e9ecef;
        }
        .form-inline {
            display: inline-flex;
            gap: 0.5rem;
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
                    <li><a href="admin_dashboard.php">Tableau de Bord</a></li>
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
                    <h2>Gérer les Commandes</h2>
                    <p>Visualisez, mettez à jour et supprimez les commandes</p>
                    <div class="sort-options">
                        <label for="sort_by">Trier par :</label>
                        <select id="sort_by" name="sort_by" onchange="window.location.href='?page=<?php echo $page; ?>&sort_by=' + this.value">
                            <option value="date_desc" <?php echo $sort_by === 'date_desc' ? 'selected' : ''; ?>>Date (Décroissant)</option>
                            <option value="date_asc" <?php echo $sort_by === 'date_asc' ? 'selected' : ''; ?>>Date (Croissant)</option>
                            <option value="price_desc" <?php echo $sort_by === 'price_desc' ? 'selected' : ''; ?>>Prix (Décroissant)</option>
                            <option value="price_asc" <?php echo $sort_by === 'price_asc' ? 'selected' : ''; ?>>Prix (Croissant)</option>
                        </select>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <?php if (empty($orders)): ?>
                    <p>Aucune commande trouvée.</p>
                <?php else: ?>
                    <table class="orders-table">
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>Date</th>
                                <th>Articles</th>
                                <th>Total</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($order['nom']); ?><br>(<?php echo htmlspecialchars($order['email']); ?>)</td>
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
                                    <td>
                                        <form method="POST" action="" class="form-inline">
                                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                            <select name="status" required>
                                                <option value="en_attente" <?php echo $order['statut'] === 'en_attente' ? 'selected' : ''; ?>>En attente</option>
                                                <option value="approuvee" <?php echo $order['statut'] === 'approuvee' ? 'selected' : ''; ?>>Approuvée</option>
                                                <option value="refusee" <?php echo $order['statut'] === 'refusee' ? 'selected' : ''; ?>>Refusée</option>
                                            </select>
                                            <button type="submit" name="update_order" class="btn btn-primary">Mettre à jour</button>
                                        </form>
                                        <form method="POST" action="" style="margin-top: 0.5rem;">
                                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                            <button type="submit" name="delete_order" class="btn btn-secondary" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette commande ?');">Supprimer</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&sort_by=<?php echo $sort_by; ?>">&laquo; Précédent</a>
                        <?php endif; ?>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>&sort_by=<?php echo $sort_by; ?>" class="<?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&sort_by=<?php echo $sort_by; ?>">Suivant &raquo;</a>
                        <?php endif; ?>
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