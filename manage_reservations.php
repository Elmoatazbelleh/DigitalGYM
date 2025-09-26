<?php
require_once 'config.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php?error=Accès non autorisé");
    exit();
}

// Handle reservation status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $id = (int)$_POST['id'];
    $statut = $_POST['statut'];
    $conn = get_db_connection();
    if (!$conn) {
        $error = "Erreur de connexion à la base de données.";
    } else {
        try {
            $conn->begin_transaction();

            // Fetch client ID and terrain name for notification
            $sql = "SELECT r.id_client, t.nom AS terrain_nom FROM reservations r JOIN terrains t ON r.id_terrain = t.id WHERE r.id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $reservation = $result->fetch_assoc();
            $stmt->close();

            if (!$reservation) {
                throw new Exception("Réservation non trouvée.");
            }

            // Update reservation status
            $sql = "UPDATE reservations SET statut = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("si", $statut, $id);
                if ($stmt->execute()) {
                    // Store notification in session for the client
                    $_SESSION['notification'] = "Votre réservation pour le terrain '" . htmlspecialchars($reservation['terrain_nom']) . "' a été " . ($statut === 'confirmee' ? 'confirmée' : 'refusée') . ".";
                    $conn->commit();
                    header("Location: manage_reservations.php?success=Statut mis à jour");
                    exit();
                } else {
                    throw new Exception("Erreur lors de la mise à jour du statut.");
                }
                $stmt->close();
            } else {
                throw new Exception("Erreur de préparation de la requête.");
            }
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
            header("Location: manage_reservations.php?error=" . urlencode($error));
            exit();
        }
        $conn->close();
    }
}

// Fetch all reservations with terrain and client info
$conn = get_db_connection();
if (!$conn) {
    $error = "Erreur de connexion à la base de données.";
} else {
    $sql = "SELECT r.*, t.nom AS terrain_nom, t.image AS terrain_image, u.nom AS client_nom 
            FROM reservations r 
            JOIN terrains t ON r.id_terrain = t.id 
            JOIN users u ON r.id_client = u.id 
            ORDER BY r.date_creation DESC";
    $result = $conn->query($sql);
    $reservations = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gérer les Réservations - FitZone</title>
    <link rel="stylesheet" href="style.css">
    <style>
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
        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            padding: 15px 20px;
            margin: 20px 0;
            border-radius: 8px;
            text-align: center;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            padding: 15px 20px;
            margin: 20px 0;
            border-radius: 8px;
            text-align: center;
        }
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            border: none;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.3);
        }
        select {
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
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
                    <li><a href="admin_dashboard.php">Retour au Tableau de Bord</a></li>
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
                    <h2>Gérer les Réservations</h2>
                    <p>Voir et gérer les réservations des clients</p>
                </div>

                <?php if (isset($_GET['success'])): ?>
                    <p class="success"><?php echo htmlspecialchars($_GET['success']); ?></p>
                <?php elseif (isset($_GET['error'])): ?>
                    <p class="error"><?php echo htmlspecialchars($_GET['error']); ?></p>
                <?php endif; ?>

                <div class="table-container">
                    <h3>Liste des Réservations</h3>
                    <?php if (empty($reservations)): ?>
                        <div style="padding: 40px; text-align: center; color: #666;">
                            <p style="font-size: 1.2rem;">Aucune réservation disponible pour le moment.</p>
                        </div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Client</th>
                                    <th>Terrain</th>
                                    <th>Image</th>
                                    <th>Date</th>
                                    <th>Heure Début</th>
                                    <th>Heure Fin</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reservations as $reservation): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($reservation['client_nom']); ?></td>
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
                                        <td>
                                            <?php if ($reservation['statut'] === 'en_attente'): ?>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="action" value="update">
                                                    <input type="hidden" name="id" value="<?php echo $reservation['id']; ?>">
                                                    <select name="statut">
                                                        <option value="confirmee">Confirmer</option>
                                                        <option value="refusee">Refuser</option>
                                                    </select>
                                                    <button type="submit" class="btn btn-primary">Mettre à jour</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
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
</body>
</html>