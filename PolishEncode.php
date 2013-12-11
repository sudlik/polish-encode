<?php

/** PolishEncode
 * @brief	Conversion ISO-8859-2 and WINDOWS-1250 to UTF-8.
 * 			Based on BIBLIOTEKA PL (http://gajdaw.pl/download/varia/polskie-ogonki-na-www/examples/7-1-biblioteka-pl.zip).
 * @todo
 * - support recognize utf-* mixed string
 * - support convert utf-* mixed string to utf
 * - enhance docs
 */

// BIBLIOTEKA PL
// ver 0.9
// 2005.09.20
//
//
//Kodowanie polskich znaków:
//
//  ISO-8859-2      - polskie znaki iso
//  WINDOWS-1250    - polskie znaki win
//  ASCII           - brak jakichkolwiek polskich znaków
//  WIN-AND-ISO     - plik zepsuty: zawiera zarówno znaki WIN jak i ISO (specyficzne)
//  WIN-OR-ISO      - plik nie zawiera znaków specyficznych żadnego kodu, ale zawiera znaki wspólne
//  UTF-8           - kodowanie utf-8
//  UTF-16          - kodowanie utf-16


// Biblioteka mb, a w szczególności funkcja mb_detect_encoding() nie
// umożliwiajš wykrycia innego kodowania niż ISO-8859-2 lub UTF-8 (w stosunku do polskich znaków).
// Wywołanie:
//     echo mb_detect_encoding($org, 'ISO-8859-2, WINDOWS-1250, UTF-8');
// nie da pożšdanych efektów, gdyż kodowanie WINDOWS-1250 nie jest rozpoznawane.
//
// Ponadto funkcja iconv() w przypadku napotkania niedozwolonych znaków kończy przetwarzanie.
// Stąd potrzeba przygotowania własnej funkcji pl_detect().
//
//
// (c)2005 gajdaw
//  http://www.gajdaw.pl
//
//

class PolishEncode
{

	/** Supported encodings:
	 * - UTF-8
	 * - ISO-8859-2
	 * - WINDOWS-1250
	 */
	const ENCODE_UTF = 'UTF-8';
	const ENCODE_ISO = 'ISO-8859-2';
	const ENCODE_WIN = 'WINDOWS-1250';

	/** Detect encode result:
	 * - UTF-8
	 * - ISO-8859-2
	 * - WINDOWS-1250
	 * - ISO-8859-2 or WINDOWS-1250
	 * - ISO-8859-2 and WINDOWS-1250 mixed
	 * - unrecognized
	 */
	const DETECTED_UTF			= 0;
	const DETECTED_ISO			= 1;
	const DETECTED_WIN			= 2;
	const DETECTED_ISO_WIN_MIX	= 3;
	const DETECTED_ISO_WIN_OR	= 4;
	const DETECTED_UNRECOGNIZED	= 5;

	const COUNT_CHARS = 3;

	/** Char code sets:
	 * - common codes for: "ćęłńóżĆĘŁŃÓŻ"
	 * - ISO-8859-2 specific codes for "ąśźĄŚŹ"
	 * - WINDOWS-1250 specific codes
	 */
	private static $_CHARS_ISO		= ["\xb1", "\xb6", "\xbc", "\xa1", "\xa6", "\xac"];
	private static $_CHARS_WIN		= ["\xb9", "\x9c", "\x9f", "\xa5", "\x8c", "\x8f"];
	private static $_CHARS_ISO_WIN	= ["\xe6", "\xea", "\xb3", "\xf1", "\xf3", "\xbf", "\xc6", "\xca", "\xa3", "\xd1", "\xd3", "\xaf"];

	private $_Content	= null;
	private $_Converted	= null;
	private $_Detected	= null;
	private $_Encode	= null;
	private $_Chars		= [];

	public function __construct($content, $encode = null, array $chars = null)
	{
		$this->_setContent($content);

		if ($chars) {
			$this->_setChars($chars);
		} else {
			$this->_Chars = $this->_getChars();
		}

		if ($encode) {
			$this->_setDetected($encode);
		} else {
			$this->_Detected = $this->_getDetected();
		}

		$this->_Encode = $this->_asEncode($this->_Detected);
	}

	private function _setContent($content)
	{
		if (!is_string($content)) {
			throw new InvalidArgumentException('Content must be type of string: ' . var_export($content, true));
		} elseif (empty($content)) {
			throw new InvalidArgumentException('Content can not be empty');
		} else {
			$this->_Content = $content;
		}
	}

