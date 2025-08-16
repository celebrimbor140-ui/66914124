<?php
/**
 * ICT3612 Task 4 — Franchise Shop Reviews
 * Single-file MVC-style app: task4.php
 * Requirements implemented:
 *  - Customer registration & login
 *  - Customers create reviews (rating 1–5, optional description, date recorded)
 *  - Customers can view their own reviews after login
 *  - Admin login; manage shops (add/edit/delete)
 *  - Admin views reviews per shop (customer full names, rating, description)
 *  - Admin sees average rating per shop
 *  - Admin can list customer details who gave 1-star per shop
 *  - Prepared statements + password_hash/verify
 *  - Minimal, accessible UI + navigation
 */

// =========================[ CONFIG ]=========================

const DB_HOST = '127.0.0.1';
const DB_NAME = 'franchise_reviews';
const DB_USER = 'root';        // change if needed
const DB_PASS = '';            // change if needed
const APP_NAME = 'FreshMart Reviews';

session_start();

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $opts = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $opts);
    }
    return $pdo;
}

function is_post(): bool { return $_SERVER['REQUEST_METHOD'] === 'POST'; }
function redirect($route, $params = []) {
    $q = http_build_query(array_merge(['route' => $route], $params));
    header("Location: ?$q");
    exit;
}
function current_user() { return $_SESSION['user'] ?? null; }
function require_login() { if (!current_user()) redirect('login'); }
function require_admin() { if (!current_user() || current_user()['role'] !== 'admin') redirect('login'); }

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// =========================[ MODELS ]=========================

class User {
    public static function create($first,$last,$email,$phone,$username,$password): int {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $sql = "INSERT INTO users(first_name,last_name,email,phone,username,password_hash,role) VALUES(?,?,?,?,?,?,'customer')";
        db()->prepare($sql)->execute([$first,$last,$email,$phone,$username,$hash]);
        return (int)db()->lastInsertId();
    }
    public static function findByUsername($username){
        $st = db()->prepare("SELECT * FROM users WHERE username=?");
        $st->execute([$username]);
        return $st->fetch();
    }
    public static function find($id){
        $st = db()->prepare("SELECT * FROM users WHERE id=?");
        $st->execute([$id]);
        return $st->fetch();
    }
}

class Shop {
    public static function all(){
        return db()->query("SELECT * FROM shops ORDER BY name")->fetchAll();
    }
    public static function find($id){
        $st = db()->prepare("SELECT * FROM shops WHERE id=?");
        $st->execute([$id]);
        return $st->fetch();
    }
    public static function create($name,$addr,$city){
        $st = db()->prepare("INSERT INTO shops(name,address,city) VALUES(?,?,?)");
        $st->execute([$name,$addr,$city]);
        return (int)db()->lastInsertId();
    }
    public static function update($id,$name,$addr,$city){
        $st = db()->prepare("UPDATE shops SET name=?, address=?, city=? WHERE id=?");
        return $st->execute([$name,$addr,$city,$id]);
    }
    public static function delete($id){
        $st = db()->prepare("DELETE FROM shops WHERE id=?");
        return $st->execute([$id]);
    }
    public static function averages(){
        $sql = "SELECT s.id, s.name, s.city, ROUND(AVG(r.rating),2) AS avg_rating, COUNT(r.id) AS review_count
                FROM shops s
                LEFT JOIN reviews r ON r.shop_id = s.id
                GROUP BY s.id, s.name, s.city
                ORDER BY s.name";
        return db()->query($sql)->fetchAll();
    }
}

