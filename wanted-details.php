<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
$id = (int)($_GET['id'] ?? 0);
if (isset($_GET['contact'])) { require_app_rate_limit('contact_wanted_' . $id, 30, 60); save_contact_click('wanted', $id, $_GET['contact']); exit('ok'); }
if (is_post()) { verify_csrf(); require_app_rate_limit('report_wanted_' . $id, 5, 15 * 60); report_target('wanted', $id, trim($_POST['reason'] ?? 'Concern'), trim($_POST['details'] ?? '')); flash('success', 'Report submitted.'); redirect('wanted-details.php?id=' . $id); }
$stmt = db()->prepare('SELECT * FROM wanted_posts WHERE id = ?');
$stmt->execute([$id]);
$post = $stmt->fetch();
if (!$post || ($post['status'] !== 'active' && !owns_listing('wanted_posts', $id) && !is_admin())) { http_response_code(404); die('Post not found.'); }
increment_view('wanted_posts', $id);
render_header($post['title'], $post['description']);
?>
<section class="details-layout single">
    <div class="details-card">
        <span class="badge teal">Wanted</span>
        <h1><?= e($post['title']) ?></h1>
        <p class="lead"><?= nl2br(e($post['description'])) ?></p>
        <h2>Desired vehicle specs</h2>
        <div class="spec-grid">
            <?php foreach (['Preferred make'=>'preferred_make','Preferred model'=>'preferred_model','Min year'=>'min_year','Max year'=>'max_year','Max mileage'=>'max_mileage','Min budget'=>'min_budget','Max budget'=>'max_budget','Body type'=>'preferred_body_type','Transmission'=>'preferred_transmission','Fuel type'=>'preferred_fuel_type','Clean title required'=>'must_have_clean_title','Location'=>'location','Distance willing to travel (miles)'=>'travel_distance'] as $label=>$key): ?>
            <div><span><?= e($label) ?></span><strong><?= $key === 'must_have_clean_title' ? (!empty($post[$key]) ? 'Yes' : 'No') : e((string)($post[$key] ?? 'Any')) ?></strong></div>
            <?php endforeach; ?>
        </div>
    </div>
    <aside class="contact-panel">
        <h2>Contact Buyer Directly</h2>
        <p><strong><?= e($post['buyer_name']) ?></strong></p>
        <a class="button full-width" data-track-contact="call" href="tel:<?= e(clean_phone_href($post['buyer_phone'])) ?>">Call Buyer</a>
        <a class="button secondary full-width" data-track-contact="text" href="sms:<?= e(clean_phone_href($post['buyer_phone'])) ?>">Text Buyer</a>
        <?php if ($post['buyer_email']): ?><a class="button ghost full-width" data-track-contact="email" href="mailto:<?= e($post['buyer_email']) ?>">Email Buyer</a><?php endif; ?>
        <button class="button ghost full-width" data-copy-link>Copy link</button>
        <button class="button ghost full-width" data-share>Share</button>
        <form method="post" class="report-form"><?= csrf_field() ?><input name="reason" placeholder="Report reason"><textarea name="details" placeholder="Details"></textarea><button class="danger" type="submit">Report post</button></form>
    </aside>
</section>
<?php render_footer(); ?>
