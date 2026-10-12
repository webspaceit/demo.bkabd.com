<?php /** Unions page: paginated list with search + inline create/edit form (behaviour in /assets/js/geo.js). */ ?>
<div class="space-y-6" id="geo-page" data-geo-page="unions">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-slate-900">Unions</h1>
            <p class="text-sm text-slate-500">Unions grouped under their upazila.</p>
        </div>
        <button type="button" class="btn btn-primary" id="geo-new">Add New Union</button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <section class="card lg:col-span-2">
            <div class="card-header">
                <span>List of All Union's With Details Info (<span id="geo-count">0</span>)</span>
                <input type="search" id="geo-search" class="w-full max-w-xs" placeholder="Search Here..." autocomplete="off" aria-label="Search unions">
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:10px">#</th>
                            <th>Union Name</th>
                            <th>Upazila Name</th>
                            <th>District Name</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="geo-rows">
                        <tr><td colspan="5" class="text-center text-slate-400">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-3 border-t border-slate-100" id="geo-pagination"></div>
        </section>

        <section class="card">
            <div class="card-header" id="geo-form-title">Add Union</div>
            <div class="card-body">
                <form id="geo-form" novalidate>
                    <div class="mb-4 fl">
                        <input class="input" type="text" id="geo-name" name="name" data-field="name" placeholder="Union name" autocomplete="off">
                        <label for="geo-name">Union Name</label>
                        <p class="field-error hidden" data-error-for="name"></p>
                    </div>
                    <div class="mb-4 fl">
                        <select class="input" id="geo-parent" name="division_id">
                            <option value="">Select Division</option>
                        </select>
                        <label for="geo-parent">Division</label>
                    </div>
                    <div class="mb-4 fl">
                        <select class="input" id="geo-child" name="district_id">
                            <option value="">Select District</option>
                        </select>
                        <label for="geo-child">District</label>
                    </div>
                    <div class="mb-4 fl">
                        <select class="input" id="geo-grandchild" name="upozila_id" data-field="upozila_id">
                            <option value="">Select Upazila</option>
                        </select>
                        <label for="geo-grandchild">Upazila</label>
                        <p class="field-error hidden" data-error-for="upozila_id"></p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary">Submit</button>
                        <button type="button" class="btn btn-light hidden" id="geo-cancel">Cancel</button>
                    </div>
                </form>
            </div>
        </section>
    </div>
</div>
