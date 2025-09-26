<?php
require_once 'config.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php?error=Accès non autorisé");
    exit();
}

$error = '';
$success = '';
$warning = '';

// Check if GD library is enabled
$gd_enabled = extension_loaded('gd') && function_exists('imagecreatefromjpeg');

// Fetch all products
try {
    $stmt = $pdo->query("SELECT * FROM produits ORDER BY date_ajout DESC");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Erreur lors du chargement des produits : " . $e->getMessage();
}

// Handle product creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $nom = trim($_POST['nom']);
    $description = trim($_POST['description']);
    $prix = floatval($_POST['prix']);
    $stock = intval($_POST['stock']);
    $image = '';

    // Validate input fields
    if (empty($nom) || empty($description) || $prix <= 0 || $stock < 0) {
        $error = "Veuillez remplir tous les champs correctement";
    } else {
        // Handle image upload
        if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/';
                
                // Ensure upload directory exists
                if (!is_dir($upload_dir)) {
                    if (!mkdir($upload_dir, 0755, true)) {
                        $error = "Impossible de créer le répertoire uploads/";
                    }
                }

                // Check if directory is writable
                if (!$error && !is_writable($upload_dir)) {
                    $error = "Le répertoire uploads/ n'est pas accessible en écriture";
                }

                if (!$error) {
                    // Validate file type
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                    $file_info = finfo_open(FILEINFO_MIME_TYPE);
                    $mime_type = finfo_file($file_info, $_FILES['image']['tmp_name']);
                    finfo_close($file_info);

                    if (!in_array($mime_type, $allowed_types)) {
                        $error = "Type de fichier non autorisé. Seuls JPEG, PNG et GIF sont acceptés.";
                    } else {
                        // Check file size (max 5MB)
                        if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
                            $error = "Le fichier est trop volumineux (max 5MB)";
                        } else {
                            $image_name = time() . '_' . basename($_FILES['image']['name']);
                            $image_path = $upload_dir . $image_name;

                            if ($gd_enabled) {
                                // Resize image
                                $max_width = 200;
                                $source_image = null;
                                switch ($mime_type) {
                                    case 'image/jpeg':
                                        $source_image = imagecreatefromjpeg($_FILES['image']['tmp_name']);
                                        break;
                                    case 'image/png':
                                        $source_image = imagecreatefrompng($_FILES['image']['tmp_name']);
                                        break;
                                    case 'image/gif':
                                        $source_image = imagecreatefromgif($_FILES['image']['tmp_name']);
                                        break;
                                }

                                if ($source_image) {
                                    $width = imagesx($source_image);
                                    $height = imagesy($source_image);
                                    $aspect_ratio = $height / $width;
                                    $new_width = $max_width;
                                    $new_height = $max_width * $aspect_ratio;

                                    $resized_image = imagecreatetruecolor($new_width, $new_height);
                                    if ($mime_type === 'image/png') {
                                        imagealphablending($resized_image, false);
                                        imagesavealpha($resized_image, true);
                                        $transparent = imagecolorallocatealpha($resized_image, 0, 0, 0, 127);
                                        imagefill($resized_image, 0, 0, $transparent);
                                    }

                                    imagecopyresampled($resized_image, $source_image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

                                    switch ($mime_type) {
                                        case 'image/jpeg':
                                            imagejpeg($resized_image, $image_path, 90);
                                            break;
                                        case 'image/png':
                                            imagepng($resized_image, $image_path);
                                            break;
                                        case 'image/gif':
                                            imagegif($resized_image, $image_path);
                                            break;
                                    }

                                    imagedestroy($source_image);
                                    imagedestroy($resized_image);

                                    $image = $image_path;
                                } else {
                                    $error = "Erreur lors du traitement de l'image";
                                }
                            } else {
                                if (move_uploaded_file($_FILES['image']['tmp_name'], $image_path)) {
                                    $image = $image_path;
                                    $warning = "La bibliothèque GD n'est pas activée. L'image a été téléchargée sans redimensionnement.";
                                } else {
                                    $error = "Erreur lors du déplacement du fichier : vérifier les permissions du répertoire";
                                }
                            }
                        }
                    }
                }
            } else {
                $error = "Erreur de téléchargement : ";
                switch ($_FILES['image']['error']) {
                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        $error .= "Le fichier dépasse la taille maximale autorisée";
                        break;
                    case UPLOAD_ERR_PARTIAL:
                        $error .= "Le fichier n'a été que partiellement téléchargé";
                        break;
                    case UPLOAD_ERR_NO_TMP_DIR:
                        $error .= "Répertoire temporaire manquant";
                        break;
                    case UPLOAD_ERR_CANT_WRITE:
                        $error .= "Échec de l'écriture sur le disque";
                        break;
                    default:
                        $error .= "Erreur inconnue (code: " . $_FILES['image']['error'] . ")";
                        break;
                }
            }
        }

        // If no errors, proceed with database insertion
        if (!$error) {
            try {
                $stmt = $pdo->prepare("INSERT INTO produits (nom, description, prix, stock, image) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$nom, $description, $prix, $stock, $image]);
                $success = "Produit ajouté avec succès";
                header("Location: manage_products.php");
                exit();
            } catch (PDOException $e) {
                $error = "Erreur lors de l'ajout du produit : " . $e->getMessage();
            }
        }
    }
}

