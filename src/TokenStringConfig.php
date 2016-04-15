<?php namespace davestewart\tokenstring;

/**
 * Class TokenStringConfig
 *
 * @package davestewart\tokenstring
 *
 * Manages creation of regex patterns for token replacement and source matching
 */
class TokenStringConfig
{

	// ------------------------------------------------------------------------------------------------
	// SETUP

		public function __construct()
		{
			$this->setToken('{([a-z][\.\w]*)}', 'i');
			$this->setSource('^source$', 'i');
		}


	// ------------------------------------------------------------------------------------------------
	// DELIMITER

		/**
		 * Global regex delimiter
		 *
		 * @var string
		 */
		protected $delimiter = '~';

		public function getDelimiter() { return $this->delimiter; }

		public function setDelimiter($value)
		{
			$this->delimiter = substr( (string) $value, 0, 1);
			return $this;
		}


	// ------------------------------------------------------------------------------------------------
	// TOKEN

		/**
		 * The regex to match a {token} and (capture) its name
		 *
		 * @var string
		 */
		protected $token;

		public function getToken() { return $this->token; }

		/**
		 * Sets the regex for source pattern matching
		 *
		 *  - DO NOT include start or end anchors or word boundaries
		 *  - MUST include token identifiers
		 *  - MUST include capturing parenthesis for key name
		 *  - reserved regex characters for token identifiers MUST be escaped
		 *
		 * @example             `([a-z]+)`      `token`
		 * @example             {([a-z]+)}      {token}
		 * @example             \${([a-z]+)}    ${token}
		 *
		 * @param   string $value    The regex pattern WITHOUT delimiters
		 * @param   string $modifier An optional mode modifier string
		 * @return  $this
		 * @throws  \Exception
		 */
		public function setToken($value, $modifier = '')
		{
			// test
			if( ! preg_match('/(\(.+?\))/', $value) )
			{
				throw new \Exception('StringToken `token` matching pattern MUST contain capturing parenthesis');
			}

			if(strpos($value, '^') === 0 || preg_match('/[^\\\\]\$$/', $value))
			{
				throw new \Exception('StringToken `token` matching pattern MUST NOT contain anchors');
			}

			// set
			$this->token = static::make($value, $this->delimiter, $modifier);

			// return
			return $this;
		}


	// ------------------------------------------------------------------------------------------------
	// SOURCE

		/**
		 * The regex format to surround the source-matching pattern;
		 *
		 * @var string
		 */
		protected $source;

		public function getSource() { return $this->source; }

		/**
		 * Sets the regex for source pattern matching
		 *
		 *  - include or omit start and end anchors as required; defaults to both (^ and $)
		 *  - use the string "source" (without quotes) as the placeholder for the source regex
		 *
		 * @example             ^source$        Must match entire string (the default)
		 * @example             ^source         Must match start of string
		 * @example             source$         Must match end of string
		 * @example             source          Must match part of the string
		 *
		 * @param   string $value    The regex pattern WITHOUT delimiters
		 * @param   string $modifier An optional mode modifier string
		 * @return  $this
		 * @throws  \Exception
		 */
		public function setSource($value, $modifier = '')
		{
			// test
			if(strstr($value, 'source') === FALSE)
			{
				throw new \Exception('StringToken `source` matching pattern MUST contain the string "source"');
			}

			// set
			$this->source = static::make($value, $this->delimiter, $modifier);;

			// return
			return $this;
		}


	// ------------------------------------------------------------------------------------------------
	// UTILS

		public static function make($value, $delimiter = '~', $modifier = '')
		{
			return $delimiter . $value . $delimiter . $modifier;
		}


}
