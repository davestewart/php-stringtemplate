<?php namespace davestewart\tokenstring;

use davestewart\tokenstring\renderers\TokenRenderer;

/**
 * Class TokenString
 *
 * @package davestewart\tokenstring
 *
 * @property Patterns $patterns
 * @property string $source
 * @property string $value
 */
class TokenString
{
	// ------------------------------------------------------------------------------------------------
	// CONFIGURATION

		/**
		 * Global configuration options
		 *
		 * @var TokenStringConfig
		 */
		protected static $config;

		/**
		 * Static configuration method
		 *
		 * See the TokenStringConfig class for more info
		 *
		 * @param   string  $name           The configuration key
		 * @param   string  $value,...      Variable configuration values
		 * @return  TokenStringConfig
		 */
		public static function configure($name = null, $value = null)
		{
			// set up new configuration
			if(self::$config == null)
			{
				self::$config = new TokenStringConfig();
			}

			// configure
			if($name && $value)
			{
				$method     = 'set' . ucfirst($name);
				$params     = array_slice(func_get_args(), 1);
				call_user_func_array([self::$config, $method], $params);
			}
			
			// return
			return self::$config;
		}

	
	// ------------------------------------------------------------------------------------------------
	// PROPERTIES

		/**
		 * The source string comprising of text and {tokens}
		 *
		 * @var string $source
		 */
		protected $source;

		/**
		 * A name => match hash of tokens matches
		 *
		 * @var Token[]
		 */
		protected $tokens;

		/**
		 * The name => value replacement hash with which to interpolate the string
		 *
		 * @var TokenRenderer[]
		 */
		protected $data;

		/**
		 * A name => regex hash of token content filters
		 *
		 * @var string[]
		 */
		protected $filters;

		/**
		 * Regex patterns for matching tokens and source
		 *
		 * - The regex to match only a {token} and (capture) its name string
		 * - The regex to both match in full, and capture token content from, the source string
		 *
		 * @var Patterns
		 */
		protected $patterns;


	// ------------------------------------------------------------------------------------------------
	// INSTANTIATION

		/**
		 * StringTemplate constructor
		 *
		 * @param   string          $source         An optional source string
		 * @param   string|null     $data           Optional replacement data
		 */
		public function __construct($source = '', $data = null)
		{
			// properties
			$this->data             = [];
			$this->tokens           = [];
			$this->filters          = [];
			$this->patterns         = new Patterns(self::$config->getToken());

			// parameters
			if($source)
			{
				$this->setSource($source);
			}
			if($data)
			{
				$this->setData($data);
			}
		}

		/**
		 * Chainable TokenString constructor
		 *
		 * @param   string          $source         An optional source string
		 * @param   string|null     $data           Optional replacement data
		 * @return  TokenString
		 */
		public static function make($source = '', $data = null)
		{
			return new TokenString($source, $data);
		}


	// ------------------------------------------------------------------------------------------------
	// ACCESSORS

		/**
		 * Set the source string
		 *
		 * Also sets tokens arrays
		 *
		 * @param   string      $source
		 * @return  TokenString
		 */
		public function setSource($source)
		{
			// properties
			$this->source = $source;
			$this->patterns->source = '';

			// match source
			preg_match_all($this->patterns->token, $source, $matches);

			// create basic "selector" => "{match}" array
			$this->tokens = array_combine($matches[1], $matches[0]);

			// convert basic array to an array of Token objects
			foreach ($this->tokens as $selector => $match)
			{
				$this->tokens[$selector] = new Token($selector, $match);
			}

			// return
			return $this;
		}

