<?php

use davestewart\tokenstring\TokenString;

class ReplacementTests extends PHPUnit_Framework_TestCase
{

	public function testBasicString()
	{
		$expect = 'foo';
		$source = '{string}';
		$data   =
		[
			'string' => 'foo',
		];

		$output = TokenString::make($source)->setData($data)->value;

		$this->assertEquals($expect, $output);
	}

	public function testMultiString()
	{
		$expect = 'foo bar baz';
		$source = '{foo} {bar} {baz}';
		$data   =
		[
			'foo' => 'foo',
			'bar' => 'bar',
			'baz' => 'baz',
		];

		$output = TokenString::make($source)->setData($data)->value;

		$this->assertEquals($expect, $output);
	}

	public function testFunction()
	{
		$expect = 'foo';
		$source = '{function}';
		$data   =
		[
			'function' => function($name){ return 'foo'; },
		];

		$output = TokenString::make($source)->setData($data)->value;

		$this->assertEquals($expect, $output);
	}

	public function testClosure()
	{
		$expect = 'foo';
		$source = '{closure}';
		$object = (object) [
			'foo' => 'foo',
			'bar' => 'bar',
			'baz' => 'baz',
		];
		$data   =
		[
			'closure' => function($name) use ($object){ return $object->foo; },
		];

		$output = TokenString::make($source)->setData($data)->value;

		$this->assertEquals($expect, $output);
	}

	public function testTokenString()
	{
		$expect = 'foo bar baz';
		$source = '{tokenstring}';
		$data   =
		[
			'tokenstring' => new TokenString('{foo} {bar} {baz}'),
			'foo' => 'foo',
			'bar' => 'bar',
			'baz' => 'baz',
		];

		$output = TokenString::make($source)->setData($data)->value;

		$this->assertEquals($expect, $output);
	}

	public function testDoubleTokenString()
	{
		$expect = 'foo bar';
		$source = '{tokenstring1}';
		$data   =
		[
			'tokenstring1' => new TokenString('{foo} {tokenstring2}'),
			'tokenstring2' => new TokenString('{bar}'),
			'foo' => 'foo',
			'bar' => 'bar',
			'baz' => 'baz',
		];

		$output = TokenString::make($source)->setData($data)->value;

		$this->assertEquals($expect, $output);
	}

	public function testTripleTokenString()
	{
		$expect = 'foo bar baz';
		$source = '{tokenstring1}';
		$data   =
		[
			'tokenstring1' => new TokenString('{foo} {tokenstring2}'),
			'tokenstring2' => new TokenString('{bar} {tokenstring3}'),
			'tokenstring3' => new TokenString('{baz}'),
			'foo' => 'foo',
			'bar' => 'bar',
			'baz' => 'baz',
		];

		$output = TokenString::make($source)->setData($data)->value;

		$this->assertEquals($expect, $output);
	}

	public function testTokenStringFunction()
	{
		$expect = 'foo bar baz';
		$source = '{tokenstring1}';
		$data   =
		[
			'tokenstring1' => new TokenString('{foo} {bar} {function}'),
			'function' => function(){ return 'baz'; },
			'foo' => 'foo',
			'bar' => 'bar',
			'baz' => 'baz',
		];

		$output = TokenString::make($source)->setData($data)->value;

		$this->assertEquals($expect, $output);
	}

	public function testResolveAndNoProcess()
	{
		$expect = 'foo bar {baz}';
		$source = '{foo} {bar} {baz}';
		$data   =
		[
			'foo' => 'foo',
			'bar' => 'bar',
		];

		$output = TokenString::make($source)
					->setData($data)
					->resolve()
					->value;

		$this->assertEquals($expect, $output);
	}

	public function testResolveAndProcess()
	{
		$expect = 'foo bar baz';
		$source = '{foo} {bar} {baz}';
		$data   =
		[
			'foo' => 'foo',
			'bar' => 'bar',
		];

		$output = TokenString::make($source)
					->setData($data)
					->resolve()
					->setData('baz', 'baz')
					->value;

		$this->assertEquals($expect, $output);
	}

	public function testResolveAndProcessLoop()
	{
		// output
		$expect =
		[
			'foo bar 1',
			'foo bar 2',
			'foo bar 3'
		];

		// input
		$source = '{foo} {bar} {baz}';

		// data
		$nums   = [1, 2, 3];
		$data   =
		[
			'foo'   => 'foo',
			'bar'   => 'bar',
		];

		// resolve string
		$input  = TokenString::make($source)
					->setData($data)
					->resolve();

		// generate final output
		$output = [];
		foreach($nums as $num)
		{
			$output[] = $input->render(['baz' => $num]);
		}

		$this->assertEquals($expect, $output);
	}
	
}
