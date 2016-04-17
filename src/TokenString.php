<?php namespace davestewart\tokenstring;

use davestewart\tokenstring\renderers\TokenRenderer;

/**
 * Class TokenString
 *
 * @package davestewart\tokenstring
 *
 * @property string $source
 * @property string[] $tokens
 * @property string $value
 * @property string $regex
 */
class TokenString
{

	// ------------------------------------------------------------------------------------------------
	// STATIC METHODS

		/**
		 * Static configuration method
		 *
		 * See the TokenStringConfig class for more info
		 *
		 * @return  TokenStringConfig
		 */
		public static function config()
		{
			return TokenStringConfig::instance();
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

		/**
		 * Chainable TokenStringMatcher constructor
		 *
		 * @param   TokenString|string  $source     An existing TokenString instance or a new string
		 * @param   array               $filters
		 * @return  TokenStringMatcher
		 */
		public static function matcher($source = '', array $filters = [])
		{
			return new TokenStringMatcher($source, $filters);
		}


	// ------------------------------------------------------------------------------------------------
	// PROPERTIES

		/**
		 * The regex to match only a {token} and (capture) its name string
		 *
		 * @var string
		 */
		protected $regex;

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
			$this->data     = [];
			$this->tokens   = [];
			$this->regex    = TokenStringConfig::instance()->getToken();

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


	// ------------------------------------------------------------------------------------------------
	// ACCESSORS

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

			// match source
			preg_match_all($this->regex, $source, $matches);

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
		 *  - a single numeric array
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
			// parameters
			$data   = is_string($name)
						? [$name => $value]
						: $name;

			// set associative
			$data   = $this->makeAssociative($data);

			// set data
			foreach($data as $key => $value)
			{
				$this->data[$key] = is_string($value)
									? $value
									: TokenRenderer::make($value);
			}

			// return
			return $this;
		}

		public function clearData()
		{
			$this->data = [];
			return $this;
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
				$data = $data === (array) $data // optimised for small arrays; see: http://stackoverflow.com/questions/3470990/is-micro-optimization-worth-the-time
					? $data
					: func_get_args();

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


	// ------------------------------------------------------------------------------------------------
	// PROTECTED METHODS

		protected function replace($source, array $data)
		{
			/** @var Token $token */
			$token = null;

			foreach($this->tokens as $name => $token)
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
								$value = $filter($value);
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


	// ------------------------------------------------------------------------------------------------
	// UTILITIES

		/**
		 * Checks if passed array is numeric, and if so, converts to associative,
		 * using the current match values. Output array is clipped to the shorter
		 * of the two array lengths
		 *
		 * @param   array   $values
		 * @return  array
		 */
		public function makeAssociative($values)
		{
			// convert if numeric
			if(array_values($values) == $values)
			{
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

			// otherwise, return
			return $values;
		}

		public function __toString()
		{
			return (string) $this->render();
		}

}

TokenString::config();