		/**
		 * Sets replacement data
		 *
		 * Pass in:
		 *
		 *  - a name and value
		 *  - a single token => value hash, with an optional true to merge data
		 *
		 * Values can be:
		 *
		 *  - strings, numbers, or any stringable value
		 *  - a TokenString instance, for nested replacements
		 *  - a function that returns a string, of the form: function($name, $source, $instance) { }
		 *
		 * @param   string|array $name
		 * @param   array|null   $value
		 * @return  TokenString
		 */
		public function setData($name, $value = null)
		{
			// if an array is passed
			if(is_array($name))
			{
				// clear data if true is not passed
				if($value !== true)
				{
					$this->data = [];
				}

				// convert numeric arrays to associative, using source matches
				$name = $this->makeAssociative($name);

				// add values, one at a time
				foreach ($name as $k => $v)
				{
					$this->setData($k, $v);
				}
			}

			// if a value is passed
			else
			{

				$this->data[$name] = TokenRenderer::make($value);
			}
			return $this;
		}

		/**
		 * Sets filter patterns for individual source tokens
		 *
		 * Pass in:
		 *
		 *  - a name and regex
		 *  - a single name => value hash
		 *  - a single numeric array
		 *
		 * Note that:
		 *
		 *  - for arrays, an optional boolean true to merge patterns
		 *  - values should be regular expressions that match the expected token content
		 *
		 * DO NOT (!) include:
		 *
		 *  - regex delimiters
		 *  - capturing parentheses
		 *
		 * These will be added for you
		 *
		 * @param   string|array    $name       The name of the token to match
		 * @param   array|null      $regex      The regex pattern to match potential token content
		 * @return  TokenString
		 */
		public function setFilter($name, array $regex = null)
		{
			// clear existing match
			$this->patterns->source = '';

			if(is_array($name))
			{
				$name = $this->makeAssociative($name);
				$this->filters = $regex === true
					? $this->filters + $name
					: $name;
			}
			else
			{
				if($regex)
				{
					$this->filters[$name] = $regex;
				}
				else
				{
					unset($this->filters[$name]);
				}
			}
			return $this;
		}

		public function __get($name)
		{
			if($name === 'value')
			{
				return (string) $this->render();
			}
			if(property_exists($this, $name))
			{
				return $this->$name;
			}
			throw new \Exception("Unknown property '$name'");
		}

	// ------------------------------------------------------------------------------------------------
	// PUBLIC METHODS

		/**
		 * Render and return the string by expanding source tokens, without updating the source
		 *
		 * Use this when you want to keep the source template tokens in place for further
		 * processing, for example you're running in a loop and only need the returned
		 * value
		 *
		 * Note that you can also pass in multiple parameters which will match the source tokens
		 * as they are found
		 *
		 * @param   array   $data       Optional data to populate the string with; is merged with the existing data
		 * @return  string
		 */
		public function render($data = null)
		{
			// no data passed, just use stored data
			if(func_num_args() == 0)
			{
				$data = $this->data;
			}

			// otherwise, convert and merge
			else
			{
				// grab data
				$data = is_array($data) ? $data : func_get_args();

				// ensure numeric arrays are converted
				$data = $this->makeAssociative($data);

				// ensure complex data types are converted to TokenRenderers
				foreach ($data as $key => $value)
				{
					$data[$key] = TokenRenderer::make($value);
				}

				// merge stored and new data
				$data = array_merge($this->data, $data);				
			}

			// render
			return $this->replace($this->source, $data);
		}

		/**
		 * Expand source tokens, update the source, and chain the original object
		 *
		 * Use this when you want to replace source tokens with the current data, so they're
		 * not processed in further loops. Usually you'll leave some tokens unresolved
		 * (by not supplying data for them) for later processing via render() or match()
		 *
		 * @param   bool    $filter     Optional flag to remove expanded data keys
		 * @return  TokenString
		 */
		public function resolve($filter = false)
		{
			// cache old tokens;
			$tokens = [] + $this->tokens;

			// update string
			$this->setSource($this->replace($this->source, $this->data));

			// filter out old data
			if($filter)
			{
				$this->data = array_intersect_key($this->data, $tokens);
			}

			// return
			return $this;
		}

		/**
		 * Special method to expand variables, and return the updated of the
		 * original TokenString instance
		 *
		 * Use this when replacements themselves return further tokens you need to
		 * populate via render() but you don't want to update the original source string
		 *
		 * @param   array|null      $data
		 * @return  TokenString
		 */
		public function chain($data = null)
		{
			return $this->setSource($this->render($data ?: $this->data));
		}

