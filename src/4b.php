<?php
declare(strict_types=1);
namespace hexydec\fourb;

class fourb {

	protected array $map = [

		// text
		[
			[' etaoinsrhldc', '¥ETAOINSRHLDC'],
			['umfpgwybvkxjq', 'UMFPGWYBVKXJQ'],
			['z0123456789.,', "Z\$£€^|`\\•éçñ\r"],
			["-'\n!?():=<>/\"", "#&_+%~*[]{}@\t"]
		]
	];

	protected function getMap(int $key) : array|false {
		if (isset($this->map[$key])) {
			$chars = [];
			foreach ($this->map[$key] AS $item) {
				$chars = \array_merge($chars, \mb_str_split($item[0]), \mb_str_split($item[1]));
			}
			return $chars;
		}
		return false;
	}

	protected function numCtrl(int $key) : int|false {
		return isset($this->map[$key]) ? \count($this->map[$key]) : false;
	}

	protected function isValid(array $chars, string $input) : bool {
		return \strspn($input, \implode('', $chars)) === \strlen($input);
	}

	protected function intToBytes(array $data) : string {
		var_dump($data);
		$bytes = [];
		for ($i = 0; $i < \count($data); $i += 2) {
			$bytes[] = (($data[$i + 1] ?? 0) << 4) | $data[$i];
		}
		return \pack('C*', ...$bytes);
	}

	public function encode(int $map, string $input, ?string &$error = null) : string|false {
		if (($chars = $this->getMap($map)) === false) {
			$error = 'Mapping '.$map.' does not exist';
		} elseif (($ctrl = $this->numCtrl($map)) === false) {
			$error = 'Mapping '.$map.' does not exist';
		} elseif (!$this->isValid($chars, $input)) {
			$error = 'Input has characters outside of mapping';
		} else {
			$flip = \array_flip($chars);
			$len = 16 - $ctrl + 1;
			$data = [$map];
			$set = 0;
			$alt = true;
			$auto = true;
			$letters = \mb_str_split($input);
			foreach ($letters AS $i => $char) {

				// turn on auto alt
				if ($char === "\n" && $letters[$i + 1] !== "\n") {
					$alt = true;
					$auto = true;
				} elseif ($char === 'I' && $letters[$i - 1] === ' ' && $letters[$i + 1] === ' ') {
					$alt = true;
					$auto = true;
				}

				// calc position of requested char
				$key = $flip[$char];
				$cset = \intval(\floor($key / $len / 2));
				$calt = \floor($key / $len) % 2 === 1;
				// var_dump($char, $key, $set, $cset, $calt);

				// change alt
				if (($cset === $set || $calt) && $calt !== $alt) {
					$nset = \intval(($set + ($set + 1 === $cset ? 1 : 2)) % $ctrl);
					$data[] = $nset - ($cset > $set ? 1 : 0);
					$set = $nset;
					// var_dump('alt', $set);
				}
				$alt = $calt;

				// change set
				if ($cset !== $set) {
					$data[] = $cset - ($cset > $set ? 1 : 0);
					$set = $cset;
				}

				// add data
				$data[] = $key % $len + $ctrl - 1;

				// turn alt off automatically
				if ($auto) {
					$alt = false;
					$auto = false;
				}
			}
			return $this->intToBytes($data);
		}
		return false;
	}

	protected function bytesToInt(string $bytes) : array {
		$data = [];
		foreach (\unpack('C*', $bytes) AS $item) {
			$data[] = $item & 0b00001111;
			$data[] = $item >> 4;
		}
		return $data;
	}

	public function decode(string $input, ?string &$error = null) : string|false {
		$data = $this->bytesToInt($input);
		$map = \array_shift($data);
		if (($chars = $this->getMap($map)) === false) {
			$error = 'Mapping '.$map.' does not exist';
		} elseif (($ctrl = $this->numCtrl($map)) === false) {
			$error = 'Mapping '.$map.' does not exist';
		} else {
			$output = [];
			$set = 0;
			$len = 16 - $ctrl + 1;
			$alt = true;
			$auto = true;
			$cset = false;
			foreach ($data AS $item) {

				// change set
				if ($item < $ctrl - 1) {
					var_dump('switch', $item, $cset);
					$set = $item + ($item >= $set ? 1 : 0);
					if ($cset) {
						$alt = !$alt;
						$cset = false;
					} else {
						$cset = true;
					}
				} else {
					if ($cset) {
						$alt = false;
						$cset = false;
					}

					// extract character
					var_dump('print', $item, $set, $alt, ($len * ($set * 2 + \intval($alt))));
					$output[] = $chars[$item + ($len * ($set * 2 + \intval($alt))) - $ctrl + 1];

					// reset alt
					if ($auto) {
						$alt = false;
						$auto = false;
					}
				}

			}
			var_dump($data, $output);

		}
		return false;
	}
}