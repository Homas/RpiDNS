<?php
#(c) Vadim Pavlov 2020-2026
#rpidns - blocked page
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
	<title>RpiDNS - Request Blocked</title>
	<style>
		html, body { height: 100%; margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
		body { background-color: #F5FAFB; display: flex; flex-direction: column; }
		.menu-bkgr { background-color: #343038; padding: 15px 20px; }
		.menu-bkgr h1 { color: white; margin: 0; font-size: 1.5rem; }
		.container { flex: 1; display: flex; align-items: center; justify-content: center; }
		.content { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 500px; text-align: center; }
		.content h2 { color: salmon; margin-top: 0; }
		.content p { color: #666; margin: 10px 0; }
		.copyright { text-align: right; height: 20px; padding: 0 10px; }
		.copyright p { margin: 0; font-size: 0.8rem; color: #666; }
	</style>
</head>
<body>
	<div class="menu-bkgr"><h1>RpiDNS</h1></div>
	<div class="container">
		<div class="content">
			<h2>🛡️ Request Blocked</h2>
			<p>This request has been blocked by the DNS Firewall.</p>
			<p><small>Protected by RpiDNS</small></p>
		</div>
	</div>
	<div class="copyright"><p>Copyright © 2020-2026 Vadim Pavlov</p></div>
</body>
</html>
