<?php
/**
 * DokuWiki Plugin bibtex4dw (BibTeX Parser Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Till Biskup <till@till-biskup.de>
 * @version 0.2
 * @date    2023-05-28
 */
 
/**
 * This class is based originally on the PHP PEAR package 
 * Structures_BibTeX, (c) 1997-2005 The PHP Group, Elmar Pitschke
 * For more information about the original PEAR package, please visit
 * http://pear.php.net/package/Structures_BibTex
 *
 * Some additional modifications to the original PHP PEAR package have
 * been made by Raphael Reitzig in 2010 for his bib2tpl program.
 * For more information about the bib2tpl program, please visit
 * http://lmazy.verrech.net/bib2tpl/
 *
 * During transition from the original PHP PEAR package to this class forming
 * part of the Dokuwiki Plugin bibtex, several unneccessary functions as the
 * output to HTML and RTF have been removed, as well as the dependency on PEAR.
 * 
 * Other functions as handling of BibTeX's @STRING patterns and a basic
 * parsing for LaTeX code common for BibTeX entries (i.e. \emph{}) have been added.
 *
 * This class is no longer PHP 4 compatible, as was the original PEAR package.
 */
 
class bibtexparser_plugin_bibtex4dw
{
    /**
     * Handle to SQLite db
     */
    public static $sqlite = array();
    /**
     * Array with the BibTex Data
     *
     * @access public
     * @var array
     */
    public $data = array();
    /**
     * String with the BibTex content
     *
     * @access public
     * @var string
     */
    public $content;
    /**
     * Array with the BibTex Strings
     *
     * @access private
     * @var array
     */
    private $_strings = array();
    /**
     * Array with the BibTex entries
     *
     * @access public
     * @var array
     */
    public $entries = array();
    /**
     * Array with possible Delimiters for the entries
     *
     * @access private
     * @var array
     */
    private $_delimiters;
    /**
     * Array with replacements for LaTeX commands in fields of entries
     * 
     * The patterns are searched for only in LaTeX math mode ($...$)
     *
     * As the output is in HTML, the best is to use the named representatives
     * of the respective signs.
     *
     * @access private
     * @var array
     */
    private $_latexMathmodeReplacements = array(
        '\to' => '&rarr;',
        '\bullet' => '&bull;',
        '\circ' => '&deg;',
        '\varepsilon' => '&epsilon;',
        '\vartheta' => '&thetasym;',
        '\varpi' => '&piv;',
        '\varrho' => '&rho;',
        '\varsigma' => '&sigmaf;',
        '\varphi' => '&phi;',
        '\cdot' => '&middot;',
        '\cdots' => '&middot;&middot;&middot;',
        '\rm ' => ''
    );
    /**
     * Array with Greek letters to replace the LaTeX commands in fields of entries
     * 
     * The greek letters are searched for only in LaTeX math mode ($...$)
     *
     * They will be checked both for lower and upper letters, as these differ only
     * in the first character of their respective name.
     *
     * Note: The LaTeX mathmode replacements (see above) will be done first, thus
     *       it is possible to use that to deal with special greek characters as 
     *       \varepsilon.
     *
     * @access private
     * @var array
     */
    private $_greekLetters = array(
        'alpha','beta','gamma','delta','epsilon',
        'zeta','eta','theta','iota','kappa',
        'lambda','mu','nu','xi','omicron',
        'pi','rho','sigma','tau','upsilon',
        'phi','chi','psi','omega',
    );
    /**
     * Array to store warnings
     *
     * @access public
     * @var array
     */
    public $warnings = array();
    /**
     * Run-time configuration options
     *
     * @access private
     * @var array
     */
    private $_options;
    /**
     * Array with the "allowed" entry types
     *
     * @access public
     * @var array
     */
    public $allowedEntryTypes;
    /**
     * Author Format Strings
     *
     * @access public
     * @var string
     */
    public $authorstring;

    /**
     * List of SQL statements to be inserted at once
     *
     * @access private
     * @var array
     */
    private $_sqlStatements = array();

    /**
     * Constructor
     *
     * @access public
     * @return void
     */
    function __construct($options = array())
    {
        $this->_delimiters     = array('"'=>'"',
                                        '{'=>'}');
        $this->data            = array();
        $this->content         = '';
        //$this->_stripDelimiter = $stripDel;
        //$this->_validate       = $val;
        $this->warnings        = array();
        $this->_options        = array(
            'replaceLatex'      => true,
            'stripDelimiter'    => true,
            'validate'          => true,
            'unwrap'            => false,
            'wordWrapWidth'     => false,
            'wordWrapBreak'     => "\n",
            'wordWrapCut'       => 0,
            'removeCurlyBraces' => true,
            'extractAuthors'    => true,
        );
        foreach ($options as $option => $value) {
            $test = $this->setOption($option, $value);
        }
        $this->allowedEntryTypes = array(
            'article',
            'book',
            'booklet',
            'conference',
            'inbook',
            'incollection',
            'inproceedings',
            'manual',
            'mastersthesis',
            'misc',
            'phdthesis',
            'proceedings',
            'techreport',
            'unpublished'
        );
        $this->authorstring = 'VON LAST, JR, FIRST';
        $this->authordelimiter = '; ';
    }

    /**
     * Sets run-time configuration options
     *
     * @access public
     * @param string $option option name
     * @param mixed  $value value for the option
     * @return mixed true on success (DW msg on failure)
     */
    public function setOption($option, $value)
    {
        $ret = true;
        if (array_key_exists($option, $this->_options)) {
            $this->_options[$option] = $value;
        } else {
            msg("Unknown option $option", 2);
            $ret = false;
        }
        return $ret;
    }

