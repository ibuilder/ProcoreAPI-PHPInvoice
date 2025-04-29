<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo defined('APP_NAME') ? htmlspecialchars(APP_NAME) : 'Procore Invoice Generator'; ?></title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body { padding-top: 20px; padding-bottom: 20px; }
        .container { max-width: 960px; }
        .form-group { margin-bottom: 1rem; }
        .alert { margin-top: 1rem; }
        .company-list a, .project-list a { display: block; margin-bottom: 0.5rem; }
    </style>
</head>
<body>
    <div class="container"> <!-- Added Bootstrap container -->
        <header class="text-center mb-4">
            <h1><?php echo defined('APP_NAME') ? htmlspecialchars(APP_NAME) : 'Procore Invoice Generator'; ?></h1>
            <?php if (defined('APP_VERSION')): ?>
                <p class="text-muted">Version <?php echo htmlspecialchars(APP_VERSION); ?></p>
            <?php endif; ?>
        </header>
        <main>