		/**
		 * Attempts to match an arbitrary string against the current template
		 *
		 * Use this when you need to check if an incoming string matches the constraints
		 *
		 * Internally, this method
		 *
		 * @param   string  $input      An input string to match
		 * @param   string  $format     An optional regex format to surround the source-matching pattern.
		 *                              See the TokenStringConfig class for more info
		 * @return  array|null
		 */
		public function match($input, $format = null)
		{
			// get regex
			if($this->patterns->source === '')
			{
				$this->patterns->source = $this->getSourceRegex($format);
			}

			// match
			preg_match($this->patterns->source, $input, $matches);

			// convert matches to named capture array
			if(count($matches))
			{
				array_shift($matches);
				return array_combine(array_keys($this->tokens), $matches);
			}
			return null;
		}

		/**
		 * Returns a regex that matches the source string and replacement pattern filters
		 *
		 * @param   string  $format     An optional regex format to surround the source-matching pattern.
		 *                              See the TokenStringConfig class for more info
		 * @return  string
		 */
		public function getSourceRegex($format = null)
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
			$format         = $format ?: self::$config->getSource();
			$delimiter      = substr($format, 0, 1);
			$source         = $this->source;
			$placeholders   = [];
			$filters        = [];

			// phase 1: build arrays
			foreach ($this->tokens as $name => $token)
			{
				// variables
				$placeholder        = '%%' . strtoupper($name) . '%%';
				$filter             = isset($this->filters[$name])
										? $this->filters[$name]
										: '.*';

				// update arrays
				$placeholders[$token->match]    = $placeholder;
				$filters[$placeholder]          = "($filter)";
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
				// also, escape any delimiter characters found in the filter replacements
				$source = str_replace($placeholder, str_replace($delimiter, '\\' . $delimiter, $filter), $source);
			}

			// debug
			//pd($source);

			// return
			return str_replace('source', $source, $format);
		}

	// ------------------------------------------------------------------------------------------------
	// PROTECTED METHODS

		protected function replace($source, array $data)
		{
			foreach($this->tokens as $name => /** @var Token */ $token)
			{
				if(isset($data[$token->name]))
				{
					// get value
					$value = $data[$token->name];

					// transform value
					if($value instanceof TokenRenderer)
					{
						$value = $value->render($token, $data, $source);
					}

					// filter
					if($token->filters)
					{
						foreach ($token->filters as $filter)
						{
							if(is_callable($filter))
							{
								$value = call_user_func($filter, $value);
							}
						}
					}

					// replace
					$source = str_replace($token->match, (string) $value, $source);
				}
			}

			// return
			return $source;
		}

		/**
		 * Checks if passed array is numeric, and if so, converts to associative,
		 * using the current match values. Output array is clipped to the shorter
		 * of the two array lengths
		 *
		 * @param   array   $values
		 * @return  array
		 */
		protected function makeAssociative($values)
		{
			// return if already associative
			if($this->isAssociative($values))
			{
				return $values;
			}

			// get existing keys
			$names       = array_keys($this->tokens);
			$numNames    = count($names);
			$numValues   = count($values);

			// make arrays the same length
			if($numNames < $numValues)
			{
				$values = array_slice($values, 0, $numNames);
			}
			else if($numNames > $numValues)
			{
				$names = array_slice($names, 0, $numValues);
			}

			// return
			return array_combine($names, $values);
		}

		protected function isAssociative($arr)
		{
			foreach ($arr as $key => $value)
			{
				if (is_string($key)) return true;
			}
			return false;
		}


	// ------------------------------------------------------------------------------------------------
	// UTILITIES
	
		public function __toString()
		{
			return (string) $this->render();
		}

}

class Patterns
{
	public $source;

	public $token;

	public function __construct($token, $source = '')
	{
		$this->token    = $token;
		$this->source   = $source;
	}
}


TokenString::configure();

