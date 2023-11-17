<?php
/**
 * Default settings for the bibtex4dw plugin
 *
 * @author Till Biskup <till@till-biskup.de>
 */

$conf['_basic'] = '';
$conf['sqlite'] = '';
$conf['citetype'] = 'numeric';
$conf['file'] = '';
$conf['pdfdir'] = '';
$conf['sort'] = '';
$conf['_formatstrings'] = '';
$conf['fmtstr_article'] = '{@AUTHOR@}{ (@YEAR@)}{: "@TITLE@"}{, <em>@JOURNAL@</em> }{<strong>@VOLUME@</strong>}{(@NUMBER@)}{:@PAGES@}.{ @NOTE@}{ <a href="https://doi.org/@DOI@">(DOI)</a>}{ <a href="@URL@">(Link)</a>}';
$conf['fmtstr_book'] = '{@AUTHOR@}{ (@YEAR@)}{: "@TITLE@"}{ <em>@EDITOR@ (EDITORABBREV)</em>}{. @PUBLISHER@}{, @ADDRESS@}.{ @NOTE@}{ <a href="https://doi.org/@DOI@">(DOI)</a>}{ <a href="@URL@">(Link)</a>}';
$conf['fmtstr_booklet'] = '';
$conf['fmtstr_conference'] = '{@AUTHOR@}{ (@YEAR@)}{: "@TITLE@"}{, in: <em>@BOOKTITLE@</em>}{ <em>@EDITOR@ (EDITORABBREV)</em>}{, PAGESABBREV @PAGES@}{. @PUBLISHER@}{, @ADDRESS@}.{ @NOTE@}{ <a href="https://doi.org/@DOI@">(DOI)</a>}{ <a href="@URL@">(Link)</a>}';
$conf['fmtstr_inbook'] = '{@AUTHOR@}{ (@YEAR@)}{: "@TITLE@"}{, in: <em>@BOOKTITLE@</em>}{ <em>@EDITOR@ (EDITORABBREV)</em>}{, CHAPTERABBREV @CHAPTER@}{, PAGESABBREV @PAGES@}{. @PUBLISHER@}{, @ADDRESS@}.{ @NOTE@}{ <a href="https://doi.org/@DOI@">(DOI)</a>}{ <a href="@URL@">(Link)</a>}';
$conf['fmtstr_incollection'] = '{@AUTHOR@}{ (@YEAR@)}{: "@TITLE@"}{, in: <em>@BOOKTITLE@</em>}{ <em>@EDITOR@ (EDITORABBREV)</em>}{, CHAPTERABBREV @CHAPTER@}{, PAGESABBREV @PAGES@}{. @PUBLISHER@}{, @ADDRESS@}.{ @NOTE@}{ <a href="https://doi.org/@DOI@">(DOI)</a>}{ <a href="@URL@">(Link)</a>}';
$conf['fmtstr_inproceedings'] = '{@AUTHOR@}{ (@YEAR@)}{: "@TITLE@"}{, in: <em>@BOOKTITLE@</em>}{ <em>@EDITOR@ (EDITORABBREV)</em>}{, PAGESABBREV @PAGES@}{. @PUBLISHER@}{, @ADDRESS@}.{ @NOTE@}{ <a href="https://doi.org/@DOI@">(DOI)</a>}{ <a href="@URL@">(Link)</a>}';
$conf['fmtstr_manual'] = '';
$conf['fmtstr_mastersthesis'] = '{@AUTHOR@}{ (@YEAR@)}{: "@TITLE@"}{. @SCHOOL@} (MASTERSTHESIS).{ @NOTE@}{ <a href="https://doi.org/@DOI@">(DOI)</a>}{ <a href="@URL@">(Link)</a>}';
$conf['fmtstr_misc'] = '{@AUTHOR@}{ (@YEAR@)}{: "@TITLE@"}{. @HOWPUBLISHED@}.{ @NOTE@}{ <a href="https://doi.org/@DOI@">(DOI)</a>}{ <a href="@URL@">(Link)</a>}';
$conf['fmtstr_phdthesis'] = '{@AUTHOR@}{ (@YEAR@)}{: "@TITLE@"}{. @SCHOOL@} (PHDTHESIS).{ @NOTE@}{ <a href="https://doi.org/@DOI@">(DOI)</a>}{ <a href="@URL@">(Link)</a>}';
$conf['fmtstr_proceedings'] = '{@EDITOR@ (EDITORABBREV)}{ (@YEAR@)}{: "@TITLE@"}{. @PUBLISHER@}{, @ADDRESS@}.{ @NOTE@}{ <a href="https://doi.org/@DOI@">(DOI)</a>}{ <a href="@URL@">(Link)</a>}';
$conf['fmtstr_techreport'] = '{@AUTHOR@}{ (@YEAR@)}{: "@TITLE@"}{, @INSTITUTION@}{ @NUMBER@} (TECHREPORT).{ @NOTE@}{ <a href="https://doi.org/@DOI@">(DOI)</a>}{ <a href="@URL@">(Link)</a>}';
$conf['fmtstr_unpublished'] = '{@AUTHOR@}{ (@YEAR@)}{: "@TITLE@"} (UNPUBLISHED).{ @NOTE@}{ <a href="@URL@">(Link)</a>}';
