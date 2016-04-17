<?php namespace davestewart\tokenstring\renderers;

use Closure;
use davestewart\tokenstring\Token;
use davestewart\tokenstring\TokenString;

class TokenRenderer
{

	// ------------------------------------------------------------------------------------------------
	// PROPERTIES

		/**
		 * @var string
		 */
		protected $value;


	// ------------------------------------------------------------------------------------------------
	// INSTANTIATION

		/**
		 * TokenRenderer constructor
		 *
		 * @param mixed $value
		 */
		public function __construct($value)
		{
			$this->value = $value;
		}

		/**
		 * TokenRenderer factory method
		 *
		 * @param   string|object|Closure|TokenString|TokenRenderer $value
		 * @return  FunctionRenderer|ObjectRenderer|TokenStringRenderer|string
		 */
		public static function make($value)
		{
			if($value instanceof self)
			{
				return $value;
			}

			if($value instanceof TokenString)
			{
				return new TokenStringRenderer($value);
			}

			if ($value instanceof \Closure )
			{
				return new FunctionRenderer($value);
			}

			if (is_object($value) )
			{
				return new ObjectRenderer($value);
			}

			return $value;
		}


	// ------------------------------------------------------------------------------------------------
	// METHODS

		/**
		 * Render the
		 *
		 * @param   Token  $token
		 * @param   array  $data
		 * @param   string $source
		 * @return  string
		 */
		public function render(Token $token, array $data, $source)
		{
			return $this->value;
		}


}

