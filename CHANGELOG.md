# Changelog

Manual changelog with some possibly important aspects

For the full history, have a look at the git commit messages.

Note that the changelog started only in May 2023, after the plugin was not developed for several years (but continuously used by its author). Old changes are taken from the (German) plugin homepage of its author.

## 2023-11-19

* Merged PR from SECtim, closing [#3](https://github.com/tillbiskup/dokuwiki-bibtex/issues/3), [#4](https://github.com/tillbiskup/dokuwiki-bibtex/issues/4), [#5](https://github.com/tillbiskup/dokuwiki-bibtex/issues/5)

## 2023-05-28

* Rename plugin: "bibtex" => "bibtex4dw"
* Further refactoring
* Start with sensible messages to users via DokuWiki ``msg()`` function

## 2023-05-27

* Massive speedup of SQL by changing SQL syntax and pooling requests in single transaction
* Start refactoring the code slightly


## 2015-04-25

* Optionally, the plugin can use an SQLite db (using the sqlite plugin). This speeds up things, particulary with large bibliography databases, and reduces latencies when rendering pages with references.


## 2013-01-10

* Display of PDF files: If a PDF file with a name identical to the BibTeX key of a reference esists in a configurable namespace, a link to this file will be added to the end of the reference, permitted the user has read access to this namespace


## 2013-01-06

* Display of "tooltips" adjusted to work with new standard template of DokuWiki; now realised in JavaScript
