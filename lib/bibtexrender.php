<?php
/**
 * DokuWiki Plugin bibtex4dw (BibTeX Renderer Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Till Biskup <till@till-biskup.de>
 * @version 0.4.0
 * @date    2023-05-28
 */

require_once(DOKU_PLUGIN.'bibtex4dw/lib/bibtexparser.php');

class bibtexrender_plugin_bibtex4dw {

    /**
     * Array containing all references to objects of this class that already exist.
     * 
     * Necessary to synchronize calls from the two different syntax plugins for the same page
     */
    public static $resources = array();

    /**
     * Handle to SQLite db
     */
    public static $sqlite = array();

    /**
     * Array containing local configuration of the object
     *
     * Options can be set by calling setOptions($options = array())
     */
    private $_conf = array(
        'sqlite' => false,
        'file' => array(),
        'citetype' => '',
        'sort' => false,
        'formatstring' => array(),
        );
    
    private $_langStrings = array(
        'pageabbrev',
        'pagesabbrev',
        'chapterabbrev',
        'editorabbrev',
        'mastersthesis',
        'phdthesis',
        'techreport',
        'unpublished'
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
        require_once(DOKU_PLUGIN.'bibtex4dw/syntax/bibtex.php');
        $this->plugin = new syntax_plugin_bibtex4dw_bibtex();

        // Transfer config settings from plugin config (via config manager) to local config
        // Note: Only those settings that can be changed by this class need to be transferred.
        // Therefore it is not necessary to transfer the format strings for the entries.
        $this->_conf['sqlite'] = $this->plugin->getConf('sqlite');
        $this->_conf['file'] = explode(';',$this->plugin->getConf('file'));
        $this->_conf['pdfdir'] = explode(';',$this->plugin->getConf('pdfdir'));
        $this->_conf['citetype'] = $this->plugin->getConf('citetype');

        // In case we shall use SQLite
        if ($this->_conf['sqlite']) {
            $this->sqlite = plugin_load('helper', 'sqlite');
            if(!$this->sqlite){
                msg('You asked for using the sqlite plugin but it is not installed. Please install it', -1);
                return;
            }
            // initialize the database connection
            if(!$this->sqlite->init('bibtex4dw', DOKU_PLUGIN.'bibtex4dw/db/')){
                return;
            }
        } else {
            // If there are files to load, load and parse them
            if (array_key_exists('file', $this->_conf)) {
                $this->_parseBibtexFile();
            }
        }
    }

