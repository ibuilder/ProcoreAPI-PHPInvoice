<?php
// This file contains the HTML form for selecting a company from the list of companies retrieved from the Procore API.

if (!empty($companies)): ?>
    <form method="get" action="" class="text-center mt-4">
        <div class="form-group">
            <label for="company_id">Select Company:</label>
            <select name="company_id" id="company_id" class="form-control" required>
                <option value="">-- Select a Company --</option>
                <?php foreach ($companies as $company): ?>
                    <option value="<?php echo htmlspecialchars($company['id']); ?>">
                        <?php echo htmlspecialchars($company['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Select Company</button>
    </form>
<?php else: ?>
    <div class="alert alert-warning">No companies found. Please log in to retrieve company data.</div>
<?php endif; ?>