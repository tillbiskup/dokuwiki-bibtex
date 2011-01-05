<?php
/**
 * english language file for bibtex plugin
 *
 * @author Till Biskup <till@till-biskup>
 */

// keys need to match the config setting name
$lang['_basic'] = 'And what happens if I include here some text?';
$lang['citetype'] = 'Reference key style, i.e. the way the citations are displayed inline. Examples would be [Hore, 1989] (authoryear), [5] (numeric), or [Hor89] (alpha)';
$lang['file'] = "BibTeX File(s) used by default for the bibliography. If you don't enter here a value, you have to include it with the <code>&lt;bibtex&gt;file=...&lt;/bibtex&gt;</code> pattern at the beginning of the page.<br />It is good practice to use dokuwiki pages here (separated by ';') that contain only BibTeX code.";
$lang['sort'] = 'Whether to sort the references in the bibliography in alphabetic order.<br /> This setting does not affect the "numeric" citation style.';
$lang['_formatstrings'] = 'Formatting of the different BibTeX entry types is done via format strings that contain the field names in capital letters. Formatting can be done via HTML tags (italics, bold, ...).';
$lang['fmtstr_article'] = 'Format string for the BibTeX article entry type references.';
$lang['fmtstr_book'] = 'Format string for the BibTeX book entry type references.';
$lang['fmtstr_booklet'] = 'Format string for the BibTeX booklet entry type references.';
$lang['fmtstr_conference'] = 'Format string for the BibTeX conference entry type references.';
$lang['fmtstr_inbook'] = 'Format string for the BibTeX inbook entry type references.';
$lang['fmtstr_incollection'] = 'Format string for the BibTeX incollection entry type references.';
$lang['fmtstr_inproceedings'] = 'Format string for the BibTeX inproceedings entry type references.';
$lang['fmtstr_manual'] = 'Format string for the BibTeX manual entry type references.';
$lang['fmtstr_mastersthesis'] = 'Format string for the BibTeX mastersthesis entry type references.';
$lang['fmtstr_misc'] = 'Format string for the BibTeX misc entry type references.';
$lang['fmtstr_phdthesis'] = 'Format string for the BibTeX phdthesis entry type references.';
$lang['fmtstr_proceedings'] = 'Format string for the BibTeX proceedings entry type references.';
$lang['fmtstr_techreport'] = 'Format string for the BibTeX techreport entry type references.';
$lang['fmtstr_unpublished'] = 'Format string for the BibTeX unpublished entry type references.';


//Setup VIM: ex: et ts=4 :