class Review {
    public static function create($user_id,$shop_id,$rating,$body,$date){
        $st = db()->prepare("INSERT INTO reviews(user_id,shop_id,rating,body,review_date) VALUES(?,?,?,?,?)");
        $st->execute([$user_id,$shop_id,$rating,$body,$date]);
        return (int)db()->lastInsertId();
    }
    public static function forUser($user_id){
        $sql = "SELECT r.*, s.name AS shop_name FROM reviews r
                JOIN shops s ON s.id = r.shop_id
                WHERE r.user_id=? ORDER BY r.review_date DESC, r.id DESC";
        $st = db()->prepare($sql);
        $st->execute([$user_id]);
        return $st->fetchAll();
    }
    public static function forShop($shop_id){
        $sql = "SELECT r.*, u.first_name, u.last_name FROM reviews r
                JOIN users u ON u.id = r.user_id
                WHERE r.shop_id=? ORDER BY r.review_date DESC, r.id DESC";
        $st = db()->prepare($sql);
        $st->execute([$shop_id]);
        return $st->fetchAll();
    }
    public static function oneStarCustomersByShop(){
        $sql = "SELECT s.id AS shop_id, s.name AS shop_name, u.first_name, u.last_name, u.email, u.phone, r.review_date
                FROM reviews r
                JOIN users u ON u.id = r.user_id
                JOIN shops s ON s.id = r.shop_id
                WHERE r.rating = 1
                ORDER BY s.name, u.last_name";
        return db()->query($sql)->fetchAll();
    }
}

// ======================[ VIEW HELPERS / LAYOUT ]======================

function header_html($title){
    echo "<!doctype html><html lang='en'><head><meta charset='utf-8'>";
    echo "<meta name='viewport' content='width=device-width, initial-scale=1'>";
    echo "<title>".e(APP_NAME.' — '.$title)."</title>";
    // Bootstrap 5 CDN (ok for marking)
    echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' rel='stylesheet'>";
    echo "<style>body{padding-top:72px}.card{border-radius:1rem}.nav-link.active{font-weight:700}</style>";
    echo "</head><body>";
    nav();
    echo "<main class='container'>";
}

function footer_html(){
    echo "</main><script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js'></script></body></html>";
}

function nav(){
    $u = current_user();
    echo "<nav class='navbar navbar-expand-lg navbar-dark bg-dark fixed-top'><div class='container'>";
    echo "<a class='navbar-brand' href='?route=home'>".e(APP_NAME)."</a>";
    echo "<button class='navbar-toggler' type='button' data-bs-toggle='collapse' data-bs-target='#nav'>";
    echo "<span class='navbar-toggler-icon'></span></button>";
    echo "<div class='collapse navbar-collapse' id='nav'><ul class='navbar-nav me-auto'>";
    echo "<li class='nav-item'><a class='nav-link' href='?route=shops'>Shops</a></li>";
    if ($u && $u['role']==='customer') {
        echo "<li class='nav-item'><a class='nav-link' href='?route=my_reviews'>My Reviews</a></li>";
        echo "<li class='nav-item'><a class='nav-link' href='?route=new_review'>Write Review</a></li>";
    }
    if ($u && $u['role']==='admin') {
        echo "<li class='nav-item'><a class='nav-link' href='?route=admin_dashboard'>Admin</a></li>";
    }
    echo "</ul><ul class='navbar-nav ms-auto'>";
    if ($u) {
        echo "<li class='nav-item'><span class='navbar-text me-2'>Hello, ".e($u['first_name'])."</span></li>";
        echo "<li class='nav-item'><a class='btn btn-outline-light' href='?route=logout'>Logout</a></li>";
    } else {
        echo "<li class='nav-item'><a class='btn btn-outline-light me-2' href='?route=login'>Login</a></li>";
        echo "<li class='nav-item'><a class='btn btn-warning' href='?route=register'>Register</a></li>";
    }
    echo "</ul></div></div></nav>";
}

function flash(){
    if (!empty($_SESSION['flash'])){
        echo "<div class='alert alert-info my-3'>".e($_SESSION['flash'])."</div>";
        unset($_SESSION['flash']);
    }
}

// =========================[ CONTROLLERS ]=========================

function home(){
    header_html('Home');
    echo "<div class='p-5 mb-4 bg-light rounded-3'><div class='container py-5'>";
    echo "<h1 class='display-5 fw-bold'>Welcome to ".e(APP_NAME)."</h1>";
    echo "<p class='col-md-8 fs-5'>Rate our franchise shops and read your past reviews. Admins can manage shops and view analytics.</p>";
    echo "<a class='btn btn-primary btn-lg me-2' href='?route=shops'>Browse Shops</a>";
    echo "<a class='btn btn-outline-secondary btn-lg' href='?route=register'>Register</a>";
    echo "</div></div>";
    // Show averages
    echo "<h2 class='h4 mb-3'>Average Ratings</h2>";
    echo "<div class='row g-3'>";
    foreach(Shop::averages() as $row){
        echo "<div class='col-md-4'><div class='card h-100'><div class='card-body'>";
        echo "<h5 class='card-title'>".e($row['name'])."</h5>";
        echo "<p class='card-text mb-1'><strong>City:</strong> ".e($row['city'])."</p>";
        $avg = $row['avg_rating'] !== null ? $row['avg_rating'] : '—';
        echo "<p class='card-text mb-1'><strong>Average:</strong> ".e($avg)." ⭐</p>";
        echo "<p class='card-text'><strong>Reviews:</strong> ".e($row['review_count'])."</p>";
        echo "<a class='btn btn-sm btn-outline-primary' href='?route=shop_reviews&id=".e($row['id'])."'>View Reviews</a>";
        echo "</div></div></div>";
    }
    echo "</div>";
    footer_html();
}

