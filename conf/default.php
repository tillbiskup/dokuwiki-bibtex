<?php
/**
 * Default settings for the bibtex plugin
 *
 * @author Till Biskup <till@till-biskup>
 */

$conf['_basic'] = '';
$conf['sqlite'] = '';
$conf['citetype'] = 'numeric';
$conf['file'] = '';
$conf['pdfdir'] = '';
$conf['sort'] = '';
$conf['_formatstrings'] = '';
$conf['fmtstr_article'] = 'AUTHOR (YEAR): TITLE, <em>JOURNAL</em> <strong>VOLUME</strong>:PAGES';
$conf['fmtstr_book'] = 'AUTHOR (YEAR): TITLE, PUBLISHER, ADDRESS';
$conf['fmtstr_booklet'] = '';
$conf['fmtstr_conference'] = 'AUTHOR (YEAR): TITLE, in: BOOKTITLE';
$conf['fmtstr_inbook'] = 'AUTHOR (YEAR): CHAPTER, in TITLE, PAGESABBREV PAGES, PUBLISHER, ADDRESS';
$conf['fmtstr_incollection'] = 'AUTHOR (YEAR): TITLE, in EDITOR (EDITORABBREV): <em>BOOKTITLE</em>, CHAPTERABBREV CHAPTER, PAGESABBREV PAGES, PUBLISHER, ADDRESS';
$conf['fmtstr_inproceedings'] = 'AUTHOR (YEAR): TITLE, in EDITOR (EDITORABBREV): <em>BOOKTITLE</em>, PAGESABBREV PAGES, PUBLISHER, ADDRESS';
$conf['fmtstr_manual'] = '';
$conf['fmtstr_mastersthesis'] = 'AUTHOR (YEAR): TITLE, SCHOOL (MASTERSTHESIS)';
$conf['fmtstr_misc'] = '';
$conf['fmtstr_phdthesis'] = 'AUTHOR (YEAR): TITLE, SCHOOL (PHDTHESIS)';
$conf['fmtstr_proceedings'] = 'EDITOR (YEAR): TITLE';
$conf['fmtstr_techreport'] = 'AUTHOR (YEAR): TITLE, INSTITUTION (TECHREPORT)';
$conf['fmtstr_unpublished'] = 'AUTHOR: TITLE, NOTE';
