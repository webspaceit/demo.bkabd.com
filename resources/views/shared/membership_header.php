<style>
    .membership-page { box-sizing: border-box; width: 100%; max-width: 72rem; margin-inline: auto; padding: 2rem 1rem 3rem; }
    .membership-hero { position: relative; overflow: hidden; margin-bottom: .85rem; padding: 1% 1%; border-radius: 1.1rem; color: #fff; background: radial-gradient(circle at 90% 10%, rgba(110,231,183,.35), transparent 28%), linear-gradient(120deg, #064e3b, #047857 58%, #059669); box-shadow: 0 18px 40px rgba(6,78,59,.18); }
    .membership-hero:after { content: ""; position: absolute; z-index: 0; width: 16rem; height: 16rem; right: -5rem; bottom: -10rem; border: 1px solid rgba(255,255,255,.2); border-radius: 50%; box-shadow: 0 0 0 1.5rem rgba(255,255,255,.05), 0 0 0 3rem rgba(255,255,255,.04); pointer-events: none; }
    .membership-hero > * { position: relative; z-index: 1; }
    .membership-header { position: relative; z-index: 1; display: grid; grid-template-columns: 5.8rem minmax(0,1fr); align-items: center; gap: .25rem; color: #fff; }
    .membership-header-logo { display: block; object-fit: contain; }
    .membership-header-copy { min-width: 0; text-align: center; }
    .membership-header-name { margin: 0; color: #fff; font-size: clamp(1.25rem, 3vw, 2.4rem); line-height: 1.1; font-weight: 800; letter-spacing: .015em; text-transform: uppercase; }
    .membership-header-reg { margin: .35rem 0 0; color: #fff; font-size: clamp(.95rem, 1.8vw, 1.55rem); line-height: 1.15; font-weight: 700; }
    .membership-header-contact { margin: .55rem 0 0; color: #d1fae5; font-size: clamp(.68rem, 1vw, .95rem); line-height: 1.5; font-weight: 600; }
    @media (max-width: 639px) { .membership-page { padding-top: .75rem; } .membership-header { grid-template-columns: 3.75rem minmax(0,1fr); gap: .7rem; } .membership-header-logo { width: 3.5rem; height: 3.5rem; } .membership-header-copy { text-align: left; } }
</style>
<section class="membership-hero">
    <header class="membership-header" aria-label="Association information">
        <img class="membership-header-logo" src="<?= e(brand_asset('membership_logo_path', '/assets/img/result/logo.png')) ?>" alt="<?= e(brand('authority_name')) ?> logo">
        <div class="membership-header-copy">
            <h1 class="membership-header-name"><?= e(brand('authority_name')) ?></h1>
            <?php if (brand('registration_no') !== ''): ?>
                <p class="membership-header-reg"><?= e(brand('registration_label')) ?>: <?= e(brand('registration_no')) ?></p>
            <?php endif; ?>
            <p class="membership-header-contact">
                <?= e(brand('address')) ?><?= brand('phone') !== '' ? ' · Mobile: ' . e(brand('phone')) : '' ?><br>
                <?= brand('email') !== '' ? 'Email: ' . e(brand('email')) : '' ?><?= brand('email') !== '' && brand('website') !== '' ? ' · ' : '' ?><?= brand('website') !== '' ? 'Web: ' . e(brand('website')) : '' ?>
            </p>
        </div>
    </header>
</section>