function register(){
    if (is_post()){
        $first = trim($_POST['first_name'] ?? '');
        $last  = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm'] ?? '';
        $errors = [];
        if ($first==='') $errors[] = 'First name is required';
        if ($last==='') $errors[] = 'Last name is required';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required';
        if ($username==='') $errors[] = 'Username is required';
        if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters';
        if ($password !== $confirm) $errors[] = 'Passwords do not match';
        if (User::findByUsername($username)) $errors[] = 'Username already taken';
        if (empty($errors)){
            $id = User::create($first,$last,$email,$phone,$username,$password);
            $_SESSION['flash'] = 'Registration successful. Please log in.';
            redirect('login');
        }
    }

    header_html('Register');
    echo "<div class='row justify-content-center'><div class='col-lg-6'>";
    echo "<div class='card'><div class='card-body'><h1 class='h4 mb-3'>Create an account</h1>";
    flash();
    if (!empty($errors)) echo "<div class='alert alert-danger'>".e(implode('\n', $errors))."</div>";
    echo "<form method='post'>";
    echo input('first_name','First name');
    echo input('last_name','Last name');
    echo input('email','Email','email');
    echo input('phone','Phone','text');
    echo input('username','Username');
    echo input('password','Password','password');
    echo input('confirm','Confirm Password','password');
    echo "<button class='btn btn-primary'>Register</button>";
    echo " <a class='btn btn-link' href='?route=login'>Have an account? Login</a>";
    echo "</form></div></div></div></div>";
    footer_html();
}

function input($name,$label,$type='text',$value=null){
    $v = $value ?? ($_POST[$name] ?? '');
    return "<div class='mb-3'><label class='form-label' for='".e($name)."'>".e($label)."</label><input required class='form-control' type='".e($type)."' id='".e($name)."' name='".e($name)."' value='".e($v)."'></div>";
}

function login(){
    if (is_post()){
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $user = User::findByUsername($username);
        if ($user && password_verify($password, $user['password_hash'])){
            $_SESSION['user'] = $user; // stored as array
            $_SESSION['flash'] = 'Welcome back, '.$user['first_name'].'!';
            redirect('home');
        } else {
            $error = 'Invalid credentials';
        }
    }

    header_html('Login');
    echo "<div class='row justify-content-center'><div class='col-lg-5'>";
    echo "<div class='card'><div class='card-body'><h1 class='h4 mb-3'>Login</h1>";
    flash();
    if (!empty($error)) echo "<div class='alert alert-danger'>".e($error)."</div>";
    echo "<form method='post'>";
    echo input('username','Username');
    echo input('password','Password','password');
    echo "<button class='btn btn-primary'>Login</button>";
    echo " <a class='btn btn-link' href='?route=register'>Register</a>";
    echo "</form></div></div></div></div>";
    footer_html();
}

function logout(){ session_destroy(); redirect('login'); }

function shops(){
    header_html('Shops');
    echo "<h1 class='h4 mb-3'>Our Shops</h1>"; flash();
    echo "<div class='row g-3'>";
    foreach(Shop::all() as $s){
        echo "<div class='col-md-4'><div class='card h-100'><div class='card-body'>";
        echo "<h5 class='card-title'>".e($s['name'])."</h5>";
        echo "<p class='card-text'>".e($s['address'])." — ".e($s['city'])."</p>";
        echo "<a class='btn btn-sm btn-outline-primary' href='?route=shop_reviews&id=".e($s['id'])."'>View Reviews</a>";
        echo "</div></div></div>";
    }
    echo "</div>";
    footer_html();
}

