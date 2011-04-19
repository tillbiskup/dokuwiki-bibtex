<?php
/**
 * DokuWiki Plugin bibtex (BibTeX Renderer Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Till Biskup <till@till-biskup>
 * @version 0.1
 * @date    2010-12-26
 */
 
require_once(DOKU_PLUGIN.'bibtex/lib/bibtexparser.php');

class bibtexrender_plugin_bibtex {

    /**
     * Array containing all references to objects of this class that already exist.
     * 
     * Necessary to synchronize calls from the two different syntax plugins for the same page
     */
    public static $resources = array();

    /**
     * Array containing local configuration of the object
     *
     * Options can be set by calling setOptions($options = array())
     */
    private $_conf = array(
    	file => array(),
    	citetype => '',
    	sort => false,
    	formatstring => array()
    	);
    
    /**
     * Array containing all references from the BibTeX files loaded
     */
    private $_langStrings = array(
        'pageabbrev',
        'pagesabbrev',
        'chapterabbrev',
        'editorabbrev'
        );
    
    /**
     * Array containing all references from the BibTeX files loaded
     */
    private $_bibtex_references = array();
    
    /**
     * Array containing all keys from the BibTeX records in $_bibtex_references
     */
    private $_bibtex_keys = array();
    
    /**
     * Array containing all keys already cited together with their number
     */
    private $_bibtex_keysCited = array();
    
    /**
     * Array containing all keys given with "nocite" together with their number
     */
    private $_bibtex_keysNotCited = array();

    /**
     * Number of the currently highest cite key if style "numeric" is used
     */
    private $_currentKeyNumber = 0;

    /**
     * Initializes the class
     *
     * As it loads the configuration options set e.g. via the config manager via the admin
     * interface of dokuwiki, the plugin can be configured such that only the final
     * <bibtex bibliography></bibtex> pattern is necessary to print the actual bibliography.
     */
    function __construct() {
        // Use trick to access configuration and language stuff from the actual bibtex plugin
        // with the builtin methods of DW
		require_once(DOKU_PLUGIN.'bibtex/syntax/bibtex.php');
		$this->plugin = new syntax_plugin_bibtex_bibtex();
		
		// Transfer config settings from plugin config (via config manager) to local config
		// Note: Only these settings have to be transferred that can be changed by this class.
		//       Therefore it is not necessary to transfer the format strings for the entries.
		$this->_conf['file'] = explode(';',$this->plugin->getConf('file'));
		$this->_conf['citetype'] = $this->plugin->getConf('citetype');
		
        // If there are files to load, load and parse them
        if (array_key_exists('file',$this->_conf)) {
            $this->_parseBibtexFile();
        }
    }
    
    /**
     * Gets instance of the class by id
     *
     * @param pageid
     */
    public function getResource($id = NULL) {
		// exit if given parameters not sufficient.
		if (is_null($id)) {
			return null;
		}
		if (!array_key_exists($id, self::$resources)) {
		    $x = new bibtexrender_plugin_bibtex($id);
		    self::$resources[$id] = $x;
		}
		// return the desired object or null in case of error
		return self::$resources[$id];
        
    }
    
    /**
     * Set options of object
     *
     * @param options
     */
    public function setOptions($options) {
        // Clear nocite array if it already exists and is not empty.
        // This is necessary for the "furtherreading" bibliographies, so that you
        // can have more than one of these bibliographies that are independent.
        if (isset($this->_bibtex_keysNotCited) && (count($this->_bibtex_keysNotCited))) {
            $this->_bibtex_keysNotCited = array();
        }
        // Handle options, convert to $_conf if possible
        foreach($options as $optkey => $optval) {
            if ('nocite' == $optkey) {
                // if $optval is an array (i.e. more than one "nocite" was given)
                if (is_array($optval)) {
                    $optval = implode(',',$optval);
                }
                $bibkeys = explode(',',$optval);
                foreach ($bibkeys as $bibkey) {
                    $this->_bibtex_keysNotCited[$bibkey] = 0;
                }
            }
            if (array_key_exists($optkey,$this->_conf)) {
                // For file, allow multiple entries
                if ('file' == $optkey) {
                    if (is_array($optval) && (count($optval) > 1)) {
                        $this->_conf[$optkey] = $optval;
                    } else {
                        $this->_conf[$optkey][] = $optval[0];
                    }
                // In all other cases, the last entry of a type wins
                } else {
                    $this->_conf[$optkey] = $optval[count($optval)-1];
                }
            }
        }
        
        // Set sort option depending on citetype
        if (!array_key_exists('sort',$options)) {
            switch ($this->_conf['citetype']) {
                case 'apa':
                    $this->_conf['sort'] = 'true';
                    break;
                case 'alpha':
                    $this->_conf['sort'] = 'true';
                    break;
                case 'authordate':
                    $this->_conf['sort'] = 'true';
                    break;
                case 'numeric':
                    $this->_conf['sort'] = 'false';
                    break;
                default:
                    $this->_conf['sort'] = 'false';
                    break;
            }
        }

        // If there are BibTeX files to load, load and parse them
        if (array_key_exists('file',$this->_conf)) {
            $this->_parseBibtexFile();
        }

    }
    
