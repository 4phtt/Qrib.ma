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
  if ($action === 'deactivate_selected') {
    $submittedIds = isset($_POST['id_annonces']) ? $_POST['id_annonces'] : [];
    if (!is_array($submittedIds) || !$submittedIds) {
      setFlash('error', 'Sélectionnez au moins une annonce à désactiver.');
      redirectTo('admin.php');
    }
    $selectedIds = [];
    foreach ($submittedIds as $submittedId) {
      if (!is_string($submittedId) || !ctype_digit($submittedId) || (int) $submittedId < 1) {
        http_response_code(400);
        exit('Identifiant d’annonce invalide.');
      }
      $selectedIds[(int) $submittedId] = (int) $submittedId;
    }

    $connection = db();
    $deactivate = $connection->prepare(
      'UPDATE annonces
       SET statut = \'Expiré\'
       WHERE id_annonce = :id
         AND COALESCE(statut, \'\') <> \'Expiré\'
         AND calculer_date_expiration(date_poster) < CURDATE()'
    );
    $connection->beginTransaction();
    $deactivatedCount = 0;
    try {
      foreach ($selectedIds as $selectedId) {
        $deactivate->execute([':id' => $selectedId]);
        $deactivatedCount += $deactivate->rowCount();
      }
      $connection->commit();
    } catch (PDOException $exception) {
      if ($connection->inTransaction()) {
        $connection->rollBack();
      }
      throw $exception;
    }
    if ($deactivatedCount > 0) {
      setFlash('success', 'Annonces désactivées : ' . $deactivatedCount . '.');
    } else {
      setFlash('error', 'Aucune annonce sélectionnée n’est encore à désactiver.');
    }
    redirectTo('admin.php');
  }

  if ($action !== 'delete') {
    http_response_code(400);
    exit('Action invalide.');
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
          (calculer_date_expiration(a.date_poster) < CURDATE()) AS expiration_due,
          sc.nom_sous_categorie, c.nom_categorie, u.nom, u.prenom
   FROM annonces a
   INNER JOIN sous_categorie sc ON sc.id_sous_categorie = a.id_sous_categorie
   INNER JOIN categorie c ON c.id_categorie = sc.id_categorie
   INNER JOIN annonceurs u ON u.id_annonceurs = a.id_annonceurs
   ORDER BY a.date_poster DESC, a.id_annonce DESC'
)->fetchAll();
$expiredCandidates = db()->query(
  'SELECT a.id_annonce, a.titre, a.ville, a.date_poster, a.statut,
          calculer_date_expiration(a.date_poster) AS date_expiration,
          c.nom_categorie, sc.nom_sous_categorie, u.nom, u.prenom
   FROM annonces a
   INNER JOIN sous_categorie sc ON sc.id_sous_categorie = a.id_sous_categorie
   INNER JOIN categorie c ON c.id_categorie = sc.id_categorie
   INNER JOIN annonceurs u ON u.id_annonceurs = a.id_annonceurs
   WHERE calculer_date_expiration(a.date_poster) < CURDATE()
   ORDER BY date_expiration ASC, a.id_annonce DESC'
)->fetchAll();
$pendingDeactivationCount = 0;
foreach ($expiredCandidates as $candidate) {
  if (!announcementIsExpired($candidate)) {
    $pendingDeactivationCount++;
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
    <div class="content-title">Annonces arrivées à expiration · <?= count($expiredCandidates) ?></div>
    <p>Cette liste est recalculée à chaque visite à partir de la fonction d’expiration. Sélectionnez les annonces encore actives que vous souhaitez désactiver (<?= (int) $pendingDeactivationCount ?> en attente).</p>
    <form action="admin.php" method="post">
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="action" value="deactivate_selected">
      <div class="admin-list-wrap">
        <table class="admin-list">
          <thead><tr><th>Sélection</th><th>Annonce</th><th>Vendeur</th><th>Ville</th><th>Expire le</th><th>Statut</th></tr></thead>
          <tbody>
            <?php foreach ($expiredCandidates as $candidate): ?>
              <?php $alreadyDeactivated = announcementIsExpired($candidate); ?>
              <tr>
                <td><?php if (!$alreadyDeactivated): ?><input aria-label="Sélectionner <?= h($candidate['titre']) ?>" name="id_annonces[]" type="checkbox" value="<?= (int) $candidate['id_annonce'] ?>"><?php endif; ?></td>
                <td><a href="annonce.php?id=<?= (int) $candidate['id_annonce'] ?>"><?= h($candidate['titre']) ?></a><span><?= h($candidate['nom_categorie'] . ' · ' . $candidate['nom_sous_categorie']) ?></span></td>
                <td><?= h($candidate['prenom'] . ' ' . $candidate['nom']) ?></td>
                <td><?= h($candidate['ville']) ?></td>
                <td><?= h(date('d/m/Y', strtotime($candidate['date_expiration']))) ?></td>
                <td class="<?= $alreadyDeactivated ? 'admin-status-expired' : 'admin-status-pending' ?>"><?= $alreadyDeactivated ? 'Déjà désactivée' : 'À désactiver' ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$expiredCandidates): ?><tr><td colspan="6">Aucune annonce dont la date d’expiration est dépassée.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
      <?php if ($pendingDeactivationCount): ?><button class="btn btn-primary" type="submit">Désactiver les annonces sélectionnées</button><?php endif; ?>
    </form>
  </section>
  <div class="admin-list-wrap">
    <table class="admin-list">
      <thead><tr><th>Annonce</th><th>Vendeur</th><th>Ville</th><th>Prix</th><th>Publiée le</th><th>Expire le</th><th>Statut</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($ads as $ad): ?>
          <?php
            $isExpired = announcementIsExpired($ad);
            $isDue = (bool) $ad['expiration_due'];
            $statusLabel = $isExpired ? 'Expirée · désactivée' : ($isDue ? 'Expirée · à désactiver' : 'Active');
            $statusClass = $isExpired ? 'admin-status-expired' : ($isDue ? 'admin-status-pending' : 'admin-status-active');
          ?>
          <tr>
            <td><a href="annonce.php?id=<?= (int) $ad['id_annonce'] ?>"><?= h($ad['titre']) ?></a><span><?= h($ad['nom_categorie'] . ' · ' . $ad['nom_sous_categorie']) ?></span></td>
            <td><?= h($ad['prenom'] . ' ' . $ad['nom']) ?></td>
            <td><?= h($ad['ville']) ?></td>
            <td><?= h(number_format((float) $ad['prix'], 0, ',', ' ')) ?> DH</td>
            <td><?= h(date('d/m/Y', strtotime($ad['date_poster']))) ?></td>
            <td><?= h(date('d/m/Y', strtotime($ad['date_expiration']))) ?></td>
            <td class="<?= $statusClass ?>"><?= h($statusLabel) ?></td>
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