	private function _setDetected($encode)
	{
		switch ($encode) {
			case self::DETECTED_UTF:
			case self::DETECTED_ISO:
			case self::DETECTED_WIN:
			case self::DETECTED_ISO_WIN_MIX:
			case self::DETECTED_ISO_WIN_OR:
				$this->_Encode = $encode;
				break;
			default:
				throw new InvalidArgumentException('Undefined encode: ' . var_export($encode, true));
				break;
		}
	}

	public function _getDetected()
	{
		$iso = $this->isIso();
		$win = $this->isWin();

		if ($iso && $win) {
			return self::DETECTED_ISO_WIN_MIX;
		} elseif ($iso && !$win) {
			return self::DETECTED_ISO;
		} elseif (!$iso && $win) {
			return self::DETECTED_WIN;
		} elseif ($this->_isIsoWin()) {
			return self::DETECTED_ISO_WIN_OR;
		} elseif($this->isUtf()) {
			return self::DETECTED_UTF;
		} else {
			return self::DETECTED_UNRECOGNIZED;
		}
	}

	public function getDetected()
	{
		return $this->_Detected;
	}

	public function getEncode()
	{
		return $this->_Encode;
	}

	public function _asEncode($type)
	{
		switch ($type)
		{
			case self::DETECTED_UTF:
				return self::ENCODE_UTF;
			case self::DETECTED_ISO:
				return self::ENCODE_ISO;
			case self::DETECTED_WIN:
				return self::ENCODE_WIN;
			default:
				return FALSE;
		}
	}

	public function isEncode($encode)
	{
		switch($encode)
		{
			case self::ENCODE_UTF:
			case self::ENCODE_ISO:
			case self::ENCODE_WIN:
				return $this->_Encode === $encode;
			default:
				throw new InvalidArgumentException('Undefined encode: ' . var_export($encode, true));
		}
	}

	public function isUtf()
	{
		if ($this->_Encode === NULL) {
			return mb_detect_encoding($this->_Content, self::ENCODE_UTF) === self::ENCODE_UTF;
		}

		return $this->_Encode === self::DETECTED_UTF;
	}

	public function _isChars($chars, $set)
	{
		return !!array_intersect($chars, $set);
	}

	public function isIso()
	{
		if ($this->_Encode === null) {
			return $this->_isIso($this->_Chars);
		}

		return $this->_Encode === self::DETECTED_UTF;
	}

	public function _isIso(array $chars)
	{
		return $this->_isChars($chars, self::$_CHARS_ISO);
	}

	public function isWin()
	{
		if ($this->_Encode === null) {
			return $this->_isChars($this->_Chars, self::$_CHARS_WIN);
		}

		return $this->_Encode === self::DETECTED_UTF;
	}

	private function _isIsoWin()
	{
		return $this->_isChars($this->_Chars, self::$_CHARS_ISO_WIN);
	}

	private function _setChars($chars)
	{
		$count = count($chars);
		if ($count !== count($chars, COUNT_RECURSIVE)) {
			throw new InvalidArgumentException('Chars must be single-dimensional array: ' . var_export($chars, true));
		} elseif ($count !== count(array_filter($chars)) || !ctype_xdigit( implode('', $chars))) {
			throw new InvalidArgumentException('Chars must be array of hexadecimal strings: ' . var_export($chars, true));
		} else {
			$this->_Chars = $chars;
		}
	}

	private function _getChars()
	{
		return str_split(count_chars($this->_Content, self::COUNT_CHARS));
	}

	public function getConverted()
	{
		if (!$this->_Converted) {
			$this->_Converted = $this->_getUtf();
		}

		return $this->_Converted;
	}

	private function _getUtf()
	{
		if ($this->_Encode === self::ENCODE_UTF) {
			return $this->_Content;
		} elseif ($this->_Detected === self::DETECTED_ISO_WIN_OR) {
			return $this->_asUtf(self::ENCODE_ISO, $this->_Content);
		} elseif ($this->_Detected === self::DETECTED_ISO_WIN_MIX) {
			return $this->_isoWinAsUtf();
		} elseif ($this->_Detected === self::DETECTED_UNRECOGNIZED) {
			return false;
		} else {
			return $this->_asUtf($this->_Encode, $this->_Content);
		}
	}

	private function _asUtf($encode, $content)
	{
		return iconv($encode, self::ENCODE_UTF, $content);
	}

	private function _isoWinAsUtf()
	{
		for ($c = '', $i = 0, $l = strlen( $this->_Chars ); $i < $l; $i++) {
			if ($this->_isIso([$this->_Chars[$i]])) {
				$c .= $this->_asUtf(self::ENCODE_ISO, $this->_Chars[$i]);
			} else {
				$c .= $this->_asUtf(self::ENCODE_WIN, $this->_Chars[$i]);
			}
		}

		return $c;
	}

}