    /**
     * Gets instance of the class by id
     *
     * @param pageid
     */
    public static function getResource($id = NULL) {
        // exit if given parameters not sufficient.
        if (is_null($id)) {
            return null;
        }
        if (!array_key_exists($id, self::$resources)) {
            $x = new bibtexrender_plugin_bibtex4dw($id);
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
        $this->_parser = new bibtexparser_plugin_bibtex4dw();
        $this->_parser->loadString($bibtex);
        $stat = $this->_parser->parseBibliography();
        if ( !$stat ) {
            return $stat;
        }
        //$this->_bibtex_references = $this->_parser->data;
        $this->_bibtex_references = $this->_parser->entries;

        foreach($this->_bibtex_references as $refno => $ref) {
            if (is_array($ref) && array_key_exists('cite', $ref)) {
                $this->_bibtex_keys[$ref['cite']] = $refno;
            }
        }
    }
    
    /**
     * Internal function adding the content to the SQLite database
     */
    public function addBibtexToSQLite($bibtex,$ID) {
        if (!$this->_conf['sqlite']) {
            return;
        }
        if (!in_array(':'.$ID, $this->_conf['file'])) {
            msg("Current page (:$ID) not configured to be a BibTeX DB, hence ignoring.
                Change in config if this is not intended.");
            return;
        }
        $this->_parser = new bibtexparser_plugin_bibtex4dw();
        $this->_parser->loadString($bibtex);
        $this->_parser->sqlite = $this->sqlite;
        $stat = $this->_parser->parseBibliography($sqlite=true);

        if ( !$stat ) {
            msg('Some problems with parsing BIBTeX code',-1);
        }

        if ( ($this->_parser->warnings['warning']) && (count($this->_parser->warnings['warning']))) {
            foreach($this->_parser->warnings as $parserWarning) {
                msg($this->_parser->warnings[$parserWarning]['warning'],'2');
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
        global $INFO;

        if ($this->_conf['sqlite']) {
            $this->_parser = new bibtexparser_plugin_bibtex4dw();
            $this->_parser->sqlite = $this->sqlite;
            $rawBibtexEntry = $this->sqlite->res2arr($this->sqlite->query("SELECT entry FROM bibtex WHERE key=?",$bibtex_key));
            $this->_parser->loadString($rawBibtexEntry[0]['entry']);
            $stat = $this->_parser->parse();
            if ( !$stat ) {
                return $stat;
            }
            $ref = $this->_parser->data[0];
        } else {
            //$ref = $this->_bibtex_references[$this->_bibtex_keys[$bibtex_key]];
            $rawBibtexEntry = $this->_bibtex_references[$bibtex_key];
            $this->_parser->loadString($rawBibtexEntry);
            $stat = $this->_parser->parse();
            if ( !$stat ) {
                return $stat;
            }
            $ref = $this->_parser->data[0];
        }
        if (empty($ref)) {
            return;
        }
        // Variant of $ref with normalized (i.e., all uppercase) field names
        $normalizedRef = [];
        foreach ($ref as $key => $value) {
            $normalizedRef[strtoupper($key)] = $value;
        }
        // Get format string from plugin config
        $formatstring = $this->plugin->getConf('fmtstr_'.$normalizedRef['ENTRYTYPE']);
        // Replace each language string ($this->_langStrings) pattern '@placeholder@' with respective value
        foreach ($this->_langStrings as $lang) {
            $formatstring = str_replace('@'.strtolower($lang).'@', $this->plugin->getLang($lang), $formatstring);
        }
        // Replace each field pattern '{...@FIELDNAME@...}' with respective value from bib data
        preg_match_all("#\{([^@]*)@([A-Z]+)@([^@]*)\}#U", $formatstring, $fieldsToBeReplaced, PREG_SET_ORDER);
        foreach ($fieldsToBeReplaced as $matchPair) {
            $partOfFormatstring = $matchPair[0];
            $priorToName = $matchPair[1];
            $fieldName = $matchPair[2];
            $afterName = $matchPair[3];
            if (empty($normalizedRef[$fieldName])) {
                $formatstring = str_replace($partOfFormatstring, "", $formatstring);
                continue;
            }
            $formattedPart = $priorToName;
            $formattedPart .= $normalizedRef[$fieldName];
            $formattedPart .= $afterName;
            $formatstring = str_replace($partOfFormatstring, $formattedPart, $formatstring);
        }
        // Handle PDF files
        // Check whether we have a directory for PDF files
        if (array_key_exists('pdfdir',$this->_conf)) {
            // Check whether we are logged in and have permissions to access the PDFs
            if ((auth_quickaclcheck($this->_conf['pdfdir'][0]) >= AUTH_READ) && 
                array_key_exists('name',$INFO['userinfo'])) {
                // do sth.
                $pdffilename = mediaFN($this->_conf['pdfdir'][0]) . "/" . $bibtex_key . ".pdf";
                if (file_exists($pdffilename)) {
                    resolve_mediaid($this->_conf['pdfdir'][0], $pdflinkname, $exists);
                    $formatstring .= '&nbsp;<a href="' . 
                    ml($pdflinkname) . "/" . $bibtex_key . ".pdf" . '">PDF</a>';
                }
            }
        }

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
                        if ($this->_conf['sqlite']) {
                            $this->_parser = new bibtexparser_plugin_bibtex4dw();
                            $this->_parser->sqlite = $this->sqlite;
                            $rawBibtexEntry = $this->sqlite->res2arr($this->sqlite->query("SELECT entry FROM bibtex WHERE key=?",$key));
                            $this->_parser->loadString($rawBibtexEntry[0]['entry']);
                            $stat = $this->_parser->parse();
                            if ( !$stat ) {
                                return $stat;
                            }
                            $citedKeys[$key] = $this->_parser->data[0]['authoryear'];
                        } else {
                            $citedKeys[$key] = $this->_bibtex_references[$this->_bibtex_keys[$key]]['authoryear'];
                        }
                    }
                    asort($citedKeys);
                } else {
                    $citedKeys = $this->_bibtex_keysCited;
                }
                if ('authordate' == $this->_conf['citetype']) {
                    $html = $this->_printReferencesAsUnorderedList($citedKeys);
                } else {
                    $html = $this->_printReferencesAsDefinitionlist($citedKeys);
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
                        if ($this->_conf['sqlite']) {
                            $this->_parser = new bibtexparser_plugin_bibtex4dw();
                            $this->_parser->sqlite = $this->sqlite;
                            $rawBibtexEntry = $this->sqlite->res2arr($this->sqlite->query("SELECT entry FROM bibtex WHERE key=?",$key));
                            $this->_parser->loadString($rawBibtexEntry[0]['entry']);
                            $stat = $this->_parser->parse();
                            if ( !$stat ) {
                                return $stat;
                            }
                            $notcitedKeys[$key] = $this->_parser->data[0]['authoryear'];
                        } else {
                            $notcitedKeys[$key] = $this->_bibtex_references[$this->_bibtex_keys[$key]]['authoryear'];
                        }
                    }
                    asort($notcitedKeys);
                } else {
                    $notcitedKeys = $this->_bibtex_keysNotCited;
                }
                if ('authordate' == $this->_conf['citetype']) {
                    $html = $this->_printReferencesAsUnorderedList($notcitedKeys);
                } else {
                    $html = $this->_printReferencesAsDefinitionlist($notcitedKeys);
                }
                return $html;
        }
    }

