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
                            <form data-brand-upload data-asset="<?= e($asset) ?>" class="space-y-2">
                                <input type="file" name="image" accept="image/png,image/jpeg" required class="block w-full text-xs text-slate-600">
                                <button class="btn btn-light btn-sm" type="submit">Upload <?= e(strtolower($label)) ?></button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
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
        try {
            await jsonRequest('/api/branding', {method: 'PUT', headers: {...headers, 'Content-Type': 'application/json'}, body: JSON.stringify(data)});
            if (window.BK) BK.alert('Branding details saved.', 'Success', 'success'); else window.alert('Branding details saved.');
        } catch (error) { showError(error.message); }
    });
    root.querySelectorAll('[data-brand-upload]').forEach((uploadForm) => {
        uploadForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const data = new FormData(uploadForm);
            data.append('asset', uploadForm.dataset.asset);
            try {
                const result = await jsonRequest('/api/branding/assets', {method: 'POST', headers, body: data});
                const path = result.branding?.[uploadForm.dataset.asset + '_path'];
                if (path) {
                    const preview = root.querySelector('[data-brand-preview="' + uploadForm.dataset.asset + '"]');
                    preview.src = path + '?v=' + Date.now();
                }
                if (window.BK) BK.alert('Image uploaded.', 'Success', 'success'); else window.alert('Image uploaded.');
            } catch (error) { showError(error.message); }
        });
    });
});
</script>
