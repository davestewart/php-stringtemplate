<?php namespace davestewart\tokenstring\renderers;

use davestewart\tokenstring\Token;

class ObjectRenderer extends TokenRenderer
{

	/**
	 * @var object
	 */
	protected $value;
	
	public function render(Token $token, array $data, $source)
	{
		// grab object
		$value = $this->value;
		
		// loop over properties and return next object
		if($token->props)
		{
			foreach ($token->props as $prop)
			{
				if(is_object($value))
				{
					$value = $value->$prop;
				}
				else
				{
					return $token->match;
				}
			}
		}
		else
		{
			return $token->match;
		}

		// when we finally have a value, filter it
		return $value;
	}
	
}
