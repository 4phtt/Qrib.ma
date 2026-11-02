<?php
require_once __DIR__ . '/../includes/layout.php';
requireLogin();

$error = '';
$categories = categoryRows();
$subcategories = subcategoryRows();
$values = ['titre' => '', 'description' => '', 'categorie' => '', 'sous_categorie' => '', 'prix' => '', 'annee' => '', 'ville' => ''];

function storeAnnouncementImages($fileField)
{
    if (!isset($fileField['name']) || !is_array($fileField['name'])) {
        throw new InvalidArgumentException('Sélectionnez au moins une photo valide.');
    }
    $selected = [];
    foreach ($fileField['name'] as $index => $originalName) {
        $errorCode = isset($fileField['error'][$index]) ? (int) $fileField['error'][$index] : UPLOAD_ERR_NO_FILE;
        if ($errorCode === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($errorCode !== UPLOAD_ERR_OK || !isset($fileField['tmp_name'][$index], $fileField['size'][$index])) {
            throw new InvalidArgumentException('Une photo n’a pas pu être téléversée correctement.');
        }
        if ((int) $fileField['size'][$index] < 1 || (int) $fileField['size'][$index] > 5 * 1024 * 1024) {
            throw new InvalidArgumentException('Chaque photo doit peser moins de 5 Mo.');
        }
        $selected[] = $index;
    }
    if (!$selected || count($selected) > 10) {
        throw new InvalidArgumentException('Ajoutez entre 1 et 10 photos.');
    }

    $cloudinaryUrl = getenv('CLOUDINARY_URL');
    $useCloudinary = $cloudinaryUrl !== false && $cloudinaryUrl !== '';
    if (!$useCloudinary && getenv('VERCEL') === '1') {
        throw new RuntimeException('Configurez CLOUDINARY_URL pour stocker les photos durablement sur Vercel.');
    }
    if ($useCloudinary) {
        $autoload = dirname(__DIR__) . '/vendor/autoload.php';
        if (!is_file($autoload)) {
            throw new RuntimeException('Cloudinary est configuré, mais les dépendances Composer ne sont pas installées.');
        }
        require_once $autoload;
        if (!class_exists('\Cloudinary\Uploader')) {
            throw new RuntimeException('La bibliothèque Cloudinary est indisponible.');
        }
        $uploadDirectory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'qrib-uploads';
    } else {
        $uploadDirectory = dirname(__DIR__) . '/uploads';
    }
    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
        throw new RuntimeException('Le dossier de téléversement ne peut pas être créé.');
    }
    $paths = [];
    $totalBytes = 0;
    try {
      foreach ($selected as $index) {
        $temporaryFile = $fileField['tmp_name'][$index];
        if (!is_uploaded_file($temporaryFile)) {
            throw new InvalidArgumentException('Fichier téléversé invalide.');
        }
        $totalBytes += (int) $fileField['size'][$index];
        if ($totalBytes > 20 * 1024 * 1024) {
            throw new InvalidArgumentException('La taille totale des photos ne peut pas dépasser 20 Mo.');
        }
        $fileInfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $fileInfo->file($temporaryFile);
        $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $imageInfo = getimagesize($temporaryFile);
        if (!isset($allowedTypes[$mime]) || !$imageInfo || $imageInfo['mime'] !== $mime) {
            throw new InvalidArgumentException('Formats acceptés : JPEG, PNG et WebP uniquement.');
        }
        if ($imageInfo[0] < 1 || $imageInfo[1] < 1 || $imageInfo[0] * $imageInfo[1] > 12000000) {
            throw new InvalidArgumentException('La résolution de la photo est trop grande.');
        }
        if ($mime === 'image/webp' && !function_exists('imagewebp')) {
            throw new RuntimeException('Le serveur ne prend pas en charge le traitement des images WebP.');
        }
        $image = imagecreatefromstring(file_get_contents($temporaryFile));
        if ($image === false) {
            throw new InvalidArgumentException('Une photo ne peut pas être décodée.');
        }
        $fileName = bin2hex(openssl_random_pseudo_bytes(16)) . '.' . $allowedTypes[$mime];
        $destination = $uploadDirectory . '/' . $fileName;
        if ($mime === 'image/jpeg') {
            $written = imagejpeg($image, $destination, 88);
        } elseif ($mime === 'image/png') {
            $written = imagepng($image, $destination, 6);
        } else {
            $written = imagewebp($image, $destination, 88);
        }
        imagedestroy($image);
        if (!$written) {
            throw new RuntimeException('Une photo n’a pas pu être enregistrée.');
        }
        if ($useCloudinary) {
            try {
                $result = \Cloudinary\Uploader::upload($destination, [
                    'folder' => 'qrib/annonces',
                    'public_id' => bin2hex(openssl_random_pseudo_bytes(16)),
                    'resource_type' => 'image',
                    'overwrite' => false,
                    'unique_filename' => true,
                ]);
                if (empty($result['secure_url'])) {
                    throw new RuntimeException('Cloudinary n’a pas retourné une URL sécurisée pour la photo.');
                }
                $paths[] = $result['secure_url'];
            } finally {
                if (is_file($destination)) { unlink($destination); }
            }
        } else {
            $paths[] = 'uploads/' . $fileName;
        }
      }
    } catch (Exception $exception) {
        if (!$useCloudinary) {
            foreach ($paths as $path) {
                $savedPath = dirname(__DIR__) . '/' . $path;
                if (is_file($savedPath)) { unlink($savedPath); }
            }
        }
        throw $exception;
    }
    return $paths;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($values as $key => $unused) {
        $values[$key] = isset($_POST[$key]) && is_string($_POST[$key]) ? trim(strip_tags($_POST[$key])) : '';
    }
    $savedImages = [];
    try {
        verifyCsrf();
        if ($values['titre'] === '' || textLength($values['titre']) > 50 || preg_match('/[\x00-\x1F<>]/', $values['titre'])) {
            throw new InvalidArgumentException('Le titre est obligatoire et ne doit pas dépasser 50 caractères.');
        }
        if ($values['description'] === '' || textLength($values['description']) > 255) {
            throw new InvalidArgumentException('La description est obligatoire et ne doit pas dépasser 255 caractères.');
        }
        if (!is_numeric($values['prix']) || (float) $values['prix'] < 0 || (float) $values['prix'] > 9999999999999.99) {
            throw new InvalidArgumentException('Saisissez un prix valide.');
        }
        $year = filter_var($values['annee'], FILTER_VALIDATE_INT);
        if (!$year || $year < 1900 || $year > (int) date('Y') + 1) {
            throw new InvalidArgumentException('Saisissez une année valide.');
        }
        if (!preg_match("/^[\\p{L}\\p{M}][\\p{L}\\p{M} '\\x{2019}-]{0,49}$/u", $values['ville'])) {
            throw new InvalidArgumentException('Saisissez une ville valide.');
        }
        $subcategoryId = filter_var($values['sous_categorie'], FILTER_VALIDATE_INT);
        $findSubcategory = db()->prepare(
            'SELECT sc.id_sous_categorie, sc.nom_sous_categorie, c.nom_categorie
             FROM sous_categorie sc INNER JOIN categorie c ON c.id_categorie = sc.id_categorie
             WHERE sc.id_sous_categorie = :subcategory AND sc.id_categorie = :category'
        );
        $findSubcategory->execute([':subcategory' => $subcategoryId, ':category' => (int) $values['categorie']]);
        $subcategory = $findSubcategory->fetch();
        if (!$subcategory) {
            throw new InvalidArgumentException('Choisissez une catégorie et une sous-catégorie valides.');
        }
        if (!isset($_FILES['photos'])) {
            throw new InvalidArgumentException('Ajoutez au moins une photo.');
        }
        $savedImages = storeAnnouncementImages($_FILES['photos']);

        $insert = db()->prepare(
            'INSERT INTO annonces (titre, description, categorie, annoe, prix, photos, ville, id_annonceurs, id_sous_categorie)
             VALUES (:title, :description, :category_name, :year, :price, :photos, :city, :seller, :subcategory)'
        );
        $insert->execute([
            ':title' => $values['titre'],
            ':description' => $values['description'],
            ':category_name' => $subcategory['nom_categorie'],
            ':year' => $year,
            ':price' => number_format((float) $values['prix'], 2, '.', ''),
            ':photos' => json_encode($savedImages, JSON_UNESCAPED_SLASHES),
            ':city' => $values['ville'],
            ':seller' => (int) $_SESSION['annonceur_id'],
            ':subcategory' => (int) $subcategory['id_sous_categorie'],
        ]);
        $newId = (int) db()->lastInsertId();
        setFlash('success', 'Votre annonce a été publiée.');
        redirectTo('annonce.php?id=' . $newId);
    } catch (InvalidArgumentException $exception) {
        $error = $exception->getMessage();
    } catch (PDOException $exception) {
        foreach ($savedImages as $imagePath) {
            if (strpos($imagePath, 'uploads/') === 0) {
                $fullPath = dirname(__DIR__) . '/' . $imagePath;
                if (is_file($fullPath)) { unlink($fullPath); }
            }
        }
        throw $exception;
    } catch (CsrfValidationException $exception) {
        $error = $exception->getMessage();
    } catch (RuntimeException $exception) {
        $error = $exception->getMessage();
    }
}

