<section class="card h-100 w-100">
    <div class="card-header"><h3 class="h5 mb-0">Submit a ground report</h3></div>
    <div class="card-body">
        <p class="text-muted">Describe an observed ground or slope condition. Reports are reviewed by an administrator.</p>
        <form action="<?= e(app_url('resident/submit_report.php')) ?>" method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <div class="mb-3">
                <label class="form-label" for="report-location-name">Selected map location</label>
                <input id="report-location-name" class="form-control" value="Select a marker on the map" readonly>
                <input type="hidden" id="report-location" name="location_id" value="">
            </div>
            <div class="form-floating mb-3">
                <input type="text" class="form-control" id="house-landmark" name="house_landmark"
                       maxlength="255" placeholder="House number, landmark, or street">
                <label for="house-landmark">House number / landmark / street (optional)</label>
            </div>
            <div class="form-floating mb-3">
                <textarea class="form-control" id="report-message" name="message" maxlength="2000"
                          placeholder="Observation details" style="height:120px" required></textarea>
                <label for="report-message">Observation details</label>
            </div>
            <button class="btn btn-warning" id="report-submit" type="submit" disabled>Submit report</button>
        </form>
    </div>
</section>
