<?php
// PSR-4 autoloader for the bundled smalot/pdfparser library.
//
// This file is shipped by local_literag (not by the upstream package, which no
// longer includes a non-Composer autoloader) so the vendored library can be used
// without Composer. It maps the Smalot\PdfParser\ namespace onto src/. The
// library's only optional runtime dependency, symfony/polyfill-mbstring, is not
// needed: it merely back-fills mb_* functions when ext-mbstring is missing, and
// Moodle hard-requires ext-mbstring.
//
// The library itself is LGPL-3.0; see LICENSE.txt and ../../../thirdpartylibs.xml.

spl_autoload_register(function (string $class): void {
    $prefix = 'Smalot\\PdfParser\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/src/Smalot/PdfParser/' . str_replace('\\', '/', $relative) . '.php';
    if (is_readable($file)) {
        require $file;
    }
});
