<?php
// Ensure variables are available
global $csrf_token, $projects, $project_id; // $project_id might be set from previous selection or POST
$selected_project_id = $_POST['project_id'] ?? $project_id ?? null; // Pre-select if available
?>
<div class="card">
    <div class="card-body">
        <h2 class="card-title text-center">Generate Invoice</h2>
        <form method="post" action="" class="mt-4">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">

            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="project_id">Select Project:</label>
                    <select name="project_id" id="project_id" class="form-control" required>
                        <option value="">-- Select Project --</option>
                        <?php if (!empty($projects)): ?>
                            <?php foreach ($projects as $proj): ?>
                                <option value="<?php echo htmlspecialchars($proj['id']); ?>" <?php echo ($selected_project_id == $proj['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($proj['name']); ?> (ID: <?php echo htmlspecialchars($proj['id']); ?>)
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="form-group col-md-6">
                    <label for="application_number">Application Number:</label>
                    <input type="text" name="application_number" id="application_number" class="form-control" value="<?php echo htmlspecialchars($_POST['application_number'] ?? ''); ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="owner_name">Owner Name:</label>
                    <input type="text" name="owner_name" id="owner_name" class="form-control" value="<?php echo htmlspecialchars($_POST['owner_name'] ?? ''); ?>" required>
                </div>
                <div class="form-group col-md-6">
                    <label for="contractor_name">Contractor Name:</label>
                    <input type="text" name="contractor_name" id="contractor_name" class="form-control" value="<?php echo htmlspecialchars($_POST['contractor_name'] ?? ''); ?>" required>
                </div>
            </div>

             <div class="form-group">
                <label for="project_name">Project Name (for AIA form):</label>
                <input type="text" name="project_name" id="project_name" class="form-control" value="<?php echo htmlspecialchars($_POST['project_name'] ?? ''); ?>" required>
             </div>

            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="contract_date">Contract Date:</label>
                    <input type="date" name="contract_date" id="contract_date" class="form-control" value="<?php echo htmlspecialchars($_POST['contract_date'] ?? ''); ?>" required>
                </div>
                <div class="form-group col-md-6">
                    <label for="period_to">Period To Date:</label>
                    <input type="date" name="period_to" id="period_to" class="form-control" value="<?php echo htmlspecialchars($_POST['period_to'] ?? ''); ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="original_contract_sum">Original Contract Sum ($):</label>
                    <input type="number" step="0.01" name="original_contract_sum" id="original_contract_sum" class="form-control" value="<?php echo htmlspecialchars($_POST['original_contract_sum'] ?? ''); ?>" required>
                </div>
                <div class="form-group col-md-6">
                    <label for="change_orders_sum">Net Change by Change Orders ($):</label>
                    <input type="number" step="0.01" name="change_orders_sum" id="change_orders_sum" class="form-control" value="<?php echo htmlspecialchars($_POST['change_orders_sum'] ?? '0.00'); ?>" required>
                </div>
            </div>

            <hr>
            <h5 class="text-center">Retainage Settings</h5>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="retainage_completed_percent">Retainage on Completed Work (%):</label>
                    <input type="number" step="0.01" name="retainage_completed_percent" id="retainage_completed_percent" class="form-control" value="<?php echo htmlspecialchars($_POST['retainage_completed_percent'] ?? '10.00'); ?>" required>
                </div>
                 <div class="form-group col-md-6">
                    <label for="retainage_stored_percent">Retainage on Stored Materials (%):</label>
                    <input type="number" step="0.01" name="retainage_stored_percent" id="retainage_stored_percent" class="form-control" value="<?php echo htmlspecialchars($_POST['retainage_stored_percent'] ?? '10.00'); ?>" required>
                </div>
            </div>
             <!-- Optional: Add fields for retainage reduction threshold if needed -->
             <!--
             <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="retainage_reduction_threshold">Reduce Retainage After Completion (%):</label>
                    <input type="number" step="0.01" name="retainage_reduction_threshold" id="retainage_reduction_threshold" class="form-control" placeholder="e.g., 50 (optional)">
                </div>
                 <div class="form-group col-md-6">
                    <label for="reduced_retainage_percent">Reduced Retainage Rate (%):</label>
                    <input type="number" step="0.01" name="reduced_retainage_percent" id="reduced_retainage_percent" class="form-control" placeholder="e.g., 5 (optional)">
                </div>
            </div>
             -->
            <hr>

            <div class="form-group">
                <label for="previous_payments">Less Previous Certificates for Payment ($):</label>
                <input type="number" step="0.01" name="previous_payments" id="previous_payments" class="form-control" value="<?php echo htmlspecialchars($_POST['previous_payments'] ?? '0.00'); ?>" required>
            </div>


            <button type="submit" name="action" value="generate_invoice" class="btn btn-success btn-block">Generate AIA G702/G703</button>
        </form>
    </div>
</div>