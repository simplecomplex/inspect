## (PHP+JS) Inspect: variable dumps and stack traces ##

- [Description](#Description)
- [License](#MIT-licensed)
- [Requirements](#Requirements)

### Get Inspect ###
```PHP
// If dependency injection container exists.
$inspect = $container->get('inspect');
// Otherwise use maker.
$inspect = \SimpleComplex\Inspect\Inspect::getInstance();
```

### Principal methods and options ###

#### (array) $options ####

- (int) `depth`: max object/array recursion; DEPTH_DEFAULT/TRACE_DEPTH_DEFAULT
- (int) `limit`: max trace frame; TRACE_LIMIT_DEFAULT
- (int) `code`: error code, overrides exception code; none
- (int) `truncate`: string truncation; TRUNCATE_DEFAULT
- (arr) `skip_keys`: skip those object/array keys; none
- (arr) `needles`: replace in strings; NEEDLES
- (arr) `replacers`: replace in strings; REPLACERS
- (bool) `escape_html`: replace in strings; ESCAPE_HTML
- (int) `output_max`: replace in strings; OUTPUT_DEFAULT
- (int) `exectime_percent`: replace in strings; EXEC_TIMEOUT_DEFAULT
- (bool) `rootdir_replace`: replace root dir in strings; ROOT_DIR_REPLACE
- (int) `wrappers`: number of wrapping functions/methods, to be hidden; zero
- (str) `kind`: (auto) 'trace' when subject is \Throwable, otherwise 'variable'

#### inspect($subject, $options = []) : Inspector ####

Do variable inspection, unless arg $subject is a throwable; then trace.
```PHP
$var = 'what is this?'; $xcptn = new \Exception('Doh');

// Inspect:
$inspect->inspect($var);
// Trace:
$inspect->inspect($xcptn);
```

#### variable($subject, $options = []) : Inspector ####

Force variable inspection, even if subject is a throwable.
```PHP
$inspect->variable($var);
```

#### trace($throwableOrNull, $options = []) : Inspector ####

Trace exception or do back-trace.
```PHP
// Trace exception:
$inspect->trace($xcptn);
// Do back-trace:
$inspect->trace(null);
```

#### Logging ####

The Inspector returned by inspect|variable|trace() is stringable. Pass it directly to a PSR logger:
```PHP
$logger->debug('Darned ding' . "\n" . $inspect->variable($var));
```

Or use the Inspector's own PSR logger-like method:

**log($level = 'debug', $message = '', array $context = []) : void**

```PHP
$inspect->variable($var)->log('debug', 'darned ding');
```

### Javascript ###

(object) options

- (string) `message`: content headline and options as string also interprets to message (except when 'protos'/'func_body')
- (integer) `depth`: array|object recursion max (default 10, max 10)
- (boolean) `protos`: analyze prototypal properties too
- (boolean) `func_body`: print bodies of functions
- (string) `type`: default 'inspect'/'inspect trace'
- (string) `severity`: default 'debug'/'error'

To console:  
```javascript
window.inspect(u, options);
window.inspect.trace(er, options);
```

<!--
To server log:  
`inspect.log(u, options);`  
`inspect.traceLog(er, options);`
-->

### Description ###

Produces tidy and informative variable dumps and exception/back traces.  
Tailormade and ready for logging - the inspect/variable/trace() methods return a stringable object.

#### Safe ####

The inspector and tracer guarantee not to fail.
A simple PHP:var_dump() is prone to raise a PHP error if you dump a large or complex array like $GLOBALS, due to references (recursion).  
Inspect limits it's recursion into sub arrays/objects. It also keeps track of how large an output it produces. And it finally makes sure that max execution time doesn't get exceeded.

#### Secure ####

Inspect hides the values of array/object buckets named 'pw', 'pass' and 'password'.  
And values of other sensitives can be hidden using 'skip_keys' option.

#### PHP and Javascript ####

Inspect consists of a PHP library for serverside inspection and tracing, and a Javascript library for clientside ditto.  
NB: The Javascript library has not been maintained since 2015.

#### Maturity ####

The library has existed in various forms since 2010.
The core has been refined continuously whereas the wrapping has evolved from a rather oldschool OOP pattern over a solid but non-orthodox Drupal style, to well-behaved PSR/Composer patterns. 

#### Used in - *extended by* - Drupal ####

The backbone of the [Drupal Inspect module](https://drupal.org/project/inspect) is SimpleComplex Inspect.
The Drupal module (D7 as well as D8) extends Inspect to accomodate to the context - that is: uses Drupal's APIs and features when it makes sense.  
Thus the Drupal module is an example of specializing contextually, by overriding attributes, methods and defaults.

### MIT licensed ###

[License and copyright](https://github.com/simplecomplex/inspect/blob/master/LICENSE).
[Explained](https://tldrlegal.com/license/mit-license).

### Requirements ###

- PHP >=7.2
