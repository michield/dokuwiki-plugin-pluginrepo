<?php
/**
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Hakan Sandell <sandell.hakan@gmail.com>
 */

/**
 * Class syntax_plugin_pluginrepo_news
 */
class syntax_plugin_pluginrepo_news extends DokuWiki_Syntax_Plugin {

    /**
     * will hold the repository helper plugin
     * @var $hlp helper_plugin_pluginrepo_repository
     */
    var $hlp = null;

    /**
     * Constructor. Load helper plugin
     */
    function __construct(){
        $this->hlp = plugin_load('helper', 'pluginrepo_repository');
        if(!$this->hlp) {
            msg('Loading the pluginrepo repository helper failed. Make sure the pluginrepo plugin is installed.',-1);
        }
    }

    /**
     * What kind of syntax are we?
     */
    function getType(){
        return 'substition';
    }

    /**
     * What about paragraphs?
     */
    function getPType(){
        return 'block';
    }

    /**
     * Where to sort in?
     */
    function getSort(){
        return 155;
    }

    /**
     * Connect pattern to lexer
     *
     * @param string $mode
     */
    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('----+ *pluginnews *-+\n.*?\n----+',$mode,'plugin_pluginrepo_news');
    }

    /**
     * Handle the match - parse the data
     *
     * This parsing is shared between the multiple different output/control
     * syntaxes
     *
     * @param   string       $match   The text matched by the patterns
     * @param   int          $state   The lexer state for the match
     * @param   int          $pos     The character position of the matched text
     * @param   Doku_Handler $handler The Doku_Handler object
     * @return  bool|array Return an array with all data you want to use in render, false don't add an instruction
     */
    function handle($match, $state, $pos, Doku_Handler $handler){
        return $this->hlp->parseData($match);
    }

    /**
     * Create output
     *
     * @param string          $format   output format being rendered
     * @param Doku_Renderer   $R        the current renderer object
     * @param array           $data     data created by handler() used entries:
     *          headline: headline of new block
     *          link:     link shown at the bottom of the news block
     *          linktext: text for the link
     *          style:    'sameauthor' shows extensions of the same author (only on extension page), otherwise random picked
     *        ..more see functions below
     * @return  boolean                 rendered correctly? (however, returned value is not used at the moment)
     */
    function render($format, Doku_Renderer $R, $data) {
        if($format != 'xhtml') return false;
        /** @var Doku_Renderer_xhtml $R */

        $R->doc .= '<div class="pluginrepo_news">'.NL;
        $R->doc .= '<h4>'.hsc($data['headline']).'</h4>'.NL;

        switch ($data['style']) {
            case 'sameauthor':
                $this->showSameAuthor($R, $data);
                break;
            default:
                $this->showDefault($R,$data);
        }

        if ($data['link']) {
            $R->doc .= '<p class="more">';
            $R->internallink($data['link'],$data['linktext']);
            $R->doc .= '</p>'.NL;
        }
        $R->doc .= '</div>';
        return true;
    }

    /**
     * Output html for showing a list of plugins/templates of same author
     *
     * @param Doku_Renderer_xhtml $R
     * @param array $data used entries:
     *          entries: number of plugins/templates displayed, otherwise 10
     */
    function showSameAuthor($R, $data) {
        global $ID;

        if (curNS($ID) == 'plugin') {
            $id = noNS($ID);
        } else {
            $id = curNS($ID).':'.noNS($ID);
        }

        $rel = $this->hlp->getPluginRelations($id);
        if (count($rel) == 0) {
            $R->doc .= '<p class="nothing">Can\'t find any other plugins or templates</p>'.NL;
            return;
        }

        $limit = (is_numeric($data['entries']) ? $data['entries']: 10);
        $itr = 0;
        $R->doc .= '<ul>'.NL;
        while ($itr < count($rel['sameauthor']) && $itr < $limit) {
            $R->doc .= '<li>'.$this->hlp->pluginlink($R,$rel['sameauthor'][$itr++]).'</li>'.NL;
        }
        $R->doc .= '</ul>'.NL;
    }

    /**
     * Output html for showing plugins/templates (eventually randomly)
     *
     * @param Doku_Renderer_xhtml $R
     * @param array $data used entries:
     *         entries: number of plugins/templates displayed, otherwise 1
     *         random: if 'no' the plugin/template is not selected randomly
     *         screenshot: if 'yes' a screenshot is shown
     *      and used by the filtering:
     *
     */
    function showDefault(&$R, $data) {
        $limit = (is_numeric($data['entries']) ? $data['entries']: 1);
        $plugins = $this->hlp->getPlugins($data);
        if ($data['random'] == 'no') {
            $start = 0;
        } else {
            $start = rand(0,count($plugins)-1-$limit);
        }
        for ($i = 0; $i < $limit; $i++) {
            $row = $plugins[$start+$i];
            $linkText = ucfirst(noNS($row['plugin'])).($row['type']==32?' template':' plugin');
            $R->doc .= '<p class="title">'.$this->hlp->pluginlink($R, $row['plugin'], $linkText).'</p>'.NL;
            $R->doc .= '<p class="description">'.$row['description'].'</p>'.NL;

            $val = $row['screenshot'];
            if ($val && $data['screenshot'] == 'yes') {
                $R->doc .= '<a href="'.ml($val).'" class="media screenshot" rel="lightbox">';
                $R->doc .= '<img src="'.ml($val,"w=200").'" alt="" width="200" /></a>'.NL;
            }

            $R->doc .= '<p class="author">Author: ';
            $R->emaillink($row['email'],$row['author']);
            $R->doc .= '</p>'.NL;
        }
    }

}

