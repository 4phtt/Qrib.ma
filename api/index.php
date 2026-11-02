<?php
require_once __DIR__ . '/../includes/layout.php';
$categories = categoryRows();
$recent = announcementRows([], 5);
renderPageStart('Accueil');
?>
<section class="hero">
  <div class="container hero-content">
    <div class="hero-kicker"><span class="live-dot"></span> Achetez et vendez partout au Maroc</div>
    <div class="hero-brand">Qrib.ma</div>
    <div class="hero-slogan">Qarebna lik li b3id 3lik</div>
    <div class="hero-title">Trouvez ce dont vous avez besoin<br><span>près de chez vous.</span></div>
    <div class="hero-copy">Achetez et vendez facilement, partout au Maroc.</div>
    <form action="annonces.php" class="hero-search" method="get">
      <label class="search-part search-grow"><span>Que recherchez-vous ?</span><input class="field" name="q" placeholder="Ordinateur, voiture, appartement..." type="search"></label>
      <label class="search-part"><span>Catégorie</span><select class="field select" name="categorie"><option value="">Toutes les catégories</option>
        <?php foreach ($categories as $category): ?><option value="<?= (int) $category['id_categorie'] ?>"><?= h($category['nom_categorie']) ?></option><?php endforeach; ?>
      </select></label>
      <button class="btn btn-primary" type="submit">Rechercher</button>
    </form>
  </div>
</section>
<main>
  <section class="container section">
    <div class="section-head"><div><div class="section-title">Explorez par catégorie</div><div class="section-subtitle">Tout ce que vous cherchez, au même endroit</div></div><a class="btn btn-link" href="annonces.php">Voir toutes</a></div>
    <div class="category-grid">
      <?php foreach ($categories as $category): ?>
        <a class="card-link" href="categorie.php?id=<?= (int) $category['id_categorie'] ?>"><div class="category-card"><div class="category-icon"><?= h(textInitials($category['nom_categorie'], '')) ?></div><div class="category-name"><?= h($category['nom_categorie']) ?></div><div class="category-count">Voir les annonces</div></div></a>
      <?php endforeach; ?>
      <?php if (!$categories): ?><p>Aucune catégorie n’est disponible pour le moment.</p><?php endif; ?>
    </div>
  </section>
  <section class="container section">
    <div class="section-head"><div><div class="section-title">Annonces récentes</div><div class="section-subtitle">Les dernières bonnes affaires publiées</div></div><a class="btn btn-link" href="annonces.php">Voir toutes les annonces</a></div>
    <div class="ads-grid">
      <?php foreach ($recent as $ad) { renderAnnouncementCard($ad); } ?>
      <?php if (!$recent): ?><p>Aucune annonce publiée pour le moment.</p><?php endif; ?>
    </div>
  </section>
</main>
<?php renderPageEnd(); ?>
