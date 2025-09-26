<?php
require_once 'config.php';

// Check if user is client
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    header("Location: index.php?error=Accès non autorisé");
    exit();
}

// Fetch available terrains
$conn = get_db_connection();
if (!$conn) {
    $error = "Erreur de connexion à la base de données. Veuillez réessayer plus tard.";
} else {
    $sql = "SELECT id, nom, type, periode_reservation, 
                   TIME_FORMAT(heure_debut, '%H:%i') as heure_debut, 
                   TIME_FORMAT(heure_fin, '%H:%i') as heure_fin, 
                   image 
            FROM terrains WHERE disponibilite = 'disponible'";
    $result = $conn->query($sql);
    $terrains = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// Handle reservation form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_terrain = (int)$_POST['id_terrain'];
    $date_reservation = $_POST['date_reservation'];
    $heure_debut = $_POST['heure_debut'];
    $periode = (int)$_POST['periode_reservation'];

    // Validate inputs
    if ($periode <= 0) {
        $error = "La période de réservation doit être supérieure à zéro.";
    } else {
        // Calculate end time based on period
        $start_time = new DateTime($heure_debut);
        $start_time->modify("+$periode minutes");
        $heure_fin = $start_time->format('H:i');

        // Validate time slot
        $sql = "SELECT TIME_FORMAT(heure_debut, '%H:%i') as heure_debut, 
                       TIME_FORMAT(heure_fin, '%H:%i') as heure_fin 
                FROM terrains WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $id_terrain);
            $stmt->execute();
            $result = $stmt->get_result();
            $terrain = $result->fetch_assoc();
            $stmt->close();

            if (!$terrain) {
                $error = "Terrain sélectionné non valide.";
            } elseif ($heure_debut < $terrain['heure_debut'] || $heure_fin > $terrain['heure_fin']) {
                $error = "Le créneau horaire sélectionné n'est pas disponible (hors des horaires d'ouverture du terrain).";
            } else {
                // Check for overlapping reservations (confirmed or pending)
                $sql = "SELECT * FROM reservations 
                        WHERE id_terrain = ? AND date_reservation = ? 
                        AND (statut = 'confirmee' OR statut = 'en_attente')
                        AND ((heure_debut <= ? AND heure_fin > ?) OR (heure_debut < ? AND heure_fin >= ?))";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("isssss", $id_terrain, $date_reservation, $heure_debut, $heure_debut, $heure_fin, $heure_fin);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->num_rows > 0) {
                        $error = "Le créneau horaire sélectionné est déjà réservé (en attente ou confirmé). Veuillez choisir un autre créneau.";
                    } else {
                        // Insert reservation
                        $id_client = $_SESSION['user_id'];
                        $sql = "INSERT INTO reservations (id_client, id_terrain, date_reservation, heure_debut, heure_fin, statut) 
                                VALUES (?, ?, ?, ?, ?, 'en_attente')";
                        $stmt = $conn->prepare($sql);
                        if ($stmt) {
                            $stmt->bind_param("iisss", $id_client, $id_terrain, $date_reservation, $heure_debut, $heure_fin);
                            if ($stmt->execute()) {
                                header("Location: reserve_terrain.php?success=Votre réservation a été envoyée à l'administrateur pour confirmation.");
                                exit();
                            } else {
                                $error = "Erreur lors de l'enregistrement de la réservation. Veuillez réessayer.";
                            }
                            $stmt->close();
                        } else {
                            $error = "Erreur de préparation de la requête d'insertion.";
                        }
                    }
                    $stmt->close();
                } else {
                    $error = "Erreur de préparation de la requête de vérification.";
                }
            }
        } else {
            $error = "Erreur de préparation de la requête de validation.";
        }
    }
    if ($conn) {
        $conn->close();
    }
}

