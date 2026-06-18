<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/options.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$editing = $id > 0;
$car = [];
if ($editing) {
    if (!owns_listing('car_listings', $id) && !is_admin()) die('Not allowed.');
    $stmt = db()->prepare('SELECT * FROM car_listings WHERE id = ?');
    $stmt->execute([$id]);
    $car = $stmt->fetch() ?: [];
}
if (is_post()) {
    verify_csrf();
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
                upload_car_images($id, $_FILES['images'] ?? []);
                db()->commit();
                flash('success', $nextStatus === 'pending' ? 'Listing changes were submitted for approval.' : 'Listing updated.');
                redirect('dashboard.php');
            } else {
                $status = new_listing_status();
                $stmt = db()->prepare('INSERT INTO car_listings (user_id,title,make,model,trim,year,mileage,price,vin,exterior_color,interior_color,body_type,transmission,drivetrain,fuel_type,engine,condition_status,accident_history,clean_title,lease_takeover,lease_months_left,lease_monthly_payment,lease_down_payment,lease_mileage_allowance,lease_miles_used,lease_transfer_fee,lease_company,lease_end_date,description,city,state,zip,seller_name,seller_phone,seller_email,preferred_contact_method,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
                $stmt->execute([current_user()['id'], ...array_values($data), $status]);
                $newId = (int)db()->lastInsertId();
                upload_car_images($newId, $_FILES['images'] ?? []);
                db()->commit();
                flash('success', $status === 'active' ? 'Your car is live.' : 'Your car was submitted for approval.');
                redirect('dashboard.php');
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
render_header($editing ? 'Edit Car Listing' : 'Post Your Car', 'Post a car for sale on Kinyan.');
?>
<section class="form-shell">
    <h1><?= $editing ? 'Edit car listing' : 'Post your car' ?></h1>
    <p class="form-intro">Use the regular sale price for cars being sold. For a lease takeover, check the lease box and fill in the monthly payment, takeover amount, and lease end date.</p>
    <form method="post" enctype="multipart/form-data" class="form-grid">
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
        <label class="full">Photos<input type="file" name="images[]" multiple accept=".jpg,.jpeg,.jfif,.png,.webp,image/jpeg,image/png,image/webp"><small>Upload up to 10 JPG, PNG, or WEBP photos. Use clear exterior, interior, odometer, and damage photos when possible.</small></label>
        <button class="button full" type="submit" <?= $editing ? 'data-confirm="Save these listing changes?"' : '' ?>><?= $editing ? 'Save changes' : 'Submit listing' ?></button>
    </form>
</section>
<?php render_footer(); ?>