    /**
     * Internal function parsing the contents of the BibTeX file
     *
     * The result will be stored in $this->_bibtex_references
     */
    private function _parseBibtexFile() {
        $bibtex = '';
        // Load all files and concatenate their contents
        foreach($this->_conf['file'] as $file) {
            $bibtex .= $this->_loadBibtexFile($file, 'page');
        }
        $this->_parser = new bibtexparser_plugin_bibtex();
        $this->_parser->loadString($bibtex);
        $stat = $this->_parser->parse();

        if ( !$stat ) {
            return $stat;
        }

        $this->_bibtex_references = $this->_parser->data;
        
        foreach($this->_bibtex_references as $refno => $ref) {
            if (array_key_exists('cite',$ref)) {
                $this->_bibtex_keys[$ref['cite']] = $refno;
            }
        }
    }

    /**
     * Prints the reference corresponding to the given bibtex key
     *
     * The format of the reference can be freely configured using the
     * dokuwiki configuration interface (see files in conf dir)
     *
     * @param  string BibTeX key of the reference
     * @return string (HTML) formatted BibTeX reference
     */
    public function printReference($bibtex_key) {
        $ref = $this->_bibtex_references[$this->_bibtex_keys[$bibtex_key]];
        if (empty($ref)) {
            return;
        }
        // Get format string from plugin config
        $formatstring = $this->plugin->getConf('fmtstr_'.$ref['entrytype']);
        // Replace each language string ($this->_langStrings) with respective value
        foreach ($this->_langStrings as $lang) {
            $formatstring = str_replace(strtoupper($lang), $this->plugin->getLang($lang), $formatstring);
        }
        // Replace each field name with respective value
        foreach ($ref as $field => $value) {
            $formatstring = str_replace(strtoupper($field), $value, $formatstring);
        }
        // Handle case of no author, but editor
        $formatstring = str_replace('AUTHOR', $ref['editor'] . " (" . $this->plugin->getLang('editorabbrev') . ")", $formatstring);
        return $formatstring;
    }

    /**
     * Prints the whole bibliography
     *
     * @param  string substate (bibliography, furtherreading)
     * @return string (HTML) formatted bibliography
     */
    public function printBibliography($substate) {
        switch ($substate) {
            case 'bibliography':
                if (!isset($this->_bibtex_keysCited) || empty($this->_bibtex_keysCited)) {
                    return;
                }
				// If there are nocite entries
                if (isset($this->_bibtex_keysNotCited) && !empty($this->_bibtex_keysNotCited)) {
                	foreach ($this->_bibtex_keysNotCited as $key => $no) {
                		if (!array_key_exists($key,$this->_bibtex_keysCited)) {
	                		$this->_bibtex_keysCited[$key] = ++$this->_currentKeyNumber;
	                	}
	                }
                }
                if ('true' == $this->_conf['sort'] && 'numeric' != $this->_conf['citetype']) {
                    $citedKeys = array();
                    foreach ($this->_bibtex_keysCited as $key => $no) {
                        $citedKeys[$key] = $this->_bibtex_references[$this->_bibtex_keys[$key]]['authoryear'];
                    }
                    asort($citedKeys);
                } else {
                    $citedKeys = $this->_bibtex_keysCited;
                }
                if ('authordate' == $this->_conf['citetype']) {
                    $html = '<ul class="bibtex_references">' . DOKU_LF;
                    foreach ($citedKeys as $key => $no) {
                        $html .= '<li><div class="li" name="ref__' . $key . '" id="ref__'. $key . '">';
                        $html .= $this->printReference($key);
                        $html .= '</div></li>' . DOKU_LF;
                    }
                    $html .= '</ul>';
                } else {
                    $html = '<dl class="bibtex_references">' . DOKU_LF;
                    foreach ($citedKeys as $key => $no) {
                        $html .= '<dt name="ref__' . $key . '" id="ref__'. $key . '">[';
                        $html .= $this->printCitekey($key);
                        $html .= ']</dt>' . DOKU_LF;
                        $html .= '<dd>';
                        $html .= $this->printReference($key);
                        $html .= '</dd>' . DOKU_LF;
                    }
                    $html .= '</dl>';
                }
                return $html;
            case 'furtherreading':
                if (!isset($this->_bibtex_keysNotCited) || empty($this->_bibtex_keysNotCited)) {
                    return;
                }
                $this->_currentKeyNumber = 0;
                if ('true' == $this->_conf['sort']) {
                    $notcitedKeys = array();
                    foreach ($this->_bibtex_keysNotCited as $key => $no) {
                        $notcitedKeys[$key] = $this->_bibtex_references[$this->_bibtex_keys[$key]]['authoryear'];
                    }
                    asort($notcitedKeys);
                } else {
                    $notcitedKeys = $this->_bibtex_keysNotCited;
                }
                if ('authordate' == $this->_conf['citetype']) {
                    $html = '<ul class="bibtex_references">' . DOKU_LF;
                    foreach ($notcitedKeys as $key => $no) {
                        $html .= '<li><div class="li">';
                        $html .= $this->printReference($key);
                        $html .= '</div></li>' . DOKU_LF;
                    }
                    $html .= '</ul>';
                } else {
                    $html = '<dl class="bibtex_references">' . DOKU_LF;
                    foreach ($notcitedKeys as $key => $no) {
                        $html .= '<dt>[';
                        $html .= $this->printCitekey($key);
                        $html .= ']</dt>' . DOKU_LF;
                        $html .= '<dd>';
                        $html .= $this->printReference($key);
                        $html .= '</dd>' . DOKU_LF;
                    }
                    $html .= '</dl>';
                }
                return $html;
        }
    }

