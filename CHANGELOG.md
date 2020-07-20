# Changelog

All notable changes to **simplecomplex/inspect** will be documented in this file,
using the [Keep a CHANGELOG](https://keepachangelog.com/) principles.


## [Unreleased]

### Added
* Separate depth config var for tracing; trace_depth.

### Changed
* Integer as $options argument for inspect()|trace() is now always interpreted
  as 'depth'; no longer interpreted as 'limit' when tracing.
* Minimum depth when tracing is now zero; i.e. don't inspect method arguments.

### Fixed
* Options interpretation algo disentangled; was probably flawed.


## [3.0]

### Added
* Config helper class to ease accommodation in frameworks.
* Inspect configure() method.
* New option rootdir_replace.
* Inspector toArray() method.
* Inspector log() method; thus reintroducing direct (chainable) logging
(see v. 2.0).
* Inspector use magic method __debugInfo() when it exists.

### Changed
* All package dependencies eliminated.
* Inspect constructor no longer explicit, to ease framework accommodation.
* Class InspectToLog abandoned, due to no globally supported means of getting
and using dependency injection containers; constructor throws exception.
* Root dir replacement no longer targeted specifically at document root;
may be whatever directory considered 'root' in context.
* Deprecated Inspector get() method; use toArray().
* Changelog in standard keepachangelog format; previous was idiosyncratic.

### Fixed
* Unicode helper doesn't need to now about the intl extension, never used.
* PHP 7.4 deprecation: The array and string offset access syntax using curly
braces is deprecated.


## [2.3] - 2019-01-04

### Fixed
* Bad and stupid assignment-in-condition bugs in exceeds time/length checkers.


## [2.2.5] - 2018-08-11

### Added
* Tracer use option (int) 'wrappers'.

### Fixed
* Escape digit one (SOH, Start of Heading), for binary string.


## [2.2.2] - 2018-05-01

### Changed
* Deny (Apache) HTTP access to the whole package.


## [2.2.1] - 2018-04-07

### Fixed
* Beware of class_exists+autoload; lethal if rubbish spl_autoload.


## [2.2] - 2018-04-07

### Added
* Convenience class InspectToLog.

### Changed
* Frontend JS inspect new methods variable, variableGet, traceGet.
* Frontend file-line method flLn() now gets wrapping by argument; checking for
filename (inspect.js) is useless when JS source files get aggregated.
* Deprecated Inspect::setConfig(); doesn't solve any (non-existing) problem.
* Package requires Utils package ^1.2.

### Fixed
* Reduce dependencies: Config package no longer required.


## [2.1] - 2017-09-24

### Added
* Frontend inspect is now exposed as simpleComplex.inspect,
apart from window.inspect.
* trace() log previous exception too, but don't trace it.
* trace() mark end of analyzed stack by printing number of frames
exceeding limit.

### Changed
* Frontend inspect now only provides functions typeOf and trace,
apart from inspect() function self.
Removed functions: configure, get, log, traceGet, traceLog,
argsGet, events, eventsGet, eventsLog, errorHandler, local.
* variable() treat Throwable as an exception, not a hashtable object.
* Default format 'enclose_tag' is now empty (none).

### Fixed
* Frontend inspect no longer requires jQuery.
* Use document root features of Utils instead of own (redundant and unsafe)
features.
* Config and locale-text no longer risk that dupe key scalar values become
array.


## [2.0] - 2017-07-12

### Added
* New option escape_html; default off.
* Report empty array as numerically indexed array.
* Reflect the array-like behaviour of ArrayAccess objects.
* Inspector toString with option to skip preface.

### Changed
* Complete remake, all extraneous features removed; like built-in logger.
* Obsoleted options: message, hide_scalars, hide_paths, by_user, one_lined,
no_preface, no_fileline, name.
* Inspect allow extending constructur to provide dependencies by other means.
* Pass Inspect instance to Inspector constructor.
* Use SimpleComplex\Utils\EnvVarConfig as fallback config object.
* Use Inspect config object directly in the Inspector class.
* Use dependency injection container when possible.
* Default string needles/replacers shan't replace <> unless option escape_html.
* Default string replacers shan't HTML escape quotes unless option escape_html.

### Fixed
* Secure dependencies on demand, not on instantiation.
* In-package exception types obsolete.


## [1.1] - 2016-05-01

### Fixed
* Bad type in frontend .errorHandler(); literal 'messsage' (3 x 's') made for
empty output.
* Don't set/get cookie in CLI mode.
* Session counting is not important enough to risk PHP warning due to response
body sending already commenced.


## [1.0] - 2015-07-12

### Added
* Extraction of generic framework-independent code completed (the separation was
initiated earlier, with Drupal module Inspect 7.x-6.0).
* New instance var code, usable for event or error code.
* Now supports logging via injected PSR-3 logger; instance var (object) logger.

### Changed
* Removed method cliMode(), use PHP_SAPI === 'cli' instead.
* UTF-8 validity checker and byte truncater rewritten from scratch.
* Removal/replacement of Drupal legacy code concluded.
* Make sure session counting is initialised (if conf session_counters),
even if we don't listen to any request init event.
* configGet/Set() must be called with non-empty arg $domain if inspect var.
* Abstracted cookie retrieval to allow overriding cookie handling by extenders.
* Backend and frontend: category is now an alias of type (instead of vice versa)
* and the use of category is deprecated.
* Default dir for filing (setting: file_path) is now system temp dir; in effect
[system temp dir]/logs.

### Fixed
* Fixed frontend file:line resolver; failed to identify inspect.js self.
* Frontend: stricter typeOf() array check; some jQuery extensions (old
dataTables) were erroneously assessed as array.


## [0.1] - 2011-10-16

### Added
* Initial version, as [Drupal module Inspect](https://www.drupal.org/project/inspect/).
