
openeclass-imscc
================
A php application converting openEclass courses to IMS Common Cartridge format

The application requires access to the openeclass database, from which it retrieves the required information.
Currently the course data being converted are title, description, information, files and links. Other modules
might also be supported in the future.

Requirements
------------

PEAR Package Console_CommandLine

Setup
-----

- Clone repository
- Edit config/config_default.php configuration values
- Rename config/config_default.php to config.php

Usage
-----

Run 'php eclass-to-imscc.php -c {Course_id} -f {output filename}'
