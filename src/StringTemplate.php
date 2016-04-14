<?php namespace davestewart\stringtemplate;

class StringTemplate
{
	// ------------------------------------------------------------------------------------------------
	// PROPERTIES

		/**
		 * The global regex to match a {token} and (capture) its name
		 *
		 * The pattern should have start and ending delimiters
		 *
		 * @var string $_pattern
		 */
		public static $regex = '/{([\.\w]+)}/';

		/**
		 * The source string comprising of text and {tokens}
		 *
		 * @var string $source
		 */
		protected $source;

		/**
		 * The Tokens instance that holds token matching and replacement data
		 *
		 * @var Tokens
		 */
		protected $tokens;

		/**
		 * The Matches instance that holds string matching data
		 *
		 * @var Matches
		 */
		protected $matches;


	// ------------------------------------------------------------------------------------------------
	// INSTANTIATION

		/**
		 * StringTemplate constructor
		 *
		 * @param   string      $source     The StringTemplate source
		 * @param   string|null $regex      An optional token-matching regex, defaults to the global token regex {token}
		 */
		public function __construct($source, $regex = null)
		{
			if($regex == null)
			{
				$regex = self::$regex;
			}
			$this->tokens       = new Tokens($regex);
			$this->matches      = new Matches();
			$this->setSource($source);
		}

		/**
		 * Chainable StringTemplate constructor
		 *
		 * @param   string      $source     The StringTemplate source
		 * @param   string|null $regex      An optional token-matching regex, defaults to the global token regex {token}
		 * @return  StringTemplate
		 */
		public static function make($source, $regex = null)
		{
			return new StringTemplate($source, $regex);
		}


	// ------------------------------------------------------------------------------------------------
	// ACCESSORS

		/**
		 * Set the source string
		 *
		 * Also sets tokens arrays
		 *
		 * @param   string      $source
		 * @return  self
		 */
		public function setSource($source)
		{
			$this->source           = $source;
			$this->matches->regex   = null;
			preg_match_all($this->tokens->regex, $source, $matches);
			$this->tokens->matches  = $matches[0];
			$this->tokens->names    = $matches[1];
			return $this;
		}

		/**
		 * Sets the token replacement data
		 *
		 * Pass in:
		 *
		 *  - a name and value
		 *  - a single token => value hash, with an optional true to merge data
		 *
		 * Values can be:
		 *
		 *  - strings, numbers, or any stringable value
		 *  - a StringTemplate instance, for nested replacements
		 *  - a function that returns a string, of the form: function($name, $source, $instance) { }
		 *
		 * @param   string|array $name
		 * @param   array|null   $data
		 * @return  self
		 */
		public function setData($name, $data = null)
		{
			if($data == null || $data === true)
			{
				$this->tokens->data = $data === true
					? $this->tokens->data + (array) $name
					: (array) $name;
			}
			else
			{
				$this->tokens->data[$name] = $data;
			}
			return $this;
		}

		/**
		 * Sets match patterns for individual tokens
		 *
		 * Pass in:
		 *
		 *  - a name and regex
		 *  - a single name => value hash, with an optional boolean true to merge patterns
		 *
		 * Values should be regular expressions that match the expected token content
		 *
		 * DO NOT (!) include:
		 *
		 *  - regex delimiters
		 *  - capturing parentheses
		 *
		 * These will be added for you
		 *
		 * @param   string|array    $name        The name of the token to match
		 * @param   array|null      $regex      The regex pattern to match potential token content
		 * @return  self
		 */
		public function setMatch($name, array $regex = null)
		{
			$this->matches->regex = null;
			if($regex == null || $regex === true)
			{
				$this->matches->data = $regex === true
					? $this->matches->data + (array) $name
					: (array) $name;
			}
			else
			{
				$this->matches->data[$name] = $regex;
			}
			return $this;
		}


	// ------------------------------------------------------------------------------------------------
	// RESOLVERS

		/**
		 * Expand source tokens, updating the source, and chain the original object
		 *
		 * Use this when you want to replace template tokens with variables, so they're
		 * not processed in further loops. Usually you'll leave some tokens unresolved
		 * (by not supplying data for them) for later processing via process() or match()
		 *
		 * @param   bool    $filter     Optional flag to remove used data keys
		 * @return  self
		 */
		public function resolve($filter = false)
		{
			$this->setSource($this->replace($this->source, $this->tokens->data));
			if($filter)
			{
				$this->tokens->data = array_intersect_key($this->tokens->data, array_flip($this->tokens->names));
			}
			return $this;
		}

		/**
		 * Expand source tokens WITHOUT updating the source, and return the result
		 *
		 * Use this when you want to keep the source template tokens in place for further
		 * processing, for example you're running in a loop and only need the returned
		 * value
		 *
		 * @param   array   $data       Optional data to populate the string with; is merged with the existing data
		 * @return  string
		 */
		public function process($data = null)
		{
			$data = $data
				? array_merge($this->tokens->data, $data)
				: $this->tokens->data;
			return $this->replace($this->source, $data);
		}

