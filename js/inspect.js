/**
 * SimpleComplex PHP Inspect
 * @link      https://github.com/simplecomplex/inspect
 * @copyright Copyright (c) 2011-2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/inspect/blob/master/LICENSE (MIT License)
 */

/*jslint browser: true, continue: true, indent: 2, newcap: true, nomen: true, plusplus: true, regexp: true, white: true, ass: true*/
/*global alert: false, confirm: false, console: false*/

/**
 * inspect variable dumper and error tracer.
 */
(function() {
  'use strict';

  var opts = ['depth', 'protos', 'func_body', 'message', 'wrappers'],
    nOpts = opts.length,
    quot = '`',
    dmnRgx = new RegExp(
      window.location.href.replace(/^(https?:\/\/[^\/]+)\/.*$/, '$1').replace(/([\/\.\-])/g, '\\' + '$1')
    ),
    /**
     * All native types are reported in lowercase (like native typeof does).
     * If given no arguments: returns 'inspect'.
     * Types are:
     * - native, typeof: object string number
     * - native, lowercased: function array date regexp image
     * - window, document, document.documentElement (not lowercase)
     * - element, checked via .getAttributeNode
     * - text node: textNode
     * - attribute node: attributeNode
     * - event: event (native and prototyped W3C Event and native IE event)
     * - jquery
     * - emptyish and bad: undefined, null, NaN, infinite
     * - custom or prototyped native: all classes having a typeOf() method.
     * RegExp is an object of type regexp (not a function - gecko/webkit/chromium).
     * Does not check if Date object is NaN.
     *
     * @ignore
     * @private
     * @param {*} u
     * @return {string}
     */
    typeOf = function(u) {
      var t = typeof u;
      if (!arguments.length) {
        return 'inspect';
      }
      switch (t) {
        case 'boolean':
        case 'string':
          return t;
        case 'number':
          return isFinite(u) ? t : (isNaN(u) ? 'NaN' : 'infinite');
        case 'object':
          if (u === null) {
            return 'null';
          }
          // Accessing properties of object may err for various reasons, like
          // missing permission (Gecko).
          try {
            if (u.typeOf && typeof u.typeOf === 'function') {
              return u.typeOf();
            }
            if (typeof u.length === 'number' && !(u.propertyIsEnumerable('length')) && typeof u.splice === 'function'
              && Object.prototype.toString.call(u) === '[object Array]'
            ) {
              return 'array';
            }
            if (u === window) {
              return 'window';
            }
            if (u === document) {
              return 'document';
            }
            if (u === document.documentElement) {
              return 'document.documentElement';
            }
            if (u.getAttributeNode) { // element
              // document has getElementsByTagName, but not getAttributeNode
              // - document.documentElement has both.
              return u.tagName.toLowerCase === 'img' ? 'image' : 'element';
            }
            if (u.nodeType) {
              switch (u.nodeType) {
                case 3:
                  return 'textNode';
                case 2:
                  return 'attributeNode';
              }
              return 'otherNode';
            }
            if (typeof u.stopPropagation === 'function' ||
              (u.cancelBubble !== undefined
                && typeof u.cancelBubble !== 'function'
                && typeof u.boundElements === 'object'
              )) {
              return 'event';
            }
            if (typeof u.getUTCMilliseconds === 'function') {
              return 'date';
            }
            if (typeof u.exec === 'function' && typeof u.test === 'function') {
              return 'regexp';
            }
            if (u.hspace && typeof u.hspace !== 'function') {
              return 'image';
            }
            if (u.jquery && typeof u.jquery === 'string' && !u.hasOwnProperty('jquery')) {
              return 'jquery';
            }
          }
          catch (ignore) {
          }
          return t;
        case 'function':
          //  gecko and webkit reports RegExp as function instead of object
          return (u.constructor === RegExp || (typeof u.exec === 'function' && typeof u.test === 'function')) ?
            'regexp' : t;
      }
      return t;
    },
    /**
     * Find file and line.
     *
     * @ignore
     * @private
     * @param {number} [wrappers]
     *    Integer.
     * @return {string}
     */
    flLn = function(wrappers) {
      var ar, le, i, v, p, wrps = wrappers || 0;
      //  Find call (first trace line outside this file).
      try {
        throw new Error(); // <- Not a mistake.
      }
      catch (er) {
        ar = trc(er);
      }
      if (typeof ar === 'string' && ar.indexOf('\n') > -1 && (le = (ar = ar.split('\n')).length)) {
        for (i = 0; i < le; i++) {
          if (i && (p = (v = ar[i]).lastIndexOf('@')) > -1 && v.indexOf('/inspect.js') === -1) {
            if (wrps) {
              if ((i += wrps) >= le) {
                return '@too_many_wrappers';
              }
              if ((p = (v = ar[i]).lastIndexOf('@')) > -1) {
                return '@wrappers_no_at';
              }
            }
            return v.substr(p);
          }
        }
      }
      return '@n/a';
    },
    /**
     * Resolve options argument.
     *
     * @ignore
     * @private
     * @param {object|number|string|boolean} u
     * @return {object}
     */
    optsRslv = function(u) {
      var o = {}, i, t;
      if (!u || (t = typeof u) !== 'object') {
        for (i = 0; i < nOpts; i++) {
          o[ opts[i] ] = undefined;
        }
        if (u) {
          switch (t) {
            case 'number':
              if (isFinite(u)) {
                o.depth = u;
              }
              break;
            case 'string':
              switch (u) {
                case 'protos':
                  o.protos = true;
                  break;
                case 'func_body':
                  o.func_body = true;
                  break;
                default:
                  o.message = u;
              }
              break;
            case 'boolean':
              if (u) {
                o.protos = true;
              }
              break;
          }
        }
        else if (u === 0) {
          o.depth = 0;
        }
      }
      else {
        for (i = 0; i < nOpts; i++) {
          o[ opts[i] ] = u.hasOwnProperty(opts[i]) ? u[ opts[i] ] : undefined;
        }
      }
      return o;
    },
    /**
     * Send message to browser console, if exists.
     *
     * @ignore
     * @private
     * @param {*} [ms]
     * @return {void}
     */
    cnsl = function(ms) {
      // Detecting console is not possible.
      try {
        console.log('' + ms);
      }
      catch (ignore) {
      }
    },
    /**
     * Analyze variable.
     *
     * @ignore
     * @private
     * @param {*} u
     * @param {boolean} [protos]
     *  - default: false
     *  - include non-native prototypals
     * @param {boolean} [funcBody]
     *  - default: false
     *  - print bodies of functions
     * @param {number} [max]
     *    Integer, default: 10.
     * @param {number} [depth]
     *    Integer, default: zero.
     * @return {string}
     */
    nspct = function(u, protos, funcBody, max, depth) {
      var m = max !== undefined ? max : 10, d = depth || 0, fb = funcBody || false,
        t, isArr, p, pT, v, buf = '', s, isProt, nInst = 0, nProt = 0, ind, fInd, i, em;
      if (m > 10 || m < 0) {
        m = 10;
      }
      if (d < 11) {
        if (u === undefined) {
          return '(undefined)\n';
        }
        // Check type by typeof ----------------------
        switch ((t = typeof u)) {
          case 'object':
            if (u === null) {
              return '(object) null\n';
            }
            break;
          case 'boolean':
            return '(' + t + ') ' + u.toString() + '\n';
          case 'string':
            return '(' + t + ':' + u.length + ') ' + quot +
              u.replace(/\n/g, '_NL_').replace(/\r/g, '_CR_').replace(/\t/g, '_TB_') +
              quot + '\n';
        }
        // Check by typeOf() -------------------------
        ind = new Array(d + 2).join('.  ');
        switch ((t = typeOf(u))) {
          case 'number':
            return '(' + t + ') ' + u + '\n';
          case 'NaN':
            return '(NaN) NaN\n';
          case 'infinite':
            return '(' + t + ') infinite\n';
          case 'regexp':
            return '(' + t + ') ' + u.toString() + '\n';
          case 'date':
            return '(date) ' + (u.toISOString ? u.toISOString() : u) + '\n';
          case 'function':
            // Find static members of the function.
            try {
              for (p in u) {
                if (u.hasOwnProperty(p) && p !== 'prototype') {
                  ++nInst;
                  // Recursion.
                  buf += ind + p + ': ' + nspct(u[p], protos, fb, m - 1, d + 1);
                }
              }
            }
            catch (er1) {
            }
            v = u.toString();
            // Remove indentation of function as a whole.
            if (fb && (i = (v = v.replace(/\r/g, '').replace(/\n$/, '')).search(/[\ ]+\}$/)) > -1) {
              v = v.replace(new RegExp('\\n' + new Array(v.length - i).join('\\ '), 'g'), '\n');
            }
            return '(' + t + ':' + nInst + ') {\n' +
              (fInd = new Array(d + 2).join('   ')) +
              (!fb ? v.substr(0, v.indexOf(')') + 1) :
                  v.replace(/\n/g, '\n' + fInd)
              ) +
              '\n' +
              buf + ind.replace(/\.\ {2}$/, '') + '}\n';
          case 'window':
          case 'document':
          case 'document.documentElement':
            return '(' + t + ')\n';
          case 'element':
            // Events may have elements, with no tagName.
            return '(' + t + ':' + (u.tagName ? u.tagName.toLowerCase() : '') +
              ') ' + (u.id || '-') + '|' + (u.className || '-') +
              (!(u = u.getAttribute('name')) ? '' : ('|' + u)) + '\n';
          case 'textNode':
          case 'attributeNode':
          case 'otherNode':
            s = '(' + t + (t === 'otherNode' ? ':' + u.nodeType + ') ' : ' ');
            // Dumping nodeValue in IE<8 will fail.
            try {
              // Recursion.
              return s + nspct(u.nodeValue, protos, fb, m - 1, d + 1);
            }
            catch (er2) {
            }
            return s + 'Unknown node value (IE)\n';
          case 'image':
            return '(image) ' + u.src + '\n';
          case 'event':
            break;
        }
        //  object -------------------------------------------------------
        isArr = (t === 'array');
        // Instance properties, prototypal attributes, prototypal methods.
        buf = ['', '', ''];
        em = '';
        try {
          for (p in u) {
            pT = typeOf(v = u[p]); // <- Not an mistake.
            // Event misses hasOwnProperty method in some browsers, so we have
            // to check if hasOwnProperty exists.
            if (u.hasOwnProperty && u.hasOwnProperty(p)) {
              // Do always check for non-numeric instance properties if array
              // (error).
              if (isArr) {
                if (('' + p).search(/\D/) > -1) {
                  buf[0] += 'ERROR, non-numeric instance property [' + p + '] in array:\n';
                }
                else {
                  continue; // see next: if(isArr) {
                }
              }
              ++nInst;
              isProt = false;
            }
            else {
              ++nProt;
              if (!protos) {
                continue;
              }
              isProt = true;
            }
            if (m > 0) {
              s = p + ': ' +
                // Reference check.
                (v && pT === t && v === u ? (!d ? 'ref THIS\n' : 'ref SELF\n') :
                  nspct(v, protos, fb, m - 1, d + 1)); // recursion
              if (!isProt) {
                buf[0] += (ind + s);
              }
              else {
                buf[ pT !== 'function' ? 1 : 2 ] += (ind + '... ' + s);
              }
            }
          }
          if (isArr) {
            nInst = u.length;
            if (m > 0) {
              for (p = 0; p < nInst; p++) {
                buf[0] += ind + p + ': ' +
                  // Reference check.
                  ((v = u[p]) && pT === t && v === u ? (!d ? 'ref THIS\n' : 'ref SELF\n') :
                    // Recursion.
                    nspct(v, protos, fb, m - 1, d + 1));
              }
            }
          }
        }
        catch (er) {
          em = ' failed to inspect property ' + p + ', type ' + pT;
        }
        return '(' + t + ':' + nInst + (isArr ? '' : ('|' + nProt)) + ')' + em + (isArr ? ' [' : ' {') + '\n' +
          (m < 1 ? '' : (buf.join(''))) +
          new Array(d + 1).join('.  ') + (isArr ? ']' : '}') + '\n';
      }
      return 'RECURSION LIMIT\n';
    },
    /**
     * Trace and stringify error.
     *
     * @ignore
     * @private
     * @param {Error} er
     * @return {string}
     */
    trc = function(er) {
      var u, le, i, es = '' + er, s = es;
      // gecko, chromium.
      if ((u = er.stack)) {
        if ((le = (u = (u.replace(/\r/, '').split(/\n/))).length)) {
          i = u[0] === es ? 1 : 0; // chromium first line is error toString()
          for (i; i < le; i++) {
            s += '\n  ' + u[i].replace(/^[\ ]+at\ /, '@').replace(dmnRgx, '');
          }
        }
      }
      return s;
    },
    /**
     * inspect() variable and send output to console log.
     *
     * inspect is a function, but documented as class due to jsdoc limitation.
     *
     * (object) options (any number of):
     *  - (integer) depth (default and absolute max: 10)
     *  - (string) message (default empty)
     *  - (boolean) protos (default not: do only report number of prototypal
     *    properties of objects)
     *  - (boolean) func_body (default not: do not print function body)
     *  - (boolean|integer) wrappers (default zero: this function is not wrapped
     *    in one or more local logging functions/methods)
     *
     * (integer) option:
     *  - interpreted as depth
     *
     * (boolean) option:
     *  - interpreted as protos
     *
     * (string) options:
     *  - 'protos' ~ report prototypal properties of objects
     *  - 'func_body' ~ print function body
     *  - otherwise interpreted as message
     *
     * Reference checks:
     *  - only child versus immediate parent
     *  - doesnt allow to recurse deeper than 10
     *
     * Prototype properties are marked with prefix '... ', function's static
     * members with prefix '.. '.
     * Detects if an Array property doesnt have numeric key.
     * Risky procedures are performed within try-catch.
     *
     * inspect doesn't require jQuery, but these functions will fail silently
     * without it: .log(), .traceLog(), .events(), .eventsLog(), .eventsGet().
     *
     * @name inspect
     * @constructor
     * @class
     * @singleton
     * @param {*} u
     * @param {object|number|boolean|string} [options]
     *    Object: options.
     *    Integer: depth.
     *    Boolean: protos.
     *    String: message
     * @return {void}
     *    Logs to browser console, if exists
     */
    inspect = function(u, options) {
      var o = optsRslv(options), ms = o.message || '';
      cnsl(
        (!ms ? '' : (ms + ':\n')) + '[Inspect ' + flLn(o.wrappers) + ']\n' + nspct(u, o.protos, o.func_body, o.depth)
      );
    };

  /**
   * All native types are reported in lowercase (like native typeof does).
   * If given no arguments: returns 'inspect'.
   * Types are:
   * - native, typeof: object string number
   * - native, corrected: function array date regexp image
   * - window, document, document.documentElement (not lowercase)
   * - element, checked via .getAttributeNode
   * - text node: textNode
   * - attribute node: attributeNode
   * - event: event (native and prototyped W3C Event and native IE event)
   * - jquery
   * - emptyish and bad: undefined, null, NaN, infinite
   * - custom or prototyped native: all classes having a typeOf() method.
   * RegExp is an object of type regexp (not a function -
   * gecko/webkit/chromium).
   * Does not check if Date object is NaN.
   *
   * @function
   * @name inspect.typeOf
   * @param {*} u
   * @return {string}
   */
  inspect.typeOf = typeOf;
  /**
   * Trace error and send output to console log.
   *
   * (object) options (any number of):
   *  - (string) message (default empty)
   *  - (boolean|integer) wrappers (default zero: this function is not wrapped
   *    in one or more local logging functions/methods)
   *
   * (string) options is interpreted as message.
   *
   * @example
   try {
  throw new Error('¿Que pasa?');
}
   catch(er) {
  inspect.trace(er, 'Class.method()');
}
   * @function
   * @name inspect.trace
   * @param {Error} er
   * @param {object|string} [options]
   *    Oobject: options.
   *    String: message.
   * @return {void}
   *    Logs to browser console, if exists.
   */
  inspect.trace = function(er, options) {
    var o = options || {}, s = o, wrps;
    if (typeof o === 'object') {
      s = o.message || '';
      wrps = o.wrappers;
    }
    cnsl(
      (!s ? '' : (s + ':\n')) + '[Inspect trace ' + flLn(wrps) + ']\n' + trc(er)
    );
  };
  /**
   * Use for checking if that window.inspect is actually the one we are
   * looking for (see example).
   * The name of the property is just something unlikely to exist (and the
   * class name backwards).
   * @example
   //  Check if inspect exists, and that it's the active sort.
   if(typeof window.inspect === 'function' && inspect.tcepsni) {
  inspect('whatever');
}
   * @name inspect.tcepsni
   * @type {boolean}
   */
  inspect.tcepsni = true;

  window.inspect = inspect;

  if (!window.simpleComplex) {
    window.simpleComplex = {};
  }
  window.simpleComplex.inspect = inspect;

})();
