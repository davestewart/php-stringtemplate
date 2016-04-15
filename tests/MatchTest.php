<?php

use davestewart\tokenstring\TokenString;

class MatchTest extends PHPUnit_Framework_TestCase
{

	public function testValid()
	{
		// data
		$expect = ['date' => '9999-99-99', 'slug' => 'yet-another-article'];
		$source = '/blog/{date}/posts/{slug}/';
		$input  = '/blog/9999-99-99/posts/yet-another-article/';

		// test
		$string = TokenString::make($source);
		$output = $string->match($input);

		// test
		$this->assertEquals($expect, $output);
	}

	public function testInvalid()
	{
		// data
		$source = '/blog/{date}/posts/{slug}/';
		$input  = '/blog/9999-99-99/media/yet-another-article/';

		// test
		$string = TokenString::make($source);
		$output = $string->match($input);

		// test
		$this->assertNull($output);
	}

	public function testNoTokens()
	{
		// data
		$expect = [];
		$source = '/foo/bar/baz/';
		$input  = '/foo/bar/baz/';

		// test
		$string = TokenString::make($source);
		$output = $string->match($input);

		// test
		$this->assertEquals($expect, $output);
	}

	public function testStartAnchor()
	{
		// data
		$expect = [];
		$source = '/foo/bar/baz/';
		$input  = '/foo/bar/baz/etc/';

		// configure
		$config = TokenString::configure('source', '^source');

		// test
		$string = TokenString::make($source);
		$output = $string->match($input);

		// test
		$this->assertEquals($expect, $output);
	}

	public function testEndAnchor()
	{
		// data
		$expect = [];
		$source = '/foo/bar/baz/';
		$input  = '/etc/foo/bar/baz/';

		// configure
		$config = TokenString::configure('source', 'source$');

		// test
		$string = TokenString::make($source);
		$output = $string->match($input);

		// test
		$this->assertEquals($expect, $output);
	}


}
