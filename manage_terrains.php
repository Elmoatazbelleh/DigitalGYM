<?php
require_once 'config.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php?error=Accès non autorisé");
    exit();
}

// Function to resize image - with GD extension check
function resizeImage($file, $targetPath, $maxWidth = 300, $maxHeight = 300) {
    // Check if GD extension is loaded
    if (!extension_loaded('gd')) {
        // If GD is not available, just copy the file without resizing
        return move_uploaded_file($file, $targetPath);
    }
    
    // Check if file exists and is readable
    if (!file_exists($file) || !is_readable($file)) {
        return false;
    }
    
    $imageInfo = getimagesize($file);
    if ($imageInfo === false) {
        return false;
    }
    
    list($width, $height, $type) = $imageInfo;
    
    switch($type) {
        case IMAGETYPE_JPEG:
            $src = imagecreatefromjpeg($file);
            break;
        case IMAGETYPE_PNG:
            $src = imagecreatefrompng($file);
            break;
        case IMAGETYPE_GIF:
            $src = imagecreatefromgif($file);
            break;
        default:
            return false;
    }
    
    if (!$src) {
        return false;
    }

    $ratio = min($maxWidth/$width, $maxHeight/$height);
    $newWidth = $width * $ratio;
    $newHeight = $height * $ratio;

    $dst = imagecreatetruecolor($newWidth, $newHeight);
    if (!$dst) {
        imagedestroy($src);
        return false;
    }
    
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    
    $success = false;
    switch($type) {
        case IMAGETYPE_JPEG:
            $success = imagejpeg($dst, $targetPath, 85);
            break;
        case IMAGETYPE_PNG:
            $success = imagepng($dst, $targetPath, 8);
            break;
        case IMAGETYPE_GIF:
            $success = imagegif($dst, $targetPath);
            break;
    }
    
    imagedestroy($src);
    imagedestroy($dst);
    return $success;
}

// Handle form submissions for CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $conn = get_db_connection();
    $error = '';
    
    // Use transactions for better data integrity
    $conn->begin_transaction();
    
    try {
        if ($action === 'add' || $action === 'update') {
            $id = ($action === 'update') ? (int)$_POST['id'] : null;
            $nom = trim($_POST['nom']);
            $type = $_POST['type'];
            $periode_reservation = (int)$_POST['periode_reservation'];
            $heure_debut = $_POST['heure_debut'];
            $heure_fin = $_POST['heure_fin'];
            $id_admin = $_SESSION['user_id'];
            $image_path = null;

            // Validate inputs
            if (empty($nom) || $periode_reservation <= 0 || $heure_debut >= $heure_fin) {
                throw new Exception("Veuillez fournir un nom valide, une période positive et des heures valides.");
            }

            // For update, get current image path first
            if ($action === 'update' && $id) {
                $sql = "SELECT image FROM terrains WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $result = $stmt->get_result();
                $current_terrain = $result->fetch_assoc();
                $image_path = $current_terrain['image']; // Keep current image by default
                $stmt->close();
            }

            // Handle image upload
            if (!empty($_FILES['image']['name'])) {
                $image = $_FILES['image'];
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                $max_size = 5 * 1024 * 1024; // 5MB
                
                if (!in_array($image['type'], $allowed_types)) {
                    throw new Exception("Type de fichier non autorisé. Utilisez JPEG, PNG ou GIF.");
                }
                if ($image['size'] > $max_size) {
                    throw new Exception("L'image est trop grande. Taille maximale : 5MB.");
                }

                $upload_dir = 'uploads/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $image_name = time() . '_' . basename($image['name']);
                $new_image_path = $upload_dir . $image_name;
                
                // Resize and save image (or just move if GD not available)
                if (!resizeImage($image['tmp_name'], $new_image_path)) {
                    throw new Exception("Erreur lors du traitement de l'image.");
                }
                
                // Delete old image if updating and new image uploaded
                if ($action === 'update' && $image_path && file_exists($image_path)) {
                    unlink($image_path);
                }
                
                $image_path = $new_image_path;
            }

            if ($action === 'add') {
                $sql = "INSERT INTO terrains (nom, type, disponibilite, id_admin, periode_reservation, heure_debut, heure_fin, image) 
                        VALUES (?, ?, 'disponible', ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssiiiss", $nom, $type, $id_admin, $periode_reservation, $heure_debut, $heure_fin, $image_path);
            } else {
                $sql = "UPDATE terrains SET nom = ?, type = ?, periode_reservation = ?, heure_debut = ?, heure_fin = ?, image = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssisssi", $nom, $type, $periode_reservation, $heure_debut, $heure_fin, $image_path, $id);
            }

            if (!$stmt->execute()) {
                throw new Exception("Erreur lors de " . ($action === 'add' ? "l'ajout" : "la mise à jour") . " du terrain.");
            }
            $stmt->close();
            
        } elseif ($action === 'delete') {
            $id = (int)$_POST['id'];
            $sql = "SELECT image FROM terrains WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $terrain = $result->fetch_assoc();
            $stmt->close();
            
            if ($terrain['image'] && file_exists($terrain['image'])) {
                unlink($terrain['image']);
            }

            $sql = "DELETE FROM terrains WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            if (!$stmt->execute()) {
                throw new Exception("Erreur lors de la suppression du terrain.");
            }
            $stmt->close();
        }

        $conn->commit();
        header("Location: manage_terrains.php?success=" . urlencode(
            $action === 'add' ? "Terrain ajouté avec succès" : 
            ($action === 'update' ? "Terrain mis à jour avec succès" : "Terrain supprimé avec succès")
        ));
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $error = $e->getMessage();
        header("Location: manage_terrains.php?error=" . urlencode($error));
        exit();
    } finally {
        $conn->close();
    }
}

