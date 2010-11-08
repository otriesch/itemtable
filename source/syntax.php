<?php
/**
 * Plugin itemtable: Renders tables in DokuWiki format by using itemlists instead of the Wiki syntax (very helpful for big tables with a lot of text)
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Olaf Trieschmann <trieschmann@otri.de>
 *
 * Thanks to Stephen C's plugin "dbtables", which was used as a starting point!
 */

// must be run within DokuWiki
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once DOKU_PLUGIN.'syntax.php';
require_once DOKU_INC.'inc/parser/parser.php';
require_once DOKU_INC . 'inc/parser/xhtml.php';

/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_itemtable extends DokuWiki_Syntax_Plugin {

    // $options is used for rendering options    
    public $options=array();
    
    function getInfo() {
        return array('author' => 'Olaf Trieschmann',
                     'email'  => 'develop@otri.de',
                     'date'   => '2010-11-06',
                     'name'   => 'Item Table',
                     'desc'   => 'Draw Items in a table structure in a Wiki',
                     'url'    => 'https://github.com/otriesch/itemtable/blob/master/itemtable.zip');
    }

    function getType() { return 'substition'; }
    function getSort() { return 32; }

    function connectTo($mode) {
        $this->Lexer->addEntryPattern('<itemtable *[^>]*>',$mode,'plugin_itemtable');
    }
 
    function postConnect() {
        $this->Lexer->addExitPattern('</itemtable>','plugin_itemtable');
    }

    /**
     * Handle the match
     */
    function handle($match, $state, $pos, &$handler){
        switch ($state) {
            case DOKU_LEXER_ENTER : 
              return array($state, substr($match, 10, -1) );
              break;
            case DOKU_LEXER_MATCHED :
              return array($state,$match);
              break;
            case DOKU_LEXER_UNMATCHED :
              return array($state, $match);
              break;
            case DOKU_LEXER_EXIT :
              return array($state, '');
              break;
        }
        return array();
    }
     
    function render_tables($match,$mode,$data) {
      // $match is the full text we're to consider
      $raw=explode("\n",$match);
      
//    foreach($this->options as $option) {
//      $TableData.=$option." ";
//		}
//		$TableData.="\n\n\n";
		
      // Yes, so draw the heading
      if (trim($this->options["header"])!=""){
      	// Draw the Dokuwiki table heading
      	$TableData.="^".$this->options["header"].substr("^^^^^^^^^^",0,$this->options["cols"]+1)."\n";
      } else {
			$TableData.="";
      }

      // Draw the descriptors of each field
      $TableData.="^ ";
      for($ColPos=1;$ColPos<=$this->options["cols"];$ColPos++)
              $TableData.="^".$this->options["c".$ColPos]." ";
      $TableData.="^\n";
      
      for($ColPos=1;$ColPos<=$this->options["cols"];$ColPos++) {
  	     $RowElements["c".$ColPos]=" ";
      }
		$RowCount=0;
		      
      // Run through each line and decide how to render the text      
      foreach($raw as $rawline) {
        $CurrentLine=trim($rawline);
        if ($CurrentLine!=""){
          // Is this row the name of a table?
          if (substr($rawline,0,1)==$this->options["thead"]) {
            if ($RowCount!=0) {
			     // Go through each entity and output it
			     for($ColPos=1;$ColPos<=$this->options["cols"];$ColPos++) {
			  	    $TableData.="|".$RowElements["c".$ColPos]."  ";
			     }
              // SHIP IT!
              $TableData.="|\n";
            }
            // Remember the current row name
      		$TableData.="|".substr($rawline,1)."  ";
      		for($ColPos=1;$ColPos<=$this->options["cols"];$ColPos++) {
  	     		  $RowElements["c".$ColPos]=" ";
  	     		}
				$RowCount++;
          } else {
            // Split the fields up.
            $RowInfo=explode($this->options["fdelim"],$rawline);
            if (count($RowInfo)>=2) {
      		  for($ColPos=1;$ColPos<=$this->options["cols"];$ColPos++) {
           		 if ($RowInfo[0]==$this->options["c".$ColPos]) {
           		   $RowElements["c".$ColPos]=$RowInfo[1];
           		 } 
				  }
            }
          }
        }
      }
      // Go through each entity and output it
   	for($ColPos=1;$ColPos<=$this->options["cols"];$ColPos++) {
  		  $TableData.="|".$RowElements["c".$ColPos]."  ";
      }
      // SHIP IT!
      $TableData.="|\n";
      // Start the HTML table rendering
      $res="</p><table";
      if ($this->options["twidth"]!="")
        $res.=" width='".$this->options["twidth"]."'>";
      else
        $res.=">";
        
      // Prepare the table information
      // The option to not render from Dokuwiki to HTML is available
      if ($this->options["norender"]=="")
        $td="<td class='dbtables-td_0'>".p_render($mode,p_get_instructions($TableData),$data)."</td>";
      else
        $td="<td><pre>".$TableData."</pre></td>";
     
      // Draw the table row
      $res.="\n<tr class='dbtables-tr_0' valign='top'>\n";
      // Write out the table data
      $res.=$td."\n";
      $CurTablePos=$CurTablePos+1;
      // Close off the HTML-Table
      $res.="</tr></table><p>";
      return $res;
    }
    
    function render($mode, &$renderer, $data) {
      // This will only render in xhtml
      if($mode == 'xhtml'){
         list($state, $match) = $data;
          switch ($state) {
              // This happens when we first find the <itemtable>
              case DOKU_LEXER_ENTER :
                $parmsexp=explode(';',$match);
                // Set the relevant default values
                $this->options["fdelim"]=":"; // The character used to delimit what goes between fields
                $this->options["header"]=""; // 
                $this->options["thead"]="_";  // The character used to indicate the table name
                // $this->options["twidth"]  // Default HTML table width in HTML specifications (IE: 95% - 960px)
                // $this->options["norender"] -> Assign a value to NOT render from Dokuwiki to HTML
                
                // Prepare each option
                $this->options["cols"]=0; 
                foreach($parmsexp as $pexp) {
                  $p=explode("=",$pexp);
                  $this->options[trim($p[0])]=$p[1];
                  if (substr($p[0],0,1)=="c") {
                    $this->options["cols"]++;
                  }
                }
                break;
              // This happens each line between <dbtables> and </dbtables>
              case DOKU_LEXER_UNMATCHED :
                // Send to the rendering function
                $renderer->doc.=$this->render_tables($match,$mode,$data);
                //$renderer->doc .= $renderer->_xmlEntities($match);
                break;
          }
          return true;
      }
      return false;
    }
}