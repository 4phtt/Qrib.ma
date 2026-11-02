<?php
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/announcement-images.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    verifyCsrf();
  } catch (CsrfValidationException $exception) {
    setFlash('error', $exception->getMessage());
    redirectTo('admin.php');
  }
  $idValue = isset($_POST['id_annonce']) ? $_POST['id_annonce'] : '';
  if (!is_string($idValue) || !ctype_digit($idValue) || (int) $idValue < 1) {
    http_response_code(400);
    exit('Identifiant d’annonce invalide.');
  }

  $connection = db();
  $connection->beginTransaction();
  $findAd = $connection->prepare('SELECT photos FROM annonces WHERE id_annonce = :id FOR UPDATE');
  $findAd->execute([':id' => (int) $idValue]);
  $photos = $findAd->fetchColumn();
  if ($photos === false) {
    $connection->rollBack();
    setFlash('error', 'Cette annonce n’existe plus.');
    redirectTo('admin.php');
  }

  $deleteAd = $connection->prepare('DELETE FROM annonces WHERE id_annonce = :id');
  $deleteAd->execute([':id' => (int) $idValue]);
  $connection->commit();
  deleteLocalAnnouncementImages(announcementImageList($photos));
  setFlash('success', 'L’annonce a été supprimée.');
  redirectTo('admin.php');
}

$ads = announcementRows();
renderPageStart('Administration des annonces');
?>
<main class="page-bg"><div class="container page-pad">
  <div class="results-heading">
    <div><div class="page-title">Administration</div><div class="muted">Toutes les annonces · <?= count($ads) ?> au total</div></div>
  </div>
  <?php renderFlash(); ?>
  <div class="admin-list-wrap">
    <table class="admin-list">
      <thead><tr><th>Annonce</th><th>Vendeur</th><th>Ville</th><th>Prix</th><th>Publiée le</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($ads as $ad): ?>
          <tr>
            <td><a href="annonce.php?id=<?= (int) $ad['id_annonce'] ?>"><?= h($ad['titre']) ?></a><span><?= h($ad['nom_categorie'] . ' · ' . $ad['nom_sous_categorie']) ?></span></td>
            <td><?= h($ad['prenom'] . ' ' . $ad['nom']) ?></td>
            <td><?= h($ad['ville']) ?></td>
            <td><?= h(number_format((float) $ad['prix'], 0, ',', ' ')) ?> DH</td>
            <td><?= h(date('d/m/Y', strtotime($ad['date_poster']))) ?></td>
            <td><div class="admin-actions">
              <a class="btn btn-outline" href="publier.php?id=<?= (int) $ad['id_annonce'] ?>">Modifier</a>
              <form action="admin.php" method="post" onsubmit="return confirm('Supprimer définitivement cette annonce ?')">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="id_annonce" value="<?= (int) $ad['id_annonce'] ?>">
                <button class="btn admin-delete-button" type="submit">Supprimer</button>
              </form>
            </div></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$ads): ?><tr><td colspan="6">Aucune annonce publiée.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div></main>
<?php renderPageEnd(); ?>