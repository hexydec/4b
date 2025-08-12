<?php
declare(strict_types=1);
require __DIR__.'/src/4b.php';

$error = null;
$msg = null;
$map = 0;
if (!empty($_POST['input'])) {
	$map = \intval($_POST['map'] ?? 0);
	$time = \microtime(true);
	$obj = new \hexydec\fourb\fourb($map === 2 ? 5 : 4);
	if (($output = $obj->encode($map, $_POST['input'], $error)) === false) {
		$error = 'Could not encode input: '.$error;
	} elseif (($input = $obj->decode($output, $error)) === false) {
		$error = 'Could not decode output: '.$error;
	} elseif ($input !== $_POST['input']) {
		$error = 'Input does not match output';
	} else {
		$stats = [
			'Input Size' => \strlen($_POST['input']),
			'Output Size' => \strlen($output),
			'Output' => $output,	
			'Input Gzip' => \strlen(\gzencode($_POST['input'])),
			'Output Gzip' => \strlen(\gzencode($output)),
		];
		$stats['Compression'] = 100 - (100 / $stats['Input Size'] * $stats['Output Size']);
		$stats['Compression Gzip'] = 100 - (100 / $stats['Input Gzip'] * $stats['Output Gzip']);
		$stats['Time'] = \microtime(true) - $time;
		$msg = 'Input encoded successfully';
		var_dump($stats);
	}
}
?>
<!DOCTYPE html>
<html>
	<head>
		<title>4b - 4-Bit Text Encoder</title>
		<style>
			body {
				font-family: segoe ui;
			}
			.msg {
				padding: 10px;
				font-weight: bold;
				background: green;
				color: #FFF;
				margin-bottom: 15px;
			}

			.msg--error {
				background: red;
			}
		</style>
	</head>
	<body>
		<h1>4b - 4-Bit Text Encoder</h1>
		<?php if ($msg || $error) { ?>
			<div class="msg msg--<?= isset($error) ? 'error' : 'success'; ?>"><?= \htmlspecialchars($msg ?? $error); ?></div>
		<?php } ?>
		<form method="POST">
			<div>
				<label>Input Text:</label>
				<textarea name="input" style="width:80vw;height:80vh"><?= \htmlspecialchars($_POST['input'] ?? ''); ?></textarea>
			</div>
			<div>
				<label>Compression Map:</label>
				<select name="map">
					<option value="0"<?= $map === 0 ? ' selected="selected"' : ''; ?>>Text</option>
					<option value="1"<?= $map === 1 ? ' selected="selected"' : ''; ?>>HTML</option>
					<option value="2"<?= $map === 2 ? ' selected="selected"' : ''; ?>>HTML (5-bit)</option>
				</select>
			</div>
			<div>
				<input type="submit" name="encode" value="Encode" />
			</div>
		</form>
	</body>
</html>