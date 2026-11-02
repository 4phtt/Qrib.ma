<?php
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/announcement-images.php';
requireLogin();

$error = '';
$categories = categoryRows();
$subcategories = subcategoryRows();
$values = ['titre' => '', 'description' => '', 'categorie' => '', 'sous_categorie' => '', 'prix' => '', 'annee' => '', 'ville' => ''];
$idValue = isset($_GET['id']) ? $_GET['id'] : '';
$editing = isset($_GET['id']);
$editId = $editing && is_string($idValue) && ctype_digit($idValue) ? (int) $idValue : 0;
$currentImages = [];
$listing = null;

if ($editing) {
    $findListing = db()->prepare(
        'SELECT a.*, sc.id_categorie
         FROM annonces a
         INNER JOIN sous_categorie sc ON sc.id_sous_categorie = a.id_sous_categorie
         WHERE a.id_annonce = :id AND a.id_annonceurs = :owner'
    );
    $findListing->execute([':id' => $editId, ':owner' => (int) $_SESSION['annonceur_id']]);
    $listing = $findListing->fetch();
    if (!$listing) {
        http_response_code(404);
    } else {
        $values = [
            'titre' => $listing['titre'],
            'description' => $listing['description'],
            'categorie' => (string) $listing['id_categorie'],
            'sous_categorie' => (string) $listing['id_sous_categorie'],
            'prix' => $listing['prix'],
            'annee' => $listing['annoe'],
            'ville' => $listing['ville'],
        ];
        $currentImages = announcementImageList($listing['photos']);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!$editing || $listing)) {
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
        if ($editing) {
            $savedImages = isset($_FILES['photos']) ? storeAnnouncementImages($_FILES['photos'], false) : [];
            $photoPaths = $savedImages ? $savedImages : $currentImages;
            $connection = db();
            $connection->beginTransaction();
            $update = $connection->prepare(
                'UPDATE annonces
                 SET titre = :title, description = :description, categorie = :category_name,
                     annoe = :year, prix = :price, photos = :photos, ville = :city,
                     id_sous_categorie = :subcategory
                 WHERE id_annonce = :id AND id_annonceurs = :owner'
            );
            $update->execute([
                ':title' => $values['titre'],
                ':description' => $values['description'],
                ':category_name' => $subcategory['nom_categorie'],
                ':year' => $year,
                ':price' => number_format((float) $values['prix'], 2, '.', ''),
                ':photos' => json_encode($photoPaths, JSON_UNESCAPED_SLASHES),
                ':city' => $values['ville'],
                ':subcategory' => (int) $subcategory['id_sous_categorie'],
                ':id' => $editId,
                ':owner' => (int) $_SESSION['annonceur_id'],
            ]);
            $connection->commit();
            if ($savedImages) {
                deleteLocalAnnouncementImages($currentImages);
            }
            setFlash('success', 'Votre annonce a été modifiée.');
            redirectTo('annonce.php?id=' . $editId);
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
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        deleteLocalAnnouncementImages($savedImages);
        throw $exception;
    } catch (CsrfValidationException $exception) {
        $error = $exception->getMessage();
    } catch (RuntimeException $exception) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        deleteLocalAnnouncementImages($savedImages);
        $error = $exception->getMessage();
    } catch (Exception $exception) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        deleteLocalAnnouncementImages($savedImages);
        throw $exception;
    }
}

renderPageStart($editing ? 'Modifier une annonce' : 'Déposer une annonce');
?>
<main class="page-bg publish-page"><div class="form-shell">
  <?php if (!$editing || $listing): ?>
  <div class="page-title center"><?= $editing ? 'Modifier votre annonce' : 'Déposez votre annonce' ?></div><div class="muted center"><?= $editing ? 'Mettez à jour les informations de votre annonce.' : 'Quelques étapes suffisent pour toucher des milliers d’acheteurs.' ?></div>
  <?php renderFlash(); ?><?php if ($error): ?><p class="form-message error" role="alert"><?= h($error) ?></p><?php endif; ?>
  <div class="form-card"><div class="content-title"><?= $editing ? 'Informations de l’annonce' : 'Décrivez votre annonce' ?></div>
    <form action="publier.php<?= $editing ? '?id=' . (int) $editId : '' ?>" class="form-grid" enctype="multipart/form-data" method="post">
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <?php if ($editing && $currentImages): ?><div class="span-two current-photos"><strong>Photos actuelles</strong><div class="current-photos-grid"><?php foreach ($currentImages as $image): ?><img alt="Photo actuelle de <?= h($values['titre']) ?>" loading="lazy" src="<?= validImageUrl($image) ?>"><?php endforeach; ?></div></div><?php endif; ?>
      <label class="span-two"><?= $editing ? 'Remplacer les photos (facultatif)' : 'Photos' ?> · JPEG, PNG ou WebP, 5 Mo maximum par photo<input accept="image/jpeg,image/png,image/webp" class="field" multiple name="photos[]" <?= $editing ? '' : 'required' ?> type="file"></label>
      <?php if ($editing): ?><small class="span-two">Sans nouvelle photo, les photos actuelles seront conservées. Sélectionner des photos les remplacera toutes.</small><?php endif; ?>
      <label>Catégorie<select class="field select" id="categorie" name="categorie" required><option value="">Choisir une catégorie</option><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id_categorie'] ?>" <?= $values['categorie'] == $category['id_categorie'] ? 'selected' : '' ?>><?= h($category['nom_categorie']) ?></option><?php endforeach; ?></select></label>
      <label>Sous-catégorie<select class="field select" id="sous-categorie" name="sous_categorie" required><option value="">Choisir une sous-catégorie</option><?php foreach ($subcategories as $subcategory): ?><option data-category="<?= (int) $subcategory['id_categorie'] ?>" value="<?= (int) $subcategory['id_sous_categorie'] ?>" <?= $values['sous_categorie'] == $subcategory['id_sous_categorie'] ? 'selected' : '' ?>><?= h($subcategory['nom_sous_categorie']) ?></option><?php endforeach; ?></select></label>
      <label class="span-two">Titre<input class="field" maxlength="50" name="titre" required value="<?= h($values['titre']) ?>"></label>
      <label class="span-two">Description<textarea class="field textarea" maxlength="255" name="description" required><?= h($values['description']) ?></textarea></label>
      <label>Prix (DH)<input class="field" min="0" name="prix" required step="0.01" type="number" value="<?= h($values['prix']) ?>"></label>
      <label>Année<input class="field" max="<?= (int) date('Y') + 1 ?>" min="1900" name="annee" required type="number" value="<?= h($values['annee']) ?>"></label>
      <label class="span-two">Localisation<input class="field" maxlength="50" name="ville" required value="<?= h($values['ville']) ?>"></label>
      <div class="form-actions span-two"><button class="btn btn-primary" type="submit"><?= $editing ? 'Enregistrer les modifications' : 'Publier l’annonce' ?></button><?php if ($editing): ?><a class="btn btn-outline" href="annonce.php?id=<?= (int) $editId ?>">Annuler</a><?php endif; ?></div>
    </form>
  </div>
  <?php else: ?>
    <div class="page-title center">Annonce introuvable</div><p class="muted center">Cette annonce n’existe pas ou vous n’avez pas l’autorisation de la modifier.</p>
    <div class="center"><a class="btn btn-primary" href="annonces.php">Voir les annonces</a></div>
  <?php endif; ?>
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