    /**
     * Print references as unordered list
     *
     * Currently used only for "authordate" citation style
     *
     * @param array List of keys bibliography should be generated for
     * @return string rendered HTML of bibliography
     */
    function _printReferencesAsUnorderedList($citedKeys) {
        $html = '<ul class="bibtex_references">' . DOKU_LF;
        foreach ($citedKeys as $key => $no) {
            if ($this->keyExists($key)) {
                $html .= '<li><div class="li" name="ref__' . $key . '" id="ref__'. $key . '">';
                $html .= $this->printReference($key);
                $html .= '</div></li>' . DOKU_LF;
            } else {
                msg("BibTeX key '$key' could not be found. Possible typo?");
            }
        }
        $html .= '</ul>';
        return $html;
    }

    /**
     * Print references as definitionlist
     *
     * Currently used for all citation styles except "authordate"
     *
     * @param array List of keys bibliography should be generated for
     * @return string rendered HTML of bibliography
     */
    function _printReferencesAsDefinitionlist($citedKeys) {
        $html = '<dl class="bibtex_references">' . DOKU_LF;
        foreach ($citedKeys as $key => $no) {
            if ($this->keyExists($key)) {
                $html .= '<dt>[';
                $html .= $this->printCitekey($key);
                $html .= ']</dt>' . DOKU_LF;
                $html .= '<dd>';
                $html .= $this->printReference($key);
                $html .= '</dd>' . DOKU_LF;
            } else {
                msg("BibTeX key '$key' could not be found. Possible typo?");
            }
        }
        $html .= '</dl>';
        return $html;
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
        if (!array_key_exists($bibtex_key,$this->_bibtex_keysCited)) {
            $this->_currentKeyNumber++;
            $this->_bibtex_keysCited[$bibtex_key] = $this->_currentKeyNumber;
        }
        if ($this->_conf['sqlite']) {
            $this->_parser = new bibtexparser_plugin_bibtex4dw();
            $rawBibtexEntry = $this->sqlite->res2arr($this->sqlite->query("SELECT entry FROM bibtex WHERE key=?",$bibtex_key));
            if (empty($rawBibtexEntry)) {
                return $bibtex_key;
            }
            $this->_parser->loadString($rawBibtexEntry[0]['entry']);
            $stat = $this->_parser->parse();
            if ( !$stat ) {
                return $stat;
            }
            $ref = $this->_parser->data[0];
        } else {
            // Check whether key exists
            if (empty($this->_bibtex_references[$bibtex_key])) {
                return $bibtex_key;
            }
            $ref = $this->_bibtex_references[$this->_bibtex_keys[$bibtex_key]];
        }
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
     * Check if given key exists in currently used BibTeX database
     *
     * @param string  bibtex key of the reference
     * @return Boolean value
     */
    function keyExists($bibkey) {
        if ($this->_conf['sqlite']) {
            $rawBibtexEntry = $this->sqlite->res2arr($this->sqlite->query("SELECT entry FROM bibtex WHERE key=?",$bibkey));
            return (!empty($rawBibtexEntry));
        } else {
            return (!empty($this->_bibtex_references[$bibkey]));
        }
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
        } elseif ($kind == 'page') {
            $exists = false;
            resolve_pageid($INFO['namespace'], $uri, $exists);
            if ( $exists ) {
                return rawWiki($uri);
            }
        }

        return null;
    }

}

?>