    /**
     * Return the cite key of the given reference for using in the text
     * The Output depends on the configuration via the variable
     * $this->_conf['citetype']
     * If the citetype is unknown, the bibtex key is returned
     *
     * @param string  bibtex key of the reference
     * @return string cite key of the reference (according to the citetype set)
     */
    public function printCitekey($bibtex_key) {
        // Check whether key exists
        if (empty($this->_bibtex_references[$this->_bibtex_keys[$bibtex_key]])) {
            return $bibtex_key;
        }
        if (!array_key_exists($bibtex_key,$this->_bibtex_keysCited)) {
            $this->_currentKeyNumber++;
            $this->_bibtex_keysCited[$bibtex_key] = $this->_currentKeyNumber;
        }
        $ref = $this->_bibtex_references[$this->_bibtex_keys[$bibtex_key]];
        switch ($this->_conf['citetype']) {
            case 'apa':
                $bibtex_key = $ref['authors'][0]['last'];
                if ($ref['authors'][0]['last'] == '') {
                  $bibtex_key = $ref['editors'][0]['last'];
                }
                $bibtex_key .= $ref['year'];
                break;
            case 'alpha':
                $bibtex_key = substr($ref['authors'][0]['last'],0,3);
                if ($ref['authors'][0]['last'] == '') {
                  $bibtex_key = substr($ref['editors'][0]['last'],0,3);
                }
                $bibtex_key .= substr($ref['year'],2,2);
                break;
            case 'authordate':
                $bibtex_key = $ref['authors'][0]['last'] . ", ";
                if ($ref['authors'][0]['last'] == '') {
                  $bibtex_key = $ref['editors'][0]['last'] . ", ";
                }
                $bibtex_key .= $ref['year'];
                break;
            case 'numeric':
                $bibtex_key = $this->_bibtex_keysCited[$bibtex_key];
                break;
            // If no known citation style is given - however, that should not happen
            default:
                $bibtex_key = $this->_bibtex_keysCited[$bibtex_key];
                break;
        }
        return $bibtex_key;
    }
    
    /**
     * Debug function to output the raw contents of the BibTeX file
     *
     * @return string raw BibTeX code
     */
    function rawOutput() {
        $bibtex = '';
        // Load all files and concatenate their contents
        foreach($this->_conf['file'] as $file) {
            $bibtex .= $this->_loadBibtexFile($file, 'page');
        }
        return $bibtex;
    }

    /**
     * Loads BibTeX code and returns the raw BibTeX as string
     *
     * @param string uri  BibTeX file (path or dokuwiki page)
     * @param string kind whether uri is a file or a dokuwiki page
     * @return string raw BibTeX code
     */
    function _loadBibtexFile($uri, $kind) {
        global $INFO;

        if ( $kind == 'file' ) {
            // FIXME: Adjust path - make it configurable
            return file_get_contents(dirname(__FILE__).'/'.$kind.'/'.$uri);
        }
        else if ( $kind == 'page' ) {
            $exists = false;
            resolve_pageid($INFO['namespace'], &$uri, &$exists);
            if ( $exists ) {
                return rawWiki($uri);
            }
        }

        return null;
    }

}

?>