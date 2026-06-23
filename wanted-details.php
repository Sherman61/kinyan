<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
$id = (int)($_GET['id'] ?? 0);
if (is_post()) { verify_csrf(); require_app_rate_limit('report_wanted_' . $id, 5, 15 * 60); $reported = report_target('wanted', $id, $_POST['reason'] ?? '', $_POST['details'] ?? ''); flash($reported ? 'success' : 'error', $reported ? 'Report submitted.' : 'Enter a report reason up to 120 characters and details up to 4,000 characters.'); redirect('wanted-details.php?id=' . $id); }
$stmt = db()->prepare('SELECT w.*, u.created_at buyer_member_since, u.trust_level buyer_trust_level FROM wanted_posts w JOIN users u ON u.id = w.user_id WHERE w.id = ?');
$stmt->execute([$id]);
$post = $stmt->fetch();
if (!$post || ($post['status'] !== 'active' && !owns_listing('wanted_posts', $id) && !is_admin())) render_status_page(404, 'Wanted post not found', 'This wanted post is unavailable, pending approval, or no longer active.', ['Browse wanted posts' => 'wanted.php', 'Go home' => 'index.php']);
increment_view('wanted_posts', $id);
render_header($post['title'], $post['description'], ['canonical'=>'wanted-details.php?id=' . $id . '&slug=' . slugify($post['title'])]);
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
    <aside class="contact-panel" data-contact-target-type="wanted" data-contact-target-id="<?= (int)$id ?>">
        <h2>Contact Buyer Directly</h2>
        <p><strong><?= e($post['buyer_name']) ?></strong></p>
        <p class="muted">Member since <?= e(date('M Y', strtotime($post['buyer_member_since']))) ?> · Trust level <?= (int)$post['buyer_trust_level'] ?></p>
        <a class="button full-width" data-track-contact="call" href="tel:<?= e(clean_phone_href($post['buyer_phone'])) ?>">Call Buyer</a>
        <a class="button secondary full-width" data-track-contact="text" href="sms:<?= e(clean_phone_href($post['buyer_phone'])) ?>">Text Buyer</a>
        <?php if ($post['buyer_email']): ?><a class="button ghost full-width" data-track-contact="email" href="mailto:<?= e($post['buyer_email']) ?>">Email Buyer</a><?php endif; ?>
        <button class="button ghost full-width" data-copy-link data-track-contact="copy_link">Copy link</button>
        <button class="button ghost full-width" data-share data-track-contact="share" data-share-text="<?= e($post['title'] . ' on Kinyan') ?>">Share</button>
        <form method="post" class="report-form"><?= csrf_field() ?><label>Reason<input required maxlength="120" name="reason" placeholder="Example: Misleading request"></label><label>Details<textarea maxlength="4000" name="details" placeholder="Explain what should be reviewed"></textarea></label><button class="danger" type="submit">Report post</button></form>
    </aside>
</section>
<?php render_footer(); ?>
