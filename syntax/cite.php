<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * DokuWiki Plugin bibtex (Syntax Component)
 *
 * Parse citation commands (i.e., actual references to the literature)
 *
 * For parsing the <bibtex>...</bibtex> structures of the markup,
 * see the file "bibtex.php".
 *
 * PHP versions 5, 7 and 8
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Till Biskup <till@till-biskup.de>
 * @version 0.3
 * @date    2023-05-28
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC.'lib/plugins/');

require_once DOKU_PLUGIN.'syntax.php';

class syntax_plugin_bibtex_cite extends DokuWiki_Syntax_Plugin {
    public function getType() {
        return 'substition';
    }

    public function getPType() {
        return 'normal';
    }

    public function getSort() {
        return 32;
    }

    public function connectTo($mode) {
        $this->Lexer->addSpecialPattern('\{\[.+?\]\}', $mode, 'plugin_bibtex_cite');
    }

    public function handle($match, $state, $pos, Doku_Handler $handler){
        // Strip syntax and return only bibtex key(s)
        preg_match('/\{\[(.+?)\]\}/', $match, $matches);
        return array($matches[1], $state, $pos);
    }

    public function render($mode, Doku_Renderer $renderer, $data) {
        @list($match, $state, $pos) = $data;
        global $ID;
        require_once(DOKU_PLUGIN.'bibtex/lib/bibtexrender.php');
        $bibtexrenderer = bibtexrender_plugin_bibtex::getResource($ID);

        // Check whether the reference exists, otherwise silently ignore
        // The problem still exists when all keys in one block do not exist
        $bibkeys = explode(',', $match);
        if ((count($bibkeys) > 1) || $bibtexrenderer->printCitekey($match)) {

            if($mode == 'xhtml') {
                $renderer->doc .= '[' ;
                foreach ($bibkeys as $bibkey) {
                    if ($bibtexrenderer->keyExists($bibkey)) {
                        $renderer->doc .= '<span class="bibtex_citekey"><a href="#ref__'
                            . $bibkey . '" name="reft__' . $bibkey . '" id="reft__'
                            . $bibkey . '" class="bibtex_citekey">';
                        $renderer->doc .= $bibtexrenderer->printCitekey($bibkey);
                        $renderer->doc .= '</a><span>';
                        $renderer->doc .= $bibtexrenderer->printReference($bibkey);
                        $renderer->doc .= '</span></span>';
                        // Suppress comma after last bibkey (alternative: implode)
                        if ($bibkey != $bibkeys[sizeof($bibkeys)-1]) {
                            $renderer->doc .= ', ';
                        }
                    } else {
                        $renderer->doc .= $bibkey;
                    }
                }
                $renderer->doc .= "]";
            }

            if($mode == 'latex') {
                $renderer->doc .= '\cite{';
                foreach ($bibkeys as $bibkey) {
                    $renderer->doc .= $bibkey;
                    // Suppress comma after last bibkey (alternative: implode)
                    if ($bibkey != $bibkeys[sizeof($bibkeys)-1]) {
                        $renderer->doc .= ",";
                    }
                }
                $renderer->doc .= "}";
            }

            if($mode == 'odt') {
                $renderer->doc .= "[" ;
                foreach ($bibkeys as $bibkey) {
                    $renderer->doc .= $bibtexrenderer->printCitekey($bibkey);
                    // Suppress comma after last bibkey (alternative: implode)
                    if ($bibkey != $bibkeys[sizeof($bibkeys)-1]) {
                        $renderer->doc .= ', ';
                    }
                }
                $renderer->doc .= "]";
            }
            return true;
        }
        return false;
    }
}
?>
