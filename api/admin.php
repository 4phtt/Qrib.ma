<?php
require_once __DIR__ . '/../includes/layout.php';
requireAdmin();

$ads = announcementRows();
renderPageStart('Administration des annonces');
?>
<main class="page-bg"><div class="container page-pad">
  <div class="results-heading">
    <div><div class="page-title">Administration</div><div class="muted">Toutes les annonces · <?= count($ads) ?> au total</div></div>
  </div>
  <div class="admin-list-wrap">
    <table class="admin-list">
      <thead><tr><th>Annonce</th><th>Vendeur</th><th>Ville</th><th>Prix</th><th>Publiée le</th><th>Action</th></tr></thead>
      <tbody>
        <?php foreach ($ads as $ad): ?>
          <tr>
            <td><a href="annonce.php?id=<?= (int) $ad['id_annonce'] ?>"><?= h($ad['titre']) ?></a><span><?= h($ad['nom_categorie'] . ' · ' . $ad['nom_sous_categorie']) ?></span></td>
            <td><?= h($ad['prenom'] . ' ' . $ad['nom']) ?></td>
            <td><?= h($ad['ville']) ?></td>
            <td><?= h(number_format((float) $ad['prix'], 0, ',', ' ')) ?> DH</td>
            <td><?= h(date('d/m/Y', strtotime($ad['date_poster']))) ?></td>
            <td><a class="btn btn-outline" href="publier.php?id=<?= (int) $ad['id_annonce'] ?>">Modifier</a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$ads): ?><tr><td colspan="6">Aucune annonce publiée.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div></main>
<?php renderPageEnd(); ?>