    /**
     * Reads a given BibTex File
     *
     * @access public
     * @param string $filename Name of the file
     * @return mixed true on success (DW msg on failure)
     */
    public function loadFile($filename)
    {
        if (file_exists($filename)) {
            if (($this->content = @file_get_contents($filename)) === false) {
                msg("Could not open file $filename", 2);
            } else {
                $this->_pos    = 0;
                $this->_oldpos = 0;
                return true;
            }
        } else {
            msg("Could not find file $filename", 2);
        }
    }

    /**
     * Reads bibtex from a string variable
     *
     * @access public
     * @param string $bib String containing bibtex
     * @return boolean true
     */
    public function loadString($bib)
    {
        $this->content = $bib;
        $this->_pos    = 0;
        $this->_oldpos = 0;
        return true; // For compatibility with loadFile
    }

    /**
     * Parse bibliography stored in content and clear the content if the parsing is successful.
     *
     * @access public
     * @return boolean true on success and PEAR_Error if there was a problem
     */
    public function parseBibliography($sqlite = false)
    {
        //The amount of opening braces is compared to the amount of closing braces
        //Braces inside comments are ignored
        $this->warnings = array();
        $this->data     = array();
        $valid          = true;
        $open           = 0;
        $entry          = false;
        $char           = '';
        $lastchar       = '';
        $buffer         = '';
        $inField        = false;
        $openInField    = 0;
        $lastNonWsChar  = '';
        for ($i = 0; $i < strlen($this->content); $i++) {
            $char = substr($this->content, $i, 1);
            if ((0 != $open) && ('@' == $char) && (!$inField)) {
                if (!$this->_checkAt($buffer)) {
                    $this->_generateWarning('WARNING_MISSING_END_BRACE', '', $buffer);
                    //To correct the data we need to insert a closing brace
                    $char     = '}';
                    $i--;
                }
            }
            if ((0 == $open) && ('@' == $char)) { //The beginning of an entry
                $entry = true;
            } elseif ($entry && ('{' == $char) && ('\\' != $lastchar)) { //Inside an entry and non quoted brace is opening
                $open++;
                if (!$inField && ($lastNonWsChar == '=')) {
                    $inField = true;
                } elseif ($inField) {
                    $openInField++;
                }
            } elseif ($entry && ('}' == $char) && ('\\' != $lastchar)) { //Inside an entry and non quoted brace is closing
                $open--;
                if ($inField) {
                    $openInField--;
                    if ($openInField == 0) {
                        $inField = false;
                    }
                }
                if ($open < 0) { //More are closed than opened
                    $valid = false;
                }
                if (0 == $open) { //End of entry
                    $entry = false;
                    // TODO: Some check for duplicate keys and issuing a warning if so?
                    if ($sqlite) {
                        $this->_createInsertStatementForSQLiteDB($buffer);
                    } else {
                        $this->_splitBibTeXEntry($buffer);
                    }
                    $buffer = '';
                }
            }
            if ($entry) { //Inside entry
                $buffer .= $char;
            }
            $lastchar = $char;
            if ($char != ' ' && $char != '\t' && $char != '\n' && $char != '\r') {
                $lastNonWsChar = $char;
            }
        }
        if ($sqlite) {
            $this->_issueSQLStatements();
        }
        //If open is one it may be possible that the last ending brace is missing
        // TODO: Handle situation with using SQLite DB
        if (1 == $open) {
            $entrydata = $this->_parseEntry($buffer);
            if (!$entrydata) {
                $valid = false;
            } else {
                $this->data[] = $entrydata;
                $buffer = '';
                $open   = 0;
            }
        }
        //At this point the open should be zero
        if (0 != $open) {
            $valid = false;
        }
        //Are there multiple entries with the same cite?
        // TODO: Meanwhile, as in both cases (SQLite and manual) bibtex keys are used as index,
        //       this situation shall no longer exist. Checking for duplicate keys needs be done above.
        if ($this->_options['validate']) {
            $cites = array();
            foreach ($this->data as $entry) {
                $cites[] = $entry['cite'];
            }
            $unique = array_unique($cites);
            if (sizeof($cites) != sizeof($unique)) { //Some values have not been unique!
                $notuniques = array();
                for ($i = 0; $i < sizeof($cites); $i++) {
                    if ('' == $unique[$i]) {
                        $notuniques[] = $cites[$i];
                    }
                }
                $this->_generateWarning('WARNING_MULTIPLE_ENTRIES', implode(',',$notuniques));
            }
        }
        if ($valid) {
            $this->content = '';
            return true;
        } else {
            return false;
        }
    }