		/**
		 * Special method to populate the source template, but return a chainable copy of the
		 * original StringTemplate
		 *
		 * Use this when replacements themselves return further tokens you need to
		 * populate via process() but you don't want to update the original source string
		 *
		 * @param   array|null      $data
		 * @return  self
		 */
		public function chain($data = null)
		{
			return self::make($this->process($data), $this->tokens->regex)
				->setData($this->tokens->data)
				->setMatch($this->matches->data);
		}

		/**
		 * Attempts to match an arbitrary string against the current template
		 *
		 * Use this when you need to check if an incoming string matches the constraints
		 *
		 * Internally, this method
		 *
		 * @param   string          $input          An input string to match
		 * @param   string          $delimiter      An optional regex delimiter with which to make the matching pattern
		 * @return  array|null
		 */
		public function match($input, $delimiter = '!')
		{
			// get regex
			if( ! $this->matches->regex )
			{
				$this->matches->regex = $this->getSourceRegex($delimiter);
			}

			// match
			preg_match($this->matches->regex, $input, $matches);

			// convert matches to named capture array
			if(count($matches))
			{
				array_shift($matches);
				return array_combine($this->tokens->names, $matches);
			}
			return null;
		}

		/**
		 * Returns a regex that matches the source string and replacement pattern filters
		 *
		 * @param   string          $delimiter      An optional regex delimiter with which to make the matching pattern
		 * @return  string
		 */
		public function getSourceRegex($delimiter = '!')
		{
			// the process of building a suitable match string is complicated due to the
			// fact that we need to escape the source string - but don't want to escape
			// either the original tokens, or the the regex matches that will match the
			// target string. as such, we need to employ some substitution trickery, and
			// build the final regex in 4 phases.
			//
			// the 1st phase sets up the data we will need for the following phases,
			// including adding capturing brackets around the regexps. the 2nd phase swaps
			// the original tokens for placeholders which are formatted in a way be immune
			// to escaping; this will allow us to escape the entire string in the 3rd phase,
			// then in 4th phase, swap out the placeholders for the individual regexes.

			// variables
			$source         = $this->source;
			$placeholders   = [];
			$filters        = [];

			// phase 1: build arrays
			for ($i = 0; $i < count($this->tokens->names); $i++)
			{
				// variables
				$name                    = $this->tokens->names[$i];
				$match                  = $this->tokens->matches[$i];
				$placeholder            = '%%' . strtoupper($name) . '%%';
				$filter                 = isset($this->matches->data[$name])
											? $this->matches->data[$name]
											: '.*';

				// update arrays
				$placeholders[$match]   = $placeholder;
				$filters[$placeholder]  = "($filter)";
			}

			// phase 2: replace tokens with placeholders
			foreach ($placeholders as $match => $placeholder)
			{
				$source = str_replace($match, $placeholder, $source);
			}

			// phase 3: quote source
			$source = preg_quote($source, $delimiter);

			// phase 4: replace placeholders with filters
			foreach ($filters as $placeholder => $filter)
			{
				$source = str_replace($placeholder, $filter, $source);
			}

			// debug
			//pd($tokens, $regexs, $source);

			// return
			return $delimiter . $source . $delimiter;
		}

	// ------------------------------------------------------------------------------------------------
	// PROTECTED METHODS

		protected function replace($source, array $data)
		{
			foreach($this->tokens->names as $index => $name)
			{
				// ignore unset keys
				if(isset($data[$name]))
				{
					// get the replacement
					$replace = $data[$name];

					// if not a string, resolve
					if( ! is_string($replace) )
					{
						if($replace instanceof StringTemplate)
						{
							$replace = $replace->process($data);
						}
						else if (is_callable($replace) )
						{
							$replace = call_user_func_array($replace, [$this->source, $source, $name, $index, $this]);
						}
					}

					// replace the original token
					$source = str_replace($this->tokens->matches[$index], (string) $replace, $source);
				}
			}

			// return
			return $source;
		}


	// ------------------------------------------------------------------------------------------------
	// UTILITIES

		public function __toString()
		{
			return (string) $this->process();
		}

}

/**
 * Class Tokens
 *
 * Holds token matching and replacement data
 */
class Tokens
{
	/**
	 * The list of captured tokens
	 *
	 * @var string[]
	 */
	public $matches;

	/**
	 * The list of captured token names
	 *
	 * @var string[]
	 */
	public $names;

	/**
	 * The name => value replacement hash with which to interpolate the string
	 *
	 * @var mixed[]
	 */
	public $data;

	/**
	 * Global regex to capture tokens
	 *
	 * @var string
	 */
	public $regex;

	/**
	 * Tokens constructor
	 *
	 * @param   string|null   $regex    Optional regex for token matches
	 */
	public function __construct($regex)
	{
		$this->regex = $regex;
		$this->data = [];

	}

}

/**
 * Class Matches
 *
 * Holds string matching data
 */
class Matches
{
	/**
	 * An array of filter regexes for each token in the source string
	 *
	 * @var string[]
	 */
	public $data;

	/**
	 * The full regex to match the source string, including tokens, and capture passed content
	 *
	 * This has to be cached as the process to create it is expensive
	 *
	 * @var string
	 */
	public $regex;

	/**
	 * Matches constructor
	 */
	public function __construct()
	{
		$this->data = [];
	}
}
