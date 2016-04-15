# TokenString

String templating class with advanced token replacement functionality


## Overview

TokenString allows you to build up complex strings from manageble building blocks, resolving placeholder values from strings, callbacks, even nested TokenStrings.

TokenStrings can be fully or only partially "resolved" into hardcoded strings, allowing you to change string content on the fly by simply setting new data.

It was designed to make building complex filesystem paths flexible and less error-prone, by starting with a kit of safe template parts, and assembling on the fly.

## Usage examples

It can build simple strings, for example:


```php
echo TokenString::make('I like {foo} !')
    ->setData('foo', 'FOO')
    ->value;

// I like FOO !
```

You can use callbacks and closures:

```php
$user = User::find(1);
echo TokenString::make('I like {name} !')
    ->setData('name', function() use ($user){ return $user->name; })
    ->value;

// I like Mr Foo !
```

Even nested TokenStrings, which in turn call closures:

```php
$source = 'I {feeling} {thing} !';
$user = User::find(1);
$data =
[
    'feeling'       => 'like',
    'thing'         => new TokenString('{action} {user}'),
    'action'        => 'talking to',
    'user'          => function() use ($user){ return $user->name; },
];
echo TokenString::make($source)->setData($data)->value;

// I like talking to Mr Foo !
```

## Usage

### Instantiation

Create a new `TokenString` using either the `new` operator, or the static `::make()` method:

```php
$string = new TokenString('I like {thing}');
$string = TokenString::make('I like {thing}');
```

### Setting data

Re-set source data using the `setSource()` method:

```php
$string->setSource('Some new {source} value');
```

Set token data using the `setData()` method, passing a `name` and `value`, or an associative array:

```php
TokenString::make('I like {thing}')->setData('thing', 'apples');
TokenString::make('I like {thing}')->setData(['thing' => 'apples']);
```

When passing an associative array, pass `true` as the second argument to append / merge the new data with any existing data.

Note that you can also pass numeric arrays, which will match existing tokens in match order:

```php
TokenString::make('I like {this} and {that}')->setData(['apples', 'oranges']);

```

### Rendering

You can render a TokenString in one of 3 ways:

- by calling the `render()` method
- by outputting its `->value`
- by `echo`ing or otherwise explicitly converting to string

```php
$value = $string->render();
$value = $string->value
echo $value
```


### Methods

TokenString has 4 main methods:

### `render()`

As expected, renders the source string using the current data:

```php
echo TokenString::make('I like {thing}')
    ->setData('thing', 'apples')
    ->render();

// I like apples
```

You can also pass in data to temporarily overwrite the current values:

```php
$string = TokenString::make('I like {thing}')
    ->setData('thing', 'apples');

echo $string->render(['thing' => 'oranges']);
// I like oranges

echo $string->render();
// I like apples
```

This is useful when you want to set up lots of data up front, but only sometimes overwrite data.

As before, you can also pass a numeric array which will match available tokens as they are found in the `source` string, **or** as a convenience, you can pass multiple parameters (this functionality is currently unique to `render()`):

```php
echo TokenString::make('I like {this} and {that}')->render(['apples', 'oranges']);
// I like apples and oranges

echo TokenString::make('I like {this} and {that}')->render('apples', 'oranges');
// I like apples and oranges
```

### `resolve()`

Resolve is a useful optimisation method; it allows you to expand source tokens where there is *existing* data, then continue to use the instance for further expansion:

```php
$string = TokenString::make('{name} likes {thing}')->setData('name', 'Ben');
echo $string->source;
// {name} likes {thing}

$string->resolve(true);
echo $string->source;
// Ben likes {thing}

echo $string->render('apples');
// Ben likes apples

echo $string->render('oranges');
// Ben likes oranges
```

### `chain()`

Chain is a convenience method to both render the existing tokens AND update the source string. It's mainly used when replacements themselves return tokens:

```php
// data
$source = 'I like {thing}';
$data =
[
    'thing'  => 'to eat {fruit}',
    'fruit'  => '{apples}',
    'apples' => 'red apples',
];

// with chain
echo TokenString::make($source)
    ->setData($data)
    ->chain()
    ->chain()
    ->chain();
    
// I like to eat red apples

// without chain
$string = TokenString::make($source);
echo $string
    ->setData($data)
    ->setSource($string->render())
    ->setSource($string->render())
    ->setSource($string->render());

// I like to eat red apples

```

### `match()`

Match is used to compare another string against the current `TokenString` instance to see if it would match according to the current source pattern.

This is particularly useful in path matching, for example comparing upload paths in a watch folder or such like.

Not only does the method tell you if you have a match, but it will capture the matching portions of the string and return them as **named** matches. These can then be used in the rest of your code, for example to locate the root folder, save something to a database, or whatever.

By default, tokens will will match any content:

```php
// data
$source = '/blog/{date}/posts/{slug}/';
$inputs =
[
    'good'      => '/blog/9999-99-99/posts/yet-another-article/',
    'bad'       => '/blog/9999-99-99/media/yet-another-article/',
];

// test
$string = TokenString::make($source);
foreach($inputs as $name => $input)
{
    $result = $string->match($input);
    print_r([$name => $input, 'regex' => $string->sourceRegex, 'result' => $result]);
}
```

You can see here that the first pattern matches because it is on the `/posts/` path, but the second does **not** because it's on the `/media/` path:

```
Array
(
    [good] => /blog/9999-99-99/posts/yet-another-article/
    [regex] => `^/blog/(.*)/posts/(.*)/$`i
    [result] => Array
        (
            [date] => 9999-99-99
            [slug] => yet-another-article
        )

)
Array
(
    [bad] => /blog/9999-99-99/media/yet-another-article/
    [regex] => `^/blog/(.*)/posts/(.*)/$`i
    [result] =>
)
```

You can get even more granular by assigning **filters** for tokens, using the `setFilter()` method.

The following (slightly lengthy) example expands on the above code by allowing only dates and slugs:

```php
// data
$source = '/blog/{date}/posts/{slug}/';
$filters =
[
    'date'      => '\d{4}-\d{2}-\d{2}',
    'slug'      => '[-a-z]+',
];

// match data
$inputs =
[
    'good'      => '/blog/9999-99-99/posts/yet-another-article/',
    'bad1'      => '/blog/2000-01-01/posts/not a slug/',
    'bad2'      => '/blog/XXXXXXXXXX/posts/some-article/',
    'bad3'      => '/blah/2000-01-01/posts/',
];

// string
$string = TokenString::make($source)->setFilter($filters);

// test
foreach($inputs as $name => $input)
{
    $result = $string->match($input);
    print_r([$name => $input, 'regex' => $string->sourceRegex, 'result' => $result]);
}

```

Note how the "bad" inputs all fail for various reasons:

```
Array
(
    [good] => /blog/9999-99-99/posts/yet-another-article/
    [regex] => `^/blog/(\d{4}-\d{2}-\d{2})/posts/([-a-z]+)/$`i
    [result] => Array
        (
            [date] => 9999-99-99
            [slug] => yet-another-article
        )

)
Array
(
    [bad1] => /blog/2000-01-01/posts/not a slug/
    [regex] => `^/blog/(\d{4}-\d{2}-\d{2})/posts/([-a-z]+)/$`i
    [result] =>
)
Array
(
    [bad2] => /blog/XXXXXXXXXX/posts/some-article/
    [regex] => `^/blog/(\d{4}-\d{2}-\d{2})/posts/([-a-z]+)/$`i
    [result] =>
)
Array
(
    [bad3] => /blah/2000-01-01/posts/
    [regex] => `^/blog/(\d{4}-\d{2}-\d{2})/posts/([-a-z]+)/$`i
    [result] =>
)
```

Match is a very powerful method, if you have a use-case for it.


## Options

