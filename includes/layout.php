<?php

require_once __DIR__ . '/bootstrap.php';

function renderHeader()
{
    $accountLabel = !empty($_SESSION['annonceur_nom']) ? $_SESSION['annonceur_nom'] : 'Mon compte';
    $searchQuery = isset($_GET['q']) && is_string($_GET['q']) ? $_GET['q'] : '';
    echo '<header class="site-header"><div class="nav-wrap">';
    echo '<a class="logo" href="index.php"><span class="logo-mark">Q</span><span>Qrib <span class="logo-dot">.ma</span></span></a>';
    echo '<div class="nav-search"><form action="annonces.php" method="get"><input class="field" name="q" placeholder="Que cherchez-vous ?" type="search" value="' . h($searchQuery) . '"></form></div>';
    echo '<div class="nav-actions"><a class="btn btn-ghost" href="annonces.php">Annonces</a>';
    if (isset($_SESSION['annonceur_role']) && $_SESSION['annonceur_role'] === 'admin') {
        echo '<a class="btn btn-ghost" href="admin.php">Administration</a>';
    }
    $accountHref = !empty($_SESSION['annonceur_id'])
        ? 'profil.php?id=' . (int) $_SESSION['annonceur_id']
        : 'login.php';
    echo '<a class="btn btn-ghost" href="' . h($accountHref) . '">' . h($accountLabel) . '</a>';
    echo '<a class="btn btn-primary" href="publier.php">Déposer une annonce</a>';
    if (!empty($_SESSION['annonceur_id'])) {
        echo '<form action="logout.php" method="post" class="logout-form"><input type="hidden" name="csrf_token" value="' . h(csrfToken()) . '"><button class="btn btn-outline" type="submit">Se déconnecter</button></form>';
    }
    echo '</div>';
    echo '</div></header>';
}

function renderFooter()
{
    echo '<footer><div class="container footer-grid"><div><a class="logo" href="index.php"><span class="logo-mark">Q</span><span>Qrib <span class="logo-dot">.ma</span></span></a><p>La marketplace proche de vous, partout au Maroc.</p></div>';
    echo '<div><strong>Qrib.ma</strong><a class="btn btn-link" href="index.php">Accueil</a><a class="btn btn-link" href="annonces.php">Annonces</a><a class="btn btn-link" href="publier.php">Déposer une annonce</a></div>';
    echo '<div><strong>Mon compte</strong><a class="btn btn-link" href="login.php">Se connecter</a><a class="btn btn-link" href="signup.php">Créer un compte</a></div></div>';
    echo '<div class="container copyright">© 2026 Qrib.ma · Fait avec soin au Maroc</div></footer>';
}

function renderPageStart($title)
{
    echo '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . h($title) . ' · Qrib.ma</title><link rel="stylesheet" href="css/style.css"><link rel="stylesheet" href="css/extra.css"></head><body><div class="app">';
    renderHeader();
}

function renderPageEnd()
{
    renderFooter();
    echo '</div></body></html>';
}

function renderFlash()
{
    $flash = takeFlash();
    if ($flash) {
        echo '<p class="form-message ' . h($flash['type']) . '" role="status">' . h($flash['message']) . '</p>';
    }
}

function validImageUrl($storedPath)
{
    return h(safeLocalImagePath($storedPath));
}