    /**
     * Parses what is stored in content and clears the content if the parsing is successful.
     *
     * @access public
     * @return boolean true on success and PEAR_Error if there was a problem
     */
    public function parse($sqlite = false)
    {
        //The amount of opening braces is compared to the amount of closing braces
        //Braces inside comments are ignored
        $this->warnings = array();
        $this->data     = array();
        $valid          = true;
        $open           = 0;
        $entry          = false;
        $char           = '';
        $lastchar       = '';
        $buffer         = '';
        for ($i = 0; $i < strlen($this->content); $i++) {
            $char = substr($this->content, $i, 1);
            if ((0 != $open) && ('@' == $char)) {
                if (!$this->_checkAt($buffer)) {
                    $this->_generateWarning('WARNING_MISSING_END_BRACE', '', $buffer);
                    //To correct the data we need to insert a closing brace
                    $char     = '}';
                    $i--;
                }
            }
            if ((0 == $open) && ('@' == $char)) { //The beginning of an entry
                $entry = true;
            } elseif ($entry && ('{' == $char) && ('\\' != $lastchar)) { //Inside an entry and non quoted brace is opening
                $open++;
            } elseif ($entry && ('}' == $char) && ('\\' != $lastchar)) { //Inside an entry and non quoted brace is closing
                $open--;
                if ($open < 0) { //More are closed than opened
                    $valid = false;
                }
                if (0 == $open) { //End of entry
                    $entry     = false;
                    if ($sqlite) {
                        //$this->_addEntryToSQLiteDB($buffer);
                        $this->_createInsertStatementForSQLiteDB($buffer);
                    } else {
                        $entrydata = $this->_parseEntry($buffer);
                        if ($entrydata) {
                            $this->data[] = $entrydata;
                        }
                    }
                    $buffer = '';
                }
            }
            if ($entry) { //Inside entry
                $buffer .= $char;
            }
            $lastchar = $char;
        }
        if ($sqlite) {
            $this->_issueSQLStatements();
        }
        //If open is one it may be possible that the last ending brace is missing
        // TODO: Handle situation with using SQLite DB
        if (1 == $open) {
            $entrydata = $this->_parseEntry($buffer);
            if (!$entrydata) {
                $valid = false;
            } else {
                $this->data[] = $entrydata;
                $buffer = '';
                $open   = 0;
            }
        }
        //At this point the open should be zero
        if (0 != $open) {
            $valid = false;
        }
        //Are there multiple entries with the same cite?
        if ($this->_options['validate']) {
            $cites = array();
            foreach ($this->data as $entry) {
                $cites[] = $entry['cite'];
            }
            $unique = array_unique($cites);
            if (sizeof($cites) != sizeof($unique)) { //Some values have not been unique!
                $notuniques = array();
                for ($i = 0; $i < sizeof($cites); $i++) {
                    if ('' == $unique[$i]) {
                        $notuniques[] = $cites[$i];
                    }
                }
                $this->_generateWarning('WARNING_MULTIPLE_ENTRIES', implode(',',$notuniques));
            }
        }
        if ($valid) {
            $this->content = '';
            return true;
        } else {
            return false;
        }
    }

    /**
     * Split entry in key and actual contents
     */
    private function _splitBibTeXEntry($entry)
    {
        $key = '';
        if ('@string' ==  strtolower(substr($entry, 0, 7))) {
            $matches = array();
            preg_match('/^@\w+\{(.+)/', $entry, $matches);
            if (count($matches) > 0) {
                $m = explode('=', $matches[1], 2);
                $string = trim($m[0]);
                $entry = substr(trim($m[1]), 1, -1);
                $this->_strings[$string] = $entry;
            }
        } else {
            $entry = $entry.'}';
            // Look for key
            $matches = array();
            preg_match('/^@(\w+)\{(.+),/', $entry, $matches);
            if (count($matches) > 0) {
                $entryType = $matches[1];
                $key = $matches[2];
                $this->entries[$key] = $entry;
            }
        }
    }

    /**
     * Create insert statement for SQLite DB
     */
    private function _createInsertStatementForSQLiteDB($entry)
    {
        $key = '';
        if ('@string' ==  strtolower(substr($entry, 0, 7))) {
            $matches = array();
            preg_match('/^@\w+\{(.+)/', $entry, $matches);
            if (count($matches) > 0)
            {
                $m = explode('=', $matches[1], 2);
                $string = trim($m[0]);
                $entry = substr(trim($m[1]), 1, -1);
                //$statement = "INSERT OR REPLACE INTO strings (string, entry) VALUES ('$string','$entry')";
                $this->sqlite->query("INSERT OR REPLACE INTO strings (string, entry) VALUES (?, ?)", $string, $entry);
                //$this->_sqlStatements[] = $statement;
            }
        } else {
            $entry = $entry.'}';
            // Look for key
            $matches = array();
            preg_match('/^@(\w+)\{(.+),/', $entry, $matches);
            if (count($matches) > 0)
            {
                $entryType = $matches[1];
                $key = $matches[2];
                $quoted_entry = $this->sqlite->escape_string($entry);
                $statement = "INSERT OR REPLACE INTO bibtex (key, entry) VALUES ('$key','$quoted_entry')";
                $this->_sqlStatements[] = $statement;
            }
        }
    }

    /**
     * Perform a series of statements in one transaction in SQLite DB
     *
     * Performing a series of statements in one transaction instead of
     * performing repeated SQL queries saves a tremendous amount of time.
     * TODO: Check whether this actually results in a single transaction.
     */
    private function _issueSQLStatements()
    {
        array_unshift($this->_sqlStatements, "BEGIN TRANSACTION");
        $this->_sqlStatements[] = "COMMIT;";
        foreach ($this->_sqlStatements as $sqlStatement) {
            $this->sqlite->query("$sqlStatement;");
        }
        $this->_sqlStatements = [];
    }

    /**
     * Add entry to SQLite DB
     */
    private function _addEntryToSQLiteDB($entry)
    {
        $key = '';
        if ('@string' ==  strtolower(substr($entry, 0, 7))) {
            $matches = array();
            preg_match('/^@\w+\{(.+)/', $entry, $matches);
            if (count($matches) > 0)
            {
                $m = explode('=', $matches[1], 2);
                $string = trim($m[0]);
                $entry = substr(trim($m[1]), 1, -1);
                $this->sqlite->query("INSERT OR REPLACE INTO strings (string, entry) VALUES (?,?)", $string, $entry);
            }
        } else {
            $entry = $entry.'}';
            // Look for key
            $matches = array();
            preg_match('/^@(\w+)\{(.+),/', $entry, $matches);
            if (count($matches) > 0)
            {
                $entryType = $matches[1];
                $key = $matches[2];
                $this->sqlite->query("INSERT OR REPLACE INTO bibtex (key, entry) VALUES (?,?)", $key, $entry);
            }
        }
    }

