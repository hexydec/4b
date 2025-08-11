<?php
declare(strict_types=1);
require __DIR__.'/src/4b.php';

$error = null;
$msg = null;
if (!empty($_POST['input'])) {
	$obj = new \hexydec\fourb\fourb();
	if (isset($_POST['encode'])) {
		if (($output = $obj->encode(0, $_POST['input'], $error)) !== false) {
			if (($input = $obj->decode($output, $error)) !== false) {
				$msg = 'Input encoded successfully';
				var_dump($output, $input);
			}
		}
	} elseif (isset($_POST['decode'])) {
		if (($output = $obj->decode($_POST['input'], $error)) !== false) {
			$msg = 'Input decoded successfully';
		}
	}
}
?>
<!DOCTYPE html>
<html>
	<head>
		<title>4b - 4-Bit Text Encoder</title>
	</head>
	<body>
		<h1>4b - 4-Bit Text Encoder</h1>
		<?php if ($msg || $error) { ?>
			<div class="msg msg--<?= isset($error) ? 'error' : 'success'; ?>"><?= \htmlspecialchars($msg ?? $error); ?></div>
		<?php } ?>
		<form method="POST">
			<div>
				<label>Input Text:</label>
				<textarea name="input"><?= \htmlspecialchars($_POST['input'] ?? ''); ?></textarea>
			</div>
			<div>
				<input type="submit" name="encode" value="Encode" />
				<input type="submit" name="decode" value="Decode" />
			</div>
		</form>
	</body>
</html>