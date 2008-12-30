<?php
  /**********************************************
  * quickerUbb (c)2004 Roönaän
  *
  * with enhancements of:
  *
  * Nathan Codding from phpBB Group
  * Markus Fassbender from Admidio Team
  *
  * version 1.4:
  * [25/02/2004]:
  * Added a _quickerUBB_isTextTag() method in order to
  * support tags like [php] en [code] whom's inner code
  * should be skipped while parsing.
  * In order to add tags, you should edit this method,
  * Starting at line 119 of this file.
  *
  * Ubb Parsing Engine based on stacks.
  *
  * Add additional parse_ubbtag methods to the main class.
  * For adding smiles and for applying htmlspecialchars and
  * transformation of \n\r to <br /> edit the ubbtexthandler
  * method
  *
  * In this file some example stylesheets are used, as a
  * additional example parsing in case this file is called
  * upon directly and not bij inclusion.
  *
  * For Questions and comments: hotscripts@roonaan.nl
  * please add script name to your email subject.
  */

  $start_time = !isset($start_time) ? explode(' ', microtime()) : $start_time;

  /**
  * UBB_LOOKDOWN defines the number of elements the parser
  * descends to find a matching closing element
  */
  define('UBB_LOOKDOWN', 2);

  /**
  * UBB_IMG_MAX_RESIZE_WIDTH and UBB_IMG_MAX_RESIZE_HEIGHT
  * define the highest values which can be used in the img
  * tags: [img=height, width]url[/img]
  * and [img=width]ur;[/img]
  **/
  define('UBB_IMG_MAX_RESIZE_WIDTH', 400);
  define('UBB_IMG_MAX_RESIZE_HEIGHT', 400);

/* UBB Text handler allows modification of text in order
   to apply additional ubb tags, or smiles */
function ubbtexthandler($text, $this = null)
{
  global $g_root_path;

  //$text = htmlspecialchars($text);
  if(is_object($this)) if(strpos(strtolower($text), '/me') > 0) $text = eregi_replace("([^[])/me([^\n\r$]*)([\n\r$])", "\\1<span class=\"me\">*".$this->username." \\2 *</span>\\3", $text);
  $text = nl2br($text);

  //echo '<div>'.htmlspecialchars($text).'</div>';
  $smiles = array();
  $smiles[':)'] = "<img src=\"". THEME_PATH. "/icons/smilies/emoticon_smile.png\" alt=\"Smile\" />";
  $smiles[':-)'] = "<img src=\"". THEME_PATH. "/icons/smilies/emoticon_smile.png\" alt=\"Smile\" />";
  $smiles[':('] = "<img src=\"". THEME_PATH. "/icons/smilies/emoticon_unhappy.png\" alt=\"Unhappy\" />";
  $smiles[':-('] = "<img src=\"". THEME_PATH. "/icons/smilies/emoticon_unhappy.png\" alt=\"Unhappy\" />";
  $smiles[';)'] = "<img src=\"". THEME_PATH. "/icons/smilies/emoticon_wink.png\" alt=\"Wink\" />";
  $smiles[';-)'] = "<img src=\"". THEME_PATH. "/icons/smilies/emoticon_wink.png\" alt=\"Wink\" />";
  $smiles[':D'] = "<img src=\"". THEME_PATH. "/icons/smilies/emoticon_grin.png\" alt=\"Grin\" />";
  $smiles[':-D'] = "<img src=\"". THEME_PATH. "/icons/smilies/emoticon_grin.png\" alt=\"Grin\" />";
  $smiles[':d'] = "<img src=\"". THEME_PATH. "/icons/smilies/emoticon_grin.png\" alt=\"Grin\" />";
  $smiles[':-d'] = "<img src=\"". THEME_PATH. "/icons/smilies/emoticon_grin.png\" alt=\"Grin\" />";
  $smiles[':P'] = "<img src=\"". THEME_PATH. "/icons/smilies/emoticon_tongue.png\" alt=\"Tongue\" />";
  $smiles[':-P'] = "<img src=\"". THEME_PATH. "/icons/smilies/emoticon_tongue.png\" alt=\"Tongue\" />";
  $smiles[':p'] = "<img src=\"". THEME_PATH. "/icons/smilies/emoticon_tongue.png\" alt=\"Tongue\" />";
  $smiles[':-p'] = "<img src=\"". THEME_PATH. "/icons/smilies/emoticon_tongue.png\" alt=\"Tongue\" />";
  $smiles[':O'] = "<img src=\"". THEME_PATH. "/icons/smilies/emoticon_surprised.png\" alt=\"Surprised\" />";
  $smiles[':-O'] = "<img src=\"". THEME_PATH. "/icons/smilies/emoticon_surprised.png\" alt=\"Surprised\" />";
  $smiles[':o'] = "<img src=\"". THEME_PATH. "/icons/smilies/emoticon_surprised.png\" alt=\"Surprised\" />";
  $smiles[':-o'] = "<img src=\"". THEME_PATH. "/icons/smilies/emoticon_surprised.png\" alt=\"Surprised\" />";
  $smiles[':lol:'] = "<img src=\"". THEME_PATH. "/icons/smilies/emoticon_happy.png\" alt=\"Happy\" />";
  $smiles[':twisted:'] = "<img src=\"". THEME_PATH. "/icons/smilies/emoticon_evilgrin.png\" alt=\"Evilgrin\" />";

  foreach($smiles as $ubb => $html)
    $text = str_replace($ubb, $html, $text);

  return $text;
}