    /**
     * Extracting the data of one bibtex entry
     *
     * The parse function splits the content into its entries.
     * Then every entry is parsed by this function.
     * It parses the entry backwards.
     * First the last '=' is searched and the value extracted from that.
     * A copy is made of the entry if warnings should be generated. This takes quite
     * some memory but it is needed to get good warnings. If no warnings are generated
     * then you don't have to worry about memory.
     * Then the last ',' is searched and the field extracted from that.
     * Again the entry is shortened.
     * Finally after all field=>value pairs the cite and type is extraced and the
     * authors are splitted.
     * If there is a problem false is returned.
     *
     * @access private
     * @param string $entry The entry
     * @return array The representation of the entry or false if there is a problem
     */
    private function _parseEntry($entry)
    {
        $entrycopy = '';
        if ($this->_options['validate']) {
            $entrycopy = $entry; //We need a copy for printing the warnings
        }
        $ret = array('bibtex' => $entry.'}');
        if ('@string' ==  strtolower(substr($entry, 0, 7))) {
            $matches = array();
            preg_match('/^@\w+\{(.+)/' ,$entry, $matches);
            if ( count($matches) > 0 )
            {
                $m = explode('=',$matches[1],2);
                $this->_strings[trim($m[0])] = substr(trim($m[1]),1,-1);
            }
        } elseif ('@preamble' ==  strtolower(substr($entry, 0, 9))) {
            //Preamble not yet supported!
            if ($this->_options['validate']) {
                $this->_generateWarning('PREAMBLE_ENTRY_NOT_YET_SUPPORTED', '', $entry.'}');
            }
        } else {
            // Look for key
            $matches = array();
            preg_match('/^@\w+\{([\w\d]+),/' ,$entry, $matches);
            if ( count($matches) > 0 )
            {
              $ret['entrykey'] = $matches[1];
            }

            //Parsing all fields
            while (strrpos($entry,'=') !== false) {
                $position = strrpos($entry, '=');
                //Checking that the equal sign is not quoted or is not inside a equation (For example in an abstract)
                $proceed  = true;
                if (substr($entry, $position-1, 1) == '\\') {
                    $proceed = false;
                }
                if ($proceed) {
                    $proceed = $this->_checkEqualSign($entry, $position);
                }
                while (!$proceed) {
                    $substring = substr($entry, 0, $position);
                    $position  = strrpos($substring,'=');
                    $proceed   = true;
                    if (substr($entry, $position-1, 1) == '\\') {
                        $proceed = false;
                    }
                    if ($proceed) {
                        $proceed = $this->_checkEqualSign($entry, $position);
                    }
                }

                $value = trim(substr($entry, $position+1));
                $entry = substr($entry, 0, $position);

                if (',' == substr($value, strlen($value)-1, 1)) {
                    $value = substr($value, 0, -1);
                }
                if ($this->_options['validate']) {
                    $this->_validateValue($value, $entrycopy);
                }

                // Handle string replacements
                // IMPORTANT: Must precede stripDelimiter call
                if (!in_array(substr($value,0,1),array_keys($this->_delimiters))) {
                      if (!empty($this->sqlite)) {
                        $stringReplacement = $this->sqlite->res2arr($this->sqlite->query("SELECT entry FROM strings WHERE string = ?",$value));
                        if (empty($stringReplacement)) {
                            $value = '';
                        } else {
                            $value = $stringReplacement[0]['entry'];
                        }
                    } elseif (array_key_exists($value,$this->_strings)) {
                        $value = $this->_strings[$value];
                    }
                }

                if ($this->_options['replaceLatex']) {
                    $value = $this->_replaceLatex($value);
                }

                if ($this->_options['stripDelimiter']) {
                    $value = $this->_stripDelimiter($value);
                }
                if ($this->_options['unwrap']) {
                    $value = $this->_unwrap($value);
                }
                if ($this->_options['removeCurlyBraces']) {
                    $value = $this->_removeCurlyBraces($value);
                }

                $position    = strrpos($entry, ',');
                $field       = strtolower(trim(substr($entry, $position+1)));
                $ret[$field] = $value;
                $entry       = substr($entry, 0, $position);
            }
            //Parsing cite and entry type
            $arr = explode('{', $entry);
            $ret['cite'] = trim($arr[1]);
            $ret['entrytype'] = strtolower(trim($arr[0]));
            if ('@' == $ret['entrytype'][0]) {
                $ret['entrytype'] = substr($ret['entrytype'], 1);
            }
            if ($this->_options['validate']) {
                if (!$this->_checkAllowedEntryType($ret['entrytype'])) {
                    $this->_generateWarning('WARNING_NOT_ALLOWED_ENTRY_TYPE', $ret['entrytype'], $entry.'}');
                }
            }
            //Handling the authors
            if (in_array('author', array_keys($ret)) && $this->_options['extractAuthors']) {
                // Array with all the authors in $ret['authors']
                $ret['authors'] = $this->_extractAuthors($ret['author']);
                // AuthorYear for sorting purposes in $ref['authoryear']
                if (empty($ret['year'])) {
                    if (!empty($ret['date']) && preg_match('|(\d\d\d\d).*|U', $ret['date'], $matches)) {
                        $ret['year'] = $matches[1];
                    } else {
                        $ret['year'] = '[n.d.]';
                    }
                }
                $ret['authoryear'] = $ret['authors'][0]['last'] . $ret['year'];
                // Nicely formatted authors list in $ret['author']
                $tmparray = array();
                foreach ($ret['authors'] as $authorentry) {
                    $tmparray[] = $this->_formatAuthor($authorentry);
                }
                $ret['author'] = implode($this->authordelimiter, $tmparray);
            }
            //Handling the editors
            if (in_array('editor', array_keys($ret)) && $this->_options['extractAuthors']) {
                // Array with all the editors in $ret['editors']
                $ret['editors'] = $this->_extractAuthors($ret['editor']);
                // Nicely formatted authors list in $ret['editor']
                $tmparray = array();
                foreach ($ret['editors'] as $editorentry) {
                    $tmparray[] = $this->_formatAuthor($editorentry);
                }
                $ret['editor'] = implode($this->authordelimiter, $tmparray);
            }
        }
        return $ret;
    }
    
