<?php
require_once __DIR__ . '/../includes/layout.php';

$error = '';
$values = ['nom' => '', 'prenom' => '', 'email' => '', 'telephone' => '', 'ville' => '', 'adresse' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($values as $key => $unused) {
        $values[$key] = isset($_POST[$key]) && is_string($_POST[$key]) ? trim(strip_tags($_POST[$key])) : '';
    }
    $values['email'] = strtolower($values['email']);
    $values['telephone'] = preg_replace('/[\s().-]/', '', $values['telephone']);
    try {
        verifyCsrf();
        $namePattern = "/^[\\p{L}\\p{M}][\\p{L}\\p{M} '\\x{2019}-]{0,49}$/u";
        $cityPattern = "/^[\\p{L}\\p{M}][\\p{L}\\p{M} '\\x{2019}-]{0,49}$/u";
        if (!preg_match($namePattern, $values['nom']) || !preg_match($namePattern, $values['prenom'])) {
            throw new InvalidArgumentException('Le nom et le prénom doivent contenir uniquement des lettres, espaces, apostrophes ou tirets.');
        }
        if (strlen($values['email']) > 254 || !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Saisissez une adresse e-mail valide.');
        }
        if (!preg_match('/^(?:\+212|0)[5-7][0-9]{8}$/', $values['telephone'])) {
            throw new InvalidArgumentException('Saisissez un numéro marocain valide (ex. 0612345678).');
        }
        if (!preg_match($cityPattern, $values['ville'])) {
            throw new InvalidArgumentException('Saisissez une ville valide.');
        }
        if ($values['adresse'] === '' || textLength($values['adresse']) > 50 || preg_match('/[\x00-\x1F<>]/', $values['adresse'])) {
            throw new InvalidArgumentException('L’adresse est obligatoire et ne doit pas dépasser 50 caractères.');
        }
        $password = isset($_POST['mot_de_passe']) && is_string($_POST['mot_de_passe']) ? $_POST['mot_de_passe'] : '';
        $confirmation = isset($_POST['confirmation_mot_de_passe']) && is_string($_POST['confirmation_mot_de_passe']) ? $_POST['confirmation_mot_de_passe'] : '';
        if (strlen($password) < 8 || strlen($password) > 72 || !preg_match('/[\p{L}]/u', $password) || !preg_match('/[0-9]/', $password)) {
            throw new InvalidArgumentException('Le mot de passe doit comporter 8 à 72 caractères, avec au moins une lettre et un chiffre.');
        }
        if (!hash_equals($password, $confirmation)) {
            throw new InvalidArgumentException('Les mots de passe ne correspondent pas.');
        }
        $username = 'q' . bin2hex(openssl_random_pseudo_bytes(12));
        $insert = db()->prepare(
            'INSERT INTO annonceurs (username, nom, prenom, email, password, ville, telephone, adresse)
             VALUES (:username, :nom, :prenom, :email, :password, :ville, :telephone, :adresse)'
        );
        $insert->execute([
            ':username' => $username,
            ':nom' => $values['nom'],
            ':prenom' => $values['prenom'],
            ':email' => $values['email'],
            ':password' => password_hash($password, PASSWORD_DEFAULT),
            ':ville' => $values['ville'],
            ':telephone' => $values['telephone'],
            ':adresse' => $values['adresse'],
        ]);
        setFlash('success', 'Votre compte a été créé. Vous pouvez maintenant vous connecter.');
        redirectTo('login.php');
    } catch (InvalidArgumentException $exception) {
        $error = $exception->getMessage();
    } catch (PDOException $exception) {
        if ($exception->getCode() === '23000') {
            $error = 'Cette adresse e-mail ou ce numéro de téléphone est déjà associé à un compte.';
        } else {
            throw $exception;
        }
    } catch (CsrfValidationException $exception) {
        $error = $exception->getMessage();
    }
}

renderPageStart('Créer un compte');
?>
<main class="auth-page auth-page-centered"><section class="auth-card signup-card">
  <div class="page-title">Créer un compte</div><div class="muted">Rejoignez la communauté Qrib.ma</div>
  <?php renderFlash(); ?><?php if ($error): ?><p class="form-message error" role="alert"><?= h($error) ?></p><?php endif; ?>
  <form action="signup.php" class="signup-fields" method="post">
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <label>Nom<input class="field" maxlength="50" name="nom" required value="<?= h($values['nom']) ?>"></label>
    <label>Prénom<input class="field" maxlength="50" name="prenom" required value="<?= h($values['prenom']) ?>"></label>
    <label>Email<input class="field" maxlength="254" name="email" required type="email" value="<?= h($values['email']) ?>"></label>
    <label>Numéro de téléphone<input class="field" maxlength="20" name="telephone" required type="tel" value="<?= h($values['telephone']) ?>"></label>
    <label>Ville<input class="field" maxlength="50" name="ville" required value="<?= h($values['ville']) ?>"></label>
    <label>Adresse<input class="field" maxlength="50" name="adresse" required value="<?= h($values['adresse']) ?>"></label>
    <label>Mot de passe<input class="field" autocomplete="new-password" minlength="8" maxlength="72" name="mot_de_passe" required type="password"></label>
    <label>Confirmer le mot de passe<input class="field" autocomplete="new-password" minlength="8" maxlength="72" name="confirmation_mot_de_passe" required type="password"></label>
    <button class="btn btn-primary wide" type="submit">Créer mon compte</button>
  </form>
  <div class="switch-auth">Vous avez déjà un compte ? <a class="btn btn-link" href="login.php">Se connecter</a></div>
</section></main>
<?php renderPageEnd(); ?>