renderPageStart('Déposer une annonce');
?>
<main class="page-bg publish-page"><div class="form-shell">
  <div class="page-title center">Déposez votre annonce</div><div class="muted center">Quelques étapes suffisent pour toucher des milliers d’acheteurs.</div>
  <?php renderFlash(); ?><?php if ($error): ?><p class="form-message error" role="alert"><?= h($error) ?></p><?php endif; ?>
  <div class="form-card"><div class="content-title">Décrivez votre annonce</div>
    <form action="publier.php" class="form-grid" enctype="multipart/form-data" method="post">
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <label class="span-two">Photos (JPEG, PNG ou WebP, 5 Mo maximum par photo)<input accept="image/jpeg,image/png,image/webp" class="field" multiple name="photos[]" required type="file"></label>
      <label>Catégorie<select class="field select" id="categorie" name="categorie" required><option value="">Choisir une catégorie</option><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id_categorie'] ?>" <?= $values['categorie'] == $category['id_categorie'] ? 'selected' : '' ?>><?= h($category['nom_categorie']) ?></option><?php endforeach; ?></select></label>
      <label>Sous-catégorie<select class="field select" id="sous-categorie" name="sous_categorie" required><option value="">Choisir une sous-catégorie</option><?php foreach ($subcategories as $subcategory): ?><option data-category="<?= (int) $subcategory['id_categorie'] ?>" value="<?= (int) $subcategory['id_sous_categorie'] ?>" <?= $values['sous_categorie'] == $subcategory['id_sous_categorie'] ? 'selected' : '' ?>><?= h($subcategory['nom_sous_categorie']) ?></option><?php endforeach; ?></select></label>
      <label class="span-two">Titre<input class="field" maxlength="50" name="titre" required value="<?= h($values['titre']) ?>"></label>
      <label class="span-two">Description<textarea class="field textarea" maxlength="255" name="description" required><?= h($values['description']) ?></textarea></label>
      <label>Prix (DH)<input class="field" min="0" name="prix" required step="0.01" type="number" value="<?= h($values['prix']) ?>"></label>
      <label>Année<input class="field" max="<?= (int) date('Y') + 1 ?>" min="1900" name="annee" required type="number" value="<?= h($values['annee']) ?>"></label>
      <label class="span-two">Localisation<input class="field" maxlength="50" name="ville" required value="<?= h($values['ville']) ?>"></label>
      <div class="form-actions span-two"><button class="btn btn-primary" type="submit">Publier l’annonce</button></div>
    </form>
  </div>
</div></main>
<script>
const categorySelect = document.getElementById('categorie');
const subcategorySelect = document.getElementById('sous-categorie');
categorySelect.addEventListener('change', () => {
  Array.from(subcategorySelect.options).forEach((option, index) => {
    if (index > 0) option.hidden = option.dataset.category !== categorySelect.value;
  });
  if (subcategorySelect.selectedOptions.length && subcategorySelect.selectedOptions[0].hidden) {
    subcategorySelect.value = '';
  }
});
categorySelect.dispatchEvent(new Event('change'));
</script>
<?php renderPageEnd(); ?>