/** In order to handle tags like [php] or [code] of which the inner
*  text should not be parsed, a new method is created, stated below
*
*  It implements a in_array statements.
*
*  Be aware! : All listed tags should be lowercase in order to be
*  recognized correctly.
*/
function _quickerUBB_isTextTag($tag)
{
  return in_array($tag,
  array(
  'code',
  'php',
  ));
}

/********
* ubbParsing class.
*
* This class builds an tree of stackItems objects and from
* there derives an appropriate html structure based upon
* code generation methods. Each code generation method
* parse_[ubb], as where [ubb] is an ubb tag which is
* supported by the parser. After adding an additional
* method, the parser will recognize the code generation
* method and apply this method when encountering a matching
* ubb-tag while parsing.
*
* In order to use the parser, initialize an ubbParser object
* and call the following method
*
* $initializedUbbParser->parse($ubb)
*
* This class can be a superclass for more flexible classess,
* for instanse the UbbAdminParser which is used to parse
* site admin messages and which allowes html input, using the
* [html]html code[/html] tag.
*
* When using the /me tag (which will automatically be
* replace to a [me=username][/me] structure), you should use
* $parser->setUsername('username') first.
*/

class ubbParser
{
  var $usedTags;
  var $username;

  function setUsername($username)
  {
    $this->username = eregi_replace('([^a-z0-9_~]*)', '', $username);
  }

  function ubbParser()
  {
    $this->usedTags = array();
    $this->textTags = array();
    $this->username = '';
    $methods = get_class_methods(get_class($this));
    foreach($methods as $m)
    {
      if(substr($m, 0, 6) == 'parse_')
      {
        $tag = substr($m, 6);
        $this->usedTags[$tag] = $m;
      }
    }

  }

  function parse($text)
  {
     if(strpos(strtolower($text), '/me') > 0) $text = eregi_replace("([^[])/me([^\n\r$]*)([\n\r$])", "\\1[me=".$this->username."]\\2[/me]\\3", $text);
     $text = str_replace('[*]','[li]', $text);
     $text = str_replace('[/*]','[/li]', $text);
     $basetree = new stackItem();
     $basetree->build(' '.trim($text));
     // MFA
     $text = $basetree->parse($this, $this->usedTags);
     $text = $this->make_clickable($text);
     return $text;
  }

  /* Auxilary method which calls upon the ubbtexthandler
     method, or does noting when not found */
  function parse_text($tree)
  {
    $this->text_handler = 'ubbtexthandler';
    if(isset($this->text_handler))
    {
      if(function_exists($this->text_handler))
      {
        $f = $this->text_handler;
        return $f($tree, $this);
      }
    }
    return $this->text_handler;
  }

