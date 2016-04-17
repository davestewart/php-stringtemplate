<?php namespace davestewart\tokenstring\renderers;

use davestewart\tokenstring\Token;
use davestewart\tokenstring\TokenString;

class TokenStringRenderer extends TokenRenderer
{
	/**
	 * @var TokenString
	 */
	protected $value;
	
	public function render(Token $token, array $data, $source)
	{
		return $this->value->render($data);
	}
	
}