    /**
     * Parsing for a subset of LaTeX code that can be found more often in BibTeX entries
     *
     * TODO: Extend this as necessary
     */
    private function _replaceLatex($entry) {
        // \emph{...} -> <em>...</em>
        $entry = preg_replace('/\\\emph\{([^\}]+)\}/', '<em>$1</em>', $entry);
        // \textbf{...} -> <strong>...</strong>
        $entry = preg_replace('/\\\textbf\{([^\}]+)\}/', '<strong>$1</strong>', $entry);
        // quotation marks
        $entry = str_replace("``","&quot;",$entry);
        $entry = str_replace("''","&quot;",$entry);
        // \& -> &amp;
        $entry = str_replace("\&","&amp;",$entry);
        // \% -> %;
        $entry = str_replace("\%","%;",$entry);
        // "\ " -> " ";
        $entry = str_replace("\ "," ",$entry);
        // --- -> &mdash;
        $entry = str_replace("---","&mdash;",$entry);
        // -- -> -
        $entry = str_replace("--","-",$entry);
        // \url{...} -> ...
        $entry = preg_replace("/\\\url\{([^\}]+)\}/",'<a href="\\1">\\1</a>',$entry);
        // Handle umlauts
        $entry = preg_replace('/\\\"\{([aeiouyAEIOU])\}/',"&\\1uml;",$entry);
        $entry = preg_replace('/\\\"([aeiouyAEIOU])/',"&\\1uml;",$entry);
        $entry = str_replace("\ss","&szlig;",$entry);
        $entry = str_replace('"s',"&szlig;",$entry);
        // Handle accents
        // Handle acute
        $entry = str_replace("\'c","&#x107;",$entry);
        $entry = preg_replace("/\\\'(.?)/","&\\1acute;",$entry);
        // Handle grave
        $entry = preg_replace("/\\\`(.?)/","&\\1grave;",$entry);
        // Handle circumflex
        $entry = preg_replace("/\\\(\^)(.?)/","&\\2circ;",$entry);
        // Handle hatschek
        $entry = str_replace('\v{z}',"&#x17E;",$entry);
        $entry = str_replace('\v{c}',"&#x10D;",$entry);
        // Handle cedille
        $entry = preg_replace("/\\\c\{(.?)\}/","\\1&#x0327;",$entry);
        // Handle tilde
        $entry = preg_replace("/\\\~(.?)/","&\\1tilde;",$entry);
        // ae and oe ligatures
        $entry = preg_replace('/\\\([aoAO]{1}[eE]{1})/',"&\\1lig;",$entry);
        // Handle i without dot
        $entry = str_replace("\i","&#305;",$entry);
        // Handle u with bar
        $entry = str_replace("\={u}","&#363;",$entry);
        // Handle \l and \L 
        $entry = str_replace("\l","&#322;",$entry);
        $entry = str_replace("\L","&#321;",$entry);
 
        // \o and \O
        $entry = preg_replace('/\\\([oO]{1})/',"&\\1slash;",$entry);        
        // \aa and \AA
        $entry = preg_replace('/\\\([aA]{1})([aA]{1})/',"&\\1ring;",$entry);      
        // Replace remaining "~" with "&nbsp;"
        $entry = str_replace("~","&nbsp;",$entry);
        // Handle math ($...$)
        preg_match('/\$([^$]+)\$/' ,$entry, $matches);
        if ( count($matches) > 0 ) {
            foreach ($matches as $match) {
                // Fix superscript and subscript
                $entry = preg_replace("/\^\{([^\}]+)\}/","<sup>\\1</sup>",$entry);
                $entry = preg_replace("/_\{([^\}]+)\}/","<sub>\\1</sub>",$entry);
                $entry = preg_replace("/\^([\\\]{1}\w+)/","<sup>\\1</sup>",$entry);
                $entry = preg_replace("/_([\\\]{1}\w+)/","<sub>\\1</sub>",$entry);
                $entry = preg_replace("/\^([^\\\]{1})/","<sup>\\1</sup>",$entry);
                $entry = preg_replace("/_([^\\\]{1})/","<sub>\\1</sub>",$entry);
                // Replace LaTeX math commands, e.g. "\to"
                foreach ($this->_latexMathmodeReplacements as $orig => $repl) {
                    $entry = str_replace($orig,$repl,$entry);
                }
                // Replace both lowercase and uppercase Greek letters
                foreach ($this->_greekLetters as $letter) {
                    $upLatex = '\\' . ucfirst($letter);
                    $upHtml = "&" . ucfirst($letter) . ";";
                    $loLatex = '\\' . $letter;
                    $loHtml = "&" . $letter . ";";
                    $entry = str_replace($upLatex,$upHtml,$entry);
                    $entry = str_replace($loLatex,$loHtml,$entry);
                }
            }
            // Finally, remove the LaTeX mathmode $ delimiters
            $entry = str_replace("$","",$entry);
        }
        return $entry;
    }

