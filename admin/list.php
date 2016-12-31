<?php
/**
 * Plugin : Move
 *
 * An admin Dokuwiki plugin to be able to move several pages
 * of a namespace.
 *
 * {@link admin_plugin_move_main} shows a form to move one page or one namespace
 * {@link admin_plugin_move_tree} shows a form in a tree version
 *
 * This admin plugin shows a list of page in a namespace that can be moved
 * together in an other namespace
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Nicolas GERARD <gerardnico@gmail.com>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

/**
 * Admin component of the move plugin. Provides the user interface.
 */
class admin_plugin_move_list extends DokuWiki_Admin_Plugin
{

    /** @var helper_plugin_move_plan $plan */
    protected $plan;

    public function __construct()
    {

        $this->plan = plugin_load('helper', 'move_plan');

    }

    /**
     * @param $language
     * @return string
     */
    public function getMenuText($language)
    {
        global $ID;

        $label = $this->getLang('menu_list').' ('.getNs($ID).')';
        if ($this->plan->isCommited()) $label .= ' ' . $this->getLang('inprogress');
        return $label;
        // if return false; it will not show in Admin menu
    }


    /**
     * Get the sort number that defines the position in the admin menu.
     *
     * @return int The sort number
     */
    function getMenuSort()
    {
        return 1011;
    }

    /**
     * If this admin plugin is for admins only
     *
     * @return bool false
     */
    function forAdminOnly()
    {
        return false;
    }

    /**
     * Handle the input: No-op
     *
     * Through the hidden 'page' element
     * ie $form->addHidden('page', 'move_main');
     * in the {@link html} the handle is redirected to the 'main' admin plugin
     *
     * See the handle function of {@link admin_plugin_move_main}
     */
    function handle()
    {


    }

    /**
     * Display the interface
     */
    function html()
    {
        global $ID;

        echo $this->locale_xhtml('list');

        $treelink = wl($ID, array('do' => 'admin', 'page' => 'move_tree'));
        $mainlink = wl($ID, array('do' => 'admin', 'page' => 'move_main'));
        echo '<p id="plugin_move__link">';
        printf($this->getLang('treelink'), $treelink);
        echo '<br />';
        echo '<br />';
        printf("If you want to rename a page or move a whole namespace, go to the <a href=\"%s\">main move manager</a>.", $mainlink);
        echo '</p>';

        echo '<noscript><div class="error">' . $this->getLang('noscript') . '</div></noscript>';

        echo '<div id="plugin_move__list">';

        echo '<h3>' . $this->getLang('pages_to_move') . '</h3>';


        /** @var helper_plugin_move_plan $plan */
        $plan = plugin_load('helper', 'move_plan');
        echo '<div class="controls">';
        if ($plan->isCommited()) {
            echo '<div class="error">' . $this->getLang('moveinprogress') . '</div>';
            printf("Go to the <a href=\"%s\">simple move manager</a> to abort it.", $mainlink);
        } else {
            $form = new Doku_Form(array('action' => wl($ID), 'id' => 'plugin_move__list_execute'));
            $form->addHidden('id', $ID);

            // Redirect the handle function to {@link admin_plugin_move_main} page
            $form->addHidden('page', 'move_main');

            // List
            $form->addHidden('list', '');

            $nameSpacePath = getNS($ID); // The complete path to the directory
            $pagesOfNamespace = $this->getNamespaceChildren($nameSpacePath);
            foreach ($pagesOfNamespace as $page) {

                $pageId = $page['id'];

                // Title
                if (useHeading('navigation')) {
                    // get page title
                    $title = $page['title'];

                }

                // If the page has no title
                // or we don't use navigation
                if (!$title) {
                    $title = noNSorNS($pageId);
                }

                // Checked or not
                if ($ID == $pageId) {
                    $checked = array('checked' => 'checked');
                } else {
                    $checked = array();
                }
                $url = tpl_link(
                    wl($pageId),
                    ucfirst($title), // First letter upper case
                    'title="' . $title . '"',
                    $return = true
                );
                $form->addElement(form_makeCheckboxField('pages[]', $pageId, $url, $pageId, '', $checked));
                $form->addElement('<br />');
            }
            $form->addElement('<br />');

            $form->addElement('<h3>' . $this->getLang('namespace_destination') . '</h3>');
            $form->addElement(form_makeTextField('namespace_destination', $nameSpacePath, $this->getLang('namespace_destination_new'), '', 'indent'));
            $form->addElement('<br />');
            $form->addElement('<br />');

            $form->addElement('<h3>Options' . $this->getLang('options') . '</h3>');
            $form->addElement(form_makeMenuField('type', array('pages' => $this->getLang('move_pages'), 'media' => $this->getLang('move_media'), 'both' => $this->getLang('move_media_and_pages')), 'both', $this->getLang('content_to_move'), '', 'indent select'));
            $form->addElement('<br />');
            $form->addElement(form_makeCheckboxField('autoskip', '1', $this->getLang('autoskip'), '', '', ($this->getConf('autoskip') ? array('checked' => 'checked') : array())));
            $form->addElement('<br />');
            $form->addElement(form_makeCheckboxField('autorewrite', '1', $this->getLang('autorewrite'), '', '', ($this->getConf('autorewrite') ? array('checked' => 'checked') : array())));
            $form->addElement('<br />');

            $form->addElement('<br />');
            $form->addElement(form_makeButton('submit', 'admin', $this->getLang('btn_start')));
            $form->printForm();
        }
        echo '</div>';

        echo '</div>';

    }


    /**
     * Return all pages of a namespace (ie directory)
     * Adapted from feed.php and copied from the minimap plugin (syntax_plugin_minimap_minisyntax class)
     *
     * @param $namespace The container of the pages
     * @return array An array of the pages for the namespace
     */
    protected function getNamespaceChildren($namespace)
    {
        require_once(DOKU_INC . 'inc/search.php');
        global $conf;

        $ns = ':' . cleanID($namespace);
        // ns as a path
        $ns = utf8_encodeFN(str_replace(':', '/', $ns));

        $data = array();

        // Options of the callback function search_universal
        // in the search.php file
        $search_opts = array(
            'depth' => 1,
            'pagesonly' => true,
            'listfiles' => true,
            'listdirs' => false,
            'firsthead' => true
        );
        // search_universal is a function in inc/search.php that accepts the $search_opts parameters
        search($data, $conf['datadir'], 'search_universal', $search_opts, $ns, $lvl = 1, $sort = 'natural');

        return $data;
    }

    /**
     * searches media files linked in the given page
     * returns an array of media id's
     *
     * Adapted From https://github.com/ssahara/dw-plugin-medialist/blob/master/helper.php
     *
     */
    static function getMediasIdFromPage($id) {
        $medias = array();

        if (auth_quickaclcheck($id) >= AUTH_READ) {
            // get the instructions
            $ins = p_cached_instructions(wikiFN($id), true, $id);
            // get linked media files
            foreach ($ins as $node) {
                if ($node[0] == 'internalmedia') {
                    $id = cleanID($node[1][0]);
                    $fn = mediaFN($id);
                    if (!file_exists($fn)) continue;
                    $medias[] = $id;
                }
            }
        }
        return array_unique($medias, SORT_REGULAR);
    }
}
