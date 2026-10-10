<?php /** Divisions page: list + inline create/edit form (behaviour in /assets/js/geo.js). */ ?>
<div class="space-y-6" id="geo-page" data-geo-page="divisions">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-slate-900">Divisions</h1>
            <p class="text-sm text-slate-500">Top-level geographical divisions used by every cascade dropdown.</p>
        </div>
        <button type="button" class="btn btn-primary" id="geo-new">Add New Division</button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <section class="card lg:col-span-2">
            <div class="card-header">
                <span>List of All Division's With Details Info (<span id="geo-count">0</span>)</span>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:10px">#</th>
                            <th>Division Name</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="geo-rows">
                        <tr><td colspan="3" class="text-center text-slate-400">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card">
            <div class="card-header" id="geo-form-title">Add Division</div>
            <div class="card-body">
                <form id="geo-form" novalidate>
                    <div class="mb-4">
                        <label class="label" for="geo-name">Name</label>
                        <input type="text" id="geo-name" name="name" data-field="name" placeholder="Division name" autocomplete="off">
                        <p class="field-error hidden" data-error-for="name"></p>
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
