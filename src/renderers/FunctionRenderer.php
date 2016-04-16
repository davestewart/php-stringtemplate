<?php namespace davestewart\tokenstring\renderers;

use davestewart\tokenstring\Token;

class FunctionRenderer extends TokenRenderer
{

	/**
	 * @var \Closure
	 */
	protected $value;

	public function render(Token $token, array $data, $source)
	{
		return call_user_func($this->value, $token->name, $token, $source);
	}


}