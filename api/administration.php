<?php
require_once __DIR__ . '/../includes/layout.php';
requireAdmin();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verifyCsrf();
        $connection = db();
        $connection->exec('SET @qrib_annonces_desactivees = 0');
        $procedure = $connection->prepare('CALL desactiver_annonces_expirees(@qrib_annonces_desactivees)');
        $procedure->execute();
        $procedure->closeCursor();
        $deactivatedCount = (int) $connection->query('SELECT @qrib_annonces_desactivees')->fetchColumn();
        setFlash('success', $deactivatedCount . ' annonce' . ($deactivatedCount > 1 ? 's ont été désactivées.' : ' a été désactivée.'));
        redirectTo('administration.php');
    } catch (CsrfValidationException $exception) {
        $error = $exception->getMessage();
    }
}

$listings = db()->query(
    'SELECT a.id_annonce, a.titre, a.date_poster, a.statut,
            calculer_date_expiration(a.date_poster) AS date_expiration,
            u.prenom, u.nom
     FROM annonces a
     INNER JOIN annonceurs u ON u.id_annonceurs = a.id_annonceurs
     ORDER BY CASE WHEN a.statut = \'Expiré\' THEN 0 ELSE 1 END, a.date_poster DESC, a.id_annonce DESC'
)->fetchAll();
$expiredCount = 0;
foreach ($listings as $listing) {
    if (announcementIsExpired($listing)) {
        $expiredCount++;
    }
}

renderPageStart('Administration');
?>
<main class="page-bg"><div class="container page-pad">
  <div class="results-heading"><div><div class="page-title">Administration</div><div class="muted">Gérez l’expiration et l’état des annonces.</div></div></div>
  <?php renderFlash(); ?><?php if ($error): ?><p class="form-message error" role="alert"><?= h($error) ?></p><?php endif; ?>
  <section class="admin-action">
    <div class="content-title">Désactivation des annonces expirées</div>
    <p>La fonction calcule une expiration 30 jours après la publication. La procédure désactive les annonces arrivées à expiration. Annonces déjà désactivées : <?= (int) $expiredCount ?>.</p>
    <form action="administration.php" method="post">
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <button class="btn btn-primary" type="submit">Exécuter la désactivation</button>
    </form>
  </section>
  <div class="admin-listings">
    <table>
      <thead><tr><th>Annonce</th><th>Vendeur</th><th>Publiée le</th><th>Expire le</th><th>Statut</th></tr></thead>
      <tbody>
        <?php foreach ($listings as $listing): ?>
          <?php $isExpired = announcementIsExpired($listing); ?>
          <tr>
            <td><a class="btn btn-link" href="annonce.php?id=<?= (int) $listing['id_annonce'] ?>"><?= h($listing['titre']) ?></a></td>
            <td><?= h($listing['prenom'] . ' ' . $listing['nom']) ?></td>
            <td><?= h(date('d/m/Y', strtotime($listing['date_poster']))) ?></td>
            <td><?= h(date('d/m/Y', strtotime($listing['date_expiration']))) ?></td>
            <td class="<?= $isExpired ? 'admin-status-expired' : 'admin-status-active' ?>"><?= $isExpired ? 'Expirée' : 'Active' ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$listings): ?><tr><td colspan="5">Aucune annonce à afficher.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div></main>
<?php renderPageEnd(); ?>
