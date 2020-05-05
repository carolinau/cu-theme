CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation
 * Configuration
 * Maintainers


INTRODUCTION
------------

Allows site administrators to add classes to the outer HTML wrapper for any
field display, so that CSS and Javascript can target them.

It's particulary useful for adding classes required by various jQuery plugins
and CSS grid systems.

 * For a full description of the module, visit the project page:
   https://www.drupal.org/project/field_formatter_class

 * To submit bug reports and feature suggestions, or to track changes:
   https://www.drupal.org/project/issues/field_formatter_class


REQUIREMENTS
------------

This module requires no modules outside of Drupal core.


INSTALLATION
------------

 * Install the Field Formatter Class module as you would normally install a
   contributed Drupal module. Visit
   https://www.drupal.org/node/1897420 for further information.


CONFIGURATION
-------------

    1. Navigate to Administration > Extend to enable the module.
    2. Navigate to Administration > Structure > [Entity to edit] > Manage
       display.

The Field Formatter Class settings are found in the Manage display tab for
content types, users, and other entities. A text box is available for each
field, revealed by using the formatter settings edit button (gear wheel icon)
for that field.

The class is added to the outer <div> container for the field. The default
Drupal field classes (e.g. "field-type-taxonomy-term-reference") remain
available - this module does not remove any classes.


MAINTAINERS
-----------

 * Oleksandr Dekhteruk (pifagor) - https://www.drupal.org/u/pifagor
 * Andrew Macpherson (andrewmacpherson) - https://www.drupal.org/u/andrewmacpherson

Supporting organizations
------------------------

 * Annertech - https://www.drupal.org/annertech
 * GOLEMS GABB - https://www.drupal.org/golems-gabb