  /* base function to convert a [*]text[*] to <**>text</**> */
  function simple_parse($tree, $html_pre, $html_post, $parseInner = true, $htmlspecialchars = true, $nl2br = true)
  {
    $text = $parseInner ? $tree->innerToHtml($this, $this->usedTags) : $tree->toText();
    $text = strlen($text) > 0 ? $html_pre.$text.$html_post : '';
    /* Added a $nl2br check, thanx to Bert Goedhals */
    if ( !$nl2br )
    {
      $text = str_replace ("<br />", "", $text);
    }
    return $text;
  }

  /* code generation methods */
  function parse_hr($tree)   {return $this->simple_parse($tree, '<hr />', '');}
  function parse_br($tree)   {return $this->simple_parse($tree, '<br />', '');}
  function parse_i($tree)    {return $this->simple_parse($tree, '<i>', '</i>');}
  function parse_u($tree)    {return $this->simple_parse($tree, '<u>', '</u>');}
  function parse_s($tree)    {return $this->simple_parse($tree, '<s>', '</s>');}
  function parse_b($tree)    {return $this->simple_parse($tree, '<b>', '</b>');}
  function parse_sub($tree)  {return $this->simple_parse($tree, '<sub>', '</sub>');}
  function parse_sup($tree)  {return $this->simple_parse($tree, '<sup>', '</sup>');}
  function parse_small($tree){return $this->simple_parse($tree, '<small>', '</small>');}
  function parse_big($tree)  {return $this->simple_parse($tree, '<big>', '</big>');}
  function parse_code($tree) {return $this->simple_parse($tree, '<blockquote><b>Code:</b><pre>','</pre></blockquote>', false);}
  function parse_php($tree)  {return '<blockquote><b>Php:</b><pre>'.highlight_string('<?php '.$tree->toText().'?>', true).'</pre></blockquote>';}
  /* Methods parse_list/_ul__ol_li are updated to match XHtml thanx to Bert Goedhals */
  function parse_list($tree) {return $this->simple_parse($tree, '<ul>', '</ul>', true, true, false);}
  function parse_ul($tree)   {return $this->simple_parse($tree, '<ul>', '</ul>', true, true, false);}
  function parse_ol($tree)   {return $this->simple_parse($tree, '<ol>', '</ol>', true, true, false);}
  function parse_li($tree)   {return $this->simple_parse($tree, '<li>', '</li>', true, true, false);}
  function parse_edit($tree) {return $this->simple_parse($tree, '<span class="edit"><b>edit:</b>','</span>');}
  function parse_bold($tree) {return $this->simple_parse($tree, '<b>', '</b>');}
  function parse_quote($tree){return $this->simple_parse($tree, '<blockquote>', '</blockquote>');}
  function parse_center($tree){return $this->simple_parse($tree, '<center>', '</center>');}

