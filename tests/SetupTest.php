<?php

use davestewart\tokenstring\TokenString;

class SetupTest extends PHPUnit_Framework_TestCase
{

	// ------------------------------------------------------------------------------------------------
	// INSTANCES

		public function testGetTokenString()
		{
			$expect = '\davestewart\tokenstring\TokenString';
			$result = TokenString::make('');

			$this->assertInstanceOf($expect, $result);
		}

		public function testGetConfig()
		{
			$expect = '\davestewart\tokenstring\TokenStringConfig';
			$result = TokenString::config();

			$this->assertInstanceOf($expect, $result);
		}

		public function testGetMatcher()
		{
			$expect = '\davestewart\tokenstring\TokenStringMatcher';
			$result = TokenString::matcher();

			$this->assertInstanceOf($expect, $result);
		}


	// ------------------------------------------------------------------------------------------------
	// CONFIG

		public function testConfigSource()
		{
			$expect = '!^source$!i';
			$result = TokenString::config()
						->setDelimiter('!')
						->setSource('^source$', 'i')
						->getSource();

			$this->assertEquals($expect, $result);
		}

		public function testConfigToken()
		{
			$expect = '!`(test)`!i';
			$result = TokenString::config()
						->setDelimiter('!')
						->setToken('`(test)`', 'i')
						->getToken();

			TokenString::config()->reset();

			$this->assertEquals($expect, $result);
		}

}
