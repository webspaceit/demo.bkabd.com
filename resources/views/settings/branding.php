<?php
$canEdit = can('setting-edit');
$textFields = [
    'authority_name' => 'Authority name',
    'short_name' => 'Short name',
    'app_name' => 'System name',
    'exam_name' => 'Examination name',
    'admit_card_rules_title' => 'Admit card instructions heading',
    'admit_card_rules' => 'Admit card special instructions (one instruction per line)',
    'registration_label' => 'Registration label',
    'registration_no' => 'Registration number',
    'address' => 'Address',
    'phone' => 'Phone',
    'email' => 'Email',
    'website' => 'Website',
    'copyright_text' => 'Copyright text',
    'developer_credit' => 'Developer credit',
    'chairman_name' => 'Chairman name',
    'chairman_title' => 'Chairman title',
    'general_secretary_name' => 'General Secretary name',
    'general_secretary_title' => 'General Secretary title',
    'controller_name' => 'Exam Controller name',
    'controller_title' => 'Exam Controller title',
    'education_secretary_name' => 'Education Secretary name',
    'education_secretary_title' => 'Education Secretary title',
];
?>
<div class="space-y-5" id="branding-page">
    <section class="card">
        <div class="card-header">Authority identity</div>
        <div class="card-body">
            <p class="mb-5 text-sm text-slate-500">Update the organization and contact details used across the system, printable cards, reports, and public result pages.</p>
            <form id="branding-form" class="space-y-4">
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <?php foreach ($textFields as $field => $label): ?>
                        <div class="fl <?= in_array($field, ['address', 'website', 'admit_card_rules'], true) ? 'md:col-span-2' : '' ?>">
                            <?php if (in_array($field, ['address', 'website', 'admit_card_rules'], true)): ?>
                                <textarea class="input" id="brand-<?= e($field) ?>" name="<?= e($field) ?>" rows="<?= $field === 'admit_card_rules' ? '6' : '2' ?>" placeholder="<?= e($label) ?>" <?= $canEdit ? '' : 'disabled' ?>><?= e($branding[$field] ?? '') ?></textarea>
                            <?php else: ?>
                                <input class="input" id="brand-<?= e($field) ?>" name="<?= e($field) ?>" value="<?= e($branding[$field] ?? '') ?>" placeholder="<?= e($label) ?>" <?= $canEdit ? '' : 'disabled' ?>>
                            <?php endif; ?>
                            <label for="brand-<?= e($field) ?>"><?= e($label) ?></label>
                            <p class="field-error hidden" data-error-for="<?= e($field) ?>"></p>
                        </div>
                    <?php endforeach; ?>
                </div>
                <label class="flex items-center gap-2 text-sm font-medium text-slate-700" for="brand-show-admit-card-print-date">
                    <input type="checkbox" id="brand-show-admit-card-print-date" <?= ($branding['show_admit_card_print_date'] ?? '1') !== '0' ? 'checked' : '' ?> <?= $canEdit ? '' : 'disabled' ?>>
                    Show print date on admit cards
                </label>
                <?php if ($canEdit): ?>
                    <button type="submit" class="btn btn-primary">Save branding details</button>
                <?php endif; ?>
            </form>
        </div>
    </section>

    <section class="card">
        <div class="card-header">Brand images</div>
        <div class="card-body">
            <p class="mb-5 text-sm text-slate-500">Upload PNG or JPEG artwork up to 4 MB. These replace the authority logo, print-card header/footer, watermark, and background pattern.</p>
            <p class="mb-5 text-sm text-slate-500">Signature images are managed in <a class="text-emerald-700 underline" href="/settings">Settings → Result Sheet Signatures</a>.</p>
            <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
                <?php foreach ([
                    'logo' => ['Logo', 'logo_path', '/assets/img/result/logo.png'],
                    'header_image' => ['Header image', 'header_image_path', '/assets/img/result/header.png'],
                    'footer_image' => ['Footer image', 'footer_image_path', '/assets/img/result/header.png'],
                    'favicon' => ['Favicon', 'favicon_path', '/assets/img/result/logo.png'],
                    'watermark' => ['Card watermark', 'watermark_path', '/assets/img/result/for-registration.png'],
                    'pattern' => ['Card background pattern', 'pattern_path', '/assets/img/result/pattern.png'],
                ] as $asset => [$label, $field, $fallback]): ?>
                    <div class="rounded-xl border border-slate-200 p-4">
                        <h3 class="mb-3 text-sm font-semibold text-slate-800"><?= e($label) ?></h3>
                        <div class="mb-3 flex h-24 items-center justify-center overflow-hidden rounded-lg bg-slate-50 p-2">
                            <img data-brand-preview="<?= e($asset) ?>" src="<?= e(brand_asset($field, $fallback)) ?>" alt="<?= e($label) ?> preview" class="max-h-full max-w-full object-contain">
                        </div>
                        <?php if ($canEdit): ?>
                            <div data-brand-upload data-asset="<?= e($asset) ?>" class="space-y-2">
                                <div class="flex items-center gap-2">
                                    <label class="btn btn-light btn-sm cursor-pointer">
                                        Choose image
                                        <input type="file" name="image" accept="image/png,image/jpeg" class="sr-only" data-brand-file>
                                    </label>
                                    <span class="min-w-0 truncate text-xs text-slate-500" data-brand-filename>No image selected</span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if ($canEdit): ?>
                <div class="mt-5 flex items-center gap-3">
                    <button class="btn btn-primary" type="button" id="submit-brand-images" disabled>Submit brand images</button>
                    <span class="text-sm text-slate-500" id="brand-images-status" aria-live="polite"></span>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const root = document.getElementById('branding-page');
    if (!root) return;
    const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const headers = {'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest'};
    const showError = (message) => window.BK ? BK.alert(message, 'Could not complete action', 'error') : window.alert(message);
    const jsonRequest = async (url, options) => {
        const response = await fetch(url, options);
        const result = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(result.message || 'Could not save branding.');
        return result;
    };
    const form = document.getElementById('branding-form');
    form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const data = Object.fromEntries(new FormData(form).entries());
        data.show_admit_card_print_date = document.getElementById('brand-show-admit-card-print-date')?.checked ? '1' : '0';
        try {
            await jsonRequest('/api/branding', {method: 'PUT', headers: {...headers, 'Content-Type': 'application/json'}, body: JSON.stringify(data)});
            if (window.BK) BK.alert('Branding details saved.', 'Success', 'success'); else window.alert('Branding details saved.');
        } catch (error) { showError(error.message); }
    });
    const imageSubmit = document.getElementById('submit-brand-images');
    const imageStatus = document.getElementById('brand-images-status');
    const imageTiles = Array.from(root.querySelectorAll('[data-brand-upload]'));
    imageTiles.forEach((tile) => {
        const fileInput = tile.querySelector('[data-brand-file]');
        const filename = tile.querySelector('[data-brand-filename]');
        fileInput?.addEventListener('change', () => {
            const file = fileInput.files?.[0];
            if (filename) filename.textContent = file?.name || 'No image selected';
            if (imageSubmit) imageSubmit.disabled = !imageTiles.some((item) => item.querySelector('[data-brand-file]')?.files?.length);
        });
    });
    imageSubmit?.addEventListener('click', async () => {
        const selectedTiles = imageTiles.filter((tile) => tile.querySelector('[data-brand-file]')?.files?.length);
        if (selectedTiles.length === 0) return;
        imageSubmit.disabled = true;
        let uploaded = 0;
        try {
            for (const tile of selectedTiles) {
                const fileInput = tile.querySelector('[data-brand-file]');
                const filename = tile.querySelector('[data-brand-filename]');
                const data = new FormData();
                data.append('image', fileInput.files[0]);
                data.append('asset', tile.dataset.asset);
                const result = await jsonRequest('/api/branding/assets', {method: 'POST', headers, body: data});
                const path = result.branding?.[tile.dataset.asset + '_path'];
                if (path) {
                    const preview = root.querySelector('[data-brand-preview="' + tile.dataset.asset + '"]');
                    preview.src = path + '?v=' + Date.now();
                }
                filename.textContent = 'Uploaded: ' + fileInput.files[0].name;
                fileInput.value = '';
                uploaded++;
                if (imageStatus) imageStatus.textContent = 'Uploaded ' + uploaded + ' of ' + selectedTiles.length + ' image(s)…';
            }
            if (imageStatus) imageStatus.textContent = 'Uploaded ' + uploaded + ' brand image(s).';
            if (window.BK) BK.alert('Brand images uploaded.', 'Success', 'success'); else window.alert('Brand images uploaded.');
        } catch (error) {
            if (imageStatus) imageStatus.textContent = uploaded ? 'Uploaded ' + uploaded + ' image(s); remaining images were not uploaded.' : '';
            showError(error.message);
        } finally {
            imageSubmit.disabled = !imageTiles.some((item) => item.querySelector('[data-brand-file]')?.files?.length);
        }
    });
});
</script>
