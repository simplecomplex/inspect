## Inspect creates variable dumps and detailed stack traces ##

### During development and debugging ###

Log a variable inspection whenever you wonder about a variable's type, structure or content.

And trace exceptions when things just don't work like they are supposed to.

### In production ###

Catch an exception and log a tidy and concise backtrace, including a comprehensible and contextually relevant message.

Lots of errors are foreseeable, and catching and logging 'em deliberately and within context (possibly re-throwing afterwards) makes for a far more debuggable and maintainable system.
*Spells saved time and happy users*.

### Safe ###

The inspector and tracer guarantee not to fail.
A simple PHP:var_dump() is prone to raise a PHP error if you dump a large or complex array like $GLOBALS, due to references (recursion).

Inspect limits it's recursion into sub arrays/objects. It also keeps track of how large an output it produces (versus database errors). And it finally makes sure that max execution time doesn't get exceeded.

### Secure ###

Inspect hides the values of array/object buckets named 'pass' and 'password', and it also makes an effort of hiding file paths (always when getting, configurable for logging).

### Doesn't expose secrets to ordinary users ###

##### Inspect defines four output targets, and corresponding permissions: #####

- get: get the inspection/trace as string, and `echo` it to screen or console
- log: to PHP error log, or via a PSR-3 logger, or extend the Inspect class and define your own logging regime
- file
- frontend log: log from Javascript to server; AJAX to PHP Inspect, log... see above

Out-of-the-box, Inspect only allows logging and filing (though in CLI mode getting is allowed too).

### PHP and Javascript ###

Inspect consists of a PHP library for serverside inspection and tracing, and a Javascript library for clientside ditto.
And clientside/Javascript inspect can even log to backend (PHP), if permitted.

### Used in - *extended by* - Drupal ###

The backbone of the Drupal Inspect module is SimpleComplex Inspect.
The Drupal module (D7 as well as D8) extends Inspect to accomodate to the context - that is: uses Drupal's APIs and features when it makes sense.

Thus the Drupal module is an example of specializing contextually, by overriding attributes, methods and defaults.