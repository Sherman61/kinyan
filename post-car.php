<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/options.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$editing = $id > 0;
$car = [];
$existingImages = [];
$libraryImages = [];
if ($editing) {
    if (!owns_listing('car_listings', $id) && !is_admin()) die('Not allowed.');
    $stmt = db()->prepare('SELECT * FROM car_listings WHERE id = ?');
    $stmt->execute([$id]);
    $car = $stmt->fetch() ?: [];
    $existingImages = car_images($id);
    $libraryUserId = (int)($car['user_id'] ?? current_user()['id']);
    $libraryImages = car_image_library_for_user($libraryUserId, $id);
}
if (is_post()) {
    verify_csrf();
    require_app_rate_limit($editing ? 'edit_car' : 'post_car', 12, 60 * 60);
    $data = [
        'title'=>trim($_POST['title'] ?? ''),'make'=>trim($_POST['make'] ?? ''),'model'=>trim($_POST['model'] ?? ''),'trim'=>trim($_POST['trim'] ?? ''),
        'year'=>(int)($_POST['year'] ?? 0),'mileage'=>(int)($_POST['mileage'] ?? 0),'price'=>(float)($_POST['price'] ?? 0),'vin'=>trim($_POST['vin'] ?? ''),
        'exterior_color'=>trim($_POST['exterior_color'] ?? ''),'interior_color'=>trim($_POST['interior_color'] ?? ''),'body_type'=>validate_choice($_POST['body_type'] ?? '', $bodyTypes, 'Other'),
        'transmission'=>validate_choice($_POST['transmission'] ?? '', $transmissions, 'Other'),'drivetrain'=>trim($_POST['drivetrain'] ?? ''),'fuel_type'=>validate_choice($_POST['fuel_type'] ?? '', $fuelTypes, 'Other'),
        'engine'=>trim($_POST['engine'] ?? ''),'condition_status'=>validate_choice($_POST['condition_status'] ?? '', $conditions, 'Good'),'accident_history'=>trim($_POST['accident_history'] ?? ''),
        'clean_title'=>isset($_POST['clean_title']) ? 1 : 0,'lease_takeover'=>isset($_POST['lease_takeover']) ? 1 : 0,
        'lease_months_left'=>null,
        'lease_monthly_payment'=>($_POST['lease_monthly_payment'] ?? '') !== '' ? (float)$_POST['lease_monthly_payment'] : null,
        'lease_down_payment'=>($_POST['lease_down_payment'] ?? '') !== '' ? (float)$_POST['lease_down_payment'] : null,
        'lease_mileage_allowance'=>($_POST['lease_mileage_allowance'] ?? '') !== '' ? (int)$_POST['lease_mileage_allowance'] : null,
        'lease_miles_used'=>($_POST['lease_miles_used'] ?? '') !== '' ? (int)$_POST['lease_miles_used'] : null,
        'lease_transfer_fee'=>($_POST['lease_transfer_fee'] ?? '') !== '' ? (float)$_POST['lease_transfer_fee'] : null,
        'lease_company'=>trim($_POST['lease_company'] ?? ''),
        'lease_end_date'=>trim($_POST['lease_end_date'] ?? '') ?: null,
        'description'=>trim($_POST['description'] ?? ''),'city'=>trim($_POST['city'] ?? ''),'state'=>strtoupper(trim($_POST['state'] ?? '')),
        'zip'=>trim($_POST['zip'] ?? ''),'seller_name'=>trim($_POST['seller_name'] ?? ''),'seller_phone'=>trim($_POST['seller_phone'] ?? ''),'seller_email'=>trim($_POST['seller_email'] ?? ''),
        'preferred_contact_method'=>validate_choice($_POST['preferred_contact_method'] ?? '', $contactMethods, 'Any')
    ];
    if ($data['lease_takeover']) {
        $data['lease_months_left'] = lease_months_left($data['lease_end_date']);
        $data['price'] = $data['lease_down_payment'] ?? 0;
    }
    $errors = [];
    foreach (['title','make','model','description','city','state','seller_name','seller_phone'] as $required) if ($data[$required] === '') $errors[] = ucfirst(str_replace('_',' ', $required)) . ' is required.';
    if ($data['year'] < 1900 || $data['year'] > ((int)date('Y') + 1)) $errors[] = 'Enter a valid year.';
    if (!$data['lease_takeover'] && $data['price'] <= 0) $errors[] = 'Enter a valid asking price.';
    if ($data['mileage'] < 0) $errors[] = 'Enter valid mileage.';
    if ($data['lease_takeover'] && (!$data['lease_end_date'] || !$data['lease_monthly_payment'])) $errors[] = 'Lease takeover posts need a lease end date and monthly payment.';
    if ($data['lease_takeover'] && $data['lease_months_left'] !== null && $data['lease_months_left'] <= 0) $errors[] = 'Lease end date must be in the future.';
    if ($data['seller_email'] && !filter_var($data['seller_email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email.';
    if ($errors) {
        foreach ($errors as $error) flash('error', $error);
        $car = array_merge($car, $data);
    } else {
        try {
            db()->beginTransaction();
            if ($editing) {
                $nextStatus = edited_listing_status((string)$car['status'], 'car_listings');
                $stmt = db()->prepare('UPDATE car_listings SET title=?, make=?, model=?, trim=?, year=?, mileage=?, price=?, vin=?, exterior_color=?, interior_color=?, body_type=?, transmission=?, drivetrain=?, fuel_type=?, engine=?, condition_status=?, accident_history=?, clean_title=?, lease_takeover=?, lease_months_left=?, lease_monthly_payment=?, lease_down_payment=?, lease_mileage_allowance=?, lease_miles_used=?, lease_transfer_fee=?, lease_company=?, lease_end_date=?, description=?, city=?, state=?, zip=?, seller_name=?, seller_phone=?, seller_email=?, preferred_contact_method=?, status=? WHERE id=?');
                $stmt->execute([...array_values($data), $nextStatus, $id]);
                $galleryResult = update_car_gallery($id, (int)$car['user_id']);
                $imageResult = upload_car_images($id, $_FILES['images'] ?? []);
                db()->commit();
                flash('success', $nextStatus === 'pending' ? 'Listing changes were submitted for approval.' : 'Listing updated.');
                flash_gallery_update_result($galleryResult);
                flash_image_upload_result($imageResult);
                finish_listing_save('dashboard.php');
            } else {
                $status = new_listing_status();
                $stmt = db()->prepare('INSERT INTO car_listings (user_id,title,make,model,trim,year,mileage,price,vin,exterior_color,interior_color,body_type,transmission,drivetrain,fuel_type,engine,condition_status,accident_history,clean_title,lease_takeover,lease_months_left,lease_monthly_payment,lease_down_payment,lease_mileage_allowance,lease_miles_used,lease_transfer_fee,lease_company,lease_end_date,description,city,state,zip,seller_name,seller_phone,seller_email,preferred_contact_method,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
                $stmt->execute([current_user()['id'], ...array_values($data), $status]);
                $newId = (int)db()->lastInsertId();
                $imageResult = upload_car_images($newId, $_FILES['images'] ?? []);
                db()->commit();
                flash('success', $status === 'active' ? 'Your car is live.' : 'Your car was submitted for approval.');
                flash_image_upload_result($imageResult);
                finish_listing_save('dashboard.php');
            }
        } catch (RuntimeException $e) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            flash('error', $e->getMessage());
            $car = array_merge($car, $data);
        } catch (PDOException $e) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            error_log($e->getMessage());
            flash('error', 'We could not save the listing. Please check the form and try again.');
            $car = array_merge($car, $data);
        }
    }
}

function flash_image_upload_result(array $result): void
{
    $saved = (int)($result['saved'] ?? 0);
    $failed = (int)($result['failed'] ?? 0);
    $skipped = (int)($result['skipped'] ?? 0);
    if ($saved > 0) {
        flash('success', $saved . ' photo' . ($saved === 1 ? '' : 's') . ' passed and uploaded.');
    }
    if ($failed > 0 || $skipped > 0) {
        $parts = [];
        if ($failed > 0) {
            $parts[] = $failed . ' failed';
        }
        if ($skipped > 0) {
            $parts[] = $skipped . ' skipped because each listing can have up to 10 photos';
        }
        flash('info', 'Some photos were not added: ' . implode(', ', $parts) . '.');
    }
}

function update_car_gallery(int $listingId, int $ownerId): array
{
    $deleted = 0;
    $attached = 0;

    foreach (($_POST['delete_images'] ?? []) as $imageId) {
        $stmt = db()->prepare('SELECT i.image_path FROM car_images i JOIN car_listings c ON c.id = i.car_listing_id WHERE i.id = ? AND i.car_listing_id = ? AND (c.user_id = ? OR ? = 1)');
        $stmt->execute([(int)$imageId, $listingId, current_user()['id'], is_admin() ? 1 : 0]);
        $path = $stmt->fetchColumn();
        if (!$path) {
            continue;
        }
        db()->prepare('DELETE FROM car_images WHERE id = ?')->execute([(int)$imageId]);
        $deleted++;
        $used = db()->prepare('SELECT COUNT(*) FROM car_images WHERE image_path = ?');
        $used->execute([$path]);
        if ((int)$used->fetchColumn() === 0 && is_file(BASE_PATH . '/' . $path)) {
            @unlink(BASE_PATH . '/' . $path);
        }
    }

    foreach (($_POST['image_titles'] ?? []) as $imageId => $title) {
        $sort = max(0, (int)($_POST['image_sort'][$imageId] ?? 1) - 1);
        $stmt = db()->prepare('UPDATE car_images i JOIN car_listings c ON c.id = i.car_listing_id SET i.image_title = ?, i.sort_order = ? WHERE i.id = ? AND i.car_listing_id = ? AND (c.user_id = ? OR ? = 1)');
        $stmt->execute([trim((string)$title), $sort, (int)$imageId, $listingId, current_user()['id'], is_admin() ? 1 : 0]);
    }

    $countStmt = db()->prepare('SELECT COUNT(*) FROM car_images WHERE car_listing_id = ?');
    $countStmt->execute([$listingId]);
    $slots = max(0, 10 - (int)$countStmt->fetchColumn());
    if ($slots > 0) {
        $sortStmt = db()->prepare('SELECT COALESCE(MAX(sort_order), -1) FROM car_images WHERE car_listing_id = ?');
        $sortStmt->execute([$listingId]);
        $sort = (int)$sortStmt->fetchColumn() + 1;
        $selected = array_slice(array_map('intval', $_POST['library_images'] ?? []), 0, $slots);
        foreach ($selected as $imageId) {
            $stmt = db()->prepare('SELECT i.image_path, i.image_title FROM car_images i JOIN car_listings c ON c.id = i.car_listing_id WHERE i.id = ? AND i.car_listing_id <> ? AND (c.user_id = ? OR ? = 1) AND NOT EXISTS (SELECT 1 FROM car_images existing WHERE existing.car_listing_id = ? AND existing.image_path = i.image_path)');
            $stmt->execute([$imageId, $listingId, $ownerId, is_admin() ? 1 : 0, $listingId]);
            $image = $stmt->fetch();
            if (!$image) {
                continue;
            }
            db()->prepare('INSERT INTO car_images (car_listing_id, image_path, image_title, sort_order) VALUES (?, ?, ?, ?)')->execute([$listingId, $image['image_path'], $image['image_title'], $sort++]);
            $attached++;
        }
    }

    return ['deleted' => $deleted, 'attached' => $attached];
}

function flash_gallery_update_result(array $result): void
{
    if (!empty($result['attached'])) {
        flash('success', (int)$result['attached'] . ' library photo' . ((int)$result['attached'] === 1 ? '' : 's') . ' added.');
    }
    if (!empty($result['deleted'])) {
        flash('info', (int)$result['deleted'] . ' photo' . ((int)$result['deleted'] === 1 ? '' : 's') . ' removed from this listing.');
    }
}

function finish_listing_save(string $path): never
{
    if (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['redirect' => $path]);
        exit;
    }
    redirect($path);
}
render_header($editing ? 'Edit Car Listing' : 'Post Your Car', 'Post a car for sale on Kinyan.');
?>
<section class="form-shell">
    <h1><?= $editing ? 'Edit car listing' : 'Post your car' ?></h1>
    <p class="form-intro">Use the regular sale price for cars being sold. For a lease takeover, check the lease box and fill in the monthly payment, takeover amount, and lease end date.</p>
    <form method="post" enctype="multipart/form-data" class="form-grid" data-upload-progress>
        <?= csrf_field() ?>
        <div class="form-section full"><h2>Vehicle basics</h2><p>These fields appear in search results and on the listing page.</p></div>
        <label class="full">Listing title<input required name="title" value="<?= e($car['title'] ?? '') ?>" placeholder="2019 Toyota Sienna XLE - clean title, 82k miles"></label>
        <label>Make<input required name="make" value="<?= e($car['make'] ?? '') ?>" placeholder="Toyota"></label>
        <label>Model<input required name="model" value="<?= e($car['model'] ?? '') ?>" placeholder="Sienna"></label>
        <label>Trim<input name="trim" value="<?= e($car['trim'] ?? '') ?>" placeholder="XLE, Limited, EX-L"></label>
        <label>Year<input required type="number" min="1900" max="<?= (int)date('Y') + 1 ?>" name="year" value="<?= e($car['year'] ?? '') ?>" placeholder="<?= (int)date('Y') - 4 ?>"></label>
        <label>Mileage<input required type="number" min="0" inputmode="numeric" name="mileage" value="<?= e($car['mileage'] ?? '') ?>" placeholder="82000"></label>
        <label data-price-field><span data-price-label>Asking price</span><input required type="number" min="0" step="1" inputmode="numeric" name="price" value="<?= e($car['price'] ?? '') ?>" placeholder="28500"><small>Required for regular sale listings. Do not include commas or a dollar sign.</small></label>
        <label>VIN optional<input name="vin" value="<?= e($car['vin'] ?? '') ?>" placeholder="17-character VIN if you want to include it"></label>
        <label>Exterior color<input name="exterior_color" value="<?= e($car['exterior_color'] ?? '') ?>" placeholder="White"></label>
        <label>Interior color<input name="interior_color" value="<?= e($car['interior_color'] ?? '') ?>" placeholder="Gray leather"></label>
        <label>Body type<select name="body_type"><?php foreach ($bodyTypes as $v): ?><option <?= selected($car['body_type'] ?? '', $v) ?>><?= e($v) ?></option><?php endforeach; ?></select></label>
        <label>Transmission<select name="transmission"><?php foreach ($transmissions as $v): ?><option <?= selected($car['transmission'] ?? '', $v) ?>><?= e($v) ?></option><?php endforeach; ?></select></label>
        <label>Drivetrain<input name="drivetrain" value="<?= e($car['drivetrain'] ?? '') ?>" placeholder="FWD, AWD, 4WD"></label>
        <label>Fuel type<select name="fuel_type"><?php foreach ($fuelTypes as $v): ?><option <?= selected($car['fuel_type'] ?? '', $v) ?>><?= e($v) ?></option><?php endforeach; ?></select></label>
        <label>Engine<input name="engine" value="<?= e($car['engine'] ?? '') ?>" placeholder="3.5L V6, hybrid, electric"></label>
        <label>Condition<select name="condition_status"><?php foreach ($conditions as $v): ?><option <?= selected($car['condition_status'] ?? '', $v) ?>><?= e($v) ?></option><?php endforeach; ?></select></label>
        <label>Accident history<input name="accident_history" value="<?= e($car['accident_history'] ?? '') ?>" placeholder="No accidents, minor rear bumper repair, unknown"></label>
        <label class="check"><input type="checkbox" name="clean_title" <?= checked($car['clean_title'] ?? 1) ?>> Clean title</label>
        <label class="check"><input type="checkbox" name="lease_takeover" data-lease-toggle <?= checked($car['lease_takeover'] ?? 0) ?>> This is a lease takeover, not a regular sale</label>
        <fieldset class="lease-fields full" data-lease-fields>
            <legend>Lease takeover details</legend>
            <p class="field-note">Fill this section only when someone will take over the remaining lease. Monthly payment and lease end date are required for lease takeover listings.</p>
            <div class="form-grid nested">
                <label>Monthly payment<input type="number" min="0" step="1" inputmode="numeric" name="lease_monthly_payment" value="<?= e($car['lease_monthly_payment'] ?? '') ?>" placeholder="699" data-required-when-lease></label>
                <label>Due at takeover<input type="number" min="0" step="1" inputmode="numeric" name="lease_down_payment" value="<?= e($car['lease_down_payment'] ?? '') ?>" placeholder="2500"><small>Cash due from the new driver at transfer.</small></label>
                <label>Annual mileage allowance<input type="number" min="0" inputmode="numeric" name="lease_mileage_allowance" value="<?= e($car['lease_mileage_allowance'] ?? '') ?>" placeholder="12000"></label>
                <label>Miles used<input type="number" min="0" inputmode="numeric" name="lease_miles_used" value="<?= e($car['lease_miles_used'] ?? '') ?>" placeholder="18500"></label>
                <label>Transfer fee<input type="number" min="0" step="1" inputmode="numeric" name="lease_transfer_fee" value="<?= e($car['lease_transfer_fee'] ?? '') ?>" placeholder="595"></label>
                <label>Lease company<input name="lease_company" value="<?= e($car['lease_company'] ?? '') ?>" placeholder="Toyota Financial, Honda Financial"></label>
                <label>Lease end date<input type="date" name="lease_end_date" data-lease-end-date data-required-when-lease value="<?= e($car['lease_end_date'] ?? '') ?>"></label>
                <label>Months left<input readonly data-lease-months-display value="<?= e($car['lease_months_left'] ?? '') ?>" placeholder="Calculated automatically"></label>
            </div>
        </fieldset>
        <div class="form-section full"><h2>Condition and seller notes</h2><p>Be direct about condition, title, maintenance, and anything a buyer should know before calling.</p></div>
        <label class="full">Description<textarea required name="description" rows="6" placeholder="Example: Clean family minivan, non-smoker, recent tires and brakes, oil changed regularly. Small scratch on rear bumper. Available to show in Lakewood evenings."><?= e($car['description'] ?? '') ?></textarea></label>
        <div class="form-section full"><h2>Location and contact</h2><p>This is what buyers use to contact you directly. Email is optional.</p></div>
        <label>City<input required name="city" value="<?= e($car['city'] ?? '') ?>" placeholder="Lakewood"></label>
        <label>State<input required maxlength="2" name="state" value="<?= e($car['state'] ?? '') ?>" placeholder="NJ"></label>
        <label>ZIP optional<input name="zip" inputmode="numeric" value="<?= e($car['zip'] ?? '') ?>" placeholder="08701"></label>
        <label>Seller name<input required name="seller_name" value="<?= e($car['seller_name'] ?? current_user()['name']) ?>" placeholder="Your name"></label>
        <label>Seller phone<input required name="seller_phone" inputmode="tel" value="<?= e($car['seller_phone'] ?? current_user()['phone']) ?>" placeholder="732-555-1234"></label>
        <label>Seller email optional<input type="email" name="seller_email" value="<?= e($car['seller_email'] ?? current_user()['email']) ?>" placeholder="you@example.com"></label>
        <label>Preferred contact<select name="preferred_contact_method"><?php foreach ($contactMethods as $v): ?><option <?= selected($car['preferred_contact_method'] ?? 'Any', $v) ?>><?= e($v) ?></option><?php endforeach; ?></select></label>
        <?php if ($editing): ?>
        <div class="form-section full"><h2>Photo gallery</h2><p>Existing photos stay on this listing unless you mark them for removal. Use sort numbers to choose the display order.</p></div>
        <?php if ($existingImages): ?>
        <div class="image-manager full">
            <?php foreach ($existingImages as $index => $img): ?>
            <div class="image-manager-item">
                <img src="<?= e($img['image_path']) ?>" alt="<?= e($img['image_title'] ?: $car['title']) ?>">
                <label>Title<input name="image_titles[<?= (int)$img['id'] ?>]" value="<?= e($img['image_title'] ?? '') ?>" placeholder="Front exterior, dashboard, odometer"></label>
                <label>Display position<input type="number" min="1" name="image_sort[<?= (int)$img['id'] ?>]" value="<?= (int)($img['sort_order'] ?? $index) + 1 ?>"></label>
                <label class="check"><input type="checkbox" name="delete_images[]" value="<?= (int)$img['id'] ?>"> Remove from this listing</label>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="field-note full">This listing does not have photos yet.</p>
        <?php endif; ?>
        <?php if ($libraryImages): ?>
        <div class="library-picker full">
            <h3>Add from your photo library</h3>
            <p class="field-note">Choose photos you already uploaded on another listing. They will be attached to this car without removing them from the original listing.</p>
            <div class="library-grid">
                <?php foreach ($libraryImages as $img): ?>
                <label>
                    <input type="checkbox" name="library_images[]" value="<?= (int)$img['id'] ?>">
                    <img src="<?= e($img['image_path']) ?>" alt="<?= e($img['image_title'] ?: 'Library photo') ?>">
                    <span><?= e($img['image_title'] ?: 'Untitled photo') ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
        <label class="full">Photos<input type="file" name="images[]" multiple accept=".jpg,.jpeg,.jfif,.png,.webp,image/jpeg,image/png,image/webp"><small>Upload up to 10 JPG, PNG, or WEBP photos, up to 15MB each. Photos are resized and compressed to WebP automatically.</small></label>
        <button class="button full" type="submit" <?= $editing ? 'data-confirm="Save these listing changes?"' : '' ?>><?= $editing ? 'Save changes' : 'Submit listing' ?></button>
        <div class="upload-progress full" data-upload-status hidden>
            <div class="upload-progress-top"><strong data-upload-stage>Preparing listing</strong><span data-upload-percent>0%</span></div>
            <div class="upload-progress-bar"><span data-upload-bar></span></div>
            <p data-upload-detail>Keep this page open while your photos upload.</p>
        </div>
    </form>
</section>
<?php render_footer(); ?>