// Handle product update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
    $product_id = $_POST['product_id'];
    $nom = trim($_POST['nom']);
    $description = trim($_POST['description']);
    $prix = floatval($_POST['prix']);
    $stock = intval($_POST['stock']);
    $image = $_POST['existing_image']; // Keep existing image unless new one is uploaded

    // Validate input fields
    if (empty($nom) || empty($description) || $prix <= 0 || $stock < 0) {
        $error = "Veuillez remplir tous les champs correctement";
    } else {
        // Handle image upload
        if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/';
                
                if (!is_dir($upload_dir)) {
                    if (!mkdir($upload_dir, 0755, true)) {
                        $error = "Impossible de créer le répertoire uploads/";
                    }
                }

                if (!$error && !is_writable($upload_dir)) {
                    $error = "Le répertoire uploads/ n'est pas accessible en écriture";
                }

                if (!$error) {
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                    $file_info = finfo_open(FILEINFO_MIME_TYPE);
                    $mime_type = finfo_file($file_info, $_FILES['image']['tmp_name']);
                    finfo_close($file_info);

                    if (!in_array($mime_type, $allowed_types)) {
                        $error = "Type de fichier non autorisé. Seuls JPEG, PNG et GIF sont acceptés.";
                    } else {
                        if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
                            $error = "Le fichier est trop volumineux (max 5MB)";
                        } else {
                            $image_name = time() . '_' . basename($_FILES['image']['name']);
                            $image_path = $upload_dir . $image_name;

                            if ($gd_enabled) {
                                $max_width = 200;
                                $source_image = null;
                                switch ($mime_type) {
                                    case 'image/jpeg':
                                        $source_image = imagecreatefromjpeg($_FILES['image']['tmp_name']);
                                        break;
                                    case 'image/png':
                                        $source_image = imagecreatefrompng($_FILES['image']['tmp_name']);
                                        break;
                                    case 'image/gif':
                                        $source_image = imagecreatefromgif($_FILES['image']['tmp_name']);
                                        break;
                                }

                                if ($source_image) {
                                    $width = imagesx($source_image);
                                    $height = imagesy($source_image);
                                    $aspect_ratio = $height / $width;
                                    $new_width = $max_width;
                                    $new_height = $max_width * $aspect_ratio;

                                    $resized_image = imagecreatetruecolor($new_width, $new_height);
                                    if ($mime_type === 'image/png') {
                                        imagealphablending($resized_image, false);
                                        imagesavealpha($resized_image, true);
                                        $transparent = imagecolorallocatealpha($resized_image, 0, 0, 0, 127);
                                        imagefill($resized_image, 0, 0, $transparent);
                                    }

                                    imagecopyresampled($resized_image, $source_image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

                                    switch ($mime_type) {
                                        case 'image/jpeg':
                                            imagejpeg($resized_image, $image_path, 90);
                                            break;
                                        case 'image/png':
                                            imagepng($resized_image, $image_path);
                                            break;
                                        case 'image/gif':
                                            imagegif($resized_image, $image_path);
                                            break;
                                    }

                                    imagedestroy($source_image);
                                    imagedestroy($resized_image);

                                    // Delete old image if it exists
                                    if (!empty($_POST['existing_image']) && file_exists($_POST['existing_image'])) {
                                        unlink($_POST['existing_image']);
                                    }

                                    $image = $image_path;
                                } else {
                                    $error = "Erreur lors du traitement de l'image";
                                }
                            } else {
                                if (move_uploaded_file($_FILES['image']['tmp_name'], $image_path)) {
                                    if (!empty($_POST['existing_image']) && file_exists($_POST['existing_image'])) {
                                        unlink($_POST['existing_image']);
                                    }
                                    $image = $image_path;
                                    $warning = "La bibliothèque GD n'est pas activée. L'image a été téléchargée sans redimensionnement.";
                                } else {
                                    $error = "Erreur lors du déplacement du fichier : vérifier les permissions du répertoire";
                                }
                            }
                        }
                    }
                }
            } else {
                $error = "Erreur de téléchargement : ";
                switch ($_FILES['image']['error']) {
                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        $error .= "Le fichier dépasse la taille maximale autorisée";
                        break;
                    case UPLOAD_ERR_PARTIAL:
                        $error .= "Le fichier n'a été que partiellement téléchargé";
                        break;
                    case UPLOAD_ERR_NO_TMP_DIR:
                        $error .= "Répertoire temporaire manquant";
                        break;
                    case UPLOAD_ERR_CANT_WRITE:
                        $error .= "Échec de l'écriture sur le disque";
                        break;
                    default:
                        $error .= "Erreur inconnue (code: " . $_FILES['image']['error'] . ")";
                        break;
                }
            }
        }

        // If no errors, proceed with database update
        if (!$error) {
            try {
                $stmt = $pdo->prepare("UPDATE produits SET nom = ?, description = ?, prix = ?, stock = ?, image = ? WHERE id = ?");
                $stmt->execute([$nom, $description, $prix, $stock, $image, $product_id]);
                $success = "Produit mis à jour avec succès";
                header("Location: manage_products.php");
                exit();
            } catch (PDOException $e) {
                $error = "Erreur lors de la mise à jour du produit : " . $e->getMessage();
            }
        }
    }
}

