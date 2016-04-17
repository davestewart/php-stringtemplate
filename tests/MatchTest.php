<?php

use davestewart\tokenstring\TokenString;
use davestewart\tokenstring\TokenStringMatcher;

class MatchTest extends PHPUnit_Framework_TestCase
{

	// ------------------------------------------------------------------------------------------------
	// basic matching

		public function testValid()
		{
			// data
			$expect = ['date' => '9999-99-99', 'slug' => 'yet-another-article'];
			$source = '/blog/{date}/posts/{slug}/';
			$input  = '/blog/9999-99-99/posts/yet-another-article/';

			// test
			$output = TokenString::matcher($source)->match($input)->matches;

			// test
			$this->assertEquals($expect, $output);
		}

		public function testInvalid()
		{
			// data
			$expect = [];
			$source = '/blog/{date}/posts/{slug}/';
			$input  = '/blog/9999-99-99/media/yet-another-article/';

			// test
			$output = TokenString::matcher($source)->match($input)->matches;

			// test
			$this->assertEquals($expect, $output);
		}

		public function testFilter()
		{
			// data
			$expect = [];
			$source = '/blog/{date}/posts/{slug}/';
			$input  = '/blog/9999-99-99/media/yet-another-article/';
			$filters=
			[
				'date' => '\d{4}-\d{2}-\d{2}',
				'slug' => '[a-z][\w-]+'
			];

			// test
			$output = TokenString::matcher($source, $filters)->match($input)->matches;

			// test
			$this->assertEquals($expect, $output);
		}

		public function testNoTokens()
		{
			// data
			$expect = [];
			$source = '/foo/bar/baz/';
			$input  = '/foo/bar/baz/';

			// test
			$output = TokenString::matcher($source)->match($input)->matches;

			// test
			$this->assertEquals($expect, $output);
		}


	// ------------------------------------------------------------------------------------------------
	// customising source regex

		public function testStartAnchor()
		{
			// data
			$expect = [];
			$source = '/foo/bar/baz/';
			$input  = '/foo/bar/baz/etc/';

			// configure
			TokenString::config()->setSource('^source');

			// test
			$output = TokenString::matcher($source)->match($input)->matches;

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
			TokenString::config()->setSource('source$');

			// test
			$output = TokenString::matcher($source)->match($input)->matches;

			// test
			$this->assertEquals($expect, $output);
		}


}
