<?php
require_once 'config.php';

// Check if user is client
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    header("Location: index.php?error=Accès non autorisé");
    exit();
}

// Fetch client reservations
$conn = get_db_connection();
if (!$conn) {
    $error = "Erreur de connexion à la base de données.";
} else {
    $sql = "SELECT r.*, t.nom AS terrain_nom, t.image AS terrain_image 
            FROM reservations r 
            JOIN terrains t ON r.id_terrain = t.id 
            WHERE r.id_client = ? 
            ORDER BY r.date_creation DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $reservations = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Fetch client orders (using MySQLi for consistency)
    $sql = "SELECT c.id, c.statut, c.date_commande, c.produits 
            FROM commandes c 
            WHERE c.id_client = ? 
            ORDER BY c.date_commande DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $orders = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $conn->close();

    // Calculate total_price for each order
    foreach ($orders as &$order) {
        $items = json_decode($order['produits'], true);
        $total_price = 0;
        if (is_array($items)) {
            foreach ($items as $item) {
                if (isset($item['nom']) && isset($item['quantite']) && is_numeric($item['quantite'])) {
                    $price = 0;
                    if (isset($item['prix']) && is_numeric($item['prix'])) {
                        $price = floatval($item['prix']);
                    } elseif (isset($item['price']) && is_numeric($item['price'])) {
                        $price = floatval($item['price']);
                    } else {
                        $conn = get_db_connection();
                        if ($conn) {
                            $sql = "SELECT prix FROM produits WHERE nom = ?";
                            $price_stmt = $conn->prepare($sql);
                            $price_stmt->bind_param("s", $item['nom']);
                            $price_stmt->execute();
                            $price_result = $price_stmt->get_result();
                            if ($price_row = $price_result->fetch_assoc()) {
                                $price = floatval($price_row['prix']);
                            }
                            $price_stmt->close();
                            $conn->close();
                        }
                    }
                    $total_price += floatval($item['quantite']) * $price;
                }
            }
        }
        $order['total_price'] = $total_price;
    }
}

// Get and clear notification from session
$notification = isset($_SESSION['notification']) ? $_SESSION['notification'] : '';
unset($_SESSION['notification']);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord Client - FitZone</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        .dashboard-section {
            padding: 40px 0;
            background-color: #f8f9fa;
            min-height: calc(100vh - 80px);
        }
        .dashboard-header {
            text-align: center;
            margin-bottom: 40px;
            padding: 30px 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            color: white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .dashboard-header h2 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            font-weight: 700;
        }
        .dashboard-header p {
            font-size: 1.2rem;
            opacity: 0.9;
        }
        .notification {
            padding: 15px 20px;
            margin: 20px 0;
            border-radius: 8px;
            font-weight: 500;
            text-align: center;
            max-width: 100%;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            animation: fadeIn 0.5s ease-out;
        }
        .error {
            padding: 15px 20px;
            margin: 20px 0;
            border-radius: 8px;
            font-weight: 500;
            text-align: center;
            max-width: 100%;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .table-container {
            margin-top: 40px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .table-container h3 {
            color: #333;
            margin: 0;
            padding: 25px 30px;
            font-size: 1.5rem;
            font-weight: 600;
            background-color: #f8f9fa;
            border-bottom: 1px solid #e0e0e0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
            background-color: #fff;
        }
        th, td {
            padding: 20px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
        }
        th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: 600;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        tr:hover {
            background-color: #f0f4ff;
            transition: background-color 0.3s ease;
        }
        img {
            max-width: 100px;
            max-height: 70px;
            object-fit: cover;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.3);
        }
        .items-list {
            margin: 0;
            padding-left: 1.5rem;
        }
        @media (max-width: 768px) {
            .container {
                padding: 0 15px;
            }
            .dashboard-header h2 {
                font-size: 2rem;
            }
            table {
                font-size: 0.9rem;
            }
            th, td {
                padding: 12px 8px;
            }
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; display: none; }
        }
        .notification.fade-out {
            animation: fadeOut 0.5s ease-out forwards;
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
                    <li><a href="reserve_terrain.php">Réserver un Terrain</a></li>
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
                    <h2>Tableau de Bord Client</h2>
                    <p>Gérez vos réservations et commandes</p>
                </div>

                <?php if (isset($error)): ?>
                    <div class="error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if ($notification): ?>
                    <div id="notification" class="notification"><?php echo htmlspecialchars($notification); ?></div>
                <?php endif; ?>

                <!-- Reservations Section -->
                <div class="table-container">
                    <h3>🏟️ Mes Réservations</h3>
                    <?php if (empty($reservations)): ?>
                        <div style="padding: 40px; text-align: center; color: #666;">
                            <p style="font-size: 1.2rem;">Aucune réservation pour le moment.</p>
                            <a href="reserve_terrain.php" class="btn">Réserver un Terrain</a>
                        </div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Terrain</th>
                                    <th>Image</th>
                                    <th>Date</th>
                                    <th>Heure Début</th>
                                    <th>Heure Fin</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reservations as $reservation): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($reservation['terrain_nom']); ?></td>
                                        <td>
                                            <?php if ($reservation['terrain_image']): ?>
                                                <img src="<?php echo htmlspecialchars($reservation['terrain_image']); ?>" alt="<?php echo htmlspecialchars($reservation['terrain_nom']); ?>">
                                            <?php else: ?>
                                                Aucune image
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $reservation['date_reservation']; ?></td>
                                        <td><?php echo $reservation['heure_debut']; ?></td>
                                        <td><?php echo $reservation['heure_fin']; ?></td>
                                        <td><?php echo htmlspecialchars($reservation['statut']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- Orders Section -->
                <div class="table-container">
                    <h3>🛒 Mes Commandes</h3>
                    <?php if (empty($orders)): ?>
                        <div style="padding: 40px; text-align: center; color: #666;">
                            <p style="font-size: 1.2rem;">Aucune commande pour le moment.</p>
                            <a href="boutique.php" class="btn">Visiter la Boutique</a>
                        </div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Commande #</th>
                                    <th>Date</th>
                                    <th>Articles</th>
                                    <th>Total</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td><?php echo $order['id']; ?></td>
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
                                                            $conn = get_db_connection();
                                                            if ($conn) {
                                                                $sql = "SELECT prix FROM produits WHERE nom = ?";
                                                                $price_stmt = $conn->prepare($sql);
                                                                $price_stmt->bind_param("s", $item['nom']);
                                                                $price_stmt->execute();
                                                                $price_result = $price_stmt->get_result();
                                                                if ($price_row = $price_result->fetch_assoc()) {
                                                                    $price = floatval($price_row['prix']);
                                                                }
                                                                $price_stmt->close();
                                                                $conn->close();
                                                            }
                                                        }
                                                        ?>
                                                        <li><?php echo htmlspecialchars($item['nom']); ?> (<?php echo $quantity; ?> x <?php echo number_format($price, 2); ?> €)</li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo number_format($order['total_price'], 2); ?> €</td>
                                        <td><?php echo ucfirst(str_replace('_', ' ', $order['statut'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
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

    <script>
        // Make notification disappear after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const notification = document.getElementById('notification');
            if (notification) {
                setTimeout(() => {
                    notification.classList.add('fade-out');
                    setTimeout(() => {
                        notification.remove();
                    }, 500); // Match the fade-out animation duration
                }, 5000); // Display for 5 seconds
            }
        });
    </script>
</body>
</html>