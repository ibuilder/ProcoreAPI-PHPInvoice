<?php
// Ensure CSRF token is available
global $csrf_token;
?>
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h2 class="card-title text-center">Login</h2>
                <form method="post" action="" class="mt-4">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">
                    <div class="form-group">
                        <label for="client_id">Client ID</label>
                        <input type="text" name="client_id" id="client_id" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="client_secret">Client Secret</label>
                        <input type="password" name="client_secret" id="client_secret" class="form-control" required>
                    </div>
                    <button type="submit" name="action" value="login" class="btn btn-primary btn-block">Login</button>
                </form>
            </div>
        </div>
    </div>
</div>