// Fetch all terrains created by all admins with admin names
$conn = get_db_connection();
$sql = "SELECT t.id, t.nom, t.type, t.periode_reservation, t.heure_debut, t.heure_fin, t.image, u.nom AS admin_nom 
        FROM terrains t 
        JOIN users u ON t.id_admin = u.id 
        ORDER BY t.nom";
$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();
$terrains = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

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
    <title>Gérer les Terrains - FitZone</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .terrains-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .terrains-table th, .terrains-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .terrains-table th {
            background-color: #f5f5f5;
            font-weight: bold;
        }
        .terrain-image img {
            max-width: 100px;
            max-height: 100px;
            object-fit: cover;
            border-radius: 4px;
        }
        .terrain-actions button {
            margin-right: 5px;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border: none;
            width: 80%;
            max-width: 600px;
            border-radius: 8px;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close:hover {
            color: black;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
        }
        .form-group label {
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input, .form-group select {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .gd-warning {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
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
                    <li><a href="admin_dashboard.php">Tableau de Bord</a></li>
                    <li><a href="logout.php">Déconnexion</a></li>
                    <li class="user-info">Bonjour, <?php echo htmlspecialchars($_SESSION['nom']); ?> (Admin)</li>
                </ul>
            </nav>
        </div>
    </header>

    <main>
        <div class="crud-container">
            <div class="crud-header">
                <div>
                    <h2>🏟️ Gestion des Terrains</h2>
                    <p>Gérez vos installations sportives</p>
                </div>
                <button class="btn btn-primary" onclick="openModal('add-modal')">
                    ➕ Ajouter un Terrain
                </button>
            </div>

            <?php if (!extension_loaded('gd')): ?>
                <div class="gd-warning">
                    ⚠️ <strong>Extension GD non disponible :</strong> Les images ne seront pas redimensionnées automatiquement. Pour activer cette fonctionnalité, activez l'extension GD dans votre configuration PHP.
                </div>
            <?php endif; ?>

            <div class="crud-stats">
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($terrains); ?></div>
                    <div class="stat-label">Terrains Total</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo count(array_filter($terrains, fn($t) => $t['type'] === 'football')); ?></div>
                    <div class="stat-label">Terrains Football</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo count(array_filter($terrains, fn($t) => $t['type'] === 'tennis')); ?></div>
                    <div class="stat-label">Terrains Tennis</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo count(array_filter($terrains, fn($t) => $t['type'] === 'natation')); ?></div>
                    <div class="stat-label">Piscines</div>
                </div>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    ✅ <?php echo htmlspecialchars($_GET['success']); ?>
                </div>
            <?php elseif (isset($_GET['error'])): ?>
                <div class="alert alert-error">
                    ❌ <?php echo htmlspecialchars($_GET['error']); ?>
                </div>
            <?php endif; ?>

            <?php if (empty($terrains)): ?>
                <div class="no-terrains">
                    <h3>🏟️ Aucun terrain disponible</h3>
                    <p>Commencez par ajouter votre premier terrain</p>
                    <button class="btn btn-primary" onclick="openModal('add-modal')">
                        ➕ Ajouter un Terrain
                    </button>
                </div>
            <?php else: ?>
                <table class="terrains-table">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Nom</th>
                            <th>Type</th>
                            <th>Durée</th>
                            <th>Ouverture</th>
                            <th>Fermeture</th>
                            <th>Créé par</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($terrains as $terrain): ?>
                            <tr>
                                <td class="terrain-image">
                                    <?php if ($terrain['image']): ?>
                                        <img src="<?php echo htmlspecialchars($terrain['image']); ?>" alt="<?php echo htmlspecialchars($terrain['nom']); ?>">
                                    <?php else: ?>
                                        <?php echo $type_icons[$terrain['type']] ?? '🏟️'; ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($terrain['nom']); ?></td>
                                <td>
                                    <?php echo $type_icons[$terrain['type']] ?? '🏟️'; ?>
                                    <?php echo ucfirst($terrain['type']); ?>
                                </td>
                                <td><?php echo $terrain['periode_reservation']; ?> min</td>
                                <td><?php echo $terrain['heure_debut']; ?></td>
                                <td><?php echo $terrain['heure_fin']; ?></td>
                                <td><?php echo htmlspecialchars($terrain['admin_nom']); ?></td>
                                <td class="terrain-actions">
                                    <button class="btn btn-primary" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($terrain)); ?>)">✏️ Modifier</button>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $terrain['id']; ?>">
                                        <button type="submit" class="btn btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce terrain ?')">
                                            🗑️ Supprimer
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </main>

    <!-- Add Modal -->
    <div id="add-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>➕ Ajouter un Terrain</h3>
                <span class="close" onclick="closeModal('add-modal')">&times;</span>
            </div>
            <form id="add-form" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="nom">Nom du terrain</label>
                        <input type="text" id="nom" name="nom" required>
                    </div>
                    <div class="form-group">
                        <label for="type">Type de sport</label>
                        <select id="type" name="type" required>
                            <option value="">Sélectionnez un type</option>
                            <option value="football">⚽ Football</option>
                            <option value="tennis">🎾 Tennis</option>
                            <option value="natation">🏊‍♂️ Natation</option>
                            <option value="volleyball">🏐 Volleyball</option>
                            <option value="basketball">🏀 Basketball</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="periode_reservation">Durée de réservation (minutes)</label>
                        <input type="number" id="periode_reservation" name="periode_reservation" min="1" required>
                    </div>
                    <div class="form-group">
                        <label for="heure_debut">Heure d'ouverture</label>
                        <input type="time" id="heure_debut" name="heure_debut" required>
                    </div>
                    <div class="form-group">
                        <label for="heure_fin">Heure de fermeture</label>
                        <input type="time" id="heure_fin" name="heure_fin" required>
                    </div>
                    <div class="form-group">
                        <label for="add-image">Image du terrain</label>
                        <input type="file" id="add-image" name="image" accept="image/jpeg,image/png,image/gif">
                        <div id="add-image-preview" style="margin-top: 10px;"></div>
                    </div>
                </div>
                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('add-modal')">Annuler</button>
                    <button type="submit" class="btn btn-primary">Ajouter le terrain</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="edit-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>✏️ Modifier un Terrain</h3>
                <span class="close" onclick="closeModal('edit-modal')">&times;</span>
            </div>
            <form id="edit-form" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit-id">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit-nom">Nom du terrain</label>
                        <input type="text" id="edit-nom" name="nom" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-type">Type de sport</label>
                        <select id="edit-type" name="type" required>
                            <option value="">Sélectionnez un type</option>
                            <option value="football">⚽ Football</option>
                            <option value="tennis">🎾 Tennis</option>
                            <option value="natation">🏊‍♂️ Natation</option>
                            <option value="volleyball">🏐 Volleyball</option>
                            <option value="basketball">🏀 Basketball</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit-periode_reservation">Durée de réservation (minutes)</label>
                        <input type="number" id="edit-periode_reservation" name="periode_reservation" min="1" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-heure_debut">Heure d'ouverture</label>
                        <input type="time" id="edit-heure_debut" name="heure_debut" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-heure_fin">Heure de fermeture</label>
                        <input type="time" id="edit-heure_fin" name="heure_fin" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-image">Image du terrain</label>
                        <input type="file" id="edit-image" name="image" accept="image/jpeg,image/png,image/gif">
                        <div id="edit-image-preview" style="margin-top: 10px;"></div>
                    </div>
                </div>
                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('edit-modal')">Annuler</button>
                    <button type="submit" class="btn btn-primary">Mettre à jour</button>
                </div>
            </form>
        </div>
    </div>

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
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            if (modalId === 'add-modal') {
                document.getElementById('add-form').reset();
                document.getElementById('add-image-preview').innerHTML = '';
            } else if (modalId === 'edit-modal') {
                document.getElementById('edit-form').reset();
                document.getElementById('edit-image-preview').innerHTML = '';
            }
        }

        function openEditModal(terrain) {
            document.getElementById('edit-id').value = terrain.id;
            document.getElementById('edit-nom').value = terrain.nom;
            document.getElementById('edit-type').value = terrain.type;
            document.getElementById('edit-periode_reservation').value = terrain.periode_reservation;
            document.getElementById('edit-heure_debut').value = terrain.heure_debut;
            document.getElementById('edit-heure_fin').value = terrain.heure_fin;
            const preview = document.getElementById('edit-image-preview');
            preview.innerHTML = terrain.image ? 
                `<img src="${terrain.image}" style="max-width: 150px; border-radius: 8px;">` : '';
            openModal('edit-modal');
        }

        // Client-side form validation and image preview
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('form').forEach(form => {
                form.addEventListener('submit', function(e) {
                    const nom = form.querySelector('input[name="nom"]');
                    const periode = form.querySelector('input[name="periode_reservation"]');
                    const heureDebut = form.querySelector('input[name="heure_debut"]');
                    const heureFin = form.querySelector('input[name="heure_fin"]');
                    const fileInput = form.querySelector('input[type="file"]');

                    if (nom && nom.value.trim() === '') {
                        alert('Le nom du terrain est requis.');
                        e.preventDefault();
                        return;
                    }

                    if (periode && parseInt(periode.value) <= 0) {
                        alert('La période de réservation doit être supérieure à zéro.');
                        e.preventDefault();
                        return;
                    }

                    if (heureDebut && heureFin && heureDebut.value >= heureFin.value) {
                        alert('L\'heure de fermeture doit être postérieure à l\'heure d\'ouverture.');
                        e.preventDefault();
                        return;
                    }

                    if (fileInput && fileInput.files.length > 0) {
                        const file = fileInput.files[0];
                        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                        if (!allowedTypes.includes(file.type)) {
                            alert('Type de fichier non autorisé. Utilisez JPEG, PNG ou GIF.');
                            e.preventDefault();
                            return;
                        }
                        if (file.size > 5 * 1024 * 1024) {
                            alert('L\'image est trop grande. Taille maximale : 5MB.');
                            e.preventDefault();
                            return;
                        }
                    }
                });
            });

            // Image preview for both forms
            ['add-image', 'edit-image'].forEach(id => {
                const element = document.getElementById(id);
                if (element) {
                    element.addEventListener('change', function(e) {
                        const preview = document.getElementById(id + '-preview');
                        preview.innerHTML = '';
                        if (e.target.files.length > 0) {
                            const file = e.target.files[0];
                            const reader = new FileReader();
                            reader.onload = function(e) {
                                preview.innerHTML = '<img src="' + e.target.result + '" style="max-width: 150px; border-radius: 8px;">';
                            };
                            reader.readAsDataURL(file);
                        }
                    });
                }
            });
        });

        // Close modal when clicking outside
        window.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal')) {
                closeModal('add-modal');
                closeModal('edit-modal');
            }
        });
    </script>
</body>
</html>