  /* more complex code generation methods */
  function parse_me($tree, $params = array())
  {
     $me = isset($params['me']) ? '*'.$params['me'].' ' : '*';
     return $this->simple_parse($tree, '<span class="me">'.$me, '*</span>');
  }
  function parse_url($tree, $params = array())
  {
     global $g_root_path;
     /* [url]href[/url] as well as [url=href]text[/url] is supported */
     $href = isset($params['url']) ? $params['url'] : $tree->toText();
     $href = $this->valid_url($href) ? $href : '';
     // MFA bei einem Verweis auf Admidioseiten, diesen im selben Fenster oeffnen
     if(strpos(strtolower($href), strtolower($g_root_path)) === 0)
     {
        $target = "_self";
     }
     else
     {
        $target = "_blank";
     }
     return $this->simple_parse($tree, '<a href="'.htmlspecialchars($href).'" target="'. $target. '">', '</a>');
  }
  // MFA mail in email umbenannt
  function parse_email($tree, $params = array())
  {
     /* [mail]email[/mail] as well as [mail=email]text[/mail] is supported */
     $href = isset($params['email']) ? $params['email'] : $tree->toText();
     return $this->simple_parse($tree, '<a href="mailto:'.htmlspecialchars($href).'">', '</a>');
  }
  function parse_img($tree)
  {
    $text = $tree->toText();
    $params = $tree->getParameters();
    $height = ''; $width = ''; $align = '';
    if(isset($params['img']))
    {
      $size = explode(',',trim($params['img']));
      $c = count($size);
      if($c==2)
      {
        $height = is_numeric($size[0]) ? (intval($size[0]) < UBB_IMG_MAX_RESIZE_HEIGHT) ? ' height="'.$size[0].'"' : '' : '';
        $width  = is_numeric($size[1]) ? (intval($size[1]) < UBB_IMG_MAX_RESIZE_WIDTH) ? ' width="'.$size[1].'"' : '' : '';
      }
      else if($c==1)
      {
        $width  = is_numeric($size[0]) ? (intval($size[0]) < UBB_IMG_MAX_RESIZE_WIDTH) ? ' width="'.$size[0].'"' : '' : '';
      }
    }
    if(isset($params['align']))
    {
      $s = strtolower($params['align']);
      /*if($s == 'left' || $s == 'links') $align = ' align="left"';
      if($s == 'right' || $s == 'rechts') $align = ' align="right"';*/
      if($s == 'left' || $s == 'links') $align = ' style="float: left;"';
      if($s == 'right' || $s == 'rechts') $align = ' style="float: right;"';
    }
    $text = $this->valid_url($text) ? $text : '';
    return '<img'.$height.$width.$align.' src="'.htmlspecialchars($text).'" alt="Bild" />';
  }
  function valid_url($href)
  {
     $lowhref = strtolower($href);
     return ((substr($lowhref,0,7)=='http://') || (substr($lowhref,0,6)=='ftp://') || (substr($lowhref,0,7)=='mailto:'));
  }

    /***************************************************************************
     *   written by           : Nathan Codding - Feb 6, 2001
     *   copyright            : (C) 2001 The phpBB Group
     ***************************************************************************/
    function make_clickable($text)
    {
        $text = preg_replace('#(script|about|applet|activex|chrome):#is', "\\1&#058;", $text);

        // pad it with a space so we can match things at the start of the 1st line.
        $ret = ' ' . $text;

        // matches an "xxxx://yyyy" URL at the start of a line, or after a space.
        // xxxx can only be alpha characters.
        // yyyy is anything up to the first space, newline, comma, double quote or <
        $ret = preg_replace("#(^|[\n ])([\w]+?://[\w\#$%&~/.\-;:=,?@\[\]+]*)#is", "\\1<a href=\"\\2\" target=\"_blank\">\\2</a>", $ret);

        // matches a "www|ftp.xxxx.yyyy[/zzzz]" kinda lazy URL thing
        // Must contain at least 2 dots. xxxx contains either alphanum, or "-"
        // zzzz is optional.. will contain everything up to the first space, newline,
        // comma, double quote or <.
        $ret = preg_replace("#(^|[\n ])((www|ftp)\.[\w\#$%&~/.\-;:=,?@\[\]+]*)#is", "\\1<a href=\"http://\\2\" target=\"_blank\">\\2</a>", $ret);

        // matches an email@domain type address at the start of a line, or after a space.
        // Note: Only the followed chars are valid; alphanums, "-", "_" and or ".".
        $ret = preg_replace("#(^|[\n ])([a-z0-9&\-_.]+?)@([\w\-]+\.([\w\-\.]+\.)*[\w]+)#i", "\\1<a href=\"mailto:\\2@\\3\">\\2@\\3</a>", $ret);

        // Remove our padding..
        $ret = substr($ret, 1);

        return($ret);
    }
}

/* ubbAdminParse class which enabled site admins to input
* plain html into their messages
*/
class ubbAdminParser extends ubbParser
{
  function parse_html($tree)
  {
    return $tree->toText();
  }
}

/*
*
*/

/* StackItems is an recursive object used to create a
* tree, from which html or plain text can be derived.
* Although methods are commented, editing is not
* recommanded
*/
class stackItem
{
    /* $parent maintaince a link to the parent object of
     * element, as where $childs is an mixed array of plain
     * text and other stackItem objects
     */
    var $parent;
    var $childs;
    /* $tag_open : the ubb tag, without parameters
     * $tag_close: the ubb closing tag.
     * $tag_full : full ubb tag as found in the original
     *             unparsed text
     */
    var $tag_open, $tag_close, $tag_full;

