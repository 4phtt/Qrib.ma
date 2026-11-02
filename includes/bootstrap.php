<?php

require_once __DIR__ . '/../config/db.php';

class CsrfValidationException extends RuntimeException
{
}

class QribSessionHandler implements SessionHandlerInterface
{
    private $connection;

    public function __construct($connection)
    {
        $this->connection = $connection;
        $this->connection->exec(
            'CREATE TABLE IF NOT EXISTS qrib_sessions (
                session_id VARCHAR(256) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                session_data MEDIUMBLOB NOT NULL,
                last_activity INT UNSIGNED NOT NULL,
                PRIMARY KEY (session_id),
                KEY idx_qrib_sessions_last_activity (last_activity)
            ) ENGINE=InnoDB'
        );
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $sessionId): string
    {
        $statement = $this->connection->prepare(
            'SELECT session_data FROM qrib_sessions WHERE session_id = :id AND last_activity >= :cutoff'
        );
        $statement->execute([
            ':id' => $sessionId,
            ':cutoff' => time() - (int) ini_get('session.gc_maxlifetime'),
        ]);
        $data = $statement->fetchColumn();
        return $data === false ? '' : $data;
    }

    public function write(string $sessionId, string $data): bool
    {
        $statement = $this->connection->prepare(
            'INSERT INTO qrib_sessions (session_id, session_data, last_activity)
             VALUES (:id, :data, :activity)
             ON DUPLICATE KEY UPDATE session_data = VALUES(session_data), last_activity = VALUES(last_activity)'
        );
        return $statement->execute([
            ':id' => $sessionId,
            ':data' => $data,
            ':activity' => time(),
        ]);
    }

    public function destroy(string $sessionId): bool
    {
        $statement = $this->connection->prepare('DELETE FROM qrib_sessions WHERE session_id = :id');
        return $statement->execute([':id' => $sessionId]);
    }

    public function gc(int $maxLifetime): int
    {
        $statement = $this->connection->prepare('DELETE FROM qrib_sessions WHERE last_activity < :cutoff');
        $statement->execute([':cutoff' => time() - (int) $maxLifetime]);
        return $statement->rowCount();
    }
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    $forwardedProtocol = isset($_SERVER['HTTP_X_FORWARDED_PROTO']) ? strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) : '';
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $forwardedProtocol === 'https';
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params(0, '/', '', $isSecure, true);
    if (PHP_VERSION_ID >= 70300) {
        ini_set('session.cookie_samesite', 'Lax');
    }
    $sessionHandler = new QribSessionHandler(db());
    session_set_save_handler($sessionHandler, true);
    session_start();
}

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function textLength($value)
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function textInitials($first, $last)
{
    if (function_exists('mb_substr')) {
        return mb_substr($first, 0, 1, 'UTF-8') . mb_substr($last, 0, 1, 'UTF-8');
    }
    preg_match('/^./us', $first, $firstMatch);
    preg_match('/^./us', $last, $lastMatch);
    return (isset($firstMatch[0]) ? $firstMatch[0] : '') . (isset($lastMatch[0]) ? $lastMatch[0] : '');
}

function db()
{
    static $connection;
    if ($connection === null) {
        $connection = getDatabase();
    }
    return $connection;
}

function csrfToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf()
{
    $submitted = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!is_string($submitted) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $submitted)) {
        http_response_code(400);
        throw new CsrfValidationException('Votre session a expiré. Rechargez la page et réessayez.');
    }
}

function redirectTo($path)
{
    header('Location: ' . $path, true, 303);
    exit;
}

