<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/options.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$editing = $id > 0;
$post = [];
if ($editing) {
    if (!owns_listing('wanted_posts', $id) && !is_admin()) render_status_page(403, 'Access denied', 'You can only edit your own wanted posts.', ['Go to dashboard' => 'dashboard.php', 'Browse wanted posts' => 'wanted.php']);
    $stmt = db()->prepare('SELECT * FROM wanted_posts WHERE id = ?');
    $stmt->execute([$id]);
    $post = $stmt->fetch() ?: [];
}
if (is_post()) {
    verify_csrf();
    require_app_rate_limit($editing ? 'edit_wanted' : 'post_wanted', 12, 60 * 60);
    $data = [
        'title'=>trim($_POST['title'] ?? ''),'preferred_make'=>trim($_POST['preferred_make'] ?? ''),'preferred_model'=>trim($_POST['preferred_model'] ?? ''),
        'min_year'=>($_POST['min_year'] ?? '') !== '' ? (int)$_POST['min_year'] : null,'max_year'=>($_POST['max_year'] ?? '') !== '' ? (int)$_POST['max_year'] : null,
        'max_mileage'=>($_POST['max_mileage'] ?? '') !== '' ? (int)$_POST['max_mileage'] : null,'min_budget'=>($_POST['min_budget'] ?? '') !== '' ? (float)$_POST['min_budget'] : null,
        'max_budget'=>($_POST['max_budget'] ?? '') !== '' ? (float)$_POST['max_budget'] : null,'preferred_body_type'=>validate_choice($_POST['preferred_body_type'] ?? '', $bodyTypes, ''),
        'preferred_transmission'=>validate_choice($_POST['preferred_transmission'] ?? '', $transmissions, ''),'preferred_fuel_type'=>validate_choice($_POST['preferred_fuel_type'] ?? '', $fuelTypes, ''),
        'must_have_clean_title'=>isset($_POST['must_have_clean_title']) ? 1 : 0,'location'=>trim($_POST['location'] ?? ''),'travel_distance'=>(int)($_POST['travel_distance'] ?? 25),
        'buyer_name'=>trim($_POST['buyer_name'] ?? ''),'buyer_phone'=>trim($_POST['buyer_phone'] ?? ''),'buyer_email'=>trim($_POST['buyer_email'] ?? ''),
        'preferred_contact_method'=>validate_choice($_POST['preferred_contact_method'] ?? '', $contactMethods, 'Any'),'description'=>trim($_POST['description'] ?? '')
    ];
    $errors = [];
    foreach (['title','location','buyer_name','buyer_phone','description'] as $required) if ($data[$required] === '') $errors[] = ucfirst(str_replace('_',' ', $required)) . ' is required.';
    if ($data['buyer_email'] && !filter_var($data['buyer_email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email.';
    if ($errors) {
        foreach ($errors as $error) flash('error', $error);
        $post = array_merge($post, $data);
    } else {
        if ($editing) {
            $nextStatus = edited_listing_status((string)$post['status'], 'wanted_posts');
            $stmt = db()->prepare('UPDATE wanted_posts SET title=?, preferred_make=?, preferred_model=?, min_year=?, max_year=?, max_mileage=?, min_budget=?, max_budget=?, preferred_body_type=?, preferred_transmission=?, preferred_fuel_type=?, must_have_clean_title=?, location=?, travel_distance=?, buyer_name=?, buyer_phone=?, buyer_email=?, preferred_contact_method=?, description=?, status=? WHERE id=?');
            $stmt->execute([...array_values($data), $nextStatus, $id]);
            flash('success', $nextStatus === 'pending' ? 'Wanted post changes were submitted for approval.' : 'Wanted post updated.');
        } else {
            $stmt = db()->prepare('INSERT INTO wanted_posts (user_id,title,preferred_make,preferred_model,min_year,max_year,max_mileage,min_budget,max_budget,preferred_body_type,preferred_transmission,preferred_fuel_type,must_have_clean_title,location,travel_distance,buyer_name,buyer_phone,buyer_email,preferred_contact_method,description,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([current_user()['id'], ...array_values($data), new_listing_status()]);
            flash('success', new_listing_status() === 'active' ? 'Your wanted post is live.' : 'Your wanted post was submitted for approval.');
        }
        redirect('dashboard.php');
    }
}
render_header($editing ? 'Edit Wanted Post' : 'Post Wanted Car', 'Post the car you are looking for on Kinyan.');
?>
<section class="form-shell">
    <h1><?= $editing ? 'Edit wanted post' : 'Post what you’re looking for' ?></h1>
    <p class="form-intro">Describe the car you want and how flexible you are. Leave optional fields blank when you are open to options.</p>
    <form method="post" class="form-grid">
        <?= csrf_field() ?>
        <div class="form-section full"><h2>Vehicle wanted</h2><p>Use broad ranges if you are flexible.</p></div>
        <label class="full">Request title<input required name="title" value="<?= e($post['title'] ?? '') ?>" placeholder="Looking for Toyota Sienna 2018+ under 90k miles"></label>
        <label>Preferred make<input name="preferred_make" value="<?= e($post['preferred_make'] ?? '') ?>" placeholder="Toyota, Honda, Lexus"></label>
        <label>Preferred model<input name="preferred_model" value="<?= e($post['preferred_model'] ?? '') ?>" placeholder="Sienna, Odyssey, RX 350"></label>
        <label>Earliest year<input type="number" min="1900" max="<?= (int)date('Y') + 1 ?>" name="min_year" value="<?= e($post['min_year'] ?? '') ?>" placeholder="2018"></label>
        <label>Latest year<input type="number" min="1900" max="<?= (int)date('Y') + 1 ?>" name="max_year" value="<?= e($post['max_year'] ?? '') ?>" placeholder="<?= (int)date('Y') ?>"></label>
        <label>Maximum mileage<input type="number" min="0" inputmode="numeric" name="max_mileage" value="<?= e($post['max_mileage'] ?? '') ?>" placeholder="90000"></label>
        <label>Minimum budget<input type="number" min="0" inputmode="numeric" name="min_budget" value="<?= e($post['min_budget'] ?? '') ?>" placeholder="15000"><small>Optional. Leave blank if only your max matters.</small></label>
        <label>Maximum budget<input type="number" min="0" inputmode="numeric" name="max_budget" value="<?= e($post['max_budget'] ?? '') ?>" placeholder="30000"></label>
        <label>Preferred body type<select name="preferred_body_type"><option value="">Any</option><?php foreach ($bodyTypes as $v): ?><option <?= selected($post['preferred_body_type'] ?? '', $v) ?>><?= e($v) ?></option><?php endforeach; ?></select></label>
        <label>Transmission<select name="preferred_transmission"><option value="">Any</option><?php foreach ($transmissions as $v): ?><option <?= selected($post['preferred_transmission'] ?? '', $v) ?>><?= e($v) ?></option><?php endforeach; ?></select></label>
        <label>Fuel type<select name="preferred_fuel_type"><option value="">Any</option><?php foreach ($fuelTypes as $v): ?><option <?= selected($post['preferred_fuel_type'] ?? '', $v) ?>><?= e($v) ?></option><?php endforeach; ?></select></label>
        <label class="check"><input type="checkbox" name="must_have_clean_title" <?= checked($post['must_have_clean_title'] ?? 0) ?>> Must have clean title</label>
        <div class="form-section full"><h2>Location and contact</h2><p>Sellers use this to decide whether their car is a good match.</p></div>
        <label>Location<input required name="location" value="<?= e($post['location'] ?? '') ?>" placeholder="Lakewood, NJ"></label>
        <label>Distance willing to travel (miles)<input type="number" min="0" inputmode="numeric" name="travel_distance" value="<?= e($post['travel_distance'] ?? 25) ?>" placeholder="25"></label>
        <label>Buyer name<input required name="buyer_name" value="<?= e($post['buyer_name'] ?? current_user()['name']) ?>" placeholder="Your name"></label>
        <label>Buyer phone<input required name="buyer_phone" inputmode="tel" value="<?= e($post['buyer_phone'] ?? current_user()['phone']) ?>" placeholder="732-555-1234"></label>
        <label>Buyer email optional<input type="email" name="buyer_email" value="<?= e($post['buyer_email'] ?? current_user()['email']) ?>" placeholder="you@example.com"></label>
        <label>Preferred contact<select name="preferred_contact_method"><?php foreach ($contactMethods as $v): ?><option <?= selected($post['preferred_contact_method'] ?? 'Any', $v) ?>><?= e($v) ?></option><?php endforeach; ?></select></label>
        <label class="full">Description<textarea required name="description" rows="6" placeholder="Example: Need a reliable 7-8 passenger family car. Prefer clean title, no major accidents, and available to see in Lakewood or nearby. Open to similar minivans."><?= e($post['description'] ?? '') ?></textarea></label>
        <button class="button full" type="submit" <?= $editing ? 'data-confirm="Save these wanted post changes?"' : '' ?>><?= $editing ? 'Save changes' : 'Submit wanted post' ?></button>
    </form>
</section>
<?php render_footer(); ?>
