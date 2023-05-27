<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * DokuWiki Plugin bibtex (Syntax Component)
 *
 * Parse bibtex-blocks in xhtml mode
 *
 * PHP versions 5, 7 and 8
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Till Biskup <till@till-biskup.de>
 * @version 0.2
 * @date    2023-05-27
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC.'lib/plugins/');

require_once DOKU_PLUGIN.'syntax.php';

class syntax_plugin_bibtex_bibtex extends DokuWiki_Syntax_Plugin {
    public function getType() {
        return 'protected';
    }

    public function getPType() {
        return 'block';
    }

    public function getSort() {
        return 32;
    }


    public function connectTo($mode) {
        $this->Lexer->addEntryPattern('<bibtex(?=.*?>.*?</bibtex>)', $mode, 'plugin_bibtex_bibtex');
    }

    public function postConnect() {
        $this->Lexer->addExitPattern('</bibtex>', 'plugin_bibtex_bibtex');
    }

    public function handle($match, $state, $pos, Doku_Handler $handler){
        if ($state == DOKU_LEXER_UNMATCHED) {
            // $matches[0] is the parameters <bibtex [parameters]>
            // $matches[1] is the text inside the block <bibtex [parameters]> </cmdexec>
            $matches = preg_split('/>/u', $match, 2);
            $matches[0] = trim($matches[0]);
            if ( trim($matches[0]) == '' ) {
	            $matches[0] = NULL;
            }
            return array($state, $matches[0], $matches[1], $pos);
        }
        return array($state, '', $match, $pos);
    }

    public function render($mode, Doku_Renderer $renderer, $data) {
        global $ID;
        if($mode == 'xhtml') {
        
	        list($state, $substate, $match, $pos) = $data;

    	    switch ($state) {
 
        	    case DOKU_LEXER_ENTER:
            	    break;
  
	            case DOKU_LEXER_UNMATCHED:                
    	            require_once(DOKU_PLUGIN.'bibtex/lib/bibtexrender.php');
        	        $bibtexrenderer = bibtexrender_plugin_bibtex::getResource($ID);
        	        // Handle special substate "database" already here
        	        if ($substate === 'database') {
						$bibtexrenderer->addBibtexToSQLite($match, $ID);
	                    $renderer->doc .= '<pre class="bibtex_database">';
	                	// return the dokuwiki markup within the figure tags
    	                $renderer->doc .= $renderer->_xmlEntities($match);
	                    $renderer->doc .= '</pre>';
        	            break;
        	        }
	                // split $match line by line
    	            $matches = preg_split("/\r?\n/", trim($match));
        	        // Add key-value pairs into options array
            	    foreach ($matches as $option) {
                	    $opt = explode('=', $option, 2);
                    	$options[$opt[0]][] = $opt[1];
	                }

    	            switch ($substate) {
        	            case 'bibliography':
            	            // Careful with options settings, as there are only very	
        	                // few options that do make sense to be set that late.
            	            if (array_key_exists('sort', $options)) {
                	            $oopt['sort'] = $options['sort'];
                        	}
            	            if (array_key_exists('nocite', $options)) {
                	            $oopt['nocite'] = $options['nocite'];
                        	}
                        	if ($oopt) {
                    	        $bibtexrenderer->setOptions($oopt);
                        	}
	                        $bibtex = $bibtexrenderer->printBibliography($substate);
    	                    $renderer->doc .= $bibtex;
        	                break;
            	        case 'furtherreading':
                	        $bibtexrenderer->setOptions($options);
                    	    $bibtex = $bibtexrenderer->printBibliography($substate);
	                        $renderer->doc .= $bibtex;
    	                    break;
        	            default:
            	            $bibtexrenderer->setOptions($options);
                	        break;
	                }
    	            break;
 
	            case DOKU_LEXER_EXIT:
    	            break;
 
	        }
 	
    	    return true;
    	}
    	
        if($mode == 'odt') {
        
	        list($state, $substate, $match, $pos) = $data;

    	    switch ($state) {
 
        	    case DOKU_LEXER_ENTER :
            	    break;
  
	            case DOKU_LEXER_UNMATCHED :
    	            break;
 
	            case DOKU_LEXER_EXIT :
    	            break;
 
	        }
 	
    	    return true;
    	}
    	
    	return false;
    }
    
}
?>
