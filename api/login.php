<?php
require_once __DIR__ . '/../includes/layout.php';

$error = '';
$identifier = isset($_POST['identifiant']) && is_string($_POST['identifiant']) ? trim($_POST['identifiant']) : '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verifyCsrf();
        $password = isset($_POST['mot_de_passe']) && is_string($_POST['mot_de_passe']) ? $_POST['mot_de_passe'] : '';
        if ($identifier === '' || strlen($identifier) > 254 || $password === '' || strlen($password) > 72) {
            throw new InvalidArgumentException('Vérifiez votre identifiant et votre mot de passe.');
        }
        $normalizedPhone = preg_replace('/[\s().-]/', '', $identifier);
        $findUser = db()->prepare(
            'SELECT id_annonceurs, nom, prenom, password
             FROM annonceurs WHERE email = :email OR telephone = :telephone LIMIT 1'
        );
        $findUser->execute([':email' => strtolower($identifier), ':telephone' => $normalizedPhone]);
        $user = $findUser->fetch();
        if (!$user || !password_verify($password, $user['password'])) {
            throw new InvalidArgumentException('Identifiant ou mot de passe incorrect.');
        }
        session_regenerate_id(true);
        $_SESSION['annonceur_id'] = (int) $user['id_annonceurs'];
        $_SESSION['annonceur_nom'] = $user['prenom'];
        setFlash('success', 'Bienvenue sur Qrib.ma.');
        redirectTo('index.php');
    } catch (InvalidArgumentException $exception) {
        $error = $exception->getMessage();
    } catch (CsrfValidationException $exception) {
        $error = $exception->getMessage();
    } catch (PDOException $exception) {
        throw $exception;
    }
}

renderPageStart('Connexion');
?>
<main class="auth-page"><section class="auth-card">
  <div class="page-title">Ravi de vous revoir</div><div class="muted">Connectez-vous pour publier et gérer vos annonces.</div>
  <?php renderFlash(); ?><?php if ($error): ?><p class="form-message error" role="alert"><?= h($error) ?></p><?php endif; ?>
  <form action="login.php" class="auth-form" method="post">
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <label>Email ou numéro de téléphone<input class="field" autocomplete="username" name="identifiant" required value="<?= h($identifier) ?>"></label>
    <label>Mot de passe<input class="field" autocomplete="current-password" name="mot_de_passe" required type="password"></label>
    <button class="btn btn-primary wide" type="submit">Se connecter</button>
  </form>
  <div class="switch-auth">Nouveau sur Qrib.ma ? <a class="btn btn-link" href="signup.php">Créer un compte</a></div>
</section></main>
<?php renderPageEnd(); ?>