// Type icons mapping
$type_icons = [
    'football' => '⚽',
    'tennis' => '🎾',
    'natation' => '🏊‍♂️',
    'volleyball' => '🏐',
    'basketball' => '🏀'
];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réserver un Terrain - FitZone</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Container principal */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Section dashboard */
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

        /* Messages de succès et d'erreur */
        .success, .error {
            padding: 15px 20px;
            margin: 20px 0;
            border-radius: 8px;
            font-weight: 500;
            text-align: center;
            max-width: 100%;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Formulaire de réservation */
        .form-container {
            margin-bottom: 50px;
            padding: 30px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #e0e0e0;
        }

        .form-container h3 {
            color: #333;
            margin-bottom: 25px;
            font-size: 1.5rem;
            font-weight: 600;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }

        .form-row {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            flex: 1;
            min-width: 250px;
        }

        .form-container label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
            font-size: 0.95rem;
        }

        .form-container select,
        .form-container input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background-color: #fff;
        }

        .form-container select:focus,
        .form-container input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-container button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 20px;
            min-width: 200px;
        }

        .form-container button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.3);
        }

        /* Table des terrains */
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

        .terrains-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
            background-color: #fff;
        }

        .terrains-table th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 18px 20px;
            text-align: left;
            font-weight: 600;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .terrains-table td {
            padding: 20px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
        }

        .terrains-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .terrains-table tr:hover {
            background-color: #f0f4ff;
            transition: background-color 0.3s ease;
        }

        .terrain-type {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
        }

        .terrain-type-icon {
            font-size: 1.2rem;
        }

        .terrain-schedule {
            color: #666;
            font-weight: 500;
        }

        .terrain-period {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            text-align: center;
            min-width: 80px;
            display: inline-block;
        }

        .terrain-image {
            text-align: center;
        }

        .terrain-image img {
            max-width: 100px;
            max-height: 70px;
            object-fit: cover;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .terrain-image img:hover {
            transform: scale(1.05);
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
            }
            
            .form-group {
                min-width: 100%;
            }
            
            .dashboard-header h2 {
                font-size: 2rem;
            }
            
            .terrains-table {
                font-size: 0.9rem;
            }
            
            .terrains-table th,
            .terrains-table td {
                padding: 12px 8px;
            }
            
            .container {
                padding: 0 15px;
            }
        }

        /* Animation pour les éléments */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .form-container,
        .table-container {
            animation: fadeIn 0.6s ease-out;
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
                    <li><a href="client_dashboard.php">Retour au Tableau de Bord</a></li>
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
                    <h2>Réserver un Terrain</h2>
                    <p>Choisissez un terrain et un créneau horaire pour votre réservation</p>
                </div>

                <?php if (isset($_GET['success'])): ?>
                    <div class="success"><?php echo htmlspecialchars($_GET['success']); ?></div>
                <?php elseif (isset($error)): ?>
                    <div class="error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <div class="form-container">
                    <h3>📋 Formulaire de Réservation</h3>
                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="id_terrain">🏟️ Terrain :</label>
                                <select name="id_terrain" id="id_terrain" required onchange="updatePeriod(this)">
                                    <option value="">Sélectionner un terrain</option>
                                    <?php foreach ($terrains as $terrain): ?>
                                        <option value="<?php echo $terrain['id']; ?>" 
                                                data-periode="<?php echo $terrain['periode_reservation']; ?>">
                                            <?php echo htmlspecialchars($terrain['nom']) . " (" . ($type_icons[$terrain['type']] ?? '🏟️') . " " . ucfirst($terrain['type']) . ")"; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="date_reservation">📅 Date :</label>
                                <input type="date" name="date_reservation" id="date_reservation" required min="<?php echo date('Y-m-d'); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="heure_debut">⏰ Heure de début :</label>
                                <input type="time" name="heure_debut" id="heure_debut" required step="300">
                            </div>
                        </div>
                        
                        <input type="hidden" name="periode_reservation" id="periode_reservation">
                        <button type="submit">✅ Réserver</button>
                    </form>
                </div>

                <div class="table-container">
                    <h3>🏟️ Terrains Disponibles</h3>
                    <?php if (empty($terrains)): ?>
                        <div style="padding: 40px; text-align: center; color: #666;">
                            <p style="font-size: 1.2rem;">Aucun terrain disponible pour le moment.</p>
                        </div>
                    <?php else: ?>
                        <table class="terrains-table">
                            <thead>
                                <tr>
                                    <th>Nom du Terrain</th>
                                    <th>Type de Sport</th>
                                    <th>Période (min)</th>
                                    <th>Heure d'Ouverture</th>
                                    <th>Heure de Fermeture</th>
                                    <th>Aperçu</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($terrains as $terrain): ?>
                                    <tr>
                                        <td style="font-weight: 600; color: #333;">
                                            <?php echo htmlspecialchars($terrain['nom']); ?>
                                        </td>
                                        <td>
                                            <div class="terrain-type">
                                                <span class="terrain-type-icon"><?php echo $type_icons[$terrain['type']] ?? '🏟️'; ?></span>
                                                <span><?php echo ucfirst($terrain['type']); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="terrain-period"><?php echo $terrain['periode_reservation']; ?> min</span>
                                        </td>
                                        <td class="terrain-schedule">
                                            <?php echo $terrain['heure_debut']; ?>
                                        </td>
                                        <td class="terrain-schedule">
                                            <?php echo $terrain['heure_fin']; ?>
                                        </td>
                                        <td class="terrain-image">
                                            <?php if (!empty($terrain['image'])): ?>
                                                <img src="<?php echo htmlspecialchars($terrain['image']); ?>" 
                                                     alt="<?php echo htmlspecialchars($terrain['nom']); ?>">
                                            <?php else: ?>
                                                <span style="color: #999;">Pas d'image</span>
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

    <script>
        function updatePeriod(select) {
            const selectedOption = select.options[select.selectedIndex];
            const periode = selectedOption.getAttribute('data-periode');
            document.getElementById('periode_reservation').value = periode || '';
        }

        // Améliorer l'UX avec des animations
        document.addEventListener('DOMContentLoaded', function() {
            // Animation pour les lignes du tableau
            const rows = document.querySelectorAll('.terrains-table tbody tr');
            rows.forEach((row, index) => {
                row.style.opacity = '0';
                row.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    row.style.transition = 'all 0.3s ease';
                    row.style.opacity = '1';
                    row.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>