function setFlash($type, $message)
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function takeFlash()
{
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function requireLogin()
{
    if (empty($_SESSION['annonceur_id'])) {
        setFlash('error', 'Connectez-vous pour publier une annonce.');
        redirectTo('login.php');
    }
}

function categoryRows()
{
    return db()->query('SELECT id_categorie, nom_categorie FROM categorie ORDER BY nom_categorie')->fetchAll();
}

function subcategoryRows()
{
    return db()->query(
        'SELECT id_sous_categorie, nom_sous_categorie, id_categorie
         FROM sous_categorie ORDER BY nom_sous_categorie'
    )->fetchAll();
}

function announcementRows($filters = [], $limit = null)
{
    $sql = 'SELECT a.id_annonce, a.titre, a.description, a.categorie, a.annonce, a.prix, a.photos, a.date_poster,
                   a.ville, a.id_sous_categorie, sc.nom_sous_categorie, c.id_categorie, c.nom_categorie,
                   u.nom, u.prenom, u.telephone, u.email
            FROM annonces a
            INNER JOIN sous_categorie sc ON sc.id_sous_categorie = a.id_sous_categorie
            INNER JOIN categorie c ON c.id_categorie = sc.id_categorie
            INNER JOIN annonceurs u ON u.id_annonceurs = a.id_annonceurs';
    $conditions = [];
    $parameters = [];

    if (!empty($filters['q'])) {
        $conditions[] = '(a.titre LIKE :q_title OR a.description LIKE :q_description OR c.nom_categorie LIKE :q_category OR sc.nom_sous_categorie LIKE :q_subcategory)';
        $term = '%' . $filters['q'] . '%';
        $parameters[':q_title'] = $term;
        $parameters[':q_description'] = $term;
        $parameters[':q_category'] = $term;
        $parameters[':q_subcategory'] = $term;
    }
    if (!empty($filters['categorie'])) {
        $conditions[] = 'c.id_categorie = :category_id';
        $parameters[':category_id'] = (int) $filters['categorie'];
    }
    if (!empty($filters['id_categorie'])) {
        $conditions[] = 'c.id_categorie = :page_category_id';
        $parameters[':page_category_id'] = (int) $filters['id_categorie'];
    }
    if (!empty($filters['ville'])) {
        $conditions[] = 'a.ville = :city';
        $parameters[':city'] = $filters['ville'];
    }
    if (!empty($filters['annee'])) {
        $conditions[] = 'a.annonce = :year';
        $parameters[':year'] = (int) $filters['annee'];
    }
    if (isset($filters['prix_min']) && $filters['prix_min'] !== '') {
        $conditions[] = 'a.prix >= :price_min';
        $parameters[':price_min'] = (float) $filters['prix_min'];
    }
    if (isset($filters['prix_max']) && $filters['prix_max'] !== '') {
        $conditions[] = 'a.prix <= :price_max';
        $parameters[':price_max'] = (float) $filters['prix_max'];
    }

    if ($conditions) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }
    $sql .= ' ORDER BY a.date_poster DESC, a.id_annonce DESC';
    if ($limit !== null) {
        $sql .= ' LIMIT ' . max(1, (int) $limit);
    }

    $statement = db()->prepare($sql);
    $statement->execute($parameters);
    return $statement->fetchAll();
}

function announcementImageList($raw)
{
    $decoded = json_decode((string) $raw, true);
    if (is_array($decoded)) {
        return array_values(array_filter($decoded, 'is_string'));
    }
    return $raw ? [(string) $raw] : [];
}

function announcementImage($raw)
{
    $images = announcementImageList($raw);
    return $images ? $images[0] : 'images/1527800792452.jpg';
}

function safeLocalImagePath($path)
{
    if (preg_match('~^https://res\.cloudinary\.com/[A-Za-z0-9_-]+/image/upload/[A-Za-z0-9_./-]+$~', $path)) {
        return $path;
    }
    if (!preg_match('~^(?:uploads/[a-f0-9]{32}\.(?:jpg|png|webp)|images/[A-Za-z0-9()_.-]+\.(?:jpg|jpeg|png|webp))$~i', $path)) {
        return 'images/1527800792452.jpg';
    }
    return $path;
}

function renderAnnouncementCard($ad)
{
    $title = h($ad['titre']);
    $image = h(safeLocalImagePath(announcementImage($ad['photos'])));
    $price = number_format((float) $ad['prix'], 0, ',', ' ') . ' DH';
    $city = h($ad['ville']);
    $description = h($ad['description']);
    $id = (int) $ad['id_annonce'];
    echo '<a class="card-link" href="annonce.php?id=' . $id . '"><article class="ad-card">';
    echo '<div class="ad-image-wrap"><img alt="' . $title . '" class="ad-image" loading="lazy" src="' . $image . '"></div>';
    echo '<div class="ad-body"><div class="ad-title">' . $title . '</div>';
    echo '<div class="ad-desc">' . $description . '</div><div class="ad-price">' . h($price) . '</div>';
    echo '<div class="ad-meta"><span>' . $city . '</span><span>' . h($ad['nom_categorie']) . '</span></div></div></article></a>';
}
