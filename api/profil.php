<?php
require_once __DIR__ . '/../includes/layout.php';

$idValue = isset($_GET['id']) ? $_GET['id'] : '';
$id = is_string($idValue) && ctype_digit($idValue) ? (int) $idValue : 0;
$statement = db()->prepare(
    'SELECT id_annonceurs, nom, prenom, ville
     FROM annonceurs
     WHERE id_annonceurs = :id'
);
$statement->execute([':id' => $id]);
$profile = $statement->fetch();
if (!$profile) {
    http_response_code(404);
}
$ads = $profile ? announcementRows(['id_annonceur' => (int) $profile['id_annonceurs']]) : [];

renderPageStart($profile ? 'Profil de ' . $profile['prenom'] : 'Profil introuvable');
?>
<main class="page-bg"><div class="container page-pad">
  <?php if ($profile): ?>
    <div class="breadcrumbs"><a href="index.php">Accueil</a> › Profil de <?= h($profile['prenom'] . ' ' . $profile['nom']) ?></div>
    <div class="results-heading">
      <div>
        <div class="page-title"><?= h($profile['prenom'] . ' ' . $profile['nom']) ?></div>
        <div class="muted">Vendeur à <?= h($profile['ville']) ?> · <?= count($ads) ?> annonce<?= count($ads) > 1 ? 's' : '' ?></div>
      </div>
    </div>
    <div class="ads-grid category-ad-grid">
      <?php foreach ($ads as $ad) { renderAnnouncementCard($ad); } ?>
      <?php if (!$ads): ?><p>Ce vendeur n’a pas d’annonce publiée pour le moment.</p><?php endif; ?>
    </div>
  <?php else: ?>
    <div class="page-title">Profil introuvable</div>
    <p>Ce profil n’existe pas ou n’est plus disponible.</p>
    <a class="btn btn-primary" href="annonces.php">Voir les annonces</a>
  <?php endif; ?>
</div></main>
<?php renderPageEnd(); ?>
