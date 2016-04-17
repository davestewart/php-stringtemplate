<?php
/**
 * Created by PhpStorm.
 * User: Dave
 * Date: 16/04/2016
 * Time: 04:19
 */

namespace davestewart\tokenstring;


/**
 * TokenStringMatcher
 *
 * @package davestewart\tokenstring
 *          
 * @property    TokenString     $source
 * @property    string          $regex
 * @property    string[]        $filters
 * @property    string[]        $matches
 */
class TokenStringMatcher
{

	// ------------------------------------------------------------------------------------------------
	// PROPERTIES
	
		/**
		 * @var TokenString
		 */
		protected $source;

		/**
		 * The regex to both match in full, and capture token content from, the source string
		 *
		 * @var string
		 */
		protected $regex;

		/**
		 * A name => regex hash of token content filters
		 *
		 * @var string[]
		 */
		protected $filters;

		/**
		 * A name => match hash of matches found since calling match()
		 *
		 * @var String[]
		 */
		protected $matches;


	// ------------------------------------------------------------------------------------------------
	// INSTANTIATION

		public function __construct($source, array $filters = [])
		{
			$this->setSource($source);
			$this->setFilter($filters);
		}

	
	// ------------------------------------------------------------------------------------------------
	// ACCESSORS

		public function __get($name)
		{
			if(property_exists($this, $name))
			{
				return $this->$name;
			}
			throw new \Exception("Unknown property '$name'");
		}
	
		/**
		 * Returns a regex that matches the source string and replacement pattern filters
		 *
		 * @param   string  $format     An optional regex format to surround the source-matching pattern.
		 *                              See the TokenStringConfig class for more info
		 * @return  string
		 */
		public function getRegex($format = null)
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
			$format         = $format ?: TokenStringConfig::instance()->getSource();
			$delimiter      = substr($format, 0, 1);
			$source         = $this->source->source;
			$tokens         = $this->source->tokens;
			$placeholders   = [];
			$filters        = [];
	
			// phase 1: build arrays
			foreach ($tokens as $name => $token)
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

		/**
		 * Set the source 
		 * @param   string|TokenString  $source
		 * @return  $this
		 */
		public function setSource($source)
		{
			// set properties
			$this->regex    = '';
			$this->source   = $source instanceof TokenString
								? $source
								: TokenString::make($source);
			
			// return
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
		 * @param   array|null      $value      The regex pattern to match potential token content
		 * @return  $this
		 */
		public function setFilter($name, $value = null)
		{
			// parameters
			$data   = is_string($name)
						? [$name => $value]
						: $name;
	
			// set associative
			$data   = $this->source->makeAssociative($data);
	
			// set data
			foreach($data as $key => $value)
			{
				$this->filters[$key] = $value;
			}
	
			// return
			return $this;
		}

		/**
		 * Clear the filters array
		 *
		 * @return $this
		 */
		public function clearFilters()
		{
			$this->filters = [];
			return $this;
		}

	
	// ------------------------------------------------------------------------------------------------
	// PUBLIC METHODS
	
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
		 * @return  $this
		 */
		public function match($input, $format = null)
		{
			// get regex
			if($this->regex == null)
			{
				$this->regex = $this->getRegex($format);
			}

			// match
			preg_match($this->regex, $input, $matches);

			// convert matches to named capture array
			$this->matches = [];
			if(count($matches))
			{
				array_shift($matches);
				$this->matches = array_combine(array_keys($this->source->tokens), $matches);
			}
			
			// return
			return $this;
		}


}