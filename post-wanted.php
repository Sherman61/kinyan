<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/options.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$editing = $id > 0;
$post = [];
if ($editing) {
    if (!owns_listing('wanted_posts', $id) && !is_admin()) die('Not allowed.');
    $stmt = db()->prepare('SELECT * FROM wanted_posts WHERE id = ?');
    $stmt->execute([$id]);
    $post = $stmt->fetch() ?: [];
}
if (is_post()) {
    verify_csrf();
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
    <form method="post" class="form-grid">
        <?= csrf_field() ?>
        <label class="full">Title<input required name="title" value="<?= e($post['title'] ?? '') ?>" placeholder="Looking for Toyota Sienna 2018+ under 90k miles"></label>
        <label>Preferred make<input name="preferred_make" value="<?= e($post['preferred_make'] ?? '') ?>"></label>
        <label>Preferred model<input name="preferred_model" value="<?= e($post['preferred_model'] ?? '') ?>"></label>
        <label>Minimum year<input type="number" name="min_year" value="<?= e($post['min_year'] ?? '') ?>"></label>
        <label>Maximum year<input type="number" name="max_year" value="<?= e($post['max_year'] ?? '') ?>"></label>
        <label>Maximum mileage<input type="number" name="max_mileage" value="<?= e($post['max_mileage'] ?? '') ?>"></label>
        <label>Minimum budget<input type="number" name="min_budget" value="<?= e($post['min_budget'] ?? '') ?>"></label>
        <label>Maximum budget<input type="number" name="max_budget" value="<?= e($post['max_budget'] ?? '') ?>"></label>
        <label>Preferred body type<select name="preferred_body_type"><option value="">Any</option><?php foreach ($bodyTypes as $v): ?><option <?= selected($post['preferred_body_type'] ?? '', $v) ?>><?= e($v) ?></option><?php endforeach; ?></select></label>
        <label>Transmission<select name="preferred_transmission"><option value="">Any</option><?php foreach ($transmissions as $v): ?><option <?= selected($post['preferred_transmission'] ?? '', $v) ?>><?= e($v) ?></option><?php endforeach; ?></select></label>
        <label>Fuel type<select name="preferred_fuel_type"><option value="">Any</option><?php foreach ($fuelTypes as $v): ?><option <?= selected($post['preferred_fuel_type'] ?? '', $v) ?>><?= e($v) ?></option><?php endforeach; ?></select></label>
        <label class="check"><input type="checkbox" name="must_have_clean_title" <?= checked($post['must_have_clean_title'] ?? 0) ?>> Must have clean title</label>
        <label>Location<input required name="location" value="<?= e($post['location'] ?? '') ?>"></label>
        <label>Distance willing to travel<input type="number" name="travel_distance" value="<?= e($post['travel_distance'] ?? 25) ?>"></label>
        <label>Buyer name<input required name="buyer_name" value="<?= e($post['buyer_name'] ?? current_user()['name']) ?>"></label>
        <label>Buyer phone<input required name="buyer_phone" value="<?= e($post['buyer_phone'] ?? current_user()['phone']) ?>"></label>
        <label>Buyer email optional<input type="email" name="buyer_email" value="<?= e($post['buyer_email'] ?? current_user()['email']) ?>"></label>
        <label>Preferred contact<select name="preferred_contact_method"><?php foreach ($contactMethods as $v): ?><option <?= selected($post['preferred_contact_method'] ?? 'Any', $v) ?>><?= e($v) ?></option><?php endforeach; ?></select></label>
        <label class="full">Description<textarea required name="description" rows="6"><?= e($post['description'] ?? '') ?></textarea></label>
        <button class="button full" type="submit" <?= $editing ? 'data-confirm="Save these wanted post changes?"' : '' ?>><?= $editing ? 'Save changes' : 'Submit wanted post' ?></button>
    </form>
</section>
<?php render_footer(); ?>
