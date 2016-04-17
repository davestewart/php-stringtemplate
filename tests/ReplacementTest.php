<?php

use davestewart\tokenstring\TokenString;

class ReplacementTest extends PHPUnit_Framework_TestCase
{

	// ------------------------------------------------------------------------------------------------
	// BASICS

		public function testBasicString()
		{
			$expect = 'foo';
			$source = '{string}';
			$data   =
			[
				'string' => 'foo',
			];

			$output = TokenString::make($source, $data)->value;

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

			$output = TokenString::make($source, $data)->value;

			$this->assertEquals($expect, $output);
		}


	// ------------------------------------------------------------------------------------------------
	// ARRAYS

		public function testMakeArrayMethod()
		{
			$expect = ['foo' => 'foo'];
			$source = '{foo}';
			$data   = ['foo'];

			$result = TokenString::make($source)->makeAssociative($data);

			$this->assertEquals($expect, $result);
		}

		public function testMakeArray()
		{
			$expect = 'foo';
			$source = '{string}';
			$data   = [ 'foo' ];

			$string = TokenString::make($source, $data);
			$output = $string->value;

			$this->assertEquals($expect, $output);
		}

		public function testConstructorArray()
		{
			$expect = 'foo';
			$source = '{string}';
			$data   = [ 'foo' ];

			$string = new TokenString($source, $data);
			$output = $string->value;

			$this->assertEquals($expect, $output);
		}

		public function testRenderNoParameters()
		{
			$expect = 'foo {bar}';
			$source = '{foo} {bar}';
			$data   = [ 'foo' ];

			$string = TokenString::make($source, $data);
			$output = $string->render();

			$this->assertEquals($expect, $output);
		}

		public function testRenderAcceptsArray()
		{
			$expect = 'foo bar';
			$source = '{foo} {bar}';
			$data   = [ 'foo', 'bar' ];

			$string = TokenString::make($source);
			$output = $string->render($data);

			$this->assertEquals($expect, $output);
		}

		public function testRenderAcceptsVariableParameters()
		{
			$expect = 'foo bar';
			$source = '{foo} {bar}';

			$string = TokenString::make($source);
			$output = $string->render('foo', 'bar');

			$this->assertEquals($expect, $output);
		}


	// ------------------------------------------------------------------------------------------------
	// RENDER TYPES

		public function testFunction()
		{
			$expect = 'foo';
			$source = '{function}';
			$data   =
			[
				'function' => function($name){ return 'foo'; },
			];

			$output = TokenString::make($source, $data)->value;

			$this->assertEquals($expect, $output);
		}

		public function testClosureWithUse()
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

			$output = TokenString::make($source, $data)->value;

			$this->assertEquals($expect, $output);
		}

		public function testFunctionParamsMatch()
		{
			$expect = 'a:3:{i:0;s:8:"function";i:1;O:29:"davestewart\tokenstring\Token":6:{s:5:"match";s:10:"{function}";s:8:"selector";s:8:"function";s:4:"name";s:8:"function";s:4:"path";N;s:5:"props";N;s:7:"filters";N;}i:2;s:10:"{function}";}';
			$source = '{function}';
			$data   =
			[
				'function' => function($name, $match, $source)
				{
					return serialize(func_get_args());
				},
			];

			$output = TokenString::make($source, $data)->value;

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

			$output = TokenString::make($source, $data)->value;

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

			$output = TokenString::make($source, $data)->value;

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

			$output = TokenString::make($source, $data)->value;

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

			$output = TokenString::make($source, $data)->value;

			$this->assertEquals($expect, $output);
		}


	// ------------------------------------------------------------------------------------------------
	// PROCESS

		public function testResolveAndNoProcess()
		{
			$expect = 'foo bar {baz}';
			$source = '{foo} {bar} {baz}';
			$data   =
			[
				'foo' => 'foo',
				'bar' => 'bar',
			];

			$output = TokenString::make($source, $data)
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

			$output = TokenString::make($source, $data)
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
			$input  = TokenString::make($source, $data)->resolve();

			// generate final output
			$output = [];
			foreach($nums as $num)
			{
				$output[] = $input->render(['baz' => $num]);
			}

			$this->assertEquals($expect, $output);
		}
	
}
