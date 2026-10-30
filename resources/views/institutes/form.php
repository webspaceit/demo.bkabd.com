<?php
$isEdit = ($mode ?? 'create') === 'edit';
?>
<div class="space-y-4" id="institute-form-page" data-mode="<?= e($mode ?? 'create') ?>" data-id="<?= (int) ($instituteId ?? 0) ?>">
    <div class="card">
        <div class="card-header"><?= $isEdit ? 'Edit Institute' : 'Institute Registration Form' ?></div>
        <div class="card-body">
            <form id="institute-form" enctype="multipart/form-data" novalidate>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div class="fl"><select class="input" id="f-center_id" name="center_id" data-field="center_id" required><option value="">Select Center</option></select><label for="f-center_id">Select Center <span class="text-red-600">*</span></label><p class="field-error hidden" data-error-for="center_id"></p></div>
                    <div class="fl"><input class="input" id="f-name" name="name" data-field="name" placeholder="Institute Name" required><label for="f-name">Name of Institute <span class="text-red-600">*</span></label><p class="field-error hidden" data-error-for="name"></p></div>
                    <div class="fl"><input class="input" id="f-address" name="address" data-field="address" placeholder="Address" required><label for="f-address">Institute Address <span class="text-red-600">*</span></label><p class="field-error hidden" data-error-for="address"></p></div>
                    <div class="fl"><input class="input" id="f-director_name" name="director_name" data-field="director_name" placeholder="Representative Name" required><label for="f-director_name">Representative Name <span class="text-red-600">*</span></label><p class="field-error hidden" data-error-for="director_name"></p></div>
                    <div class="fl"><input class="input" id="f-phone" name="phone" placeholder="Mobile Number" required><label for="f-phone">Authority Mobile Number <span class="text-red-600">*</span></label><p class="field-error hidden" data-error-for="phone"></p></div>
                    <div class="fl"><input class="input" type="email" id="f-email" name="email" placeholder="Email Address" required><label for="f-email">Email Address <span class="text-red-600">*</span></label><p class="field-error hidden" data-error-for="email"></p></div>
                    <div class="fl"><select class="input" id="f-division_id" name="division_id" required><option value="">Select Division</option></select><label for="f-division_id">Division <span class="text-red-600">*</span></label><p class="field-error hidden" data-error-for="division_id"></p></div>
                    <div class="fl"><select class="input" id="f-district_id" name="district_id" required><option value="">Select District</option></select><label for="f-district_id">District <span class="text-red-600">*</span></label><p class="field-error hidden" data-error-for="district_id"></p></div>
                    <div class="fl"><select class="input" id="f-upazila_id" name="upazila_id" required><option value="">Select Upazila</option></select><label for="f-upazila_id">Upazila <span class="text-red-600">*</span></label><p class="field-error hidden" data-error-for="upazila_id"></p></div>
                    <div class="fl"><input class="input" id="f-zip" name="zip" placeholder="Postal Code" required><label for="f-zip">Postal Code <span class="text-red-600">*</span></label><p class="field-error hidden" data-error-for="zip"></p></div>
                    <div class="fl"><input class="input" type="text" id="f-established_date" name="established_date" placeholder="YYYY-MM-DD" required><label for="f-established_date">Established Date <span class="text-red-600">*</span></label><p class="field-error hidden" data-error-for="established_date"></p></div>
                    <div class="fl"><input class="input" id="f-website" name="website" placeholder="Website"><label for="f-website">Website (If any)</label><p class="field-error hidden" data-error-for="website"></p></div>
                    <div class="fl"><input class="input" type="number" min="0" id="f-total_students" name="total_students" required><label for="f-total_students">Total Students <span class="text-red-600">*</span></label><p class="field-error hidden" data-error-for="total_students"></p></div>
                    <div class="fl"><input class="input" type="number" min="0" id="f-total_teachers" name="total_teachers" required><label for="f-total_teachers">Total Teachers <span class="text-red-600">*</span></label><p class="field-error hidden" data-error-for="total_teachers"></p></div>
                    <div class="fl"><select class="input" id="f-membership_type" name="membership_type" required><option value="">Select Membership Type</option><option value="1">Temporary (1500 BDT)</option><option value="2">Life Time (3000 BDT)</option></select><label for="f-membership_type">Membership Type <span class="text-red-600">*</span></label><p class="field-error hidden" data-error-for="membership_type"></p></div>
                    <div class="fl"><select class="input" id="f-member_type" name="member_type" required><option value="">Select Member Type</option><option value="1">New Member</option><option value="2">Old Member</option></select><label for="f-member_type">Member Type <span class="text-red-600">*</span></label><p class="field-error hidden" data-error-for="member_type"></p></div>
                    <div class="fl hidden" id="cert-wrap"><input class="input" id="f-certificate_no" name="certificate_no" placeholder="Certificate No"><label for="f-certificate_no">Membership Certificate No <span class="text-red-600">*</span></label><p class="field-error hidden" data-error-for="certificate_no"></p></div>
                    <div class="fl"><select class="input" id="f-type" name="type" required><option value="">Select Education Type</option><option value="1">Bangla Medium</option><option value="2">English Version</option><option value="3">Arabic Version</option></select><label for="f-type">Education Type <span class="text-red-600">*</span></label><p class="field-error hidden" data-error-for="type"></p></div>
                    <div class="fl"><select class="input" id="f-recognized" name="recognized" required><option value="">Select Govt. Recognized Status</option><option value="1">Yes</option><option value="2">No</option></select><label for="f-recognized">Is it Govt. Recognized Approved? <span class="text-red-600">*</span></label><p class="field-error hidden" data-error-for="recognized"></p></div>
                    <div class="fl"><select class="input" id="f-institution_run" name="institution_run" required><option value="">Select Institution Run</option><option value="1">Personal</option><option value="2">Committee</option><option value="3">Partnership</option></select><label for="f-institution_run">How does the Institution Run? <span class="text-red-600">*</span></label><p class="field-error hidden" data-error-for="institution_run"></p></div>
                    <div class="fl"><input class="input" id="f-monthly_income" name="monthly_income" placeholder="Monthly Income"><label for="f-monthly_income">Monthly Income</label><p class="field-error hidden" data-error-for="monthly_income"></p></div>
                    <div class="fl"><input class="input" id="f-monthly_expense" name="monthly_expense" placeholder="Monthly Expense"><label for="f-monthly_expense">Monthly Expense</label><p class="field-error hidden" data-error-for="monthly_expense"></p></div>
                    <div class="fl"><input class="input" type="file" id="f-logo" name="logo" accept="image/jpeg,image/png,image/gif" <?= $isEdit ? '' : 'required' ?>><label for="f-logo">Institute Logo <?= $isEdit ? '(replace if needed)' : '<span class="text-red-600">*</span>' ?></label><img id="logo-preview" class="mt-2 hidden h-20 w-20 rounded object-cover" alt="Institute logo"><p class="field-error hidden" data-error-for="logo"></p></div>
                    <div class="fl"><input class="input" type="file" id="f-principal_photo" name="principal_photo" accept="image/jpeg,image/png,image/gif"><label for="f-principal_photo">Principal Photo</label><img id="principal-preview" class="mt-2 hidden h-20 w-20 rounded object-cover" alt="Principal photo"><p class="field-error hidden" data-error-for="principal_photo"></p></div>
                    <div class="fl"><input class="input" id="f-note" name="note" placeholder="Note"><label for="f-note">Note (If Any)</label><p class="field-error hidden" data-error-for="note"></p></div>
                    <?php if ($isEdit): ?><div class="fl"><select class="input" id="f-active" name="active"><option value="1">Active</option><option value="0">Pending</option></select><label for="f-active">Status</label></div><?php endif; ?>
                </div>
                <label class="mt-4 flex items-start gap-2 text-sm text-slate-600"><input type="checkbox" id="f-declaration" class="mt-1"><span>My Declaration: I hereby declare that if this/my educational institution is recognized as a member according to the laws of the association, I'll abide by all these rules and regulations and take part in all activities of the association.</span></label>
                <div class="mt-5 flex flex-wrap gap-2"><button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save Changes' : 'Submit' ?></button><a href="/institutes" class="btn btn-light">Back</a></div>
            </form>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', async function () {
    const root=document.getElementById('institute-form-page'); if(!root)return;
    const isEdit=root.dataset.mode==='edit', id=root.dataset.id, form=document.getElementById('institute-form');
    const center=document.getElementById('f-center_id'), division=document.getElementById('f-division_id'), district=document.getElementById('f-district_id'), upazila=document.getElementById('f-upazila_id');
    const allDistricts=<?= json_encode($districtOptions ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    const allUpazilas=<?= json_encode($upazilaOptions ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    function options(items,selected,label){let html='<option value="">'+BK.esc(label)+'</option>';(items||[]).forEach(x=>{html+='<option value="'+BK.esc(String(x.id))+'"'+(String(x.id)===String(selected??'')?' selected':'')+'>'+BK.esc(String(x.name||'').toUpperCase())+'</option>';});return html;}
    function districts(selected){const rows=allDistricts.filter(x=>String(x.division_id)===String(division.value));district.innerHTML=options(rows,selected,'Select District');}
    function upazilas(selected){const rows=allUpazilas.filter(x=>String(x.district_id)===String(district.value));upazila.innerHTML=options(rows,selected,'Select Upazila');}
    division.addEventListener('change',()=>{upazila.innerHTML=options([],'','Select Upazila');districts('');});
    district.addEventListener('change',()=>upazilas(''));
    document.getElementById('f-member_type').addEventListener('change',function(){const show=this.value==='2';document.getElementById('cert-wrap').classList.toggle('hidden',!show);document.getElementById('f-certificate_no').required=show;});
    function showPreview(idValue,path,file){const img=document.getElementById(idValue);if(file){img.src='/'+path+'/'+encodeURIComponent(file);img.classList.remove('hidden');}}
    document.getElementById('f-logo').addEventListener('change',function(){const f=this.files&&this.files[0];if(f){const img=document.getElementById('logo-preview');img.src=URL.createObjectURL(f);img.classList.remove('hidden');}});
    document.getElementById('f-principal_photo').addEventListener('change',function(){const f=this.files&&this.files[0];if(f){const img=document.getElementById('principal-preview');img.src=URL.createObjectURL(f);img.classList.remove('hidden');}});
    try {
        const [centers,divisions]=await Promise.all([BK.get('/api/center'),BK.get('/api/divisionList')]);
        center.innerHTML=options(centers.centerList||[],'','Select Center');
        division.innerHTML=options(divisions.divisionList||[],'','Select Division');
        if(isEdit){
            const row=(await BK.get('/api/institutes/'+id)).instituteById;
            ['center_id','name','address','director_name','phone','email','zip','established_date','website','total_students','total_teachers','membership_type','member_type','certificate_no','type','recognized','institution_run','monthly_income','monthly_expense','note','active'].forEach(k=>{const el=form.elements[k];if(el&&row[k]!=null)el.value=row[k];});
            division.value=row.division_id||'';districts(row.district_id);upazilas(row.upazila_id);
            const memberType=document.getElementById('f-member_type');memberType.dispatchEvent(new Event('change'));
            showPreview('logo-preview','images/logos',row.logo);showPreview('principal-preview','images/principals',row.principal_photo);
        }
    } catch(e){BK.toast(e.message||'Could not load the institute form','error');}
    form.addEventListener('submit',function(event){
        event.preventDefault();
        if(!form.reportValidity())return;
        if(!document.getElementById('f-declaration').checked){BK.toast('Please check the declaration before submitting.','error');return;}
        BK.submit(form,async function(){
            await BK.request(isEdit?'/api/institutes/'+id:'/api/institutes',{method:'POST',body:new FormData(form)});
            BK.toast(isEdit?'Institute updated and sent back for approval.':'Institute registered successfully. Please wait for authority approval.','success');
            window.location.href=isEdit?'/pending-institutes':'/institutes';
        });
    });
});
</script>