function shop_reviews(){
    $id = (int)($_GET['id'] ?? 0);
    $shop = Shop::find($id);
    if (!$shop) redirect('shops');
    header_html('Shop Reviews');
    echo "<h1 class='h4 mb-3'>Reviews — ".e($shop['name'])."</h1>";
    echo "<p class='text-muted'>".e($shop['address'])." — ".e($shop['city'])."</p>";
    echo "<div class='list-group mb-4'>";
    $rows = Review::forShop($id);
    if (!$rows){
        echo "<div class='alert alert-secondary'>No reviews yet.</div>";
    } else {
        foreach($rows as $r){
            echo "<div class='list-group-item'>";
            echo "<div class='d-flex justify-content-between'>";
            echo "<strong>".e($r['first_name'].' '.$r['last_name'])."</strong>";
            echo "<span>".e($r['rating'])." ⭐ — ".e($r['review_date'])."</span>";
            echo "</div>";
            if (!empty($r['body'])) echo "<div class='mt-2'>".nl2br(e($r['body']))."</div>";
            echo "</div>";
        }
    }
    echo "</div>";
    if (current_user() && current_user()['role']==='customer'){
        echo "<a class='btn btn-primary' href='?route=new_review&shop_id=".e($shop['id'])."'>Write a Review</a>";
    }
    footer_html();
}

function new_review(){
    require_login();
    $u = current_user();
    $shop_id = (int)($_GET['shop_id'] ?? 0);
    if (is_post()){
        $shop_id = (int)($_POST['shop_id'] ?? 0);
        $rating = (int)($_POST['rating'] ?? 0);
        $body = trim($_POST['body'] ?? '');
        $date = $_POST['review_date'] ?? date('Y-m-d');
        $errors = [];
        if (!$shop_id || !Shop::find($shop_id)) $errors[] = 'Please choose a valid shop';
        if ($rating < 1 || $rating > 5) $errors[] = 'Rating must be 1–5';
        if (!$date) $errors[] = 'Date is required';
        if (empty($errors)){
            Review::create($u['id'],$shop_id,$rating,$body,$date);
            $_SESSION['flash'] = 'Review recorded. Thank you!';
            redirect('my_reviews');
        }
    }

    header_html('New Review');
    echo "<div class='row justify-content-center'><div class='col-lg-6'>";
    echo "<div class='card'><div class='card-body'><h1 class='h4 mb-3'>Write a Review</h1>";
    flash();
    if (!empty($errors)) echo "<div class='alert alert-danger'>".e(implode('\n', $errors))."</div>";
    echo "<form method='post'>";
    echo "<div class='mb-3'><label class='form-label'>Shop</label><select class='form-select' name='shop_id' required>";
    foreach(Shop::all() as $s){
        $sel = ($s['id']==$shop_id)?'selected':'';
        echo "<option value='".e($s['id'])."' $sel>".e($s['name'].' — '.$s['city'])."</option>";
    }
    echo "</select></div>";
    echo "<div class='mb-3'><label class='form-label'>Rating (1=poor, 5=excellent)</label>";
//    echo "<input class='form-range' type='range' min='1' max='5' name='rating' value='".e($_POST['rating'] ?? 5)."' oninput='document.getElementById("ratingOut").innerText=this.value'>";
    echo "<input class='form-range' type='range' min='1' max='5' name='rating' value='" . e($_POST['rating'] ?? 5) . "' oninput='document.getElementById(\"ratingOut\").innerText=this.value'>";
    echo " <span id='ratingOut' class='ms-2'>".e($_POST['rating'] ?? 5)."</span> ⭐</div>";
    echo "<div class='mb-3'><label class='form-label'>Review (optional)</label><textarea class='form-control' name='body' rows='4'>".e($_POST['body'] ?? '')."</textarea></div>";
    echo "<div class='mb-3'><label class='form-label'>Review Date</label><input class='form-control' type='date' name='review_date' value='".e($_POST['review_date'] ?? date('Y-m-d'))."' required></div>";
    echo "<button class='btn btn-primary'>Submit</button>";
    echo " <a class='btn btn-outline-secondary' href='?route=my_reviews'>Cancel</a>";
    echo "</form></div></div></div></div>";
    footer_html();
}

