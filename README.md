# bibtex Plugin for DokuWiki

Handling references stored in BIBTeX (text) databases in DokuWiki.

DokuWiki is an excellent tool for knowledge management and has been successfully used in academic context for long time. However, academists tend to use references to the literature when managing their knowledge, and DokuWiki is missing appropriate tools here. Being familiar with using BibTeX both, as a bibliographic (text) database format and as an engine to format references in documents written using LaTeX, a plugin for DokuWiki understanding the BibTeX format and allowing to reference literature from within wiki pages seems an obvious choice.

## Intended use cases

* Literature references in an academic context
* Reuse existing BibTeX databases


## Core criteria

* Works with larg(er) bibliographic databases (several thousand entries)
* Allows to use string replacements (concrete example: abbreviated and full journal names)
* Access to PDF files of the reference, as long as available locally and accessible by the currently logged-in unser (obeying ACLs)

## Philosophy

* Resilience: plain text files, no external dependencies, no database as permanent storage (only temporary, the "truth" is in the text files)
* Unix philosophy (sort of):
  * Write programs that do one thing and do it well.
  * Write programs to work together.
  * Write programs that handle text streams, because that is a universal interface.


## Installation

If you install this plugin manually, make sure it is installed in
lib/plugins/bibtex/ - if the folder is called different it
will not work!

Please refer to http://www.dokuwiki.org/plugins for additional info
on how to install plugins in DokuWiki.


## Documentation

All documentation for this plugin can (for the time being) be found at:

* https://till-biskup.de/de/software/dokuwiki/bibtex


## Contributing

Development of the plugin takes place primarily on GitHub:

* https://github.com/tillbiskup/dokuwiki-bibtex

Feel free to fork the repository, change it and submit pull requests.

If you find some problems, open an issue on GitHub, and ideally provide a pull request with a possible solution.


## Copyright and license

Copyright (c) Till Biskup

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; version 2 of the License

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

See the COPYING file in your DokuWiki folder for details
