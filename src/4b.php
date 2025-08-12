<?php
declare(strict_types=1);
namespace hexydec\fourb;

class fourb {

	protected array $map = [

		// text
		[
			[' etaoinsrhldc', 'éETAOINSRHLDC'],
			['umfpgwybvkxjq', 'UMFPGWYBVKXJQ'],
			['z0123456789.,', "Z\$£€^|`\\~•çñ\r"],
			["-'\n!?():=<>/\"", "#&_+%;*[]{}@\t"]
		],

		// html
		[
			["<>=\"&;\n\t/.,?-", '#_%:|[]{}@!()'],
			[' etaoinsrhldc', 'ETAOINSRHLDCU'],
			['umfpgwybvkxjq', 'MFPGWYBVKXJQZ'],
			['z0123456789$+', "£€^*`~•\\\'\réç…"]
		],

		// html 5-bit
		[
			["<>=\"&;\n\t/.,?-#_%:|[]{}@!()\'\r", '67890$+£€¥^*`‘’“”~·•éçñ…—©®™\\'],
			[' etaoinsrhldcumfpgwybvkxjqz12', 'ETAOINSRHLDCUMFPGWYBVKXJQZ345']
		]
	];
	protected int $bits = 4;

	public function __construct(int $bits = 4) {
		$this->bits = $bits;
	}

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

	protected function getSets(int $key) : int|false {
		return isset($this->map[$key]) ? \count($this->map[$key]) : false;
	}

	protected function getLength(int $key) : int|false {
		return isset($this->map[$key]) ? \mb_strlen($this->map[$key][0][0]) : false;
	}

	protected function isValid(array $chars, string $input) : bool {
		return \strspn($input, \implode('', $chars)) === \strlen($input);
	}

	protected function intToBytes(array $data, int $bits) : string {
		$output = '';
		$buffer = 0;
		$size = 0; // Tracks how many bits are currently in the buffer.
		$mask = (1 << $bits) - 1; // mask to clean the integers

		foreach ($data AS $item) {

			// mask the nibble
			$item &= $mask;

			// shift current buffer and add new item
			$buffer = ($buffer << $bits) | $item;
			$size += $bits;

			// While the buffer has at least 8 bits, extract a full byte and append it to the output.
			while ($size >= 8) {

				// Get the most significant 8 bits from the buffer
				$byte = ($buffer >> ($size - 8)) & 0xFF;

				// Append the byte to our output string.
				$output .= \chr($byte);

				// Subtract 8 bits from the buffer size.
				$size -= 8;
			}
		}

		// Write remainining bits and blank out the rest
		if ($size > 0) {
			$byte = ($buffer << (8 - $size)) & 0xFF;
			$output .= \chr($byte);
		}
		return $output;
	}

	public function encode(int $map, string $input, ?string &$error = null) : string|false {

		// get mapping
		if (($chars = $this->getMap($map)) === false) {
			$error = 'Mapping '.$map.' does not exist';

		// check all the input characters have a mapped character
		} elseif (($spn = \strspn($input, \implode('', $chars))) !== \strlen($input)) {
			$error = 'Input has characters outside of mapping (...'.\mb_substr($input, $spn - 10, 20).'...) ('.$spn.') ('.\substr($input, $spn, 1).')';

		// encode
		} else {
			$flip = \array_flip($chars);
			$sets = $this->getSets($map);
			$half = $sets / 2;
			$len = $this->getLength($map);
			$ctrl = 3; // 3 control bits
			$data = [$map]; // record the mapping first
			$set = 0;
			$alt = false;
			$letters = \mb_str_split($input);
			foreach ($letters AS $char) {

				// calc position of requested char
				$key = $flip[$char];
				$cset = \intval(\floor($key / $len / 2));
				$calt = \floor($key / $len) % 2 === 1;

				// change set
				$rev = \abs($cset - $set) > $half ? $cset > $set : $cset < $set; // sometimes quicker to go the other way
				while ($cset !== $set) {
					$data[] = $rev ? 2 : 1;
					$set = ($set + ($rev ? -1 : 1) + $sets) % $sets;
					$alt = false;
				}

				// change alt
				if ($calt !== $alt) {
					$data[] = 0;
					$alt = $calt;
				}

				// add data
				$data[] = ($key % $len) + $ctrl;
			}
			return $this->intToBytes($data, $this->bits);
		}
		return false;
	}

	protected function bytesToInt(string $input, int $bits) : array {
		$data = [];
		$buffer = 0;
		$size = 0; // Tracks how many bits are currently in the buffer.
		$mask = (1 << $bits) - 1; // mask to clean the integers
		
		// loop through bytes
		foreach (\unpack('C*', $input) AS $item) {

			// Add current byte to buffer
			$buffer = ($buffer << 8) | $item;
			$size += 8;

			// While the buffer has at least $bits bits, extract an integer.
			while ($size >= $bits) {
				// Get the most significant $bits bits from the buffer.
				$integer = $buffer >> ($size - $bits);

				// Mask and append the integer
				$data[] = $integer & $mask;

				// Subtract $bits bits from the buffer size.
				$size -= $bits;
			}
		}
		return $data;
	}

	public function decode(string $input, ?string &$error = null) : string|false {

		// decode byte stream and fetch map
		$data = $this->bytesToInt($input, $this->bits);
		$map = \array_shift($data);
		if (($chars = $this->getMap($map)) === false) {
			$error = 'Mapping '.$map.' does not exist';

		// decode
		} else {
			$output = [];
			$sets = $this->getSets($map);
			$set = 0;
			$len = $this->getLength($map); // 3 control nibbles
			$ctrl = 3;
			$alt = false;
			foreach ($data AS $item) {

				// change alt
				if ($item === 0) {
					$alt = !$alt;

				// move forward and backwards
				} elseif (\in_array($item, [1, 2], true)) {
					$set = ($set + ($item === 1 ? 1 : -1) + $sets) % $sets;
					$alt = false;

				// decode data
				} else {
					$output[] = $chars[$item + ($len * ($set * 2 + \intval($alt))) - $ctrl];
				}
			}
			return \implode('', $output);
		}
		return false;
	}
}