function my_reviews(){
    require_login();
    $u = current_user();
    $rows = Review::forUser($u['id']);
    header_html('My Reviews');
    echo "<h1 class='h4 mb-3'>My Reviews</h1>"; flash();
    if (!$rows){ echo "<div class='alert alert-secondary'>You have no reviews yet.</div>"; }
    foreach($rows as $r){
        echo "<div class='card mb-3'><div class='card-body'>";
        echo "<div class='d-flex justify-content-between'>";
        echo "<strong>".e($r['shop_name'])."</strong>";
        echo "<span>".e($r['rating'])." ⭐ — ".e($r['review_date'])."</span>";
        echo "</div>";
        if (!empty($r['body'])) echo "<div class='mt-2'>".nl2br(e($r['body']))."</div>";
        echo "</div></div>";
    }
    footer_html();
}

// ---------------- Admin -----------------

function admin_dashboard(){
    require_admin();
    header_html('Admin');
    echo "<h1 class='h4 mb-3'>Admin Dashboard</h1>"; flash();

    echo "<div class='row g-3'>";
    // Shops management
    echo "<div class='col-lg-6'><div class='card h-100'><div class='card-body'>";
    echo "<h2 class='h5'>Manage Shops</h2>";
    echo "<a class='btn btn-sm btn-primary mb-2' href='?route=shop_new'>Add Shop</a>";
    echo "<div class='list-group'>";
    foreach(Shop::all() as $s){
        echo "<div class='list-group-item d-flex justify-content-between align-items-center'>";
        echo "<div><strong>".e($s['name'])."</strong><div class='small text-muted'>".e($s['address'])." — ".e($s['city'])."</div></div>";
        echo "<div>";
        echo "<a class='btn btn-sm btn-outline-primary me-1' href='?route=shop_edit&id=".e($s['id'])."'>Edit</a>";
//        echo "<a class='btn btn-sm btn-outline-danger' href='?route=shop_delete&id=".e($s['id'])."' onclick='return confirm("Delete this shop?")'>Delete</a>";
        echo "<a class='btn btn-sm btn-outline-danger' href='?route=shop_delete&id=" . e($s['id']) . "' onclick='return confirm(\"Delete this shop?\")'>Delete</a>";
        echo " <a class='btn btn-sm btn-outline-secondary ms-1' href='?route=admin_shop_reviews&id=".e($s['id'])."'>Reviews</a>";
        echo "</div></div>";
    }
    echo "</div></div></div>";

    // Analytics
    echo "<div class='col-lg-6'><div class='card h-100'><div class='card-body'>";
    echo "<h2 class='h5'>Analytics</h2>";
    echo "<h3 class='h6 mt-3'>Average Rating per Shop</h3>";
    echo "<ul class='list-group mb-3'>";
    foreach(Shop::averages() as $row){
        $avg = $row['avg_rating'] !== null ? $row['avg_rating'] : '—';
        echo "<li class='list-group-item d-flex justify-content-between align-items-center'>".e($row['name'])."<span>".e($avg)." ⭐ (".e($row['review_count']).")</span></li>";
    }
    echo "</ul>";
    echo "<h3 class='h6 mt-3'>Customers who gave 1‑star</h3>";
    $ones = Review::oneStarCustomersByShop();
    if (!$ones) {
        echo "<div class='alert alert-secondary'>No 1-star reviews found.</div>";
    } else {
        echo "<div class='table-responsive'><table class='table table-sm'>";
        echo "<thead><tr><th>Shop</th><th>Customer</th><th>Email</th><th>Phone</th><th>Date</th></tr></thead><tbody>";
        foreach($ones as $o){
            echo "<tr><td>".e($o['shop_name'])."</td><td>".e($o['first_name'].' '.$o['last_name'])."</td><td>".e($o['email'])."</td><td>".e($o['phone'])."</td><td>".e($o['review_date'])."</td></tr>";
        }
        echo "</tbody></table></div>";
    }
    echo "</div></div></div>";

    echo "</div>"; // row
    footer_html();
}