    /**
     * Checking whether the position of the '=' is correct
     *
     * Sometimes there is a problem if a '=' is used inside an entry (for example abstract).
     * This method checks if the '=' is outside braces then the '=' is correct and true is returned.
     * If the '=' is inside braces it contains to a equation and therefore false is returned.
     *
     * @access private
     * @param string $entry The text of the whole remaining entry
     * @param int the current used place of the '='
     * @return bool true if the '=' is correct, false if it contains to an equation
     */
    private function _checkEqualSign($entry, $position)
    {
        $ret = true;
        //This is getting tricky
        //We check the string backwards until the position and count the closing an opening braces
        //If we reach the position the amount of opening and closing braces should be equal
        $length = strlen($entry);
        $open   = 0;
        for ($i = $length-1; $i >= $position; $i--) {
            $precedingchar = substr($entry, $i-1, 1);
            $char          = substr($entry, $i, 1);
            if (('{' == $char) && ('\\' != $precedingchar)) {
                $open++;
            }
            if (('}' == $char) && ('\\' != $precedingchar)) {
                $open--;
            }
        }
        if (0 != $open) {
            $ret = false;
        }
        //There is still the posibility that the entry is delimited by double quotes.
        //Then it is possible that the braces are equal even if the '=' is in an equation.
        if ($ret) {
            $entrycopy = trim($entry);
            $lastchar  = $entrycopy[strlen($entrycopy)-1];
            if (',' == $lastchar) {
                $lastchar = $entrycopy[strlen($entrycopy)-2];
            }
            if ('"' == $lastchar) {
                //The return value is set to false
                //If we find the closing " before the '=' it is set to true again.
                //Remember we begin to search the entry backwards so the " has to show up twice - ending and beginning delimiter
                $ret = false;
                $found = 0;
                for ($i = $length; $i >= $position; $i--) {
                    $precedingchar = substr($entry, $i-1, 1);
                    $char          = substr($entry, $i, 1);
                    if (('"' == $char) && ('\\' != $precedingchar)) {
                        $found++;
                    }
                    if (2 == $found) {
                        $ret = true;
                        break;
                    }
                }
            }
        }
        return $ret;
    }

    /**
     * Checking if the entry type is allowed
     *
     * @access private
     * @param string $entry The entry to check
     * @return bool true if allowed, false otherwise
     */
    private function _checkAllowedEntryType($entry)
    {
        return in_array($entry, $this->allowedEntryTypes);
    }

    /**
     * Checking whether an at is outside an entry
     *
     * Sometimes an entry misses an entry brace. Then the at of the next entry seems to be
     * inside an entry. This is checked here. When it is most likely that the at is an opening
     * at of the next entry this method returns true.
     *
     * @access private
     * @param string $entry The text of the entry until the at
     * @return bool true if the at is correct, false if the at is likely to begin the next entry.
     */
    private function _checkAt($entry)
    {
        $ret     = false;
        $opening = array_keys($this->_delimiters);
        $closing = array_values($this->_delimiters);
        //Getting the value (at is only allowd in values)
        if (strrpos($entry,'=') !== false) {
            $position = strrpos($entry, '=');
            $proceed  = true;
            if (substr($entry, $position-1, 1) == '\\') {
                $proceed = false;
            }
            while (!$proceed) {
                $substring = substr($entry, 0, $position);
                $position  = strrpos($substring,'=');
                $proceed   = true;
                if (substr($entry, $position-1, 1) == '\\') {
                    $proceed = false;
                }
            }
            $value    = trim(substr($entry, $position+1));
            $open     = 0;
            $char     = '';
            $lastchar = '';
            for ($i = 0; $i < strlen($value); $i++) {
                $char = substr($this->content, $i, 1);
                if (in_array($char, $opening) && ('\\' != $lastchar)) {
                    $open++;
                } elseif (in_array($char, $closing) && ('\\' != $lastchar)) {
                    $open--;
                }
                $lastchar = $char;
            }
            //if open is grater zero were are inside an entry
            if ($open>0) {
                $ret = true;
            }
        }
        return $ret;
    }

    /**
     * Stripping Delimiter
     *
     * @access private
     * @param string $entry The entry where the Delimiter should be stripped from
     * @return string Stripped entry
     */
    private function _stripDelimiter($entry)
    {
        $beginningdels = array_keys($this->_delimiters);
        $length        = strlen($entry);
        $firstchar     = substr($entry, 0, 1);
        $lastchar      = substr($entry, -1, 1);
        while (in_array($firstchar, $beginningdels)) { //The first character is an opening delimiter
            if ($lastchar == $this->_delimiters[$firstchar]) { //Matches to closing Delimiter
                $entry = substr($entry, 1, -1);
            } else {
                break;
            }
            $firstchar = substr($entry, 0, 1);
            $lastchar  = substr($entry, -1, 1);
        }
        return $entry;
    }

    /**
     * Unwrapping entry
     *
     * @access private
     * @param string $entry The entry to unwrap
     * @return string unwrapped entry
     */
    private function _unwrap($entry)
    {
        $entry = preg_replace('/\s+/', ' ', $entry);
        return trim($entry);
    }

