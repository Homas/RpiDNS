<?php
#(c) Vadim Pavlov 2020-2026
#rpidns - Vite + Vue 2 build
	require_once "/opt/rpidns/www/rpidns_vars.php";
	require_once "/opt/rpidns/www/rpisettings.php";
	$AddressType = $assets_by == "mac" ? "MAC" : "IP";

	// Read Vite manifest to get hashed asset filenames
	$manifestPath = __DIR__ . '/dist/.vite/manifest.json';
	$manifest = [];
	if (file_exists($manifestPath)) {
		$manifest = json_decode(file_get_contents($manifestPath), true);
	}

	// Get main entry point assets from manifest
	$mainJs = '';
	$mainCss = '';
	$vendorBootstrapCss = '';
	
	if (isset($manifest['index.html'])) {
		$mainJs = '/rpi_admin/dist/' . $manifest['index.html']['file'];
		if (isset($manifest['index.html']['css'][0])) {
			$mainCss = '/rpi_admin/dist/' . $manifest['index.html']['css'][0];
		}
	}
	
	// Find vendor-bootstrap CSS dynamically by searching manifest keys
	foreach ($manifest as $key => $value) {
		if (strpos($key, 'vendor-bootstrap') !== false && strpos($key, '.css') !== false) {
			$vendorBootstrapCss = '/rpi_admin/dist/' . $value['file'];
			break;
		}
	}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
	<title>RpiDNS</title>

	<!-- Vite built CSS assets -->
	<?php if ($vendorBootstrapCss): ?>
	<link rel="stylesheet" href="<?= $vendorBootstrapCss ?>">
	<?php endif; ?>
	<?php if ($mainCss): ?>
	<link rel="stylesheet" href="<?= $mainCss ?>">
	<?php endif; ?>
</head>
<body>
	<div id="app" class="h-100"></div>

	<!-- Inject PHP configuration variables for Vue app -->
	<script>
		window.RPIDNS_CONFIG = {
			rpiver: "<?= htmlspecialchars($rpiver, ENT_QUOTES, 'UTF-8') ?>",
			assets_by: "<?= htmlspecialchars($assets_by, ENT_QUOTES, 'UTF-8') ?>",
			addressType: "<?= htmlspecialchars($AddressType, ENT_QUOTES, 'UTF-8') ?>"
		};
	</script>

	<!-- Vite built JavaScript entry point -->
	<?php if ($mainJs): ?>
	<script type="module" src="<?= $mainJs ?>"></script>
	<?php else: ?>
	<!-- Fallback: manifest not found, try default paths -->
	<script type="module" src="/rpi_admin/dist/assets/main.js"></script>
	<?php endif; ?>

	<div class="copyright"><p>Copyright © 2020-2026 Vadim Pavlov</p></div>
</body>
</html>