function shop_new(){
    require_admin();
    if (is_post()){
        $name = trim($_POST['name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        if ($name && $address && $city){
            Shop::create($name,$address,$city);
            $_SESSION['flash'] = 'Shop added.';
            redirect('admin_dashboard');
        } else $error = 'All fields are required';
    }

    header_html('Add Shop');
    echo "<div class='row justify-content-center'><div class='col-lg-6'><div class='card'><div class='card-body'>";
    echo "<h1 class='h5 mb-3'>Add Shop</h1>";
    if (!empty($error)) echo "<div class='alert alert-danger'>".e($error)."</div>";
    echo "<form method='post'>";
    echo input('name','Name');
    echo input('address','Address');
    echo input('city','City');
    echo "<button class='btn btn-primary'>Save</button> <a class='btn btn-outline-secondary' href='?route=admin_dashboard'>Cancel</a>";
    echo "</form></div></div></div></div>";
    footer_html();
}

function shop_edit(){
    require_admin();
    $id = (int)($_GET['id'] ?? 0);
    $shop = Shop::find($id); if (!$shop) redirect('admin_dashboard');
    if (is_post()){
        $name = trim($_POST['name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        if ($name && $address && $city){
            Shop::update($id,$name,$address,$city);
            $_SESSION['flash'] = 'Shop updated.';
            redirect('admin_dashboard');
        } else $error = 'All fields are required';
    }
    header_html('Edit Shop');
    echo "<div class='row justify-content-center'><div class='col-lg-6'><div class='card'><div class='card-body'>";
    echo "<h1 class='h5 mb-3'>Edit Shop</h1>";
    if (!empty($error)) echo "<div class='alert alert-danger'>".e($error)."</div>";
    echo "<form method='post'>";
    echo input('name','Name','text',$shop['name']);
    echo input('address','Address','text',$shop['address']);
    echo input('city','City','text',$shop['city']);
    echo "<button class='btn btn-primary'>Save</button> <a class='btn btn-outline-secondary' href='?route=admin_dashboard'>Cancel</a>";
    echo "</form></div></div></div></div>";
    footer_html();
}

function shop_delete(){
    require_admin();
    $id = (int)($_GET['id'] ?? 0);
    if ($id) { Shop::delete($id); $_SESSION['flash']='Shop deleted.'; }
    redirect('admin_dashboard');
}

function admin_shop_reviews(){
    require_admin();
    $id = (int)($_GET['id'] ?? 0);
    $shop = Shop::find($id); if(!$shop) redirect('admin_dashboard');
    $rows = Review::forShop($id);
    header_html('Shop Reviews (Admin)');
    echo "<h1 class='h4 mb-3'>Reviews — ".e($shop['name'])."</h1>";
    if (!$rows){ echo "<div class='alert alert-secondary'>No reviews yet.</div>"; }
    else {
        echo "<div class='table-responsive'><table class='table'>";
        echo "<thead><tr><th>Customer</th><th>Rating</th><th>Date</th><th>Review</th></tr></thead><tbody>";
        foreach($rows as $r){
            echo "<tr>";
            echo "<td>".e($r['first_name'].' '.$r['last_name'])."</td>";
            echo "<td>".e($r['rating'])." ⭐</td>";
            echo "<td>".e($r['review_date'])."</td>";
            echo "<td>".nl2br(e($r['body']))."</td>";
            echo "</tr>";
        }
        echo "</tbody></table></div>";
    }
    echo "<a class='btn btn-outline-secondary' href='?route=admin_dashboard'>&larr; Back</a>";
    footer_html();
}

// =========================[ ROUTER ]=========================

$route = $_GET['route'] ?? 'home';
$routes = [
    'home' => 'home',
    'register' => 'register',
    'login' => 'login',
    'logout' => 'logout',
    'shops' => 'shops',
    'shop_reviews' => 'shop_reviews',
    'new_review' => 'new_review',
    'my_reviews' => 'my_reviews',
    // Admin
    'admin_dashboard' => 'admin_dashboard',
    'shop_new' => 'shop_new',
    'shop_edit' => 'shop_edit',
    'shop_delete' => 'shop_delete',
    'admin_shop_reviews' => 'admin_shop_reviews',
];

if (isset($routes[$route])) {
    call_user_func($routes[$route]);
} else {
    http_response_code(404);
    header_html('Not Found');
    echo "<div class='alert alert-danger mt-4'>Page not found.</div>";
    footer_html();
}
