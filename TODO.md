# TODO

Things that need to/should be done, as of May 2023


## Refactor plugin code

The code is not in very good shape and should be refactored and properly documented.

Some references how to properly format the code (including PHP style guides):

* https://pear.php.net/manual/en/standards.php

Nowadays, it seems that the PHP-FIG guys are the most accepted, with PSR-1, PSR-12, and PER:

* https://www.php-fig.org/psr/psr-1/
* https://www.php-fig.org/psr/psr-12/
* https://www.php-fig.org/per/coding-style/

Another thing missing is a proper documentation (at least of the code as such). Coming from Python/Sphinx, documentation integrating both, API and user documentation, seems still in its infancies in PHP. However, phpDocumentor seems to be the *de facto* standard:

* https://phpdoc.org/
* https://docs.phpdoc.org/


## Better error handling

Currently, most errors in BibTeX files (and it is damn easy to have typos and alike in BibTeX files) get ignored silently, *i.e.* some things might not work due to not obvious reasons.

DokuWiki seems to have good messaging capabilities, hence make use of these to display problems to the user.


## Replace BibTeX backend code

Currently, the BibTeX parsing relies on rather old and badly changed code. Perhaps exchanging with newer code from the "PHP BibTeX Parser 2.x" project would be an option:

* https://github.com/renanbr/bibtex-parser

That would be available via composer and seems actively developed.

First speed tests with a larger bibliography (>2500 entries) shows reasonable speed (< 1 sec).


## Add proper handling of citation styles

Nowadays there is things such as the "Citation Style Language (CSL)", and PHP processors for this thing, such as

* https://github.com/seboettg/citeproc-php/
  
Perhaps that would be a (long-term) option to have proper handling of citation styles from within the DokuWiki BibTeX plugin.


## Some GUI for managing bibliographies?

Probably not sensible in times of Mendeley, JabRef, and other much more powerful tools. But eventually...

However, the main focus of the plugin remains allowing to reference literature from within the DokuWiki, much the same as BibTeX allows authors to reference literature in their writings. Managing a bibliographic database is an entirely different issue.
