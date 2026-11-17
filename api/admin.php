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
  $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';
  if ($action === 'deactivate_expired') {
    $connection = db();
    $connection->exec('SET @qrib_annonces_desactivees = 0');
    $procedure = $connection->prepare('CALL desactiver_annonces_expirees(@qrib_annonces_desactivees)');
    $procedure->execute();
    $procedure->closeCursor();
    $deactivatedCount = (int) $connection->query('SELECT @qrib_annonces_desactivees')->fetchColumn();
    setFlash('success', 'Procédure exécutée. Annonces désactivées : ' . $deactivatedCount . '.');
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

$ads = db()->query(
  'SELECT a.id_annonce, a.titre, a.ville, a.prix, a.date_poster, a.statut,
          calculer_date_expiration(a.date_poster) AS date_expiration,
          sc.nom_sous_categorie, c.nom_categorie, u.nom, u.prenom
   FROM annonces a
   INNER JOIN sous_categorie sc ON sc.id_sous_categorie = a.id_sous_categorie
   INNER JOIN categorie c ON c.id_categorie = sc.id_categorie
   INNER JOIN annonceurs u ON u.id_annonceurs = a.id_annonceurs
   ORDER BY a.date_poster DESC, a.id_annonce DESC'
)->fetchAll();
$expiredCount = 0;
foreach ($ads as $ad) {
  if (announcementIsExpired($ad)) {
    $expiredCount++;
  }
}
renderPageStart('Administration des annonces');
?>
<main class="page-bg"><div class="container page-pad">
  <div class="results-heading">
    <div><div class="page-title">Administration</div><div class="muted">Toutes les annonces · <?= count($ads) ?> au total</div></div>
  </div>
  <?php renderFlash(); ?>
  <section class="admin-action">
    <div class="content-title">Désactivation des annonces expirées</div>
    <p>La fonction calcule une expiration 30 jours après la publication. La procédure désactive les annonces arrivées à expiration. Annonces déjà désactivées : <?= (int) $expiredCount ?>.</p>
    <form action="admin.php" method="post">
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="action" value="deactivate_expired">
      <button class="btn btn-primary" type="submit">Exécuter la désactivation</button>
    </form>
  </section>
  <div class="admin-list-wrap">
    <table class="admin-list">
      <thead><tr><th>Annonce</th><th>Vendeur</th><th>Ville</th><th>Prix</th><th>Publiée le</th><th>Expire le</th><th>Statut</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($ads as $ad): ?>
          <?php $isExpired = announcementIsExpired($ad); ?>
          <tr>
            <td><a href="annonce.php?id=<?= (int) $ad['id_annonce'] ?>"><?= h($ad['titre']) ?></a><span><?= h($ad['nom_categorie'] . ' · ' . $ad['nom_sous_categorie']) ?></span></td>
            <td><?= h($ad['prenom'] . ' ' . $ad['nom']) ?></td>
            <td><?= h($ad['ville']) ?></td>
            <td><?= h(number_format((float) $ad['prix'], 0, ',', ' ')) ?> DH</td>
            <td><?= h(date('d/m/Y', strtotime($ad['date_poster']))) ?></td>
            <td><?= h(date('d/m/Y', strtotime($ad['date_expiration']))) ?></td>
            <td class="<?= $isExpired ? 'admin-status-expired' : 'admin-status-active' ?>"><?= $isExpired ? 'Expirée' : 'Active' ?></td>
            <td><div class="admin-actions">
              <a class="btn btn-outline" href="publier.php?id=<?= (int) $ad['id_annonce'] ?>">Modifier</a>
              <form action="admin.php" method="post" onsubmit="return confirm('Supprimer définitivement cette annonce ?')">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id_annonce" value="<?= (int) $ad['id_annonce'] ?>">
                <button class="btn admin-delete-button" type="submit">Supprimer</button>
              </form>
            </div></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$ads): ?><tr><td colspan="8">Aucune annonce publiée.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div></main>
<?php renderPageEnd(); ?>