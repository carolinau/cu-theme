<?php

namespace Drupal\collapse_text\Plugin\Filter;

use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Html;

// Set default title.
define('COLLAPSE_TEXT_DEFAULT_TITLE', t('Click here to expand or collapse this section'));

/**
 * Provides a filter to display Collapsible text blocks.
 *
 * @Filter(
 *   id = "filter_collapse_text",
 *   title = @Translation("Collapsible text blocks"),
 *   description = @Translation("Allows the creation of collapsing blocks of text. This filter must be after the 'Limit allowed HTML tags' filter, and should be after the 'Convert line breaks into HTML' filter."),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_MARKUP_LANGUAGE,
 * )
 */
class CollapseText extends FilterBase {

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form['default_title'] = array(
      '#type'          => 'textfield',
      '#title'         => t('Default title'),
      '#description'   => t('If no title is supplied for a section, use this as the default. This may not be empty. The original default title is "@default_title".', array('@default_title' => COLLAPSE_TEXT_DEFAULT_TITLE)),
      '#default_value' => isset($this->settings['default_title']) ? $this->settings['default_title'] : COLLAPSE_TEXT_DEFAULT_TITLE,
      '#required'      => TRUE,
    );
    $form['form'] = array(
      '#type'          => 'checkbox',
      '#title'         => t('Surround text with an empty form tag'),
      '#description'   => t('Collapse text works by generating &lt;details&gt; tags. To validate as proper HTML, these need to be within a &lt;form&gt; tag. This option allows you to prevent the generation of the surrounding form tag. You probably do not want to change this.'),
      '#default_value' => isset($this->settings['form']) ? $this->settings['form'] : 1,
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function prepare($text, $langcode) {
    // Fix any html style (ie, '<>' delimited) tags into our '[]'
    // style delimited tags.
    $text = preg_replace(
      '/(?<!\\\\)     # not preceded by a backslash
        <             # an open bracket
        (             # start capture
          \/?         # optional backslash
          collapse    # the string collapse
          [^>]*       # everything up to the closing angle bracket; note that you cannot use one inside the tag!
        )             # stop capture
        >             # close bracket
      /ix',
      '[$1]',
      $text
    );

    $text = preg_replace_callback(
      '/(?<!\\\\)     # not preceded by a backslash
        \[            # open bracket
        collapse      # the string collapse
        [^\]]*        # everything up to a closing straight bracket; note that you cannot use one inside a tag!
        \]            # closing bracket
      /ix',
      array($this, 'filterPrepareRegexCallback'),
      $text
    );
    return $text;
  }

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    // Retrieve the options, then look for overrides.
    $options = $this->settings;
    list($text, $options) = $this->checkOptions($text, $options);

    // Find all of the collapse tags and their location in the string.
    $tags = $this->findTags($text, $options);

    // Determine the level of nesting for each element.
    $levels = $this->findLevels($tags, $options);

