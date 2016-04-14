<?php namespace davestewart\stringtemplate;


class StringTemplate
{

	// ------------------------------------------------------------------------------------------------
	// STATIC PROPERTIES

		public static $_tokenPattern = '/{([\.\w]+)}/';


	// ------------------------------------------------------------------------------------------------
	// PROPERTIES

		/**
		 * The source string comprising of various {tokens}
		 *
		 * @var string
		 */
		protected $source;

		/**
		 * The key => value replacement hash with which to interpolate the string
		 *
		 * @var mixed[]
		 */
		protected $tokens;

		/**
		 * The current array of matched {token} strings
		 *
		 * @var string[]
		 */
		protected $matchTokens;

		/**
		 * The current array of matched {token} "key" strings
		 *
		 * @var string[]
		 */
		protected $matchKeys;

		/**
		 * The regex to match a {token} and (capture) its key
		 *
		 * The pattern should have start and ending delimiters
		 *
		 * @var string
		 */
		protected $tokenPattern;

		/**
		 * The full regex to match the source string, including tokens, and capture passed content
		 *
		 * Generated and cached when match() is called
		 *
		 * @var string
		 */
		protected $sourcePattern;

		/**
		 * An array of filter regexes for each token in the source string
		 *
		 * Used when building the $sourcePattern to match arbitrary passed strings
		 *
		 * @var string[]
		 */
		protected $filterPatterns;


	// ------------------------------------------------------------------------------------------------
	// INSTANTIATION

		/**
		 * StringTemplate constructor.
		 *
		 * @param   string          $source     The StringTemplate source
		 * @param   string|null     $pattern    An optional token-matching regex, defaults to the global token regex {token}
		 */
		public function __construct($source, $pattern = null)
		{
			$this->setTokenPattern($pattern); // tokens need to be set first
			$this->setSource($source);
			$this->filterPatterns   = [];
			$this->tokens           = [];
			$this->matches          = [];
			$this->keys             = [];
		}

		/**
		 * Chainable StringTemplate constructor
		 *
		 * @param   string          $source     The StringTemplate source
		 * @param   string|null     $pattern    An optional token-matching regex, defaults to the global token regex {token}
		 * @return  StringTemplate
		 */
		public static function make($source, $pattern = null)
		{
			return new StringTemplate($source, $pattern);
		}


	// ------------------------------------------------------------------------------------------------
	// ACCESSORS

		/**
		 * Set the source string
		 *
		 * Also sets tokens and keys arrays
		 *
		 * @param   string      $source
		 * @return  self
		 */
		public function setSource($source)
		{
			preg_match_all($this->tokenPattern, $source, $matches);
			$this->matchTokens      = $matches[0];
			$this->matchKeys    = $matches[1];
			$this->source           = $source;
			$this->sourcePattern    = null;
			return $this;
		}

		/**
		 * Sets the token replacement
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
		 *  - a function that returns a string, of the form: function($key, $source, $instance) { }
		 *
		 * @param   string|array $name
		 * @param   array|null   $tokens
		 * @return  self
		 */
		public function setToken($name, $tokens = null)
		{
			if($tokens == null || $tokens === true)
			{
				$this->tokens = $tokens === true
					? $this->tokens + (array) $name
					: (array) $name;
			}
			else
			{
				$this->tokens[$name] = $tokens;
			}
			return $this;
		}

		/**
		 * Sets filter patterns for individual tokens
		 *
		 * Pass in:
		 *
		 *  - a name and pattern
		 *  - a single name => value hash, with an optional true to merge patterns
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
		 * @param   string|array    $name
		 * @param   array|null      $patterns
		 * @return  self
		 */
		public function setFilter($name, array $patterns = null)
		{
			$this->sourcePattern = null;
			if($patterns == null || $patterns === true)
			{
				$this->filterPatterns = $patterns === true
					? $this->filterPatterns + (array) $name
					: (array) $name;
			}
			else
			{
				$this->filterPatterns[$name] = $patterns;
			}
			return $this;
		}

		/**
		 * Sets the global token pattern regex
		 *
		 * @param   string          $pattern
		 * @return  self
		 */
		public function setTokenPattern($pattern)
		{
			$this->tokenPattern = $pattern ?: self::$_tokenPattern;
			return $this;
		}


	// ------------------------------------------------------------------------------------------------
	// RESOLVERS

		/**
		 * Expand source tokens, updating the source, and chain the original object
		 *
		 * Use this when you want to replace template tokens with variables,
		 * and leave some for further processing with process() or match()
		 *
		 * @param   bool    $filter     Optional flag to remove used data keys
		 * @return  self
		 */
		public function resolve($filter = false)
		{
			$this->setSource($this->replace($this->source, $this->tokens));
			if($filter)
			{
				$this->tokens = array_intersect_key($this->tokens, array_flip($this->matchKeys));
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
				? array_merge($this->tokens, $data)
				: $this->tokens;
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
			return self::make($this->process($data), $this->tokenPattern)
				->setToken($this->tokens)
				->setFilter($this->filterPatterns);
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
			if( ! $this->sourcePattern )
			{
				$this->sourcePattern = $this->getSourcePattern($delimiter);
			}

			// match
			preg_match($this->sourcePattern, $input, $matches);

			// convert matches to named capture array
			if(count($matches))
			{
				array_shift($matches);
				return array_combine($this->matchKeys, $matches);
			}
			return null;
		}

		/**
		 * Returns a regex that matches the source string and replacement pattern filters
		 *
		 * @param   string          $delimiter      An optional regex delimiter with which to make the matching pattern
		 * @return  string
		 */
		public function getSourcePattern($delimiter = '!')
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
			for ($i = 0; $i < count($this->matchKeys); $i++)
			{
				// variables
				$key                    = $this->matchKeys[$i];
				$token                  = $this->matchTokens[$i];
				$placeholder            = '%%' . strtoupper($key) . '%%';
				$filter                 = isset($this->filterPatterns[$key])
											? $this->filterPatterns[$key]
											: '.*';

				// update arrays
				$placeholders[$token]   = $placeholder;
				$filters[$placeholder]  = "($filter)";
			}

			// phase 2: replace tokens with placeholders
			foreach ($placeholders as $token => $placeholder)
			{
				$source = str_replace($token, $placeholder, $source);
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
			foreach($this->matchKeys as $index => $key)
			{
				// ignore unset keys
				if(isset($data[$key]))
				{
					// get the replacement
					$replace = $data[$key];

					// if not a string, resolve
					if( ! is_string($replace) )
					{
						if($replace instanceof StringTemplate)
						{
							$replace = $replace->process($data);
						}
						else if (is_callable($replace) )
						{
							$replace = call_user_func_array($replace, [$this->source, $source, $key, $index, $this]);
						}
					}

					// replace the original token
					$source = str_replace($this->matchTokens[$index], (string) $replace, $source);
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
