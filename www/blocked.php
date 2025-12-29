<?php
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Blocked</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f5f5f5; }
        .container { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 500px; margin: 0 auto; }
        h1 { color: #d32f2f; }
        p { color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🛡️ Request Blocked</h1>
        <p>This request has been blocked by the DNS Firewall.</p>
        <p><small>Protected by RpiDNS</small></p>
    </div>
</body>
</html>