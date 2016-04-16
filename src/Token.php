<?php namespace davestewart\tokenstring;

/**
 * Class Token
 *
 * Generic value object to calculate and store token data
 *
 * @package davestewart\tokenstring
 */
class Token
{

	// ------------------------------------------------------------------------------------------------
	// PROPERTIES

		/** @var string */
		public $match;

		/** @var string */
		public $selector;

		/** @var string */
		public $name;

		/** @var string */
		public $path;

		/** @var string[]|null */
		public $props;

		/** @var string[]|null */
		public $filters;


	// ------------------------------------------------------------------------------------------------
	// INSTANTIATION

		public function __construct($selector, $match)
		{
			// paramaters
			$this->selector     = $selector;
			$this->match        = $match;

			// test for filters
			if(strstr($selector, '|') !== false)
			{
				$filters        = explode('|', $selector);
				$selector       = array_shift($filters);
				$this->filters  = $filters;
			}

			// test for object
			if(strstr($selector, '.') !== false)
			{
				$this->path     = $selector;
				$props          = explode('.', $selector);
				$selector       = array_shift($props);
				$this->props    = $props;
			}

			// assign properties
			$this->name         = $selector;
		}
	

}

