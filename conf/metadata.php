<?php
/**
 * Options for the bibtex plugin
 *
 * @author Till Biskup <till@till-biskup>
 */

$meta['_basic'] = array('fieldset');
$meta['sqlite'] = array('onoff');
$meta['citetype'] = array('multichoice','_choices' => array('alpha','apa','authordate','numeric'));
$meta['file'] = array('string');
$meta['pdfdir'] = array('string');
$meta['sort'] = array('onoff');
$meta['_formatstrings'] = array('fieldset');
$meta['fmtstr_article'] = array('string');
$meta['fmtstr_book'] = array('string');
$meta['fmtstr_booklet'] = array('string');
$meta['fmtstr_conference'] = array('string');
$meta['fmtstr_inbook'] = array('string');
$meta['fmtstr_incollection'] = array('string');
$meta['fmtstr_inproceedings'] = array('string');
$meta['fmtstr_manual'] = array('string');
$meta['fmtstr_mastersthesis'] = array('string');
$meta['fmtstr_misc'] = array('string');
$meta['fmtstr_phdthesis'] = array('string');
$meta['fmtstr_proceedings'] = array('string');
$meta['fmtstr_techreport'] = array('string');
$meta['fmtstr_unpublished'] = array('string');

