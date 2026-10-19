<?php /* Temporary entry page. Replaced by the real landing page in a later commit. */ ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="light dark">
  <title>Track-a-Demic</title>
  <style>
    body { font-family: system-ui, sans-serif; margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f2f6f5; color: #0f1f1d; }
    main { text-align: center; padding: 1.5rem; }
    a { display: inline-block; margin: .25rem; padding: .7rem 1.2rem; border-radius: 8px; background: #0b5d57; color: #fff; text-decoration: none; font-weight: 600; }
    a.alt { background: transparent; color: #0b5d57; border: 1px solid #0b5d57; }
    code { background: #e1e9e7; padding: .15rem .4rem; border-radius: 4px; }
    @media (prefers-color-scheme: dark) { body { background: #0b1514; color: #e7f1ef; } code { background: #25403c; } a { background: #34b3a6; color: #04211e; } a.alt { background: transparent; color: #34b3a6; border-color: #34b3a6; } }
  </style>
</head>
<body>
  <main>
    <h1>Track-a-Demic</h1>
    <p>PHP <code><?php echo htmlspecialchars(PHP_VERSION); ?></code></p>
    <p>
      <a href="pages/login.php">Sign in</a>
      <a class="alt" href="pages/register.php">Create account</a>
    </p>
  </main>
</body>
</html>