The following methods are available to set data:

- `setSource()`
- `setData()`
- `setFilter()`

All have been covered above.

## How it works

Dumping out any `StringToken` instance reveals its internals (this example is from the [Usage examples](#Usage-examples) section):

```php
TokenString {#341 ▼
  #source: "I {feeling} {thing} !"
  #data: array:4 [▼
    "feeling" => "like"
    "thing" => TokenString {#342 ▶}
    "action" => "talking to"
    "user" => Closure {#334 ▶}
  ]
  #matches: array:2 [▼
    "feeling" => "{feeling}"
    "thing" => "{thing}"
  ]
  #filters: []
  #tokenRegex: "!{([\.\w]+)}!"
  #sourceRegex: ""
}
```

Note the `matches` and `data` arrays, with the closure and nested `TokenString` instance clearly visible in the data array.

The `filters` and `sourceRegex` properties will be populated when `match()` has been called.


## Advanced usage examples

### Building complex file paths

```php
// data
$clients =
[
    'acme',
    'the firm',
    'future corp',
];
$dates =
[
    '2016-04-01',
    '2016-04-02',
    '2016-04-03',
];
$folders =
[
    'documents',
    'files/downloads',
    'images',
];

// alternative folder structures
$source = '/Volumes/projects/{client}/{folder}/{date}/';
//$source = '/Volumes/projects/{folder}/{date}/{client}/';
//$source = '/Volumes/projects/{date}/{folder}/{client}/';

// variables
$root   = TokenString::make($source);
$paths  = [];

// build
foreach($clients as $client)
{
    foreach($dates as $date)
    {
        foreach($folders as $folder)
        {
            $path = $root->render([
                'client'    => $client,
                'date'      => $date,
                'folder'    => $folder,
            ]);
            $paths[] = $path;
        }
    }
}

// output
sort($paths);
print_r($paths);
```

Results, depending on chosen `$source`:

```
Array
(
    [0]  => /Volumes/projects/acme/documents/2016-04-01/
    [1]  => /Volumes/projects/acme/documents/2016-04-02/
    [2]  => /Volumes/projects/acme/documents/2016-04-03/
    [3]  => /Volumes/projects/acme/files/downloads/2016-04-01/
    [4]  => /Volumes/projects/acme/files/downloads/2016-04-02/
    [5]  => /Volumes/projects/acme/files/downloads/2016-04-03/
    [6]  => /Volumes/projects/acme/images/2016-04-01/
    [7]  => /Volumes/projects/acme/images/2016-04-02/
    [8]  => /Volumes/projects/acme/images/2016-04-03/
    [9]  => /Volumes/projects/future corp/documents/2016-04-01/
    [10] => /Volumes/projects/future corp/documents/2016-04-02/
    [11] => /Volumes/projects/future corp/documents/2016-04-03/
    [12] => /Volumes/projects/future corp/files/downloads/2016-04-01/
    [13] => /Volumes/projects/future corp/files/downloads/2016-04-02/
    [14] => /Volumes/projects/future corp/files/downloads/2016-04-03/
    [15] => /Volumes/projects/future corp/images/2016-04-01/
    [16] => /Volumes/projects/future corp/images/2016-04-02/
    [17] => /Volumes/projects/future corp/images/2016-04-03/
    [18] => /Volumes/projects/the firm/documents/2016-04-01/
    [19] => /Volumes/projects/the firm/documents/2016-04-02/
    [20] => /Volumes/projects/the firm/documents/2016-04-03/
    [21] => /Volumes/projects/the firm/files/downloads/2016-04-01/
    [22] => /Volumes/projects/the firm/files/downloads/2016-04-02/
    [23] => /Volumes/projects/the firm/files/downloads/2016-04-03/
    [24] => /Volumes/projects/the firm/images/2016-04-01/
    [25] => /Volumes/projects/the firm/images/2016-04-02/
    [26] => /Volumes/projects/the firm/images/2016-04-03/
)
```