<?php
require_once __DIR__ . '/../includes/layout.php';
$id = isset($_GET['id']) && is_string($_GET['id']) && ctype_digit($_GET['id']) ? (int) $_GET['id'] : 0;
$statement = db()->prepare(
    'SELECT a.*, sc.id_categorie, c.nom_categorie, sc.nom_sous_categorie,
            u.nom, u.prenom, u.ville AS ville_vendeur, u.telephone, u.email
     FROM annonces a
     INNER JOIN sous_categorie sc ON sc.id_sous_categorie = a.id_sous_categorie
     INNER JOIN categorie c ON c.id_categorie = sc.id_categorie
     INNER JOIN annonceurs u ON u.id_annonceurs = a.id_annonceurs
     WHERE a.id_annonce = :id'
);
$statement->execute([':id' => $id]);
$ad = $statement->fetch();
if (!$ad) {
    http_response_code(404);
}
$photos = $ad ? announcementImageList($ad['photos']) : [];
renderPageStart($ad ? $ad['titre'] : 'Annonce introuvable');
?>
<main class="page-bg"><div class="container page-pad">
  <?php if ($ad): ?>
    <div class="breadcrumbs"><a href="index.php">Accueil</a> › <a href="categorie.php?id=<?= (int) $ad['id_categorie'] ?>"><?= h($ad['nom_categorie']) ?></a> › <?= h($ad['titre']) ?></div>
    <div class="detail-grid">
      <section>
        <div class="gallery">
          <img alt="<?= h($ad['titre']) ?>" class="gallery-main" src="<?= validImageUrl(isset($photos[0]) ? $photos[0] : '') ?>">
          <?php if (count($photos) > 1): ?><div class="gallery-thumbs"><?php foreach (array_slice($photos, 0, 10) as $photo): ?><img alt="<?= h($ad['titre']) ?>" loading="lazy" src="<?= validImageUrl($photo) ?>"><?php endforeach; ?></div><?php endif; ?>
        </div>
        <div class="detail-card">
          <div class="detail-head"><div><div class="page-title detail-title"><?= h($ad['titre']) ?></div><div class="ad-meta"><span><?= h($ad['ville']) ?></span><span><?= h($ad['nom_categorie']) ?> · <?= h($ad['nom_sous_categorie']) ?></span></div></div><div class="detail-price"><?= h(number_format((float) $ad['prix'], 0, ',', ' ')) ?> DH</div></div>
          <div class="divider"></div><div class="content-title">Description</div><p class="description"><?= nl2br(h($ad['description'])) ?></p>
          <div class="specs"><div><span>Catégorie</span><strong><?= h($ad['nom_categorie']) ?></strong></div><div><span>Sous-catégorie</span><strong><?= h($ad['nom_sous_categorie']) ?></strong></div><div><span>Année</span><strong><?= (int) $ad['annee'] ?></strong></div><div><span>Localisation</span><strong><?= h($ad['ville']) ?></strong></div><div><span>Publié le</span><strong><?= h(date('d/m/Y', strtotime($ad['date_poster']))) ?></strong></div><div><span>Référence</span><strong>QRB-<?= (int) $ad['id_annonce'] ?></strong></div></div>
        </div>
      </section>
      <aside class="seller-card">
        <a class="seller-row seller-profile-link" href="profil.php?id=<?= (int) $ad['id_annonceurs'] ?>"><div class="avatar"><?= h(textInitials($ad['prenom'], $ad['nom'])) ?></div><div><div class="seller-name"><?= h($ad['prenom'] . ' ' . $ad['nom']) ?></div><div class="muted small">Vendeur à <?= h($ad['ville_vendeur']) ?></div></div></a>
        <?php if (!empty($_SESSION['annonceur_id']) && (int) $_SESSION['annonceur_id'] === (int) $ad['id_annonceurs']): ?>
          <a class="btn btn-outline wide" href="publier.php?id=<?= (int) $ad['id_annonce'] ?>">Modifier mon annonce</a>
        <?php endif; ?>
        <div class="seller-contact"><strong>Coordonnées du vendeur</strong><a href="tel:<?= h($ad['telephone']) ?>"><?= h($ad['telephone']) ?></a><a href="mailto:<?= h($ad['email']) ?>"><?= h($ad['email']) ?></a></div>
        <a class="btn btn-primary wide" href="tel:<?= h($ad['telephone']) ?>">Appeler le vendeur</a>
        <a class="btn btn-secondary wide" href="mailto:<?= h($ad['email']) ?>">Écrire au vendeur</a>
        <div class="safe-note"><div><strong>Achetez en toute sécurité</strong><span>Ne payez jamais à l’avance. Rencontrez le vendeur dans un lieu public.</span></div></div>
      </aside>
    </div>
  <?php else: ?>
    <div class="page-title">Annonce introuvable</div><p>Cette annonce n’existe pas ou n’est plus disponible.</p><a class="btn btn-primary" href="annonces.php">Voir les annonces</a>
  <?php endif; ?>
</div></main>
<?php renderPageEnd(); ?>