    var $was_closed = false;
    /* storeage array for parameter information*/
    var $parameters;

    /* construtor initializes attributes */
    function stackItem()
    {
      $this->parent = null;
      $this->childs = array();
      $this->parameters = array();
      $this->tag_open = '';
      $this->tag_close = '';
      $this->tag_full = '';
    }

    /* set the parent of the object, this method is often
     * calles upon, just after creation of the object */
    function setParent(&$parent)
    {
      if(!is_object($parent)) return false;
      if(get_class($parent) != get_class($this)) return false;
      $this->parent = $parent;
      return true;
    }

    /* Alter $this->tag_open and $this->tag_close from an
     * external scope */
    function setTag($open, $close = '')
    {
       $this->tag_open = strtolower($open);
       $this->tag_close = strtolower($close);
    }

    /* parse $text until $this->tag_close is encountered.
     * When a other closing tag than expected is found,
     * handle it appropriate:
     * - Look down the tree, werther there is an element for
     *   which the found closing tag is appropriate. If this
     *   element is less then UBB_LOOKDOWN steps away, close
     *   the current tag and return to calling object. When
     *   out of range, handle the closing tag as ordinary
     *   text
     */
    function take($text)
    {
      while(($s = strpos($text, '[')) >= 0 && strlen($text) > 0)
      {
        if($s===false)
        {
          $this->append($text);
          $text = '';
        }
        elseif($s == 0)
        {
          $close = strpos($text, ']');
          if($close < 0)
          {
            $this->append($text);
            $text = '';
          }
          elseif(substr($text, 0, 2) == '[/')
          {
            $tag = strtolower(substr($text, 0, $close+1));
            $text = substr($text, $close+1);
            if($tag==$this->tag_close)
            {
              $this->was_closed = true;
              return $text;
            }
            else if($this->parent != null)
            {
              $subelem = $this->parent->isThisYours($tag, UBB_LOOKDOWN);
              if(!$subelem)
              {
                $this->append($tag);
              }
              else
              {
                if($subelem <= UBB_LOOKDOWN)
                {
                  return $tag.$text;
                }
                else
                {
                  $this->append($tag);
                }
              }
            }
            else
            {
              $this->append($tag);
            }
          }
          else
          {
            $child = new stackItem();
            $child->setParent($this);
            $text = $child->build($text);
            $this->append($child);
          }
        }
        else
        {
          $this->append(substr($text, 0, $s));
          $text = substr($text, $s);
        }
        $s = -1;
      } //end while

      return $text;
    }

    /**
    * parse $tag into $tag_open en $tag_full.
    * extract (parameter,value) pairs and store
    * these in $this->parameters;
    */
    function parseTag($tag)
    {
      $this->tag_full = '['.$tag.']';
      while(strpos($tag, ' =') > 0) $tag = str_replace(' =', '=', $tag);
      while(strpos($tag, '= ') > 0) $tag = str_replace('= ', '=', $tag);
      while(strpos($tag, ', ') > 0) $tag = str_replace(', ', ',', $tag);
      while(strpos($tag, ' ,') > 0) $tag = str_replace(' ,', ',', $tag);
      $exploded = explode(' ', $tag);
      $tag_open = '';
      foreach($exploded as $index => $element)
      {
        $pair = explode('=', $element, 2);

        if(count($pair) == 2)
        {
          $this->parameters[strtolower($pair[0])] = $pair[1];
        }
        if($index == 0) $tag_open = $pair[0];
      }
      $this->tag_open = strtolower($tag_open);
      $this->tag_close = strtolower('[/'.$tag_open.']');
    }

    /* build($text) generates a tree from $text from where
     * $this is the current root element.
     */