    // Process the text if there are any collapse tags...
    if (count($levels)) {
      // Turn the levels and the string into a structured tree.
      $tree = $this->processRecurseLevels($text, 0, strlen($text), $levels, $options);

      // Take the tree, and turn it into FAPI elements, then embed
      // them in a form if requested.
      // Used to generate unique ids to prevent an E_NOTICE.
      static $render_number = 1;
      $holder = array();
      if ($options['form']) {
        $holder = array(
          '#type'  => 'form',
          '#theme' => 'collapse_text_form',
          '#form_id' => 'collapse-text-dynamic-form-number-' . $render_number,
          '#id' => 'collapse-text-dynamic-form-number-' . $render_number++,
        );
      }
      else {
        $holder = array(
          '#type'   => 'markup',
          '#prefix' => '<div id="' . 'collapse-text-dynamic-div-number-' . $render_number++ . '">',
          '#suffix' => '</div>',
        );
      }
      $holder['collapse_text_internal_text'] = $this->processRecurseTree($tree, $options);

      // Render the elements back to a string.
      $text = drupal_render($holder);
    }
    return new FilterProcessResult($text);
  }

  /**
   * {@inheritdoc}
   */
  public function tips($long = FALSE) {
    if ($long) {
      return t(
          '<p>You may surround a section of text with "[collapse]" and "[/collapse]" to it into a collapsible section.</p><p>You may use "[collapse]" tags within other "[collapse]" tags for nested collapsing sections.</p><p>If you start with "[collapsed]" or "[collapse collapsed]", the section will default to a collapsed state.</p><p>You may specify a title for the section in two ways. You may add a "title=" parameter to the opening tag, such as "[collapse title=&lt;your title here&gt;]". In this case, you should surround the title with double-quotes. If you need to include double-quotes in the title, use the html entity "&amp;quot;". For example: \'[collapse title="&amp;quot;Once upon a time&amp;quot;"]\'. If a title is not specified in the "[collapse]" tag, the title will be taken from the first heading found inside the section. A heading is specified using the "&lt;hX&gt;" html tag, where X is a number from 1-6. The heading will be removed from the section in order to prevent duplication. If a title is not found using these two methods, a default title will be supplied.</p><p>For advanced uses, you may also add a "class=" option to specify CSS classes to be added to the section. The CSS classes should be surrounded by double-quotes, and separated by spaces; e.g. \'[collapse class="class1 class2"]\'.</p><p>You may combine these options in (almost) any order. The "collapsed" option should always come first; things will break if it comes after "title=" or "class=". If you need to have it come after the other options, you must specify it as \'collapsed="collapsed"\'; e.g. \'[collapse title="foo" collapsed="collapsed"]\'.</p><p>If you wish to put the string "[collapse" into the document, you will need to prefix it with a backslash ("\\"). The first backslash before any instance of "[collapse" or "[/collapse" will be removed, all others will remain. Thus, if you want to display "[collapse stuff here", you should enter "\\[collapse stuff here". If you wish to display "\\[collapse other stuff", you will need to put in "\\\\[collapse other stuff". If you prefix three backslashes, two will be displayed, etc.</p><p>If you prefer, you can use angle brackets ("&lt;&gt;") instead of straight brackets ("[]"). This module will find any instance of "&lt;collapse" and change it to "[collapse" (also fixing the end of the tags and the closing tags).</p><p>You may override the settings of the filter on an individual basis using a "[collapse options ...]" tag. The possible options now are \'form="form"\' or \'form="noform"\', and \'default_title="..."\'. For example, \'[collapse options form="noform" default_title="Click me!"]\'. Only the first options tag will be looked at, and the settings apply for the entire text area, not just the "[collapse]" tags following the options tag. Note that surrounding &lt;p&gt; and &lt;br&gt; tags will be removed.</p><p>This module supports some historical variants of the tag as well. The following are <strong>not</strong> recommended for any new text, but are left in place so that old uses still work. The "class=" option used to called "style=", and "style=" will be changed into "class=". If you don\'t put a double-quote immediately after "class=", everything up to the end of the tag or the string "title=" will be interpreted as the class string. Similarly, if you don\'t have a double-quote immediately following "title=", everything up to the end of the tag will be used as the title. Note that in this format, "style=" <em>must</em> precede "title=".</p>'
      );
    }
    else {
      return t('Use [collapse] and [/collapse] to create collapsible text blocks. [collapse collapsed] or [collapsed] will start with the block closed.');
    }
  }

  /**
   * Callback function for the prepare replacement.
   *
   * Attempt to clean up poorly formatted tags.
   */
  public function filterPrepareRegexCallback($matches) {
    // All regexes here are running against an already extracted tag.
    $tag = $matches[0];

    // Allow the [collapsed] open tag.
    $tag = preg_replace(
      '/^                  # start of tag
        \[                 # open bracket
        (                  # start capture
          collapsed        # the string collapsed
          (?: |\])         # either a space or a close bracket
        )                  # end capture
      /ix',
      '[collapse $1',
      $tag
    );

    // Fix the collapsed element.
    $tag = preg_replace(
      '/^\[collapse collapsed( |\])/i',
      '[collapse collapsed="collapsed"$1',
      $tag
    );

    // Fix the style element. going forward, we prefer "class=".
    $tag = preg_replace(
      '/ style=([^"].*?)(?= collapsed=| title=|\])/i',
      ' class="$1"',
      $tag
    );
    $tag = preg_replace(
      '/ style="/i',
      ' class="',
      $tag
    );

    // Fix the title element.
    // Not sufficient if title includes double-quotes.
    $tag = preg_replace(
      '/ title=([^"].*?)(?= collapsed=| class=|\])/i',
      ' title="$1"',
      $tag
    );

    return $tag;
  }

  /**
   * Helper function to take a nested tree and turn it into a string.
   *
   * This function is recursive.
   */
  public function processRecurseTree($tree, $options) {
    $parts = array();
    // We use $weight to make sure elements are displayed in the correct order.
    $weight = 0;

    foreach ($tree as $item) {
      // Iterate over the tree.
      $part = NULL;

      if ($item['type'] == 'text') {
        $part = $this->processTextItem($item['value'], $options);
      }
      elseif ($item['type'] = 'child') {
        $part = $this->processChildItem($item, $options);
      }

      if (isset($part)) {
        $part['#weight'] = $weight++;
        $parts[] = $part;
      }
    }

    return $parts;
  }

  /**
   * Helper function to process a text item.
   */
  public function processTextItem($item, $options) {
    // Remove any leftover [collapse] or [/collapse] tags,
    // such as might be caused by the teaser.
    // Leaving out the closing tag.
    // Note that a backslash before the collapse tag will act as an escape.
    $item = preg_replace('/(?<!\\\\)\[\/?collapse[^\]]*\]/', '', $item);

    // Remove the first backslash before any collapse tags.
    // This allows collapse tags to be escaped.
    $item = str_replace(array('\\[collapse', '\\[/collapse'),
                        array('[collapse', '[/collapse'),
                        $item);

    // Clear out some miscellaneous tags that are
    // introduced by visual editors...
    // Close paragraph right at the start.
    $item = preg_replace('/^<\/p>/', '', $item);
    // Open paragraph right at the end.
    $item = preg_replace('/<p(?:\s[^>]*)?>$/', '', $item);
    // Clear out cruft introduced by the html line ending filter.
    // These are probably more controversial,
    // since they may actually be intended...
    // At the very start.
    $item = preg_replace('/^<br ?\/?>/', '', $item);
    // At the very end.
    $item = preg_replace('/<br ?\/?>$/', '', $item);
    // Only return a value if there is something besides whitespace.
    if (preg_match('/\S/', $item)) {
      return array(
        '#type'   => 'markup',
        '#markup' => $item,
        '#prefix' => '<div class="collapse-text-text">',
        '#suffix' => '</div>',
      );
    }
    else {
      return NULL;
    }
  }

  /**
   * Helper function to process a child item.
   */
  public function processChildItem($item, $options) {

    // Translate the "tag" into a proper tag,
    // and then parse it as an xml tag.
    $tag = preg_replace(array('/^\[/', '/\]$/', '/&/'), array('<', '/>', '&amp;'), $item['tag']);

    // Turn HTML entities into XML entities.
    // Issue #1109792 by eronte.
    $tag = $this->htmlToXmlEntities($tag);

    $xmltag = simplexml_load_string($tag);

    $collapsed = ($xmltag['collapsed'] == 'collapsed');
    $class     = trim($xmltag['class']);
    // Issue #1096070 by Asgardinho: issues with UTF8 text.
    $title     = htmlspecialchars(trim($xmltag['title']), ENT_QUOTES, 'UTF-8');

    // Set up the styles array.
    // We need to include the 'collapsible' and 'collapsed' classes ourself,
    // because this is no longer done by the theme system.
    $classes = array();
    $classes[] = Html::cleanCssIdentifier('collapse-text-deatils');
    $classes[] = 'collapsible';
    if ($collapsed) {
      $classes[] = 'collapsed';
    }

    // Change the style item into an array.
    foreach ((explode(' ', $class)) as $c) {
      if (!empty($c)) {
        $classes[] = Html::cleanCssIdentifier($c);
      }
    }

    // If a title is not supplied, look in the first child for a header tag.
    if (empty($title)) {
      if ($item['value'][0]['type'] == 'text') {
        $h_matches = array();
        if (preg_match('/(<h\d[^>]*>(.+?)<\/h\d>)/smi', $item['value'][0]['value'], $h_matches)) {
          $title = strip_tags($h_matches[2]);
        }

        // If we get the title from the first header tag,
        // we should remove it from the text so that it isn't repeated.
        if (!empty($title)) {
          // This is a hack to only replace the first instance.
          $occ = 1;
          $item['value'][0]['value'] = str_replace($h_matches[0], '', $item['value'][0]['value'], $occ);
        }
      }
    }

    // If still no title, put in the default title.
    if (empty($title)) {
      $title = $options['default_title'];
      $classes[] = Html::cleanCssIdentifier('collapse-text-default-title');
    }

    // Create a details element that can be themed.
    $details = array(
      '#type'        => 'details',
      '#theme'       => 'collapse_text_details',
      '#title'       => htmlspecialchars_decode($title),
      '#open'        => !$collapsed,
      '#attributes'  => array('class' => $classes),
      'collapse_text_contents' => $this->processRecurseTree($item['value'], $options),
    );
    return $details;
  }

  /**
   * Helper function to translate the flat levels array into a tree.
   *
   * This function is recursive.
   */
  public function processRecurseLevels($string, $string_start, $string_end, $elements, $options) {

    $text_start = $string_start;
    $text_length = $string_end - $string_start;
    $child_start = $string_start;
    $child_end = $string_end;
    $slice_start = -1;

    // Find the first start element.
    $elt_start_found = FALSE;
    $elt_start = 0;
    while ((!$elt_start_found) and ($elt_start < count($elements))) {
      if ($elements[$elt_start]['type'] == 'start') {
        $elt_start_found = TRUE;
      }
      else {
        $elt_start++;
      }
    }

    if ($elt_start_found) {
      // If there is an opening element,
      // set the text length to everything up to it.
      $text_length = $elements[$elt_start]['start'] - $string_start;
      $child_start = $elements[$elt_start]['end'];
      $slice_start = $elt_start + 1;
    }
    else {
      // Otherwise, return everything in this segment as a string.
      return array(array(
        'type'  => 'text',
        'value' => substr($string, $text_start, $text_length),
      ),
      );
    }

    // Find the next end element at the same level.
    $elt_end_found = FALSE;
    $elt_end = $elt_start;
    while ((!$elt_end_found) and ($elt_end < count($elements))) {
      if (($elements[$elt_end]['type'] == 'end') and ($elements[$elt_end]['level'] == $elements[$elt_start]['level'])) {
        $elt_end_found = TRUE;
      }
      else {
        $elt_end++;
      }
    }

    if ($elt_end_found) {
      $child_end = $elements[$elt_end]['start'];
      $slice_length = $elt_end - $slice_start;
    }
    else {
      // There is a matching failure.
      // Try skipping the start element...
      if (($elt_start + 1) < count($elements)) {
        return $this->processRecurseLevels($string, $string_start, $string_end, array_slice($elements, $elt_start + 1), $options);
      }
      else {
        // Fall back to just returning the string...
        // Reset the text length.
        $text_length = $string_end - $text_start;
        return array(array(
          'type' => 'text',
          'value' => substr($string, $text_start, $text_length),
        ),
        );
      }
    }

    $parts = array();

    // Add the text before the opening element.
    $parts[] = array(
      'type'  => 'text',
      'value' => substr($string, $text_start, $text_length),
    );

    // Add the child element.
    $parts[] = array(
      'type'  => 'child',
      'tag'   => $elements[$elt_start]['tag'],
      'value' => $this->processRecurseLevels($string, $child_start, $child_end, array_slice($elements, $slice_start, $slice_length), $options),
    );

    // Tail recurse (which ideally could be optimized away,
    // although it won't be...) to handle any siblings.
    $parts = array_merge($parts, $this->processRecurseLevels($string, $elements[$elt_end]['end'], $string_end, array_slice($elements, $elt_end), $options));

    // Return the result.
    return $parts;
  }

  /**
   * Helper function to determine what the nesting structure is.
   */
  public function findLevels($tags, $options) {
    $levels = array();

    $curr_level = 0;
    foreach ($tags as $item) {
      // Determine whether this is an open or close tag.
      $type = 'unknown';
      if (substr($item[0], 0, 9) == '[collapse') {
        $type = 'start';
      }
      elseif (substr($item[0], 0, 10) == '[/collapse') {
        $type = 'end';
      }

      // The level of an open tag is incremented before we save its
      // information, while the level of a close tag is decremented after.
      if ($type == 'start') {
        $curr_level++;
      }

      $levels[] = array(
        'type'  => $type,
        'tag'   => $item[0],
        'start' => $item[1],
        'end'   => $item[1] + strlen($item[0]),
        'level' => $curr_level,
      );

      if ($type == 'end') {
        $curr_level--;
      }
    }

    return $levels;
  }

  /**
   * Helper function to find all of the [collapse...] tags location.
   */
  public function findTags($text, $options) {
    $matches = array();

    $regex = '/
      (?<!\\\\)     # not proceeded by a backslash
      \[            # opening bracket
      \/?           # a closing tag?
      collapse      # the word collapse
      [^\]]*        # everything until the closing bracket
      \]            # a closing bracket
    /smx';
    preg_match_all($regex, $text, $matches, PREG_OFFSET_CAPTURE);

    return $matches[0];
  }

  /**
   * Helper function to see if there is an options tag available.
   *
   * If so, remove it from the text and set the options.
   */
  public function checkOptions($text, $options) {
    $matches = array();
    $regex_text = '
      (?<!\\\\)     # not proceeded by a backslash
      \[            # opening bracket
      collapse      # the word collapse
      \s+           # white space
      options       # the word options
      [^\]]*        # everything until the closing bracket
      \]            # a closing bracket
    ';
    if (preg_match('/' . $regex_text . '/smx', $text, $matches)) {
      $opt_tag = $matches[0];

      // Remove the "collapse" from the front of the tag,
      // baking in an "options" tag.
      $opt_tag = preg_replace('/^\[collapse /', '[', $opt_tag);
      // Change to angle brackets, so it can be parsed as XML.
      $opt_tag = preg_replace(array('/^\[/', '/\]$/'), array('<', '/>'), $opt_tag);

      // Turn HTML entities into XML entities.
      $opt_tag = $this->htmlToXmlEntities($opt_tag);

      $opt_tag = simplexml_load_string($opt_tag);

      // Form options are either 'form="form"' or 'form="noform"'.
      if ($opt_tag['form'] == 'form') {
        $options['form'] = 1;
      }
      elseif ($opt_tag['form'] == 'noform') {
        $options['form'] = 0;
      }

      if ($opt_tag['default_title']) {
        // Issue #1096070 by Asgardinho: issues with UTF8 text.
        $options['default_title'] = htmlspecialchars(trim($opt_tag['default_title']), ENT_QUOTES, 'UTF-8');
      }

      // Remove the options tag, including any miscellaneous <p>, </p>,
      // or <br> tags around it.
      $text = preg_replace('/(?:<\/?p>|<br\s*\/?>)*' . $regex_text .
        '(?:<\/?p>|<br\s*\/?>)*/smx',
        '',
        $text
      );
    }
    return array($text, $options);
  }

  /**
   * Helper function to convert html entities to xml entities.
   *
   * HTML entity lists from
   * - http://www.w3.org/TR/xhtml1/DTD/xhtml-lat1.ent
   * - http://www.w3.org/TR/xhtml1/DTD/xhtml-special.ent
   * - http://www.w3.org/TR/xhtml1/DTD/xhtml-symbol.ent.
   *
   * @todo -- rewrite to use str_replace
   */
  public function htmlToXmlEntities($text) {
    static $replace = array(
      // Latin 1.
      '&nbsp;'    => '&#160;',
      '&iexcl;'   => '&#161;',
      '&cent;'    => '&#162;',
      '&pound;'   => '&#163;',
      '&curren;'  => '&#164;',
      '&yen;'     => '&#165;',
      '&brvbar;'  => '&#166;',
      '&sect;'    => '&#167;',
      '&uml;'     => '&#168;',
      '&copy;'    => '&#169;',
      '&ordf;'    => '&#170;',
      '&laquo;'   => '&#171;',
      '&not;'     => '&#172;',
      '&shy;'     => '&#173;',
      '&reg;'     => '&#174;',
      '&macr;'    => '&#175;',
      '&deg;'     => '&#176;',
      '&plusmn;'  => '&#177;',
      '&sup2;'    => '&#178;',
      '&sup3;'    => '&#179;',
      '&acute;'   => '&#180;',
      '&micro;'   => '&#181;',
      '&para;'    => '&#182;',
      '&middot;'  => '&#183;',
      '&cedil;'   => '&#184;',
      '&sup1;'    => '&#185;',
      '&ordm;'    => '&#186;',
      '&raquo;'   => '&#187;',
      '&frac14;'  => '&#188;',
      '&frac12;'  => '&#189;',
      '&frac34;'  => '&#190;',
      '&iquest;'  => '&#191;',
      '&Agrave;'  => '&#192;',
      '&Aacute;'  => '&#193;',
      '&Acirc;'   => '&#194;',
      '&Atilde;'  => '&#195;',
      '&Auml;'    => '&#196;',
      '&Aring;'   => '&#197;',
      '&AElig;'   => '&#198;',
      '&Ccedil;'  => '&#199;',
      '&Egrave;'  => '&#200;',
      '&Eacute;'  => '&#201;',
      '&Ecirc;'   => '&#202;',
      '&Euml;'    => '&#203;',
      '&Igrave;'  => '&#204;',
      '&Iacute;'  => '&#205;',
      '&Icirc;'   => '&#206;',
      '&Iuml;'    => '&#207;',
      '&ETH;'     => '&#208;',
      '&Ntilde;'  => '&#209;',
      '&Ograve;'  => '&#210;',
      '&Oacute;'  => '&#211;',
      '&Ocirc;'   => '&#212;',
      '&Otilde;'  => '&#213;',
      '&Ouml;'    => '&#214;',
      '&times;'   => '&#215;',
      '&Oslash;'  => '&#216;',
      '&Ugrave;'  => '&#217;',
      '&Uacute;'  => '&#218;',
      '&Ucirc;'   => '&#219;',
      '&Uuml;'    => '&#220;',
      '&Yacute;'  => '&#221;',
      '&THORN;'   => '&#222;',
      '&szlig;'   => '&#223;',
      '&agrave;'  => '&#224;',
      '&aacute;'  => '&#225;',
      '&acirc;'   => '&#226;',
      '&atilde;'  => '&#227;',
      '&auml;'    => '&#228;',
      '&aring;'   => '&#229;',
      '&aelig;'   => '&#230;',
      '&ccedil;'  => '&#231;',
      '&egrave;'  => '&#232;',
      '&eacute;'  => '&#233;',
      '&ecirc;'   => '&#234;',
      '&euml;'    => '&#235;',
      '&igrave;'  => '&#236;',
      '&iacute;'  => '&#237;',
      '&icirc;'   => '&#238;',
      '&iuml;'    => '&#239;',
      '&eth;'     => '&#240;',
      '&ntilde;'  => '&#241;',
      '&ograve;'  => '&#242;',
      '&oacute;'  => '&#243;',
      '&ocirc;'   => '&#244;',
      '&otilde;'  => '&#245;',
      '&ouml;'    => '&#246;',
      '&divide;'  => '&#247;',
      '&oslash;'  => '&#248;',
      '&ugrave;'  => '&#249;',
      '&uacute;'  => '&#250;',
      '&ucirc;'   => '&#251;',
      '&uuml;'    => '&#252;',
      '&yacute;'  => '&#253;',
      '&thorn;'   => '&#254;',
      '&yuml;'    => '&#255;',

      // Special.
      '&apos;'    => '&#39;',
      '&OElig;'   => '&#338;',
      '&oelig;'   => '&#339;',
      '&Scaron;'  => '&#352;',
      '&scaron;'  => '&#353;',
      '&Yuml;'    => '&#376;',
      '&circ;'    => '&#710;',
      '&tilde;'   => '&#732;',
      '&ensp;'    => '&#8194;',
      '&emsp;'    => '&#8195;',
      '&thinsp;'  => '&#8201;',
      '&zwnj;'    => '&#8204;',
      '&zwj;'     => '&#8205;',
      '&lrm;'     => '&#8206;',
      '&rlm;'     => '&#8207;',
      '&ndash;'   => '&#8211;',
      '&mdash;'   => '&#8212;',
      '&lsquo;'   => '&#8216;',
      '&rsquo;'   => '&#8217;',
      '&sbquo;'   => '&#8218;',
      '&ldquo;'   => '&#8220;',
      '&rdquo;'   => '&#8221;',
      '&bdquo;'   => '&#8222;',
      '&dagger;'  => '&#8224;',
      '&Dagger;'  => '&#8225;',
      '&permil;'  => '&#8240;',
      '&lsaquo;'  => '&#8249;',
      '&rsaquo;'  => '&#8250;',
      '&euro;'    => '&#8364;',

      // Symbols.
      '&fnof;'    => '&#402;',
      '&Alpha;'   => '&#913;',
      '&Beta;'    => '&#914;',
      '&Gamma;'   => '&#915;',
      '&Delta;'   => '&#916;',
      '&Epsilon;' => '&#917;',
      '&Zeta;'    => '&#918;',
      '&Eta;'     => '&#919;',
      '&Theta;'   => '&#920;',
      '&Iota;'    => '&#921;',
      '&Kappa;'   => '&#922;',
      '&Lambda;'  => '&#923;',
      '&Mu;'      => '&#924;',
      '&Nu;'      => '&#925;',
      '&Xi;'      => '&#926;',
      '&Omicron;' => '&#927;',
      '&Pi;'      => '&#928;',
      '&Rho;'     => '&#929;',
      '&Sigma;'   => '&#931;',
      '&Tau;'     => '&#932;',
      '&Upsilon;' => '&#933;',
      '&Phi;'     => '&#934;',
      '&Chi;'     => '&#935;',
      '&Psi;'     => '&#936;',
      '&Omega;'   => '&#937;',
      '&alpha;'   => '&#945;',
      '&beta;'    => '&#946;',
      '&gamma;'   => '&#947;',
      '&delta;'   => '&#948;',
      '&epsilon;' => '&#949;',
      '&zeta;'    => '&#950;',
      '&eta;'     => '&#951;',
      '&theta;'   => '&#952;',
      '&iota;'    => '&#953;',
      '&kappa;'   => '&#954;',
      '&lambda;'  => '&#955;',
      '&mu;'      => '&#956;',
      '&nu;'      => '&#957;',
      '&xi;'      => '&#958;',
      '&omicron;' => '&#959;',
      '&pi;'      => '&#960;',
      '&rho;'     => '&#961;',
      '&sigmaf;'  => '&#962;',
      '&sigma;'   => '&#963;',
      '&tau;'     => '&#964;',
      '&upsilon;' => '&#965;',
      '&phi;'     => '&#966;',
      '&chi;'     => '&#967;',
      '&psi;'     => '&#968;',
      '&omega;'   => '&#969;',
      '&upsih;'   => '&#978;',
      '&piv;'     => '&#982;',
      '&bull;'    => '&#8226;',
      '&hellip;'  => '&#8230;',
      '&prime;'   => '&#8242;',
      '&Prime;'   => '&#8243;',
      '&oline;'   => '&#8254;',
      '&frasl;'   => '&#8260;',
      '&weierp;'  => '&#8472;',
      '&image;'   => '&#8465;',
      '&real;'    => '&#8476;',
      '&trade;'   => '&#8482;',
      '&alefsym;' => '&#8501;',
      '&larr;'    => '&#8592;',
      '&uarr;'    => '&#8593;',
      '&rarr;'    => '&#8594;',
      '&darr;'    => '&#8595;',
      '&harr;'    => '&#8596;',
      '&crarr;'   => '&#8629;',
      '&lArr;'    => '&#8656;',
      '&uArr;'    => '&#8657;',
      '&rArr;'    => '&#8658;',
      '&dArr;'    => '&#8659;',
      '&hArr;'    => '&#8660;',
      '&forall;'  => '&#8704;',
      '&part;'    => '&#8706;',
      '&exist;'   => '&#8707;',
      '&empty;'   => '&#8709;',
      '&nabla;'   => '&#8711;',
      '&isin;'    => '&#8712;',
      '&notin;'   => '&#8713;',
      '&ni;'      => '&#8715;',
      '&prod;'    => '&#8719;',
      '&sum;'     => '&#8721;',
      '&minus;'   => '&#8722;',
      '&lowast;'  => '&#8727;',
      '&radic;'   => '&#8730;',
      '&prop;'    => '&#8733;',
      '&infin;'   => '&#8734;',
      '&ang;'     => '&#8736;',
      '&and;'     => '&#8743;',
      '&or;'      => '&#8744;',
      '&cap;'     => '&#8745;',
      '&cup;'     => '&#8746;',
      '&int;'     => '&#8747;',
      '&there4;'  => '&#8756;',
      '&sim;'     => '&#8764;',
      '&cong;'    => '&#8773;',
      '&asymp;'   => '&#8776;',
      '&ne;'      => '&#8800;',
      '&equiv;'   => '&#8801;',
      '&le;'      => '&#8804;',
      '&ge;'      => '&#8805;',
      '&sub;'     => '&#8834;',
      '&sup;'     => '&#8835;',
      '&nsub;'    => '&#8836;',
      '&sube;'    => '&#8838;',
      '&supe;'    => '&#8839;',
      '&oplus;'   => '&#8853;',
      '&otimes;'  => '&#8855;',
      '&perp;'    => '&#8869;',
      '&sdot;'    => '&#8901;',
      '&lceil;'   => '&#8968;',
      '&rceil;'   => '&#8969;',
      '&lfloor;'  => '&#8970;',
      '&rfloor;'  => '&#8971;',
      '&lang;'    => '&#9001;',
      '&rang;'    => '&#9002;',
      '&loz;'     => '&#9674;',
      '&spades;'  => '&#9824;',
      '&clubs;'   => '&#9827;',
      '&hearts;'  => '&#9829;',
      '&diams;'   => '&#9830;',
    );
    // Only run the substitution if there is actually an entity in the tag.
    if (strpos($text, '&') !== FALSE) {
      $text = strtr($text, $replace);
    }

    return $text;
  }

}
