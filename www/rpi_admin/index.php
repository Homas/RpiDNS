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

	// Helper function to get asset path from manifest
	function getAssetPath($manifest, $key) {
		if (isset($manifest[$key]) && isset($manifest[$key]['file'])) {
			return '/rpi_admin/dist/' . $manifest[$key]['file'];
		}
		return '';
	}

	// Get main entry point assets
	$mainJs = getAssetPath($manifest, 'index.html');
	$mainCss = '';
	if (isset($manifest['index.html']['css'][0])) {
		$mainCss = '/rpi_admin/dist/' . $manifest['index.html']['css'][0];
	}

	// Get vendor assets for preloading
	$vendorVueJs = getAssetPath($manifest, '_vendor-vue-BkyXSOm9.js');
	$vendorBootstrapJs = getAssetPath($manifest, '_vendor-bootstrap-Dm1xJeC8.js');
	$vendorBootstrapCss = '';
	if (isset($manifest['_vendor-bootstrap-Bl5lLFHa.css']['file'])) {
		$vendorBootstrapCss = '/rpi_admin/dist/' . $manifest['_vendor-bootstrap-Bl5lLFHa.css']['file'];
	}
	$vendorChartsJs = getAssetPath($manifest, '_vendor-charts-C2ogHOKZ.js');
	$vendorUtilsJs = getAssetPath($manifest, '_vendor-utils-B9ygI19o.js');
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

	<!-- Preload vendor chunks for better performance -->
	<?php if ($vendorVueJs): ?>
	<link rel="modulepreload" href="<?= $vendorVueJs ?>">
	<?php endif; ?>
	<?php if ($vendorBootstrapJs): ?>
	<link rel="modulepreload" href="<?= $vendorBootstrapJs ?>">
	<?php endif; ?>
	<?php if ($vendorChartsJs): ?>
	<link rel="modulepreload" href="<?= $vendorChartsJs ?>">
	<?php endif; ?>
	<?php if ($vendorUtilsJs): ?>
	<link rel="modulepreload" href="<?= $vendorUtilsJs ?>">
	<?php endif; ?>
</head>
<body>
	<div id="app"></div>

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

	<div class="copyright"><p>Copyright © 2020-2023 Vadim Pavlov</p></div>
</body>
</html>
