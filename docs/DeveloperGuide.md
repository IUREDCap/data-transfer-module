<!-- =================================================
Copyright (C) 2025 The Trustees of Indiana University
SPDX-License-Identifier: BSD-3-Clause
================================================== -->

Developer Guide
===================

Directory Structure
-----------------------

* __classes/__ - PHP classes other than the main module class
* config.json - module configuration file
* __dev/__ - development dependencies, which are NOT committed to Git
* __docs/__ - documents
* README.md - module description and usage requirements
* DataTransfer.php - main module class
* __resources/__ - CSS, image, and JavaScript files
* __tests/__ - test files
    * __unit/__ - unit tests
    * __web/__ - web tests (that access a running instance of the module)
* __vendor/__ - production dependencies, which are committed to Git
* __web/__ - user web pages
    * __admin/__ - admin web pages

Updating Dependencies
--------------------------

__Production Dependencies__

To avoid requiring Composer to be run when the module is installed, dependencies are committed to Git, however,
only the non-development dependencies should be committed to Git. The non-development dependencies are stored
in the standard __vendor/__ directory.

To check for out of date dependencies, use:

    composer outdated --direct

To update the production dependencies update the composer.json file with the new dependency version
numbers and run the following command:

    composer update

__Development Dependencies__

Development dependencies are stored in the __dev/__ directory and are NOT committed to Git. They are managed
using the __dev-composer.json__ configuration file.

To check for out of date dependencies, use:

    COMPOSER=dev-composer.json composer outdated --direct

To install and update the development dependencies, which will be stored in directory __dev/__, use the following
commands in the top-level directory:

    COMPOSER=dev-composer.json composer install
    COMPOSER=dev-composer.json composer update

__Automated Web Tests Dependencies__

There are also separate dependencies (not committed to Git) that are used for the automated web tests.
The configuration file for these dependencies is:

    tests/web/composer.json

And the dependencies are stored in the following directory:

    tests/web/vendor


Coding Standards Compliance
-----------------------------

The Data Transfer external module follows these PHP coding standards, except where
prevented from following them by REDCap:

* [PSR-1: Basic Coding Standard](http://www.php-fig.org/psr/psr-1/)
* [PSR-2: Coding Style Guide](http://www.php-fig.org/psr/psr-2/)
* [PSR-4: Autoloader](http://www.php-fig.org/psr/psr-4/)
* Lower camel case variable names, e.g., $primaryKey


To check for coding standards compliance, enter the following command in the top-level directory:

    ./dev/bin/phpcs -n
    
The "-n" option eliminated warnings. The configuration for phpcs is in file __phpcs.xml__ in the top-level directory.


REDCap External Module Security Scan
-----------------------------------------------

Before releasing a new public version, run REDCap's external module security scan.


Automated Tests
--------------------------
To run the unit tests, enter the following command in the top-level directory:

    ./dev/bin/phpunit
    
The configuration for phpunit is in file __phpunit.xml__ in the top-level directory.

The module also has web tests that access a running Data Transfer external module. For
information on running these tests, see the file:

[tests/web/README.md](../tests/web/README.md)