    /**
     * Wordwrap an entry
     *
     * @access private
     * @param string $entry The entry to wrap
     * @return string wrapped entry
     */
    private function _wordwrap($entry)
    {
        if ( (''!=$entry) && (is_string($entry)) ) {
            $entry = wordwrap($entry, $this->_options['wordWrapWidth'], $this->_options['wordWrapBreak'], $this->_options['wordWrapCut']);
        }
        return $entry;
    }

    /**
     * Extracting the authors
     *
     * @access private
     * @param string $entry The entry with the authors
     * @return array the extracted authors
     */
    private function _extractAuthors($entry) {
        $entry       = $this->_unwrap($entry);
        // Replace AND with and in author list - added 2010-12-12, till@till-bisup.de
        $entry       = str_replace(' AND ',' and ',$entry);
        $authorarray = array();
        $authorarray = explode(' and ', $entry);
        for ($i = 0; $i < sizeof($authorarray); $i++) {
            $author = trim($authorarray[$i]);
            /*The first version of how an author could be written (First von Last)
             has no commas in it*/
            $first    = '';
            $von      = '';
            $last     = '';
            $jr       = '';
            if (strpos($author, ',') === false) {
                $tmparray = array();
                $tmparray = explode(' ', $author);
                $size     = sizeof($tmparray);
                if (1 == $size) { //There is only a last
                    $last = $tmparray[0];
                } elseif (2 == $size) { //There is a first and a last
                    $first = $tmparray[0];
                    $last  = $tmparray[1];
                } else {
                    $invon  = false;
                    $inlast = false;
                    for ($j=0; $j<($size-1); $j++) {
                        if ($inlast) {
                            $last .= ' '.$tmparray[$j];
                        } elseif ($invon) {
                            $case = $this->_determineCase($tmparray[$j]);
                            if ((0 == $case) || (-1 == $case)) { //Change from von to last
                                //You only change when there is no more lower case there
                                $islast = true;
                                for ($k=($j+1); $k<($size-1); $k++) {
                                    $futurecase = $this->_determineCase($tmparray[$k]);
                                    if ($case == PHP_INT_MAX) {
                                        // Error case. IGNORE?
                                    } elseif (0 == $futurecase) {
                                        $islast = false;
                                    }
                                }
                                if ($islast) {
                                    $inlast = true;
                                    if (-1 == $case) { //Caseless belongs to the last
                                        $last .= ' '.$tmparray[$j];
                                    } else {
                                        $von  .= ' '.$tmparray[$j];
                                    }
                                } else {
                                    $von    .= ' '.$tmparray[$j];
                                }
                            } else {
                                $von .= ' '.$tmparray[$j];
                            }
                        } else {
                            $case = $this->_determineCase($tmparray[$j]);
                            if (0 == $case) { //Change from first to von
                                $invon = true;
                                $von   .= ' '.$tmparray[$j];
                            } else {
                                $first .= ' '.$tmparray[$j];
                            }
                        }
                    }
                    //The last entry is always the last!
                    $last .= ' '.$tmparray[$size-1];
                }
            } else { //Version 2 and 3
                $tmparray     = array();
                $tmparray     = explode(',', $author);
                //The first entry must contain von and last
                $vonlastarray = array();
                $vonlastarray = explode(' ', $tmparray[0]);
                $size         = sizeof($vonlastarray);
                if (1==$size) { //Only one entry->got to be the last
                    $last = $vonlastarray[0];
                } else {
                    $inlast = false;
                    for ($j=0; $j<($size-1); $j++) {
                        if ($inlast) {
                            $last .= ' '.$vonlastarray[$j];
                        } else {
                            if (0 != ($this->_determineCase($vonlastarray[$j]))) { //Change from von to last
                                $islast = true;
                                for ($k=($j+1); $k<($size-1); $k++) {
                                    $this->_determineCase($vonlastarray[$k]);
                                    $case = $this->_determineCase($vonlastarray[$k]);
                                    if (0 == $case) {
                                        $islast = false;
                                    }
                                }
                                if ($islast) {
                                    $inlast = true;
                                    $last   .= ' '.$vonlastarray[$j];
                                } else {
                                    $von    .= ' '.$vonlastarray[$j];
                                }
                            } else {
                                $von    .= ' '.$vonlastarray[$j];
                            }
                        }
                    }
                    $last .= ' '.$vonlastarray[$size-1];
                }
                //Now we check if it is version three (three entries in the array (two commas)
                if (3==sizeof($tmparray)) {
                    $jr = $tmparray[1];
                }
                //Everything in the last entry is first
                $first = $tmparray[sizeof($tmparray)-1];
            }
            $authorarray[$i] = array('first'=>trim($first), 'von'=>trim($von), 'last'=>trim($last), 'jr'=>trim($jr));
        }
        return $authorarray;
    }