    function build($text)
    {
      if(empty($text)) return '';

      if(substr($text, 0, 1) == '[')
      {
         /* Starts with an tag?
          * parsing should stop when /tag is found
          *
          * therefor $tag_open, $tag_close should be
          * initialized
          */
        $sclose = strpos($text, ']');
        if($sclose<0)
        {
          $this->append($text);
          return '';
        }
        $tag = substr($text, 1, $sclose-1);

        $text = substr($text, $sclose + 1);
        $this->parseTag($tag);
        if(_quickerUBB_isTextTag(strtolower($tag)))
        {
          $s = strpos(strtolower($text),'[/'.strtolower($tag));
          if($s == false)
          {
            $text = $this->take($text);
          }
          else
          {
            $subtext = substr($text, 0, $s);
            $this->childs[] = $subtext;
            $text = substr($text, $s);
            $text = substr($text, strpos($text,']')+1);
          }
        }
        else
        {
          $text = $this->take($text);
        }
        return $text;
      }
      else
      {
        /* Starts with text, therefor containerobject
         */
        $text = $this->take($text);
        $this->append($text);
      }
    }

    /* appends $data to the internal leaf structure.
     * $data can be object or plain text
     */
    function append($data)
    {
      if(empty($data)) return;
      $this->childs[] = $data;
    }

    /* This method is called upon from child object, to
     * find a object matching to a found closing tag
     * in order to maintain a stable structure.
     *
     * returns 'false' or a numeric value, telling the
     * calling child how many levels the corresponding
     * element is down in the tree, from the childs origin
     */
    function isThisYours($closingTag, $was_closed = 0)
    {
      if($closingTag == $this->tag_close)
      {
        if($was_closed >= 0) { $this->was_closed = true;}
        return 1;
      }
      if($this->parent == null)
      {
        return false;
      }
      else
      {
        $s = $this->parent->isThisYours($closingTag, $was_closed - 1);
        if(is_int($s)) return $s + 1;
        return $s;
      }

    }
    /* Return the parameters for this object */
    function getParameters()
    {
      return $this->parameters;
    }

    /* Return a string representation of this tag in plain
     * ubb */
    function toString()
    {
      return $this->tag_full.$this->toText().($this->was_closed ? $this->tag_close : '');
    }

    /* Return a string representation of this tags inner
     * in plain ubb */
    function toText()
    {
      $text = '';
      foreach($this->childs as $c)
      {
        if(is_object($c))
        {
          $text.= $c->toString();
        }
        else
        {
          $text.= $c;
        }
      }
      return $text;
    }

    /* convert the contents of this element to html.
     * the $parser object is used to find appropriate
     * parse_tag methods.
     */
    function innerToHtml(&$parser, $methods = array())
    {
      $text = '';
      foreach($this->childs as $c)
      {
        if(is_object($c))
        {
          $text.= $c->parse($parser, $methods);
        }
        else
        {
          $text.= $parser->parse_text($c);
        }
      }
      return $text;

    }

    /* Convert the total object to html */
    function toHtml(&$parser, $methods=array(), $inner_only = true)
    {
      $text = '';
      if(strlen($this->tag_full) > 0 && !$inner_only)
      {
        if(isset($methods[$this->tag_open]))
        {
          $method = $methods[$this->tag_open];
          $text = $parser->$method($this);
        }
        else
        {
          return $this->innerToHtml($parser, $methods);
        }
      }
      else
      {
        /* No method found for this tag */
        foreach($this->childs as $c)
        {
          if(is_object($c))
          {
            $text.= $c->parse($parser, $methods);
          }
          else
          {
            $text.= $parser->parse_text($c);
          }
        }
      }
      return $text;
    }

    /* Parse this object into html, this method is called
     * from the root element of the constructed tree */
    function parse(&$parser, $methods = array())
    {
      $text = '';
      if(strlen($this->tag_full) > 0)
      {

        if(isset($methods[$this->tag_open]))
        {
          $method = $methods[$this->tag_open];
          $text = $parser->$method($this, $this->parameters);
        }
        else
        {
          foreach($this->childs as $c)
          {
            if(is_object($c))
            {
              $text.= $c->parse($parser, $methods);
            }
            else
            {
              $text.= $parser->parse_text($c);
            }
          }
          return $this->tag_full.$text.($this->was_closed ? $this->tag_close : '');
        }
      }
      else
      {
        /* No method found for this tag */
        foreach($this->childs as $c)
        {
          if(is_object($c))
          {
            $text.= $c->parse($parser, $methods);
          }
          else
          {
            $text.= $parser->parse_text($c);
          }
        }
      }
      return $text;
    }
}

?>