// Handle product deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
    $product_id = $_POST['product_id'];
    try {
        // Delete associated image file
        $stmt = $pdo->prepare("SELECT image FROM produits WHERE id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($product['image'] && file_exists($product['image'])) {
            unlink($product['image']);
        }

        $stmt = $pdo->prepare("DELETE FROM produits WHERE id = ?");
        $stmt->execute([$product_id]);
        $success = "Produit supprimé avec succès";
        header("Location: manage_products.php");
        exit();
    } catch (PDOException $e) {
        $error = "Erreur lors de la suppression du produit : " . $e->getMessage();
    }
}

// Fetch product for editing
$edit_product = null;
if (isset($_GET['edit_product'])) {
    $product_id = $_GET['edit_product'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM produits WHERE id = ?");
        $stmt->execute([$product_id]);
        $edit_product = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$edit_product) {
            $error = "Produit non trouvé";
        }
    } catch (PDOException $e) {
        $error = "Erreur lors du chargement du produit : " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gérer les Produits - FitZone</title>
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
                    <h2>Gérer les Produits</h2>
                    <p>Ajoutez, modifiez ou supprimez des produits</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                <?php if ($warning): ?>
                    <div class="alert alert-warning"><?php echo htmlspecialchars($warning); ?></div>
                <?php endif; ?>

                <div class="auth-form">
                    <h3><?php echo $edit_product ? 'Modifier le produit' : 'Ajouter un nouveau produit'; ?></h3>
                    <form method="POST" action="" enctype="multipart/form-data">
                        <?php if ($edit_product): ?>
                            <input type="hidden" name="product_id" value="<?php echo $edit_product['id']; ?>">
                            <input type="hidden" name="existing_image" value="<?php echo htmlspecialchars($edit_product['image']); ?>">
                        <?php endif; ?>
                        <div class="form-group">
                            <label for="nom">Nom du produit</label>
                            <input type="text" id="nom" name="nom" value="<?php echo $edit_product ? htmlspecialchars($edit_product['nom']) : ''; ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" required rows="4"><?php echo $edit_product ? htmlspecialchars($edit_product['description']) : ''; ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="prix">Prix (€)</label>
                            <input type="number" id="prix" name="prix" step="0.01" min="0" value="<?php echo $edit_product ? number_format($edit_product['prix'], 2) : ''; ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="stock">Stock</label>
                            <input type="number" id="stock" name="stock" min="0" value="<?php echo $edit_product ? $edit_product['stock'] : ''; ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="image">Image du produit (max 5MB, JPEG/PNG/GIF, sera redimensionnée à 200px de large)</label>
                            <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/gif">
                            <?php if ($edit_product && $edit_product['image']): ?>
                                <p>Image actuelle : <img src="<?php echo htmlspecialchars($edit_product['image']); ?>" alt="Image actuelle" style="max-width: 100px;"></p>
                            <?php endif; ?>
                        </div>
                        <button type="submit" name="<?php echo $edit_product ? 'update_product' : 'add_product'; ?>" class="btn btn-primary btn-full"><?php echo $edit_product ? 'Mettre à jour le produit' : 'Ajouter le produit'; ?></button>
                    </form>
                </div>

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
                            <p class="stock">Stock: <?php echo $product['stock']; ?>
                                <?php if ($product['stock'] <= 10): ?>
                                    <span class="stock-badge">Stock faible</span>
                                <?php endif; ?>
                            </p>
                            <a href="?edit_product=<?php echo $product['id']; ?>" class="btn btn-primary btn-full">Modifier</a>
                            <form method="POST" action="" style="margin-top: 0.5rem;">
                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                <button type="submit" name="delete_product" class="btn btn-secondary btn-full">Supprimer</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
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