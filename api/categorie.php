<?php
require_once __DIR__ . '/../includes/layout.php';
$id = isset($_GET['id']) && is_string($_GET['id']) && ctype_digit($_GET['id']) ? (int) $_GET['id'] : 0;
$statement = db()->prepare('SELECT id_categorie, nom_categorie FROM categorie WHERE id_categorie = :id');
$statement->execute([':id' => $id]);
$category = $statement->fetch();
if (!$category) {
    http_response_code(404);
}
$query = isset($_GET['q']) && is_string($_GET['q']) ? trim(strip_tags($_GET['q'])) : '';
$filters = ['id_categorie' => $id, 'q' => function_exists('mb_substr') ? mb_substr($query, 0, 100, 'UTF-8') : substr($query, 0, 100)];
$ads = $category ? announcementRows($filters) : [];
renderPageStart($category ? $category['nom_categorie'] : 'Catégorie introuvable');
?>
<main class="page-bg"><div class="container page-pad">
  <?php if ($category): ?>
    <div class="breadcrumbs"><a href="index.php">Accueil</a> › <?= h($category['nom_categorie']) ?></div>
    <div class="results-heading"><div><div class="page-title"><?= h($category['nom_categorie']) ?></div><div class="muted"><?= count($ads) ?> annonce<?= count($ads) > 1 ? 's' : '' ?> dans cette catégorie</div></div></div>
    <form action="categorie.php" class="category-hero-search" method="get"><input type="hidden" name="id" value="<?= (int) $category['id_categorie'] ?>"><input class="field" name="q" placeholder="Rechercher dans cette catégorie" type="search" value="<?= h($query) ?>"><button class="btn btn-primary" type="submit">Rechercher</button></form>
    <div class="ads-grid category-ad-grid"><?php foreach ($ads as $ad) { renderAnnouncementCard($ad); } ?><?php if (!$ads): ?><p>Aucune annonce dans cette catégorie pour le moment.</p><?php endif; ?></div>
  <?php else: ?>
    <div class="page-title">Catégorie introuvable</div><p>Cette catégorie n’existe pas ou a été supprimée.</p><a class="btn btn-primary" href="index.php">Retour à l’accueil</a>
  <?php endif; ?>
</div></main>
<?php renderPageEnd(); ?>