    /**
     * Case Determination according to the needs of BibTex
     *
     * To parse the Author(s) correctly a determination is needed
     * to get the Case of a word. There are three possible values:
     * - Upper Case (return value 1)
     * - Lower Case (return value 0)
     * - Caseless   (return value -1)
     *
     * @access private
     * @param string $word
     * @return int The Case or PHP_INT_MAX if there was a problem
     */
    private function _determineCase($word) {
        $ret         = -1;
        $trimmedword = trim ($word);
        /*We need this variable. Without the next of would not work
         (trim changes the variable automatically to a string!)*/
        if (is_string($word) && (strlen($trimmedword) > 0)) {
            $i         = 0;
            $found     = false;
            $openbrace = 0;
            while (!$found && ($i <= strlen($word))) {
                $letter = substr($trimmedword, $i, 1);
                $ord    = ord($letter);
                if ($ord == 123) { //Open brace
                    $openbrace++;
                }
                if ($ord == 125) { //Closing brace
                    $openbrace--;
                }
                if (($ord>=65) && ($ord<=90) && (0==$openbrace)) { //The first character is uppercase
                    $ret   = 1;
                    $found = true;
                } elseif ( ($ord>=97) && ($ord<=122) && (0==$openbrace) ) { //The first character is lowercase
                    $ret   = 0;
                    $found = true;
                } else { //Not yet found
                    $i++;
                }
            }
        } else {
            $ret = PHP_INT_MAX;
//            $ret = PEAR::raiseError('Could not determine case on word: '.(string)$word);
        }
        return $ret;
    }

    /**
     * Validation of a value
     *
     * There may be several problems with the value of a field.
     * These problems exist but do not break the parsing.
     * If a problem is detected a warning is appended to the array warnings.
     *
     * @access private
     * @param string $entry The entry aka one line which which should be validated
     * @param string $wholeentry The whole BibTex Entry which the one line is part of
     * @return void
     */
    private function _validateValue($entry, $wholeentry)
    {
        //There is no @ allowed if the entry is enclosed by braces
        if (preg_match('/^{.*@.*}$/', $entry)) {
            $this->_generateWarning('WARNING_AT_IN_BRACES', $entry, $wholeentry);
        }
        //No escaped " allowed if the entry is enclosed by double quotes
        if (preg_match('/^\".*\\".*\"$/', $entry)) {
            $this->_generateWarning('WARNING_ESCAPED_DOUBLE_QUOTE_INSIDE_DOUBLE_QUOTES', $entry, $wholeentry);
        }
        //Amount of Braces is not correct
        $open     = 0;
        $lastchar = '';
        $char     = '';
        for ($i = 0; $i < strlen($entry); $i++) {
            $char = substr($entry, $i, 1);
            if (('{' == $char) && ('\\' != $lastchar)) {
                $open++;
            }
            if (('}' == $char) && ('\\' != $lastchar)) {
                $open--;
            }
            $lastchar = $char;
        }
        if (0 != $open) {
            $this->_generateWarning('WARNING_UNBALANCED_AMOUNT_OF_BRACES', $entry, $wholeentry);
        }
    }

    /**
     * Remove curly braces from entry
     *
     * @access private
     * @param string $value The value in which curly braces to be removed
     * @param string Value with removed curly braces
     */
    private function _removeCurlyBraces($value)
    {
        //First we save the delimiters
        $beginningdels = array_keys($this->_delimiters);
        $firstchar     = substr($entry, 0, 1);
        $lastchar      = substr($entry, -1, 1);
        $begin         = '';
        $end           = '';
        while (in_array($firstchar, $beginningdels)) { //The first character is an opening delimiter
            if ($lastchar == $this->_delimiters[$firstchar]) { //Matches to closing Delimiter
                $begin .= $firstchar;
                $end   .= $lastchar;
                $value  = substr($value, 1, -1);
            } else {
                break;
            }
            $firstchar = substr($value, 0, 1);
            $lastchar  = substr($value, -1, 1);
        }
        //Now we get rid of the curly braces
        $value = preg_replace('/[\{\}]/', '', $value);
        //Reattach delimiters
        $value       = $begin.$value.$end;
        return $value;
    }

    /**
     * Generates a warning
     *
     * @access private
     * @param string $type The type of the warning
     * @param string $entry The line of the entry where the warning occurred
     * @param string $wholeentry OPTIONAL The whole entry where the warning occurred
     */
    private function _generateWarning($type, $entry, $wholeentry='')
    {
        $warning['warning']    = $type;
        $warning['entry']      = $entry;
        $warning['wholeentry'] = $wholeentry;
        $this->warnings[]      = $warning;
    }

    /**
     * Cleares all warnings
     *
     * @access public
     */
    public function clearWarnings()
    {
        $this->warnings = array();
    }

    /**
     * Is there a warning?
     *
     * @access public
     * @return true if there is, false otherwise
     */
    public function hasWarning()
    {
        if (sizeof($this->warnings)>0) return true;
        else return false;
    }

    /**
     * Returns the author formatted
     *
     * The Author is formatted as setted in the authorstring
     *
     * @access private
     * @param array $array Author array
     * @return string the formatted author string
     */
    private function _formatAuthor($array)
    {
        if (!array_key_exists('von', $array)) {
            $array['von'] = '';
        } else {
            $array['von'] = trim($array['von']);
        }
        if (!array_key_exists('last', $array)) {
            $array['last'] = '';
        } else {
            $array['last'] = trim($array['last']);
        }
        if (!array_key_exists('jr', $array)) {
            $array['jr'] = '';
        } else {
            $array['jr'] = trim($array['jr']);
        }
        if (!array_key_exists('first', $array)) {
            $array['first'] = '';
        } else {
            $array['first'] = trim($array['first']);
        }
        $ret = $this->authorstring;
        $ret = str_replace("VON", $array['von'], $ret);
        $ret = str_replace("LAST", $array['last'], $ret);
        // Assuming that "jr" is always separated by a comma
        if (!empty($array['jr'])) {
          $ret = str_replace("JR", $array['jr'], $ret);
        } else {
          $ret = str_replace(", JR", '', $ret);
        }
        $ret = str_replace("FIRST", $array['first'], $ret);
        return trim($ret);
    }

}
?>
