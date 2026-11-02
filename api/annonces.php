<?php
require_once __DIR__ . '/../includes/layout.php';

$filters = [];
$filterError = '';
$queryInput = isset($_GET['q']) ? $_GET['q'] : '';
if ($queryInput !== '' && !is_string($queryInput)) {
    $filterError = 'Le texte recherché est invalide.';
}
$query = is_string($queryInput) ? trim(strip_tags($queryInput)) : '';
$filters['q'] = function_exists('mb_substr') ? mb_substr($query, 0, 100, 'UTF-8') : substr($query, 0, 100);
$categoryValue = isset($_GET['categorie']) ? $_GET['categorie'] : '';
$categoryId = 0;
if ($categoryValue !== '') {
    if (!is_string($categoryValue) || !ctype_digit($categoryValue) || (int) $categoryValue < 1) {
        $filterError = 'La catégorie sélectionnée est invalide.';
    } else {
        $categoryId = (int) $categoryValue;
    }
}
$cityValue = isset($_GET['ville']) ? $_GET['ville'] : '';
$city = is_string($cityValue) ? trim(strip_tags($cityValue)) : '';
if ($cityValue !== '' && (!is_string($cityValue) || textLength($city) > 50)) {
    $filterError = 'La ville sélectionnée est invalide.';
}
$yearValue = isset($_GET['annee']) ? $_GET['annee'] : '';
$year = 0;
if ($yearValue !== '') {
    if (!is_string($yearValue) || !ctype_digit($yearValue) || (int) $yearValue < 1900 || (int) $yearValue > (int) date('Y') + 1) {
        $filterError = 'L’année sélectionnée est invalide.';
    } else {
        $year = (int) $yearValue;
    }
}
$minimum = isset($_GET['prix_min']) && is_string($_GET['prix_min']) ? trim($_GET['prix_min']) : '';
$maximum = isset($_GET['prix_max']) && is_string($_GET['prix_max']) ? trim($_GET['prix_max']) : '';
if ((isset($_GET['prix_min']) && !is_string($_GET['prix_min'])) || (isset($_GET['prix_max']) && !is_string($_GET['prix_max']))) {
    $filterError = 'La fourchette de prix est invalide.';
}
if ($categoryId) { $filters['categorie'] = $categoryId; }
if ($city !== '') { $filters['ville'] = function_exists('mb_substr') ? mb_substr($city, 0, 50, 'UTF-8') : substr($city, 0, 50); }
if ($year) { $filters['annee'] = $year; }
foreach (['prix_min' => $minimum, 'prix_max' => $maximum] as $key => $price) {
    if ($price !== '' && (!is_numeric($price) || (float) $price < 0 || (float) $price > 9999999999999.99)) {
        $filterError = 'Saisissez des prix valides.';
    } elseif ($price !== '') {
        $filters[$key] = $price;
    }
}
if ($minimum !== '' && $maximum !== '' && is_numeric($minimum) && is_numeric($maximum) && (float) $maximum < (float) $minimum) {
    $filterError = 'Le prix maximum doit être supérieur ou égal au prix minimum.';
}

$categories = categoryRows();
$cities = db()->query("SELECT DISTINCT ville FROM annonces WHERE ville <> '' ORDER BY ville")->fetchAll(PDO::FETCH_COLUMN);
$years = db()->query('SELECT DISTINCT annee FROM annonces ORDER BY annee DESC')->fetchAll(PDO::FETCH_COLUMN);
$ads = $filterError === '' ? announcementRows($filters) : [];
renderPageStart('Toutes les annonces');
?>
<main class="page-bg"><div class="container page-pad">
  <div class="results-heading"><div><div class="page-title">Toutes les annonces</div><div class="muted"><?= count($ads) ?> annonce<?= count($ads) > 1 ? 's' : '' ?> trouvée<?= count($ads) > 1 ? 's' : '' ?></div></div></div>
  <?php if ($filterError): ?><p class="form-message error" role="alert"><?= h($filterError) ?></p><?php endif; ?>
  <div class="results-layout">
    <aside class="filters"><div class="filter-title">Filtres</div>
      <form action="annonces.php" class="filter-form" method="get">
        <div class="filter-group"><label for="q">Recherche</label><input class="field" id="q" name="q" type="search" value="<?= h($filters['q']) ?>"></div>
        <div class="filter-group"><label for="categorie">Catégorie</label><select class="field select" id="categorie" name="categorie"><option value="">Toutes les catégories</option>
          <?php foreach ($categories as $category): ?><option value="<?= (int) $category['id_categorie'] ?>" <?= $categoryId === (int) $category['id_categorie'] ? 'selected' : '' ?>><?= h($category['nom_categorie']) ?></option><?php endforeach; ?>
        </select></div>
        <div class="filter-group"><label for="ville">Ville</label><select class="field select" id="ville" name="ville"><option value="">Tout le Maroc</option>
          <?php foreach ($cities as $cityOption): ?><option value="<?= h($cityOption) ?>" <?= $city === $cityOption ? 'selected' : '' ?>><?= h($cityOption) ?></option><?php endforeach; ?>
        </select></div>
        <div class="filter-group"><label for="annee">Année</label><select class="field select" id="annee" name="annee"><option value="">Toutes les années</option>
          <?php foreach ($years as $yearOption): ?><option value="<?= (int) $yearOption ?>" <?= $year === (int) $yearOption ? 'selected' : '' ?>><?= (int) $yearOption ?></option><?php endforeach; ?>
        </select></div>
        <div class="filter-group"><label>Prix (DH)</label><div class="price-row"><input class="field" min="0" name="prix_min" placeholder="Min" type="number" value="<?= h($minimum) ?>"><input class="field" min="0" name="prix_max" placeholder="Max" type="number" value="<?= h($maximum) ?>"></div></div>
        <button class="btn btn-primary wide" type="submit">Appliquer les filtres</button>
      </form><a class="btn btn-link wide" href="annonces.php">Réinitialiser</a>
    </aside>
    <section class="result-list"><div class="ads-grid">
      <?php foreach ($ads as $ad) { renderAnnouncementCard($ad); } ?>
      <?php if (!$ads): ?><p>Aucune annonce ne correspond à ces critères.</p><?php endif; ?>
    </div></section>
  </div>
</div></main>
<?php renderPageEnd(); ?>
